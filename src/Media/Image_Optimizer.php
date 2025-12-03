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
use Metodo\MediaToolkit\Database\OptimizationTable;

/**
 * Handles image optimization/compression for media files
 */
final class Image_Optimizer extends Batch_Processor
{
    private const SETTINGS_KEY = 'media_toolkit_optimize_settings';
    private const STATS_KEY = 'media_toolkit_optimization_stats';
    
    private ?S3_Client $s3_client;
    private History $history;
    
    /** @var array|null In-memory cache for stats within same request */
    private ?array $stats_cache = null;

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
        add_action('wp_ajax_media_toolkit_rebuild_optimization_stats', [$this, 'ajax_rebuild_stats']);
        
        // Hook into attachment deletion to update stats
        add_action('delete_attachment', [$this, 'on_attachment_deleted'], 10, 1);
        
        // Invalidate total images cache when new images are uploaded
        add_action('add_attachment', [$this, 'on_attachment_added'], 10, 1);
    }

    /**
     * Handle attachment deletion - update aggregated stats and remove from table
     */
    public function on_attachment_deleted(int $attachment_id): void
    {
        // Check if it's an image
        $mime_type = get_post_mime_type($attachment_id);
        if (strpos($mime_type, 'image/') !== 0) {
            return;
        }
        
        // Delete from optimization table (this handles stats update)
        OptimizationTable::delete_by_attachment($attachment_id);
        
        // Invalidate stats cache so it rebuilds from table
        $this->invalidate_stats_cache();
        
        // Decrement total images count
        $this->decrement_total_images();
    }

    /**
     * Handle new attachment - increment total images
     */
    public function on_attachment_added(int $attachment_id): void
    {
        $mime_type = get_post_mime_type($attachment_id);
        if (strpos($mime_type, 'image/') === 0) {
            $this->increment_total_images();
        }
    }

    /**
     * AJAX: Rebuild optimization stats from database
     */
    public function ajax_rebuild_stats(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $stats = $this->rebuild_aggregated_stats();
        
        wp_send_json_success([
            'message' => 'Stats rebuilt successfully',
            'stats' => $this->get_stats(),
        ]);
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
     * 
     * Ultra-performant: single option read + in-memory cache.
     * No database queries on subsequent calls within same request.
     */
    public function get_stats(): array
    {
        $stats = $this->get_aggregated_stats();
        
        $total_images = $stats['total_images'];
        $optimized_images = $stats['optimized_count'];
        $total_saved = $stats['total_bytes_saved'];
        $total_original_size = $stats['total_original_size'];
        
        $pending_images = max(0, $total_images - $optimized_images);
        $progress = $total_images > 0 ? round(($optimized_images / $total_images) * 100, 1) : 0;
        
        // Calculate average savings percentage
        $average_savings_percent = $total_original_size > 0 
            ? round(($total_saved / $total_original_size) * 100, 1) 
            : 0;

        return [
            'total_images' => $total_images,
            'optimized_images' => $optimized_images,
            'pending_images' => $pending_images,
            'total_saved' => $total_saved,
            'total_saved_formatted' => size_format($total_saved),
            'total_original_size' => $total_original_size,
            'total_original_size_formatted' => size_format($total_original_size),
            'average_savings_percent' => $average_savings_percent,
            'progress_percentage' => $progress,
        ];
    }

    /**
     * Get aggregated stats from option with multi-layer caching
     * 
     * Cache hierarchy:
     * 1. In-memory cache (same PHP request) - instant
     * 2. Object cache (Redis/Memcached if available) - ~1ms
     * 3. wp_options table - ~5ms
     * 
     * Auto-initializes from database if stats don't exist yet (migration).
     */
    private function get_aggregated_stats(): array
    {
        // Level 1: In-memory cache (same request)
        if ($this->stats_cache !== null) {
            return $this->stats_cache;
        }

        $defaults = $this->get_stats_defaults();
        
        // Level 2: Object cache (Redis/Memcached) - faster than options if available
        $cache_key = 'media_toolkit_opt_stats';
        $stats = wp_cache_get($cache_key, 'media_toolkit');
        
        if ($stats !== false && is_array($stats)) {
            $this->stats_cache = array_merge($defaults, $stats);
            return $this->stats_cache;
        }
        
        // Level 3: Database option
        $stats = get_option(self::STATS_KEY);
        
        // Auto-initialize if option doesn't exist (first run or migration)
        if ($stats === false) {
            $stats = $this->initialize_stats();
        }
        
        $stats = array_merge($defaults, is_array($stats) ? $stats : []);
        
        // Populate caches
        wp_cache_set($cache_key, $stats, 'media_toolkit', 300); // 5 min object cache
        $this->stats_cache = $stats;
        
        return $stats;
    }

    /**
     * Get default stats structure
     */
    private function get_stats_defaults(): array
    {
        return [
            'total_images' => 0,
            'optimized_count' => 0,
            'total_bytes_saved' => 0,
            'total_original_size' => 0,
            'last_updated' => 0,
            'version' => 2, // Stats schema version for future migrations
        ];
    }

    /**
     * Initialize stats - either fresh or from custom table
     */
    private function initialize_stats(): array
    {
        // Check if optimization table has data
        if (OptimizationTable::table_exists()) {
            $table_stats = OptimizationTable::get_aggregate_stats();
            
            if ($table_stats['total_records'] > 0) {
                // Rebuild from custom table
                return $this->rebuild_aggregated_stats();
            }
        }
        
        // Fresh install - count current images and save
        $total_images = $this->count_total_images_from_db();
        
        $stats = $this->get_stats_defaults();
        $stats['total_images'] = $total_images;
        $stats['last_updated'] = time();
        
        $this->save_stats($stats);
        
        return $stats;
    }

    /**
     * Save stats with cache invalidation
     */
    private function save_stats(array $stats): void
    {
        $stats['last_updated'] = time();
        
        // Save to database (autoload = false for performance)
        update_option(self::STATS_KEY, $stats, false);
        
        // Update object cache
        wp_cache_set('media_toolkit_opt_stats', $stats, 'media_toolkit', 300);
        
        // Update in-memory cache
        $this->stats_cache = $stats;
    }

    /**
     * Invalidate all stats caches
     */
    private function invalidate_stats_cache(): void
    {
        $this->stats_cache = null;
        wp_cache_delete('media_toolkit_opt_stats', 'media_toolkit');
    }

    /**
     * Update aggregated stats incrementally after optimization
     * 
     * @param int $bytes_saved Bytes saved in this optimization
     * @param int $original_size Original file size
     * @param bool $is_new Whether this is a new optimization (not re-optimization)
     */
    private function update_aggregated_stats(int $bytes_saved, int $original_size, bool $is_new = true): void
    {
        $stats = $this->get_aggregated_stats();
        
        if ($is_new) {
            $stats['optimized_count']++;
        }
        
        $stats['total_bytes_saved'] += $bytes_saved;
        $stats['total_original_size'] += $original_size;
        
        $this->save_stats($stats);
    }

    /**
     * Decrement stats when an image is deleted or re-optimized
     * 
     * @param int $attachment_id The attachment being removed/changed
     * @deprecated Use OptimizationTable::delete_by_attachment() instead
     */
    public function decrement_stats_for_attachment(int $attachment_id): void
    {
        // Get data from custom table
        $record = OptimizationTable::get_by_attachment($attachment_id);
        
        if (!$record || $record['status'] !== 'optimized') {
            return;
        }
        
        // Stats are now calculated from the table directly, so just invalidate cache
        $this->invalidate_stats_cache();
    }

    /**
     * Increment total images count (call when new image uploaded)
     */
    public function increment_total_images(): void
    {
        $stats = $this->get_aggregated_stats();
        $stats['total_images']++;
        $this->save_stats($stats);
    }

    /**
     * Decrement total images count (call when image deleted)
     */
    public function decrement_total_images(): void
    {
        $stats = $this->get_aggregated_stats();
        $stats['total_images'] = max(0, $stats['total_images'] - 1);
        $this->save_stats($stats);
    }

    /**
     * Count total images from database (expensive - use sparingly)
     */
    private function count_total_images_from_db(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'"
        );
    }

    /**
     * Rebuild aggregated stats from custom table
     * 
     * Use this if stats get out of sync (e.g., after manual DB edits).
     * Now uses the efficient custom optimization table.
     */
    public function rebuild_aggregated_stats(): array
    {
        // Get total images from posts table
        $total_images = $this->count_total_images_from_db();

        // Get optimization stats from custom table (single efficient query)
        $table_stats = OptimizationTable::get_aggregate_stats();

        $stats = [
            'total_images' => $total_images,
            'optimized_count' => $table_stats['optimized_count'],
            'total_bytes_saved' => $table_stats['total_bytes_saved'],
            'total_original_size' => $table_stats['total_original_size'],
            'last_updated' => time(),
            'version' => 3, // Bumped version for custom table
        ];

        $this->save_stats($stats);
        
        $this->logger->info('optimization', 'Aggregated stats rebuilt from optimization table');

        return $stats;
    }

    /**
     * Process a batch of items with bytes saved tracking
     * 
     * @override
     */
    public function process_batch(): array
    {
        $state = $this->get_state();

        if ($state['status'] !== 'running') {
            return [
                'success' => false,
                'message' => 'Processor is not running',
                'state' => $state,
                'batch_bytes_saved' => 0,
            ];
        }

        $batch_size = $state['options']['batch_size'] ?? 25;
        $items = $this->get_pending_items($batch_size, $state['last_item_id'], $state['options']);

        if (empty($items)) {
            // Processing complete
            $state['status'] = 'completed';
            $this->save_state($state);
            
            $this->logger->success(
                $this->processor_id,
                "{$this->get_processor_name()} completed. Processed: {$state['processed']}, Failed: {$state['failed']}"
            );

            return [
                'success' => true,
                'complete' => true,
                'state' => $state,
                'batch_bytes_saved' => 0,
            ];
        }

        $batch_processed = 0;
        $batch_failed = 0;
        $batch_skipped = 0;
        $batch_errors = [];
        $batch_bytes_saved = 0;

        foreach ($items as $item) {
            $item_id = $this->get_item_id($item);
            $result = $this->process_item($item, $state['options']);

            if ($result['success']) {
                if ($result['skipped'] ?? false) {
                    $batch_skipped++;
                    $state['skipped']++;
                } else {
                    $batch_processed++;
                    $state['processed']++;
                    
                    // Track bytes saved
                    if (!empty($result['bytes_saved'])) {
                        $batch_bytes_saved += (int) $result['bytes_saved'];
                    }
                }
            } else {
                $batch_failed++;
                $state['failed']++;
                $batch_errors[] = [
                    'item_id' => $item_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }

            $state['last_item_id'] = $item_id;
        }

        $state['current_batch']++;
        $state['errors'] = array_merge($state['errors'], $batch_errors);
        
        // Keep only last 50 errors
        if (count($state['errors']) > 50) {
            $state['errors'] = array_slice($state['errors'], -50);
        }

        $this->save_state($state);

        return [
            'success' => true,
            'complete' => false,
            'batch_processed' => $batch_processed,
            'batch_failed' => $batch_failed,
            'batch_skipped' => $batch_skipped,
            'batch_errors' => $batch_errors,
            'batch_bytes_saved' => $batch_bytes_saved,
            'batch_bytes_saved_formatted' => size_format($batch_bytes_saved),
            'state' => $state,
        ];
    }

    /**
     * Count pending items to optimize
     * 
     * Uses custom table for efficient counting.
     */
    protected function count_pending_items(array $options = []): int
    {
        // Count images not in table + images with pending status
        $untracked_count = count(OptimizationTable::get_untracked_attachment_ids(10000, 0));
        $pending_count = OptimizationTable::count_by_status('pending');
        
        return $untracked_count + $pending_count;
    }

    /**
     * Get pending items for optimization
     * 
     * Uses the custom optimization table for efficient queries.
     * First checks for images not yet tracked, then for pending status.
     */
    protected function get_pending_items(int $limit, int $after_id, array $options = []): array
    {
        // First: Get images not yet in the optimization table
        $untracked = OptimizationTable::get_untracked_attachment_ids($limit, $after_id);
        
        if (!empty($untracked)) {
            return $untracked;
        }
        
        // Then: Get images with 'pending' status in the table
        return OptimizationTable::get_pending_attachment_ids($limit, $after_id);
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
        
        try {
            return $this->optimize_attachment($attachment_id, $options);
        } catch (\Throwable $e) {
            // Catch any fatal errors from image processing libraries
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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

        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        $is_s3_active = $this->s3_client !== null;
        $is_on_s3 = !empty($s3_key);
        
        // Determine source of truth based on S3 configuration
        if ($is_s3_active && $is_on_s3) {
            // S3 is configured AND file is on S3 -> S3 is source of truth
            // Always download fresh from S3, ignore local copy
            $dir = dirname($file);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Remove any existing local copy to ensure we get fresh from S3
            if (file_exists($file)) {
                @unlink($file);
            }
            
            $downloaded = $this->s3_client->download_file($s3_key, $file, $attachment_id);
            if (!$downloaded) {
                return [
                    'success' => false,
                    'error' => 'Failed to download file from S3',
                ];
            }
        } else {
            // S3 not configured OR file not on S3 -> Local is source of truth
            if (!file_exists($file)) {
                return [
                    'success' => false,
                    'error' => 'File does not exist locally',
                ];
            }
        }

        $settings = array_merge($this->get_optimization_settings(), $options);
        $mime_type = get_post_mime_type($attachment_id);
        $original_size = filesize($file);

        // Validate image file is readable
        $image_info = @getimagesize($file);
        if ($image_info === false) {
            OptimizationTable::mark_failed($attachment_id, 'Failed to read file - image may be corrupted');
            $this->invalidate_stats_cache();
            
            return [
                'success' => false,
                'error' => 'Failed to read the file - image may be corrupted',
            ];
        }

        // Check max file size
        $max_size = ($settings['max_file_size_mb'] ?? 10) * 1024 * 1024;
        if ($original_size > $max_size) {
            // Mark as skipped in custom table
            OptimizationTable::mark_skipped($attachment_id, 'File too large: ' . size_format($original_size));
            $this->invalidate_stats_cache();
            
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

        // Save to custom optimization table with settings used
        OptimizationTable::mark_optimized(
            $attachment_id,
            $original_size,
            $new_size,
            [
                'jpeg_quality' => $settings['jpeg_quality'] ?? null,
                'png_compression' => $settings['png_compression'] ?? null,
                'strip_metadata' => $settings['strip_metadata'] ?? null,
                'max_file_size_mb' => $settings['max_file_size_mb'] ?? null,
                'min_savings_percent' => $settings['min_savings_percent'] ?? null,
            ]
        );

        // Invalidate stats cache so it rebuilds from table
        $this->invalidate_stats_cache();

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
        
        $is_s3_active = $this->s3_client !== null;
        $thumb_keys = get_post_meta($attachment_id, '_media_toolkit_thumb_keys', true) ?: [];

        foreach ($metadata['sizes'] as $size_name => $size_data) {
            $thumb_file = $file_dir . '/' . $size_data['file'];
            $thumb_s3_key = $thumb_keys[$size_name] ?? null;
            
            // Determine source of truth for this thumbnail
            if ($is_s3_active && !empty($thumb_s3_key)) {
                // S3 is source of truth - download fresh
                if (!file_exists($file_dir)) {
                    wp_mkdir_p($file_dir);
                }
                
                // Remove existing local copy
                if (file_exists($thumb_file)) {
                    @unlink($thumb_file);
                }
                
                $downloaded = $this->s3_client->download_file($thumb_s3_key, $thumb_file, $attachment_id);
                if (!$downloaded) {
                    continue; // Skip this thumbnail if download fails
                }
            } elseif (!file_exists($thumb_file)) {
                continue; // No local file and not on S3
            }

            $mime_type = $size_data['mime-type'] ?? '';
            
            try {
                $result = match ($mime_type) {
                    'image/jpeg', 'image/jpg' => $this->optimize_jpeg($thumb_file, $settings),
                    'image/png' => $this->optimize_png($thumb_file, $settings),
                    'image/webp' => $this->optimize_webp($thumb_file, $settings),
                    default => ['success' => false],
                };
            } catch (\Throwable $e) {
                continue; // Skip this thumbnail on error
            }

            // Re-upload to S3 if needed
            if ($result['success'] && $is_s3_active && !empty($thumb_s3_key)) {
                $this->s3_client->upload_file($thumb_file, $attachment_id);
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

