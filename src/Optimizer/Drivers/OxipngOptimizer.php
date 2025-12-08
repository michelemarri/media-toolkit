<?php
/**
 * Oxipng Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using oxipng CLI tool
 * Modern, multi-threaded lossless PNG optimizer written in Rust
 * Successor to OptiPNG with better performance
 */
final class OxipngOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['png'];
    protected int $priority = 60; // Higher than optipng (modern, faster)

    public function getId(): string
    {
        return 'oxipng';
    }

    public function getName(): string
    {
        return 'oxipng';
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

        $this->binaryPath = $this->findBinary('oxipng');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        // oxipng 8.0.0
        if (preg_match('/oxipng\s+(\d+\.\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('oxipng is not available', $this->getId());
        }

        $error = $this->validateSourceFile($sourcePath);
        if ($error !== null) {
            return OptimizerResult::failure($error, $this->getId());
        }

        if (!$this->ensureDirectoryExists($destinationPath)) {
            return OptimizerResult::failure('Cannot create destination directory', $this->getId());
        }

        $originalSize = $this->getFileSize($sourcePath);

        // Build command arguments
        $args = $this->buildArguments($options);

        // Output file
        $args[] = '--out';
        $args[] = $destinationPath;

        // Input file
        $args[] = $sourcePath;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            return OptimizerResult::failure('oxipng failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "oxipng optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Optimization level (0-6, higher = slower but better)
        $level = $options['level'] ?? $options['png_compression'] ?? 2;
        $level = max(0, min(6, $level)); // Clamp to 0-6
        $args[] = "-o{$level}";

        // Strip metadata
        if ($options['strip_metadata'] ?? true) {
            $args[] = '--strip';
            $args[] = 'safe'; // Remove all non-critical chunks
        }

        // Multi-threading (use all available cores)
        $args[] = '--threads';
        $args[] = '0'; // 0 = auto-detect

        // Interlacing
        if ($options['interlace'] ?? false) {
            $args[] = '--interlace';
            $args[] = '1';
        } else {
            $args[] = '--interlace';
            $args[] = '0';
        }

        // Force overwrite
        $args[] = '--force';

        // Quiet mode
        $args[] = '--quiet';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install oxipng:\n' .
            '• Using cargo: cargo install oxipng\n' .
            '• macOS: brew install oxipng\n' .
            '• Download binary from https://github.com/shssoichiro/oxipng/releases',
            'media-toolkit'
        );
    }
}

