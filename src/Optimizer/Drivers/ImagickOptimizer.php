<?php
/**
 * ImageMagick Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using ImageMagick (Imagick PHP extension)
 */
final class ImagickOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['jpeg', 'png', 'gif', 'webp', 'avif'];
    protected int $priority = 20; // Higher than GD

    public function getId(): string
    {
        return 'imagick';
    }

    public function getName(): string
    {
        return 'ImageMagick';
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $this->available = extension_loaded('imagick') && class_exists('\Imagick');

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $imagick = new \Imagick();
            $version = $imagick->getVersion();
            return $version['versionString'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        $error = $this->validateSourceFile($sourcePath);
        if ($error !== null) {
            return OptimizerResult::failure($error, $this->getId());
        }

        if (!$this->ensureDirectoryExists($destinationPath)) {
            return OptimizerResult::failure('Cannot create destination directory', $this->getId());
        }

        $originalSize = $this->getFileSize($sourcePath);
        $format = $this->getFormatFromPath($sourcePath);

        if (!$this->supportsFormat($format)) {
            return OptimizerResult::failure("Format not supported: {$format}", $this->getId(), $originalSize);
        }

        // Use temp file to protect original from corruption if optimization fails
        // This is critical for in-place optimization (sourcePath === destinationPath)
        $tempPath = wp_tempnam('imagick_opt_');
        if (!$tempPath) {
            return OptimizerResult::failure('Failed to create temp file', $this->getId(), $originalSize);
        }

        try {
            $imagick = new \Imagick($sourcePath);

            $result = match ($format) {
                'jpeg' => $this->optimizeJpeg($imagick, $options),
                'png' => $this->optimizePng($imagick, $options),
                'gif' => $this->optimizeGif($imagick, $options),
                'webp' => $this->optimizeWebp($imagick, $options),
                'avif' => $this->optimizeAvif($imagick, $options),
                default => ['success' => false, 'error' => "Unsupported format: {$format}"],
            };

            if (!$result['success']) {
                $imagick->destroy();
                @unlink($tempPath);
                return OptimizerResult::failure($result['error'] ?? 'Optimization failed', $this->getId(), $originalSize);
            }

            // Write to temp file first (protects original from corruption)
            $imagick->writeImage($tempPath);
            $imagick->destroy();

            // Verify temp file is valid before replacing original
            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                @unlink($tempPath);
                return OptimizerResult::failure('Optimization produced empty or invalid file', $this->getId(), $originalSize);
            }

            // Atomically move temp file to destination
            if (!rename($tempPath, $destinationPath)) {
                // Fallback to copy + delete if rename fails (cross-filesystem)
                if (!copy($tempPath, $destinationPath)) {
                    @unlink($tempPath);
                    return OptimizerResult::failure('Failed to save optimized file', $this->getId(), $originalSize);
                }
                @unlink($tempPath);
            }

            $optimizedSize = $this->getFileSize($destinationPath);

            return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());

        } catch (\ImagickException $e) {
            @unlink($tempPath);
            return OptimizerResult::failure('ImageMagick error: ' . $e->getMessage(), $this->getId(), $originalSize);
        }
    }

    /**
     * Optimize JPEG image
     */
    private function optimizeJpeg(\Imagick $imagick, array $options): array
    {
        $quality = $options['quality'] ?? $options['jpeg_quality'] ?? 82;
        $stripMetadata = $options['strip_metadata'] ?? true;

        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $imagick->setImageCompressionQuality($quality);
        $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE); // Progressive JPEG

        if ($stripMetadata) {
            $imagick->stripImage();
        }

        // Optimize sampling factor for better compression
        $imagick->setSamplingFactors(['2x2', '1x1', '1x1']);

        return ['success' => true];
    }

    /**
     * Optimize PNG image
     */
    private function optimizePng(\Imagick $imagick, array $options): array
    {
        $compression = $options['compression'] ?? $options['png_compression'] ?? 6;
        $stripMetadata = $options['strip_metadata'] ?? true;

        $imagick->setImageFormat('png');
        $imagick->setImageCompression(\Imagick::COMPRESSION_ZIP);
        $imagick->setImageCompressionQuality($compression * 10); // 0-90 for PNG

        if ($stripMetadata) {
            $imagick->stripImage();
        }

        return ['success' => true];
    }

    /**
     * Optimize GIF image
     */
    private function optimizeGif(\Imagick $imagick, array $options): array
    {
        // Check for animated GIF
        if ($imagick->getNumberImages() > 1) {
            // Animated GIF - optimize each frame
            $imagick = $imagick->coalesceImages();
            
            foreach ($imagick as $frame) {
                $frame->setImageCompression(\Imagick::COMPRESSION_LZW);
            }
            
            $imagick = $imagick->deconstructImages();
        } else {
            $imagick->setImageFormat('gif');
            $imagick->setImageCompression(\Imagick::COMPRESSION_LZW);
        }

        return ['success' => true];
    }

    /**
     * Optimize WebP image
     */
    private function optimizeWebp(\Imagick $imagick, array $options): array
    {
        if (!in_array('WEBP', $imagick->queryFormats('WEBP'), true)) {
            return ['success' => false, 'error' => 'WebP not supported by this ImageMagick installation'];
        }

        $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;
        $stripMetadata = $options['strip_metadata'] ?? true;

        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality($quality);

        if ($stripMetadata) {
            $imagick->stripImage();
        }

        return ['success' => true];
    }

    /**
     * Optimize AVIF image
     */
    private function optimizeAvif(\Imagick $imagick, array $options): array
    {
        if (!in_array('AVIF', $imagick->queryFormats('AVIF'), true)) {
            return ['success' => false, 'error' => 'AVIF not supported by this ImageMagick installation'];
        }

        $quality = $options['quality'] ?? $options['avif_quality'] ?? 50;
        $stripMetadata = $options['strip_metadata'] ?? true;

        $imagick->setImageFormat('avif');
        $imagick->setImageCompressionQuality($quality);

        if ($stripMetadata) {
            $imagick->stripImage();
        }

        return ['success' => true];
    }

    /**
     * Check if a format is supported by this ImageMagick installation
     */
    public function supportsFormat(string $format): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $format = strtoupper($format === 'jpeg' ? 'JPEG' : $format);

        try {
            $imagick = new \Imagick();
            $supported = $imagick->queryFormats($format);
            return !empty($supported);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getInstallInstructions(): string
    {
        return __('Install php-imagick package. On Ubuntu/Debian: sudo apt install php-imagick', 'media-toolkit');
    }
}

