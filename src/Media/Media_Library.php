<?php
/**
 * Media Library class for URL rewriting and UI integration
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\Core\Settings;

/**
 * Handles Media Library URL rewriting and UI integration
 */
final class Media_Library
{
    private StorageInterface $storage;
    private Settings $settings;

    public function __construct(
        StorageInterface $storage,
        Settings $settings
    ) {
        $this->storage = $storage;
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
        
        // Content URL rewriting - fixes relative URLs in post content
        add_filter('the_content', [$this, 'filter_content_urls'], 10, 1);
        add_filter('the_excerpt', [$this, 'filter_content_urls'], 10, 1);
        add_filter('widget_text_content', [$this, 'filter_content_urls'], 10, 1);
        
        // Rank Math sitemap integration - fix image URLs in sitemap
        add_filter('rank_math/sitemap/urlimages', [$this, 'filter_sitemap_images'], 10, 2);
        
        // Note: Cloud Storage fields are now rendered via Media_Library_UI::render_attachment_details_template()
        // to avoid duplication in the modal view
    }

    /**
     * Filter attachment URL to use CloudFront/S3
     */
    public function filter_attachment_url(string $url, int|string $attachment_id): string
    {
        $attachment_id = (int) $attachment_id;
        
        // Check if migrated to S3 - use key to build URL dynamically
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (!empty($s3_key)) {
            // Build URL dynamically using current CDN/storage settings
            return $this->get_base_url() . '/' . ltrim($s3_key, '/');
        }

        // Not migrated yet, return original
        return $url;
    }

    /**
     * Filter image source array
     */
    public function filter_image_src(array|false|null $image, int|string $attachment_id, string|array $size, bool $icon): array|false|null
    {
        $attachment_id = (int) $attachment_id;
        
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
    public function filter_image_downsize(bool|array $downsize, int|string $attachment_id, string|array $size): bool|array
    {
        $attachment_id = (int) $attachment_id;
        
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
     * 
     * Note: WordPress can pass `false` to disable srcset entirely
     */
    public function filter_image_srcset(array|false $sources, array $size_array, string $image_src, array $image_meta, int|string $attachment_id): array|false
    {
        $attachment_id = (int) $attachment_id;
        
        // WordPress passes false to disable srcset
        if ($sources === false || empty($sources)) {
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
                // Extract relative path and construct storage URL
                $upload_dir = wp_upload_dir();
                $base_dir = $upload_dir['basedir'];
                $relative_url = str_replace($upload_dir['baseurl'], '', $source['url']);

                $storage_path = $this->settings->get_storage_base_path() . $relative_url;
                $source['url'] = $base_url . '/' . ltrim($storage_path, '/');
            }
        }

        return $sources;
    }

    /**
     * Filter image sizes attribute
     */
    public function filter_image_sizes(string $sizes, array $size, string $image_src, array $image_meta, int|string $attachment_id): string
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
        
        // Update main URL - build dynamically using current CDN/storage settings
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($s3_key)) {
            $response['url'] = $this->get_base_url() . '/' . ltrim($s3_key, '/');
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
     * Get URL for a specific size
     */
    private function get_size_url(int $attachment_id, string|array $size): string
    {
        // Get main S3 key - build URL dynamically using current CDN/storage settings
        $main_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($main_key)) {
            return '';
        }

        $base_url = $this->get_base_url();
        $main_url = $base_url . '/' . ltrim($main_key, '/');

        // If requesting full size
        if ($size === 'full' || (is_array($size) && empty($size))) {
            return $main_url;
        }

        // Get thumbnail URLs
        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true);
        
        if (is_array($thumb_keys) && is_string($size) && isset($thumb_keys[$size])) {
            return $base_url . '/' . $thumb_keys[$size];
        }

        return $main_url;
    }

    /**
     * Get base URL (CDN or S3)
     */
    private function get_base_url(): string
    {
        $config = $this->settings->get_storage_config();
        
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

    /**
     * Filter content to rewrite relative storage URLs to absolute CDN URLs
     * 
     * This fixes URLs like "media/production/wp-content/uploads/..." that were
     * saved in post content without the CDN domain, which causes 404 errors
     * when accessed relatively from the page URL.
     * 
     * Note: WordPress can pass null or empty string for content
     */
    public function filter_content_urls(string|null $content): string
    {
        if ($content === null || $content === '') {
            return $content ?? '';
        }

        $base_url = $this->get_base_url();
        if (empty($base_url)) {
            return $content;
        }

        $storage_base_path = $this->settings->get_storage_base_path();
        
        // Pattern to match storage paths that need to be rewritten
        // Matches: media/production/wp-content/uploads/... or /media/production/wp-content/uploads/...
        // But NOT when already prefixed with http:// or https://
        $patterns = [
            // Relative path without leading slash (e.g., in src="media/production/...")
            '#(src|href)=(["\'])(?!https?://|//)(' . preg_quote($storage_base_path, '#') . '/[^"\']+)(["\'])#i',
            // Relative path with leading slash (e.g., in src="/media/production/...")
            '#(src|href)=(["\'])(?!https?://|//)/' . preg_quote($storage_base_path, '#') . '/([^"\']+)(["\'])#i',
        ];

        // Replace relative storage paths with full CDN URLs
        $content = preg_replace_callback(
            $patterns[0],
            function ($matches) use ($base_url) {
                // $matches[1] = src or href
                // $matches[2] = opening quote
                // $matches[3] = the path (media/production/wp-content/uploads/...)
                // $matches[4] = closing quote
                return $matches[1] . '=' . $matches[2] . $base_url . '/' . $matches[3] . $matches[4];
            },
            $content
        );

        $content = preg_replace_callback(
            $patterns[1],
            function ($matches) use ($base_url, $storage_base_path) {
                // $matches[1] = src or href
                // $matches[2] = opening quote
                // $matches[3] = the path after storage base (2024/05/image.jpg)
                // $matches[4] = closing quote
                return $matches[1] . '=' . $matches[2] . $base_url . '/' . $storage_base_path . '/' . $matches[3] . $matches[4];
            },
            $content
        );

        return $content ?: '';
    }

    /**
     * Filter Rank Math sitemap images to use correct CDN URLs
     * 
     * Rank Math extracts image URLs from post content and may pick up
     * relative storage paths. This filter ensures all image URLs in
     * the sitemap are absolute CDN URLs.
     */
    public function filter_sitemap_images(array $images, int $post_id): array
    {
        if (empty($images)) {
            return $images;
        }

        $base_url = $this->get_base_url();
        if (empty($base_url)) {
            return $images;
        }

        $storage_base_path = $this->settings->get_storage_base_path();
        $site_url = site_url();

        foreach ($images as &$image) {
            if (!isset($image['src'])) {
                continue;
            }

            $src = $image['src'];

            // Check if URL contains storage path but is missing CDN domain
            // Case 1: Relative URL like "media/production/wp-content/uploads/..."
            if (str_starts_with($src, $storage_base_path . '/')) {
                $image['src'] = $base_url . '/' . $src;
                continue;
            }

            // Case 2: URL with leading slash "/media/production/wp-content/uploads/..."
            if (str_starts_with($src, '/' . $storage_base_path . '/')) {
                $image['src'] = $base_url . $src;
                continue;
            }

            // Case 3: URL like "https://site.com/page/media/production/wp-content/uploads/..."
            // This is the case from SEMrush - the storage path was appended to page URL
            if (str_contains($src, '/' . $storage_base_path . '/') && !str_starts_with($src, $base_url)) {
                // Extract the storage path portion
                $pattern = '#^.*?(' . preg_quote($storage_base_path, '#') . '/.+)$#';
                if (preg_match($pattern, $src, $matches)) {
                    $image['src'] = $base_url . '/' . $matches[1];
                }
                continue;
            }

            // Case 4: URL with site URL but should be CDN (legacy URLs)
            if (str_starts_with($src, $site_url) && str_contains($src, '/wp-content/uploads/')) {
                // Try to find attachment by URL and get CDN URL
                $attachment_id = attachment_url_to_postid($src);
                if ($attachment_id > 0) {
                    $cdn_url = wp_get_attachment_url($attachment_id);
                    if ($cdn_url && $cdn_url !== $src) {
                        $image['src'] = $cdn_url;
                    }
                }
            }
        }

        return $images;
    }
}

