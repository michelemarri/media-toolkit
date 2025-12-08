<?php
/**
 * jpegoptim Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using jpegoptim CLI tool
 * Fast and common JPEG optimizer
 */
final class JpegoptimOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['jpeg'];
    protected int $priority = 50; // Higher than Imagick

    public function getId(): string
    {
        return 'jpegoptim';
    }

    public function getName(): string
    {
        return 'jpegoptim';
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

        $this->binaryPath = $this->findBinary('jpegoptim');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        if ($result['return_code'] !== 0) {
            return null;
        }

        // jpegoptim v1.5.0 (...)
        if (preg_match('/jpegoptim\s+v?(\d+\.\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('jpegoptim is not available', $this->getId());
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

        // If source and destination are different, we need to copy first
        $workingFile = $sourcePath;
        if ($sourcePath !== $destinationPath) {
            $workingFile = $this->createTempCopy($sourcePath);
            if ($workingFile === null) {
                return OptimizerResult::failure('Failed to create temporary copy', $this->getId(), $originalSize);
            }
        }

        // Add the file to optimize
        $args[] = $workingFile;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            if ($sourcePath !== $destinationPath && $workingFile !== null) {
                @unlink($workingFile);
            }
            return OptimizerResult::failure('jpegoptim failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        // Move to destination if needed
        if ($sourcePath !== $destinationPath) {
            if (!rename($workingFile, $destinationPath)) {
                @unlink($workingFile);
                return OptimizerResult::failure('Failed to move optimized file', $this->getId(), $originalSize);
            }
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "jpegoptim optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Quality (max quality to keep, lower = more compression)
        $quality = $options['quality'] ?? $options['jpeg_quality'] ?? 82;
        $args[] = "--max={$quality}";

        // Strip metadata
        if ($options['strip_metadata'] ?? true) {
            $args[] = '--strip-all';
        }

        // Progressive JPEG
        if ($options['progressive'] ?? true) {
            $args[] = '--all-progressive';
        }

        // Overwrite original file
        $args[] = '--overwrite';

        // Quiet mode (less output)
        $args[] = '--quiet';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install jpegoptim:\n' .
            '• Ubuntu/Debian: sudo apt install jpegoptim\n' .
            '• CentOS/RHEL: sudo yum install jpegoptim (EPEL required)\n' .
            '• macOS: brew install jpegoptim',
            'media-toolkit'
        );
    }
}

