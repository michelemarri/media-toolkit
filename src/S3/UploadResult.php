<?php
/**
 * Upload result value object
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\S3;

readonly class UploadResult
{
    public function __construct(
        public bool $success,
        public string $s3_key,
        public string $url,
        public ?string $error = null,
    ) {}
}

