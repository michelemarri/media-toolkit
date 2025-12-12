# Media Toolkit - Documentation

Complete documentation for the Media Toolkit WordPress plugin.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Storage Providers](#storage-providers)
4. [CDN Integration](#cdn-integration)
5. [Image Optimization](#image-optimization)
6. [Image Resizing](#image-resizing)
7. [AI Metadata Generation](#ai-metadata-generation)
8. [CloudSync](#cloudsync)
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

#### URL Rewriting Hooks

| Hook | Description |
|------|-------------|
| `wp_get_attachment_url` | Main attachment URL |
| `wp_get_attachment_image_src` | Image source array |
| `wp_calculate_image_srcset` | Responsive images srcset |
| `wp_prepare_attachment_for_js` | Media Library JS data |
| `the_content` | Post content URLs |
| `the_excerpt` | Excerpt URLs |
| `widget_text_content` | Widget text URLs |

#### Content URL Rewriting

The plugin automatically fixes image URLs in post content that may have been saved incorrectly. This handles:

1. **Relative paths without slash**: `src="media/production/wp-content/uploads/..."`
2. **Relative paths with slash**: `src="/media/production/wp-content/uploads/..."`
3. **srcset attributes**: Responsive image URLs in srcset
4. **Corrupted absolute URLs**: `src="https://site.com/page/media/production/wp-content/uploads/..."`

The last case (corrupted URLs) occurs when relative storage paths are resolved against the current page URL, resulting in URLs like `https://site.com/some-page/media/production/...`. The plugin detects and fixes these by extracting the storage path and rewriting with the correct CDN base URL.

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
- Comprehensive server capabilities check

#### Server Capabilities

The dashboard includes a detailed server capabilities panel that verifies your server can perform image optimization:

**Status Indicator**
- ✅ **Optimization Ready**: All requirements met, functional test passed
- ⚠️ **Issues Detected**: Problems found that may prevent optimization

**Image Libraries**
| Library | Description |
|---------|-------------|
| GD Library | PHP's built-in image processing library (with version number) |
| ImageMagick | More powerful library for advanced image processing (with version) |
| WP Image Editor | Shows which library WordPress is actually using |

**Supported Formats**
Visual indicators for each format:
- JPEG - Core format, always required
- PNG - Lossless compression support
- GIF - Including animated GIF handling
- WebP - Modern web format
- AVIF - Next-gen format (if available)

**Server Limits**
- Memory Limit - PHP memory available for processing
- Max Execution Time - Maximum script runtime
- Upload Max Size - Maximum file upload size

**Functional Test**
The plugin performs a real test to verify optimization works:
1. Creates a small test image
2. Processes it through WordPress image editor
3. Verifies the output file is valid
4. Reports success or detailed error message

This catches issues like:
- Missing library dependencies
- File permission problems
- Corrupted PHP extensions
- WordPress configuration issues

### Optimize Tab

#### Multi-Driver Optimizer System

The plugin automatically detects and uses the best available optimization tool for each format:

| Format | Priority | Tools (best to fallback) |
|--------|----------|--------------------------|
| JPEG | High | mozjpeg → jpegoptim → ImageMagick → GD |
| PNG | High | pngquant (lossy) → oxipng → optipng → ImageMagick → GD |
| GIF | Medium | gifsicle → ImageMagick → GD |
| WebP | High | cwebp → ImageMagick → GD |
| AVIF | High | avifenc → ImageMagick |
| SVG | Low | svgo |

**Tool Benefits:**
- **mozjpeg**: 5-15% better JPEG compression than libjpeg
- **pngquant**: Up to 70% smaller PNG files (lossy, excellent quality)
- **oxipng**: Modern, multi-threaded lossless PNG optimizer
- **cwebp**: Native Google WebP encoder
- **avifenc**: AVIF encoder (20-50% smaller than WebP)
- **gifsicle**: GIF optimizer with animated GIF support
- **svgo**: SVG optimizer (removes unnecessary data)

#### Available Optimizers Panel

The Dashboard shows:
- Available tools by format with version numbers
- Best tool currently in use for each format
- Missing tools with installation instructions
- Recommendations for improving compression

#### Optimization Process

1. Backup original (if enabled) as `filename_original.ext`
2. Temporary backup is created before optimization
3. Original image is compressed using best available tool
4. **Size check**: If optimized file is larger than original, original is restored and image is marked as "skipped"
5. All thumbnails are optimized (same size-check logic applies)
6. WebP/AVIF versions generated (if enabled)

**Smart Size Protection:**
The optimizer automatically compares the file size before and after optimization. If compression results in a larger file (which can happen with already-optimized or highly-compressed images), the original is preserved and the image is skipped with a message indicating the would-be size increase.
5. All files re-uploaded to S3
6. Space savings are tracked

#### Settings

Navigate to **Media Toolkit → Optimize → Optimize tab**:

| Setting | Description | Default |
|---------|-------------|---------|
| Optimize on Upload | Auto-compress images when uploaded | Disabled |
| JPEG Quality | Compression quality | 82% |
| PNG Compression | Compression level | 6 |
| Strip Metadata | Remove EXIF data | Enabled |
| Min Savings | Minimum % to keep | 5% |
| Max File Size | Skip larger files | 10 MB |

#### Optimize on Upload

When enabled, images are automatically compressed during the upload process:

**Main file optimization:**
1. User uploads an image
2. **Resize** (priority 5): Image is resized if it exceeds max dimensions
3. **Optimize** (priority 7): Main image is compressed using configured settings
4. **Cloud Upload** (priority 10): Optimized image is uploaded to storage provider

**Thumbnail optimization:**
5. WordPress generates all thumbnail sizes
6. **Optimize Thumbnails** (priority 5): All thumbnails are compressed
7. **Cloud Upload** (priority 10): Optimized thumbnails are uploaded to storage provider

This ensures that both the main image and all thumbnails are optimized before being stored in the cloud, maximizing storage and bandwidth savings.

### Backup System

Keep original images before optimization with the `_original` suffix:

**How it works:**
```
wp-content/uploads/2024/01/
├── photo.jpg              # Optimized version
├── photo_original.jpg     # Backup of original (if enabled)
├── photo.webp             # WebP version (if enabled)
└── photo.avif             # AVIF version (if enabled)
```

**Settings:**
| Setting | Description | Default |
|---------|-------------|---------|
| Keep Original Backup | Save original before optimization | Disabled |
| Auto Cleanup | Delete backups after X days | Never |

**Cloud Storage Integration:**
- Backup files are automatically uploaded to cloud storage
- Stored with same key pattern: `uploads/2024/01/photo_original.jpg`
- Restore downloads from cloud, overwrites optimized, re-uploads

**Restore Process:**
1. Download `_original` from cloud (if needed)
2. Overwrite optimized file with original
3. Re-upload restored file to cloud
4. Delete backup file (local and cloud)
5. Reset optimization status to "pending"

### Format Conversion

Generate modern image formats alongside originals:

**WebP Conversion:**
- Creates `.webp` version alongside original
- 20-30% smaller than JPEG at equivalent quality
- Supported by 97%+ of browsers
- Uses: cwebp > ImageMagick > GD

**AVIF Conversion:**
- Creates `.avif` version alongside original
- 20-50% smaller than WebP
- Supported by 93%+ of browsers
- Uses: avifenc > ImageMagick > GD (PHP 8.1+)

**Settings:**
| Setting | Description | Default |
|---------|-------------|---------|
| Generate WebP | Create WebP versions | Disabled |
| WebP Quality | Quality setting | 80 |
| Generate AVIF | Create AVIF versions | Disabled |
| AVIF Quality | Quality setting | 50 |

**Cloud Storage:**
- Converted files uploaded with same path structure
- Stored as: `uploads/2024/01/photo.webp`, `uploads/2024/01/photo.avif`
- Metadata saved in post meta for URL retrieval

### Installing Optimization Tools

**Ubuntu/Debian:**
```bash
# JPEG
sudo apt install jpegoptim
# For mozjpeg, download from: https://github.com/mozilla/mozjpeg/releases

# PNG
sudo apt install pngquant optipng
# For oxipng: cargo install oxipng

# WebP
sudo apt install webp

# AVIF
sudo apt install libavif-bin  # Ubuntu 22.04+

# GIF
sudo apt install gifsicle

# SVG (requires Node.js)
npm install -g svgo
```

**macOS (Homebrew):**
```bash
brew install jpegoptim mozjpeg pngquant optipng oxipng webp libavif gifsicle
npm install -g svgo
```

#### Batch Optimization

For existing images that were uploaded before enabling "Optimize on Upload":

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

## AI Metadata Generation

Automatically generate image metadata (alt text, titles, captions, descriptions) using AI Vision providers.

### Supported Providers

| Provider | Models | Cost per Image |
|----------|--------|----------------|
| **OpenAI** | GPT-4o, GPT-4o Mini, GPT-4 Turbo | ~$0.001-0.01 |
| **Anthropic Claude** | Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus | ~$0.001-0.02 |
| **Google Gemini** | Gemini 1.5 Pro, Gemini 1.5 Flash, Gemini 1.5 Flash 8B | ~$0.0002-0.003 |

### Configuration

1. Go to **Media Toolkit → Settings → AI Providers**
2. Enter your API key for one or more providers
3. Select your preferred model for each provider
4. Drag to reorder provider priority (first provider is used, others are fallbacks)
5. Select the output language for generated metadata
6. Click **Save AI Settings**

### Provider Priority & Fallback

Providers are tried in order of priority. If the first provider fails (rate limit, network error), the next provider is automatically used. Configure multiple providers for reliability.

### Generated Metadata Fields

| Field | Character Limit | Purpose |
|-------|-----------------|---------|
| **Title** | 50-70 | Descriptive identifier for media library |
| **Alt Text** | Max 125 | Accessibility + Image SEO |
| **Caption** | 150-250 | Engaging text below images |
| **Description** | Unlimited | Full context with keywords |

### Batch Generation

1. Go to **Media Toolkit → AI Metadata → Generate**
2. Configure options:
   - **Only empty fields**: Generate only for images missing metadata
   - **Overwrite**: Replace existing metadata
   - **Batch size**: Number of images per batch (5, 10, 25)
3. Review cost estimation
4. Click **Start Generation**
5. Monitor progress and logs

### Single Image Generation

In the WordPress Media Library:

1. Click on any image to open the attachment modal
2. Scroll to the **AI Metadata** section
3. Click **Generate with AI**
4. Fields are automatically updated with AI-generated content

### Single Image Optimization

In the WordPress Media Library attachment modal:

1. Click on any image to open the attachment modal
2. The **Image Optimization** section shows:
   - **Status**: Optimized, Not optimized, Skipped, or Failed
   - **Savings**: Bytes saved and percentage (if optimized)
   - **Size**: Original → Optimized size comparison
   - **Thumbnails**: Number of thumbnail sizes included
   - **Error**: Details if optimization failed
3. Click **Optimize Now** to optimize unoptimized images
4. Click **Re-optimize** to re-process already optimized images

**Status meanings:**
| Status | Description |
|--------|-------------|
| ✓ Optimized | Image was successfully compressed |
| Not optimized | Image has not been processed yet |
| ⏭ Skipped | Image was skipped (file too large, no size reduction, unsupported type) |
| ✗ Failed | Optimization encountered an error |

**Common Skip Reasons:**
- File exceeds maximum size limit
- No size reduction (optimization would make file larger)
- Unsupported image type
- SVG optimization not available (svgo not installed)

### Cost Estimation

Before starting batch processing, the plugin estimates total cost based on:
- Number of images to process
- Selected provider and model
- Average cost per image

Review the estimate before confirming to avoid unexpected API charges.

### Rate Limiting

Built-in delays between API calls prevent rate limiting:
- OpenAI: 200ms delay
- Claude: 500ms delay
- Gemini: 100ms delay

Failed requests are automatically retried with exponential backoff.

### Image Processing

Images are automatically resized to max 1024px before sending to AI to reduce API costs and improve response times.

### Generate on Upload

Enable automatic AI metadata generation when new images are uploaded:

1. Go to **Media Toolkit → Settings → AI Providers**
2. Enable **Generate on Upload** toggle
3. Configure **Minimum Image Size** (default: 100px)
4. Save settings

**How it works:**
- When an image is uploaded, a background job is scheduled (5 seconds delay)
- The upload completes immediately - no waiting for AI
- AI analyzes the image asynchronously and saves metadata
- A "⏳ AI generation pending" indicator shows in Media Library
- Once complete, metadata fields are automatically populated

**Skipped images:**
- Images smaller than the minimum size (width or height)
- Images that already have alt text (not just filename)
- Images already processed by AI

---

## CloudSync

CloudSync is the unified tool for keeping your media library synchronized with cloud storage.

### Accessing CloudSync

Navigate to **Media Toolkit → CloudSync**.

### Status Overview

The CloudSync page displays:

| Metric | Description |
|--------|-------------|
| **Total Files** | All media attachments in WordPress |
| **On Cloud** | Files successfully uploaded to cloud storage |
| **Pending** | Files waiting to be uploaded |
| **Issues** | Integrity problems (marked as migrated but not on cloud) |

### Optimization Status

CloudSync shows the optimization status of your image library:

| Metric | Description |
|--------|-------------|
| **Total Images** | All image attachments in WordPress |
| **Optimized** | Images that have been optimized |
| **Pending** | Images not yet optimized |
| **Space Saved** | Total bytes saved from optimization |

**Recommendation:** Optimize images before syncing to cloud to reduce:
- Upload bandwidth
- Cloud storage costs
- CDN egress fees

A warning banner appears when syncing unoptimized images, with a direct link to the optimization page.

### Sync Modes

| Mode | Description |
|------|-------------|
| **Upload pending files** | Upload local files not yet on cloud |
| **Check and fix integrity** | Verify files exist on cloud, re-upload if missing |
| **Full sync + integrity check** | Complete sync with verification |

### Options

- **Batch Size**: 10, 25, 50, or 100 files per batch
- **Delete local files**: Remove local copies after successful upload (use with caution!)

### Suggested Actions

CloudSync analyzes your library and suggests actions:

1. **Fix integrity issues** (high priority): Files marked as migrated but not found on cloud
2. **Optimize images before sync** (high priority): Unoptimized images waiting to be uploaded
3. **Sync pending files** (medium priority): Files waiting to be uploaded
4. **Clean up orphan files** (low priority): Cloud files without WordPress attachment

### Advanced Actions

- **Deep Analyze**: Full scan of cloud storage to detect discrepancies
- **View Discrepancies**: Detailed view of files not matching between WordPress and cloud
- **Clear All Metadata**: Reset migration metadata (files on cloud are NOT deleted)

### Migration Process

For each attachment being synced:

1. Main file is uploaded to cloud storage
2. All thumbnails are uploaded
3. Post meta is updated with storage keys and URLs
4. Local file is deleted (if option enabled)

### Resume Support

Sync operations can be paused and resumed:

- State is saved in transients
- Failed uploads are queued for retry
- Progress is preserved across sessions

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

