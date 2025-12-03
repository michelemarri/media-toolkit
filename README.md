# Media Toolkit

A powerful WordPress plugin for complete media management. Offload media files to Amazon S3 with CDN support (Cloudflare, CloudFront), image optimization, and advanced management tools.

**Author:** Metodo  
**Plugin URI:** https://metodo.dev  
**Contact:** plugins@metodo.dev

## Features

### 🚀 Automatic Media Offloading
- Automatically uploads new media files to Amazon S3 when added to WordPress
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

### 📦 Bulk Migration
- Migrate existing media library to S3 in bulk
- Configurable batch size (10, 25, 50, 100 files per batch)
- Real-time progress tracking with detailed logs
- Pause/Resume capability for large migrations
- Automatic retry for failed uploads
- Option to delete local files after migration

### 🖼️ Image Optimization
Compress and optimize your media library images to save storage and bandwidth:

- **JPEG Compression**: Configurable quality (60-100%)
- **PNG Compression**: Lossless compression levels (0-9)
- **GIF Support**: Automatic handling (animated GIFs are preserved)
- **WebP Support**: Optimize existing WebP files
- **EXIF Stripping**: Remove camera metadata, GPS data, etc.
- **Batch Processing**: Optimize images in bulk with progress tracking
- **Thumbnail Optimization**: Automatically optimizes all generated sizes
- **S3 Re-upload**: Optimized images are automatically re-uploaded to S3
- **Space Savings Tracking**: Monitor how much storage you've saved
- **Server Capability Detection**: Checks for GD, ImageMagick, WebP support

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

### 📚 Media Library Integration
Enhanced Media Library with S3 status and actions:

- **S3 Status Column**: Visual badge showing "S3" (offloaded) or "Local" status
- **Optimization Badge**: Shows space saved for optimized images (-X KB)
- **Quick Actions**: Direct links to view file on CDN
- **Attachment Details**: Full S3 info in the attachment modal
  - S3 Key and CDN URL
  - Local file status
  - Space savings from optimization
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
