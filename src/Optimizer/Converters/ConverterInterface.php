<?php
/**
 * Converter Interface
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Converters;

/**
 * Interface for image format converters
 */
interface ConverterInterface
{
    /**
     * Get converter unique identifier
     */
    public function getId(): string;

    /**
     * Get display name
     */
    public function getName(): string;

    /**
     * Get target format (e.g., 'webp', 'avif')
     */
    public function getTargetFormat(): string;

    /**
     * Get supported source formats
     *
     * @return array<string> Format identifiers (e.g., 'jpeg', 'png')
     */
    public function getSupportedSourceFormats(): array;

    /**
     * Check if converter is available on the server
     */
    public function isAvailable(): bool;

    /**
     * Convert an image to the target format
     *
     * @param string $sourcePath Path to source image
     * @param string|null $destinationPath Path to save converted image (null = auto-generate)
     * @param array<string, mixed> $options Conversion options
     * @return ConversionResult
     */
    public function convert(string $sourcePath, ?string $destinationPath = null, array $options = []): ConversionResult;

    /**
     * Check if source format is supported
     */
    public function supportsSourceFormat(string $format): bool;
}

