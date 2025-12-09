<?php
/**
 * CloudSync - Unified tool for storage synchronization
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

use Metodo\MediaToolkit\Core\Batch_Processor;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Error\Error_Handler;
use Metodo\MediaToolkit\Database\OptimizationTable;
use Metodo\MediaToolkit\Stats\Stats;

/**
 * Unified CloudSync tool that handles:
 * - Migration (upload new files to cloud)
 * - Integrity Check (verify files exist on cloud)
 * - Reconciliation (fix discrepancies)
 * - Stats Sync (update storage statistics)
 */
final class CloudSync extends Batch_Processor
{
    // Sync modes
    public const MODE_SYNC = 'sync';              // Upload pending files
    public const MODE_INTEGRITY = 'integrity';     // Check and fix integrity issues
    public const MODE_FULL = 'full';              // Full sync + integrity check

    private StorageInterface $storage;
    private History $history;
    private Error_Handler $error_handler;
    private Stats $stats;

    /** @var array Cached cloud files during sync */
    private array $cloud_files_cache = [];

    /** @var string Transient key for cloud files cache */
    private const CLOUD_FILES_CACHE_KEY = 'media_toolkit_cloudsync_cloud_files';

    /** @var string Option key for sync status (used by admin notice) */
    private const SYNC_STATUS_OPTION = 'media_toolkit_cloudsync_status';

    public function __construct(
        StorageInterface $storage,
        Settings $settings,
        Logger $logger,
        History $history,
        Error_Handler $error_handler,
        Stats $stats
    ) {
        parent::__construct($logger, $settings, 'cloudsync');

        $this->storage = $storage;
        $this->history = $history;
        $this->error_handler = $error_handler;
        $this->stats = $stats;

        // Register additional AJAX handlers
        add_action('wp_ajax_media_toolkit_cloudsync_analyze', [$this, 'ajax_analyze']);
        add_action('wp_ajax_media_toolkit_cloudsync_get_discrepancies', [$this, 'ajax_get_discrepancies']);
        add_action('wp_ajax_media_toolkit_cloudsync_fix_integrity', [$this, 'ajax_fix_integrity']);
        add_action('wp_ajax_media_toolkit_cloudsync_clear_metadata', [$this, 'ajax_clear_metadata']);
    }

    protected function get_processor_name(): string
    {
        return 'CloudSync';
    }

    /**
     * Analyze current sync status
     * This is a quick check that doesn't scan all cloud files
     * 
     * Uses existing Stats::get_migration_stats() for consistency.
     */
    public function analyze(): CloudSyncStatus
    {
        // Use existing migration stats method
        $migrationStats = $this->stats->get_migration_stats();
        
        $totalAttachments = $migrationStats['total_attachments'];
        $migratedToCloud = $migrationStats['migrated_attachments'];
        $pendingMigration = $migrationStats['pending_attachments'];

        // Get storage stats for integrity checking
        $storageStats = $this->settings->get_cached_storage_stats();
        $cloudFilesCount = $storageStats['original_files'] ?? $storageStats['files'] ?? 0;
        $lastSyncAt = $storageStats['synced_at'] ?? null;

        // Calculate integrity issues (marked but potentially not on cloud)
        // This is an estimate - actual count requires full scan
        $integrityIssues = 0;
        if ($cloudFilesCount > 0 && $migratedToCloud > $cloudFilesCount) {
            $integrityIssues = $migratedToCloud - $cloudFilesCount;
        }

        // Orphan files on cloud (more files on cloud than marked)
        $orphanCloudFiles = 0;
        if ($cloudFilesCount > $migratedToCloud) {
            $orphanCloudFiles = $cloudFilesCount - $migratedToCloud;
        }

        // Count local files still available for recovery
        $localFilesAvailable = $this->count_local_files_available();

        // Get optimization stats
        $optimizationStats = $this->get_optimization_stats();

        return CloudSyncStatus::fromAnalysis([
            'total_attachments' => $totalAttachments,
            'migrated_to_cloud' => $migratedToCloud,
            'pending_migration' => $pendingMigration,
            'cloud_files_count' => $cloudFilesCount,
            'integrity_issues' => $integrityIssues,
            'orphan_cloud_files' => $orphanCloudFiles,
            'local_files_available' => $localFilesAvailable,
            'remove_local_enabled' => $this->settings->should_remove_local_files(),
            'last_sync_at' => $lastSyncAt,
            // Optimization stats
            'total_images' => $optimizationStats['total_images'],
            'optimized_images' => $optimizationStats['optimized_images'],
            'pending_optimization' => $optimizationStats['pending_optimization'],
            'optimization_percentage' => $optimizationStats['optimization_percentage'],
            'total_bytes_saved' => $optimizationStats['total_bytes_saved'],
            'average_savings_percent' => $optimizationStats['average_savings_percent'],
        ]);
    }

    /**
     * Get optimization statistics for images
     * 
     * Uses centralized OptimizationTable::get_full_stats() for consistency
     * across all pages (Dashboard, CloudSync, Batch Processor).
     */
    private function get_optimization_stats(): array
    {
        // Check if optimization table exists
        if (!OptimizationTable::table_exists()) {
        global $wpdb;
        $totalImages = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'"
        );

            return [
                'total_images' => $totalImages,
                'optimized_images' => 0,
                'pending_optimization' => $totalImages,
                'optimization_percentage' => 0,
                'total_bytes_saved' => 0,
                'average_savings_percent' => 0,
            ];
        }

        // Use centralized stats - single source of truth
        $stats = OptimizationTable::get_full_stats();

        return [
            'total_images' => $stats['total_images'],
            'optimized_images' => $stats['optimized_images'],
            'pending_optimization' => $stats['pending_images'],
            'optimization_percentage' => (int) $stats['progress_percentage'],
            'total_bytes_saved' => $stats['total_saved'],
            'average_savings_percent' => $stats['average_savings_percent'],
        ];
    }

    /**
     * Full analysis with cloud scan
     * This scans all cloud files and compares with WordPress metadata
     */
    public function analyzeDeep(): CloudSyncStatus
    {
        $this->logger->info('cloudsync', 'Starting deep analysis with cloud scan...');

        // Scan cloud storage
        $cloudFiles = $this->scan_cloud_files();
        $cloudFilesCount = count($cloudFiles);

        // Cache for later use
        set_transient(self::CLOUD_FILES_CACHE_KEY, $cloudFiles, HOUR_IN_SECONDS);

        global $wpdb;

        // Use existing migration stats method for total count
        $migrationStats = $this->stats->get_migration_stats();
        $totalAttachments = $migrationStats['total_attachments'];

        // Get all attachments with their status (need full list for detailed comparison)
        $attachments = $wpdb->get_results(
            "SELECT p.ID, pm.meta_value as file_path, pm2.meta_value as is_migrated
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_media_toolkit_migrated'
             WHERE p.post_type = 'attachment'",
            OBJECT
        );

        $migratedToCloud = 0;
        $pendingMigration = 0;
        $integrityIssues = 0;
        $localFilesAvailable = 0;

        foreach ($attachments as $attachment) {
            $isMigrated = $attachment->is_migrated === '1';
            $inCloud = isset($cloudFiles[$attachment->file_path]);

            if ($isMigrated) {
                $migratedToCloud++;

                if (!$inCloud) {
                    $integrityIssues++;
                }
            } else {
                $pendingMigration++;
            }

            // Check if local file exists
            $uploadDir = wp_upload_dir();
            $localPath = $uploadDir['basedir'] . '/' . $attachment->file_path;
            if (file_exists($localPath)) {
                $localFilesAvailable++;
            }
        }

        // Orphan files on cloud
        $matchedCloudFiles = 0;
        foreach ($attachments as $attachment) {
            if (isset($cloudFiles[$attachment->file_path])) {
                $matchedCloudFiles++;
            }
        }
        $orphanCloudFiles = $cloudFilesCount - $matchedCloudFiles;

        // Update storage stats cache
        $totalSize = array_sum(array_column($cloudFiles, 'size'));
        $this->settings->save_storage_stats([
            'files' => $cloudFilesCount,
            'original_files' => $cloudFilesCount,
            'size' => $totalSize,
            'original_size' => $totalSize,
            'synced_at' => current_time('mysql'),
        ]);

        // Get optimization stats
        $optimizationStats = $this->get_optimization_stats();

        $status = CloudSyncStatus::fromAnalysis([
            'total_attachments' => $totalAttachments,
            'migrated_to_cloud' => $migratedToCloud,
            'pending_migration' => $pendingMigration,
            'cloud_files_count' => $cloudFilesCount,
            'integrity_issues' => $integrityIssues,
            'orphan_cloud_files' => $orphanCloudFiles,
            'local_files_available' => $localFilesAvailable,
            'remove_local_enabled' => $this->settings->should_remove_local_files(),
            'last_sync_at' => current_time('mysql'),
            // Optimization stats
            'total_images' => $optimizationStats['total_images'],
            'optimized_images' => $optimizationStats['optimized_images'],
            'pending_optimization' => $optimizationStats['pending_optimization'],
            'optimization_percentage' => $optimizationStats['optimization_percentage'],
            'total_bytes_saved' => $optimizationStats['total_bytes_saved'],
            'average_savings_percent' => $optimizationStats['average_savings_percent'],
        ]);

        // Save status for admin notice
        $this->save_sync_status($status);

        $this->logger->info('cloudsync', sprintf(
            'Deep analysis complete: %d total, %d migrated, %d pending, %d issues, %d%% optimized',
            $totalAttachments,
            $migratedToCloud,
            $pendingMigration,
            $integrityIssues,
            $optimizationStats['optimization_percentage']
        ));

        return $status;
    }

    /**
     * Scan cloud storage for files
     * Returns array keyed by relative path
     */
    private function scan_cloud_files(): array
    {
        $basePath = $this->settings->get_storage_base_path();
        $files = [];
        $continuationToken = null;

        // Thumbnail pattern to identify non-original files
        $thumbnailPattern = '/-\d+x\d+(-[a-z0-9]+)?\.[a-zA-Z0-9]+$/';

        do {
            $result = $this->storage->list_objects_with_metadata(1000, $continuationToken);

            if ($result === null) {
                $this->logger->error('cloudsync', 'Failed to list storage objects');
                break;
            }

            foreach ($result['objects'] as $object) {
                $key = $object['key'];
                $size = $object['size'];

                // Skip thumbnails - only track originals
                if (preg_match($thumbnailPattern, $key)) {
                    continue;
                }

                // Extract relative path from storage key
                $relativePath = $this->extract_relative_path($key, $basePath);

                if ($relativePath) {
                    $files[$relativePath] = [
                        'storage_key' => $key,
                        'size' => $size,
                    ];
                }
            }

            $continuationToken = $result['next_token'];

        } while ($result['is_truncated'] && $continuationToken !== null);

        return $files;
    }

    /**
     * Extract relative upload path from storage key
     */
    private function extract_relative_path(string $storageKey, string $basePath): ?string
    {
        // Remove base path prefix
        $basePath = rtrim($basePath, '/') . '/';

        if (strpos($storageKey, $basePath) === 0) {
            return substr($storageKey, strlen($basePath));
        }

        // Try to extract from wp-content/uploads
        if (preg_match('#wp-content/uploads/(.+)$#', $storageKey, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Count local files still available
     */
    private function count_local_files_available(): int
    {
        global $wpdb;

        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'];

        $filePaths = $wpdb->get_col(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
             WHERE pm.meta_key = '_wp_attached_file'"
        );

        $count = 0;
        foreach ($filePaths as $filePath) {
            if (file_exists($baseDir . '/' . $filePath)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Save sync status for admin notice
     */
    private function save_sync_status(CloudSyncStatus $status): void
    {
        update_option(self::SYNC_STATUS_OPTION, [
            'integrity_issues' => $status->integrityIssues,
            'pending_migration' => $status->pendingMigration,
            'overall_status' => $status->overallStatus,
            'last_check' => current_time('mysql'),
        ]);
    }

    /**
     * Get saved sync status
     */
    public static function get_saved_sync_status(): ?array
    {
        return get_option(self::SYNC_STATUS_OPTION, null);
    }

    // ==================== Batch Processing Implementation ====================

    public function get_stats(): array
    {
        $status = $this->analyze();
        return $status->toArray();
    }

    protected function count_pending_items(array $options = []): int
    {
        $mode = $options['mode'] ?? self::MODE_SYNC;

        if ($mode === self::MODE_INTEGRITY) {
            // For integrity mode, we process all marked as migrated
            return $this->count_migrated_attachments();
        }

        // For sync mode, count not migrated
        return $this->count_pending_migration();
    }

    private function count_pending_migration(): int
    {
        global $wpdb;

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

    private function count_migrated_attachments(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_type = 'attachment'
                 WHERE pm.meta_key = %s AND pm.meta_value = %s",
                '_media_toolkit_migrated',
                '1'
            )
        );
    }

    protected function get_pending_items(int $limit, int $afterId, array $options = []): array
    {
        global $wpdb;

        $mode = $options['mode'] ?? self::MODE_SYNC;

        if ($mode === self::MODE_INTEGRITY) {
            // Get migrated attachments for integrity check
            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT p.ID FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                     WHERE p.post_type = 'attachment'
                     AND pm.meta_value = %s
                     AND p.ID > %d
                     ORDER BY p.ID ASC
                     LIMIT %d",
                    '_media_toolkit_migrated',
                    '1',
                    $afterId,
                    $limit
                )
            );
        }

        // Get not migrated attachments for sync
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
                $afterId,
                $limit
            )
        );
    }

    protected function get_item_id($item): int
    {
        return (int) $item;
    }

    protected function process_item($item, array $options = []): array
    {
        $attachmentId = (int) $item;
        $mode = $options['mode'] ?? self::MODE_SYNC;

        if ($mode === self::MODE_INTEGRITY) {
            return $this->process_integrity_check($attachmentId, $options);
        }

        return $this->process_sync($attachmentId, $options);
    }

    /**
     * Process sync (upload to cloud)
     */
    private function process_sync(int $attachmentId, array $options): array
    {
        $removeLocal = $options['remove_local'] ?? false;
        $file = get_attached_file($attachmentId);

        if (empty($file)) {
            return [
                'success' => false,
                'error' => 'No file path found',
            ];
        }

        if (!file_exists($file)) {
            return [
                'success' => false,
                'error' => 'File does not exist locally',
            ];
        }

        // Upload main file
        $result = $this->storage->upload_file($file, $attachmentId);

        if (!$result->success) {
            return [
                'success' => false,
                'error' => $result->error,
            ];
        }

        // Update meta
        update_post_meta($attachmentId, '_media_toolkit_key', $result->key);
        update_post_meta($attachmentId, '_media_toolkit_url', $result->url);
        update_post_meta($attachmentId, '_media_toolkit_migrated', '1');
        if ($result->provider !== null) {
            update_post_meta($attachmentId, '_media_toolkit_provider', $result->provider->value);
        }

        $fileSize = filesize($file) ?: 0;

        // Record in history
        $this->history->record(
            HistoryAction::MIGRATED,
            $attachmentId,
            $file,
            $result->key,
            $fileSize
        );

        // Upload thumbnails
        $metadata = wp_get_attachment_metadata($attachmentId);

        if (!empty($metadata['sizes'])) {
            $fileDir = dirname($file);
            $thumbKeys = [];

            foreach ($metadata['sizes'] as $sizeName => $sizeData) {
                $thumbFile = $fileDir . '/' . $sizeData['file'];

                if (file_exists($thumbFile)) {
                    $thumbResult = $this->storage->upload_file($thumbFile, $attachmentId);

                    if ($thumbResult->success) {
                        $thumbKeys[$sizeName] = $thumbResult->key;

                        if ($removeLocal) {
                            @unlink($thumbFile);
                        }
                    }
                }
            }

            update_post_meta($attachmentId, '_media_toolkit_thumb_keys', $thumbKeys);
        }

        // Remove local file if requested
        if ($removeLocal) {
            @unlink($file);
        }

        $this->logger->success(
            'cloudsync',
            'File synced to cloud storage',
            $attachmentId,
            basename($file)
        );

        return [
            'success' => true,
            'storage_key' => $result->key,
            'url' => $result->url,
        ];
    }

    /**
     * Process integrity check
     */
    private function process_integrity_check(int $attachmentId, array $options): array
    {
        $filePath = get_post_meta($attachmentId, '_wp_attached_file', true);

        if (empty($filePath)) {
            return [
                'success' => false,
                'error' => 'No file path found',
            ];
        }

        // Get cloud files from cache
        $cloudFiles = $this->cloud_files_cache;
        if (empty($cloudFiles)) {
            $cloudFiles = get_transient(self::CLOUD_FILES_CACHE_KEY);
            if ($cloudFiles === false) {
                // Need to scan if cache is empty
                $cloudFiles = $this->scan_cloud_files();
                set_transient(self::CLOUD_FILES_CACHE_KEY, $cloudFiles, HOUR_IN_SECONDS);
            }
            $this->cloud_files_cache = $cloudFiles;
        }

        $inCloud = isset($cloudFiles[$filePath]);

        if ($inCloud) {
            // File exists on cloud, ensure metadata is correct
            $cloudData = $cloudFiles[$filePath];
            update_post_meta($attachmentId, '_media_toolkit_key', $cloudData['storage_key']);
            $url = $this->settings->get_file_url($cloudData['storage_key']);
            update_post_meta($attachmentId, '_media_toolkit_url', $url);
            update_post_meta($attachmentId, '_media_toolkit_migrated', '1');

            return [
                'success' => true,
                'found' => true,
                'storage_key' => $cloudData['storage_key'],
            ];
        }

        // File NOT found on cloud
        $autoFix = $options['auto_fix'] ?? false;

        if ($autoFix) {
            // Check if local file exists for re-upload
            $uploadDir = wp_upload_dir();
            $localPath = $uploadDir['basedir'] . '/' . $filePath;

            if (file_exists($localPath)) {
                // Re-upload the file
                $result = $this->storage->upload_file($localPath, $attachmentId);

                if ($result->success) {
                    update_post_meta($attachmentId, '_media_toolkit_key', $result->key);
                    update_post_meta($attachmentId, '_media_toolkit_url', $result->url);

                    $this->history->record(
                        HistoryAction::MIGRATED,
                        $attachmentId,
                        $localPath,
                        $result->key,
                        filesize($localPath) ?: 0,
                        ['source' => 'integrity_fix']
                    );

                    $this->logger->success(
                        'cloudsync',
                        'File re-uploaded during integrity fix',
                        $attachmentId,
                        basename($localPath)
                    );

                    return [
                        'success' => true,
                        'found' => false,
                        'fixed' => true,
                        'storage_key' => $result->key,
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Failed to re-upload: ' . $result->error,
                ];
            }

            // No local file - clear metadata
            delete_post_meta($attachmentId, '_media_toolkit_migrated');
            delete_post_meta($attachmentId, '_media_toolkit_key');
            delete_post_meta($attachmentId, '_media_toolkit_url');
            delete_post_meta($attachmentId, '_media_toolkit_thumb_keys');

            $this->logger->warning(
                'cloudsync',
                'Cleared metadata for missing file (no local backup)',
                $attachmentId,
                $filePath
            );

            return [
                'success' => true,
                'found' => false,
                'fixed' => true,
                'action' => 'metadata_cleared',
            ];
        }

        // Just report the issue without fixing
        return [
            'success' => true,
            'found' => false,
            'skipped' => true,
        ];
    }

    /**
     * Start sync with mode selection
     */
    public function start(array $options = []): array
    {
        $mode = $options['mode'] ?? self::MODE_SYNC;

        // For integrity mode, scan cloud files first
        if ($mode === self::MODE_INTEGRITY || $mode === self::MODE_FULL) {
            $this->logger->info('cloudsync', 'Scanning cloud storage for integrity check...');
            $cloudFiles = $this->scan_cloud_files();
            set_transient(self::CLOUD_FILES_CACHE_KEY, $cloudFiles, HOUR_IN_SECONDS);
            $options['cloud_files_count'] = count($cloudFiles);
        }

        return parent::start($options);
    }

    /**
     * Override stop to clean up cache
     */
    public function stop(): void
    {
        delete_transient(self::CLOUD_FILES_CACHE_KEY);
        $this->cloud_files_cache = [];

        parent::stop();
    }

    /**
     * Clear all migration metadata
     */
    public function clear_all_metadata(): int
    {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s, %s)",
                '_media_toolkit_migrated',
                '_media_toolkit_key',
                '_media_toolkit_url',
                '_media_toolkit_thumb_keys',
                '_media_toolkit_provider'
            )
        );

        $this->logger->info('cloudsync', "Cleared migration metadata from {$deleted} records");

        // Clear stats cache
        delete_transient('media_toolkit_stats_cache');

        return $deleted;
    }

    /**
     * Get detailed discrepancies
     */
    public function get_discrepancies(): array
    {
        global $wpdb;

        // Scan cloud files
        $cloudFiles = get_transient(self::CLOUD_FILES_CACHE_KEY);
        if ($cloudFiles === false) {
            $cloudFiles = $this->scan_cloud_files();
            set_transient(self::CLOUD_FILES_CACHE_KEY, $cloudFiles, HOUR_IN_SECONDS);
        }

        // Get all attachments with their status
        $attachments = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value as file_path, pm2.meta_value as is_migrated
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_media_toolkit_migrated'
             WHERE p.post_type = 'attachment'",
            OBJECT
        );

        $uploadDir = wp_upload_dir();
        $notOnCloud = [];
        $notMarked = [];
        $matchedCloudFiles = [];

        foreach ($attachments as $attachment) {
            $isMigrated = $attachment->is_migrated === '1';
            $inCloud = isset($cloudFiles[$attachment->file_path]);
            $localPath = $uploadDir['basedir'] . '/' . $attachment->file_path;
            $localExists = file_exists($localPath);

            if ($inCloud) {
                $matchedCloudFiles[$attachment->file_path] = true;
            }

            // Marked as migrated but NOT on cloud
            if ($isMigrated && !$inCloud) {
                $notOnCloud[] = [
                    'id' => (int) $attachment->ID,
                    'title' => $attachment->post_title,
                    'file' => $attachment->file_path,
                    'local_exists' => $localExists,
                    'edit_url' => get_edit_post_link($attachment->ID, 'raw'),
                ];
            }

            // On cloud but NOT marked as migrated
            if (!$isMigrated && $inCloud) {
                $notMarked[] = [
                    'id' => (int) $attachment->ID,
                    'title' => $attachment->post_title,
                    'file' => $attachment->file_path,
                    'storage_key' => $cloudFiles[$attachment->file_path]['storage_key'] ?? '',
                    'edit_url' => get_edit_post_link($attachment->ID, 'raw'),
                ];
            }
        }

        // Orphan files on cloud (no corresponding WP attachment)
        $orphans = [];
        foreach ($cloudFiles as $filePath => $cloudData) {
            if (!isset($matchedCloudFiles[$filePath])) {
                $orphans[] = [
                    'file' => $filePath,
                    'storage_key' => $cloudData['storage_key'],
                    'size' => size_format((int) $cloudData['size']),
                    'url' => $this->settings->get_file_url($cloudData['storage_key']),
                ];
            }
        }

        return [
            'not_on_cloud' => array_slice($notOnCloud, 0, 100),
            'not_on_cloud_total' => count($notOnCloud),
            'not_marked' => array_slice($notMarked, 0, 100),
            'not_marked_total' => count($notMarked),
            'orphans' => array_slice($orphans, 0, 100),
            'orphans_total' => count($orphans),
            'summary' => [
                'cloud_files_scanned' => count($cloudFiles),
                'wp_attachments' => count($attachments),
                'matched' => count($matchedCloudFiles),
            ],
        ];
    }

    // ==================== AJAX Handlers ====================

    protected function get_start_options_from_request(): array
    {
        return [
            'batch_size' => isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 25,
            'mode' => isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : self::MODE_SYNC,
            'remove_local' => isset($_POST['remove_local']) && $_POST['remove_local'] === 'true',
            'auto_fix' => isset($_POST['auto_fix']) && $_POST['auto_fix'] === 'true',
        ];
    }

    public function ajax_analyze(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $deep = isset($_POST['deep']) && $_POST['deep'] === 'true';

        try {
            $status = $deep ? $this->analyzeDeep() : $this->analyze();
            wp_send_json_success($status->toArray());
        } catch (\Throwable $e) {
            $this->logger->error('cloudsync', 'Error in ajax_analyze: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error analyzing: ' . $e->getMessage()]);
        }
    }

    public function ajax_get_discrepancies(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        try {
            $discrepancies = $this->get_discrepancies();
            wp_send_json_success($discrepancies);
        } catch (\Throwable $e) {
            $this->logger->error('cloudsync', 'Error getting discrepancies: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function ajax_fix_integrity(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Start integrity check with auto-fix
        $options = [
            'batch_size' => 50,
            'mode' => self::MODE_INTEGRITY,
            'auto_fix' => true,
        ];

        try {
            $state = $this->start($options);
            wp_send_json_success(['state' => $state]);
        } catch (\Throwable $e) {
            $this->logger->error('cloudsync', 'Error starting integrity fix: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

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
}

