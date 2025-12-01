<?php
/**
 * Tools page template - Migration, Stats Sync, Cache Sync
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$settings = $plugin->get_settings();
$is_configured = $settings && $settings->is_configured();

$stats = new \Metodo\MediaToolkit\Stats\Stats(
    $plugin->get_logger(),
    $plugin->get_history(),
    $settings
);
$migration_stats = $stats->get_migration_stats();
$dashboard_stats = $stats->get_dashboard_stats();

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'migration';
$s3_stats = $settings ? $settings->get_cached_s3_stats() : null;
$sync_interval = $settings ? $settings->get_s3_sync_interval() : 24;
$cache_control = $settings ? $settings->get_cache_control_max_age() : 31536000;
?>

<div class="wrap mds-wrap">
    <div class="mds-page">
        <header class="mds-page-header">
            <h1 class="mds-page-title">
                <span class="mds-logo"><span class="dashicons dashicons-admin-tools"></span></span>
                <?php esc_html_e('Tools', 'media-toolkit'); ?>
            </h1>
            <p class="mds-description"><?php esc_html_e('Migration and maintenance tools for your media files.', 'media-toolkit'); ?></p>
        </header>

        <!-- Tab Navigation -->
        <nav class="mds-tabs-nav">
        <a href="?page=media-toolkit-tools&tab=migration" class="mds-tab-link <?php echo $active_tab === 'migration' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Migration', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-tools&tab=stats-sync" class="mds-tab-link <?php echo $active_tab === 'stats-sync' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Stats Sync', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-tools&tab=cache-sync" class="mds-tab-link <?php echo $active_tab === 'cache-sync' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-database"></span>
            <?php esc_html_e('Cache Headers', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-tools&tab=reconciliation" class="mds-tab-link <?php echo $active_tab === 'reconciliation' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e('Reconciliation', 'media-toolkit'); ?>
        </a>
    </nav>

    <?php if (!$is_configured): ?>
    <div class="mds-alert mds-alert-error">
        <span class="dashicons dashicons-warning"></span>
        <div>
            <strong><?php esc_html_e('S3 Offload is not configured.', 'media-toolkit'); ?></strong>
            <p><?php printf(esc_html__('Please %sconfigure your S3 settings%s before using these tools.', 'media-toolkit'), '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings')) . '">', '</a>'); ?></p>
        </div>
    </div>
    <?php else: ?>

    <div class="mds-tab-content">
        <?php if ($active_tab === 'migration'): ?>
            <!-- ==================== MIGRATION TAB ==================== -->
            
            <div class="mds-stats-grid">
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-primary">
                        <span class="dashicons dashicons-format-gallery"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="stat-total"><?php echo esc_html($migration_stats['total_attachments']); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Total Files', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="stat-migrated"><?php echo esc_html($migration_stats['migrated_attachments']); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Migrated', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-warning">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="stat-pending"><?php echo esc_html($migration_stats['pending_attachments']); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-info">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="stat-size"><?php echo esc_html($migration_stats['pending_size_formatted']); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Pending Size', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e('Migration Progress', 'media-toolkit'); ?></h3>
                    <span class="mds-progress-badge" id="progress-percentage"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
                </div>
                <div class="mds-card-body">
                    <div class="mds-progress mds-progress-lg mds-progress-animated">
                        <div class="mds-progress-bar">
                            <div class="mds-progress-fill" id="migration-progress" style="width: <?php echo esc_attr($migration_stats['progress_percentage']); ?>%"></div>
                        </div>
                        <span class="mds-progress-percent"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
                    </div>
                </div>
            </div>

            <div class="mds-cards-grid">
                <!-- Options -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Migration Options', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <div class="mds-form-group">
                            <label for="batch-size" class="mds-label"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                            <select id="batch-size" class="mds-select">
                                <option value="10"><?php esc_html_e('10 files per batch', 'media-toolkit'); ?></option>
                                <option value="25" selected><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                                <option value="50"><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                                <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                            </select>
                            <span class="mds-help"><?php esc_html_e('Smaller batches are safer but slower', 'media-toolkit'); ?></span>
                        </div>
                        
                        <div class="mds-form-group" style="margin-top: 20px;">
                            <label class="mds-toggle">
                                <input type="checkbox" id="remove-local">
                                <span class="mds-toggle-slider"></span>
                                <span class="mds-toggle-label"><strong><?php esc_html_e('Delete local files after migration', 'media-toolkit'); ?></strong></span>
                            </label>
                            <p class="mds-help" style="margin-left: 52px;">
                                <span class="mds-badge mds-badge-warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></span>
                                <?php esc_html_e('This will permanently delete local copies!', 'media-toolkit'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Controls', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <div class="mds-actions" style="flex-direction: column; gap: 12px;">
                            <button type="button" class="mds-btn mds-btn-primary mds-btn-lg" id="btn-start-migration" style="width: 100%;">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e('Start Migration', 'media-toolkit'); ?>
                            </button>
                            
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="mds-btn mds-btn-secondary" id="btn-pause-migration" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Pause', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="mds-btn mds-btn-secondary" id="btn-resume-migration" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Resume', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="mds-btn mds-btn-danger" id="btn-stop-migration" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="migration-status" style="display: none; margin-top: 16px;">
                            <div class="mds-sync-details">
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Current file', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value mds-file-path" id="current-file">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value">
                                        <span id="processed-count">0</span> / <span id="total-count">0</span>
                                        <span class="mds-badge mds-badge-error" style="margin-left: 8px; display: none;" id="failed-badge">
                                            <span id="failed-count">0</span> <?php esc_html_e('failed', 'media-toolkit'); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Migration Log -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Migration Log', 'media-toolkit'); ?></h3>
                    <button type="button" class="mds-btn mds-btn-secondary mds-btn-sm" id="btn-retry-failed" disabled>
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Retry Failed', 'media-toolkit'); ?>
                    </button>
                </div>
                <div class="mds-card-body" style="padding: 0;">
                    <div class="mds-terminal">
                        <div class="mds-terminal-header">
                            <div class="mds-terminal-dots">
                                <span class="mds-terminal-dot mds-terminal-dot-red"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-yellow"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-green"></span>
                            </div>
                            <span class="mds-terminal-title"><?php esc_html_e('migration.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mds-terminal-body" id="migration-log">
                            <div class="mds-terminal-line">
                                <span class="mds-terminal-prompt">$</span>
                                <span class="mds-terminal-text mds-terminal-muted"><?php esc_html_e('Migration log will appear here...', 'media-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'stats-sync'): ?>
            <!-- ==================== STATS SYNC TAB ==================== -->
            
            <div class="mds-stats-grid mds-stats-grid-3">
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-primary">
                        <span class="dashicons dashicons-format-gallery"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value"><?php echo number_format($dashboard_stats['original_files'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Files on S3', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-info">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Total (with thumbnails)', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-success">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Storage Used', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>

            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-update"></span> <?php esc_html_e('S3 Statistics Sync', 'media-toolkit'); ?></h3>
                </div>
                <div class="mds-card-body">
                    <p class="mds-text-secondary"><?php esc_html_e('Sync statistics from S3 to get accurate file count and storage usage for the current environment.', 'media-toolkit'); ?></p>
                    
                    <?php if ($s3_stats): ?>
                    <div class="mds-sync-details" style="margin-top: 20px;">
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Last sync', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value"><?php echo esc_html($s3_stats['synced_at'] ?? 'Never'); ?></span>
                        </div>
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Original files', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value"><?php echo number_format($s3_stats['original_files'] ?? $s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Total files', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value"><?php echo number_format($s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Storage', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value"><?php echo size_format($s3_stats['size'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mds-form-group" style="margin-top: 20px; max-width: 300px;">
                        <label for="s3_sync_interval" class="mds-label"><?php esc_html_e('Auto Sync Interval', 'media-toolkit'); ?></label>
                        <select name="s3_sync_interval" id="s3_sync_interval" class="mds-select">
                            <option value="0" <?php selected($sync_interval, 0); ?>><?php esc_html_e('Disabled', 'media-toolkit'); ?></option>
                            <option value="1" <?php selected($sync_interval, 1); ?>><?php esc_html_e('Every hour', 'media-toolkit'); ?></option>
                            <option value="6" <?php selected($sync_interval, 6); ?>><?php esc_html_e('Every 6 hours', 'media-toolkit'); ?></option>
                            <option value="12" <?php selected($sync_interval, 12); ?>><?php esc_html_e('Every 12 hours', 'media-toolkit'); ?></option>
                            <option value="24" <?php selected($sync_interval, 24); ?>><?php esc_html_e('Daily (recommended)', 'media-toolkit'); ?></option>
                            <option value="168" <?php selected($sync_interval, 168); ?>><?php esc_html_e('Weekly', 'media-toolkit'); ?></option>
                        </select>
                        <span class="mds-help"><?php esc_html_e('How often to automatically query S3 for statistics.', 'media-toolkit'); ?></span>
                    </div>
                    
                    <div class="mds-actions" style="margin-top: 24px;">
                        <button type="button" class="mds-btn mds-btn-primary mds-btn-lg" id="btn-sync-stats">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Sync Now', 'media-toolkit'); ?>
                        </button>
                        <span id="sync-status" class="mds-text-secondary"></span>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'cache-sync'): ?>
            <!-- ==================== CACHE SYNC TAB ==================== -->
            
            <div class="mds-stats-grid mds-stats-grid-3">
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-primary">
                        <span class="dashicons dashicons-format-gallery"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="cache-total-files"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Files to Update', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="cache-processed-files">0</span>
                        <span class="mds-stat-label"><?php esc_html_e('Processed', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card mds-stat-card-lg">
                    <div class="mds-stat-icon mds-stat-icon-error">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="cache-failed-files">0</span>
                        <span class="mds-stat-label"><?php esc_html_e('Failed', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e('Cache Headers Update Progress', 'media-toolkit'); ?></h3>
                    <span class="mds-progress-badge" id="cache-progress-percentage">0%</span>
                </div>
                <div class="mds-card-body">
                    <div class="mds-progress mds-progress-lg mds-progress-animated">
                        <div class="mds-progress-bar">
                            <div class="mds-progress-fill" id="cache-progress-bar" style="width: 0%"></div>
                        </div>
                        <span class="mds-progress-percent">0%</span>
                    </div>
                </div>
            </div>

            <div class="mds-cards-grid">
                <!-- Options -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Cache-Control Settings', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <p class="mds-text-secondary"><?php esc_html_e('Update Cache-Control headers on all files already uploaded to S3 for the current environment.', 'media-toolkit'); ?></p>
                        
                        <div class="mds-form-group" style="margin-top: 16px;">
                            <label for="cache_control_value" class="mds-label"><?php esc_html_e('Cache-Control Value', 'media-toolkit'); ?></label>
                            <select id="cache_control_value" class="mds-select">
                                <option value="0" <?php selected($cache_control, 0); ?>><?php esc_html_e('No cache (no-cache, no-store)', 'media-toolkit'); ?></option>
                                <option value="86400" <?php selected($cache_control, 86400); ?>><?php esc_html_e('1 day', 'media-toolkit'); ?></option>
                                <option value="604800" <?php selected($cache_control, 604800); ?>><?php esc_html_e('1 week', 'media-toolkit'); ?></option>
                                <option value="2592000" <?php selected($cache_control, 2592000); ?>><?php esc_html_e('1 month', 'media-toolkit'); ?></option>
                                <option value="31536000" <?php selected($cache_control, 31536000); ?>><?php esc_html_e('1 year — Recommended', 'media-toolkit'); ?></option>
                            </select>
                            <span class="mds-help"><?php esc_html_e('This will be applied to all existing files on S3.', 'media-toolkit'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Controls', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <div class="mds-actions" style="flex-direction: column; gap: 12px;">
                            <button type="button" class="mds-btn mds-btn-primary mds-btn-lg" id="btn-start-cache-sync" style="width: 100%;">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e('Start Cache Update', 'media-toolkit'); ?>
                            </button>
                            <button type="button" class="mds-btn mds-btn-danger" id="btn-cancel-cache-sync" style="display: none; width: 100%;">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                            </button>
                        </div>
                        
                        <div id="cache-sync-status" style="display: none; margin-top: 16px;">
                            <div class="mds-sync-details">
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="cache-status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value">
                                        <span id="cache-current-count">0</span> / <span id="cache-total-count">0</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cache Sync Log -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Update Log', 'media-toolkit'); ?></h3>
                </div>
                <div class="mds-card-body" style="padding: 0;">
                    <div class="mds-terminal">
                        <div class="mds-terminal-header">
                            <div class="mds-terminal-dots">
                                <span class="mds-terminal-dot mds-terminal-dot-red"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-yellow"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-green"></span>
                            </div>
                            <span class="mds-terminal-title"><?php esc_html_e('cache-sync.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mds-terminal-body" id="cache-sync-log">
                            <div class="mds-terminal-line">
                                <span class="mds-terminal-prompt">$</span>
                                <span class="mds-terminal-text mds-terminal-muted"><?php esc_html_e('Cache update log will appear here...', 'media-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'reconciliation'): ?>
            <!-- ==================== RECONCILIATION TAB ==================== -->
            <?php
            $reconciliation_stats = [];
            if ($is_configured) {
                $reconciliation = $plugin->get_reconciliation();
                $reconciliation_stats = $reconciliation ? $reconciliation->get_stats() : [];
            }
            ?>
            
            <div class="mds-alert mds-alert-info">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong><?php esc_html_e('What is Reconciliation?', 'media-toolkit'); ?></strong>
                    <p><?php esc_html_e('This tool compares files on S3 with WordPress attachments and syncs the metadata. Use it when files were uploaded to S3 before the plugin was installed, or when the sync status appears incorrect.', 'media-toolkit'); ?></p>
                </div>
            </div>

            <div class="mds-stats-grid">
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-primary">
                        <span class="dashicons dashicons-format-gallery"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="recon-wp-attachments"><?php echo number_format($reconciliation_stats['total_attachments'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('WP Attachments', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="recon-marked"><?php echo number_format($reconciliation_stats['marked_migrated'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Marked as Migrated', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-info">
                        <span class="dashicons dashicons-cloud-upload"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="recon-s3-files"><?php echo number_format($reconciliation_stats['s3_original_files'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('S3 Original Files', 'media-toolkit'); ?></span>
                    </div>
                </div>
                
                <div class="mds-stat-card <?php echo ($reconciliation_stats['has_discrepancy'] ?? false) ? 'mds-stat-card-warning' : ''; ?>">
                    <div class="mds-stat-icon mds-stat-icon-warning">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value" id="recon-discrepancy"><?php echo number_format($reconciliation_stats['discrepancy'] ?? 0); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Discrepancy', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e('Reconciliation Progress', 'media-toolkit'); ?></h3>
                    <span class="mds-progress-badge" id="recon-progress-percentage"><?php echo esc_html($reconciliation_stats['progress_percentage'] ?? 0); ?>%</span>
                </div>
                <div class="mds-card-body">
                    <div class="mds-progress mds-progress-lg mds-progress-animated">
                        <div class="mds-progress-bar">
                            <div class="mds-progress-fill" id="recon-progress-bar" style="width: <?php echo esc_attr($reconciliation_stats['progress_percentage'] ?? 0); ?>%"></div>
                        </div>
                        <span class="mds-progress-percent"><?php echo esc_html($reconciliation_stats['progress_percentage'] ?? 0); ?>%</span>
                    </div>
                </div>
            </div>

            <div class="mds-cards-grid">
                <!-- Scan Preview -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-search"></span> <?php esc_html_e('Scan Preview', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <p class="mds-text-secondary"><?php esc_html_e('Scan S3 to see how many files match WordPress attachments before running reconciliation.', 'media-toolkit'); ?></p>
                        
                        <div class="mds-actions" style="margin-top: 16px;">
                            <button type="button" class="mds-btn mds-btn-secondary" id="btn-scan-s3">
                                <span class="dashicons dashicons-search"></span>
                                <?php esc_html_e('Scan S3', 'media-toolkit'); ?>
                            </button>
                        </div>
                        
                        <div id="scan-results" style="display: none; margin-top: 16px;">
                            <div class="mds-sync-details">
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('S3 Original Files', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="scan-s3-files">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('WordPress Attachments', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="scan-wp-files">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Matching Files', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="scan-matches">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Not Found on S3', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="scan-not-found">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Would be Marked', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" style="color: var(--mds-success);" id="scan-would-mark">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3><span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Controls', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="mds-card-body">
                        <div class="mds-form-group">
                            <label for="recon-batch-size" class="mds-label"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                            <select id="recon-batch-size" class="mds-select">
                                <option value="25"><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                                <option value="50" selected><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                                <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                            </select>
                        </div>
                        
                        <div class="mds-actions" style="flex-direction: column; gap: 12px; margin-top: 20px;">
                            <button type="button" class="mds-btn mds-btn-primary mds-btn-lg" id="btn-start-reconciliation" style="width: 100%;">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php esc_html_e('Start Reconciliation', 'media-toolkit'); ?>
                            </button>
                            
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="mds-btn mds-btn-secondary" id="btn-pause-reconciliation" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Pause', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="mds-btn mds-btn-secondary" id="btn-resume-reconciliation" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Resume', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="mds-btn mds-btn-danger" id="btn-stop-reconciliation" disabled style="flex: 1;">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="recon-status" style="display: none; margin-top: 16px;">
                            <div class="mds-sync-details">
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value" id="recon-status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Current file', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value mds-file-path" id="recon-current-file">-</span>
                                </div>
                                <div class="mds-sync-detail-item">
                                    <span class="mds-sync-detail-label"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                    <span class="mds-sync-detail-value">
                                        <span id="recon-processed-count">0</span> / <span id="recon-total-count">0</span>
                                        <span class="mds-badge mds-badge-success" style="margin-left: 8px;" id="recon-found-badge">
                                            <span id="recon-found-count">0</span> <?php esc_html_e('found', 'media-toolkit'); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Log -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3><span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Reconciliation Log', 'media-toolkit'); ?></h3>
                    <button type="button" class="mds-btn mds-btn-ghost mds-btn-sm" id="btn-clear-metadata" title="<?php esc_attr_e('Clear all migration metadata', 'media-toolkit'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Reset Metadata', 'media-toolkit'); ?>
                    </button>
                </div>
                <div class="mds-card-body" style="padding: 0;">
                    <div class="mds-terminal">
                        <div class="mds-terminal-header">
                            <div class="mds-terminal-dots">
                                <span class="mds-terminal-dot mds-terminal-dot-red"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-yellow"></span>
                                <span class="mds-terminal-dot mds-terminal-dot-green"></span>
                            </div>
                            <span class="mds-terminal-title"><?php esc_html_e('reconciliation.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mds-terminal-body" id="recon-log">
                            <div class="mds-terminal-line">
                                <span class="mds-terminal-prompt">$</span>
                                <span class="mds-terminal-text mds-terminal-muted"><?php esc_html_e('Reconciliation log will appear here...', 'media-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <?php endif; ?>

        <footer class="mds-footer">
            <p>
                <?php
                printf(
                    esc_html__('Developed by %s', 'media-toolkit'),
                    '<a href="https://metodo.dev" target="_blank" rel="noopener">Michele Marri - Metodo.dev</a>'
                );
                ?>
                &bull;
                <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
            </p>
        </footer>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="mds-modal-overlay" style="display:none;">
    <div class="mds-modal">
        <div class="mds-modal-header">
            <h3 class="mds-modal-title" id="confirm-title"><?php esc_html_e('Confirm Action', 'media-toolkit'); ?></h3>
            <button type="button" class="mds-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="mds-modal-body">
            <p id="confirm-message"></p>
        </div>
        <div class="mds-modal-footer">
            <button type="button" class="mds-btn mds-btn-ghost" id="btn-confirm-no"><?php esc_html_e('Cancel', 'media-toolkit'); ?></button>
            <button type="button" class="mds-btn mds-btn-primary" id="btn-confirm-yes"><?php esc_html_e('Yes, Continue', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>
