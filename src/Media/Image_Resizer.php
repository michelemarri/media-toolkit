<?php
/**
 * Image Resizer class for automatic image resizing on upload
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;

/**
 * Handles automatic image resizing when images are uploaded
 * 
 * Automatically resizes images (JPEG, GIF, PNG, WebP) when they are uploaded
 * to within a given maximum width and/or height to reduce server space usage,
 * speed up your website, save time and boost SEO.
 */
final class Image_Resizer
{
    private const SETTINGS_KEY = 'media_toolkit_resize_settings';
    private const STATS_KEY = 'media_toolkit_resize_stats';

    private Logger $logger;
    private History $history;

    /**
     * Supported MIME types for resizing
     */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * BMP MIME types that can be converted to JPEG
     */
    private const BMP_MIME_TYPES = [
        'image/bmp',
        'image/x-ms-bmp',
    ];

    public function __construct(
        Logger $logger,
        ?History $history = null
    ) {
        $this->logger = $logger;
        $this->history = $history ?? new History();

        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void
    {
        // Hook into image upload - process before WordPress generates thumbnails
        add_filter('wp_handle_upload', [$this, 'handle_upload'], 5, 2);

        // Also handle sideloads (images uploaded from URL)
        add_filter('wp_handle_sideload', [$this, 'handle_upload'], 5, 2);

        // Register AJAX handlers
        add_action('wp_ajax_media_toolkit_save_resize_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_media_toolkit_get_resize_stats', [$this, 'ajax_get_stats']);
    }

    /**
     * Get resize settings
     */
    public function get_resize_settings(): array
    {
        $defaults = [
            'enabled' => false,
            'max_width' => 2560,
            'max_height' => 2560,
            'jpeg_quality' => 82,
            'convert_bmp_to_jpg' => true,
            'resize_existing' => false,
        ];

        $saved = get_option(self::SETTINGS_KEY, []);

        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /**
     * Save resize settings
     */
    public function save_resize_settings(array $settings): bool
    {
        $sanitized = [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'max_width' => max(0, min(10000, (int) ($settings['max_width'] ?? 2560))),
            'max_height' => max(0, min(10000, (int) ($settings['max_height'] ?? 2560))),
            'jpeg_quality' => max(1, min(100, (int) ($settings['jpeg_quality'] ?? 82))),
            'convert_bmp_to_jpg' => (bool) ($settings['convert_bmp_to_jpg'] ?? true),
            'resize_existing' => (bool) ($settings['resize_existing'] ?? false),
        ];

        return update_option(self::SETTINGS_KEY, $sanitized);
    }

    /**
     * Get resize statistics
     */
    public function get_stats(): array
    {
        $defaults = [
            'total_resized' => 0,
            'total_bytes_saved' => 0,
            'total_bmp_converted' => 0,
            'last_resize_at' => null,
        ];

        $stats = get_option(self::STATS_KEY, []);

        $merged = array_merge($defaults, is_array($stats) ? $stats : []);
        $merged['total_bytes_saved_formatted'] = size_format($merged['total_bytes_saved']);

        return $merged;
    }

    /**
     * Update resize statistics
     */
    private function update_stats(int $bytes_saved, bool $was_bmp_converted = false): void
    {
        $stats = $this->get_stats();

        $stats['total_resized']++;
        $stats['total_bytes_saved'] += $bytes_saved;
        if ($was_bmp_converted) {
            $stats['total_bmp_converted']++;
        }
        $stats['last_resize_at'] = time();

        update_option(self::STATS_KEY, $stats);
    }

    /**
     * Handle uploaded file - resize if needed
     *
     * @param array<string, mixed> $upload Upload data from WordPress
     * @param string $context Upload context
     * @return array<string, mixed> Modified upload data
     */
    public function handle_upload(array $upload, string $context = 'upload'): array
    {
        // Skip if there was an error
        if (isset($upload['error'])) {
            return $upload;
        }

        // Skip if no file
        if (empty($upload['file'])) {
            return $upload;
        }

        // Get settings
        $settings = $this->get_resize_settings();

        // Skip if resize is disabled
        if (!$settings['enabled']) {
            return $upload;
        }

        // Skip if no dimensions set
        if ($settings['max_width'] <= 0 && $settings['max_height'] <= 0) {
            return $upload;
        }

        $file_path = $upload['file'];
        $mime_type = $upload['type'] ?? '';

        // Handle BMP conversion
        if (in_array($mime_type, self::BMP_MIME_TYPES, true) && $settings['convert_bmp_to_jpg']) {
            $converted = $this->convert_bmp_to_jpg($file_path, $settings);

            if ($converted !== null) {
                $upload['file'] = $converted['file'];
                $upload['type'] = 'image/jpeg';
                $upload['url'] = str_replace('.bmp', '.jpg', $upload['url']);
                $file_path = $converted['file'];
                $mime_type = 'image/jpeg';

                $this->update_stats($converted['bytes_saved'], true);

                $this->logger->success(
                    'resize',
                    'BMP converted to JPEG',
                    null,
                    basename($converted['file']),
                    ['saved' => size_format($converted['bytes_saved'])]
                );
            }
        }

        // Skip if not a supported image type
        if (!in_array($mime_type, self::SUPPORTED_MIME_TYPES, true)) {
            return $upload;
        }

        // Get image dimensions
        $image_size = @getimagesize($file_path);

        if ($image_size === false) {
            return $upload;
        }

        [$width, $height] = $image_size;

        // Check if resize is needed
        $needs_resize = false;

        if ($settings['max_width'] > 0 && $width > $settings['max_width']) {
            $needs_resize = true;
        }

        if ($settings['max_height'] > 0 && $height > $settings['max_height']) {
            $needs_resize = true;
        }

        if (!$needs_resize) {
            return $upload;
        }

        // Perform resize
        $result = $this->resize_image(
            $file_path,
            $settings['max_width'],
            $settings['max_height'],
            $settings['jpeg_quality']
        );

        if ($result['success']) {
            $this->update_stats($result['bytes_saved']);

            $this->logger->success(
                'resize',
                sprintf(
                    'Image resized from %dx%d to %dx%d, saved %s',
                    $width,
                    $height,
                    $result['new_width'],
                    $result['new_height'],
                    size_format($result['bytes_saved'])
                ),
                null,
                basename($file_path)
            );

            // Record in history
            $this->history->record(
                HistoryAction::RESIZED,
                null,
                $file_path,
                null,
                $result['bytes_saved'],
                [
                    'original_width' => $width,
                    'original_height' => $height,
                    'new_width' => $result['new_width'],
                    'new_height' => $result['new_height'],
                ]
            );
        }

        return $upload;
    }

    /**
     * Resize an image file
     *
     * @param string $file_path Path to image file
     * @param int $max_width Maximum width (0 = no limit)
     * @param int $max_height Maximum height (0 = no limit)
     * @param int $quality JPEG quality
     * @return array{success: bool, error?: string, bytes_saved?: int, new_width?: int, new_height?: int}
     */
    public function resize_image(
        string $file_path,
        int $max_width,
        int $max_height,
        int $quality = 82
    ): array {
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'error' => 'File not found',
            ];
        }

        $original_size = filesize($file_path);

        // Use WordPress image editor
        $editor = wp_get_image_editor($file_path);

        if (is_wp_error($editor)) {
            return [
                'success' => false,
                'error' => $editor->get_error_message(),
            ];
        }

        $size = $editor->get_size();

        if (!$size) {
            return [
                'success' => false,
                'error' => 'Could not get image dimensions',
            ];
        }

        $orig_width = $size['width'];
        $orig_height = $size['height'];

        // Calculate new dimensions maintaining aspect ratio
        $new_dimensions = $this->calculate_dimensions(
            $orig_width,
            $orig_height,
            $max_width,
            $max_height
        );

        if ($new_dimensions['width'] === $orig_width && $new_dimensions['height'] === $orig_height) {
            return [
                'success' => true,
                'bytes_saved' => 0,
                'new_width' => $orig_width,
                'new_height' => $orig_height,
            ];
        }

        // Resize the image
        $resized = $editor->resize($new_dimensions['width'], $new_dimensions['height'], false);

        if (is_wp_error($resized)) {
            return [
                'success' => false,
                'error' => $resized->get_error_message(),
            ];
        }

        // Set quality for JPEG
        $editor->set_quality($quality);

        // Save the image (overwrite original)
        $saved = $editor->save($file_path);

        if (is_wp_error($saved)) {
            return [
                'success' => false,
                'error' => $saved->get_error_message(),
            ];
        }

        clearstatcache(true, $file_path);
        $new_size = filesize($file_path);
        $bytes_saved = $original_size - $new_size;

        return [
            'success' => true,
            'bytes_saved' => max(0, $bytes_saved),
            'new_width' => $new_dimensions['width'],
            'new_height' => $new_dimensions['height'],
            'original_width' => $orig_width,
            'original_height' => $orig_height,
        ];
    }

    /**
     * Calculate new dimensions maintaining aspect ratio
     *
     * @param int $orig_width Original width
     * @param int $orig_height Original height
     * @param int $max_width Maximum width
     * @param int $max_height Maximum height
     * @return array{width: int, height: int}
     */
    private function calculate_dimensions(
        int $orig_width,
        int $orig_height,
        int $max_width,
        int $max_height
    ): array {
        $new_width = $orig_width;
        $new_height = $orig_height;

        // Handle max width
        if ($max_width > 0 && $orig_width > $max_width) {
            $ratio = $max_width / $orig_width;
            $new_width = $max_width;
            $new_height = (int) round($orig_height * $ratio);
        }

        // Handle max height
        if ($max_height > 0 && $new_height > $max_height) {
            $ratio = $max_height / $new_height;
            $new_height = $max_height;
            $new_width = (int) round($new_width * $ratio);
        }

        return [
            'width' => $new_width,
            'height' => $new_height,
        ];
    }

    /**
     * Convert BMP to JPEG
     *
     * @param string $file_path Path to BMP file
     * @param array<string, mixed> $settings Resize settings
     * @return array{file: string, bytes_saved: int}|null Converted file info or null on failure
     */
    private function convert_bmp_to_jpg(string $file_path, array $settings): ?array
    {
        $original_size = filesize($file_path);

        // Check if GD supports BMP
        if (!function_exists('imagecreatefrombmp')) {
            // Try ImageMagick
            if (!extension_loaded('imagick')) {
                $this->logger->warning(
                    'resize',
                    'BMP conversion not available - GD or ImageMagick required',
                    null,
                    basename($file_path)
                );
                return null;
            }

            try {
                $imagick = new \Imagick($file_path);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality($settings['jpeg_quality'] ?? 82);

                $new_path = preg_replace('/\.bmp$/i', '.jpg', $file_path);
                $imagick->writeImage($new_path);
                $imagick->destroy();

                // Delete original BMP
                @unlink($file_path);

                $new_size = filesize($new_path);

                return [
                    'file' => $new_path,
                    'bytes_saved' => $original_size - $new_size,
                ];
            } catch (\Exception $e) {
                $this->logger->error(
                    'resize',
                    'BMP conversion failed: ' . $e->getMessage(),
                    null,
                    basename($file_path)
                );
                return null;
            }
        }

        // Use GD
        $image = @imagecreatefrombmp($file_path);

        if ($image === false) {
            return null;
        }

        $new_path = preg_replace('/\.bmp$/i', '.jpg', $file_path);
        $quality = $settings['jpeg_quality'] ?? 82;

        $result = imagejpeg($image, $new_path, $quality);
        imagedestroy($image);

        if (!$result) {
            return null;
        }

        // Delete original BMP
        @unlink($file_path);

        $new_size = filesize($new_path);

        return [
            'file' => $new_path,
            'bytes_saved' => $original_size - $new_size,
        ];
    }

    /**
     * Resize an existing attachment
     *
     * @param int $attachment_id Attachment ID
     * @return array{success: bool, error?: string, bytes_saved?: int}
     */
    public function resize_attachment(int $attachment_id): array
    {
        $file = get_attached_file($attachment_id);

        if (empty($file) || !file_exists($file)) {
            return [
                'success' => false,
                'error' => 'File not found',
            ];
        }

        $settings = $this->get_resize_settings();

        if ($settings['max_width'] <= 0 && $settings['max_height'] <= 0) {
            return [
                'success' => false,
                'error' => 'No resize dimensions configured',
            ];
        }

        $result = $this->resize_image(
            $file,
            $settings['max_width'],
            $settings['max_height'],
            $settings['jpeg_quality']
        );

        if ($result['success'] && $result['bytes_saved'] > 0) {
            // Update attachment metadata
            $metadata = wp_get_attachment_metadata($attachment_id);

            if ($metadata) {
                $metadata['width'] = $result['new_width'];
                $metadata['height'] = $result['new_height'];
                wp_update_attachment_metadata($attachment_id, $metadata);
            }

            // Store resize info
            update_post_meta($attachment_id, '_media_toolkit_resized', '1');
            update_post_meta($attachment_id, '_media_toolkit_resize_bytes_saved', $result['bytes_saved']);
        }

        return $result;
    }

    /**
     * AJAX: Save resize settings
     */
    public function ajax_save_settings(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $settings = [
            'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === 'true',
            'max_width' => isset($_POST['max_width']) ? (int) $_POST['max_width'] : 2560,
            'max_height' => isset($_POST['max_height']) ? (int) $_POST['max_height'] : 2560,
            'jpeg_quality' => isset($_POST['jpeg_quality']) ? (int) $_POST['jpeg_quality'] : 82,
            'convert_bmp_to_jpg' => isset($_POST['convert_bmp_to_jpg']) && $_POST['convert_bmp_to_jpg'] === 'true',
            'resize_existing' => isset($_POST['resize_existing']) && $_POST['resize_existing'] === 'true',
        ];

        $saved = $this->save_resize_settings($settings);

        if ($saved) {
            $this->logger->info('resize', 'Resize settings updated');
            wp_send_json_success([
                'message' => __('Settings saved successfully', 'media-toolkit'),
                'settings' => $this->get_resize_settings(),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save settings', 'media-toolkit')]);
        }
    }

    /**
     * AJAX: Get resize stats
     */
    public function ajax_get_stats(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        wp_send_json_success($this->get_stats());
    }

    /**
     * Check if resize is enabled
     */
    public function is_enabled(): bool
    {
        $settings = $this->get_resize_settings();
        return $settings['enabled'] ?? false;
    }

    /**
     * Get max width setting
     */
    public function get_max_width(): int
    {
        $settings = $this->get_resize_settings();
        return $settings['max_width'] ?? 2560;
    }

    /**
     * Get max height setting
     */
    public function get_max_height(): int
    {
        $settings = $this->get_resize_settings();
        return $settings['max_height'] ?? 2560;
    }
}

