<?php
/**
 * Upload Handler class for intercepting WordPress uploads
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Error\Error_Handler;
use Metodo\MediaToolkit\CDN\CDN_Manager;

/**
 * Handles WordPress upload/sideload hooks to redirect to storage
 */
final class Upload_Handler
{
    private StorageInterface $storage;
    private Settings $settings;
    private Logger $logger;
    private History $history;
    private Error_Handler $error_handler;
    private ?CDN_Manager $cdn_manager;

    public function __construct(
        StorageInterface $storage,
        Settings $settings,
        Logger $logger,
        History $history,
        Error_Handler $error_handler,
        ?CDN_Manager $cdn_manager = null
    ) {
        $this->storage = $storage;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->history = $history;
        $this->error_handler = $error_handler;
        $this->cdn_manager = $cdn_manager;

        $this->register_hooks();
    }

    /**
     * Register all upload-related hooks
     */
    private function register_hooks(): void
    {
        // Upload hooks
        add_filter('wp_handle_upload_prefilter', [$this, 'prefilter_upload']);
        add_filter('wp_handle_upload', [$this, 'handle_upload'], 10, 2);
        
        // Sideload hooks (upload from URL)
        add_filter('wp_handle_sideload_prefilter', [$this, 'prefilter_upload']);
        add_filter('wp_handle_sideload', [$this, 'handle_upload'], 10, 2);
        
        // Attachment metadata
        add_filter('wp_generate_attachment_metadata', [$this, 'handle_attachment_metadata'], 10, 3);
        add_filter('wp_update_attachment_metadata', [$this, 'handle_update_metadata'], 10, 2);
        
        // Thumbnail sizes
        add_filter('intermediate_image_sizes_advanced', [$this, 'filter_image_sizes'], 10, 3);
        
        // Delete attachment
        add_action('delete_attachment', [$this, 'handle_delete_attachment'], 10, 1);
        add_filter('wp_delete_file', [$this, 'handle_delete_file']);
        
        // Path hooks
        add_filter('get_attached_file', [$this, 'filter_attached_file'], 10, 2);
        add_filter('update_attached_file', [$this, 'filter_update_attached_file'], 10, 2);
    }

    /**
     * Pre-filter upload (validation)
     */
    public function prefilter_upload(array $file): array
    {
        // Can add validation here if needed
        // Return $file unchanged or add 'error' key to reject
        return $file;
    }

    /**
     * Handle upload after WordPress processes it locally
     */
    public function handle_upload(array $upload, string $context = 'upload'): array
    {
        // Skip if there was an error
        if (isset($upload['error'])) {
            return $upload;
        }

        // Skip if no file path
        if (empty($upload['file'])) {
            return $upload;
        }

        $file_path = $upload['file'];
        
        // Upload to S3
        $result = $this->storage->upload_file($file_path);

        if (!$result->success) {
            $this->logger->error(
                'upload',
                "Failed to upload to storage: {$result->error}",
                null,
                basename($file_path)
            );
            
            // Don't block WordPress upload, just log the error
            // The file will be retried later
            return $upload;
        }

        $this->logger->success(
            'upload',
            'File uploaded to storage',
            null,
            basename($file_path),
            ['s3_key' => $result->key]
        );

        // Record in history
        $this->history->record(
            HistoryAction::UPLOADED,
            null,
            $file_path,
            $result->key,
            filesize($file_path) ?: 0
        );

        // Optionally remove local file
        if ($this->settings->should_remove_local_files()) {
            @unlink($file_path);
        }

        // Update URL to CloudFront/S3
        if (!empty($result->url)) {
            $upload['url'] = $result->url;
        }

        // Store S3 key in a way we can retrieve later
        $upload['s3_key'] = $result->key;

        return $upload;
    }

    /**
     * Handle attachment metadata generation (thumbnails)
     */
    public function handle_attachment_metadata(array $metadata, int $attachment_id, string $context = 'create'): array
    {
        if (empty($metadata['file'])) {
            return $metadata;
        }

        // Get the main file path
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $main_file = $base_dir . '/' . $metadata['file'];

        // Upload main file if not already on S3
        $main_s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($main_s3_key) && file_exists($main_file)) {
            $result = $this->storage->upload_file($main_file, $attachment_id);
            
            if ($result->success) {
                update_post_meta($attachment_id, '_media_toolkit_key', $result->key);
                update_post_meta($attachment_id, '_media_toolkit_url', $result->url);
                update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
                if ($result->provider !== null) {
                    update_post_meta($attachment_id, '_media_toolkit_provider', $result->provider->value);
                }
                
                $this->history->record(
                    HistoryAction::UPLOADED,
                    $attachment_id,
                    $main_file,
                    $result->key,
                    filesize($main_file) ?: 0
                );

                if ($this->settings->should_remove_local_files()) {
                    @unlink($main_file);
                }
            }
        }

        // Upload thumbnails
        if (!empty($metadata['sizes'])) {
            $file_dir = dirname($main_file);
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $thumb_file = $file_dir . '/' . $size_data['file'];
                
                if (file_exists($thumb_file)) {
                    $result = $this->storage->upload_file($thumb_file, $attachment_id);
                    
                    if ($result->success) {
                        // Store thumbnail S3 keys
                        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true) ?: [];
                        $thumb_keys[$size_name] = $result->key;
                        update_post_meta($attachment_id, '_media_toolkit_thumb_keys', $thumb_keys);

                        if ($this->settings->should_remove_local_files()) {
                            @unlink($thumb_file);
                        }
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Handle metadata update
     */
    public function handle_update_metadata(array $metadata, int $attachment_id): array
    {
        // Re-process thumbnails on update
        return $this->handle_attachment_metadata($metadata, $attachment_id, 'update');
    }

    /**
     * Filter image sizes (can be used to limit sizes)
     */
    public function filter_image_sizes(array $sizes, array $metadata, int $attachment_id): array
    {
        // Return all sizes by default
        // Can be used to filter out certain sizes if needed
        return $sizes;
    }

    /**
     * Handle attachment deletion
     */
    public function handle_delete_attachment(int $attachment_id): void
    {
        // Get S3 keys to delete
        $s3_keys = [];
        
        // Main file
        $main_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($main_key)) {
            $s3_keys[] = $main_key;
        }

        // Thumbnails
        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true);
        if (is_array($thumb_keys)) {
            $s3_keys = array_merge($s3_keys, array_values($thumb_keys));
        }

        if (empty($s3_keys)) {
            return;
        }

        // Delete from S3
        $success = $this->storage->delete_files($s3_keys, $attachment_id);

        if ($success) {
            $this->logger->success(
                'delete',
                'Attachment files deleted from storage',
                $attachment_id,
                null,
                ['keys' => $s3_keys]
            );

            // Record in history
            foreach ($s3_keys as $key) {
                $this->history->record(
                    HistoryAction::DELETED,
                    $attachment_id,
                    null,
                    $key
                );
            }

            // Queue CDN cache invalidation
            if ($this->cdn_manager !== null && $this->cdn_manager->is_available()) {
                $paths = array_map(fn($key) => '/' . ltrim($key, '/'), $s3_keys);
                $this->cdn_manager->queue_invalidation($paths);
            }
        } else {
            $this->logger->error(
                'delete',
                'Failed to delete attachment files from storage',
                $attachment_id,
                null,
                ['keys' => $s3_keys]
            );
        }
    }

    /**
     * Handle individual file deletion
     */
    public function handle_delete_file(string $file): string
    {
        // This hook is called for each file being deleted
        // We'll handle bulk deletion in handle_delete_attachment instead
        return $file;
    }

    /**
     * Filter attached file path
     */
    public function filter_attached_file(string $file, int $attachment_id): string
    {
        // If file doesn't exist locally but is on S3, return a virtual path
        // that the S3 client can recognize
        if (!file_exists($file)) {
            $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
            if (!empty($s3_key)) {
                // Return the local path anyway - WordPress needs a local path
                // The actual file is on S3
                return $file;
            }
        }

        return $file;
    }

    /**
     * Filter update attached file
     */
    public function filter_update_attached_file(string $file, int $attachment_id): string
    {
        // Store the local path reference
        return $file;
    }

    /**
     * Check if an attachment is offloaded to S3
     */
    public function is_offloaded(int $attachment_id): bool
    {
        return !empty(get_post_meta($attachment_id, '_media_toolkit_migrated', true));
    }

    /**
     * Get S3 URL for an attachment
     */
    public function get_s3_url(int $attachment_id): string
    {
        return get_post_meta($attachment_id, '_media_toolkit_url', true) ?: '';
    }

    /**
     * Get S3 key for an attachment
     */
    public function get_s3_key(int $attachment_id): string
    {
        return get_post_meta($attachment_id, '_media_toolkit_key', true) ?: '';
    }

    /**
     * Manually upload an existing attachment to S3
     * 
     * @param int $attachment_id The attachment ID
     * @param bool $force Re-upload even if already on S3
     * @return array{success: bool, message: string, s3_key?: string, s3_url?: string}
     */
    public function upload_attachment(int $attachment_id, bool $force = false): array
    {
        // Check if already migrated
        if (!$force && $this->is_offloaded($attachment_id)) {
            return [
                'success' => true,
                'message' => 'Already on cloud',
                's3_key' => $this->get_s3_key($attachment_id),
                's3_url' => $this->get_s3_url($attachment_id),
            ];
        }

        $file = get_attached_file($attachment_id);
        
        if (!file_exists($file)) {
            return [
                'success' => false,
                'message' => 'Local file not found: ' . basename($file),
            ];
        }

        // Upload main file
        $result = $this->storage->upload_file($file, $attachment_id);
        
        if (!$result->success) {
            return [
                'success' => false,
                'message' => $result->error ?? 'Upload failed',
            ];
        }

        // Save metadata
        update_post_meta($attachment_id, '_media_toolkit_key', $result->key);
        update_post_meta($attachment_id, '_media_toolkit_url', $result->url);
        update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
        if ($result->provider !== null) {
            update_post_meta($attachment_id, '_media_toolkit_provider', $result->provider->value);
        }

        $this->history->record(
            HistoryAction::UPLOADED,
            $attachment_id,
            $file,
            $result->key,
            filesize($file) ?: 0
        );

        $this->logger->success('upload', 'File uploaded to S3', $attachment_id, basename($file));

        // Upload thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!empty($metadata['sizes'])) {
            $file_dir = dirname($file);
            $thumb_keys = [];
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $thumb_file = $file_dir . '/' . $size_data['file'];
                
                if (file_exists($thumb_file)) {
                    $thumb_result = $this->storage->upload_file($thumb_file, $attachment_id);
                    
                    if ($thumb_result->success) {
                        $thumb_keys[$size_name] = $thumb_result->key;
                    }
                }
            }
            
            if (!empty($thumb_keys)) {
                update_post_meta($attachment_id, '_media_toolkit_thumb_keys', $thumb_keys);
            }
        }

        // Remove local files if configured
        if ($this->settings->should_remove_local_files()) {
            @unlink($file);
            
            if (!empty($metadata['sizes'])) {
                $file_dir = dirname($file);
                foreach ($metadata['sizes'] as $size_data) {
                    @unlink($file_dir . '/' . $size_data['file']);
                }
            }
        }

        return [
            'success' => true,
            'message' => 'File uploaded to storage',
            's3_key' => $result->key,
            's3_url' => $result->url,
        ];
    }
}

