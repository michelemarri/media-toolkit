<?php
/**
 * cwebp Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using cwebp CLI tool (Google WebP encoder)
 * Native WebP encoder with best WebP support
 */
final class CwebpOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['webp'];
    protected int $priority = 80; // Highest priority for WebP

    public function getId(): string
    {
        return 'cwebp';
    }

    public function getName(): string
    {
        return 'cwebp (WebP)';
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

        $this->binaryPath = $this->findBinary('cwebp');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['-version']);
        
        // 1.3.2
        if (preg_match('/(\d+\.\d+\.\d+)/', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('cwebp is not available', $this->getId());
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
        $args[] = '-o';
        $args[] = $destinationPath;

        // Input file
        $args[] = $sourcePath;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            return OptimizerResult::failure('cwebp failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "cwebp optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Quality (0-100)
        $quality = $options['quality'] ?? $options['webp_quality'] ?? 80;
        $args[] = '-q';
        $args[] = (string) $quality;

        // Method (0-6, higher = slower but better compression)
        $method = $options['method'] ?? 4;
        $args[] = '-m';
        $args[] = (string) $method;

        // Alpha quality (0-100) for images with transparency
        $alphaQuality = $options['alpha_quality'] ?? 90;
        $args[] = '-alpha_q';
        $args[] = (string) $alphaQuality;

        // Compression method for alpha (0=no, 1=yes)
        $args[] = '-alpha_method';
        $args[] = '1';

        // Enable auto-filter for better compression
        $args[] = '-af';

        // Strip metadata
        if ($options['strip_metadata'] ?? true) {
            $args[] = '-metadata';
            $args[] = 'none';
        } else {
            $args[] = '-metadata';
            $args[] = 'all';
        }

        // Multi-threading
        $args[] = '-mt';

        // Quiet mode
        $args[] = '-quiet';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install cwebp (WebP tools):\n' .
            '• Ubuntu/Debian: sudo apt install webp\n' .
            '• CentOS/RHEL: sudo yum install libwebp-tools\n' .
            '• macOS: brew install webp',
            'media-toolkit'
        );
    }
}

