<?php
/**
 * avifenc Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using avifenc CLI tool (libavif encoder)
 * Native AVIF encoder - 20-50% smaller than WebP
 */
final class AvifencOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['avif'];
    protected int $priority = 80; // Highest priority for AVIF

    public function getId(): string
    {
        return 'avifenc';
    }

    public function getName(): string
    {
        return 'avifenc (AVIF)';
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

        $this->binaryPath = $this->findBinary('avifenc');
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        $result = $this->executeCommand($this->binaryPath, ['--version']);
        
        // Version: 1.0.1
        if (preg_match('/Version:\s*(\d+\.\d+\.\d+)/i', $result['output'], $matches)) {
            return $matches[1];
        }

        // Try simpler pattern
        if (preg_match('/(\d+\.\d+\.\d+)/', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('avifenc is not available', $this->getId());
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

        // Input and output files
        $args[] = $sourcePath;
        $args[] = $destinationPath;

        $result = $this->executeCommand($this->binaryPath, $args);

        if ($result['return_code'] !== 0) {
            return OptimizerResult::failure('avifenc failed: ' . $result['output'], $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "avifenc optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Quality (0-63 for AVIF, where 0 is lossless and 63 is worst)
        // We map from 0-100 scale to 0-63 scale (inverted)
        $quality = $options['quality'] ?? $options['avif_quality'] ?? 50;
        $avifQuality = (int) round((100 - $quality) * 63 / 100);
        $avifQuality = max(0, min(63, $avifQuality));

        // Min and max quantizer
        $args[] = '--min';
        $args[] = (string) max(0, $avifQuality - 10);
        $args[] = '--max';
        $args[] = (string) $avifQuality;

        // Speed (0-10, lower = slower but better compression)
        $speed = $options['speed'] ?? 6;
        $args[] = '--speed';
        $args[] = (string) $speed;

        // Codec (aom, rav1e, svt)
        // aom is the reference encoder and most compatible
        $codec = $options['codec'] ?? 'aom';
        $args[] = '--codec';
        $args[] = $codec;

        // Jobs (multi-threading)
        $args[] = '--jobs';
        $args[] = 'all';

        // Depth (8, 10, or 12 bit)
        $args[] = '--depth';
        $args[] = '8';

        // YUV format
        $args[] = '--yuv';
        $args[] = '420';

        // Overwrite output
        $args[] = '--overwrite';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install avifenc (libavif):\n' .
            '• Ubuntu 22.04+: sudo apt install libavif-bin\n' .
            '• macOS: brew install libavif\n' .
            '• Build from source: https://github.com/AOMediaCodec/libavif',
            'media-toolkit'
        );
    }
}

