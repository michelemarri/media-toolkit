<?php
/**
 * Conversion Result DTO
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Converters;

/**
 * Data Transfer Object for conversion results
 */
final class ConversionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $outputPath,
        public readonly int $originalSize,
        public readonly int $convertedSize,
        public readonly string $sourceFormat,
        public readonly string $targetFormat,
        public readonly string $converterId,
        public readonly ?string $error = null,
    ) {}

    /**
     * Get bytes saved
     */
    public function getBytesSaved(): int
    {
        return max(0, $this->originalSize - $this->convertedSize);
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
     * Check if conversion resulted in smaller file
     */
    public function isSmaller(): bool
    {
        return $this->convertedSize < $this->originalSize;
    }

    /**
     * Create a success result
     */
    public static function success(
        string $outputPath,
        int $originalSize,
        int $convertedSize,
        string $sourceFormat,
        string $targetFormat,
        string $converterId
    ): self {
        return new self(
            success: true,
            outputPath: $outputPath,
            originalSize: $originalSize,
            convertedSize: $convertedSize,
            sourceFormat: $sourceFormat,
            targetFormat: $targetFormat,
            converterId: $converterId,
        );
    }

    /**
     * Create a failure result
     */
    public static function failure(
        string $error,
        string $sourceFormat,
        string $targetFormat,
        string $converterId,
        int $originalSize = 0
    ): self {
        return new self(
            success: false,
            outputPath: null,
            originalSize: $originalSize,
            convertedSize: 0,
            sourceFormat: $sourceFormat,
            targetFormat: $targetFormat,
            converterId: $converterId,
            error: $error,
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
            'output_path' => $this->outputPath,
            'original_size' => $this->originalSize,
            'converted_size' => $this->convertedSize,
            'bytes_saved' => $this->getBytesSaved(),
            'percent_saved' => $this->getPercentSaved(),
            'source_format' => $this->sourceFormat,
            'target_format' => $this->targetFormat,
            'converter_id' => $this->converterId,
            'error' => $this->error,
        ];
    }
}

