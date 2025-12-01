<?php
/**
 * Reconciliation class for syncing S3 state with WordPress metadata
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Migration;

use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;

use Aws\Exception\AwsException;

/**
 * Handles reconciliation between S3 bucket and WordPress metadata
 * 
 * This tool scans S3 and matches files with WordPress attachments,
 * then updates the `_media_toolkit_migrated` metadata accordingly.
 */
final class Reconciliation extends Batch_Processor
{
    private S3_Client $s3_client;
    private History $history;
    
    /** @var array Cached S3 file list during reconciliation */
    private array $s3_files_cache = [];

    public function __construct(
        S3_Client $s3_client,
        Settings $settings,
        Logger $logger,
        History $history
    ) {
        parent::__construct($logger, $settings, 'reconciliation');
        
        $this->s3_client = $s3_client;
        $this->history = $history;
        
        // Register additional AJAX handlers
        add_action('wp_ajax_media_toolkit_reconciliation_scan_s3', [$this, 'ajax_scan_s3']);
        add_action('wp_ajax_media_toolkit_reconciliation_preview', [$this, 'ajax_preview']);
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
     */
    public function get_stats(): array
    {
        global $wpdb;

        // Total WordPress attachments
        $total_attachments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        // Marked as migrated
        $marked_migrated = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                 WHERE pm.meta_key = %s AND pm.meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );

        // S3 stats from cache
        $s3_stats = $this->settings->get_cached_s3_stats();
        $s3_original_files = $s3_stats['original_files'] ?? 0;
        $s3_total_files = $s3_stats['files'] ?? 0;

        // Discrepancy
        $discrepancy = abs($s3_original_files - $marked_migrated);
        $has_discrepancy = $discrepancy > 0 && $s3_original_files > 0;

        return [
            'total_attachments' => $total_attachments,
            'marked_migrated' => $marked_migrated,
            'not_marked' => $total_attachments - $marked_migrated,
            's3_original_files' => $s3_original_files,
            's3_total_files' => $s3_total_files,
            'discrepancy' => $discrepancy,
            'has_discrepancy' => $has_discrepancy,
            'progress_percentage' => $total_attachments > 0 
                ? round(($marked_migrated / $total_attachments) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Scan S3 and build a map of files
     * Returns array of S3 keys indexed by relative path
     */
    public function scan_s3_files(): array
    {
        $client = $this->s3_client->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return [];
        }

        $base_path = $this->settings->get_s3_base_path();
        $files = [];
        $continuation_token = null;

        // Thumbnail pattern to identify non-original files
        $thumbnail_pattern = '/-\d+x\d+(-[a-z0-9]+)?\.[a-zA-Z0-9]+$/';

        try {
            do {
                $params = [
                    'Bucket' => $config->bucket,
                    'Prefix' => $base_path,
                    'MaxKeys' => 1000,
                ];

                if ($continuation_token !== null) {
                    $params['ContinuationToken'] = $continuation_token;
                }

                $result = $client->listObjectsV2($params);

                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        $key = $object['Key'] ?? '';
                        $size = $object['Size'] ?? 0;
                        
                        // Skip thumbnails - only track originals
                        if (preg_match($thumbnail_pattern, $key)) {
                            continue;
                        }

                        // Extract relative path from S3 key
                        // S3 key format: media/{env}/wp-content/uploads/2024/01/image.jpg
                        // We want: 2024/01/image.jpg
                        $relative_path = $this->extract_relative_path($key, $base_path);
                        
                        if ($relative_path) {
                            $files[$relative_path] = [
                                's3_key' => $key,
                                'size' => $size,
                            ];
                        }
                    }
                }

                $continuation_token = $result['NextContinuationToken'] ?? null;
                
            } while ($result['IsTruncated'] ?? false);

        } catch (AwsException $e) {
            $this->logger->error('reconciliation', 'Failed to scan S3: ' . $e->getMessage());
            return [];
        }

        return $files;
    }

    /**
     * Extract relative upload path from S3 key
     */
    private function extract_relative_path(string $s3_key, string $base_path): ?string
    {
        // Remove base path prefix
        $base_path = rtrim($base_path, '/') . '/';
        
        if (strpos($s3_key, $base_path) === 0) {
            return substr($s3_key, strlen($base_path));
        }

        // Try to extract from wp-content/uploads
        if (preg_match('#wp-content/uploads/(.+)$#', $s3_key, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get pending items (WordPress attachments not marked as migrated)
     */
    protected function count_pending_items(array $options = []): int
    {
        global $wpdb;

        // If we're in "mark all found" mode, count all attachments
        if ($options['mode'] ?? '' === 'mark_found') {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
            );
        }

        // Default: count not marked
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                 WHERE p.post_type = 'attachment'
                 AND (pm.meta_value IS NULL OR pm.meta_value != %s)",
                '_media_toolkit_migrated',
                '1'
            )
        );
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

        // Get S3 files from options (should be passed from start)
        $s3_files = $options['s3_files'] ?? [];
        
        // Check if file exists in S3
        $found_on_s3 = isset($s3_files[$file_path]);
        
        if ($found_on_s3) {
            $s3_data = $s3_files[$file_path];
            
            // Update metadata
            update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
            update_post_meta($attachment_id, '_media_toolkit_key', $s3_data['s3_key']);
            
            // Generate URL
            $url = $this->settings->get_file_url($s3_data['s3_key']);
            update_post_meta($attachment_id, '_media_toolkit_url', $url);
            
            // Record in history
            $this->history->record(
                HistoryAction::MIGRATED,
                $attachment_id,
                $file_path,
                $s3_data['s3_key'],
                $s3_data['size'],
                ['source' => 'reconciliation']
            );

            return [
                'success' => true,
                'found' => true,
                's3_key' => $s3_data['s3_key'],
            ];
        } else {
            // File not found on S3
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
        // First, scan S3 for all files
        $this->logger->info('reconciliation', 'Scanning S3 bucket for files...');
        
        $s3_files = $this->scan_s3_files();
        $s3_count = count($s3_files);
        
        $this->logger->info('reconciliation', "Found {$s3_count} original files on S3");

        // Store S3 files in options for processing
        $options['s3_files'] = $s3_files;
        $options['s3_count'] = $s3_count;

        // Call parent start
        return parent::start($options);
    }

    /**
     * Override process_batch to include s3_files from state
     */
    public function process_batch(): array
    {
        $state = $this->get_state();
        
        // Restore s3_files from state options
        if (empty($state['options']['s3_files'])) {
            // Need to re-scan if s3_files is not in state (might happen on resume)
            $s3_files = $this->scan_s3_files();
            $state['options']['s3_files'] = $s3_files;
            $this->save_state_internal($state);
        }

        return parent::process_batch();
    }

    /**
     * Save state (internal helper)
     */
    private function save_state_internal(array $state): void
    {
        $state['updated_at'] = time();
        set_transient($this->state_transient_key, $state, $this->transient_ttl);
        update_option($this->state_backup_key, $state);
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
     * AJAX: Scan S3 and return preview
     */
    public function ajax_scan_s3(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->logger->info('reconciliation', 'Starting S3 scan for preview...');
        
        $s3_files = $this->scan_s3_files();
        $s3_count = count($s3_files);

        // Count how many WordPress attachments match
        global $wpdb;
        
        $total_attachments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        // Get all attachment file paths
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
            if (isset($s3_files[$data->file_path])) {
                $matches++;
                $matched_ids[] = $id;
            } else {
                $not_found++;
            }
        }

        // Currently marked as migrated
        $currently_marked = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                 WHERE pm.meta_key = %s AND pm.meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );

        // How many would be newly marked
        $would_be_marked = count(array_diff($matched_ids, $this->get_already_marked_ids()));

        wp_send_json_success([
            's3_original_files' => $s3_count,
            'wp_attachments' => $total_attachments,
            'matches' => $matches,
            'not_found_on_s3' => $not_found,
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

        // Check if file exists on S3
        $s3_key = $this->settings->get_s3_base_path() . '/' . $file_path;
        $exists = $this->s3_client->file_exists($s3_key);

        if ($exists) {
            update_post_meta($attachment_id, '_media_toolkit_migrated', '1');
            update_post_meta($attachment_id, '_media_toolkit_key', $s3_key);
            
            $url = $this->settings->get_file_url($s3_key);
            update_post_meta($attachment_id, '_media_toolkit_url', $url);

            return [
                'success' => true,
                'found' => true,
                's3_key' => $s3_key,
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
}

