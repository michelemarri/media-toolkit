<?php
/**
 * OpenAI GPT-4 Vision Provider
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;

/**
 * OpenAI GPT-4 Vision implementation
 */
final class OpenAIProvider extends AbstractAIProvider
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** @var array<string, string> Available models (fallback) */
    private const MODELS = [
        'gpt-4o' => 'GPT-4o (Best quality)',
        'gpt-4o-mini' => 'GPT-4o Mini (Faster, cheaper)',
        'chatgpt-4o-latest' => 'ChatGPT-4o Latest',
        'gpt-4-turbo' => 'GPT-4 Turbo (Legacy)',
    ];

    /** @var array<string, float> Cost per image estimate in USD */
    private const COST_PER_IMAGE = [
        'gpt-4o' => 0.005,
        'gpt-4o-mini' => 0.001,
        'chatgpt-4o-latest' => 0.005,
        'gpt-4-turbo' => 0.01,
    ];

    /** @var string[] Vision-capable model prefixes */
    private const VISION_MODEL_PREFIXES = ['gpt-4o', 'gpt-4-turbo', 'gpt-4-vision', 'chatgpt-4o'];

    public function __construct(Encryption $encryption, ?Logger $logger = null)
    {
        parent::__construct($encryption, $logger);
        $this->model = 'gpt-4o-mini'; // Default to cost-effective model
        $this->rateLimitDelay = 200; // OpenAI has higher rate limits
    }

    public function getId(): string
    {
        return 'openai';
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function getAvailableModels(): array
    {
        return self::MODELS;
    }

    /**
     * Fetch models from OpenAI API
     */
    protected function fetchModelsFromApi(): array
    {
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']) || !is_array($body['data'])) {
            return [];
        }

        $models = [];
        foreach ($body['data'] as $model) {
            $id = $model['id'] ?? '';
            
            // Only include vision-capable models
            foreach (self::VISION_MODEL_PREFIXES as $prefix) {
                if (str_starts_with($id, $prefix)) {
                    // Create a friendly name
                    $name = str_replace(['-', '_'], ' ', $id);
                    $name = ucwords($name);
                    $models[$id] = $name;
                    break;
                }
            }
        }

        // Sort by key (newer models usually have later names)
        krsort($models);

        return $models;
    }

    public function getEstimatedCostPerImage(): float
    {
        return self::COST_PER_IMAGE[$this->model] ?? 0.005;
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('API key not configured', 'media-toolkit'),
            ];
        }

        try {
            $this->logDebug('Testing connection', [
                'model' => $this->model,
                'api_key_length' => strlen($this->apiKey),
                'api_key_prefix' => substr($this->apiKey, 0, 10) . '...',
            ]);

            // Simple models list call to test API key
            $response = wp_remote_get('https://api.openai.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'timeout' => 10,
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

            $this->logDebug('Response received', [
                'status' => $statusCode,
                'body_length' => strlen($responseBody),
            ]);

            if ($statusCode === 401 || $statusCode === 403) {
                $body = json_decode($responseBody, true);
                $message = $this->extractErrorMessage($body, $statusCode);
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            if ($statusCode !== 200) {
                $body = json_decode($responseBody, true);
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($body, $statusCode),
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

    public function analyzeImage(string $imageSource, string $language = 'en', array $customPrompts = []): array
    {
        if (!$this->isConfigured()) {
            throw AIProviderException::invalidApiKey($this->getName());
        }

        $image = $this->prepareImage($imageSource);
        $prompt = $this->buildAnalysisPrompt($language, $customPrompts);

        // Build content array
        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        // Add image
        if ($image['type'] === 'url') {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image['data'],
                    'detail' => 'low', // Use low detail to reduce costs
                ],
            ];
        } else {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image['data'],
                    'detail' => 'low',
                ],
            ];
        }

        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'max_tokens' => 1000,
            'temperature' => 0.3, // Lower temperature for more consistent output
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $this->log('info', 'Analyzing image with OpenAI', ['model' => $this->model]);

        $response = $this->makeRequest(self::API_URL, $headers, $body);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new AIProviderException(
                'Invalid response structure from OpenAI',
                AIProviderException::ERROR_PARSE
            );
        }

        $content = $response['choices'][0]['message']['content'];
        
        return $this->parseJsonResponse($content);
    }
}

