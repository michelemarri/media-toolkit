<?php
/**
 * AVIF Converter
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Converters;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Optimizer\OptimizerManager;

/**
 * Converts images to AVIF format
 */
final class AvifConverter implements ConverterInterface
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
        return 'avif_converter';
    }

    public function getName(): string
    {
        return 'AVIF Converter';
    }

    public function getTargetFormat(): string
    {
        return 'avif';
    }

    public function getSupportedSourceFormats(): array
    {
        return ['jpeg', 'png'];
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

        // Check if we have avifenc
        $avifenc = $this->optimizerManager->getOptimizer('avifenc');
        if ($avifenc !== null && $avifenc->isAvailable()) {
            $this->available = true;
            return true;
        }

        // Check ImageMagick with AVIF support
        $imagick = $this->optimizerManager->getOptimizer('imagick');
        if ($imagick !== null && $imagick->isAvailable() && $imagick->supportsFormat('avif')) {
            $this->available = true;
            return true;
        }

        // Check GD with AVIF support (PHP 8.1+)
        if (extension_loaded('gd') && function_exists('imageavif')) {
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
                'avif',
                $this->getId()
            );
        }

        if (!file_exists($sourcePath)) {
            return ConversionResult::failure(
                'Source file does not exist',
                $sourceFormat,
                'avif',
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
        $result = $this->convertWithAvifenc($sourcePath, $destinationPath, $options);
        
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
                'avif',
                $this->getId(),
                $originalSize
            );
        }

        $convertedSize = filesize($destinationPath);
        if ($convertedSize === false) {
            $convertedSize = 0;
        }

        $this->log('success', "Converted to AVIF: {$originalSize} -> {$convertedSize}");

        return ConversionResult::success(
            $destinationPath,
            $originalSize,
            $convertedSize,
            $sourceFormat,
            'avif',
            $this->getId()
        );
    }

    /**
     * Convert using avifenc CLI
     * 
     * avifenc requires PNG or Y4M input, so for JPEG we need to convert first
     */
    private function convertWithAvifenc(string $source, string $destination, array $options): array
    {
        $avifenc = $this->optimizerManager->getOptimizer('avifenc');
        if ($avifenc === null || !$avifenc->isAvailable()) {
            return ['success' => false, 'error' => 'avifenc not available'];
        }

        $binary = $avifenc->getBinaryPath();
        if ($binary === null) {
            return ['success' => false, 'error' => 'avifenc binary not found'];
        }

        $sourceFormat = $this->getFormatFromPath($source);
        $tempPng = null;

        // avifenc works best with PNG input
        if ($sourceFormat === 'jpeg') {
            // Convert JPEG to PNG first
            $tempPng = wp_tempnam('avif_convert_') . '.png';
            if (!$this->convertJpegToPng($source, $tempPng)) {
                @unlink($tempPng);
                return ['success' => false, 'error' => 'Failed to prepare image for AVIF conversion'];
            }
            $source = $tempPng;
        }

        // Quality mapping (0-100 to 0-63, inverted)
        $quality = $options['quality'] ?? $options['avif_quality'] ?? 50;
        $avifQuality = (int) round((100 - $quality) * 63 / 100);
        $avifQuality = max(0, min(63, $avifQuality));

        $args = [
            '--min', (string) max(0, $avifQuality - 10),
            '--max', (string) $avifQuality,
            '--speed', '6',
            '--jobs', 'all',
            '--depth', '8',
            '--yuv', '420',
            '--overwrite',
            $source,
            $destination,
        ];

        $escapedArgs = array_map('escapeshellarg', $args);
        $command = escapeshellarg($binary) . ' ' . implode(' ', $escapedArgs) . ' 2>&1';

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Clean up temp file
        if ($tempPng !== null) {
            @unlink($tempPng);
        }

        if ($returnCode !== 0 || !file_exists($destination)) {
            return ['success' => false, 'error' => implode("\n", $output)];
        }

        return ['success' => true];
    }

    /**
     * Convert JPEG to PNG for avifenc
     */
    private function convertJpegToPng(string $jpegPath, string $pngPath): bool
    {
        if (extension_loaded('imagick') && class_exists('\Imagick')) {
            try {
                $imagick = new \Imagick($jpegPath);
                $imagick->setImageFormat('png');
                $imagick->writeImage($pngPath);
                $imagick->destroy();
                return true;
            } catch (\Throwable $e) {
                // Fall through to GD
            }
        }

        if (function_exists('imagecreatefromjpeg') && function_exists('imagepng')) {
            $image = @imagecreatefromjpeg($jpegPath);
            if ($image !== false) {
                $result = imagepng($image, $pngPath);
                imagedestroy($image);
                return $result;
            }
        }

        return false;
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
            
            if (!in_array('AVIF', $imagick->queryFormats('AVIF'), true)) {
                $imagick->destroy();
                return ['success' => false, 'error' => 'AVIF not supported by ImageMagick'];
            }

            $quality = $options['quality'] ?? $options['avif_quality'] ?? 50;

            $imagick->setImageFormat('avif');
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
     * Convert using GD (PHP 8.1+)
     */
    private function convertWithGD(string $source, string $destination, array $options): array
    {
        if (!function_exists('imageavif')) {
            return ['success' => false, 'error' => 'GD AVIF support not available (requires PHP 8.1+)'];
        }

        $sourceFormat = $this->getFormatFromPath($source);
        
        $image = match ($sourceFormat) {
            'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
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

        $quality = $options['quality'] ?? $options['avif_quality'] ?? 50;
        
        // GD's imageavif quality is -1 to 100
        $success = imageavif($image, $destination, $quality);
        imagedestroy($image);

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to save AVIF image'];
        }

        return ['success' => true];
    }

    /**
     * Generate output path with .avif extension
     */
    private function generateOutputPath(string $sourcePath): string
    {
        $pathInfo = pathinfo($sourcePath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.avif';
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
            'success' => $this->logger->success('avif_converter', $message),
            'error' => $this->logger->error('avif_converter', $message),
            default => $this->logger->info('avif_converter', $message),
        };
    }
}

