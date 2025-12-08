<?php
/**
 * pngquant Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using pngquant CLI tool
 * Lossy PNG compression with excellent quality (reduces to 256 colors)
 * Can achieve 60-80% file size reduction
 */
final class PngquantOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['png'];
    protected int $priority = 80; // Highest priority for PNG (lossy but great results)

    public function getId(): string
    {
        return 'pngquant';
    }

    public function getName(): string
    {
        return 'pngquant';
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

        $this->binaryPath = $this->findBinary('pngquant');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        // 2.18.0 (July 2023)
        if (preg_match('/(\d+\.\d+\.\d+)/', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('pngquant is not available', $this->getId());
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

        // Output to stdout and redirect to destination
        $args[] = '--output';
        $args[] = $destinationPath;
        $args[] = $sourcePath;

        $result = $this->executeCommand($this->binaryPath, $args);

        // pngquant returns 99 if image cannot be reduced further (already optimized)
        // This is not really an error
        if ($result['return_code'] !== 0 && $result['return_code'] !== 99) {
            return OptimizerResult::failure('pngquant failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        // If return code was 99 and destination doesn't exist, copy original
        if ($result['return_code'] === 99 && !file_exists($destinationPath)) {
            if ($sourcePath !== $destinationPath) {
                copy($sourcePath, $destinationPath);
            }
            return OptimizerResult::skipped('Image already optimized', $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "pngquant optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Quality range (min-max)
        $quality = $options['quality'] ?? $options['png_quality'] ?? 80;
        $minQuality = max(0, $quality - 20);
        $args[] = "--quality={$minQuality}-{$quality}";

        // Speed (1=slowest/best, 11=fastest)
        $speed = $options['speed'] ?? 4;
        $args[] = "--speed={$speed}";

        // Force overwrite
        $args[] = '--force';

        // Strip metadata
        if ($options['strip_metadata'] ?? true) {
            $args[] = '--strip';
        }

        // Skip if larger than original
        $args[] = '--skip-if-larger';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install pngquant:\n' .
            '• Ubuntu/Debian: sudo apt install pngquant\n' .
            '• CentOS/RHEL: sudo yum install pngquant (EPEL required)\n' .
            '• macOS: brew install pngquant',
            'media-toolkit'
        );
    }
}

