# Media Toolkit - Hooks Reference

Complete reference for all available filters and actions.

---

## Filters

### Upload Filters

#### `media_toolkit_s3_key`

Modify the S3 key before upload.

```php
add_filter('media_toolkit_s3_key', function(string $s3_key, int $attachment_id, string $file_path): string {
    // Custom path structure
    return 'custom-prefix/' . $s3_key;
}, 10, 3);
```

**Parameters:**
- `$s3_key` (string) - The generated S3 key
- `$attachment_id` (int) - WordPress attachment ID
- `$file_path` (string) - Local file path

---

#### `media_toolkit_upload_params`

Modify S3 upload parameters.

```php
add_filter('media_toolkit_upload_params', function(array $params, int $attachment_id): array {
    // Add custom metadata
    $params['Metadata'] = [
        'uploaded-by' => 'media-toolkit',
        'wp-attachment-id' => (string) $attachment_id,
    ];
    return $params;
}, 10, 2);
```

**Parameters:**
- `$params` (array) - S3 putObject parameters
- `$attachment_id` (int) - WordPress attachment ID

**Available params:**
- `Bucket` - S3 bucket name
- `Key` - Object key
- `SourceFile` - Local file path
- `ContentType` - MIME type
- `ACL` - Access control (default: public-read)
- `CacheControl` - Cache headers
- `ContentDisposition` - Inline or attachment

---

#### `media_toolkit_skip_file`

Skip specific files from offloading.

```php
add_filter('media_toolkit_skip_file', function(bool $skip, int $attachment_id, string $file_path): bool {
    // Skip files larger than 50MB
    if (filesize($file_path) > 50 * 1024 * 1024) {
        return true;
    }
    
    // Skip specific MIME types
    $mime = get_post_mime_type($attachment_id);
    if (str_starts_with($mime, 'video/')) {
        return true;
    }
    
    return $skip;
}, 10, 3);
```

**Parameters:**
- `$skip` (bool) - Current skip status
- `$attachment_id` (int) - WordPress attachment ID
- `$file_path` (string) - Local file path

---

### Optimization Filters

#### `media_toolkit_optimize_settings`

Modify optimization settings per file.

```php
add_filter('media_toolkit_optimize_settings', function(array $settings, int $attachment_id): array {
    $mime = get_post_mime_type($attachment_id);
    
    // Higher quality for specific post types
    $post_parent = wp_get_post_parent_id($attachment_id);
    if ($post_parent && get_post_type($post_parent) === 'portfolio') {
        $settings['jpeg_quality'] = 95;
    }
    
    return $settings;
}, 10, 2);
```

**Parameters:**
- `$settings` (array) - Optimization settings
- `$attachment_id` (int) - WordPress attachment ID

**Settings array:**
- `jpeg_quality` (int) - JPEG quality 0-100
- `png_compression` (int) - PNG compression 0-9
- `webp_quality` (int) - WebP quality 0-100
- `strip_metadata` (bool) - Remove EXIF data
- `min_savings_percent` (int) - Minimum savings threshold

---

#### `media_toolkit_skip_optimization`

Skip specific files from optimization.

```php
add_filter('media_toolkit_skip_optimization', function(bool $skip, int $attachment_id): bool {
    // Skip already optimized images
    if (get_post_meta($attachment_id, '_media_toolkit_optimized', true)) {
        return true;
    }
    return $skip;
}, 10, 2);
```

---

### URL Filters

#### `media_toolkit_file_url`

Modify the public URL for a file.

```php
add_filter('media_toolkit_file_url', function(string $url, string $s3_key, int $attachment_id): string {
    // Add query string for cache busting
    $modified = get_post_modified_time('U', true, $attachment_id);
    return add_query_arg('v', $modified, $url);
}, 10, 3);
```

**Parameters:**
- `$url` (string) - Generated URL
- `$s3_key` (string) - S3 object key
- `$attachment_id` (int) - WordPress attachment ID

---

### Migration Filters

#### `media_toolkit_migration_batch_size`

Modify batch size for migration.

```php
add_filter('media_toolkit_migration_batch_size', function(int $size): int {
    // Smaller batches on shared hosting
    if (defined('WPE_APIKEY')) {
        return 10;
    }
    return $size;
});
```

---

#### `media_toolkit_migration_attachments`

Filter attachments to migrate.

```php
add_filter('media_toolkit_migration_attachments', function(array $attachment_ids): array {
    // Only migrate images
    return array_filter($attachment_ids, function($id) {
        return wp_attachment_is_image($id);
    });
});
```

---

### CDN Filters

#### `media_toolkit_invalidation_paths`

Modify paths before CDN invalidation.

```php
add_filter('media_toolkit_invalidation_paths', function(array $paths, string $provider): array {
    // Add wildcard for CloudFront
    if ($provider === 'cloudfront') {
        $paths = array_map(fn($p) => rtrim($p, '*') . '*', $paths);
    }
    return $paths;
}, 10, 2);
```

---

### Settings Filters

#### `media_toolkit_default_settings`

Modify default settings.

```php
add_filter('media_toolkit_default_settings', function(array $defaults): array {
    $defaults['remove_local'] = true;
    $defaults['cache_control'] = 86400; // 1 day
    return $defaults;
});
```

---

## Actions

### Upload Actions

#### `media_toolkit_uploaded`

Fired after a file is uploaded to S3.

```php
add_action('media_toolkit_uploaded', function(int $attachment_id, string $s3_key, string $file_path): void {
    // Log to external service
    external_logger()->info('File uploaded to S3', [
        'attachment_id' => $attachment_id,
        's3_key' => $s3_key,
    ]);
}, 10, 3);
```

**Parameters:**
- `$attachment_id` (int) - WordPress attachment ID
- `$s3_key` (string) - S3 object key
- `$file_path` (string) - Original local file path

---

#### `media_toolkit_upload_failed`

Fired when an upload fails.

```php
add_action('media_toolkit_upload_failed', function(int $attachment_id, string $error, string $file_path): void {
    // Send notification
    wp_mail(
        get_option('admin_email'),
        'Media Toolkit Upload Failed',
        sprintf('Attachment %d failed: %s', $attachment_id, $error)
    );
}, 10, 3);
```

---

### Delete Actions

#### `media_toolkit_deleted`

Fired after a file is deleted from S3.

```php
add_action('media_toolkit_deleted', function(int $attachment_id, string $s3_key): void {
    // Clean up related data
    delete_post_meta($attachment_id, '_custom_s3_data');
}, 10, 2);
```

---

### Migration Actions

#### `media_toolkit_migration_started`

Fired when migration begins.

```php
add_action('media_toolkit_migration_started', function(int $total_files): void {
    // Disable other heavy processes
    remove_action('save_post', 'expensive_operation');
});
```

---

#### `media_toolkit_migration_complete`

Fired when migration completes.

```php
add_action('media_toolkit_migration_complete', function(int $migrated, int $failed): void {
    // Send summary notification
    wp_mail(
        get_option('admin_email'),
        'Media Toolkit Migration Complete',
        sprintf('Migrated: %d, Failed: %d', $migrated, $failed)
    );
}, 10, 2);
```

---

#### `media_toolkit_migration_batch_complete`

Fired after each batch is processed.

```php
add_action('media_toolkit_migration_batch_complete', function(int $processed, int $remaining): void {
    // Update external progress tracker
    update_option('migration_progress', [
        'processed' => $processed,
        'remaining' => $remaining,
    ]);
}, 10, 2);
```

---

### Optimization Actions

#### `media_toolkit_optimized`

Fired after an image is optimized.

```php
add_action('media_toolkit_optimized', function(int $attachment_id, int $bytes_saved, float $percent_saved): void {
    // Track total savings
    $total = (int) get_option('media_toolkit_total_saved', 0);
    update_option('media_toolkit_total_saved', $total + $bytes_saved);
}, 10, 3);
```

---

#### `media_toolkit_optimization_complete`

Fired when batch optimization completes.

```php
add_action('media_toolkit_optimization_complete', function(int $optimized, int $skipped, int $total_saved): void {
    // Log summary
    error_log(sprintf(
        'Optimization complete: %d optimized, %d skipped, %s saved',
        $optimized,
        $skipped,
        size_format($total_saved)
    ));
}, 10, 3);
```

---

### AI Metadata Actions

#### `media_toolkit_ai_metadata_generated`

Fired after AI metadata is generated for an image.

```php
add_action('media_toolkit_ai_metadata_generated', function(int $attachment_id, string $provider, array $metadata): void {
    // Log the generation
    error_log(sprintf(
        'AI metadata generated for #%d using %s',
        $attachment_id,
        $provider
    ));
    
    // Send to external service
    external_api()->update_asset($attachment_id, [
        'alt_text' => $metadata['alt_text'],
        'title' => $metadata['title'],
    ]);
}, 10, 3);
```

**Parameters:**
- `$attachment_id` (int) - WordPress attachment ID
- `$provider` (string) - AI provider used (openai, claude, gemini)
- `$metadata` (array) - Generated metadata (title, alt_text, caption, description)

---

#### `media_toolkit_ai_metadata_batch_complete`

Fired when AI metadata batch processing completes.

```php
add_action('media_toolkit_ai_metadata_batch_complete', function(int $processed, int $failed, int $total): void {
    // Send notification
    wp_mail(
        get_option('admin_email'),
        'AI Metadata Generation Complete',
        sprintf('Processed: %d, Failed: %d, Total: %d', $processed, $failed, $total)
    );
}, 10, 3);
```

---

#### `media_toolkit_ai_generate_on_upload`

Internal cron hook fired for async AI metadata generation on upload. Can be used to track when uploads trigger AI generation.

```php
add_action('media_toolkit_ai_generate_on_upload', function(int $attachment_id): void {
    // This runs asynchronously after image upload
    error_log(sprintf('AI generation starting for attachment #%d', $attachment_id));
}, 5); // Priority 5 runs before the actual generation
```

---

### CDN Actions

#### `media_toolkit_cache_invalidated`

Fired after CDN cache is invalidated.

```php
add_action('media_toolkit_cache_invalidated', function(array $paths, string $provider): void {
    // Log invalidation
    error_log(sprintf('Invalidated %d paths on %s', count($paths), $provider));
}, 10, 2);
```

---

### Settings Actions

#### `media_toolkit_settings_saved`

Fired when settings are saved.

```php
add_action('media_toolkit_settings_saved', function(array $old_settings, array $new_settings): void {
    // Clear caches when settings change
    if ($old_settings['cdn_url'] !== $new_settings['cdn_url']) {
        wp_cache_flush();
    }
}, 10, 2);
```

---

## Hook Priorities

Use priorities to control execution order:

```php
// Run early (before other filters)
add_filter('media_toolkit_s3_key', $callback, 5, 3);

// Run late (after other filters)
add_filter('media_toolkit_s3_key', $callback, 99, 3);

// Default priority
add_filter('media_toolkit_s3_key', $callback, 10, 3);
```

---

## Best Practices

1. **Always return values** - Filters must return a value
2. **Check parameters** - Validate input before modifying
3. **Use specific hooks** - Prefer specific hooks over general ones
4. **Consider performance** - Avoid expensive operations in hooks
5. **Document changes** - Comment why you're modifying behavior

---

## Support

- **Documentation**: [docs/DOCUMENTATION.md](DOCUMENTATION.md)
- **Extending Guide**: [docs/EXTENDING.md](EXTENDING.md)
- **Website**: [metodo.dev](https://metodo.dev)

