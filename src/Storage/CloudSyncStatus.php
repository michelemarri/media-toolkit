<?php
/**
 * CloudSync Status DTO
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

/**
 * Data Transfer Object for CloudSync status
 * Contains all information about the current sync state
 */
final class CloudSyncStatus
{
    public function __construct(
        public int $totalAttachments = 0,
        public int $migratedToCloud = 0,
        public int $pendingMigration = 0,
        public int $cloudFilesCount = 0,
        public int $integrityIssues = 0,
        public int $orphanCloudFiles = 0,
        public int $localFilesAvailable = 0,
        public bool $removeLocalEnabled = false,
        public ?string $lastSyncAt = null,
        public array $suggestedActions = [],
        public string $overallStatus = 'unknown',
        public int $syncPercentage = 0,
        // Optimization stats
        public int $totalImages = 0,
        public int $optimizedImages = 0,
        public int $pendingOptimization = 0,
        public int $optimizationPercentage = 0,
        public int $totalBytesSaved = 0,
        public float $averageSavingsPercent = 0,
    ) {}

    /**
     * Create status from analysis data
     */
    public static function fromAnalysis(array $data): self
    {
        $totalAttachments = $data['total_attachments'] ?? 0;
        $migratedToCloud = $data['migrated_to_cloud'] ?? 0;
        $pendingMigration = $data['pending_migration'] ?? 0;
        $cloudFilesCount = $data['cloud_files_count'] ?? 0;
        $integrityIssues = $data['integrity_issues'] ?? 0;
        $orphanCloudFiles = $data['orphan_cloud_files'] ?? 0;
        $localFilesAvailable = $data['local_files_available'] ?? 0;
        $removeLocalEnabled = $data['remove_local_enabled'] ?? false;
        $lastSyncAt = $data['last_sync_at'] ?? null;

        // Optimization stats
        $totalImages = $data['total_images'] ?? 0;
        $optimizedImages = $data['optimized_images'] ?? 0;
        $pendingOptimization = $data['pending_optimization'] ?? 0;
        $optimizationPercentage = $data['optimization_percentage'] ?? 0;
        $totalBytesSaved = $data['total_bytes_saved'] ?? 0;
        $averageSavingsPercent = $data['average_savings_percent'] ?? 0;

        // Calculate sync percentage
        $syncPercentage = $totalAttachments > 0
            ? (int) round(($migratedToCloud / $totalAttachments) * 100)
            : 0;

        // Determine overall status
        $overallStatus = self::determineOverallStatus(
            $totalAttachments,
            $migratedToCloud,
            $pendingMigration,
            $integrityIssues
        );

        // Generate suggested actions
        $suggestedActions = self::generateSuggestedActions(
            $pendingMigration,
            $integrityIssues,
            $orphanCloudFiles,
            $localFilesAvailable,
            $pendingOptimization
        );

        return new self(
            totalAttachments: $totalAttachments,
            migratedToCloud: $migratedToCloud,
            pendingMigration: $pendingMigration,
            cloudFilesCount: $cloudFilesCount,
            integrityIssues: $integrityIssues,
            orphanCloudFiles: $orphanCloudFiles,
            localFilesAvailable: $localFilesAvailable,
            removeLocalEnabled: $removeLocalEnabled,
            lastSyncAt: $lastSyncAt,
            suggestedActions: $suggestedActions,
            overallStatus: $overallStatus,
            syncPercentage: $syncPercentage,
            totalImages: $totalImages,
            optimizedImages: $optimizedImages,
            pendingOptimization: $pendingOptimization,
            optimizationPercentage: $optimizationPercentage,
            totalBytesSaved: $totalBytesSaved,
            averageSavingsPercent: $averageSavingsPercent,
        );
    }

    /**
     * Determine overall sync status
     */
    private static function determineOverallStatus(
        int $totalAttachments,
        int $migratedToCloud,
        int $pendingMigration,
        int $integrityIssues
    ): string {
        if ($integrityIssues > 0) {
            return 'integrity_issues';
        }

        if ($pendingMigration > 0) {
            return 'pending_sync';
        }

        if ($totalAttachments > 0 && $migratedToCloud === $totalAttachments) {
            return 'synced';
        }

        if ($migratedToCloud === 0) {
            return 'not_started';
        }

        return 'partial';
    }

    /**
     * Generate suggested actions based on current status
     */
    private static function generateSuggestedActions(
        int $pendingMigration,
        int $integrityIssues,
        int $orphanCloudFiles,
        int $localFilesAvailable,
        int $pendingOptimization = 0
    ): array {
        $actions = [];

        // Priority 1: Integrity issues
        if ($integrityIssues > 0) {
            $actions[] = [
                'type' => 'integrity_fix',
                'priority' => 'high',
                'title' => 'Fix integrity issues',
                'description' => sprintf(
                    '%d files marked as migrated but not found on cloud storage.',
                    $integrityIssues
                ),
                'count' => $integrityIssues,
                'can_auto_fix' => $localFilesAvailable > 0,
            ];
        }

        // Priority 2: Optimize before sync (only if there are pending files to sync AND unoptimized images)
        if ($pendingMigration > 0 && $pendingOptimization > 0) {
            $actions[] = [
                'type' => 'optimize_before_sync',
                'priority' => 'high',
                'title' => 'Optimize images before sync',
                'description' => sprintf(
                    '%d images not optimized. Optimize them before uploading to save bandwidth and storage costs.',
                    $pendingOptimization
                ),
                'count' => $pendingOptimization,
                'can_auto_fix' => true,
                'action_url' => admin_url('admin.php?page=media-toolkit-optimize'),
            ];
        }

        // Priority 3: Pending migration
        if ($pendingMigration > 0) {
            $actions[] = [
                'type' => 'sync',
                'priority' => 'medium',
                'title' => 'Sync pending files',
                'description' => sprintf(
                    '%d files waiting to be uploaded to cloud storage.',
                    $pendingMigration
                ),
                'count' => $pendingMigration,
                'can_auto_fix' => true,
            ];
        }

        // Priority 4: Orphan files on cloud
        if ($orphanCloudFiles > 0) {
            $actions[] = [
                'type' => 'cleanup_orphans',
                'priority' => 'low',
                'title' => 'Clean up orphan files',
                'description' => sprintf(
                    '%d files on cloud storage without matching WordPress attachment.',
                    $orphanCloudFiles
                ),
                'count' => $orphanCloudFiles,
                'can_auto_fix' => true,
            ];
        }

        return $actions;
    }

    /**
     * Check if everything is synced
     */
    public function isSynced(): bool
    {
        return $this->overallStatus === 'synced';
    }

    /**
     * Check if there are critical issues
     */
    public function hasIssues(): bool
    {
        return $this->integrityIssues > 0;
    }

    /**
     * Check if there's work to do
     */
    public function hasPendingWork(): bool
    {
        return $this->pendingMigration > 0 || $this->integrityIssues > 0;
    }

    /**
     * Get primary action (highest priority)
     */
    public function getPrimaryAction(): ?array
    {
        return $this->suggestedActions[0] ?? null;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'total_attachments' => $this->totalAttachments,
            'migrated_to_cloud' => $this->migratedToCloud,
            'pending_migration' => $this->pendingMigration,
            'cloud_files_count' => $this->cloudFilesCount,
            'integrity_issues' => $this->integrityIssues,
            'orphan_cloud_files' => $this->orphanCloudFiles,
            'local_files_available' => $this->localFilesAvailable,
            'remove_local_enabled' => $this->removeLocalEnabled,
            'last_sync_at' => $this->lastSyncAt,
            'suggested_actions' => $this->suggestedActions,
            'overall_status' => $this->overallStatus,
            'sync_percentage' => $this->syncPercentage,
            // Optimization stats
            'total_images' => $this->totalImages,
            'optimized_images' => $this->optimizedImages,
            'pending_optimization' => $this->pendingOptimization,
            'optimization_percentage' => $this->optimizationPercentage,
            'total_bytes_saved' => $this->totalBytesSaved,
            'total_bytes_saved_formatted' => size_format($this->totalBytesSaved),
            'average_savings_percent' => $this->averageSavingsPercent,
        ];
    }
}

