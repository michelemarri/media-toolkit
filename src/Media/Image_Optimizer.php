<?php
/**
 * Image Optimizer class for compressing images
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Migration\Batch_Processor;
use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;

/**
 * Handles image optimization/compression for media files
 */
final class Image_Optimizer extends Batch_Processor
{
    private const SETTINGS_KEY = 'media_toolkit_optimize_settings';
    
    private ?S3_Client $s3_client;
    private History $history;

    public function __construct(
        Logger $logger,
        Settings $settings,
        ?S3_Client $s3_client = null,
        ?History $history = null
    ) {
        parent::__construct($logger, $settings, 'optimization');
        
        $this->s3_client = $s3_client;
        $this->history = $history ?? new History();
        
        // Register settings AJAX handler
        add_action('wp_ajax_media_toolkit_save_optimize_settings', [$this, 'ajax_save_settings']);
    }

    /**
     * Get processor display name
     */
    protected function get_processor_name(): string
    {
        return 'Image Optimization';
    }

    /**
     * Get optimization settings
     */
    public function get_optimization_settings(): array
    {
        $defaults = [
            'jpeg_quality' => 82,
            'png_compression' => 6,
            'strip_metadata' => true,
            'convert_to_webp' => false,
            'webp_quality' => 80,
            'skip_already_optimized' => true,
            'min_savings_percent' => 5,
            'max_file_size_mb' => 10,
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /**
     * Save optimization settings
     */
    public function save_optimization_settings(array $settings): bool
    {
        $sanitized = [
            'jpeg_quality' => max(1, min(100, (int) ($settings['jpeg_quality'] ?? 82))),
            'png_compression' => max(0, min(9, (int) ($settings['png_compression'] ?? 6))),
            'strip_metadata' => (bool) ($settings['strip_metadata'] ?? true),
            'convert_to_webp' => (bool) ($settings['convert_to_webp'] ?? false),
            'webp_quality' => max(1, min(100, (int) ($settings['webp_quality'] ?? 80))),
            'skip_already_optimized' => (bool) ($settings['skip_already_optimized'] ?? true),
            'min_savings_percent' => max(0, min(50, (int) ($settings['min_savings_percent'] ?? 5))),
            'max_file_size_mb' => max(1, min(100, (int) ($settings['max_file_size_mb'] ?? 10))),
        ];

        return update_option(self::SETTINGS_KEY, $sanitized);
    }

    /**
     * Get optimization statistics
     */
    public function get_stats(): array
    {
        global $wpdb;

        // Total image attachments
        $total_images = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'"
        );

        // Optimized images (have meta key)
        $optimized_images = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'attachment' 
                 AND p.post_mime_type LIKE 'image/%%'
                 AND pm.meta_key = %s",
                '_media_toolkit_optimized'
            )
        );

        // Total bytes saved
        $total_saved = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm
                 WHERE pm.meta_key = %s",
                '_media_toolkit_bytes_saved'
            )
        );

        $pending_images = $total_images - $optimized_images;
        $progress = $total_images > 0 ? round(($optimized_images / $total_images) * 100, 1) : 0;

        return [
            'total_images' => $total_images,
            'optimized_images' => $optimized_images,
            'pending_images' => $pending_images,
            'total_saved' => $total_saved,
            'total_saved_formatted' => size_format($total_saved),
            'progress_percentage' => $progress,
        ];
    }

    /**
     * Count pending items to optimize
     */
    protected function count_pending_items(array $options = []): int
    {
        global $wpdb;

        $mime_types = $this->get_supported_mime_types();
        $placeholders = implode(',', array_fill(0, count($mime_types), '%s'));

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_media_toolkit_optimized'
                 WHERE p.post_type = 'attachment' 
                 AND p.post_mime_type IN ($placeholders)
                 AND (pm.meta_value IS NULL OR pm.meta_value != '1')",
                ...$mime_types
            )
        );
    }

    /**
     * Get pending items for optimization
     */
    protected function get_pending_items(int $limit, int $after_id, array $options = []): array
    {
        global $wpdb;

        $mime_types = $this->get_supported_mime_types();
        $placeholders = implode(',', array_fill(0, count($mime_types), '%s'));

        $query_args = array_merge($mime_types, [$after_id, $limit]);

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_media_toolkit_optimized'
                 WHERE p.post_type = 'attachment' 
                 AND p.post_mime_type IN ($placeholders)
                 AND (pm.meta_value IS NULL OR pm.meta_value != '1')
                 AND p.ID > %d
                 ORDER BY p.ID ASC
                 LIMIT %d",
                ...$query_args
            )
        );
    }

    /**
     * Get item ID from item
     */
    protected function get_item_id($item): int
    {
        return (int) $item;
    }

    /**
     * Process a single item (optimize an image)
     */
    protected function process_item($item, array $options = []): array
    {
        $attachment_id = (int) $item;
        
        return $this->optimize_attachment($attachment_id, $options);
    }

    /**
     * Optimize a single attachment
     */
    public function optimize_attachment(int $attachment_id, array $options = []): array
    {
        $file = get_attached_file($attachment_id);
        
        if (empty($file)) {
            return [
                'success' => false,
                'error' => 'No file path found',
            ];
        }

        // Check if file exists locally, if not try to download from S3
        if (!file_exists($file)) {
            $downloaded = $this->ensure_local_file($attachment_id, $file);
            if (!$downloaded) {
                return [
                    'success' => false,
                    'error' => 'File does not exist and could not be downloaded from S3',
                ];
            }
        }

        $settings = array_merge($this->get_optimization_settings(), $options);
        $mime_type = get_post_mime_type($attachment_id);
        $original_size = filesize($file);

        // Check max file size
        $max_size = ($settings['max_file_size_mb'] ?? 10) * 1024 * 1024;
        if ($original_size > $max_size) {
            // Mark as optimized but skipped
            update_post_meta($attachment_id, '_media_toolkit_optimized', '1');
            update_post_meta($attachment_id, '_media_toolkit_optimize_skipped', 'file_too_large');
            
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'File too large',
            ];
        }

        // Optimize based on mime type
        $result = match ($mime_type) {
            'image/jpeg', 'image/jpg' => $this->optimize_jpeg($file, $settings),
            'image/png' => $this->optimize_png($file, $settings),
            'image/gif' => $this->optimize_gif($file, $settings),
            'image/webp' => $this->optimize_webp($file, $settings),
            default => ['success' => false, 'error' => 'Unsupported image type'],
        };

        if (!$result['success']) {
            return $result;
        }

        clearstatcache(true, $file);
        $new_size = filesize($file);
        $bytes_saved = $original_size - $new_size;
        $percent_saved = $original_size > 0 ? round(($bytes_saved / $original_size) * 100, 1) : 0;

        // Check minimum savings threshold
        $min_savings = $settings['min_savings_percent'] ?? 5;
        if ($percent_saved < $min_savings && $bytes_saved > 0) {
            // Restore original if savings too small? No, keep optimized version
        }

        // Update meta
        update_post_meta($attachment_id, '_media_toolkit_optimized', '1');
        update_post_meta($attachment_id, '_media_toolkit_original_size', $original_size);
        update_post_meta($attachment_id, '_media_toolkit_optimized_size', $new_size);
        update_post_meta($attachment_id, '_media_toolkit_bytes_saved', max(0, $bytes_saved));
        update_post_meta($attachment_id, '_media_toolkit_optimized_at', time());

        // Re-upload to S3 if already offloaded
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($s3_key) && $this->s3_client !== null) {
            $upload_result = $this->s3_client->upload_file($file, $attachment_id);
            
            if ($upload_result->success) {
                $this->logger->success(
                    'optimization',
                    'Optimized image re-uploaded to S3',
                    $attachment_id,
                    basename($file),
                    ['saved' => size_format($bytes_saved)]
                );
            }
        }

        // Also optimize thumbnails
        $this->optimize_thumbnails($attachment_id, $settings);

        // Record in history
        $this->history->record(
            HistoryAction::OPTIMIZED,
            $attachment_id,
            $file,
            $s3_key,
            $bytes_saved,
            [
                'original_size' => $original_size,
                'optimized_size' => $new_size,
                'percent_saved' => $percent_saved,
            ]
        );

        $this->logger->success(
            'optimization',
            "Image optimized: saved {$percent_saved}% ({$bytes_saved} bytes)",
            $attachment_id,
            basename($file)
        );

        return [
            'success' => true,
            'original_size' => $original_size,
            'optimized_size' => $new_size,
            'bytes_saved' => $bytes_saved,
            'percent_saved' => $percent_saved,
        ];
    }

    /**
     * Optimize JPEG image
     */
    private function optimize_jpeg(string $file, array $settings): array
    {
        $quality = $settings['jpeg_quality'] ?? 82;
        $strip_metadata = $settings['strip_metadata'] ?? true;

        $editor = wp_get_image_editor($file);
        
        if (is_wp_error($editor)) {
            return [
                'success' => false,
                'error' => $editor->get_error_message(),
            ];
        }

        $editor->set_quality($quality);
        
        $result = $editor->save($file, 'image/jpeg');
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        // Strip EXIF metadata if requested (using GD directly)
        if ($strip_metadata && function_exists('imagecreatefromjpeg')) {
            $this->strip_jpeg_metadata($file, $quality);
        }

        return ['success' => true];
    }

    /**
     * Strip JPEG metadata by re-encoding
     */
    private function strip_jpeg_metadata(string $file, int $quality): bool
    {
        $image = @imagecreatefromjpeg($file);
        
        if ($image === false) {
            return false;
        }

        $result = imagejpeg($image, $file, $quality);
        imagedestroy($image);
        
        return $result;
    }

    /**
     * Optimize PNG image
     */
    private function optimize_png(string $file, array $settings): array
    {
        $compression = $settings['png_compression'] ?? 6;

        $editor = wp_get_image_editor($file);
        
        if (is_wp_error($editor)) {
            return [
                'success' => false,
                'error' => $editor->get_error_message(),
            ];
        }

        // PNG compression is 0-9 (0 = none, 9 = max)
        // WP_Image_Editor doesn't have direct PNG compression setting,
        // so we use GD directly for better control
        if (function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($file);
            
            if ($image !== false) {
                // Preserve transparency
                imagesavealpha($image, true);
                imagealphablending($image, false);
                
                // Save with compression
                imagepng($image, $file, $compression);
                imagedestroy($image);
                
                return ['success' => true];
            }
        }

        // Fallback to WP editor
        $result = $editor->save($file, 'image/png');
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        return ['success' => true];
    }

    /**
     * Optimize GIF image
     */
    private function optimize_gif(string $file, array $settings): array
    {
        // GIF optimization is limited - mainly just re-save
        // Animated GIFs should not be processed
        if ($this->is_animated_gif($file)) {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'Animated GIF',
            ];
        }

        $editor = wp_get_image_editor($file);
        
        if (is_wp_error($editor)) {
            return [
                'success' => false,
                'error' => $editor->get_error_message(),
            ];
        }

        $result = $editor->save($file, 'image/gif');
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        return ['success' => true];
    }

    /**
     * Optimize WebP image
     */
    private function optimize_webp(string $file, array $settings): array
    {
        $quality = $settings['webp_quality'] ?? 80;

        $editor = wp_get_image_editor($file);
        
        if (is_wp_error($editor)) {
            return [
                'success' => false,
                'error' => $editor->get_error_message(),
            ];
        }

        if (!$editor->supports_mime_type('image/webp')) {
            return [
                'success' => false,
                'error' => 'WebP not supported by image editor',
            ];
        }

        $editor->set_quality($quality);
        $result = $editor->save($file, 'image/webp');
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
            ];
        }

        return ['success' => true];
    }

    /**
     * Optimize thumbnails for an attachment
     */
    private function optimize_thumbnails(int $attachment_id, array $settings): void
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (empty($metadata['sizes'])) {
            return;
        }

        $file = get_attached_file($attachment_id);
        $file_dir = dirname($file);

        foreach ($metadata['sizes'] as $size_name => $size_data) {
            $thumb_file = $file_dir . '/' . $size_data['file'];
            
            if (!file_exists($thumb_file)) {
                continue;
            }

            $mime_type = $size_data['mime-type'] ?? '';
            
            $result = match ($mime_type) {
                'image/jpeg', 'image/jpg' => $this->optimize_jpeg($thumb_file, $settings),
                'image/png' => $this->optimize_png($thumb_file, $settings),
                'image/webp' => $this->optimize_webp($thumb_file, $settings),
                default => ['success' => false],
            };

            // Re-upload to S3 if needed
            if ($result['success'] && $this->s3_client !== null) {
                $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
                if (!empty($s3_key)) {
                    $this->s3_client->upload_file($thumb_file, $attachment_id);
                }
            }
        }
    }

    /**
     * Ensure local file exists (download from S3 if needed)
     */
    private function ensure_local_file(int $attachment_id, string $file): bool
    {
        if ($this->s3_client === null) {
            return false;
        }

        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($s3_key)) {
            return false;
        }

        // Create directory if needed
        $dir = dirname($file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        return $this->s3_client->download_file($s3_key, $file, $attachment_id);
    }

    /**
     * Check if GIF is animated
     */
    private function is_animated_gif(string $file): bool
    {
        $content = file_get_contents($file);
        
        if ($content === false) {
            return false;
        }

        $count = preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $content);
        
        return $count > 1;
    }

    /**
     * Get supported MIME types
     */
    private function get_supported_mime_types(): array
    {
        return [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
    }

    /**
     * Check server capabilities
     */
    public function get_server_capabilities(): array
    {
        $capabilities = [
            'gd' => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
            'webp_support' => false,
            'avif_support' => false,
            'max_memory' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        // Check WebP support
        if ($capabilities['gd'] && function_exists('imagewebp')) {
            $capabilities['webp_support'] = true;
        } elseif ($capabilities['imagick']) {
            $imagick = new \Imagick();
            $capabilities['webp_support'] = in_array('WEBP', $imagick->queryFormats('WEBP'));
        }

        // Check AVIF support
        if ($capabilities['gd'] && function_exists('imageavif')) {
            $capabilities['avif_support'] = true;
        } elseif ($capabilities['imagick']) {
            $imagick = new \Imagick();
            $capabilities['avif_support'] = in_array('AVIF', $imagick->queryFormats('AVIF'));
        }

        return $capabilities;
    }

    /**
     * Get start options from AJAX request
     */
    protected function get_start_options_from_request(): array
    {
        $settings = $this->get_optimization_settings();
        
        return [
            'batch_size' => isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 25,
            'jpeg_quality' => $settings['jpeg_quality'],
            'png_compression' => $settings['png_compression'],
            'strip_metadata' => $settings['strip_metadata'],
            'skip_already_optimized' => $settings['skip_already_optimized'],
        ];
    }

    /**
     * AJAX: Save optimization settings
     */
    public function ajax_save_settings(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $settings = [
            'jpeg_quality' => isset($_POST['jpeg_quality']) ? (int) $_POST['jpeg_quality'] : 82,
            'png_compression' => isset($_POST['png_compression']) ? (int) $_POST['png_compression'] : 6,
            'strip_metadata' => isset($_POST['strip_metadata']) && $_POST['strip_metadata'] === 'true',
            'convert_to_webp' => isset($_POST['convert_to_webp']) && $_POST['convert_to_webp'] === 'true',
            'webp_quality' => isset($_POST['webp_quality']) ? (int) $_POST['webp_quality'] : 80,
            'skip_already_optimized' => isset($_POST['skip_already_optimized']) && $_POST['skip_already_optimized'] === 'true',
            'min_savings_percent' => isset($_POST['min_savings_percent']) ? (int) $_POST['min_savings_percent'] : 5,
            'max_file_size_mb' => isset($_POST['max_file_size_mb']) ? (int) $_POST['max_file_size_mb'] : 10,
        ];

        $saved = $this->save_optimization_settings($settings);

        if ($saved) {
            $this->logger->info('optimization', 'Optimization settings updated');
            wp_send_json_success([
                'message' => 'Settings saved successfully',
                'settings' => $this->get_optimization_settings(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }
    }
}

