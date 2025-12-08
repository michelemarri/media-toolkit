<?php
/**
 * MozJPEG Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using MozJPEG (cjpeg) CLI tool
 * Best JPEG compression quality/size ratio
 */
final class MozjpegOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['jpeg'];
    protected int $priority = 80; // Highest priority for JPEG

    public function getId(): string
    {
        return 'mozjpeg';
    }

    public function getName(): string
    {
        return 'MozJPEG';
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (!$this->canExecuteCommands()) {
            $this->available = false;
            return false;
        }

        // MozJPEG provides cjpeg and jpegtran binaries
        // Try to find mozjpeg-specific paths first, then fall back to standard cjpeg
        $possiblePaths = [
            '/opt/mozjpeg/bin/cjpeg',
            '/usr/local/opt/mozjpeg/bin/cjpeg',
            '/usr/local/bin/cjpeg',
            '/usr/bin/cjpeg',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                // Verify it's actually MozJPEG
                $result = $this->executeCommand($path, ['-version']);
                if (strpos($result['output'], 'mozjpeg') !== false || strpos($result['output'], 'MozJPEG') !== false) {
                    $this->binaryPath = $path;
                    $this->available = true;
                    return true;
                }
            }
        }

        // Fall back to generic cjpeg and check if it's mozjpeg
        $cjpeg = $this->findBinary('cjpeg');
        if ($cjpeg !== null) {
            $result = $this->executeCommand($cjpeg, ['-version']);
            if (strpos(strtolower($result['output']), 'mozjpeg') !== false) {
                $this->binaryPath = $cjpeg;
                $this->available = true;
                return true;
            }
        }

        $this->available = false;
        return false;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['-version']);
        
        // mozjpeg version 4.1.1
        if (preg_match('/mozjpeg\s+version\s+(\d+\.\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        // libjpeg-turbo version X.X.X (mozjpeg variant)
        if (preg_match('/(\d+\.\d+\.\d+)/', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('MozJPEG is not available', $this->getId());
        }

        $error = $this->validateSourceFile($sourcePath);
        if ($error !== null) {
            return OptimizerResult::failure($error, $this->getId());
        }

        if (!$this->ensureDirectoryExists($destinationPath)) {
            return OptimizerResult::failure('Cannot create destination directory', $this->getId());
        }

        $originalSize = $this->getFileSize($sourcePath);

        // MozJPEG cjpeg requires input from a BMP/PPM/TARGA file or stdin
        // We need to first decompress the JPEG, then recompress with MozJPEG
        // Alternative: use jpegtran for lossless optimization
        
        // Check if we have djpeg for decompression
        $djpeg = $this->findDjpeg();
        
        if ($djpeg !== null) {
            // Full recompression path (best results)
            return $this->optimizeWithRecompression($sourcePath, $destinationPath, $options, $djpeg, $originalSize);
        }

        // Try jpegtran for lossless optimization
        $jpegtran = $this->findJpegtran();
        if ($jpegtran !== null) {
            return $this->optimizeWithJpegtran($sourcePath, $destinationPath, $options, $jpegtran, $originalSize);
        }

        return OptimizerResult::failure('Neither djpeg nor jpegtran found for MozJPEG optimization', $this->getId(), $originalSize);
    }

    /**
     * Find djpeg binary (MozJPEG version preferred)
     */
    private function findDjpeg(): ?string
    {
        $possiblePaths = [
            '/opt/mozjpeg/bin/djpeg',
            '/usr/local/opt/mozjpeg/bin/djpeg',
            '/usr/local/bin/djpeg',
            '/usr/bin/djpeg',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return $this->findBinary('djpeg');
    }

    /**
     * Find jpegtran binary (MozJPEG version preferred)
     */
    private function findJpegtran(): ?string
    {
        $possiblePaths = [
            '/opt/mozjpeg/bin/jpegtran',
            '/usr/local/opt/mozjpeg/bin/jpegtran',
            '/usr/local/bin/jpegtran',
            '/usr/bin/jpegtran',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return $this->findBinary('jpegtran');
    }

    /**
     * Optimize with full recompression (djpeg | cjpeg)
     */
    private function optimizeWithRecompression(string $sourcePath, string $destinationPath, array $options, string $djpeg, int $originalSize): OptimizerResult
    {
        $quality = $options['quality'] ?? $options['jpeg_quality'] ?? 82;
        $progressive = ($options['progressive'] ?? true) ? '-progressive' : '';

        // Create temp file for output
        $tempFile = wp_tempnam('mozjpeg_');
        if (!$tempFile) {
            return OptimizerResult::failure('Failed to create temp file', $this->getId(), $originalSize);
        }

        // Build pipeline: djpeg -> cjpeg
        $command = sprintf(
            '%s %s | %s -quality %d %s -outfile %s',
            escapeshellarg($djpeg),
            escapeshellarg($sourcePath),
            escapeshellarg($this->binaryPath),
            $quality,
            $progressive,
            escapeshellarg($tempFile)
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempFile) || filesize($tempFile) === 0) {
            @unlink($tempFile);
            return OptimizerResult::failure('MozJPEG recompression failed: ' . implode("\n", $output), $this->getId(), $originalSize);
        }

        // Move to destination
        if (!rename($tempFile, $destinationPath)) {
            @unlink($tempFile);
            return OptimizerResult::failure('Failed to move optimized file', $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "MozJPEG optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Optimize with jpegtran (lossless)
     */
    private function optimizeWithJpegtran(string $sourcePath, string $destinationPath, array $options, string $jpegtran, int $originalSize): OptimizerResult
    {
        $args = ['-copy', 'none']; // Strip metadata

        if ($options['progressive'] ?? true) {
            $args[] = '-progressive';
        }

        $args[] = '-optimize';
        $args[] = '-outfile';
        $args[] = $destinationPath;
        $args[] = $sourcePath;

        $result = $this->executeCommand($jpegtran, $args);

        if ($result['return_code'] !== 0) {
            return OptimizerResult::failure('jpegtran failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install MozJPEG:\n' .
            '• Ubuntu/Debian: Download from https://github.com/mozilla/mozjpeg/releases\n' .
            '• macOS: brew install mozjpeg\n' .
            '• Docker: Available as mozjpeg image',
            'media-toolkit'
        );
    }
}

