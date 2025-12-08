<?php
/**
 * OptiPNG Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using OptiPNG CLI tool
 * Lossless PNG optimization (common and widely available)
 */
final class OptipngOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['png'];
    protected int $priority = 40; // Lower than pngquant (lossless)

    public function getId(): string
    {
        return 'optipng';
    }

    public function getName(): string
    {
        return 'OptiPNG';
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

        $this->binaryPath = $this->findBinary('optipng');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        // OptiPNG version 0.7.7
        if (preg_match('/OptiPNG\s+version\s+(\d+\.\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('OptiPNG is not available', $this->getId());
        }

        $error = $this->validateSourceFile($sourcePath);
        if ($error !== null) {
            return OptimizerResult::failure($error, $this->getId());
        }

        if (!$this->ensureDirectoryExists($destinationPath)) {
            return OptimizerResult::failure('Cannot create destination directory', $this->getId());
        }

        $originalSize = $this->getFileSize($sourcePath);

        // If source and destination are different, copy first
        if ($sourcePath !== $destinationPath) {
            if (!copy($sourcePath, $destinationPath)) {
                return OptimizerResult::failure('Failed to copy source file', $this->getId(), $originalSize);
            }
        }

        // Build command arguments
        $args = $this->buildArguments($options);
        $args[] = $destinationPath;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            if ($sourcePath !== $destinationPath) {
                @unlink($destinationPath);
            }
            return OptimizerResult::failure('OptiPNG failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "OptiPNG optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Optimization level (0-7, higher = slower but better)
        $level = $options['level'] ?? $options['png_compression'] ?? 2;
        $level = max(0, min(7, $level)); // Clamp to 0-7
        $args[] = "-o{$level}";

        // Strip metadata
        if ($options['strip_metadata'] ?? true) {
            $args[] = '-strip';
            $args[] = 'all';
        }

        // Quiet mode
        $args[] = '-quiet';

        // Preserve file attributes
        $args[] = '-preserve';

        // Interlacing (0=non-interlaced, 1=interlaced)
        if ($options['interlace'] ?? false) {
            $args[] = '-i';
            $args[] = '1';
        }

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install OptiPNG:\n' .
            '• Ubuntu/Debian: sudo apt install optipng\n' .
            '• CentOS/RHEL: sudo yum install optipng\n' .
            '• macOS: brew install optipng',
            'media-toolkit'
        );
    }
}

