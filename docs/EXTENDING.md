# Media Toolkit - Extending Guide

Learn how to extend the Media Toolkit plugin with custom functionality.

---

## Table of Contents

1. [Custom CDN Providers](#custom-cdn-providers)
2. [Custom Storage Backends](#custom-storage-backends)
3. [Custom Optimization Handlers](#custom-optimization-handlers)
4. [Integration with Other Plugins](#integration-with-other-plugins)
5. [Custom Admin Pages](#custom-admin-pages)

---

## Custom CDN Providers

### Create Provider Class

```php
<?php

namespace MyPlugin\CDN;

use Metodo\MediaToolkit\CDN\CDNProvider;
use Metodo\MediaToolkit\Core\Logger;

class BunnyCDN
{
    private string $apiKey;
    private string $pullZoneId;
    private Logger $logger;

    public function __construct(string $apiKey, string $pullZoneId, Logger $logger)
    {
        $this->apiKey = $apiKey;
        $this->pullZoneId = $pullZoneId;
        $this->logger = $logger;
    }

    /**
     * Purge specific URLs from cache
     */
    public function purge(array $urls): bool
    {
        $response = wp_remote_post('https://api.bunny.net/purge', [
            'headers' => [
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['urls' => $urls]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('bunnycdn', 'Purge failed: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $this->logger->error('bunnycdn', "Purge failed with status: {$code}");
            return false;
        }

        $this->logger->info('bunnycdn', 'Purged ' . count($urls) . ' URLs');
        return true;
    }

    /**
     * Purge entire zone
     */
    public function purgeAll(): bool
    {
        $response = wp_remote_post(
            "https://api.bunny.net/pullzone/{$this->pullZoneId}/purgeCache",
            [
                'headers' => ['AccessKey' => $this->apiKey],
                'timeout' => 30,
            ]
        );

        return !is_wp_error($response) && 
               wp_remote_retrieve_response_code($response) === 204;
    }
}
```

### Register Provider

```php
add_filter('media_toolkit_cdn_providers', function(array $providers): array {
    $providers['bunnycdn'] = [
        'label' => 'BunnyCDN',
        'class' => \MyPlugin\CDN\BunnyCDN::class,
        'fields' => [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
            ],
            'pull_zone_id' => [
                'label' => 'Pull Zone ID',
                'type' => 'text',
            ],
        ],
    ];
    return $providers;
});
```

### Hook into Invalidation

```php
add_action('media_toolkit_invalidate_cdn', function(array $paths, string $provider) {
    if ($provider !== 'bunnycdn') {
        return;
    }

    $settings = get_option('media_toolkit_cdn_settings', []);
    $bunny = new \MyPlugin\CDN\BunnyCDN(
        $settings['bunnycdn_api_key'],
        $settings['bunnycdn_pull_zone_id'],
        media_toolkit()->get_logger()
    );

    $bunny->purge($paths);
}, 10, 2);
```

---

## Custom Storage Backends

### Alternative to S3 (e.g., DigitalOcean Spaces)

DigitalOcean Spaces is S3-compatible, so you can use the existing S3 client with custom endpoints:

```php
add_filter('media_toolkit_s3_client_config', function(array $config): array {
    // Use DigitalOcean Spaces
    $config['endpoint'] = 'https://nyc3.digitaloceanspaces.com';
    $config['use_path_style_endpoint'] = false;
    
    return $config;
});
```

### Custom Storage Provider

For non-S3-compatible storage:

```php
<?php

namespace MyPlugin\Storage;

interface StorageInterface
{
    public function upload(string $localPath, string $remotePath): bool;
    public function delete(string $remotePath): bool;
    public function exists(string $remotePath): bool;
    public function getUrl(string $remotePath): string;
}

class GoogleCloudStorage implements StorageInterface
{
    private $client;
    private string $bucket;

    public function __construct(string $bucket, string $keyFilePath)
    {
        $this->client = new \Google\Cloud\Storage\StorageClient([
            'keyFilePath' => $keyFilePath,
        ]);
        $this->bucket = $bucket;
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        try {
            $bucket = $this->client->bucket($this->bucket);
            $bucket->upload(
                fopen($localPath, 'r'),
                ['name' => $remotePath]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            $bucket = $this->client->bucket($this->bucket);
            $bucket->object($remotePath)->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function exists(string $remotePath): bool
    {
        $bucket = $this->client->bucket($this->bucket);
        return $bucket->object($remotePath)->exists();
    }

    public function getUrl(string $remotePath): string
    {
        return sprintf(
            'https://storage.googleapis.com/%s/%s',
            $this->bucket,
            $remotePath
        );
    }
}
```

---

## Custom Optimization Handlers

### WebP Conversion

```php
<?php

namespace MyPlugin\Optimization;

class WebPConverter
{
    private int $quality;

    public function __construct(int $quality = 80)
    {
        $this->quality = $quality;
    }

    public function convert(string $sourcePath): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }

        $info = getimagesize($sourcePath);
        if (!$info) {
            return null;
        }

        $mime = $info['mime'];
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            default => null,
        };

        if (!$image) {
            return null;
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $sourcePath);
        
        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        if (imagewebp($image, $webpPath, $this->quality)) {
            imagedestroy($image);
            return $webpPath;
        }

        imagedestroy($image);
        return null;
    }
}
```

### Register Custom Optimizer

```php
add_filter('media_toolkit_optimize_image', function($result, int $attachment_id, string $file_path) {
    // Skip if already processed
    if ($result !== null) {
        return $result;
    }

    // Only process specific types
    $mime = get_post_mime_type($attachment_id);
    if (!in_array($mime, ['image/jpeg', 'image/png'])) {
        return $result;
    }

    $converter = new \MyPlugin\Optimization\WebPConverter(85);
    $webpPath = $converter->convert($file_path);

    if ($webpPath && file_exists($webpPath)) {
        // Upload WebP version to S3
        $s3 = media_toolkit()->get_s3_client();
        $webpKey = preg_replace('/\.[^.]+$/', '.webp', get_post_meta($attachment_id, '_media_toolkit_key', true));
        
        $s3->upload_file($webpPath, $attachment_id, $webpKey);
        
        // Store WebP URL
        update_post_meta($attachment_id, '_media_toolkit_webp_url', $s3->get_file_url($webpKey));
    }

    return $result;
}, 10, 3);
```

### Serve WebP When Available

```php
add_filter('wp_get_attachment_url', function(string $url, int $attachment_id): string {
    // Check if browser supports WebP
    if (!isset($_SERVER['HTTP_ACCEPT']) || 
        strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') === false) {
        return $url;
    }

    // Check if WebP version exists
    $webp_url = get_post_meta($attachment_id, '_media_toolkit_webp_url', true);
    
    return $webp_url ?: $url;
}, 10, 2);
```

---

## Integration with Other Plugins

### WooCommerce Product Images

```php
<?php

namespace MyPlugin\Integration;

class WooCommerceIntegration
{
    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Ensure product gallery images are offloaded
        add_action('woocommerce_process_product_meta', [$this, 'processGallery']);
        
        // Custom S3 path for products
        add_filter('media_toolkit_s3_key', [$this, 'customProductPath'], 10, 3);
    }

    public function processGallery(int $post_id): void
    {
        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        
        if (empty($gallery_ids)) {
            return;
        }

        $ids = explode(',', $gallery_ids);
        $upload_handler = media_toolkit()->get_upload_handler();

        foreach ($ids as $attachment_id) {
            if (!get_post_meta($attachment_id, '_media_toolkit_migrated', true)) {
                $upload_handler->process_attachment((int) $attachment_id);
            }
        }
    }

    public function customProductPath(string $s3_key, int $attachment_id, string $file_path): string
    {
        $parent_id = wp_get_post_parent_id($attachment_id);
        
        if ($parent_id && get_post_type($parent_id) === 'product') {
            // Store product images in dedicated folder
            return str_replace(
                'wp-content/uploads/',
                'wp-content/uploads/products/',
                $s3_key
            );
        }

        return $s3_key;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    (new \MyPlugin\Integration\WooCommerceIntegration())->init();
}, 20);
```

### Easy Digital Downloads

```php
add_filter('media_toolkit_skip_file', function(bool $skip, int $attachment_id, string $file_path): bool {
    // Don't offload EDD downloadable files (they need protection)
    $parent_id = wp_get_post_parent_id($attachment_id);
    
    if ($parent_id && get_post_type($parent_id) === 'download') {
        // Check if this is a downloadable file (not just featured image)
        $download_files = get_post_meta($parent_id, 'edd_download_files', true);
        
        if (is_array($download_files)) {
            foreach ($download_files as $file) {
                if (strpos($file['file'], basename($file_path)) !== false) {
                    return true; // Skip this file
                }
            }
        }
    }

    return $skip;
}, 10, 3);
```

---

## Custom Admin Pages

### Add Custom Tab to Settings

```php
<?php

namespace MyPlugin\Admin;

class CustomSettingsTab
{
    public function __construct()
    {
        add_filter('media_toolkit_settings_tabs', [$this, 'registerTab']);
        add_action('media_toolkit_settings_tab_custom', [$this, 'renderTab']);
        add_action('wp_ajax_media_toolkit_save_custom', [$this, 'saveSettings']);
    }

    public function registerTab(array $tabs): array
    {
        $tabs['custom'] = [
            'label' => __('Custom Settings', 'my-plugin'),
            'icon' => 'dashicons-admin-generic',
        ];
        return $tabs;
    }

    public function renderTab(): void
    {
        $settings = get_option('media_toolkit_custom_settings', []);
        ?>
        <div class="mds-card">
            <div class="mds-card-header">
                <h3><?php _e('Custom Settings', 'my-plugin'); ?></h3>
            </div>
            <div class="mds-card-body">
                <div class="mds-form-group">
                    <label for="custom_option">
                        <?php _e('Custom Option', 'my-plugin'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="custom_option" 
                        name="custom_option"
                        value="<?php echo esc_attr($settings['custom_option'] ?? ''); ?>"
                        class="mds-input"
                    >
                </div>
                <button type="button" class="mds-btn mds-btn-primary" id="save-custom">
                    <?php _e('Save Settings', 'my-plugin'); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery('#save-custom').on('click', function() {
            jQuery.post(ajaxurl, {
                action: 'media_toolkit_save_custom',
                nonce: mediaToolkit.nonce,
                custom_option: jQuery('#custom_option').val()
            }, function(response) {
                alert(response.success ? 'Saved!' : 'Error: ' + response.data.message);
            });
        });
        </script>
        <?php
    }

    public function saveSettings(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $settings = [
            'custom_option' => sanitize_text_field($_POST['custom_option'] ?? ''),
        ];

        update_option('media_toolkit_custom_settings', $settings);

        wp_send_json_success(['message' => 'Settings saved']);
    }
}

// Initialize
new \MyPlugin\Admin\CustomSettingsTab();
```

---

## Best Practices

### 1. Check Plugin Availability

```php
if (!function_exists('media_toolkit')) {
    return;
}
```

### 2. Use Proper Namespaces

```php
namespace MyPlugin\MediaToolkitExtension;
```

### 3. Handle Edge Cases

```php
add_filter('media_toolkit_s3_key', function($key, $id, $path) {
    // Validate inputs
    if (empty($key) || !$id || !file_exists($path)) {
        return $key;
    }
    
    // Your modifications...
    return $key;
}, 10, 3);
```

### 4. Log Important Operations

```php
$logger = media_toolkit()->get_logger();
$logger->info('custom', 'Operation completed', null, null, [
    'attachment_id' => $id,
    'custom_data' => $data,
]);
```

### 5. Clean Up on Deactivation

```php
register_deactivation_hook(__FILE__, function() {
    delete_option('media_toolkit_custom_settings');
    // Remove custom post meta, transients, etc.
});
```

---

## Support

- **Documentation**: [docs/DOCUMENTATION.md](DOCUMENTATION.md)
- **Hooks Reference**: [docs/HOOKS.md](HOOKS.md)
- **Website**: [metodo.dev](https://metodo.dev)
- **Contact**: plugins@metodo.dev

