<?php
/**
 * Reconciliation class for syncing storage state with WordPress metadata
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Migration;

use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\Core\Batch_Processor;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Stats\Stats;

/**
 * Handles reconciliation between storage bucket and WordPress metadata
 * 
 * This tool scans storage and matches files with WordPress attachments,
 * then updates the `_media_toolkit_migrated` metadata accordingly.
 */
final class Reconciliation extends Batch_Processor
{
    private StorageInterface $storage;
    private History $history;
    private Stats $stats;
    
    /** @var array Cached storage file list during reconciliation */
    private array $storage_files_cache = [];
    
    /** @var string Transient key for storage files cache */
    private const STORAGE_FILES_CACHE_KEY = 'media_toolkit_reconciliation_storage_files';

    public function __construct(
        StorageInterface $storage,
        Settings $settings,
        Logger $logger,
        History $history,
        Stats $stats
    ) {
        parent::__construct($logger, $settings, 'reconciliation');
        
        $this->storage = $storage;
        $this->history = $history;
        $this->stats = $stats;
        
        // Register additional AJAX handlers
        add_action('wp_ajax_media_toolkit_reconciliation_scan_storage', [$this, 'ajax_scan_storage']);
        add_action('wp_ajax_media_toolkit_reconciliation_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_media_toolkit_clear_metadata', [$this, 'ajax_clear_metadata']);
        add_action('wp_ajax_media_toolkit_get_discrepancies', [$this, 'ajax_get_discrepancies']);
    }

    /**
     * Get processor display name (required by parent)
     */
    protected function get_processor_name(): string
    {
        return 'Reconciliation';
    }

    /**
     * Get reconciliation statistics
     * 
     * Uses existing Stats::get_migration_stats() for consistency.
     */
    public function get_stats(): array
    {
        // Use existing migration stats method
        $migration = $this->stats->get_migration_stats();

        // Get storage stats for discrepancy check
        $storageStats = $this->settings->get_cached_storage_stats();
        $storageOriginalFiles = $storageStats['original_files'] ?? $storageStats['files'] ?? 0;
        $storageTotalFiles = $storageStats['files'] ?? 0;

        // Discrepancy between WordPress metadata and actual storage
        $discrepancy = abs($storageOriginalFiles - $migration['migrated_attachments']);
        $has_discrepancy = $discrepancy > 0 && $storageOriginalFiles > 0;

        return [
            'total_attachments' => $migration['total_attachments'],
            'marked_migrated' => $migration['migrated_attachments'],
            'not_marked' => $migration['pending_attachments'],
            'storage_original_files' => $storageOriginalFiles,
            'storage_total_files' => $storageTotalFiles,
            'discrepancy' => $discrepancy,
            'has_discrepancy' => $has_discrepancy,
            'progress_percentage' => $migration['progress_percentage'],
        ];
    }

    /**
     * Scan storage and build a map of files
     * Returns array of storage keys indexed by relative path
     */
    public function scan_storage_files(): array
    {
        $this->logger->info('reconciliation', 'scan_storage_files() started');

        $base_path = $this->settings->get_storage_base_path();
        $this->logger->info('reconciliation', 'Scanning storage with base_path: ' . $base_path);

        $files = [];
        $continuation_token = null;

        // Thumbnail pattern to identify non-original files
        $thumbnail_pattern = '/-\d+x\d+(-[a-z0-9]+)?\.[a-zA-Z0-9]+$/';

        try {
            do {
                $result = $this->storage->list_objects_with_metadata(1000, $continuation_token);

                if ($result === null) {
                    $this->logger->error('reconciliation', 'Failed to list storage objects');
                    return [];
                }

                foreach ($result['objects'] as $object) {
                    $key = $object['key'];
                    $size = $object['size'];

                    // Skip thumbnails - only track originals
                    if (preg_match($thumbnail_pattern, $key)) {
                        continue;
                    }

                    // Extract relative path from storage key
                    // Key format: media/{env}/wp-content/uploads/2024/01/image.jpg
                    // We want: 2024/01/image.jpg
                    $relative_path = $this->extract_relative_path($key, $base_path);

                    if ($relative_path) {
                        $files[$relative_path] = [
                            'storage_key' => $key,
                            'size' => $size,
                        ];
                    }
                }

                $continuation_token = $result['next_token'];

            } while ($result['is_truncated'] && $continuation_token !== null);

            $this->logger->info('reconciliation', 'Storage scan completed: ' . count($files) . ' files found');

        } catch (\Throwable $e) {
            $this->logger->error('reconciliation', 'Exception in scan_storage_files(): ' . $e->getMessage());
            $this->logger->error('reconciliation', 'Stack trace: ' . $e->getTraceAsString());
            return [];
        }

        return $files;
    }

    /**
     * Extract relative upload path from storage key
     */
    private function extract_relative_path(string $storage_key, string $base_path): ?string
    {
        // Remove base path prefix
        $base_path = rtrim($base_path, '/') . '/';

        if (strpos($storage_key, $base_path) === 0) {
            return substr($storage_key, strlen($base_path));
        }

        // Try to extract from wp-content/uploads
        if (preg_match('#wp-content/uploads/(.+)$#', $storage_key, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get pending items (WordPress attachments not marked as migrated)
     * 
     * Uses existing Stats::get_migration_stats() for consistency.
     */
    protected function count_pending_items(array $options = []): int
    {
        // Use existing migration stats method
        $migration = $this->stats->get_migration_stats();

        // If we're in "mark all found" mode, count all attachments
        if ($options['mode'] ?? '' === 'mark_found') {
            return $migration['total_attachments'];
        }

        // Default: count not marked
        return $migration['pending_attachments'];
    }

    /**
     * Get pending items for reconciliation
     */
    protected function get_pending_items(int $limit, int $after_id, array $options = []): array
    {
        global $wpdb;

        // If we're in "mark all found" mode, get all attachments
        if ($options['mode'] ?? '' === 'mark_found') {
            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'attachment'
                     AND ID > %d
                     ORDER BY ID ASC
                     LIMIT %d",
                    $after_id,
                    $limit
                )
            );
        }

        // Default: get not marked
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                 WHERE p.post_type = 'attachment'
                 AND (pm.meta_value IS NULL OR pm.meta_value != %s)
                 AND p.ID > %d
                 ORDER BY p.ID ASC
                 LIMIT %d",
                '_media_toolkit_migrated',
                '1',
                $after_id,
                $limit
            )
        );
    }

    /**
     * Get item ID from item
     */
    protected function get_item_id($item): int
    {
        return (int) $item;
    }

    /**
     * Process a single item (check if attachment exists on S3)
     */
    protected function process_item($item, array $options = []): array
    {
        $attachment_id = (int) $item;
        
        // Get the attachment's file path
        $file_path = get_post_meta($attachment_id, '_wp_attached_file', true);
        
        if (empty($file_path)) {
            return [
                'success' => false,
                'error' => 'No file path found for attachment',
            ];
        }

        // Get storage files from internal cache (populated in process_batch)
        $storage_files = $this->storage_files_cache;

        // Check if file exists in storage
        $found_in_storage = isset($storage_files[$file_path]);

        if ($found_in_storage) {
            $storage_data = $storage_files[$file_path];

            // Update metadata
            update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
            update_post_meta($attachment_id, '_media_toolkit_key', $storage_data['storage_key']);

            // Generate URL
            $url = $this->settings->get_file_url($storage_data['storage_key']);
            update_post_meta($attachment_id, '_media_toolkit_url', $url);

            // Record in history (cast size to int)
            $this->history->record(
                HistoryAction::MIGRATED,
                $attachment_id,
                $file_path,
                $storage_data['storage_key'],
                (int) $storage_data['size'],
                ['source' => 'reconciliation']
            );

            return [
                'success' => true,
                'found' => true,
                'storage_key' => $storage_data['storage_key'],
            ];
        } else {
            // File not found in storage
            // In "mark_found" mode, we mark as NOT migrated
            if ($options['mode'] ?? '' === 'mark_found') {
                delete_post_meta($attachment_id, '_media_toolkit_migrated');
                delete_post_meta($attachment_id, '_media_toolkit_key');
                delete_post_meta($attachment_id, '_media_toolkit_url');
            }
            
            return [
                'success' => true,
                'found' => false,
                'skipped' => true,
            ];
        }
    }

    /**
     * Start reconciliation with S3 file scan
     */
    public function start(array $options = []): array
    {
        try {
            // First, scan storage for all files
            $this->logger->info('reconciliation', 'Scanning storage bucket for files...');

            $storage_files = $this->scan_storage_files();
            $storage_count = count($storage_files);

            $this->logger->info('reconciliation', "Found {$storage_count} original files on storage");

            // Store storage files in a separate transient (not in state, too large)
            $transient_saved = set_transient(self::STORAGE_FILES_CACHE_KEY, $storage_files, HOUR_IN_SECONDS);

            if (!$transient_saved) {
                $this->logger->error('reconciliation', 'Failed to save storage files transient - data might be too large');
            }

            // Only store count in options (lightweight)
            $options['storage_count'] = $storage_count;

            // Call parent start
            return parent::start($options);

        } catch (\Throwable $e) {
            $this->logger->error('reconciliation', 'Exception in start(): ' . $e->getMessage());
            $this->logger->error('reconciliation', 'Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Override process_batch to load storage_files from separate cache
     */
    public function process_batch(): array
    {
        try {
            $this->logger->info('reconciliation', 'process_batch() called');

            // Get storage files from separate transient cache (not from state)
            $storage_files = get_transient(self::STORAGE_FILES_CACHE_KEY);

            if ($storage_files === false) {
                // Need to re-scan if cache expired (might happen on resume)
                $this->logger->info('reconciliation', 'Storage cache expired or not found, re-scanning...');
                $storage_files = $this->scan_storage_files();

                if (empty($storage_files)) {
                    $this->logger->error('reconciliation', 'Storage scan returned empty array');
                }

                set_transient(self::STORAGE_FILES_CACHE_KEY, $storage_files, HOUR_IN_SECONDS);
            }

            $this->logger->info('reconciliation', 'Storage files loaded: ' . count($storage_files) . ' files');

            // Store in memory for process_item() to use
            $this->storage_files_cache = $storage_files;

            return parent::process_batch();
            
        } catch (\Throwable $e) {
            $this->logger->error('reconciliation', 'Exception in process_batch(): ' . $e->getMessage());
            $this->logger->error('reconciliation', 'Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Override stop to clean up S3 files cache
     */
    public function stop(): void
    {
        // Clean up S3 files cache
        delete_transient(self::STORAGE_FILES_CACHE_KEY);
        $this->storage_files_cache = [];
        
        parent::stop();
    }

    /**
     * Get start options from AJAX request
     */
    protected function get_start_options_from_request(): array
    {
        return [
            'batch_size' => isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 50,
            'mode' => isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'mark_found',
        ];
    }

    /**
     * AJAX: Scan storage and return preview
     */
    public function ajax_scan_storage(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->logger->info('reconciliation', 'Starting storage scan for preview...');

        $storage_files = $this->scan_storage_files();
        $storage_count = count($storage_files);

        // Use existing migration stats method
        $migration = $this->stats->get_migration_stats();
        $total_attachments = $migration['total_attachments'];
        $currently_marked = $migration['migrated_attachments'];

        // Get all attachment file paths for detailed matching
        global $wpdb;
        $attachment_files = $wpdb->get_results(
            "SELECT p.ID, pm.meta_value as file_path 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'",
            OBJECT_K
        );

        $matches = 0;
        $not_found = 0;
        $matched_ids = [];

        foreach ($attachment_files as $id => $data) {
            if (isset($storage_files[$data->file_path])) {
                $matches++;
                $matched_ids[] = $id;
            } else {
                $not_found++;
            }
        }

        // How many would be newly marked
        $would_be_marked = count(array_diff($matched_ids, $this->get_already_marked_ids()));

        // Calculate total size from scanned files
        $total_size = 0;
        foreach ($storage_files as $file_data) {
            $total_size += $file_data['size'] ?? 0;
        }

        // Update cached storage stats so the dashboard cards show correct values
        // Note: save_storage_stats() automatically invalidates dashboard stats cache
        $this->settings->save_storage_stats([
            'files' => $storage_count,           // Total files (originals only, thumbnails excluded)
            'original_files' => $storage_count,  // Same as files (we only count originals)
            'size' => $total_size,
            'original_size' => $total_size,
            'synced_at' => current_time('mysql'),
        ]);

        $this->logger->info('reconciliation', "Storage stats cache updated: {$storage_count} files, " . size_format($total_size));

        wp_send_json_success([
            'storage_original_files' => $storage_count,
            'wp_attachments' => $total_attachments,
            'matches' => $matches,
            'not_found_on_storage' => $not_found,
            'currently_marked' => $currently_marked,
            'would_be_marked' => $would_be_marked,
            'match_percentage' => $total_attachments > 0
                ? round(($matches / $total_attachments) * 100, 1)
                : 0,
        ]);
    }

    /**
     * Get IDs already marked as migrated
     */
    private function get_already_marked_ids(): array
    {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );
    }

    /**
     * AJAX: Get preview of reconciliation
     */
    public function ajax_preview(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Just return current stats
        wp_send_json_success([
            'stats' => $this->get_stats(),
        ]);
    }

    /**
     * Quick reconciliation for a single attachment
     */
    public function reconcile_single(int $attachment_id): array
    {
        $file_path = get_post_meta($attachment_id, '_wp_attached_file', true);

        if (empty($file_path)) {
            return [
                'success' => false,
                'error' => 'No file path found',
            ];
        }

        // Check if file exists in storage
        $storage_key = $this->settings->get_storage_base_path() . '/' . $file_path;
        $exists = $this->storage->file_exists($storage_key);

        if ($exists) {
            update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
            update_post_meta($attachment_id, '_media_toolkit_key', $storage_key);

            $url = $this->settings->get_file_url($storage_key);
            update_post_meta($attachment_id, '_media_toolkit_url', $url);

            return [
                'success' => true,
                'found' => true,
                'storage_key' => $storage_key,
            ];
        }

        return [
            'success' => true,
            'found' => false,
        ];
    }

    /**
     * Clear all migration metadata (reset)
     */
    public function clear_all_metadata(): int
    {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)",
                '_media_toolkit_migrated',
                '_media_toolkit_key',
                '_media_toolkit_url',
                '_media_toolkit_thumb_keys'
            )
        );

        $this->logger->info('reconciliation', "Cleared migration metadata from {$deleted} records");

        // Clear stats cache
        delete_transient('media_toolkit_stats_cache');

        return $deleted;
    }

    /**
     * AJAX: Clear all migration metadata
     */
    public function ajax_clear_metadata(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $deleted = $this->clear_all_metadata();

        wp_send_json_success([
            'message' => "Cleared migration metadata from {$deleted} records",
            'deleted' => $deleted,
        ]);
    }

    /**
     * AJAX: Get discrepancies details
     */
    public function ajax_get_discrepancies(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        try {
            global $wpdb;

            // Scan storage files
            $storage_files = $this->scan_storage_files();

            // Get all attachments with their file paths
            $attachments = $wpdb->get_results(
                "SELECT p.ID, p.post_title, pm.meta_value as file_path, pm2.meta_value as is_migrated
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                 LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_media_toolkit_migrated'
                 WHERE p.post_type = 'attachment'",
                OBJECT
            );

            $not_in_storage = [];
            $not_marked = [];
            $storage_files_matched = [];

            foreach ($attachments as $attachment) {
                $is_migrated = $attachment->is_migrated === '1';
                $in_storage = isset($storage_files[$attachment->file_path]);

                if ($in_storage) {
                    $storage_files_matched[$attachment->file_path] = true;
                }

                // Marked as migrated but NOT in storage
                if ($is_migrated && !$in_storage) {
                    $not_in_storage[] = [
                        'id' => (int) $attachment->ID,
                        'title' => $attachment->post_title,
                        'file' => $attachment->file_path,
                        'edit_url' => get_edit_post_link($attachment->ID, 'raw'),
                    ];
                }

                // In storage but NOT marked as migrated
                if (!$is_migrated && $in_storage) {
                    $not_marked[] = [
                        'id' => (int) $attachment->ID,
                        'title' => $attachment->post_title,
                        'file' => $attachment->file_path,
                        'storage_key' => $storage_files[$attachment->file_path]['storage_key'] ?? '',
                        'edit_url' => get_edit_post_link($attachment->ID, 'raw'),
                    ];
                }
            }

            // Find orphan files in storage (no corresponding WP attachment)
            $orphans = [];
            foreach ($storage_files as $file_path => $storage_data) {
                if (!isset($storage_files_matched[$file_path])) {
                    $orphans[] = [
                        'file' => $file_path,
                        'storage_key' => $storage_data['storage_key'],
                        'size' => size_format((int) $storage_data['size']),
                        'url' => $this->settings->get_file_url($storage_data['storage_key']),
                    ];
                }
            }

            // Count totals before slicing
            $not_in_storage_total = count($not_in_storage);
            $not_marked_total = count($not_marked);
            $orphans_total = count($orphans);

            // Limit to first 50 for performance
            $not_in_storage = array_slice($not_in_storage, 0, 50);
            $not_marked = array_slice($not_marked, 0, 50);
            $orphans = array_slice($orphans, 0, 50);

            // Get cached stats for comparison
            $cached_stats = $this->settings->get_cached_storage_stats();
            $cached_storage_files = $cached_stats['original_files'] ?? $cached_stats['files'] ?? 0;

            // Count marked as migrated
            $marked_migrated = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                     WHERE pm.meta_key = %s AND pm.meta_value = %s",
                    '_media_toolkit_migrated',
                    '1'
                )
            );

            wp_send_json_success([
                'not_on_storage' => $not_in_storage,
                'not_on_storage_count' => count($not_in_storage),
                'not_on_storage_total' => $not_in_storage_total,
                'not_marked' => $not_marked,
                'not_marked_count' => count($not_marked),
                'not_marked_total' => $not_marked_total,
                'orphans' => $orphans,
                'orphans_count' => count($orphans),
                'orphans_total' => $orphans_total,
                'summary' => [
                    'storage_files_scanned' => count($storage_files),
                    'storage_files_cached' => $cached_storage_files,
                    'wp_attachments' => count($attachments),
                    'wp_marked_migrated' => $marked_migrated,
                    'matched' => count($storage_files_matched),
                ],
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('reconciliation', 'Error getting discrepancies: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Error getting discrepancies: ' . $e->getMessage(),
            ]);
        }
    }
}

