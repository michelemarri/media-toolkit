<?php
/**
 * Upload Result DTO
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

/**
 * Result object for upload operations
 */
readonly class UploadResult
{
    public function __construct(
        public bool $success,
        public string $key,
        public string $url,
        public ?string $error = null,
        public ?StorageProvider $provider = null,
    ) {}

    /**
     * Create a successful result
     */
    public static function success(string $key, string $url, ?StorageProvider $provider = null): self
    {
        return new self(
            success: true,
            key: $key,
            url: $url,
            error: null,
            provider: $provider,
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(string $error, string $key = ''): self
    {
        return new self(
            success: false,
            key: $key,
            url: '',
            error: $error,
            provider: null,
        );
    }
}

