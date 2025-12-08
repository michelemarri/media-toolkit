<?php
/**
 * Optimizer Result DTO
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

/**
 * Data Transfer Object for optimization results
 */
final class OptimizerResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $originalSize,
        public readonly int $optimizedSize,
        public readonly string $optimizerId,
        public readonly ?string $error = null,
        public readonly bool $skipped = false,
        public readonly ?string $skipReason = null,
    ) {}

    /**
     * Get bytes saved
     */
    public function getBytesSaved(): int
    {
        return max(0, $this->originalSize - $this->optimizedSize);
    }

    /**
     * Get percentage saved
     */
    public function getPercentSaved(): float
    {
        if ($this->originalSize <= 0) {
            return 0.0;
        }

        return round(($this->getBytesSaved() / $this->originalSize) * 100, 2);
    }

    /**
     * Check if any bytes were actually saved
     */
    public function hasSavings(): bool
    {
        return $this->getBytesSaved() > 0;
    }

    /**
     * Create a success result
     */
    public static function success(
        int $originalSize,
        int $optimizedSize,
        string $optimizerId
    ): self {
        return new self(
            success: true,
            originalSize: $originalSize,
            optimizedSize: $optimizedSize,
            optimizerId: $optimizerId,
        );
    }

    /**
     * Create a failure result
     */
    public static function failure(
        string $error,
        string $optimizerId,
        int $originalSize = 0
    ): self {
        return new self(
            success: false,
            originalSize: $originalSize,
            optimizedSize: $originalSize,
            optimizerId: $optimizerId,
            error: $error,
        );
    }

    /**
     * Create a skipped result
     */
    public static function skipped(
        string $reason,
        string $optimizerId,
        int $originalSize = 0
    ): self {
        return new self(
            success: true,
            originalSize: $originalSize,
            optimizedSize: $originalSize,
            optimizerId: $optimizerId,
            skipped: true,
            skipReason: $reason,
        );
    }

    /**
     * Convert to array for storage/logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'original_size' => $this->originalSize,
            'optimized_size' => $this->optimizedSize,
            'bytes_saved' => $this->getBytesSaved(),
            'percent_saved' => $this->getPercentSaved(),
            'optimizer_id' => $this->optimizerId,
            'error' => $this->error,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
        ];
    }
}

