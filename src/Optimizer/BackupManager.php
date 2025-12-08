<?php
/**
 * Backup Manager
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;

/**
 * Manages backup of original images before optimization
 * 
 * Backup files are stored with _original suffix in the same directory:
 * - photo.jpg (optimized)
 * - photo_original.jpg (backup)
 */
final class BackupManager
{
    private const META_KEY = '_media_toolkit_backup';
    private const SETTINGS_KEY = 'media_toolkit_backup_settings';

    private ?Logger $logger;
    private ?Settings $settings;
    private ?StorageInterface $storage;
    private ?History $history;

    public function __construct(
        ?Logger $logger = null,
        ?Settings $settings = null,
        ?StorageInterface $storage = null,
        ?History $history = null
    ) {
        $this->logger = $logger;
        $this->settings = $settings;
        $this->storage = $storage;
        $this->history = $history;
    }

    /**
     * Get backup settings
     *
     * @return array{enabled: bool, auto_cleanup: bool, cleanup_days: int}
     */
    public function getSettings(): array
    {
        $defaults = [
            'enabled' => false,
            'auto_cleanup' => false,
            'cleanup_days' => 0, // 0 = never cleanup
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /**
     * Save backup settings
     */
    public function saveSettings(array $settings): bool
    {
        $sanitized = [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'auto_cleanup' => (bool) ($settings['auto_cleanup'] ?? false),
            'cleanup_days' => max(0, (int) ($settings['cleanup_days'] ?? 0)),
        ];

        return update_option(self::SETTINGS_KEY, $sanitized);
    }

    /**
     * Check if backup is enabled
     */
    public function isEnabled(): bool
    {
        return $this->getSettings()['enabled'];
    }

    /**
     * Create backup of original file before optimization
     *
     * @param int $attachmentId Attachment ID
     * @param string $filePath Path to the file to backup
     * @return array{success: bool, backup_path?: string, backup_key?: string, error?: string}
     */
    public function createBackup(int $attachmentId, string $filePath): array
    {
        if (!$this->isEnabled()) {
            return ['success' => true]; // Backup disabled, skip silently
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Source file does not exist',
            ];
        }

        // Check if backup already exists
        $existingBackup = $this->getBackupInfo($attachmentId);
        if ($existingBackup !== null) {
            $this->log('info', "Backup already exists for attachment {$attachmentId}, skipping");
            return [
                'success' => true,
                'backup_path' => $existingBackup['local_path'] ?? null,
                'backup_key' => $existingBackup['storage_key'] ?? null,
            ];
        }

        // Generate backup path
        $backupPath = $this->generateBackupPath($filePath);
        
        // Create local backup
        if (!copy($filePath, $backupPath)) {
            return [
                'success' => false,
                'error' => 'Failed to create local backup copy',
            ];
        }

        $originalSize = filesize($filePath);

        // Prepare backup metadata
        $backupMeta = [
            'local_path' => $backupPath,
            'original_size' => $originalSize,
            'backup_date' => current_time('mysql'),
            'storage_key' => null,
        ];

        // Upload to cloud storage if configured and file is on cloud
        $s3Key = get_post_meta($attachmentId, '_media_toolkit_key', true);
        if (!empty($s3Key) && $this->storage !== null) {
            $backupKey = $this->generateBackupKey($s3Key);
            
            $uploadResult = $this->storage->upload_file($backupPath, $attachmentId);
            
            if ($uploadResult->success) {
                $backupMeta['storage_key'] = $backupKey;
                $this->log('success', "Backup uploaded to cloud: {$backupKey}");
            } else {
                $this->log('warning', "Failed to upload backup to cloud, keeping local only");
            }
        }

        // Save backup metadata
        update_post_meta($attachmentId, self::META_KEY, $backupMeta);

        // Record in history
        $this->history?->record(
            HistoryAction::BACKUP_CREATED,
            $attachmentId,
            $filePath,
            $backupMeta['storage_key'],
            $originalSize,
            [
                'backup_path' => $backupPath,
            ]
        );

        $this->log('success', "Backup created for attachment {$attachmentId}");

        return [
            'success' => true,
            'backup_path' => $backupPath,
            'backup_key' => $backupMeta['storage_key'],
        ];
    }

    /**
     * Restore original file from backup
     *
     * @param int $attachmentId Attachment ID
     * @return array{success: bool, error?: string}
     */
    public function restoreBackup(int $attachmentId): array
    {
        $backupInfo = $this->getBackupInfo($attachmentId);
        
        if ($backupInfo === null) {
            return [
                'success' => false,
                'error' => 'No backup found for this attachment',
            ];
        }

        $originalFile = get_attached_file($attachmentId);
        if ($originalFile === false || $originalFile === '') {
            return [
                'success' => false,
                'error' => 'Cannot determine original file path',
            ];
        }

        $backupPath = $backupInfo['local_path'] ?? null;
        $backupKey = $backupInfo['storage_key'] ?? null;

        // Try to get backup from cloud if local doesn't exist
        if (($backupPath === null || !file_exists($backupPath)) && $backupKey !== null && $this->storage !== null) {
            $backupPath = $this->generateBackupPath($originalFile);
            
            $downloaded = $this->storage->download_file($backupKey, $backupPath, $attachmentId);
            if (!$downloaded) {
                return [
                    'success' => false,
                    'error' => 'Failed to download backup from cloud storage',
                ];
            }
        }

        if ($backupPath === null || !file_exists($backupPath)) {
            return [
                'success' => false,
                'error' => 'Backup file not found',
            ];
        }

        // Restore: copy backup over optimized file
        if (!copy($backupPath, $originalFile)) {
            return [
                'success' => false,
                'error' => 'Failed to restore backup',
            ];
        }

        // Re-upload to cloud if needed
        $s3Key = get_post_meta($attachmentId, '_media_toolkit_key', true);
        if (!empty($s3Key) && $this->storage !== null) {
            $uploadResult = $this->storage->upload_file($originalFile, $attachmentId);
            
            if (!$uploadResult->success) {
                $this->log('warning', "Restored locally but failed to re-upload to cloud");
            }
        }

        // Delete backup after successful restore
        $this->deleteBackup($attachmentId);

        // Update optimization table to pending
        \Metodo\MediaToolkit\Database\OptimizationTable::update_status($attachmentId, 'pending');

        // Record in history
        $this->history?->record(
            HistoryAction::BACKUP_RESTORED,
            $attachmentId,
            $originalFile,
            $s3Key,
            $backupInfo['original_size'] ?? 0,
            []
        );

        $this->log('success', "Backup restored for attachment {$attachmentId}");

        return ['success' => true];
    }

    /**
     * Delete backup for an attachment
     *
     * @param int $attachmentId Attachment ID
     * @return bool Success
     */
    public function deleteBackup(int $attachmentId): bool
    {
        $backupInfo = $this->getBackupInfo($attachmentId);
        
        if ($backupInfo === null) {
            return true; // Nothing to delete
        }

        // Delete local backup
        $localPath = $backupInfo['local_path'] ?? null;
        if ($localPath !== null && file_exists($localPath)) {
            @unlink($localPath);
        }

        // Delete from cloud storage
        $storageKey = $backupInfo['storage_key'] ?? null;
        if ($storageKey !== null && $this->storage !== null) {
            $this->storage->delete_file($storageKey, $attachmentId);
        }

        // Remove metadata
        delete_post_meta($attachmentId, self::META_KEY);

        $this->log('info', "Backup deleted for attachment {$attachmentId}");

        return true;
    }

    /**
     * Check if backup exists for an attachment
     */
    public function hasBackup(int $attachmentId): bool
    {
        return $this->getBackupInfo($attachmentId) !== null;
    }

    /**
     * Get backup information for an attachment
     *
     * @return array{local_path: ?string, storage_key: ?string, original_size: int, backup_date: string}|null
     */
    public function getBackupInfo(int $attachmentId): ?array
    {
        $meta = get_post_meta($attachmentId, self::META_KEY, true);
        
        if (empty($meta) || !is_array($meta)) {
            return null;
        }

        return $meta;
    }

    /**
     * Generate backup file path from original path
     * photo.jpg -> photo_original.jpg
     */
    public function generateBackupPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        $extension = $pathInfo['extension'] ?? '';
        
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_original' . ($extension ? '.' . $extension : '');
    }

    /**
     * Generate backup storage key from original key
     * uploads/2024/01/photo.jpg -> uploads/2024/01/photo_original.jpg
     */
    public function generateBackupKey(string $originalKey): string
    {
        $pathInfo = pathinfo($originalKey);
        $extension = $pathInfo['extension'] ?? '';
        
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_original' . ($extension ? '.' . $extension : '');
    }

    /**
     * Cleanup old backups based on settings
     */
    public function cleanupOldBackups(): int
    {
        $settings = $this->getSettings();
        
        if (!$settings['auto_cleanup'] || $settings['cleanup_days'] <= 0) {
            return 0;
        }

        global $wpdb;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$settings['cleanup_days']} days"));

        // Find attachments with old backups
        $attachments = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND meta_value LIKE %s",
                self::META_KEY,
                '%' . $wpdb->esc_like('"backup_date"') . '%'
            )
        );

        $deleted = 0;

        foreach ($attachments as $attachmentId) {
            $backupInfo = $this->getBackupInfo((int) $attachmentId);
            
            if ($backupInfo === null) {
                continue;
            }

            $backupDate = $backupInfo['backup_date'] ?? null;
            if ($backupDate !== null && $backupDate < $cutoffDate) {
                if ($this->deleteBackup((int) $attachmentId)) {
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->log('info', "Cleaned up {$deleted} old backups");
        }

        return $deleted;
    }

    /**
     * Get statistics about backups
     *
     * @return array{total: int, total_size: int, oldest_date: ?string}
     */
    public function getStats(): array
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::META_KEY
            )
        );

        $totalSize = 0;
        $oldestDate = null;

        if ($count > 0) {
            $metas = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    self::META_KEY
                )
            );

            foreach ($metas as $meta) {
                $data = maybe_unserialize($meta);
                if (is_array($data)) {
                    $totalSize += $data['original_size'] ?? 0;
                    
                    $date = $data['backup_date'] ?? null;
                    if ($date !== null && ($oldestDate === null || $date < $oldestDate)) {
                        $oldestDate = $date;
                    }
                }
            }
        }

        return [
            'total' => $count,
            'total_size' => $totalSize,
            'oldest_date' => $oldestDate,
        ];
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        match ($level) {
            'info' => $this->logger->info('backup', $message),
            'success' => $this->logger->success('backup', $message),
            'warning' => $this->logger->warning('backup', $message),
            'error' => $this->logger->error('backup', $message),
            default => $this->logger->info('backup', $message),
        };
    }
}

