<?php
/**
 * AI Manager - Orchestrates AI providers with fallback support
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;

/**
 * Manages AI providers with fallback and cost estimation
 */
final class AIManager
{
    private const SETTINGS_OPTION = 'media_toolkit_ai_settings';

    private Encryption $encryption;
    private ?Logger $logger;
    
    /** @var array<string, AIProviderInterface> */
    private array $providers = [];
    
    /** @var array<string> Provider IDs in priority order */
    private array $providerOrder = [];
    
    private string $language = 'en';
    private array $customPrompts = [];
    private bool $generateOnUpload = false;
    private int $minImageSize = 100; // Minimum image dimension in pixels

    public function __construct(Encryption $encryption, ?Logger $logger = null)
    {
        $this->encryption = $encryption;
        $this->logger = $logger;
        
        $this->initializeProviders();
        $this->loadSettings();
    }

    /**
     * Initialize available providers
     */
    private function initializeProviders(): void
    {
        $this->providers = [
            'openai' => new OpenAIProvider($this->encryption, $this->logger),
            'claude' => new ClaudeProvider($this->encryption, $this->logger),
            'gemini' => new GeminiProvider($this->encryption, $this->logger),
        ];
    }

    /**
     * Load settings from database
     */
    private function loadSettings(): void
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        
        if (!is_array($settings)) {
            $settings = [];
        }

        // Load provider order
        $this->providerOrder = $settings['provider_order'] ?? ['openai', 'claude', 'gemini'];
        
        // Load language
        $this->language = $settings['language'] ?? 'en';
        if ($this->language === 'site') {
            $this->language = substr(get_locale(), 0, 2);
        }
        
        // Load custom prompts
        $this->customPrompts = $settings['custom_prompts'] ?? [];
        
        // Load generate on upload setting
        $this->generateOnUpload = $settings['generate_on_upload'] ?? false;
        $this->minImageSize = $settings['min_image_size'] ?? 100;
        
        // Configure providers with their API keys and models
        foreach ($this->providers as $id => $provider) {
            $providerSettings = $settings['providers'][$id] ?? [];
            
            if (!empty($providerSettings['api_key'])) {
                $provider->setApiKey($providerSettings['api_key']);
            }
            
            if (!empty($providerSettings['model'])) {
                $provider->setModel($providerSettings['model']);
            }
        }
    }

    /**
     * Save settings to database
     */
    public function saveSettings(array $settings): bool
    {
        // Encrypt API keys before saving (only if not already encrypted)
        if (isset($settings['providers'])) {
            foreach ($settings['providers'] as $id => &$providerSettings) {
                $isAlreadyEncrypted = $providerSettings['_encrypted'] ?? false;
                unset($providerSettings['_encrypted']); // Remove the flag, don't save it
                
                if (!empty($providerSettings['api_key']) && !$isAlreadyEncrypted && !str_contains($providerSettings['api_key'], '•')) {
                    $providerSettings['api_key'] = $this->encryption->encrypt($providerSettings['api_key']);
                }
            }
        }
        
        $saved = update_option(self::SETTINGS_OPTION, $settings);
        
        if ($saved) {
            $this->loadSettings();
        }
        
        return $saved;
    }

    /**
     * Get current settings (with masked API keys)
     */
    public function getSettings(): array
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        
        if (!is_array($settings)) {
            $settings = [];
        }

        // Mask API keys for display
        $masked = [
            'provider_order' => $settings['provider_order'] ?? ['openai', 'claude', 'gemini'],
            'language' => $settings['language'] ?? 'en',
            'custom_prompts' => $settings['custom_prompts'] ?? [],
            'generate_on_upload' => $settings['generate_on_upload'] ?? false,
            'min_image_size' => $settings['min_image_size'] ?? 100,
            'providers' => [],
        ];

        foreach ($this->providers as $id => $provider) {
            $providerSettings = $settings['providers'][$id] ?? [];
            $masked['providers'][$id] = [
                'api_key' => !empty($providerSettings['api_key']) 
                    ? $this->encryption->mask($this->encryption->decrypt($providerSettings['api_key']))
                    : '',
                'model' => $providerSettings['model'] ?? $provider->getModel(),
                'enabled' => !empty($providerSettings['api_key']),
            ];
        }

        return $masked;
    }

    /**
     * Check if generate on upload is enabled
     */
    public function isGenerateOnUploadEnabled(): bool
    {
        return $this->generateOnUpload && $this->hasConfiguredProvider();
    }

    /**
     * Get minimum image size for AI processing
     */
    public function getMinImageSize(): int
    {
        return $this->minImageSize;
    }

    /**
     * Get all available providers info
     */
    public function getProvidersInfo(bool $fetchDynamicModels = false): array
    {
        $info = [];
        
        foreach ($this->providers as $id => $provider) {
            // Use dynamic models if provider is configured and requested
            $models = ($fetchDynamicModels && $provider->isConfigured())
                ? $provider->fetchAvailableModels()
                : $provider->getAvailableModels();

            $info[$id] = [
                'id' => $provider->getId(),
                'name' => $provider->getName(),
                'models' => $models,
                'current_model' => $provider->getModel(),
                'configured' => $provider->isConfigured(),
                'cost_per_image' => $provider->getEstimatedCostPerImage(),
            ];
        }

        return $info;
    }

    /**
     * Refresh cached models for all configured providers
     */
    public function refreshModelsCache(): void
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                $provider->clearModelsCache();
                $provider->fetchAvailableModels();
            }
        }
    }

    /**
     * Get configured providers in priority order
     *
     * @return AIProviderInterface[]
     */
    public function getConfiguredProviders(): array
    {
        $configured = [];
        
        foreach ($this->providerOrder as $id) {
            if (isset($this->providers[$id]) && $this->providers[$id]->isConfigured()) {
                $configured[] = $this->providers[$id];
            }
        }

        return $configured;
    }

    /**
     * Check if at least one provider is configured
     */
    public function hasConfiguredProvider(): bool
    {
        return count($this->getConfiguredProviders()) > 0;
    }

    /**
     * Get a specific provider
     */
    public function getProvider(string $id): ?AIProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * Test connection for a specific provider
     */
    public function testProviderConnection(string $providerId): array
    {
        $provider = $this->providers[$providerId] ?? null;
        
        if ($provider === null) {
            return [
                'success' => false,
                'message' => __('Provider not found', 'media-toolkit'),
            ];
        }

        return $provider->testConnection();
    }

    /**
     * Analyze image with fallback support
     *
     * @param string $imageSource URL or file path
     * @return array{title: string, alt_text: string, caption: string, description: string, provider: string}
     * @throws AIProviderException If all providers fail
     */
    public function analyzeImage(string $imageSource): array
    {
        $providers = $this->getConfiguredProviders();
        
        if (empty($providers)) {
            throw new AIProviderException(
                __('No AI providers configured', 'media-toolkit'),
                AIProviderException::ERROR_UNKNOWN
            );
        }

        $lastException = null;
        
        foreach ($providers as $provider) {
            try {
                $this->logger?->info(
                    'ai_manager',
                    sprintf('Trying provider: %s', $provider->getName())
                );

                $result = $provider->analyzeImage(
                    $imageSource,
                    $this->language,
                    $this->customPrompts
                );

                // Add provider info to result
                $result['provider'] = $provider->getId();
                
                // Apply rate limit delay
                if ($provider->getRateLimitDelay() > 0) {
                    usleep($provider->getRateLimitDelay() * 1000);
                }

                return $result;
                
            } catch (AIProviderException $e) {
                $lastException = $e;
                
                $this->logger?->warning(
                    'ai_manager',
                    sprintf(
                        'Provider %s failed: %s. Trying next...',
                        $provider->getName(),
                        $e->getMessage()
                    )
                );

                // If not retryable (e.g., invalid API key), skip to next
                if (!$e->isRetryable() && $e->getErrorType() !== AIProviderException::ERROR_RATE_LIMITED) {
                    continue;
                }

                // For rate limiting, try next provider
                continue;
            }
        }

        // All providers failed
        throw $lastException ?? new AIProviderException(
            __('All AI providers failed', 'media-toolkit'),
            AIProviderException::ERROR_UNKNOWN
        );
    }

    /**
     * Estimate cost for batch processing
     *
     * @param int $imageCount Number of images to process
     * @return array{total: float, per_image: float, provider: string, currency: string}
     */
    public function estimateCost(int $imageCount): array
    {
        $providers = $this->getConfiguredProviders();
        
        if (empty($providers)) {
            return [
                'total' => 0,
                'per_image' => 0,
                'provider' => '',
                'currency' => 'USD',
            ];
        }

        // Use first configured provider for estimation
        $provider = $providers[0];
        $perImage = $provider->getEstimatedCostPerImage();

        return [
            'total' => round($perImage * $imageCount, 4),
            'per_image' => $perImage,
            'provider' => $provider->getName(),
            'provider_id' => $provider->getId(),
            'currency' => 'USD',
        ];
    }

    /**
     * Get supported languages
     */
    public function getSupportedLanguages(): array
    {
        return [
            'site' => __('Site Language', 'media-toolkit'),
            'en' => 'English',
            'it' => 'Italiano',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'pt' => 'Português',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'ja' => '日本語',
            'zh' => '中文',
        ];
    }

    /**
     * Get current language
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Get default prompts for customization
     */
    public function getDefaultPrompts(): array
    {
        return [
            'title' => __('Generate a concise, descriptive title (50-70 characters).', 'media-toolkit'),
            'alt_text' => __('Generate alt text for accessibility and SEO (max 125 characters).', 'media-toolkit'),
            'caption' => __('Generate an engaging caption (150-250 characters).', 'media-toolkit'),
            'description' => __('Generate a detailed description with context and keywords.', 'media-toolkit'),
        ];
    }

    /**
     * Get provider order
     */
    public function getProviderOrder(): array
    {
        return $this->providerOrder;
    }

    /**
     * Set provider order
     */
    public function setProviderOrder(array $order): void
    {
        $validIds = array_keys($this->providers);
        $this->providerOrder = array_intersect($order, $validIds);
    }
}

