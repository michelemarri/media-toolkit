<?php
/**
 * AI Provider Interface
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

/**
 * Interface for AI Vision providers
 */
interface AIProviderInterface
{
    /**
     * Get provider unique identifier
     */
    public function getId(): string;

    /**
     * Get provider display name
     */
    public function getName(): string;

    /**
     * Check if provider is configured (has API key)
     */
    public function isConfigured(): bool;

    /**
     * Test connection to the provider
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Analyze an image and generate metadata
     *
     * @param string $imageSource URL or base64 encoded image
     * @param string $language Target language for generated text (e.g., 'it', 'en')
     * @param array $customPrompts Optional custom prompts for each field
     * @return array{title: string, alt_text: string, caption: string, description: string}
     * @throws AIProviderException On API errors
     */
    public function analyzeImage(string $imageSource, string $language = 'en', array $customPrompts = []): array;

    /**
     * Get estimated cost per image in USD
     */
    public function getEstimatedCostPerImage(): float;

    /**
     * Get available models for this provider
     *
     * @return array<string, string> Model ID => Display name
     */
    public function getAvailableModels(): array;

    /**
     * Get currently selected model
     */
    public function getModel(): string;

    /**
     * Set the model to use
     */
    public function setModel(string $model): void;

    /**
     * Get rate limit delay in milliseconds
     */
    public function getRateLimitDelay(): int;
}

