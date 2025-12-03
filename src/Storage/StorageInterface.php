<?php
/**
 * Storage Interface - Common contract for all storage providers
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

/**
 * Interface for object storage providers (S3, R2, Spaces, B2, Wasabi)
 */
interface StorageInterface
{
    /**
     * Upload a file to storage
     *
     * @param string $file_path Local file path
     * @param int|null $attachment_id WordPress attachment ID
     * @param string|null $custom_key Custom storage key (optional)
     * @return UploadResult
     */
    public function upload_file(
        string $file_path,
        ?int $attachment_id = null,
        ?string $custom_key = null
    ): UploadResult;

    /**
     * Delete a file from storage
     *
     * @param string $key Storage key
     * @param int|null $attachment_id WordPress attachment ID
     * @return bool
     */
    public function delete_file(string $key, ?int $attachment_id = null): bool;

    /**
     * Delete multiple files from storage
     *
     * @param array<string> $keys Storage keys
     * @param int|null $attachment_id WordPress attachment ID
     * @return bool
     */
    public function delete_files(array $keys, ?int $attachment_id = null): bool;

    /**
     * Check if a file exists in storage
     *
     * @param string $key Storage key
     * @return bool
     */
    public function file_exists(string $key): bool;

    /**
     * Get public URL for a file
     *
     * @param string $key Storage key
     * @return string
     */
    public function get_file_url(string $key): string;

    /**
     * Generate storage key from local file path
     *
     * @param string $file_path Local file path
     * @return string
     */
    public function generate_key(string $file_path): string;

    /**
     * Test connection to storage provider
     *
     * @return array{credentials: array, bucket: array, permissions: array, cdn: array}
     */
    public function test_connection(): array;

    /**
     * Get storage statistics
     *
     * @return array{files: int, original_files: int, size: int, original_size: int, synced_at: string}|null
     */
    public function get_bucket_stats(): ?array;

    /**
     * Download a file from storage to local path
     *
     * @param string $key Storage key
     * @param string $local_path Local destination path
     * @param int|null $attachment_id WordPress attachment ID
     * @return bool
     */
    public function download_file(string $key, string $local_path, ?int $attachment_id = null): bool;

    /**
     * Copy a file within storage
     *
     * @param string $source_key Source storage key
     * @param string $dest_key Destination storage key
     * @return bool
     */
    public function copy_file(string $source_key, string $dest_key): bool;

    /**
     * List objects in storage (paginated)
     *
     * @param int $batch_size Number of objects to return
     * @param string|null $continuation_token Token for pagination
     * @return array{keys: array<string>, next_token: string|null, is_truncated: bool}|null
     */
    public function list_objects_batch(int $batch_size = 100, ?string $continuation_token = null): ?array;

    /**
     * Update object metadata (e.g., Cache-Control)
     *
     * @param string $key Storage key
     * @param int $cache_max_age Cache-Control max-age in seconds
     * @return bool
     */
    public function update_object_metadata(string $key, int $cache_max_age): bool;

    /**
     * Get the storage provider type
     *
     * @return StorageProvider
     */
    public function get_provider(): StorageProvider;

    /**
     * Get storage key from URL
     *
     * @param string $url Public URL
     * @return string Storage key or empty string if not recognized
     */
    public function get_key_from_url(string $url): string;

    /**
     * Reset client connection (needed when settings change)
     */
    public function reset_client(): void;
}

