# Media Toolkit - Documentation

Complete documentation for the Media Toolkit WordPress plugin.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Storage Providers](#storage-providers)
4. [CDN Integration](#cdn-integration)
5. [Image Optimization](#image-optimization)
6. [Image Resizing](#image-resizing)
7. [Migration](#migration)
8. [Reconciliation](#reconciliation)
9. [Caching & Headers](#caching--headers)
10. [Import/Export](#importexport)
11. [Troubleshooting](#troubleshooting)

---

## Installation

### Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- Cloud storage account (AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, or Wasabi)
- API credentials for your chosen provider
- GD Library or ImageMagick for image optimization
- Composer (for dependency management)

### Installation Steps

1. Download or clone the plugin to `/wp-content/plugins/media-toolkit/`
2. Run `composer install` to install dependencies
3. Activate the plugin through the **Plugins** menu
4. Navigate to **Media Toolkit → Settings** to configure

### Directory Structure

```
media-toolkit/
├── media-toolkit.php      # Entry point
├── src/                   # PHP source code
│   ├── Plugin.php         # Main plugin class
│   ├── Admin/             # Admin pages and AJAX handlers
│   ├── CDN/               # CDN integration (Cloudflare, CloudFront)
│   ├── Core/              # Core services (Settings, Logger, etc.)
│   ├── Error/             # Error handling and retry logic
│   ├── History/           # Operation history tracking
│   ├── Media/             # Media handlers (Upload, Optimize, etc.)
│   ├── Migration/         # Bulk migration tools
│   ├── Storage/           # Multi-provider storage abstraction
│   │   ├── Providers/     # Provider implementations
│   │   └── ...            # Interfaces and base classes
│   └── Stats/             # Statistics and dashboard data
├── assets/                # CSS and JavaScript files
├── templates/             # Admin page templates
└── vendor/                # Composer dependencies
```

---

## Configuration

### Storage Provider Selection

Navigate to **Media Toolkit → Settings → Storage Provider** to configure your cloud storage.

#### Supported Providers

| Provider | Description | Requirements |
|----------|-------------|--------------|
| **Amazon S3** | Industry standard with global CDN options | Access Key, Secret Key, Region, Bucket |
| **Cloudflare R2** | Zero egress fees, requires CDN URL | Account ID, Access Key, Secret Key, Bucket, **CDN URL** |
| **DigitalOcean Spaces** | Simple S3-compatible with built-in CDN | Access Key, Secret Key, Region, Bucket |
| **Backblaze B2** | Cost-effective S3-compatible storage | Key ID, Application Key, Region, Bucket |
| **Wasabi** | Hot cloud storage, no egress fees | Access Key, Secret Key, Region, Bucket |

#### Provider-Specific Notes

- **Cloudflare R2**: Does not provide public URLs. You **must** configure a CDN URL or custom domain in the CDN tab for files to be publicly accessible.
- **Backblaze B2**: Bucket must have "S3 Compatibility" enabled in B2 settings.
- **Wasabi**: Has minimum storage charge (1TB) and 90-day retention policy.

**Security Note:** Credentials are encrypted using AES-256-CBC with WordPress salts before storage.

### Required IAM Permissions

Create an IAM user with the following policy:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:PutObjectAcl",
                "s3:CopyObject"
            ],
            "Resource": "arn:aws:s3:::your-bucket-name/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket",
                "s3:HeadBucket"
            ],
            "Resource": "arn:aws:s3:::your-bucket-name"
        }
    ]
}
```

### Environment Support

The plugin supports multiple environments with separate S3 paths:

| Environment | S3 Path |
|-------------|---------|
| Production | `media/production/wp-content/uploads/` |
| Staging | `media/staging/wp-content/uploads/` |
| Development | `media/development/wp-content/uploads/` |

Switch environments in **Settings → Environment**.

---

## Storage Offloading

### Automatic Upload

Once configured, all new media uploads are automatically offloaded to S3:

1. WordPress handles the initial upload to the server
2. Plugin uploads file and all thumbnails to S3
3. URLs are rewritten to serve from S3/CDN
4. Optionally, local files are deleted

### URL Rewriting

The plugin automatically rewrites URLs to point to S3 or CDN:

```
Original: https://example.com/wp-content/uploads/2024/01/image.jpg
Rewritten: https://cdn.example.com/media/production/wp-content/uploads/2024/01/image.jpg
```

### Post Meta Storage

For each migrated attachment, the plugin stores:

| Meta Key | Description |
|----------|-------------|
| `_media_toolkit_migrated` | Boolean flag indicating offload status |
| `_media_toolkit_key` | S3 object key for main file |
| `_media_toolkit_url` | Public URL (CDN or S3) |
| `_media_toolkit_thumb_keys` | Array of thumbnail S3 keys |

---

## CDN Integration

### Cloudflare

1. Select **Cloudflare** as CDN Provider
2. Enter your CDN URL (e.g., `https://media.yourdomain.com`)
3. For automatic cache purging:
   - Enter your Cloudflare Zone ID
   - Create an API Token with "Zone.Cache Purge" permission

### CloudFront

1. Select **CloudFront** as CDN Provider
2. Enter your CloudFront distribution URL
3. Enter the Distribution ID for cache invalidation
4. Add IAM permission:

```json
{
    "Effect": "Allow",
    "Action": "cloudfront:CreateInvalidation",
    "Resource": "arn:aws:cloudfront::ACCOUNT_ID:distribution/DISTRIBUTION_ID"
}
```

### Cache Invalidation

When files are updated or deleted:

1. The plugin queues paths for invalidation
2. Paths are batched (up to 15 per request for Cloudflare)
3. Background cron processes the invalidation queue

---

## Image Optimization

The Optimize page is organized into three tabs: **Dashboard**, **Optimize**, and **Resize**.

### Dashboard Tab

Overview of optimization status:

- Total images count
- Optimized vs pending images
- Space saved from optimization
- Resize statistics
- Server capabilities (GD, ImageMagick, WebP support)

### Optimize Tab

#### Supported Formats

| Format | Method | Settings |
|--------|--------|----------|
| JPEG | GD/ImageMagick | Quality 60-100% |
| PNG | GD/ImageMagick | Compression 0-9 |
| GIF | Preserved | No changes to animated GIFs |
| WebP | GD/ImageMagick | Quality 60-100% |

#### Optimization Process

1. Original image is compressed locally
2. All thumbnails are optimized
3. Files are re-uploaded to S3
4. Space savings are tracked

#### Settings

Navigate to **Media Toolkit → Optimize → Optimize tab**:

| Setting | Description | Default |
|---------|-------------|---------|
| JPEG Quality | Compression quality | 82% |
| PNG Compression | Compression level | 6 |
| Strip Metadata | Remove EXIF data | Enabled |
| Min Savings | Minimum % to keep | 5% |
| Max File Size | Skip larger files | 10 MB |

#### Batch Optimization

1. Go to **Media Toolkit → Optimize → Optimize**
2. Configure settings
3. Click **Start Optimization**
4. Monitor progress in real-time

---

## Image Resizing

Automatically resize oversized images when they are uploaded to WordPress.

### Overview

The resize feature intercepts uploads and automatically resizes images that exceed the configured maximum dimensions. This:

- Reduces server space usage
- Speeds up your website
- Saves bandwidth
- Improves SEO (faster page loads)

### Supported Formats

| Format | Resize | Notes |
|--------|--------|-------|
| JPEG | ✅ | Full support |
| PNG | ✅ | Transparency preserved |
| GIF | ✅ | Non-animated only |
| WebP | ✅ | Full support |
| BMP | ✅ | Converts to JPEG |

### Settings

Navigate to **Media Toolkit → Optimize → Resize tab**:

| Setting | Description | Default |
|---------|-------------|---------|
| Enabled | Enable auto-resize on upload | Disabled |
| Max Width | Maximum width in pixels (0 = no limit) | 2560 |
| Max Height | Maximum height in pixels (0 = no limit) | 2560 |
| JPEG Quality | Compression quality when resizing | 82% |
| Convert BMP | Convert BMP to JPEG automatically | Enabled |

### Quick Presets

One-click presets for common use cases:

| Preset | Dimensions | Use Case |
|--------|------------|----------|
| Full HD | 1920 × 1920 | Standard web displays |
| 2K / Retina | 2560 × 2560 | High-DPI displays (recommended) |
| 4K Ultra HD | 3840 × 3840 | Large displays, print |
| Blog / Web | 1200 × 1200 | Blog posts, small images |

### How It Works

1. User uploads an image via WordPress Media Library
2. Plugin intercepts the upload (before thumbnails are generated)
3. If image exceeds max dimensions, it's resized maintaining aspect ratio
4. Thumbnails are then generated from the resized image
5. Statistics are updated

### BMP Conversion

BMP files are automatically converted to JPEG when the "Convert BMP to JPEG" option is enabled:

1. BMP file is detected on upload
2. Image is converted to JPEG using configured quality
3. Original BMP is deleted
4. New JPEG file is used for the attachment

### Statistics

The resize feature tracks:

- Total images resized
- Total bytes saved
- BMP files converted
- Last resize timestamp

### Independence from S3

The resize feature works independently of S3 configuration:

- ✅ Works without S3 configured
- ✅ Works with S3 configured (resized images are then offloaded)
- ✅ Works before thumbnail generation

### Programmatic Usage

```php
use Metodo\MediaToolkit\Media\Image_Resizer;

// Get resizer instance
$plugin = \Metodo\MediaToolkit\media_toolkit();
$resizer = $plugin->get_image_resizer();

// Get settings
$settings = $resizer->get_resize_settings();

// Manually resize an attachment
$result = $resizer->resize_attachment($attachment_id);

// Resize a file directly
$result = $resizer->resize_image(
    $file_path,
    $max_width,
    $max_height,
    $jpeg_quality
);
```

---

## Migration

### Bulk Migration

Migrate existing media library to S3:

1. Go to **Media Toolkit → Tools → Migration**
2. Review statistics (total files, pending, size)
3. Select batch size (10, 25, 50, 100)
4. Click **Start Migration**

### Migration Process

For each attachment:

1. Main file is uploaded to S3
2. All thumbnails are uploaded
3. Post meta is updated with S3 keys
4. URL is updated in post content (optional)
5. Local file is deleted (optional)

### Resume Support

Migration can be paused and resumed:

- State is saved in transients
- Failed uploads are queued for retry
- Progress is preserved across sessions

---

## Reconciliation

When files exist on S3 but WordPress doesn't know about them (e.g., manual uploads or plugin reinstall):

### Scan S3

1. Go to **Media Toolkit → Tools → Reconciliation**
2. Click **Scan S3**
3. Review matched and unmatched files
4. Click **Start Reconciliation**

### Process

1. List all objects in S3 bucket path
2. Match S3 keys to WordPress attachments
3. Update post meta for matched files
4. Report discrepancies

---

## Caching & Headers

### Cache-Control Headers

Configure HTTP cache headers for uploaded files:

| Value | Header |
|-------|--------|
| 1 year | `public, max-age=31536000, immutable` |
| 1 month | `public, max-age=2592000` |
| 0 | `no-cache, no-store, must-revalidate` |

### Content-Disposition

Configure how files are served:

| File Type | Options |
|-----------|---------|
| Images | Inline (default) |
| PDFs | Inline / Attachment |
| Videos | Inline (default) |
| Archives | Attachment (default) |

### Bulk Update Headers

Update Cache-Control headers on existing S3 files:

1. Go to **Media Toolkit → Tools → Cache Headers**
2. Set desired max-age value
3. Click **Start Update**
4. Files are processed in batches

---

## Import/Export

The Import/Export feature allows you to backup settings or transfer configuration between sites.

### Accessing Import/Export

Navigate to **Media Toolkit → Settings → Import/Export** tab.

### Export Settings

1. Review what will be exported (listed in the green box)
2. Click **Export Settings**
3. A JSON file will be downloaded

#### What's Included

All settings with `media_toolkit_` prefix are automatically included:

- Active environment
- Cache-Control settings
- Content-Disposition settings (by file type)
- General options (remove local files, etc.)
- Update settings (auto-update preference)

#### What's Excluded (Security)

For security, the following are **never** exported:

- **AWS Credentials**: Access Key, Secret Key, Region, Bucket
- **GitHub Token**: Update authentication token
- **CDN API Tokens**: Cloudflare API token, CloudFront credentials
- **S3 Statistics Cache**: Runtime data that should be regenerated

> **Important:** Credentials must be configured manually on each site. This is intentional for security.

### Import Settings

1. Drag & drop a JSON file or click to browse
2. Review file info (version, export date, settings count)
3. Select **Merge with existing settings** if you want to combine with current settings
4. Click **Import Settings**
5. Page will reload with imported settings

#### Import Options

| Option | Description |
|--------|-------------|
| **Replace** (default) | Imported settings completely replace existing ones |
| **Merge** | Imported settings are combined with existing ones |

### Export Format

```json
{
    "export_format": "2.0",
    "plugin_version": "1.1.0",
    "exported_at": "2024-01-15T12:00:00+00:00",
    "site_url": "https://example.com/",
    "options": {
        "media_toolkit_active_env": "production",
        "media_toolkit_cache_control": 31536000,
        "media_toolkit_content_disposition": {
            "image": "inline",
            "pdf": "inline",
            "video": "inline",
            "archive": "attachment"
        },
        "media_toolkit_remove_local": false,
        "media_toolkit_remove_on_uninstall": false,
        ...
    }
}
```

### Future-Proof Design

The export system uses auto-discovery. When new settings are added to the plugin:

- They are **automatically included** in exports
- They are **automatically imported** without any code changes
- Backward compatibility is maintained with older exports

### Programmatic Export/Import

```php
use Metodo\MediaToolkit\Tools\Exporter;
use Metodo\MediaToolkit\Tools\Importer;
use Metodo\MediaToolkit\Core\Encryption;

// Export
$encryption = new Encryption();
$exporter = new Exporter($encryption);
$data = $exporter->export();

// Get list of exportable options
$options = $exporter->getExportableOptions();

// Get info about what's excluded
$excluded = $exporter->getExcludedInfo();

// Validate before import
$importer = new Importer();
$validation = $importer->validate($data);

if ($validation['valid']) {
    $importer->import($data, mergeExisting: false);
}
```

### Hooks

```php
// Filter export data before download
add_filter('media_toolkit_export_data', function($data) {
    // Add custom data
    $data['custom_key'] = 'custom_value';
    return $data;
});

// Filter import data before processing
add_filter('media_toolkit_import_data', function($data) {
    // Modify data before import
    return $data;
});

// Action after import completes
add_action('media_toolkit_after_import', function($data) {
    // Clear caches, etc.
});
```

---

## Troubleshooting

### Connection Test Fails

1. Verify AWS credentials are correct
2. Check IAM user permissions
3. Ensure bucket exists in specified region
4. Check for AWS service outages

### Files Not Uploading

1. Check **Logs** page for error details
2. Verify PHP memory and execution time limits
3. Ensure file size doesn't exceed limits
4. Check S3 bucket permissions

### CDN URLs Not Working

1. Verify CDN URL is correct
2. Check CDN configuration for S3 origin
3. Configure CORS on S3 bucket if needed
4. Verify SSL certificates

### Migration Stops

1. Increase `max_execution_time` in PHP
2. Increase WordPress memory limit
3. Reduce batch size
4. Check server error logs

### Debug Logging

Logs are stored in the database and viewable at **Media Toolkit → Logs**.

#### Activity Logs Tab

Real-time operation logs with:

- Filter by level (info, warning, error, success)
- Filter by operation type
- Auto-refresh for real-time monitoring
- Export logs

#### Optimization Status Tab

View and manage optimization records:

| Column | Description |
|--------|-------------|
| ID | Attachment ID |
| File | Attachment filename |
| Status | optimized, pending, failed, skipped |
| Original | Original file size |
| Optimized | Size after optimization |
| Saved | Percentage of space saved |
| Optimized At | Timestamp of optimization |

**Features:**

- Filter by status (optimized, pending, failed, skipped)
- Paginated table for large datasets
- Summary stats (optimized count, pending, failed, space saved)
- Retry failed records with one click

---

## Support

- **Documentation**: [docs/DOCUMENTATION.md](DOCUMENTATION.md)
- **Hooks Reference**: [docs/HOOKS.md](HOOKS.md)
- **Extending Guide**: [docs/EXTENDING.md](EXTENDING.md)
- **Website**: [metodo.dev](https://metodo.dev)
- **Contact**: plugins@metodo.dev

