<?php
/**
 * Image Editor class for handling image editing operations
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\Storage\UploadResult;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\CDN\CDN_Manager;

/**
 * Handles WordPress image editor operations (crop, rotate, scale, flip)
 */
final class Image_Editor
{
    private StorageInterface $storage;
    private ?CDN_Manager $cdn_manager;
    private Logger $logger;
    private History $history;
    private Settings $settings;

    public function __construct(
        StorageInterface $storage,
        ?CDN_Manager $cdn_manager,
        Logger $logger,
        History $history,
        Settings $settings
    ) {
        $this->storage = $storage;
        $this->cdn_manager = $cdn_manager;
        $this->logger = $logger;
        $this->history = $history;
        $this->settings = $settings;

        $this->register_hooks();
    }

    /**
     * Register image editor hooks
     */
    private function register_hooks(): void
    {
        // Hook into image editor save - this fires AFTER WordPress saves the file
        add_filter('wp_save_image_editor_file', [$this, 'handle_save_editor_file'], 10, 5);
        
        // Hook into attachment metadata update (happens after editing)
        add_filter('wp_update_attachment_metadata', [$this, 'handle_metadata_update'], 10, 2);
        
        // Hook into image editing completion
        add_action('wp_ajax_image-editor', [$this, 'before_image_editor'], 1);
    }

    /**
     * Before image editor AJAX action
     */
    public function before_image_editor(): void
    {
        // Ensure local file exists for editing if only on S3
        if (!isset($_POST['postid'])) {
            return;
        }

        $attachment_id = (int) $_POST['postid'];
        
        // Download from S3 if needed
        $this->ensure_local_file($attachment_id);
    }

    /**
     * Ensure local file exists (download from S3 if needed)
     */
    private function ensure_local_file(int $attachment_id): void
    {
        $file = get_attached_file($attachment_id);
        
        if (file_exists($file)) {
            return;
        }

        // File doesn't exist locally - download from S3
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($s3_key)) {
            return;
        }

        $this->logger->info(
            'image_editor',
            'Downloading file from S3 for editing',
            $attachment_id,
            basename($file)
        );

        // Create directory if needed
        $dir = dirname($file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Download from S3
        $downloaded = $this->storage->download_file($s3_key, $file, $attachment_id);
        
        if (!$downloaded) {
            $this->logger->error(
                'image_editor',
                'Failed to download file from S3 for editing',
                $attachment_id,
                basename($file)
            );
        }
    }

    /**
     * Handle image editor file save
     */
    public function handle_save_editor_file(
        ?array $override,
        string $filename,
        \WP_Image_Editor $image,
        string $mime_type,
        int $attachment_id
    ): ?array {
        // We don't override WordPress's save behavior
        // The sync happens in handle_metadata_update after WordPress finishes
        return $override;
    }

    /**
     * Handle attachment metadata update (triggered after image editing)
     */
    public function handle_metadata_update(array $metadata, int $attachment_id): array
    {
        // Check if this attachment is offloaded
        $is_offloaded = get_post_meta($attachment_id, '_media_toolkit_migrated', true);
        
        if (!$is_offloaded) {
            return $metadata;
        }

        // Check if we're in an image editing context
        if (!doing_action('wp_ajax_image-editor')) {
            return $metadata;
        }

        $this->logger->info(
            'image_editor',
            'Image edited, syncing to S3',
            $attachment_id
        );

        // Sync all sizes to S3
        $this->sync_all_sizes($attachment_id, $metadata);

        return $metadata;
    }

    /**
     * Sync edited image to S3
     */
    public function sync_edited_image(int $attachment_id, string $file_path): ?UploadResult
    {
        if (!file_exists($file_path)) {
            $this->logger->error(
                'image_editor',
                'Edited file not found',
                $attachment_id,
                basename($file_path)
            );
            return null;
        }

        // Get old S3 key for cache invalidation
        $old_s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);

        // Upload new file
        $result = $this->storage->upload_file($file_path, $attachment_id);

        if (!$result->success) {
            $this->logger->error(
                'image_editor',
                "Failed to upload edited image: {$result->error}",
                $attachment_id,
                basename($file_path)
            );
            return null;
        }

        // Update meta
        update_post_meta($attachment_id, '_media_toolkit_key', $result->key);
        update_post_meta($attachment_id, '_media_toolkit_url', $result->url);

        // Record in history
        $this->history->record(
            HistoryAction::EDITED,
            $attachment_id,
            $file_path,
            $result->key,
            filesize($file_path) ?: 0,
            ['previous_key' => $old_s3_key]
        );

        $this->logger->success(
            'image_editor',
            'Edited image synced to S3',
            $attachment_id,
            basename($file_path)
        );

        // Delete old S3 file if key changed
        if (!empty($old_s3_key) && $old_s3_key !== $result->key) {
            $this->storage->delete_file($old_s3_key, $attachment_id);
        }

        return $result;
    }

    /**
     * Sync all sizes after image edit
     */
    public function sync_all_sizes(int $attachment_id, ?array $metadata = null): void
    {
        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }
        
        if (empty($metadata['file'])) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $main_file = $base_dir . '/' . $metadata['file'];
        $file_dir = dirname($main_file);

        // Collect all S3 keys for cache invalidation
        $keys_to_invalidate = [];

        // Get old keys for invalidation
        $old_main_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        $old_thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true) ?: [];

        if (!empty($old_main_key)) {
            $keys_to_invalidate[] = '/' . $old_main_key;
        }
        foreach ($old_thumb_keys as $key) {
            $keys_to_invalidate[] = '/' . $key;
        }

        // Sync main file
        $result = $this->sync_edited_image($attachment_id, $main_file);
        
        if ($result && $result->success) {
            $keys_to_invalidate[] = '/' . $result->key;
        }

        // Sync thumbnails
        if (!empty($metadata['sizes'])) {
            $thumb_keys = [];
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $thumb_file = $file_dir . '/' . $size_data['file'];
                
                if (file_exists($thumb_file)) {
                    $thumb_result = $this->storage->upload_file($thumb_file, $attachment_id);
                    
                    if ($thumb_result->success) {
                        $thumb_keys[$size_name] = $thumb_result->key;
                        $keys_to_invalidate[] = '/' . $thumb_result->key;
                    }
                }
            }

            // Update thumbnail keys
            update_post_meta($attachment_id, '_media_toolkit_thumb_keys', $thumb_keys);
        }

        // Invalidate CDN cache (Cloudflare/CloudFront)
        if ($this->cdn_manager !== null && $this->cdn_manager->is_available() && !empty($keys_to_invalidate)) {
            $this->cdn_manager->queue_invalidation(array_unique($keys_to_invalidate));
            
            $this->logger->info(
                'image_editor',
                'Queued CDN cache invalidation for ' . count($keys_to_invalidate) . ' files',
                $attachment_id
            );
        }
    }

    /**
     * Handle image restore to original
     */
    public function handle_restore(int $attachment_id): void
    {
        $backup_file = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        
        if (empty($backup_file)) {
            return;
        }

        $this->logger->info(
            'image_editor',
            'Image restored to original',
            $attachment_id
        );

        // Sync restored image
        $this->sync_all_sizes($attachment_id);
    }

    /**
     * Delete backup images from S3
     */
    public function cleanup_backups(int $attachment_id): void
    {
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        
        if (empty($backup_sizes) || !is_array($backup_sizes)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (empty($metadata['file'])) {
            return;
        }

        $base_path = dirname($metadata['file']);
        $s3_base = rtrim($this->storage->generate_s3_key($upload_dir['basedir'] . '/' . $base_path), '/');

        $keys_to_delete = [];
        
        foreach ($backup_sizes as $size_name => $size_data) {
            if (isset($size_data['file'])) {
                $keys_to_delete[] = $s3_base . '/' . $size_data['file'];
            }
        }

        if (!empty($keys_to_delete)) {
            $this->storage->delete_files($keys_to_delete, $attachment_id);
            
            $this->logger->info(
                'image_editor',
                'Cleaned up ' . count($keys_to_delete) . ' backup files from S3',
                $attachment_id
            );
        }
    }
}
