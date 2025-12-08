<?php
/**
 * Gifsicle Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using gifsicle CLI tool
 * Best GIF optimizer - supports animated GIFs
 */
final class GifsicleOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['gif'];
    protected int $priority = 80; // Highest priority for GIF

    public function getId(): string
    {
        return 'gifsicle';
    }

    public function getName(): string
    {
        return 'Gifsicle';
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

        $this->binaryPath = $this->findBinary('gifsicle');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        // LCDF Gifsicle 1.94
        if (preg_match('/Gifsicle\s+(\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('gifsicle is not available', $this->getId());
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
        $args[] = '--output';
        $args[] = $destinationPath;

        // Input file
        $args[] = $sourcePath;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            return OptimizerResult::failure('gifsicle failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "gifsicle optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Optimization level (1-3, higher = more aggressive)
        $level = $options['level'] ?? $options['gif_level'] ?? 3;
        $level = max(1, min(3, $level));
        $args[] = "-O{$level}";

        // Lossy compression (reduces colors for smaller size)
        if ($options['lossy'] ?? false) {
            $lossyLevel = $options['lossy_level'] ?? 80;
            $args[] = "--lossy={$lossyLevel}";
        }

        // Color reduction (optional)
        if (isset($options['colors']) && $options['colors'] > 0 && $options['colors'] < 256) {
            $args[] = '--colors';
            $args[] = (string) $options['colors'];
        }

        // Interlace (progressive loading)
        if ($options['interlace'] ?? false) {
            $args[] = '--interlace';
        }

        // Batch mode (for animated GIFs)
        $args[] = '--batch';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install gifsicle:\n' .
            '• Ubuntu/Debian: sudo apt install gifsicle\n' .
            '• CentOS/RHEL: sudo yum install gifsicle (EPEL required)\n' .
            '• macOS: brew install gifsicle',
            'media-toolkit'
        );
    }
}

