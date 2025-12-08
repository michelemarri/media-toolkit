<?php
/**
 * WebP Converter
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Converters;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Optimizer\OptimizerManager;

/**
 * Converts images to WebP format
 */
final class WebPConverter implements ConverterInterface
{
    private ?Logger $logger;
    private OptimizerManager $optimizerManager;
    private ?bool $available = null;

    public function __construct(OptimizerManager $optimizerManager, ?Logger $logger = null)
    {
        $this->optimizerManager = $optimizerManager;
        $this->logger = $logger;
    }

    public function getId(): string
    {
        return 'webp_converter';
    }

    public function getName(): string
    {
        return 'WebP Converter';
    }

    public function getTargetFormat(): string
    {
        return 'webp';
    }

    public function getSupportedSourceFormats(): array
    {
        return ['jpeg', 'png', 'gif'];
    }

    public function supportsSourceFormat(string $format): bool
    {
        $format = strtolower($format);
        if ($format === 'jpg') {
            $format = 'jpeg';
        }
        return in_array($format, $this->getSupportedSourceFormats(), true);
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        // Check if we have cwebp or ImageMagick with WebP support
        $cwebp = $this->optimizerManager->getOptimizer('cwebp');
        if ($cwebp !== null && $cwebp->isAvailable()) {
            $this->available = true;
            return true;
        }

        $imagick = $this->optimizerManager->getOptimizer('imagick');
        if ($imagick !== null && $imagick->isAvailable() && $imagick->supportsFormat('webp')) {
            $this->available = true;
            return true;
        }

        // Check GD with WebP support
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $this->available = true;
            return true;
        }

        $this->available = false;
        return false;
    }

    public function convert(string $sourcePath, ?string $destinationPath = null, array $options = []): ConversionResult
    {
        $sourceFormat = $this->getFormatFromPath($sourcePath);
        
        if (!$this->supportsSourceFormat($sourceFormat)) {
            return ConversionResult::failure(
                "Source format not supported: {$sourceFormat}",
                $sourceFormat,
                'webp',
                $this->getId()
            );
        }

        if (!file_exists($sourcePath)) {
            return ConversionResult::failure(
                'Source file does not exist',
                $sourceFormat,
                'webp',
                $this->getId()
            );
        }

        $originalSize = filesize($sourcePath);
        if ($originalSize === false) {
            $originalSize = 0;
        }

        // Generate destination path if not provided
        if ($destinationPath === null) {
            $destinationPath = $this->generateOutputPath($sourcePath);
        }

        // Ensure destination directory exists
        $destDir = dirname($destinationPath);
        if (!is_dir($destDir)) {
            wp_mkdir_p($destDir);
        }

        // Try conversion methods in order of preference
        $result = $this->convertWithCwebp($sourcePath, $destinationPath, $options);
        
        if (!$result['success']) {
            $result = $this->convertWithImagick($sourcePath, $destinationPath, $options);
        }

        if (!$result['success']) {
            $result = $this->convertWithGD($sourcePath, $destinationPath, $options);
        }

        if (!$result['success']) {
            return ConversionResult::failure(
                $result['error'] ?? 'All conversion methods failed',
                $sourceFormat,
                'webp',
                $this->getId(),
                $originalSize
            );
        }

        $convertedSize = filesize($destinationPath);
        if ($convertedSize === false) {
            $convertedSize = 0;
        }

        $this->log('success', "Converted to WebP: {$originalSize} -> {$convertedSize}");

        return ConversionResult::success(
            $destinationPath,
            $originalSize,
            $convertedSize,
            $sourceFormat,
            'webp',
            $this->getId()
        );
    }

    /**
     * Convert using cwebp CLI
     */
    private function convertWithCwebp(string $source, string $destination, array $options): array
    {
        $cwebp = $this->optimizerManager->getOptimizer('cwebp');
        if ($cwebp === null || !$cwebp->isAvailable()) {
            return ['success' => false, 'error' => 'cwebp not available'];
        }

        $binary = $cwebp->getBinaryPath();
        if ($binary === null) {
            return ['success' => false, 'error' => 'cwebp binary not found'];
        }

        $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;

        $args = [
            '-q', (string) $quality,
            '-m', '4',
            '-af',
            '-mt',
            '-quiet',
            '-o', $destination,
            $source,
        ];

        $escapedArgs = array_map('escapeshellarg', $args);
        $command = escapeshellarg($binary) . ' ' . implode(' ', $escapedArgs) . ' 2>&1';

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($destination)) {
            return ['success' => false, 'error' => implode("\n", $output)];
        }

        return ['success' => true];
    }

    /**
     * Convert using ImageMagick
     */
    private function convertWithImagick(string $source, string $destination, array $options): array
    {
        if (!extension_loaded('imagick') || !class_exists('\Imagick')) {
            return ['success' => false, 'error' => 'Imagick not available'];
        }

        try {
            $imagick = new \Imagick($source);
            
            if (!in_array('WEBP', $imagick->queryFormats('WEBP'), true)) {
                $imagick->destroy();
                return ['success' => false, 'error' => 'WebP not supported by ImageMagick'];
            }

            $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);
            
            if ($options['strip_metadata'] ?? true) {
                $imagick->stripImage();
            }

            $imagick->writeImage($destination);
            $imagick->destroy();

            return ['success' => true];

        } catch (\ImagickException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert using GD
     */
    private function convertWithGD(string $source, string $destination, array $options): array
    {
        if (!function_exists('imagewebp')) {
            return ['success' => false, 'error' => 'GD WebP support not available'];
        }

        $sourceFormat = $this->getFormatFromPath($source);
        
        $image = match ($sourceFormat) {
            'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
            'gif' => @imagecreatefromgif($source),
            default => false,
        };

        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to read source image'];
        }

        // Preserve transparency for PNG
        if ($sourceFormat === 'png') {
            imagesavealpha($image, true);
            imagealphablending($image, false);
        }

        $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;
        $success = imagewebp($image, $destination, $quality);
        imagedestroy($image);

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to save WebP image'];
        }

        return ['success' => true];
    }

    /**
     * Generate output path with .webp extension
     */
    private function generateOutputPath(string $sourcePath): string
    {
        $pathInfo = pathinfo($sourcePath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    }

    /**
     * Get format from file path
     */
    private function getFormatFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $extension === 'jpg' ? 'jpeg' : $extension;
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        match ($level) {
            'success' => $this->logger->success('webp_converter', $message),
            'error' => $this->logger->error('webp_converter', $message),
            default => $this->logger->info('webp_converter', $message),
        };
    }
}

