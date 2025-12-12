# Media Toolkit

A powerful WordPress plugin for complete media management. Offload media files to multiple cloud storage providers (AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, Wasabi) with CDN support, image optimization, and advanced management tools.

**Author:** Metodo  
**Plugin URI:** https://metodo.dev  
**Contact:** plugins@metodo.dev

## Features

### 🚀 Multi-Provider Storage
- **5 Storage Providers Supported**:
  - **Amazon S3**: Industry standard with global CDN options
  - **Cloudflare R2**: Zero egress fees, requires CDN URL for public access
  - **DigitalOcean Spaces**: Simple S3-compatible storage with built-in CDN
  - **Backblaze B2**: Cost-effective storage with S3 compatibility
  - **Wasabi**: Hot cloud storage with no egress fees
- Automatically uploads new media files to your chosen provider
- Supports all file types handled by WordPress Media Library
- Preserves original file structure (`wp-content/uploads/YYYY/MM/filename.ext`)
- Handles image thumbnails and all generated sizes
- Automatic URL rewriting for seamless media serving

### 🌍 Multi-Environment Support
- Separate folder structure for **Production**, **Staging**, and **Development** environments
- Files are stored as: `bucket/media/{environment}/wp-content/uploads/...`
- Easy environment switching without affecting other environments' files
- Ideal for development workflows and staging sites

### 🔄 CDN Integration
- **Cloudflare**: Automatic cache purging when files are updated or deleted
- **CloudFront**: Cache invalidation support with Distribution ID
- **Custom CDN**: Use any CDN with custom URL mapping
- Seamless URL rewriting for served files
- Batched cache invalidation for efficiency (up to 15 paths per request)

### ☁️ CloudSync
Unified tool for keeping your media library in sync with cloud storage:

- **Smart Analysis**: Automatically detects pending uploads, integrity issues, and orphan files
- **Batch Upload**: Migrate existing media to cloud with configurable batch sizes
- **Integrity Check**: Finds files marked as migrated but missing from cloud storage
- **Auto-Fix**: Re-uploads from local backup or cleans orphan metadata
- **Deep Scan**: Full cloud inventory with discrepancy detection
- **Progress Tracking**: Real-time progress with pause/resume capability
- **Admin Notices**: Global warnings when sync issues are detected
- **Optimization Status**: Shows optimization progress alongside sync status
  - Warns when syncing unoptimized images
  - Suggests optimizing before upload to save bandwidth and storage costs
  - Displays total space saved from optimization

### 🖼️ Image Optimization
Compress and optimize your media library images to save storage and bandwidth:

- **Multi-Driver Optimizer System**: Automatically uses the best available tool for each format
  - **JPEG**: mozjpeg > jpegoptim > ImageMagick > GD
  - **PNG**: pngquant (lossy) > oxipng > optipng > ImageMagick
  - **WebP**: cwebp > ImageMagick > GD
  - **AVIF**: avifenc > ImageMagick
  - **GIF**: gifsicle > ImageMagick > GD
  - **SVG**: svgo
- **Optimize on Upload**: Automatically compress images when uploaded (after resize, before cloud upload)
- **JPEG Compression**: Configurable quality (60-100%)
- **PNG Compression**: Lossless compression levels (0-9)
- **GIF Support**: Automatic handling (animated GIFs are preserved)
- **WebP/AVIF Support**: Optimize existing files and convert from JPEG/PNG
- **EXIF Stripping**: Remove camera metadata, GPS data, etc.
- **Batch Processing**: Optimize images in bulk with progress tracking
- **Thumbnail Optimization**: Automatically optimizes all generated sizes
- **S3 Re-upload**: Optimized images are automatically re-uploaded to S3
- **Space Savings Tracking**: Monitor how much storage you've saved
- **Server Capability Detection**: Auto-detects available CLI tools with recommendations

### 🔄 Format Conversion
Generate modern formats for better performance:

- **WebP Conversion**: Create .webp version alongside original (20-30% smaller)
- **AVIF Conversion**: Create .avif version (20-50% smaller than WebP)
- **Cloud Storage Integration**: Converted files are automatically uploaded to S3/R2/etc.
- **Keep Original**: Original format is always preserved
- **Quality Settings**: Configurable quality for each format

### 💾 Backup System
Keep original images before optimization:

- **Backup Before Optimize**: Save original as `filename_original.ext`
- **Same Directory**: Backup stays alongside optimized file
- **Cloud Sync**: Backups are uploaded to cloud storage
- **Restore Capability**: One-click restore from backup
- **Auto-Cleanup**: Optional cleanup after X days

### 📐 Automatic Image Resizing
Automatically resize oversized images when they are uploaded:

- **Upload-Time Resizing**: Resize images automatically when uploaded to WordPress
- **Max Width/Height**: Set maximum dimensions (e.g., 2560px for retina displays)
- **Aspect Ratio**: Maintains original aspect ratio when resizing
- **Supported Formats**: JPEG, PNG, GIF, WebP
- **BMP Conversion**: Automatically convert BMP to JPEG for space savings
- **Quick Presets**: One-click presets for Full HD, 2K/Retina, 4K, and Web sizes
- **Statistics**: Track total images resized and space saved
- **Independent of S3**: Works with or without S3 offloading configured

### 🤖 AI Metadata Generation
Automatically generate image metadata using AI Vision:

- **Multi-Provider Support**: OpenAI GPT-4o, Anthropic Claude, Google Gemini
- **Generate Complete Metadata**:
  - **Title**: 50-70 character descriptive identifier
  - **Alt Text**: Accessibility-optimized, max 125 characters
  - **Caption**: Engaging, 150-250 characters
  - **Description**: Full context with keywords, unlimited length
- **Generate on Upload**: Automatically generate metadata when new images are uploaded (async, non-blocking)
- **Batch Processing**: Process entire media library with progress tracking
- **Background Processing**: Continue processing even with browser closed (via WP Cron)
- **Cost Estimation**: Preview estimated API costs before starting
- **Multi-Language**: Generate metadata in any supported language
- **Fallback System**: Automatic failover between providers
- **Rate Limiting**: Built-in delays to respect API limits
- **Media Library Button**: One-click generation for individual images
- **Statistics Dashboard**: Track metadata completeness across your library
- **Smart Filtering**: Skip small images (icons/placeholders) based on minimum size setting

### 📚 Media Library Integration
Enhanced Media Library with S3 status and actions:

- **S3 Status Column**: Visual badge showing "S3" (offloaded) or "Local" status
- **Optimization Badge**: Shows space saved for optimized images (-X KB)
- **Quick Actions**: Direct links to view file on CDN
- **Attachment Details**: Full S3 info in the attachment modal
  - S3 Key and CDN URL
  - Local file status
  - Space savings from optimization
- **Optimization Section**: Dedicated section in attachment modal
  - View optimization status (Optimized, Not optimized, Skipped, Failed)
  - See original vs optimized size with percentage saved
  - Number of thumbnails included
  - One-click "Optimize Now" or "Re-optimize" button
- **Row Actions**: Upload, Re-upload, or Download from S3
- **Bulk Actions**: Process multiple files at once
- **Sortable Column**: Sort media by S3 status

### 🔃 Reconciliation Tool
Sync S3 bucket state with WordPress metadata:

- Scan S3 bucket to find all files
- Match files with WordPress attachments
- Update migration metadata for files already on S3
- Useful when files were uploaded before plugin installation
- Preview mode to see changes before applying
- Reset metadata option for clean slate

### 🖼️ Image Editing Support
- Full compatibility with WordPress image editor
- Crop, rotate, flip operations work seamlessly
- Edited images are automatically uploaded to S3
- Old versions are cleaned up from S3
- CDN cache is automatically purged for edited images

### 📊 Dashboard & Monitoring
- **Statistics**: Total files, storage used, daily uploads, error count
- **Activity Chart**: Visual representation of upload activity (last 7 days)
- **Migration Status**: Progress tracking with percentage complete
- **Connection Status**: Real-time S3 connection monitoring
- **S3 Stats Sync**: Periodic synchronization with S3 for accurate statistics

### 📝 Logging & History
- **Logs**: Detailed operation logs with filtering by level and type
- **History**: Complete audit trail of all file operations
- Export history to CSV
- Automatic log cleanup (configurable retention)
- Operation types: uploaded, migrated, deleted, edited, optimized

### 🔒 Security
- AWS credentials encrypted at rest using WordPress salts
- Secure credential masking in admin UI
- Permission checks on all admin actions
- Nonce verification for all AJAX requests
- Sensitive data never exposed in logs

### ⚙️ Advanced Options
- **Remove Local Files**: Option to delete local files after S3 upload (save disk space)
- **Cache-Control Headers**: Configurable cache headers for uploaded files (1 day to 1 year)
- **Content-Disposition**: Configure how files are served (inline vs attachment) by file type
- **S3 Stats Sync**: Periodic sync with S3 for accurate statistics (hourly to weekly)
- **Automatic Retry**: Failed operations are automatically retried every 15 minutes
- **Bulk Cache Header Update**: Update Cache-Control headers on existing S3 files

### 🛠️ Tools
The Tools page provides several maintenance utilities:

- **Migration**: Migrate existing media library to S3
- **Stats Sync**: Manually sync statistics from S3 bucket
- **Cache Headers**: Bulk update Cache-Control headers on all S3 files
- **Reconciliation**: Sync S3 state with WordPress metadata

## Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- Amazon S3 bucket with appropriate permissions
- AWS IAM credentials with S3 access
- GD Library or ImageMagick for image optimization

## Installation

1. Upload the `media-toolkit` folder to `/wp-content/plugins/`
2. Run `composer install` to install dependencies
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Media Toolkit → Settings** to configure your AWS credentials

## Configuration

### AWS Credentials

1. Create an IAM user in AWS with the following permissions:
   - `s3:PutObject`
   - `s3:GetObject`
   - `s3:DeleteObject`
   - `s3:ListBucket`
   - `s3:HeadBucket`
   - `s3:CopyObject` (for cache header updates)

2. Generate Access Key and Secret Key for the IAM user

3. Enter the credentials in **Media Toolkit → Settings**:
   - AWS Access Key
   - AWS Secret Key
   - AWS Region
   - S3 Bucket Name

### CDN Configuration (Optional)

#### Cloudflare
1. Select "Cloudflare" as CDN Provider
2. Enter your CDN URL (e.g., `https://media.yourdomain.com`)
3. For automatic cache purging:
   - Enter your Cloudflare Zone ID
   - Create an API Token with "Zone.Cache Purge" permission

#### CloudFront
1. Select "CloudFront" as CDN Provider
2. Enter your CloudFront distribution URL
3. Enter the Distribution ID for cache invalidation

### Recommended IAM Policy

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

If using CloudFront cache invalidation, add:

```json
{
    "Effect": "Allow",
    "Action": "cloudfront:CreateInvalidation",
    "Resource": "arn:aws:cloudfront::YOUR_ACCOUNT_ID:distribution/YOUR_DISTRIBUTION_ID"
}
```

## S3 Bucket Structure

Files are organized in a clean, environment-separated structure:

```
your-bucket/
└── media/
    ├── production/
    │   └── wp-content/
    │       └── uploads/
    │           └── 2024/
    │               └── 01/
    │                   ├── image.jpg
    │                   ├── image-150x150.jpg
    │                   ├── image-300x200.jpg
    │                   └── ...
    ├── staging/
    │   └── wp-content/
    │       └── uploads/
    │           └── ...
    └── development/
        └── wp-content/
            └── uploads/
                └── ...
```

## Usage

### New Uploads
Once configured, all new media uploads are automatically sent to S3. No action required.

### Migrating Existing Media

1. Go to **Media Toolkit → Tools → Migration**
2. Review the migration statistics (total files, pending, size)
3. Configure batch size and options
4. Click **Start Migration**
5. Monitor progress in real-time
6. Pause/Resume as needed

### Image Optimization

1. Go to **Media Toolkit → Optimize → Optimize tab**
2. Configure compression settings:
   - JPEG Quality (60-100%)
   - PNG Compression Level (0-9)
   - Strip EXIF metadata
   - Max file size to process
3. Click **Start Optimization**
4. Monitor progress and space savings

### Automatic Image Resizing

1. Go to **Media Toolkit → Optimize → Resize tab**
2. Enable "Auto-Resize on Upload"
3. Configure resize settings:
   - Max Width (e.g., 2560 for retina displays)
   - Max Height (e.g., 2560 for retina displays)
   - JPEG Quality for resized images
   - BMP to JPEG conversion (optional)
4. Or use Quick Presets for common sizes
5. Click **Save Settings**
6. All new uploads will be automatically resized if they exceed the limits

### Reconciliation

Use this when files exist on S3 but WordPress doesn't know about them:

1. Go to **Media Toolkit → Tools → Reconciliation**
2. Click **Scan S3** to preview changes
3. Review matching files and discrepancies
4. Click **Start Reconciliation** to sync metadata

### Dashboard

The **Dashboard** page provides an overview of:
- Total files on S3
- Storage used
- Files uploaded today
- Errors in the last 7 days
- Migration progress
- Upload activity chart

### Viewing Logs

Go to **Media Toolkit → Logs** to view:
- All plugin operations
- Filter by log level (info, warning, error, success)
- Filter by operation type
- Auto-refresh for real-time monitoring

### Viewing History

Go to **Media Toolkit → History** to see:
- Complete file operation history
- Filter by action type (uploaded, migrated, deleted, edited, optimized)
- Filter by date range
- Export to CSV

## Settings Reference

| Setting | Description | Default |
|---------|-------------|---------|
| Active Environment | Determines the S3 folder path | Production |
| Remove Local Files | Delete files from server after S3 upload | Disabled |
| Cache-Control | HTTP cache header for uploaded files | 1 year |
| Content-Disposition | How files are served (inline/attachment) | By file type |
| S3 Stats Sync | How often to sync statistics from S3 | Daily |
| Delete on Uninstall | Remove plugin data when uninstalling | Disabled |

### Image Optimization Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Optimize on Upload | Auto-compress images when uploaded | Disabled |
| JPEG Quality | Compression quality for JPEG images | 82% |
| PNG Compression | Compression level for PNG images (0-9) | 6 |
| Strip Metadata | Remove EXIF/camera data from images | Enabled |
| WebP Quality | Compression quality for WebP images | 80% |
| Min Savings % | Minimum savings threshold to keep optimization | 5% |
| Max File Size | Skip files larger than this | 10 MB |

### Image Resize Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Enabled | Auto-resize images on upload | Disabled |
| Max Width | Maximum image width in pixels (0 = no limit) | 2560 |
| Max Height | Maximum image height in pixels (0 = no limit) | 2560 |
| JPEG Quality | Compression quality when resizing | 82% |
| Convert BMP | Convert BMP images to JPEG | Enabled |

## Hooks & Filters

### Actions

```php
// Fired after a file is uploaded to S3
do_action('media_toolkit_uploaded', $attachment_id, $s3_key, $file_path);

// Fired after a file is deleted from S3
do_action('media_toolkit_deleted', $attachment_id, $s3_key);

// Fired after migration completes
do_action('media_toolkit_migration_complete', $total_migrated, $total_failed);

// Fired after an image is optimized
do_action('media_toolkit_optimized', $attachment_id, $bytes_saved, $percent_saved);

// Fired after an image is resized on upload
do_action('media_toolkit_resized', $attachment_id, $bytes_saved, $original_dimensions, $new_dimensions);

// Fired after AI metadata is generated for an image
do_action('media_toolkit_ai_metadata_generated', $attachment_id, $provider, $metadata);
```

### Filters

```php
// Modify the S3 key before upload
$s3_key = apply_filters('media_toolkit_s3_key', $s3_key, $attachment_id, $file_path);

// Modify upload parameters
$params = apply_filters('media_toolkit_upload_params', $params, $attachment_id);

// Skip specific files from offloading
$skip = apply_filters('media_toolkit_skip_file', false, $attachment_id, $file_path);

// Modify optimization settings per file
$settings = apply_filters('media_toolkit_optimize_settings', $settings, $attachment_id);

// Modify resize settings per file
$settings = apply_filters('media_toolkit_resize_settings', $settings, $file_path);

// Skip specific files from resizing
$skip = apply_filters('media_toolkit_skip_resize', false, $file_path, $mime_type);
```

## Admin Pages

| Page | Description |
|------|-------------|
| Dashboard | Overview statistics and activity chart |
| Settings | AWS credentials, CDN, and general settings |
| Tools | Migration, Stats Sync, Cache Headers, Reconciliation |
| Optimize | Image optimization with Dashboard, Optimize, and Resize tabs |
| AI Metadata | AI-powered metadata generation with Dashboard and Generate tabs |
| Logs | Activity Logs (real-time logs) and Optimization Status tabs |
| History | File operation history with export |

## Troubleshooting

### Connection Test Fails

1. Verify your AWS credentials are correct
2. Check that the IAM user has the required permissions
3. Ensure the bucket exists and is in the specified region
4. Check for any AWS service outages

### Files Not Uploading

1. Check the **Logs** page for error details
2. Verify PHP has sufficient memory and execution time
3. Ensure the file size doesn't exceed your S3 limits
4. Check that the plugin is properly configured

### CDN URLs Not Working

1. Verify the CDN URL is correct and accessible
2. Check that your CDN is properly configured to serve from S3
3. Ensure CORS is configured on your S3 bucket if needed
4. Verify SSL certificates are valid

### Migration Stops Unexpectedly

1. Check PHP max_execution_time setting
2. Increase WordPress memory limit if needed
3. Try reducing the batch size
4. Check server error logs for timeouts

### Image Optimization Issues

1. Ensure GD Library or ImageMagick is installed
2. Check file permissions on the uploads directory
3. Verify sufficient PHP memory for large images
4. Check the **Logs** page for specific errors

### Reconciliation Not Finding Files

1. Run **Sync S3 Stats** first to update the file list
2. Verify the environment setting matches where files are stored
3. Check that file paths match (wp-content/uploads structure)
4. Use **Scan S3** preview to see what will be matched

## Performance Recommendations

1. **Batch Size**: Start with smaller batches (10-25) for shared hosting
2. **Memory Limit**: Set to at least 256M for large operations
3. **S3 Stats Sync**: Daily sync is usually sufficient
4. **Cache-Control**: Use 1 year for immutable assets
5. **Image Optimization**: Process during low-traffic periods

## Changelog

### 2.13.15
- **Fix**: Improved relative storage path rewriting for YooTheme
  - Re-enabled output buffering (safe from double processing with CDN URL checks)
  - Pattern 3 now also checks if URL already contains CDN base URL
  - Better handling of YooTheme's relative URL format like `media/production/wp-content/uploads/...`

### 2.13.14
- **Improvement**: Made debug logging conditional on `WP_DEBUG`
  - `AbstractAIProvider::logDebug()` now only logs when WP_DEBUG is enabled
  - `Admin_AI_Metadata` refactored to use centralized `debug_log()` method
  - Reduces log noise in production environments

### 2.13.13
- **Refactor**: Cleaned up and optimized `filter_content_urls()` method
  - Reduced from 5 to 4 patterns with early-exit checks using `str_contains()`
  - Combined relative path patterns (with/without leading slash) into one
  - Removed redundant site_url pattern (covered by generic URL pattern)
  - Used arrow functions for cleaner callback syntax
  - Better code organization with numbered comments

### 2.13.12
- **Fix**: Rewrite ANY URL with `/wp-content/uploads/` to CDN
  - Added fifth pattern to catch URLs where domain doesn't match `site_url()`
  - Fixes staging/production URL mismatches (e.g., `staging.example.com` vs `example.com`)
  - Skips URLs already pointing to CDN

### 2.13.11
- **Feature**: Rewrite YooTheme image API URLs to direct CDN URLs
  - Added `rewrite_yootheme_image_urls()` method
  - Intercepts YooTheme's `/wp-json/yootheme/image?src={...}` URLs
  - Extracts file path from JSON-encoded src parameter
  - Converts to direct CDN URL: `cdn.com/media/{env}/wp-content/uploads/...`
  - Note: Bypasses YooTheme's dynamic resize but serves images from CDN

### 2.13.10
- **Fix**: YooTheme compatibility - skip URL rewriting when YooTheme handles images
  - Added `is_yootheme_image_request()` detection method
  - Skip processing in `filter_attachment_url`, `filter_image_src`, `filter_image_downsize`, `filter_image_srcset`
  - Skip content filtering when YooTheme image API URLs detected
  - Prevents interference with YooTheme's proprietary image handling system

### 2.13.9
- **Fix**: Rewrite site URLs with `/wp-content/uploads/` to CDN URLs
  - Added fourth pattern to convert `site_url/wp-content/uploads/...` → `cdn_url/storage_path/...`
  - Fixes `src` attribute not being rewritten when srcset works
  - Added fallback in `filter_attachment_url` for migrated attachments with incomplete metadata

### 2.13.8
- **Fix**: Improved regex patterns for content URL rewriting
  - Added comma to exclusion pattern `[^\s"\'<>,]+` to avoid capturing srcset width descriptors
  - Added `\s*` after prefix to handle whitespace after comma in srcset
  - Disabled output buffering (was causing double processing issues)

### 2.13.7
- **Fix**: Prevent double URL rewriting in `filter_image_srcset()`
  - Added check to skip URLs that already start with CDN base URL
  - Fixes corrupted srcset URLs like `...uploads https://...uploads/...`
  - Also improved thumb_keys lookup logic with explicit found flag

### 2.13.6
- **Fix**: URL rewriting now works with page builders (YooTheme, Elementor, etc.)
  - Added output buffering to catch content that bypasses standard WordPress filters
  - Page builders often render content through their own templating systems
  - Uses `template_redirect` hook with `ob_start` callback for final HTML filtering

### 2.13.5
- **Fix**: Content URL rewriting now correctly matches storage paths
  - Removed duplicate `/wp-content/uploads/` in regex patterns
  - `get_storage_base_path()` already includes `/wp-content/uploads` in its return value
  - Fixes images not being rewritten to CDN URLs when using relative paths

### 2.13.4
- **Fix**: Content URL rewriting no longer matches plain text containing storage paths
  - Changed regex patterns to only match URL contexts (quoted attributes, srcset commas, unquoted attributes)
  - Removed space matching which could incorrectly rewrite prose text containing paths
  - Example: "see directory media/production/..." is no longer rewritten

### 2.13.2
- **Fix**: PHP TypeError when WordPress passes `false` instead of expected types in filters
  - Fixed `filter_image_src()` to accept `array|false|null` (WordPress returns `false` when attachment doesn't exist)
  - Fixed `filter_image_srcset()` to accept `array|false` (plugins can return `false` to disable srcset)
  - Fixed `filter_content_urls()` to accept `string|null` (edge cases in content filters)
  - Fixed `filter_attached_file()` and `filter_update_attached_file()` to accept `string|false`
  - Resolves fatal errors with ACF Icon Picker and other plugins that trigger image functions on non-existent attachments

### 2.13.1
- **Fix**: PHP TypeError when WordPress passes attachment ID as string instead of int
  - Fixed `filter_image_downsize()`, `filter_attachment_url()`, `filter_image_src()`, `filter_image_srcset()`, and `filter_image_sizes()` to accept both `int|string` and cast to int internally
  - Resolves compatibility issues with plugins like ACF that may pass string IDs

### 2.13.3
- **Fix**: Content URL rewriting now handles `srcset` attribute for responsive images
  - Previously only `src` and `href` were rewritten
  - Now properly rewrites all URLs in srcset (e.g., "url 1x, url 2x")
- **Fix**: Content URL rewriting now fixes corrupted absolute URLs
  - Handles URLs like `https://site.com/page/media/production/wp-content/uploads/...`
  - These occur when relative storage paths are resolved against the page URL
  - Extracts storage path portion and rewrites with correct CDN base URL
- **Improved**: More robust URL pattern matching in post content

### 2.13.0
- **Fix**: Optimization now preserves original file when compression produces a larger result
  - Creates temporary backup before optimization
  - Automatically restores original if optimized file is larger
  - Marks as "skipped" with reason showing the would-be increase percentage
- **Fix**: Fixed "Saved false" display in optimization log when file size increased
  - Now shows "No change" for zero savings
  - Shows "Increased" with percentage for negative results (edge cases)
  - Handles missing/undefined values gracefully

### 2.12.0
- **New**: Content URL rewriting for CDN paths in HTML
  - Automatically rewrites relative storage paths (`media/production/wp-content/uploads/...`) to absolute CDN URLs in post content
  - Fixes 404 errors reported by SEMrush and other SEO tools for images with relative URLs
  - Applies to `the_content`, `the_excerpt`, and widget text content
- **New**: Rank Math sitemap integration
  - Filters `rank_math/sitemap/urlimages` to ensure correct CDN URLs in image sitemaps
  - Handles multiple URL patterns: relative paths, paths with leading slash, and legacy site URLs
  - Prevents SEO tools from reporting 404 errors for sitemap images

### 2.11.4
- **Fixed**: Statistics consistency across all pages (Dashboard, CloudSync, Batch Processor)
  - Centralized `OptimizationTable::get_full_stats()` method for optimization stats
  - `Stats::get_migration_stats()` is now the single source of truth for migration stats
  - Private `get_migration_counts()` helper for fast queries (skips expensive pending_size)
  - CloudSync and Reconciliation use Stats via dependency injection with optimized calls
  - All stats calculations now use unified methods - no more duplicate queries
  - Properly accounts for skipped images in pending optimization count
- **Improved**: Performance optimization for batch operations
  - `count_pending_items()` no longer calculates unnecessary pending_size on each batch
  - CloudSync analyze uses fast path without file size calculation
- **Improved**: CloudSync page optimization card simplified
  - Shows compact recommendation card only when optimization is below 100%
  - Removed redundant "optimize_before_sync" from Suggested Actions (now in dedicated card)
- **Improved**: Optimize Dashboard simplified
  - Consolidated 6 stat cards into 3: Optimized (%), Space Saved, Avg. Savings
  - Cleaner, more focused UI

### 2.11.2
- **Fixed**: SVG files incorrectly marked as "corrupted"
  - `getimagesize()` doesn't work for SVG (they're XML, not bitmap images)
  - Now validates SVG content by checking for `<svg` or `<?xml` markup
  - SVG files without svgo installed are now skipped with clear message instead of failing
- **Fixed**: Unsupported image types now skipped instead of failing
  - Previously marked as "Unsupported image type" error
  - Now properly skipped with clear reason in the log
- **Improved**: Better error messages for SVG optimization
  - Clear indication when svgo is not installed: "SVG optimization not available (install svgo)"

### 2.11.1
- **New**: Detailed per-image logging in optimization batch process
  - Shows individual stats for each image: original size → optimized size, savings %, thumbnails count
  - Direct "View image" link for each processed image in the log
  - For failed images: shows error message + "Check image" link to verify the file
  - New "1 image at a time" batch option for maximum visibility
  - Added batch size options: 1 and 5 images for granular control

### 2.11.0
- **New**: Failed/Corrupted Images section in Optimization page
  - Shows list of all images that failed during optimization
  - Displays error messages for each failed image (corrupted, missing, permission issues)
  - Direct "Edit" link to inspect individual images in Media Library
  - "Retry All Failed" button to reset failed images to pending status
  - Pagination support for large numbers of failed images
  - Helpful information about why images fail
- **Fixed**: "NaN undefined" display bug in Batch Saved indicator
  - Fixed issue when page was reloaded during active optimization
  - Improved formatBytes function to handle invalid values

### 2.10.0
- **New**: Optimization Status integration in CloudSync page
  - Shows optimization progress bar with percentage
  - Displays total images, optimized count, pending count, and space saved
  - Average savings percentage per image
- **New**: "Optimize before sync" suggested action
  - When there are pending files to sync AND unoptimized images, shows recommendation
  - Direct link to optimization page for quick access
  - Helps reduce upload bandwidth and cloud storage costs
- **New**: Warning banner when syncing unoptimized images
  - Visual alert in Optimization Status card
  - Explains benefits of optimizing before cloud sync
- **Improved**: CloudSync analysis now includes optimization statistics

### 2.9.0
- **New**: CloudSync - Unified tool for storage synchronization
  - Replaces and unifies Migration, Sync stats, and Reconciliation tools
  - Smart analysis: automatically detects what needs to be done
  - Deep scan mode: full cloud storage inventory with discrepancy detection
  - Integrity check: finds files marked as migrated but missing from cloud
  - Auto-fix: re-uploads files from local backup or cleans orphan metadata
  - New dedicated CloudSync page with intuitive UI
- **New**: Admin notice for sync issues
  - Shows global warning when integrity issues are detected
  - Links directly to CloudSync page for resolution
- **Improved**: AI Metadata Generator now verifies cloud URLs before processing
  - Performs HEAD request to check if cloud URL is accessible
  - Falls back to local file if cloud URL returns error (404, etc.)
  - Caches URL accessibility status to avoid repeated requests
  - Better error messages when files are truly unavailable
- **Improved**: Remove Local Files warning modal
  - Shows detailed warning about data loss risk
  - Requires explicit confirmation checkbox before enabling
- **Refactored**: Batch_Processor moved to Core namespace
  - Used by CloudSync, AI Metadata Generator, and Image Optimizer
  - Cleaner architecture with shared base class

### 2.8.3
- **Fixed**: Dashboard stats showing wrong values after storage sync
  - Changed hybrid logic to prioritize storage sync data over WordPress metadata
  - Previously, metadata count (migrated files) was shown even when storage sync returned 0
  - Now uses storage sync as source of truth when available, with WP metadata as fallback

### 2.8.2
- **Fixed**: PHP warnings for undefined `$ai_providers_info` variable on settings page
  - Variable was only defined in AI Providers tab but used globally in JavaScript

### 2.8.1
- **Fixed**: Dashboard stats showing old values after storage sync
  - Storage stats cache was not invalidating dashboard stats transient
  - Now `save_storage_stats()` automatically clears the dashboard cache

### 2.8.0
- **New**: Background Processing for AI Metadata Generation
  - New toggle option to enable background processing via WP Cron
  - Process continues even if you close the browser
  - Auto-reconnect: page automatically reconnects to active background processes
  - Proper stop/pause handling that cancels scheduled cron events
- **New**: Batch size option of 1 image for granular progress updates
- **Improved**: AI metadata generation progress tracking

### 2.7.7
- **Improved**: S3 download validation for optimization
  - Now verifies file content is not empty before and after download
  - Checks file_put_contents actually wrote bytes
  - Verifies local file size matches S3 content length
  - Better error logging to identify download vs corruption issues
  - Deletes failed/empty downloads instead of leaving corrupt files

### 2.7.6
- **Fixed**: Database error "CONSTRAINT settings_json failed" when marking failed optimizations
  - JSON columns don't accept empty strings, only NULL or valid JSON
  - Now properly handles NULL values for settings_json, optimized_at, and error_message columns

### 2.7.5
- **Fixed**: "File became unreadable after optimization" - files being corrupted during optimization
  - **Root cause**: ImageMagick and GD were writing directly to destination, corrupting files if optimization failed mid-write
  - **Solution**: Both optimizers now write to temp file first, then atomically rename to destination
  - This "write-to-temp-then-rename" pattern protects original files from corruption
  - If optimization fails, original file is preserved intact

### 2.7.4
- **Fixed**: "Duplicate entry for key 'attachment_id'" database errors
  - Changed upsert to use atomic `INSERT ... ON DUPLICATE KEY UPDATE` query
  - Prevents race conditions when multiple batches process the same file
- **Improved**: Better handling of missing files during optimization
  - Added explicit file existence check before reading file size
  - Added check after optimization to detect if optimizer deleted the file
  - Better error messages indicating if file was missing from S3 download or deleted by optimizer
- **Improved**: More detailed logging for optimization failures
  - Logs which optimizer was used when failures occur
  - Logs S3 key and download status for missing files

### 2.7.3
- **Improved**: Automatic retry for HTTP errors (502, 503, 504) during batch processing
  - Retries up to 2 times with exponential backoff (2s, 4s delays)
  - Shows retry progress in log: "HTTP 502 - retrying in 2s... (attempt 2/3)"
  - Added 60 second timeout for AJAX requests
- **Improved**: Better diagnostics for "File became unreadable after optimization" error
  - Now logs file exists status, readability, directory status, and optimizer used
  - Helps identify if issue is permissions, missing file, or optimizer problem

### 2.7.2
- **Fixed**: Stop/Cancel button not responding on first click during batch processing
  - Added check for stop request after AJAX response to handle in-flight requests
  - Disabled all action buttons during stop to prevent multiple clicks
  - Better feedback message: "Stopping... (waiting for current batch to complete)"

### 2.7.1
- **Fixed**: AWS S3 BadDigest (CRC32) checksum errors during file uploads
  - Disabled automatic checksum calculation in AWS SDK to prevent errors with concurrent file access
  - Added BadDigest as retryable error for automatic retry on checksum failures
- **Improved**: Better batch processor feedback in frontend logs
  - Show bytes saved per batch for optimization
  - Show skipped items count
  - Show retry queue count
  - Simplified error messages for common issues (checksum errors, timeouts)
- **Improved**: User-friendly error message for checksum failures

### 2.7.0
- **Improved**: Reorganized Image Optimization page with new "Settings" tab
  - Compression settings and format conversion options moved to dedicated tab
  - Settings now apply to both "Optimize on Upload" and batch optimization
  - Cleaner UI with separation between configuration and execution controls

### 2.6.0
- **New**: Multi-Driver Optimizer System with automatic tool detection
  - Supports CLI tools: mozjpeg, jpegoptim, pngquant, optipng, oxipng, cwebp, avifenc, gifsicle, svgo
  - Automatic fallback chain: uses best available tool for each format
  - Dashboard shows available tools with version and recommendations
- **New**: WebP Conversion - Generate .webp versions alongside original images
- **New**: AVIF Conversion - Generate .avif versions (20-50% smaller than WebP)
- **New**: Backup System - Keep original images with `_original` suffix before optimization
  - Backup stored in same directory as optimized file
  - Cloud storage integration (backup uploaded to S3/R2/etc.)
  - One-click restore from Media Library
- **New**: SVG Optimization support (requires svgo)
- **New**: AVIF image optimization support
- **Improved**: Optimization settings page shows available tools by format
- **Improved**: Installation instructions for missing optimization tools

### 2.5.4
- **Cleanup**: Removed all reads from legacy optimization post meta (`_media_toolkit_optimized`, `_media_toolkit_bytes_saved`, etc.)
- **Improved**: All optimization data now read exclusively from `OptimizationTable` (single source of truth)
- **Simplified**: `cloudStorage` response object no longer includes redundant optimization fields

### 2.5.3
- **Improved**: All optimization data now stored in `OptimizationTable` with complete breakdown in `settings_json`
- **Improved**: Table stores TOTAL savings (main + thumbnails) in main fields for accurate statistics
- **Improved**: Detailed breakdown available: main image (original/optimized/saved) + thumbnails (count/original/optimized/saved)
- **Removed**: Post meta for thumbnails optimization data (now all in optimization table)
- **Improved**: Logs now show complete breakdown: "Asset optimized: saved X (Y%) - Main: Z, Thumbnails (N): W"

### 2.5.2
- **New**: Show total savings (main image + thumbnails) in Media Library modal optimization section
- **New**: Save thumbnails optimization data (bytes saved, count) for each attachment
- **New**: Save main image optimization data during automatic "optimize on upload"
- **Improved**: Optimization modal now shows detailed breakdown: Total Savings, Main Image size reduction, Thumbnails count with bytes saved

### 2.5.1
- **Fix**: "Optimizer not available" shown after successful optimization in Media Library modal - The AJAX response was missing the `available` flag causing the UI to incorrectly show unavailable status after optimizing

### 2.5.0
- **New**: Image Optimization section in Media Library attachment modal
- **New**: View optimization status directly from attachment details (Optimized, Not optimized, Skipped, Failed)
- **New**: One-click "Optimize Now" button to optimize individual images from Media Library
- **New**: "Re-optimize" button to re-process already optimized images
- **New**: Display original vs optimized size with percentage saved in attachment modal
- **New**: Show number of thumbnails optimized for each image
- **New**: Error message display for failed optimizations
- **Improved**: Media Library UI now shows complete image optimization information alongside Cloud Storage and AI Metadata sections

### 2.4.0
- **New**: AI Metadata Generation - Automatically generate alt text, titles, captions, and descriptions using AI Vision
- **New**: Multi-provider support: OpenAI GPT-4o, Anthropic Claude, Google Gemini
- **New**: Provider priority system with automatic fallback
- **New**: AI Providers tab in Settings for configuration and testing
- **New**: AI Metadata page with statistics dashboard and batch processing
- **New**: Generate on Upload - Async AI metadata generation when new images are uploaded
- **New**: Cost estimation before batch processing starts
- **New**: Multi-language support for generated metadata
- **New**: One-click "Generate with AI" button in Media Library attachment modal
- **New**: Field completeness statistics (alt text, title, caption, description)
- **New**: Minimum image size filter to skip icons and placeholders
- **New**: Rate limiting and exponential backoff for API calls
- **New**: Image resizing before AI analysis to reduce API costs
- **New**: "AI Pending" indicator in Media Library for images being processed

### 2.3.0
- **New**: Optimize on Upload - Automatically compress images when uploaded
- **New**: Thumbnail optimization - All generated thumbnail sizes are also optimized on upload
- **New**: Upload processing flow:
  - Main file: Resize → Optimize → Cloud Upload
  - Thumbnails: Generate → Optimize → Cloud Upload
- **New**: Toggle to enable/disable automatic optimization on upload
- **Improved**: Both main images and thumbnails are optimized before cloud upload, maximizing storage and bandwidth savings

### 2.2.2
- **Fix**: CDN URL not applied to media URLs - URLs were using saved S3 direct URLs instead of computing them dynamically from current CDN settings
- **Improved**: URL generation now always uses current CDN/storage configuration, allowing CDN URL changes to take effect immediately without re-migration

### 2.2.1
- **Improved**: Cache Headers update now shows progress in the log every 100 files processed

### 2.2.0
- **Fix**: Added missing `update_objects_metadata_batch()` method for Cache Headers tool
- **Fix**: Corrected `generate_s3_key()` method call in Image_Editor (was `generate_key()`)
- **Improved**: Reconciliation now uses StorageInterface methods instead of direct client access
- **Improved**: Added `list_objects_with_metadata()` method to StorageInterface for better abstraction
- **Refactor**: Renamed all `_s3_` naming to `_storage_` for provider-agnostic consistency
  - Methods: `get_storage_base_path()`, `get_storage_sync_interval()`, `save_storage_stats()`, etc.
  - Database options: `media_toolkit_storage_stats`
  - Cron hooks: `media_toolkit_sync_storage_stats`
- **Removed**: Deprecated `sync_s3_stats()` method and legacy aliases

### 2.0.0
- **Major**: Multi-provider storage architecture
- **New**: Support for Amazon S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, and Wasabi
- **New**: Storage Provider selection in Settings with dynamic configuration fields
- **New**: Provider-specific regions and endpoint configuration
- **New**: Automatic backward compatibility for existing S3 configurations
- **Improved**: Renamed "Credentials" tab to "Storage Provider"
- **Improved**: Refactored codebase with StorageInterface abstraction for extensibility
- **Note**: Existing S3 configurations are automatically recognized as "Amazon S3"

### 1.3.1
- Bug fixes and improvements

### 1.3.0
- **New**: Optimization Status tab in Logs page
- **New**: View all optimization records with status, sizes, and savings
- **New**: Filter optimization records by status (optimized, pending, failed, skipped)
- **New**: Paginated optimization table with real-time stats
- **New**: Retry failed optimization records with one click
- **Improved**: Logs page now has tab navigation for better organization

### 1.2.2
- **Compatibility**: Tested with WordPress 6.9
- **Compatibility**: Verified PHP 8.4 support

### 1.2.0
- **New**: Automatic image resizing on upload
- **New**: Max width/height settings to limit uploaded image dimensions
- **New**: BMP to JPEG automatic conversion
- **New**: Optimize page reorganized with 3 tabs: Dashboard, Optimize, Resize
- **New**: Quick presets for common resize dimensions (Full HD, 2K, 4K, Web)
- **New**: Resize statistics tracking (images resized, space saved, BMP converted)
- **Feature**: Image resizing works independently of S3 configuration

### 1.1.0
- **New**: Import/Export tab in Settings page
- **New**: Export settings to JSON file for backup or transfer to another site
- **New**: Import settings from previously exported JSON file
- **New**: Auto-discovery of all `media_toolkit_*` options for future-proof exports
- **New**: Merge or replace option when importing settings
- **Security**: AWS credentials, GitHub tokens, and API keys are automatically excluded from exports
- **New**: Drag & drop file upload for importing
- **New**: Pre-import validation with file info preview

### 1.0.1
- Fixed: Migration tab scripts not loading after moving to Tools page
- Changed: Migration page merged into Tools page as tab

### 1.0.0
- Initial release as Media Toolkit (rebranded from Media S3 Offload)
- S3 upload and deletion
- Cloudflare and CloudFront CDN support
- Bulk migration tool
- Dashboard with statistics
- Logging and history
- Multi-environment support
- Image editing support
- Image optimization with batch processing
- Reconciliation tool for S3/WordPress sync
- Cache header bulk update tool
- Content-Disposition settings by file type

## License

GPL-3.0-or-later

## Credits

Developed by [Metodo](https://metodo.dev)

Uses:
- AWS SDK for PHP
- GuzzleHTTP
