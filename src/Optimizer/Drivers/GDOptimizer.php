<?php
/**
 * GD Library Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using PHP GD library (built-in fallback)
 */
final class GDOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['jpeg', 'png', 'gif', 'webp'];
    protected int $priority = 10; // Lowest priority (fallback)

    public function getId(): string
    {
        return 'gd';
    }

    public function getName(): string
    {
        return 'GD Library';
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $this->available = extension_loaded('gd') && function_exists('imagecreatefromjpeg');

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $info = gd_info();
        return $info['GD Version'] ?? null;
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

        $result = match ($format) {
            'jpeg' => $this->optimizeJpeg($sourcePath, $destinationPath, $options),
            'png' => $this->optimizePng($sourcePath, $destinationPath, $options),
            'gif' => $this->optimizeGif($sourcePath, $destinationPath, $options),
            'webp' => $this->optimizeWebp($sourcePath, $destinationPath, $options),
            default => ['success' => false, 'error' => "Unsupported format: {$format}"],
        };

        if (!$result['success']) {
            return OptimizerResult::failure($result['error'] ?? 'Optimization failed', $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Safely write to destination using temp file (protects original from corruption)
     */
    private function safeWriteImage(callable $writeFunc, string $destination): array
    {
        // Use temp file to protect original from corruption
        $tempPath = wp_tempnam('gd_opt_');
        if (!$tempPath) {
            return ['success' => false, 'error' => 'Failed to create temp file'];
        }

        $success = $writeFunc($tempPath);

        if (!$success) {
            @unlink($tempPath);
            return ['success' => false, 'error' => 'Failed to write image'];
        }

        // Verify temp file is valid
        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);
            return ['success' => false, 'error' => 'Optimization produced empty or invalid file'];
        }

        // Atomically move temp file to destination
        if (!rename($tempPath, $destination)) {
            // Fallback to copy + delete if rename fails
            if (!copy($tempPath, $destination)) {
                @unlink($tempPath);
                return ['success' => false, 'error' => 'Failed to save optimized file'];
            }
            @unlink($tempPath);
        }

        return ['success' => true];
    }

    /**
     * Optimize JPEG image
     */
    private function optimizeJpeg(string $source, string $destination, array $options): array
    {
        $quality = $options['quality'] ?? $options['jpeg_quality'] ?? 82;

        $image = @imagecreatefromjpeg($source);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read JPEG image'];
        }

        $result = $this->safeWriteImage(
            fn($tempPath) => imagejpeg($image, $tempPath, $quality),
            $destination
        );
        
        imagedestroy($image);
        return $result;
    }

    /**
     * Optimize PNG image
     */
    private function optimizePng(string $source, string $destination, array $options): array
    {
        $compression = $options['compression'] ?? $options['png_compression'] ?? 6;

        $image = @imagecreatefrompng($source);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read PNG image'];
        }

        // Preserve transparency
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $result = $this->safeWriteImage(
            fn($tempPath) => imagepng($image, $tempPath, $compression),
            $destination
        );
        
        imagedestroy($image);
        return $result;
    }

    /**
     * Optimize GIF image
     */
    private function optimizeGif(string $source, string $destination, array $options): array
    {
        // Check for animated GIF
        if ($this->isAnimatedGif($source)) {
            // GD cannot handle animated GIFs properly, just copy
            if ($source !== $destination) {
                copy($source, $destination);
            }
            return ['success' => true, 'skipped' => true];
        }

        $image = @imagecreatefromgif($source);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read GIF image'];
        }

        $result = $this->safeWriteImage(
            fn($tempPath) => imagegif($image, $tempPath),
            $destination
        );
        
        imagedestroy($image);
        return $result;
    }

    /**
     * Optimize WebP image
     */
    private function optimizeWebp(string $source, string $destination, array $options): array
    {
        if (!function_exists('imagecreatefromwebp')) {
            return ['success' => false, 'error' => 'WebP support not available in GD'];
        }

        $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;

        $image = @imagecreatefromwebp($source);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read WebP image'];
        }

        // Preserve transparency
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $result = $this->safeWriteImage(
            fn($tempPath) => imagewebp($image, $tempPath, $quality),
            $destination
        );
        
        imagedestroy($image);
        return $result;
    }

    /**
     * Check if GIF is animated
     */
    private function isAnimatedGif(string $path): bool
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $count = preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $content);
        return $count > 1;
    }

    public function getInstallInstructions(): string
    {
        return __('GD Library is usually pre-installed with PHP. If missing, install php-gd package.', 'media-toolkit');
    }
}

