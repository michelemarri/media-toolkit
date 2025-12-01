<?php
/**
 * Failed operation structure
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Error;

readonly class FailedOperation
{
    public function __construct(
        public string $operation,
        public int $attachment_id,
        public string $file_path,
        public string $error_code,
        public string $error_message,
        public int $retry_count,
        public int $created_at,
    ) {}

    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'attachment_id' => $this->attachment_id,
            'file_path' => $this->file_path,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'retry_count' => $this->retry_count,
            'created_at' => $this->created_at,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            operation: $data['operation'],
            attachment_id: (int) $data['attachment_id'],
            file_path: $data['file_path'],
            error_code: $data['error_code'],
            error_message: $data['error_message'],
            retry_count: (int) $data['retry_count'],
            created_at: (int) $data['created_at'],
        );
    }
}

