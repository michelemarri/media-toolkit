<?php
/**
 * AI Provider Exception
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

/**
 * Exception thrown by AI providers
 */
class AIProviderException extends \Exception
{
    public const ERROR_INVALID_API_KEY = 'invalid_api_key';
    public const ERROR_RATE_LIMITED = 'rate_limited';
    public const ERROR_QUOTA_EXCEEDED = 'quota_exceeded';
    public const ERROR_INVALID_IMAGE = 'invalid_image';
    public const ERROR_NETWORK = 'network_error';
    public const ERROR_PARSE = 'parse_error';
    public const ERROR_UNKNOWN = 'unknown';

    private string $errorType;
    private bool $isRetryable;
    private int $retryAfter;

    public function __construct(
        string $message,
        string $errorType = self::ERROR_UNKNOWN,
        bool $isRetryable = false,
        int $retryAfter = 0,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->isRetryable = $isRetryable;
        $this->retryAfter = $retryAfter;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Create from rate limit error
     */
    public static function rateLimited(int $retryAfter = 60): self
    {
        return new self(
            sprintf('Rate limited. Retry after %d seconds.', $retryAfter),
            self::ERROR_RATE_LIMITED,
            true,
            $retryAfter,
            429
        );
    }

    /**
     * Create from invalid API key error
     */
    public static function invalidApiKey(string $provider): self
    {
        return new self(
            sprintf('Invalid API key for %s provider.', $provider),
            self::ERROR_INVALID_API_KEY,
            false,
            0,
            401
        );
    }

    /**
     * Create from quota exceeded error
     */
    public static function quotaExceeded(string $provider): self
    {
        return new self(
            sprintf('API quota exceeded for %s provider.', $provider),
            self::ERROR_QUOTA_EXCEEDED,
            false,
            0,
            402
        );
    }
}

