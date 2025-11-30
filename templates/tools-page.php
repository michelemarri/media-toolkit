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

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-tools">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
            <div>
                <h1>Tools</h1>
                <p class="s3-subtitle">Migration and maintenance tools</p>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="s3-tabs">
        <a href="?page=media-toolkit-tools&tab=migration" class="s3-tab <?php echo $active_tab === 'migration' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            Migration
        </a>
        <a href="?page=media-toolkit-tools&tab=stats-sync" class="s3-tab <?php echo $active_tab === 'stats-sync' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Stats Sync
        </a>
        <a href="?page=media-toolkit-tools&tab=cache-sync" class="s3-tab <?php echo $active_tab === 'cache-sync' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            Cache Headers
        </a>
        <a href="?page=media-toolkit-tools&tab=reconciliation" class="s3-tab <?php echo $active_tab === 'reconciliation' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Reconciliation
        </a>
    </div>

    <?php if (!$is_configured): ?>
        <div class="s3-alert s3-alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div>
                <strong>S3 Offload is not configured.</strong>
                <p>Please <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>">configure your S3 settings</a> before using these tools.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="s3-tab-content">
        <?php if ($active_tab === 'migration'): ?>
            <!-- ==================== MIGRATION TAB ==================== -->
            
            <!-- Stats Row -->
            <div class="s3-stats-row">
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-uploaded">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="stat-total"><?php echo esc_html($migration_stats['total_attachments']); ?></span>
                        <span class="s3-stat-label">Total Files</span>
                    </div>
                </div>
                
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-migrated">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="stat-migrated"><?php echo esc_html($migration_stats['migrated_attachments']); ?></span>
                        <span class="s3-stat-label">Migrated</span>
                    </div>
                </div>
                
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-edited">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="stat-pending"><?php echo esc_html($migration_stats['pending_attachments']); ?></span>
                        <span class="s3-stat-label">Pending</span>
                    </div>
                </div>
                
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-deleted">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="stat-size"><?php echo esc_html($migration_stats['pending_size_formatted']); ?></span>
                        <span class="s3-stat-label">Pending Size</span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        Migration Progress
                    </h3>
                    <span class="s3-progress-badge" id="progress-percentage"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
                </div>
                <div class="s3-card-body">
                    <div class="s3-progress-large">
                        <div class="s3-progress-track">
                            <div class="s3-progress-fill-animated" id="migration-progress" 
                                 style="width: <?php echo esc_attr($migration_stats['progress_percentage']); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="s3-grid-2">
                <!-- Options -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21 a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4"></path>
                            </svg>
                            Migration Options
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <div class="s3-form-group">
                            <label for="batch-size" class="s3-label">Batch Size</label>
                            <div class="s3-select-wrapper s3-select-full">
                                <select id="batch-size" class="s3-select">
                                    <option value="10">10 files per batch</option>
                                    <option value="25" selected>25 files per batch</option>
                                    <option value="50">50 files per batch</option>
                                    <option value="100">100 files per batch</option>
                                </select>
                            </div>
                            <span class="s3-help">Smaller batches are safer but slower</span>
                        </div>
                        
                        <div class="s3-checkbox-group" style="margin-top: 20px;">
                            <label class="s3-checkbox-label s3-checkbox-warning">
                                <input type="checkbox" id="remove-local">
                                <span class="s3-checkbox-box"></span>
                                <span class="s3-checkbox-text">
                                    <strong>Delete local files after migration</strong>
                                    <span>⚠️ This will permanently delete local copies!</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Controls
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <div class="s3-migration-controls">
                            <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-start-migration">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polygon points="10 8 16 12 10 16 10 8"></polygon>
                                </svg>
                                Start Migration
                            </button>
                            
                            <div class="s3-btn-group">
                                <button type="button" class="s3-btn s3-btn-secondary" id="btn-pause-migration" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="6" y="4" width="4" height="16"></rect>
                                        <rect x="14" y="4" width="4" height="16"></rect>
                                    </svg>
                                    Pause
                                </button>
                                
                                <button type="button" class="s3-btn s3-btn-secondary" id="btn-resume-migration" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                    Resume
                                </button>
                                
                                <button type="button" class="s3-btn s3-btn-danger" id="btn-stop-migration" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    Cancel
                                </button>
                            </div>
                        </div>
                        
                        <div class="s3-status-panel" id="migration-status" style="display: none;">
                            <div class="s3-status-item">
                                <span class="s3-status-label">Status</span>
                                <span class="s3-status-value" id="status-text">Idle</span>
                            </div>
                            <div class="s3-status-item">
                                <span class="s3-status-label">Current file</span>
                                <span class="s3-status-value s3-file-mono" id="current-file">-</span>
                            </div>
                            <div class="s3-status-item">
                                <span class="s3-status-label">Progress</span>
                                <span class="s3-status-value">
                                    <span id="processed-count">0</span> / <span id="total-count">0</span>
                                    <span class="s3-badge s3-badge-error" style="margin-left: 8px; display: none;" id="failed-badge">
                                        <span id="failed-count">0</span> failed
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Migration Log -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        Migration Log
                    </h3>
                    <button type="button" class="s3-btn s3-btn-secondary s3-btn-sm" id="btn-retry-failed" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        Retry Failed
                    </button>
                </div>
                <div class="s3-card-body s3-card-body-dark">
                    <div class="s3-terminal" id="migration-log">
                        <div class="s3-terminal-line s3-terminal-muted">
                            <span class="s3-terminal-prompt">$</span> Migration log will appear here...
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'stats-sync'): ?>
            <!-- ==================== STATS SYNC TAB ==================== -->
            
            <!-- Stats Row -->
            <div class="s3-stats-row s3-stats-row-3">
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-uploaded">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value"><?php echo number_format($dashboard_stats['original_files'] ?? 0); ?></span>
                        <span class="s3-stat-label">Files on S3</span>
                    </div>
                </div>
                
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-total">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                        <span class="s3-stat-label">Total (with thumbnails)</span>
                    </div>
                </div>
                
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-migrated">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                        <span class="s3-stat-label">Storage Used</span>
                    </div>
                </div>
            </div>

            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        S3 Statistics Sync
                    </h3>
                </div>
                <div class="s3-card-body">
                    <p class="s3-muted-text">Sync statistics from S3 to get accurate file count and storage usage for the current environment.</p>
                    
                    <?php if ($s3_stats): ?>
                    <div class="s3-sync-info-box">
                        <div class="s3-sync-info-item">
                            <span class="s3-sync-info-label">Last sync</span>
                            <span class="s3-sync-info-value"><?php echo esc_html($s3_stats['synced_at'] ?? 'Never'); ?></span>
                        </div>
                        <div class="s3-sync-info-item">
                            <span class="s3-sync-info-label">Original files</span>
                            <span class="s3-sync-info-value"><?php echo number_format($s3_stats['original_files'] ?? $s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="s3-sync-info-item">
                            <span class="s3-sync-info-label">Total files</span>
                            <span class="s3-sync-info-value"><?php echo number_format($s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="s3-sync-info-item">
                            <span class="s3-sync-info-label">Storage</span>
                            <span class="s3-sync-info-value"><?php echo size_format($s3_stats['size'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="s3-form-group" style="margin-top: 20px;">
                        <label for="s3_sync_interval" class="s3-label">Auto Sync Interval</label>
                        <div class="s3-select-wrapper s3-select-full" style="max-width: 300px;">
                            <select name="s3_sync_interval" id="s3_sync_interval" class="s3-select">
                                <option value="0" <?php selected($sync_interval, 0); ?>>Disabled</option>
                                <option value="1" <?php selected($sync_interval, 1); ?>>Every hour</option>
                                <option value="6" <?php selected($sync_interval, 6); ?>>Every 6 hours</option>
                                <option value="12" <?php selected($sync_interval, 12); ?>>Every 12 hours</option>
                                <option value="24" <?php selected($sync_interval, 24); ?>>Daily (recommended)</option>
                                <option value="168" <?php selected($sync_interval, 168); ?>>Weekly</option>
                            </select>
                        </div>
                        <span class="s3-help">How often to automatically query S3 for statistics.</span>
                    </div>
                    
                    <div class="s3-form-actions">
                        <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-sync-s3-stats">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            Sync Now
                        </button>
                        <span id="sync-status" class="s3-action-status"></span>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'cache-sync'): ?>
            <!-- ==================== CACHE SYNC TAB ==================== -->
            
            <!-- Stats Row -->
            <div class="s3-stats-row s3-stats-row-3">
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-total">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="cache-total-files"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                        <span class="s3-stat-label">Files to Update</span>
                    </div>
                </div>
                
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-migrated">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="cache-processed-files">0</span>
                        <span class="s3-stat-label">Processed</span>
                    </div>
                </div>
                
                <div class="s3-stat-card s3-stat-card-large">
                    <div class="s3-stat-icon s3-stat-deleted">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="cache-failed-files">0</span>
                        <span class="s3-stat-label">Failed</span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        Cache Headers Update Progress
                    </h3>
                    <span class="s3-progress-badge" id="cache-progress-percentage">0%</span>
                </div>
                <div class="s3-card-body">
                    <div class="s3-progress-large">
                        <div class="s3-progress-track">
                            <div class="s3-progress-fill-animated" id="cache-progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="s3-grid-2">
                <!-- Options -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21 a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4"></path>
                            </svg>
                            Cache-Control Settings
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <p class="s3-muted-text">Update Cache-Control headers on all files already uploaded to S3 for the current environment.</p>
                        
                        <div class="s3-form-group" style="margin-top: 16px;">
                            <label for="cache_control_value" class="s3-label">Cache-Control Value</label>
                            <div class="s3-select-wrapper s3-select-full">
                                <select id="cache_control_value" class="s3-select">
                                    <option value="0" <?php selected($cache_control, 0); ?>>No cache (no-cache, no-store)</option>
                                    <option value="86400" <?php selected($cache_control, 86400); ?>>1 day (86,400 seconds)</option>
                                    <option value="604800" <?php selected($cache_control, 604800); ?>>1 week (604,800 seconds)</option>
                                    <option value="2592000" <?php selected($cache_control, 2592000); ?>>1 month (2,592,000 seconds)</option>
                                    <option value="31536000" <?php selected($cache_control, 31536000); ?>>1 year (31,536,000 seconds) — Recommended</option>
                                </select>
                            </div>
                            <span class="s3-help">This will be applied to all existing files on S3.</span>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Controls
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <div class="s3-migration-controls">
                            <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-start-cache-sync">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polygon points="10 8 16 12 10 16 10 8"></polygon>
                                </svg>
                                Start Cache Update
                            </button>
                            
                            <button type="button" class="s3-btn s3-btn-danger" id="btn-cancel-cache-sync" style="display: none;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                                Cancel
                            </button>
                        </div>
                        
                        <div class="s3-status-panel" id="cache-sync-status" style="display: none; margin-top: 16px;">
                            <div class="s3-status-item">
                                <span class="s3-status-label">Status</span>
                                <span class="s3-status-value" id="cache-status-text">Idle</span>
                            </div>
                            <div class="s3-status-item">
                                <span class="s3-status-label">Progress</span>
                                <span class="s3-status-value">
                                    <span id="cache-current-count">0</span> / <span id="cache-total-count">0</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cache Sync Log -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        Update Log
                    </h3>
                </div>
                <div class="s3-card-body s3-card-body-dark">
                    <div class="s3-terminal" id="cache-sync-log">
                        <div class="s3-terminal-line s3-terminal-muted">
                            <span class="s3-terminal-prompt">$</span> Cache update log will appear here...
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
            
            <!-- Info Banner -->
            <div class="s3-alert s3-alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <strong>What is Reconciliation?</strong>
                    <p>
                        This tool compares files on S3 with WordPress attachments and syncs the metadata.
                        Use it when files were uploaded to S3 before the plugin was installed, or when the 
                        sync status appears incorrect.
                    </p>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="s3-stats-row">
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-wordpress">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="recon-wp-attachments"><?php echo number_format($reconciliation_stats['total_attachments'] ?? 0); ?></span>
                        <span class="s3-stat-label">WP Attachments</span>
                    </div>
                </div>
                
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-migrated">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="recon-marked"><?php echo number_format($reconciliation_stats['marked_migrated'] ?? 0); ?></span>
                        <span class="s3-stat-label">Marked as Migrated</span>
                    </div>
                </div>
                
                <div class="s3-stat-card">
                    <div class="s3-stat-icon s3-stat-uploaded">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="recon-s3-files"><?php echo number_format($reconciliation_stats['s3_original_files'] ?? 0); ?></span>
                        <span class="s3-stat-label">S3 Original Files</span>
                    </div>
                </div>
                
                <div class="s3-stat-card <?php echo ($reconciliation_stats['has_discrepancy'] ?? false) ? 's3-stat-card-warning' : ''; ?>">
                    <div class="s3-stat-icon s3-stat-edited">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="s3-stat-content">
                        <span class="s3-stat-value" id="recon-discrepancy"><?php echo number_format($reconciliation_stats['discrepancy'] ?? 0); ?></span>
                        <span class="s3-stat-label">Discrepancy</span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        Reconciliation Progress
                    </h3>
                    <span class="s3-progress-badge" id="recon-progress-percentage"><?php echo esc_html($reconciliation_stats['progress_percentage'] ?? 0); ?>%</span>
                </div>
                <div class="s3-card-body">
                    <div class="s3-progress-large">
                        <div class="s3-progress-track">
                            <div class="s3-progress-fill-animated" id="recon-progress-bar" 
                                 style="width: <?php echo esc_attr($reconciliation_stats['progress_percentage'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="s3-grid-2">
                <!-- Scan Preview -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Scan Preview
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <p class="s3-muted-text">Scan S3 to see how many files match WordPress attachments before running reconciliation.</p>
                        
                        <div class="s3-form-actions" style="margin-top: 16px;">
                            <button type="button" class="s3-btn s3-btn-secondary" id="btn-scan-s3">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                Scan S3
                            </button>
                        </div>
                        
                        <div class="s3-scan-results" id="scan-results" style="display: none; margin-top: 16px;">
                            <div class="s3-sync-info-box">
                                <div class="s3-sync-info-item">
                                    <span class="s3-sync-info-label">S3 Original Files</span>
                                    <span class="s3-sync-info-value" id="scan-s3-files">-</span>
                                </div>
                                <div class="s3-sync-info-item">
                                    <span class="s3-sync-info-label">WordPress Attachments</span>
                                    <span class="s3-sync-info-value" id="scan-wp-files">-</span>
                                </div>
                                <div class="s3-sync-info-item">
                                    <span class="s3-sync-info-label">Matching Files</span>
                                    <span class="s3-sync-info-value" id="scan-matches">-</span>
                                </div>
                                <div class="s3-sync-info-item">
                                    <span class="s3-sync-info-label">Not Found on S3</span>
                                    <span class="s3-sync-info-value" id="scan-not-found">-</span>
                                </div>
                                <div class="s3-sync-info-item">
                                    <span class="s3-sync-info-label">Would be Marked</span>
                                    <span class="s3-sync-info-value s3-text-success" id="scan-would-mark">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Controls
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <div class="s3-form-group">
                            <label for="recon-batch-size" class="s3-label">Batch Size</label>
                            <div class="s3-select-wrapper s3-select-full">
                                <select id="recon-batch-size" class="s3-select">
                                    <option value="25">25 files per batch</option>
                                    <option value="50" selected>50 files per batch</option>
                                    <option value="100">100 files per batch</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="s3-migration-controls" style="margin-top: 20px;">
                            <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-start-reconciliation">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10"></polyline>
                                    <polyline points="1 20 1 14 7 14"></polyline>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                Start Reconciliation
                            </button>
                            
                            <div class="s3-btn-group">
                                <button type="button" class="s3-btn s3-btn-secondary" id="btn-pause-reconciliation" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="6" y="4" width="4" height="16"></rect>
                                        <rect x="14" y="4" width="4" height="16"></rect>
                                    </svg>
                                    Pause
                                </button>
                                
                                <button type="button" class="s3-btn s3-btn-secondary" id="btn-resume-reconciliation" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                    Resume
                                </button>
                                
                                <button type="button" class="s3-btn s3-btn-danger" id="btn-stop-reconciliation" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    Cancel
                                </button>
                            </div>
                        </div>
                        
                        <div class="s3-status-panel" id="recon-status" style="display: none; margin-top: 16px;">
                            <div class="s3-status-item">
                                <span class="s3-status-label">Status</span>
                                <span class="s3-status-value" id="recon-status-text">Idle</span>
                            </div>
                            <div class="s3-status-item">
                                <span class="s3-status-label">Current file</span>
                                <span class="s3-status-value s3-file-mono" id="recon-current-file">-</span>
                            </div>
                            <div class="s3-status-item">
                                <span class="s3-status-label">Progress</span>
                                <span class="s3-status-value">
                                    <span id="recon-processed-count">0</span> / <span id="recon-total-count">0</span>
                                    <span class="s3-badge s3-badge-success" style="margin-left: 8px;" id="recon-found-badge">
                                        <span id="recon-found-count">0</span> found
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Log -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        Reconciliation Log
                    </h3>
                    <button type="button" class="s3-btn s3-btn-ghost s3-btn-sm" id="btn-clear-metadata" title="Clear all migration metadata">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Reset Metadata
                    </button>
                </div>
                <div class="s3-card-body s3-card-body-dark">
                    <div class="s3-terminal" id="recon-log">
                        <div class="s3-terminal-line s3-terminal-muted">
                            <span class="s3-terminal-prompt">$</span> Reconciliation log will appear here...
                        </div>
                    </div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Scan S3 Preview
                $('#btn-scan-s3').on('click', function() {
                    const $btn = $(this);
                    $btn.prop('disabled', true).html('<svg class="s3-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Scanning...');
                    
                    $.ajax({
                        url: mediaToolkit.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'media_toolkit_reconciliation_scan_s3',
                            nonce: mediaToolkit.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const data = response.data;
                                $('#scan-results').show();
                                $('#scan-s3-files').text(data.s3_original_files.toLocaleString());
                                $('#scan-wp-files').text(data.wp_attachments.toLocaleString());
                                $('#scan-matches').text(data.matches.toLocaleString() + ' (' + data.match_percentage + '%)');
                                $('#scan-not-found').text(data.not_found_on_s3.toLocaleString());
                                $('#scan-would-mark').text(data.would_be_marked.toLocaleString());
                                
                                logToTerminal('recon-log', 'Scan completed: ' + data.matches + ' matching files found', 'success');
                            } else {
                                logToTerminal('recon-log', 'Scan failed: ' + (response.data.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function() {
                            logToTerminal('recon-log', 'Scan failed: Network error', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Scan S3');
                        }
                    });
                });

                // Initialize batch processor for reconciliation
                if (typeof BatchProcessor !== 'undefined') {
                    const reconProcessor = new BatchProcessor({
                        name: 'reconciliation',
                        actions: {
                            start: 'media_toolkit_reconciliation_start',
                            process: 'media_toolkit_reconciliation_process_batch',
                            pause: 'media_toolkit_reconciliation_pause',
                            resume: 'media_toolkit_reconciliation_resume',
                            stop: 'media_toolkit_reconciliation_stop',
                            status: 'media_toolkit_reconciliation_status'
                        },
                        selectors: {
                            startBtn: '#btn-start-reconciliation',
                            pauseBtn: '#btn-pause-reconciliation',
                            resumeBtn: '#btn-resume-reconciliation',
                            stopBtn: '#btn-stop-reconciliation',
                            progressBar: '#recon-progress-bar',
                            progressPercentage: '#recon-progress-percentage',
                            statusPanel: '#recon-status',
                            statusText: '#recon-status-text',
                            currentFile: '#recon-current-file',
                            processedCount: '#recon-processed-count',
                            totalCount: '#recon-total-count',
                            failedCount: '#recon-found-count',
                            failedBadge: '#recon-found-badge',
                            logPanel: '#recon-log',
                            confirmModal: '#confirm-modal',
                            confirmTitle: '#confirm-title',
                            confirmMessage: '#confirm-message',
                            confirmYes: '#btn-confirm-yes',
                            confirmNo: '#btn-confirm-no'
                        },
                        options: {
                            batchSizeSelector: '#recon-batch-size'
                        },
                        messages: {
                            startConfirm: null,
                            stopConfirm: 'Are you sure you want to stop the reconciliation? Progress will be saved.'
                        },
                        onUpdateStats: function(stats) {
                            $('#recon-wp-attachments').text((stats.total_attachments || 0).toLocaleString());
                            $('#recon-marked').text((stats.marked_migrated || 0).toLocaleString());
                            $('#recon-s3-files').text((stats.s3_original_files || 0).toLocaleString());
                            $('#recon-discrepancy').text((stats.discrepancy || 0).toLocaleString());
                        }
                    });
                    reconProcessor.init();
                }

                // Reset Metadata button
                $('#btn-clear-metadata').on('click', function() {
                    if (!confirm('Are you sure you want to clear ALL migration metadata? This cannot be undone.')) {
                        return;
                    }
                    
                    const $btn = $(this);
                    $btn.prop('disabled', true);
                    
                    $.ajax({
                        url: mediaToolkit.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'media_toolkit_clear_migration_metadata',
                            nonce: mediaToolkit.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                logToTerminal('recon-log', 'Metadata cleared: ' + response.data.deleted + ' records removed', 'success');
                                // Refresh stats
                                $('#recon-marked').text('0');
                                $('#recon-discrepancy').text($('#recon-s3-files').text());
                            } else {
                                logToTerminal('recon-log', 'Failed to clear metadata: ' + (response.data.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function() {
                            logToTerminal('recon-log', 'Failed to clear metadata: Network error', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                        }
                    });
                });

                // Helper function to log to terminal
                function logToTerminal(terminalId, message, type) {
                    const $terminal = $('#' + terminalId);
                    const timestamp = new Date().toLocaleTimeString();
                    const typeClass = type === 'error' ? 's3-terminal-error' : (type === 'success' ? 's3-terminal-success' : '');
                    
                    $terminal.append(
                        '<div class="s3-terminal-line ' + typeClass + '">' +
                        '<span class="s3-terminal-time">[' + timestamp + ']</span> ' +
                        message +
                        '</div>'
                    );
                    $terminal.scrollTop($terminal[0].scrollHeight);
                }
            });
            </script>

        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="s3-modal" style="display:none;">
    <div class="s3-modal-content">
        <button type="button" class="s3-modal-close">&times;</button>
        <h2 id="confirm-title">Confirm Action</h2>
        <p id="confirm-message"></p>
        <div class="s3-modal-buttons">
            <button type="button" class="s3-btn s3-btn-primary" id="btn-confirm-yes">Yes, Continue</button>
            <button type="button" class="s3-btn s3-btn-ghost" id="btn-confirm-no">Cancel</button>
        </div>
    </div>
</div>

