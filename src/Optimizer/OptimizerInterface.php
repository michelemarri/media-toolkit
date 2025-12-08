<?php
/**
 * Optimizer Interface
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

/**
 * Interface for image optimization drivers
 */
interface OptimizerInterface
{
    /**
     * Get unique identifier for this optimizer
     */
    public function getId(): string;

    /**
     * Get display name for this optimizer
     */
    public function getName(): string;

    /**
     * Check if this optimizer is available on the server
     */
    public function isAvailable(): bool;

    /**
     * Get list of supported image formats
     *
     * @return array<string> Format identifiers (e.g., 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg')
     */
    public function getSupportedFormats(): array;

    /**
     * Get priority for this optimizer (higher = better)
     * Used to determine which optimizer to use when multiple are available
     */
    public function getPriority(): int;

    /**
     * Get the version of the optimizer tool
     */
    public function getVersion(): ?string;

    /**
     * Optimize an image file
     *
     * @param string $sourcePath Path to the source image
     * @param string $destinationPath Path to save the optimized image (can be same as source)
     * @param array<string, mixed> $options Optimization options
     * @return OptimizerResult Result of the optimization
     */
    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult;

    /**
     * Check if the optimizer supports a specific format
     */
    public function supportsFormat(string $format): bool;

    /**
     * Get the binary path for CLI-based optimizers
     */
    public function getBinaryPath(): ?string;

    /**
     * Get installation instructions for this optimizer
     */
    public function getInstallInstructions(): string;
}

