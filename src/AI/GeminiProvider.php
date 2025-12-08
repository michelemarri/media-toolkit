<?php
/**
 * Google Gemini Vision Provider
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;

/**
 * Google Gemini Pro Vision implementation
 */
final class GeminiProvider extends AbstractAIProvider
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** @var array<string, string> Available models (fallback) */
    private const MODELS = [
        'gemini-2.0-flash' => 'Gemini 2.0 Flash (Latest)',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro (Best quality)',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash (Faster, cheaper)',
        'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B (Fastest)',
    ];

    /** @var array<string, float> Cost per image estimate in USD */
    private const COST_PER_IMAGE = [
        'gemini-2.0-flash' => 0.0003,
        'gemini-1.5-pro' => 0.003,
        'gemini-1.5-flash' => 0.0005,
        'gemini-1.5-flash-8b' => 0.0002,
    ];

    /** @var string[] Vision-capable model patterns */
    private const VISION_MODEL_PATTERNS = ['gemini-1.5', 'gemini-2.0', 'gemini-pro-vision'];

    public function __construct(Encryption $encryption, ?Logger $logger = null)
    {
        parent::__construct($encryption, $logger);
        $this->model = 'gemini-1.5-flash'; // Default to cost-effective model
        $this->rateLimitDelay = 100; // Gemini has generous rate limits
    }

    public function getId(): string
    {
        return 'gemini';
    }

    public function getName(): string
    {
        return 'Google Gemini';
    }

    public function getAvailableModels(): array
    {
        return self::MODELS;
    }

    /**
     * Fetch models from Gemini API
     */
    protected function fetchModelsFromApi(): array
    {
        $url = self::API_BASE_URL . '?key=' . $this->apiKey;
        
        $response = wp_remote_get($url, [
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
        
        if (!isset($body['models']) || !is_array($body['models'])) {
            return [];
        }

        $models = [];
        foreach ($body['models'] as $model) {
            $name = $model['name'] ?? '';
            $displayName = $model['displayName'] ?? '';
            
            // Extract model ID from "models/gemini-1.5-pro" format
            $id = str_replace('models/', '', $name);
            
            // Only include vision-capable models
            foreach (self::VISION_MODEL_PATTERNS as $pattern) {
                if (str_contains($id, $pattern)) {
                    // Skip tuning or experimental models
                    if (str_contains($id, 'tuning') || str_contains($id, 'exp')) {
                        continue;
                    }
                    $models[$id] = $displayName ?: ucwords(str_replace('-', ' ', $id));
                    break;
                }
            }
        }

        // Sort by key
        krsort($models);

        return $models;
    }

    public function getEstimatedCostPerImage(): float
    {
        return self::COST_PER_IMAGE[$this->model] ?? 0.001;
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

            // List models to test API key
            $url = self::API_BASE_URL . '?key=' . $this->apiKey;
            
            $response = wp_remote_get($url, [
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
            $body = json_decode($responseBody, true);

            $this->logDebug('Response received', [
                'status' => $statusCode,
                'body' => $responseBody,
            ]);
            
            if ($statusCode === 400 || $statusCode === 401 || $statusCode === 403) {
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($body, $statusCode),
                ];
            }

            if ($statusCode !== 200) {
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

        // Build parts array
        $parts = [];

        // Add image
        if ($image['type'] === 'url') {
            // Gemini can handle URLs for public images
            $parts[] = [
                'fileData' => [
                    'mimeType' => $this->getMimeTypeFromUrl($image['data']),
                    'fileUri' => $image['data'],
                ],
            ];
        } else {
            // Extract base64 data from data URI
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $image['data'], $matches)) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $matches[1],
                        'data' => $matches[2],
                    ],
                ];
            }
        }

        // Add text prompt
        $parts[] = [
            'text' => $prompt,
        ];

        $body = [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2000,
            ],
        ];

        $url = self::API_BASE_URL . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $this->log('info', 'Analyzing image with Gemini', ['model' => $this->model]);

        $response = $this->makeRequestGemini($url, $headers, $body);

        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new AIProviderException(
                'Invalid response structure from Gemini',
                AIProviderException::ERROR_PARSE
            );
        }

        $content = $response['candidates'][0]['content']['parts'][0]['text'];
        
        return $this->parseJsonResponse($content);
    }

    /**
     * Make request to Gemini API (different error handling)
     */
    private function makeRequestGemini(string $url, array $headers, array $body): array
    {
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 120,
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
        $data = json_decode($responseBody, true);

        // Handle rate limiting
        if ($statusCode === 429) {
            throw AIProviderException::rateLimited(60);
        }

        // Handle auth errors
        if ($statusCode === 400 || $statusCode === 403) {
            $message = $data['error']['message'] ?? 'Invalid API key';
            if (str_contains(strtolower($message), 'api key')) {
                throw AIProviderException::invalidApiKey($this->getName());
            }
            throw new AIProviderException(
                "API error: {$message}",
                AIProviderException::ERROR_UNKNOWN,
                false,
                0,
                $statusCode
            );
        }

        // Handle other errors
        if ($statusCode >= 400) {
            $message = $data['error']['message'] ?? $responseBody;
            throw new AIProviderException(
                "API error ({$statusCode}): {$message}",
                AIProviderException::ERROR_UNKNOWN,
                $statusCode >= 500,
                0,
                $statusCode
            );
        }

        return $data ?? [];
    }

    /**
     * Get MIME type from URL
     */
    private function getMimeTypeFromUrl(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

