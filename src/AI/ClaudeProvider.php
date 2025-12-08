<?php
/**
 * Anthropic Claude Vision Provider
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;

/**
 * Anthropic Claude Vision implementation
 */
final class ClaudeProvider extends AbstractAIProvider
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /** @var array<string, string> Available models (updated Dec 2024) */
    private const MODELS = [
        'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (Latest, best quality)',
        'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Great quality)',
        'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Fast, cheap)',
        'claude-3-opus-20240229' => 'Claude 3 Opus (Most capable)',
        'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)',
    ];

    /** @var array<string, float> Cost per image estimate in USD */
    private const COST_PER_IMAGE = [
        'claude-sonnet-4-20250514' => 0.004,
        'claude-3-5-sonnet-20241022' => 0.004,
        'claude-3-5-haiku-20241022' => 0.001,
        'claude-3-opus-20240229' => 0.02,
        'claude-3-haiku-20240307' => 0.0005,
    ];

    public function __construct(Encryption $encryption, ?Logger $logger = null)
    {
        parent::__construct($encryption, $logger);
        $this->model = 'claude-3-5-haiku-20241022'; // Default to cost-effective model
        $this->rateLimitDelay = 500;
    }

    public function getId(): string
    {
        return 'claude';
    }

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    public function getAvailableModels(): array
    {
        return self::MODELS;
    }

    public function getEstimatedCostPerImage(): float
    {
        return self::COST_PER_IMAGE[$this->model] ?? 0.004;
    }

    /**
     * Get configuration for test connection
     */
    protected function getTestConnectionConfig(): array
    {
        return [
            'url' => self::API_URL,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'model' => $this->model,
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hi'],
                ],
            ],
        ];
    }

    public function analyzeImage(string $imageSource, string $language = 'en', array $customPrompts = []): array
    {
        if (!$this->isConfigured()) {
            throw AIProviderException::invalidApiKey($this->getName());
        }

        $image = $this->prepareImage($imageSource);
        $prompt = $this->buildAnalysisPrompt($language, $customPrompts);

        // Build content array
        $content = [];

        // Add image - Claude requires base64 for images
        if ($image['type'] === 'url') {
            // Claude doesn't support URLs directly, fetch and convert
            $imageData = $this->fetchImageAsBase64($image['data']);
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $imageData['mime_type'],
                    'data' => $imageData['data'],
                ],
            ];
        } else {
            // Extract base64 data from data URI
            $base64Data = $image['data'];
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $matches[1],
                        'data' => $matches[2],
                    ],
                ];
            }
        }

        // Add text prompt
        $content[] = [
            'type' => 'text',
            'text' => $prompt,
        ];

        $body = [
            'model' => $this->model,
            'max_tokens' => 1000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        ];

        $this->log('info', 'Analyzing image with Claude', ['model' => $this->model]);

        $response = $this->makeRequest(self::API_URL, $headers, $body);

        if (!isset($response['content'][0]['text'])) {
            throw new AIProviderException(
                'Invalid response structure from Claude',
                AIProviderException::ERROR_PARSE
            );
        }

        $content = $response['content'][0]['text'];
        
        return $this->parseJsonResponse($content);
    }

    /**
     * Fetch image from URL and convert to base64
     */
    private function fetchImageAsBase64(string $url): array
    {
        $response = wp_remote_get($url, [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new AIProviderException(
                'Failed to fetch image: ' . $response->get_error_message(),
                AIProviderException::ERROR_INVALID_IMAGE
            );
        }

        $body = wp_remote_retrieve_body($response);
        $contentType = wp_remote_retrieve_header($response, 'content-type');
        
        // Default to JPEG if content type not detected
        if (empty($contentType) || !str_starts_with($contentType, 'image/')) {
            $contentType = 'image/jpeg';
        }

        return [
            'data' => base64_encode($body),
            'mime_type' => $contentType,
        ];
    }
}

