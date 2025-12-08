<?php
/**
 * Stats class for dashboard statistics
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Stats;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\History\History;

/**
 * Handles statistics calculation for dashboard
 */
final class Stats
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY = 'media_toolkit_stats_cache';
    private const CONNECTION_CACHE_KEY = 'media_toolkit_connection_status';

    private Logger $logger;
    private History $history;
    private ?Settings $settings;

    public function __construct(Logger $logger, History $history, ?Settings $settings = null)
    {
        $this->logger = $logger;
        $this->history = $history;
        $this->settings = $settings;
    }

    /**
     * Get all dashboard stats
     * 
     * Uses hybrid logic:
     * 1. If WordPress has migration metadata → use it (plugin actively used)
     * 2. Otherwise → estimate from S3 stats (files may have been uploaded without plugin)
     * 3. Show both values for transparency
     */
    public function get_dashboard_stats(): array
    {
        // Try to get from cache
        $cached = get_transient(self::CACHE_KEY);
        
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;

        // Count WordPress media attachments
        $wp_attachments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        // Count WordPress attachments that are marked as migrated to S3
        $migrated_via_plugin = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                 WHERE pm.meta_key = %s AND pm.meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );

        // Check if we have real storage stats
        $storage_stats = $this->settings?->get_cached_storage_stats();
        $storage_synced_at = null;

        // Storage bucket stats
        if ($storage_stats !== null) {
            $storage_total_files = $storage_stats['files'] ?? 0;
            $storage_original_files = $storage_stats['original_files'] ?? $storage_total_files;
            $total_storage = $storage_stats['size'] ?? 0;
            $original_storage = $storage_stats['original_size'] ?? $total_storage;
            $storage_synced_at = $storage_stats['synced_at'] ?? null;
        } else {
            $storage_total_files = 0;
            $storage_original_files = 0;
            $total_storage = 0;
            $original_storage = 0;
        }

        // HYBRID LOGIC: Determine the best estimate of files in storage
        // Priority: 1) Storage sync data (if available), 2) WordPress metadata (fallback)
        $sync_source = 'unknown';
        $estimated_in_storage = 0;
        $needs_reconciliation = false;

        if ($storage_stats !== null) {
            // We have real storage sync data - use it as the source of truth
            $sync_source = 'storage_sync';
            $estimated_in_storage = $storage_original_files;

            // Check if WordPress metadata differs significantly (might need reconciliation)
            if ($migrated_via_plugin > 0 && abs($migrated_via_plugin - $storage_original_files) > max(10, $storage_original_files * 0.1)) {
                $needs_reconciliation = true;
            }
        } elseif ($migrated_via_plugin > 0) {
            // No storage sync data, but plugin has been actively used - use WordPress metadata as fallback
            $sync_source = 'wordpress_meta';
            $estimated_in_storage = $migrated_via_plugin;
            $needs_reconciliation = true; // Recommend doing a storage sync
        } else {
            // No data from either source
            $sync_source = 'none';
            $estimated_in_storage = 0;
        }

        // Calculate sync percentage
        $sync_percentage = $wp_attachments > 0
            ? min(100, round(($estimated_in_storage / $wp_attachments) * 100, 1))
            : 0;

        // Calculate pending
        $pending_attachments = max(0, $wp_attachments - $estimated_in_storage);

        $stats = [
            // WordPress stats
            'wp_attachments' => $wp_attachments,

            // Sync status (hybrid calculation)
            'estimated_in_storage' => $estimated_in_storage,
            'pending_attachments' => $pending_attachments,
            'sync_percentage' => $sync_percentage,
            'sync_source' => $sync_source,
            'needs_reconciliation' => $needs_reconciliation,

            // WordPress metadata (what plugin knows)
            'migrated_via_plugin' => $migrated_via_plugin,

            // Storage bucket raw stats
            'storage_total_files' => $storage_total_files,
            'storage_original_files' => $storage_original_files,
            'storage_total_size' => $total_storage,
            'storage_original_size' => $original_storage,
            'total_storage_formatted' => $this->format_bytes($total_storage),
            'original_storage_formatted' => $this->format_bytes($original_storage),

            // For backwards compatibility
            'original_files' => $estimated_in_storage,
            'total_files' => $storage_total_files,
            'total_storage' => $total_storage,
            'migrated_attachments' => $estimated_in_storage,

            // Activity stats
            'files_today' => $this->history->get_files_uploaded_today(),
            'files_migrated' => $this->history->get_migrated_count(),
            'errors_last_7_days' => $this->logger->get_error_count_last_days(7),
            'last_upload' => $this->history->get_last_upload(),
            'last_upload_formatted' => $this->format_relative_time($this->history->get_last_upload()),
            'uploads_per_day' => $this->history->get_uploads_per_day(7),
            'connection_status' => $this->get_connection_status(),
            'storage_synced_at' => $storage_synced_at,
            'storage_synced_at_formatted' => $storage_synced_at ? $this->format_relative_time($storage_synced_at) : null,
            'using_real_storage_stats' => $storage_stats !== null,
        ];

        // Cache for 5 minutes
        set_transient(self::CACHE_KEY, $stats, self::CACHE_TTL);

        return $stats;
    }

    /**
     * Get connection status (cached)
     */
    public function get_connection_status(): array
    {
        $cached = get_transient(self::CONNECTION_CACHE_KEY);
        
        if ($cached !== false) {
            return $cached;
        }

        // Default to unknown status
        $status = [
            'connected' => null,
            'message' => 'Connection status unknown',
            'checked_at' => null,
        ];

        set_transient(self::CONNECTION_CACHE_KEY, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Update connection status (called after test connection)
     */
    public function update_connection_status(bool $connected, string $message): void
    {
        $status = [
            'connected' => $connected,
            'message' => $message,
            'checked_at' => current_time('mysql'),
        ];

        set_transient(self::CONNECTION_CACHE_KEY, $status, self::CACHE_TTL);
        
        // Invalidate main stats cache
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Clear stats cache
     */
    public function clear_cache(): void
    {
        delete_transient(self::CACHE_KEY);
        delete_transient(self::CONNECTION_CACHE_KEY);
    }

    /**
     * Get migration stats
     */
    public function get_migration_stats(): array
    {
        global $wpdb;

        // Total attachments in WordPress
        $total_attachments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );

        // Attachments already migrated (only count meta for existing attachments)
        $migrated_attachments = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                WHERE pm.meta_key = %s AND pm.meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );

        // Get total size of non-migrated files
        $pending_size = $this->calculate_pending_migration_size();

        // Cap at 100% maximum
        $progress = $total_attachments > 0 
            ? min(100, round(($migrated_attachments / $total_attachments) * 100, 2))
            : 0;

        return [
            'total_attachments' => $total_attachments,
            'migrated_attachments' => min($migrated_attachments, $total_attachments),
            'pending_attachments' => max(0, $total_attachments - $migrated_attachments),
            'pending_size' => $pending_size,
            'pending_size_formatted' => $this->format_bytes($pending_size),
            'progress_percentage' => $progress,
        ];
    }

    /**
     * Calculate size of pending migration files
     */
    private function calculate_pending_migration_size(): int
    {
        global $wpdb;

        // Get IDs of non-migrated attachments
        $non_migrated_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                WHERE p.post_type = 'attachment' AND (pm.meta_value IS NULL OR pm.meta_value != '1')
                LIMIT 1000",
                '_media_toolkit_migrated'
            )
        );

        if (empty($non_migrated_ids)) {
            return 0;
        }

        $total_size = 0;
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        foreach ($non_migrated_ids as $id) {
            $file = get_attached_file($id);
            
            if ($file && file_exists($file)) {
                $total_size += filesize($file);
                
                // Also count thumbnails
                $metadata = wp_get_attachment_metadata($id);
                if (!empty($metadata['sizes'])) {
                    $file_dir = dirname($file);
                    foreach ($metadata['sizes'] as $size) {
                        $thumb_file = $file_dir . '/' . $size['file'];
                        if (file_exists($thumb_file)) {
                            $total_size += filesize($thumb_file);
                        }
                    }
                }
            }
        }

        return $total_size;
    }

    /**
     * Format bytes to human readable
     */
    public function format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format timestamp to relative time
     */
    public function format_relative_time(?string $timestamp): string
    {
        if ($timestamp === null) {
            return 'Never';
        }

        $time = strtotime($timestamp);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    /**
     * Get sparkline data for uploads with labels
     */
    public function get_sparkline_data(int $days = 7): array
    {
        $uploads = $this->history->get_uploads_per_day($days);
        
        // Create array with all days (including zeros)
        $data = [];
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[$date] = 0;
            // Short day name (Mon, Tue, etc.)
            $labels[$date] = date_i18n('D', strtotime("-{$i} days"));
        }
        
        // Fill in actual values
        foreach ($uploads as $upload) {
            if (isset($data[$upload['date']])) {
                $data[$upload['date']] = (int) $upload['count'];
            }
        }
        
        return [
            'labels' => array_values($labels),
            'values' => array_values($data),
        ];
    }

    /**
     * Get history statistics by action type
     */
    public function get_history_stats(): array
    {
        return [
            'uploaded' => $this->history->get_action_count('uploaded'),
            'migrated' => $this->history->get_action_count('migrated'),
            'edited' => $this->history->get_action_count('edited'),
            'deleted' => $this->history->get_action_count('deleted'),
            'invalidation' => $this->history->get_action_count('invalidation'),
        ];
    }
}

