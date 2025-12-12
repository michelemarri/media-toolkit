<?php
/**
 * Abstract AI Provider
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;

/**
 * Base class for AI Vision providers
 */
abstract class AbstractAIProvider implements AIProviderInterface
{
    protected Encryption $encryption;
    protected ?Logger $logger;
    protected string $apiKey = '';
    protected string $model = '';
    protected int $rateLimitDelay = 500; // ms

    /** @var array<string, string> Default prompts for metadata generation */
    protected const DEFAULT_PROMPTS = [
        'title' => 'Generate a concise, descriptive title for this image. The title should be 50-70 characters, serving as a clear identifier for the image content.',
        'alt_text' => 'Generate an alt text description for this image optimized for accessibility and SEO. Maximum 125 characters. Describe the key visual elements concisely.',
        'caption' => 'Generate an engaging caption for this image. 150-250 characters. The caption should engage readers and add context to what they see.',
        'description' => 'Generate a detailed description of this image including all relevant visual elements and context. No character limit.',
        'keywords' => 'Generate 5-10 relevant SEO keywords/tags for this image, comma-separated. Focus on searchable terms that describe the image content, style, and context.',
    ];

    /** @var array<string, array<string, string>> Language-specific prompts */
    protected const LANGUAGE_PROMPTS = [
        'it' => [
            'title' => 'Genera un titolo conciso e descrittivo per questa immagine. Il titolo dovrebbe essere di 50-70 caratteri, servendo come identificatore chiaro del contenuto.',
            'alt_text' => 'Genera un testo alternativo per questa immagine ottimizzato per accessibilità e SEO. Massimo 125 caratteri. Descrivi gli elementi visivi chiave in modo conciso.',
            'caption' => 'Genera una didascalia coinvolgente per questa immagine. 150-250 caratteri. La didascalia dovrebbe coinvolgere i lettori e aggiungere contesto.',
            'description' => 'Genera una descrizione dettagliata di questa immagine includendo tutti gli elementi visivi rilevanti e il contesto. Nessun limite di caratteri.',
            'keywords' => 'Genera 5-10 parole chiave/tag SEO rilevanti per questa immagine, separate da virgola. Concentrati su termini ricercabili che descrivono il contenuto, lo stile e il contesto.',
        ],
        'en' => self::DEFAULT_PROMPTS,
    ];

    public function __construct(Encryption $encryption, ?Logger $logger = null)
    {
        $this->encryption = $encryption;
        $this->logger = $logger;
    }

    /**
     * Set API key (encrypted)
     */
    public function setApiKey(string $encryptedKey): void
    {
        $this->apiKey = $this->encryption->decrypt($encryptedKey);
    }

    /**
     * Set API key (plain)
     */
    public function setPlainApiKey(string $key): void
    {
        $this->apiKey = $key;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $availableModels = $this->getAvailableModels();
        if (array_key_exists($model, $availableModels)) {
            $this->model = $model;
        }
    }

    /**
     * Fetch available models from API
     * Returns cached results if available
     *
     * @return array<string, string> Model ID => Display name
     */
    public function fetchAvailableModels(): array
    {
        if (!$this->isConfigured()) {
            return $this->getAvailableModels(); // Return static list if not configured
        }

        $cacheKey = 'media_toolkit_models_' . $this->getId();
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }

        try {
            $models = $this->fetchModelsFromApi();
            
            if (!empty($models)) {
                // Cache for 24 hours
                set_transient($cacheKey, $models, DAY_IN_SECONDS);
                return $models;
            }
        } catch (\Exception $e) {
            $this->logDebug('Failed to fetch models', ['error' => $e->getMessage()]);
        }

        // Fallback to static list
        return $this->getAvailableModels();
    }

    /**
     * Fetch models from provider API
     * Override in each provider
     *
     * @return array<string, string> Model ID => Display name
     */
    protected function fetchModelsFromApi(): array
    {
        // Default: return static list
        return $this->getAvailableModels();
    }

    /**
     * Clear cached models
     */
    public function clearModelsCache(): void
    {
        delete_transient('media_toolkit_models_' . $this->getId());
    }

    public function getRateLimitDelay(): int
    {
        return $this->rateLimitDelay;
    }

    /**
     * Set rate limit delay in milliseconds
     */
    public function setRateLimitDelay(int $ms): void
    {
        $this->rateLimitDelay = max(0, $ms);
    }

    /**
     * Get prompts for a specific language
     */
    protected function getPrompts(string $language, array $customPrompts = []): array
    {
        $basePrompts = self::LANGUAGE_PROMPTS[$language] ?? self::DEFAULT_PROMPTS;
        return array_merge($basePrompts, $customPrompts);
    }

    /**
     * Build the full prompt for image analysis
     */
    protected function buildAnalysisPrompt(string $language, array $customPrompts = []): string
    {
        $prompts = $this->getPrompts($language, $customPrompts);
        $langInstruction = $language === 'it' 
            ? 'Rispondi SEMPRE in italiano.' 
            : ($language !== 'en' ? "Respond in {$language} language." : '');

        return <<<PROMPT
Analyze this image and generate metadata in the following JSON format:
{
    "title": "...",
    "alt_text": "...",
    "caption": "...",
    "description": "...",
    "keywords": "..."
}

Guidelines for each field:
- TITLE: {$prompts['title']}
- ALT_TEXT: {$prompts['alt_text']}
- CAPTION: {$prompts['caption']}
- DESCRIPTION: {$prompts['description']}
- KEYWORDS: {$prompts['keywords']}

{$langInstruction}

Return ONLY the JSON object, no additional text or markdown formatting.
PROMPT;
    }

    /**
     * Parse JSON response from AI
     *
     * @throws AIProviderException
     */
    protected function parseJsonResponse(string $response): array
    {
        // Log raw response for debugging
        $this->logDebug('Raw AI response length', ['length' => strlen($response)]);
        
        // Clean up response - remove markdown code blocks if present
        $response = trim($response);
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/i', '', $response);
        
        // First try to parse as-is
        $data = json_decode($response, true);
        
        // If wrapped in {"response": "..."} format, extract the inner content
        if ($data !== null && isset($data['response']) && is_string($data['response'])) {
            $innerResponse = $data['response'];
            // Remove code blocks from inner content too
            $innerResponse = preg_replace('/^```(?:json)?\s*/i', '', trim($innerResponse));
            $innerResponse = preg_replace('/\s*```$/i', '', $innerResponse);
            $data = json_decode($innerResponse, true);
        }
        
        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            $this->logDebug('JSON parse error', [
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 500)
            ]);
            throw new AIProviderException(
                'Failed to parse AI response: ' . json_last_error_msg(),
                AIProviderException::ERROR_PARSE
            );
        }

        // Validate required fields
        $required = ['title', 'alt_text', 'caption', 'description'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new AIProviderException(
                    "Missing required field in AI response: {$field}",
                    AIProviderException::ERROR_PARSE
                );
            }
        }

        // Build description with keywords appended
        $description = trim($data['description']);
        $keywords = isset($data['keywords']) ? trim($data['keywords']) : '';
        
        if (!empty($keywords)) {
            $description .= "\n\nKeywords: " . $keywords;
        }

        // Enforce character limits
        return [
            'title' => mb_substr(trim($data['title']), 0, 70),
            'alt_text' => mb_substr(trim($data['alt_text']), 0, 125),
            'caption' => mb_substr(trim($data['caption']), 0, 250),
            'description' => $description,
            'keywords' => $keywords, // Also return separately for potential future use
        ];
    }

    /**
     * Prepare image for API call
     * Resizes large images to reduce API costs
     *
     * @param string $imageSource URL or file path
     * @return array{type: string, data: string} Base64 or URL data
     */
    protected function prepareImage(string $imageSource): array
    {
        // If it's a URL, return as-is for most providers
        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            return [
                'type' => 'url',
                'data' => $imageSource,
            ];
        }

        // If it's a local file, convert to base64
        if (file_exists($imageSource)) {
            $imageData = $this->resizeImageForApi($imageSource);
            $mimeType = wp_check_filetype($imageSource)['type'] ?? 'image/jpeg';
            
            return [
                'type' => 'base64',
                'data' => 'data:' . $mimeType . ';base64,' . base64_encode($imageData),
                'mime_type' => $mimeType,
            ];
        }

        // Assume it's already base64
        if (str_starts_with($imageSource, 'data:')) {
            return [
                'type' => 'base64',
                'data' => $imageSource,
            ];
        }

        throw new AIProviderException(
            'Invalid image source provided',
            AIProviderException::ERROR_INVALID_IMAGE
        );
    }

    /**
     * Resize image to reduce API costs
     * Max 1024px on longest side
     */
    protected function resizeImageForApi(string $filePath, int $maxSize = 1024): string
    {
        $editor = wp_get_image_editor($filePath);
        
        if (is_wp_error($editor)) {
            // Return original file if we can't edit
            return file_get_contents($filePath);
        }

        $size = $editor->get_size();
        $needsResize = $size['width'] > $maxSize || $size['height'] > $maxSize;

        if ($needsResize) {
            $editor->resize($maxSize, $maxSize, false);
        }

        // Set quality for smaller file size
        $editor->set_quality(85);

        // Save to temp file and read contents
        $tempFile = wp_tempnam('ai_resize_');
        $result = $editor->save($tempFile);

        if (is_wp_error($result)) {
            @unlink($tempFile);
            return file_get_contents($filePath);
        }

        $contents = file_get_contents($result['path']);
        @unlink($result['path']);
        
        return $contents;
    }

    /**
     * Make HTTP request with retry logic
     *
     * @throws AIProviderException
     */
    protected function makeRequest(
        string $url,
        array $headers,
        array $body,
        int $maxRetries = 3
    ): array {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = wp_remote_post($url, [
                    'headers' => $headers,
                    'body' => wp_json_encode($body),
                    'timeout' => 120, // Vision APIs can be slow
                ]);

                if (is_wp_error($response)) {
                    throw new AIProviderException(
                        'Network error: ' . $response->get_error_message(),
                        AIProviderException::ERROR_NETWORK,
                        true
                    );
                }

                $statusCode = wp_remote_retrieve_response_code($response);
                $responseBody = wp_remote_retrieve_body($response);

                // Handle rate limiting
                if ($statusCode === 429) {
                    $retryAfter = (int) wp_remote_retrieve_header($response, 'retry-after') ?: 60;
                    throw AIProviderException::rateLimited($retryAfter);
                }

                // Handle auth errors
                if ($statusCode === 401 || $statusCode === 403) {
                    throw AIProviderException::invalidApiKey($this->getName());
                }

                // Handle quota errors
                if ($statusCode === 402) {
                    throw AIProviderException::quotaExceeded($this->getName());
                }

                // Handle other errors
                if ($statusCode >= 400) {
                    $error = json_decode($responseBody, true);
                    $message = $error['error']['message'] ?? $responseBody;
                    throw new AIProviderException(
                        "API error ({$statusCode}): {$message}",
                        AIProviderException::ERROR_UNKNOWN,
                        $statusCode >= 500, // Server errors are retryable
                        0,
                        $statusCode
                    );
                }

                return json_decode($responseBody, true) ?? [];
                
            } catch (AIProviderException $e) {
                $lastException = $e;
                
                if (!$e->isRetryable() || $attempt >= $maxRetries) {
                    throw $e;
                }

                // Exponential backoff
                $delay = $e->getRetryAfter() > 0 
                    ? $e->getRetryAfter() * 1000 
                    : (int) (1000 * pow(2, $attempt - 1));
                
                usleep($delay * 1000);
                
                $this->logger?->warning(
                    'ai_provider',
                    sprintf(
                        '%s: Retry %d/%d after %dms - %s',
                        $this->getName(),
                        $attempt,
                        $maxRetries,
                        $delay,
                        $e->getMessage()
                    )
                );
            }
        }

        throw $lastException ?? new AIProviderException(
            'Request failed after max retries',
            AIProviderException::ERROR_UNKNOWN
        );
    }

    /**
     * Test connection to the API
     * Can be overridden by providers for custom test logic
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('API key not configured', 'media-toolkit'),
            ];
        }

        try {
            $testConfig = $this->getTestConnectionConfig();
            
            $this->logDebug('Testing connection', [
                'provider' => $this->getName(),
                'model' => $this->model,
                'api_key_length' => strlen($this->apiKey),
                'api_key_prefix' => substr($this->apiKey, 0, 10) . '...',
            ]);

            $response = wp_remote_post($testConfig['url'], [
                'headers' => $testConfig['headers'],
                'body' => wp_json_encode($testConfig['body']),
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                $this->logDebug('Network error', ['error' => $error]);
                return [
                    'success' => false,
                    'message' => __('Network error', 'media-toolkit') . ': ' . $error,
                ];
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);
            $parsedBody = json_decode($responseBody, true);

            $this->logDebug('Response received', [
                'status' => $statusCode,
                'body' => $responseBody,
            ]);

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'success' => false,
                    'message' => __('Invalid API key', 'media-toolkit'),
                ];
            }

            if ($statusCode !== 200) {
                $errorMessage = $this->extractErrorMessage($parsedBody, $statusCode);
                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }

            return [
                'success' => true,
                'message' => sprintf(
                    __('Connection successful! Using model: %s', 'media-toolkit'),
                    $this->model
                ),
            ];

        } catch (\Exception $e) {
            $this->logDebug('Exception during test', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get configuration for test connection request
     * Override in providers for custom test setup
     *
     * @return array{url: string, headers: array, body: array}
     */
    protected function getTestConnectionConfig(): array
    {
        // Default implementation - providers should override this
        throw new \RuntimeException('Provider must implement getTestConnectionConfig()');
    }

    /**
     * Extract error message from API response
     */
    protected function extractErrorMessage(?array $body, int $statusCode): string
    {
        if ($body === null) {
            return sprintf(__('API returned status %d', 'media-toolkit'), $statusCode);
        }

        // Common error formats
        $message = $body['error']['message'] 
            ?? $body['error'] 
            ?? $body['message'] 
            ?? sprintf(__('API returned status %d', 'media-toolkit'), $statusCode);

        // Add error type if available
        $type = $body['error']['type'] ?? null;
        if ($type && is_string($message)) {
            $message = "[{$type}] {$message}";
        }

        return is_string($message) ? $message : json_encode($message);
    }

    /**
     * Log debug information to error_log and plugin logger
     * Only logs to error_log when WP_DEBUG is enabled
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $logMessage = sprintf('[Media Toolkit %s] %s', $this->getName(), $message);
        
        if (!empty($context)) {
            // Truncate long values for readability
            $safeContext = array_map(function ($value) {
                if (is_string($value) && strlen($value) > 500) {
                    return substr($value, 0, 500) . '... (truncated)';
                }
                return $value;
            }, $context);
            $logMessage .= ' - ' . json_encode($safeContext, JSON_UNESCAPED_SLASHES);
        }
        
        error_log($logMessage);
    }

    /**
     * Log to plugin logger
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        match ($level) {
            'info' => $this->logger->info('ai_' . $this->getId(), $message, null, null, $context),
            'warning' => $this->logger->warning('ai_' . $this->getId(), $message, null, null, $context),
            'error' => $this->logger->error('ai_' . $this->getId(), $message, null, null, $context),
            default => $this->logger->info('ai_' . $this->getId(), $message, null, null, $context),
        };
    }
}

