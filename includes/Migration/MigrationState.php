<?php
/**
 * Migration state value object
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Migration;

class MigrationState
{
    public function __construct(
        public MigrationStatus $status = MigrationStatus::IDLE,
        public int $total_files = 0,
        public int $processed = 0,
        public int $failed = 0,
        public int $current_batch = 0,
        public int $last_attachment_id = 0,
        public ?int $started_at = null,
        public ?int $updated_at = null,
        public array $errors = [],
        public bool $remove_local = false,
        public int $batch_size = 25,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'total_files' => $this->total_files,
            'processed' => $this->processed,
            'failed' => $this->failed,
            'current_batch' => $this->current_batch,
            'last_attachment_id' => $this->last_attachment_id,
            'started_at' => $this->started_at,
            'updated_at' => $this->updated_at,
            'errors' => $this->errors,
            'remove_local' => $this->remove_local,
            'batch_size' => $this->batch_size,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: MigrationStatus::tryFrom($data['status'] ?? 'idle') ?? MigrationStatus::IDLE,
            total_files: (int) ($data['total_files'] ?? 0),
            processed: (int) ($data['processed'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            current_batch: (int) ($data['current_batch'] ?? 0),
            last_attachment_id: (int) ($data['last_attachment_id'] ?? 0),
            started_at: $data['started_at'] ?? null,
            updated_at: $data['updated_at'] ?? null,
            errors: $data['errors'] ?? [],
            remove_local: (bool) ($data['remove_local'] ?? false),
            batch_size: (int) ($data['batch_size'] ?? 25),
        );
    }
}

