<?php
/**
 * Media Library class for URL rewriting and UI integration
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\Core\Settings;

/**
 * Handles Media Library URL rewriting and UI integration
 */
final class Media_Library
{
    private S3_Client $s3_client;
    private Settings $settings;

    public function __construct(
        S3_Client $s3_client,
        Settings $settings
    ) {
        $this->s3_client = $s3_client;
        $this->settings = $settings;

        $this->register_hooks();
    }

    /**
     * Register URL rewriting hooks
     */
    private function register_hooks(): void
    {
        // Main URL hook
        add_filter('wp_get_attachment_url', [$this, 'filter_attachment_url'], 10, 2);
        
        // Image source hooks
        add_filter('wp_get_attachment_image_src', [$this, 'filter_image_src'], 10, 4);
        add_filter('image_downsize', [$this, 'filter_image_downsize'], 10, 3);
        
        // Srcset hooks for responsive images
        add_filter('wp_calculate_image_srcset', [$this, 'filter_image_srcset'], 10, 5);
        add_filter('wp_calculate_image_sizes', [$this, 'filter_image_sizes'], 10, 5);
        
        // Image attributes
        add_filter('wp_get_attachment_image_attributes', [$this, 'filter_image_attributes'], 10, 3);
        
        // Media Library JS data
        add_filter('wp_prepare_attachment_for_js', [$this, 'filter_attachment_for_js'], 10, 3);
        
        // Attachment edit fields
        add_filter('attachment_fields_to_edit', [$this, 'add_attachment_fields'], 10, 2);
    }

    /**
     * Filter attachment URL to use CloudFront/S3
     */
    public function filter_attachment_url(string $url, int $attachment_id): string
    {
        // Check if migrated to S3
        $s3_url = get_post_meta($attachment_id, '_media_toolkit_url', true);
        
        if (!empty($s3_url)) {
            return $s3_url;
        }

        // Not migrated yet, return original
        return $url;
    }

    /**
     * Filter image source array
     */
    public function filter_image_src(?array $image, int $attachment_id, string|array $size, bool $icon): ?array
    {
        if ($image === null || $image === false) {
            return $image;
        }

        // Replace URL if migrated
        $s3_url = $this->get_size_url($attachment_id, $size);
        
        if (!empty($s3_url)) {
            $image[0] = $s3_url;
        }

        return $image;
    }

    /**
     * Filter image downsize
     */
    public function filter_image_downsize(bool|array $downsize, int $attachment_id, string|array $size): bool|array
    {
        if ($downsize !== false) {
            // WordPress already has a result, just update URL
            if (is_array($downsize)) {
                $s3_url = $this->get_size_url($attachment_id, $size);
                if (!empty($s3_url)) {
                    $downsize[0] = $s3_url;
                }
            }
            return $downsize;
        }

        return $downsize;
    }

    /**
     * Filter image srcset for responsive images
     */
    public function filter_image_srcset(array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id): array
    {
        if (empty($sources)) {
            return $sources;
        }

        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($s3_key)) {
            return $sources;
        }

        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true) ?: [];
        $base_url = $this->get_base_url();
        $s3_base_dir = dirname($s3_key);

        foreach ($sources as $width => &$source) {
            // Try to find matching thumbnail
            $source_file = basename(wp_parse_url($source['url'], PHP_URL_PATH));
            
            // Check if this size has a known S3 key
            foreach ($thumb_keys as $size_name => $thumb_key) {
                if (str_contains($thumb_key, $source_file)) {
                    $source['url'] = $base_url . '/' . $thumb_key;
                    break;
                }
            }

            // If not found in thumb_keys, construct URL
            if (str_contains($source['url'], '/wp-content/uploads/')) {
                // Extract relative path and construct S3 URL
                $upload_dir = wp_upload_dir();
                $base_dir = $upload_dir['basedir'];
                $relative_url = str_replace($upload_dir['baseurl'], '', $source['url']);
                
                $s3_path = $this->settings->get_s3_base_path() . $relative_url;
                $source['url'] = $base_url . '/' . ltrim($s3_path, '/');
            }
        }

        return $sources;
    }

    /**
     * Filter image sizes attribute
     */
    public function filter_image_sizes(string $sizes, array $size, string $image_src, array $image_meta, int $attachment_id): string
    {
        // Return sizes unchanged - this is the sizes attribute, not URLs
        return $sizes;
    }

    /**
     * Filter image attributes
     */
    public function filter_image_attributes(array $attr, \WP_Post $attachment, string|array $size): array
    {
        // Can add custom attributes here
        // e.g., data-s3="true" for offloaded images
        
        if ($this->is_offloaded($attachment->ID)) {
            $attr['data-s3-offload'] = 'true';
        }

        return $attr;
    }

    /**
     * Filter attachment data for Media Library JS
     */
    public function filter_attachment_for_js(array $response, \WP_Post $attachment, array $meta): array
    {
        $attachment_id = $attachment->ID;
        
        // Update main URL
        $s3_url = get_post_meta($attachment_id, '_media_toolkit_url', true);
        if (!empty($s3_url)) {
            $response['url'] = $s3_url;
        }

        // Update sizes URLs
        if (!empty($response['sizes'])) {
            $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true) ?: [];
            $base_url = $this->get_base_url();

            foreach ($response['sizes'] as $size_name => &$size_data) {
                if (isset($thumb_keys[$size_name])) {
                    $size_data['url'] = $base_url . '/' . $thumb_keys[$size_name];
                }
            }
        }

        // Add S3 info
        $response['s3_offload'] = [
            'migrated' => $this->is_offloaded($attachment_id),
            's3_key' => get_post_meta($attachment_id, '_media_toolkit_key', true) ?: null,
        ];

        return $response;
    }

    /**
     * Add S3 info fields to attachment edit screen
     */
    public function add_attachment_fields(array $form_fields, \WP_Post $post): array
    {
        $attachment_id = $post->ID;
        
        if (!$this->is_offloaded($attachment_id)) {
            return $form_fields;
        }

        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        $s3_url = get_post_meta($attachment_id, '_media_toolkit_url', true);

        $form_fields['media_toolkit_info'] = [
            'label' => 'S3 Offload',
            'input' => 'html',
            'html' => sprintf(
                '<div class="mds-offload-info">
                    <p><strong>Status:</strong> <span class="mds-badge mds-badge-success">Offloaded</span></p>
                    <p><strong>S3 Key:</strong> <code>%s</code></p>
                    <p><strong>URL:</strong> <a href="%s" target="_blank">%s</a></p>
                </div>',
                esc_html($s3_key),
                esc_url($s3_url),
                esc_html($s3_url)
            ),
            'helps' => 'This file is stored on Amazon S3',
        ];

        return $form_fields;
    }

    /**
     * Get URL for a specific size
     */
    private function get_size_url(int $attachment_id, string|array $size): string
    {
        // Get main S3 URL
        $main_url = get_post_meta($attachment_id, '_media_toolkit_url', true);
        
        if (empty($main_url)) {
            return '';
        }

        // If requesting full size
        if ($size === 'full' || (is_array($size) && empty($size))) {
            return $main_url;
        }

        // Get thumbnail URLs
        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true);
        
        if (is_array($thumb_keys) && is_string($size) && isset($thumb_keys[$size])) {
            return $this->get_base_url() . '/' . $thumb_keys[$size];
        }

        return $main_url;
    }

    /**
     * Get base URL (CDN or S3)
     */
    private function get_base_url(): string
    {
        $config = $this->settings->get_config();
        
        if ($config === null) {
            return '';
        }

        // Use CDN URL if configured (Cloudflare, CloudFront, or other)
        if ($config->hasCDN()) {
            return rtrim($config->cdnUrl, '/');
        }

        // Fallback to direct S3 URL
        return sprintf(
            'https://%s.s3.%s.amazonaws.com',
            $config->bucket,
            $config->region
        );
    }

    /**
     * Check if attachment is offloaded
     */
    public function is_offloaded(int $attachment_id): bool
    {
        return !empty(get_post_meta($attachment_id, '_media_toolkit_migrated', true));
    }

    /**
     * Get all S3 keys for an attachment (main + thumbnails)
     */
    public function get_all_s3_keys(int $attachment_id): array
    {
        $keys = [];
        
        $main_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($main_key)) {
            $keys['full'] = $main_key;
        }

        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true);
        if (is_array($thumb_keys)) {
            $keys = array_merge($keys, $thumb_keys);
        }

        return $keys;
    }
}

