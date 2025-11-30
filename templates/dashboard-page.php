<?php
/**
 * Dashboard page template
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$settings = $plugin->get_settings();
$stats = new \Metodo\MediaToolkit\Stats\Stats(
    $plugin->get_logger(),
    $plugin->get_history(),
    $settings
);

$is_configured = false;
$dashboard_stats = [];
$migration_stats = [];

if ($settings) {
    $is_configured = $settings->is_configured();
    $dashboard_stats = $stats->get_dashboard_stats();
    $migration_stats = $stats->get_migration_stats();
}
?>

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </div>
            <div>
                <h1>Dashboard</h1>
                <p class="s3-subtitle">Media S3 Offload Overview</p>
            </div>
        </div>
    </div>

    <?php if (!$is_configured): ?>
        <div class="s3-card-panel s3-notice-card">
            <div class="s3-notice-content">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    <h3>Plugin Not Configured</h3>
                    <p>Configure your AWS credentials to start offloading media files to S3.</p>
                    <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>" class="s3-btn s3-btn-primary">
                        Go to Settings
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Stats Cards -->
    <div class="s3-stats-row s3-stats-row-3">
        <!-- WordPress Media Files -->
        <div class="s3-stat-card s3-stat-card-large">
            <div class="s3-stat-icon s3-stat-wordpress">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo number_format($dashboard_stats['wp_attachments'] ?? 0); ?></span>
                <span class="s3-stat-label">WordPress Media Files</span>
            </div>
        </div>
        
        <!-- Files on S3 (migrated attachments) with sync percentage -->
        <div class="s3-stat-card s3-stat-card-large s3-stat-card-highlight">
            <div class="s3-stat-icon s3-stat-uploaded">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo number_format($dashboard_stats['migrated_attachments'] ?? 0); ?></span>
                <span class="s3-stat-label">Files on S3</span>
                <span class="s3-stat-badge s3-stat-badge-<?php echo ($dashboard_stats['sync_percentage'] ?? 0) >= 100 ? 'success' : 'info'; ?>">
                    <?php echo esc_html($dashboard_stats['sync_percentage'] ?? 0); ?>% synced
                </span>
            </div>
        </div>
        
        <!-- Storage Used -->
        <div class="s3-stat-card s3-stat-card-large">
            <div class="s3-stat-icon s3-stat-migrated">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                <span class="s3-stat-label">Total Storage Used</span>
            </div>
        </div>
    </div>

    <!-- Secondary Stats Cards -->
    <div class="s3-stats-row">
        <!-- Total Files on S3 (with thumbnails) -->
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-total">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                <span class="s3-stat-label">Total Files (incl. thumbnails)</span>
            </div>
        </div>
        
        <!-- Uploaded Today -->
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-edited">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo esc_html($dashboard_stats['files_today'] ?? 0); ?></span>
                <span class="s3-stat-label">Uploaded Today</span>
            </div>
        </div>
        
        <!-- Errors -->
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-deleted">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value"><?php echo esc_html($dashboard_stats['errors_last_7_days'] ?? 0); ?></span>
                <span class="s3-stat-label">Errors (7 days)</span>
            </div>
        </div>
        
        <!-- Last Sync -->
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-sync">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value s3-stat-value-small"><?php echo esc_html($dashboard_stats['s3_synced_at_formatted'] ?? 'Never'); ?></span>
                <span class="s3-stat-label">Last S3 Sync</span>
            </div>
        </div>
    </div>

    <!-- Sync Progress Card -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
                Sync Status
            </h3>
            <?php if ($dashboard_stats['sync_source'] ?? '' === 's3_estimated'): ?>
                <span class="s3-badge s3-badge-warning">Estimated</span>
            <?php endif; ?>
        </div>
        <div class="s3-card-body">
            <div class="s3-sync-overview">
                <div class="s3-sync-progress">
                    <div class="s3-progress-modern">
                        <div class="s3-progress-bar-modern">
                            <div class="s3-progress-fill-modern" style="width: <?php echo esc_attr($dashboard_stats['sync_percentage'] ?? 0); ?>%"></div>
                        </div>
                        <span class="s3-progress-percent"><?php echo esc_html($dashboard_stats['sync_percentage'] ?? 0); ?>%</span>
                    </div>
                    <p class="s3-muted-text">
                        <strong><?php echo number_format($dashboard_stats['estimated_on_s3'] ?? 0); ?></strong> 
                        of 
                        <strong><?php echo number_format($dashboard_stats['wp_attachments'] ?? 0); ?></strong> 
                        WordPress media files are on S3
                        <?php if ($dashboard_stats['sync_source'] ?? '' === 's3_estimated'): ?>
                            <span class="s3-hint">*</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="s3-sync-details">
                    <div class="s3-sync-detail-item">
                        <span class="s3-sync-detail-label">Tracked by plugin</span>
                        <span class="s3-sync-detail-value"><?php echo number_format($dashboard_stats['migrated_via_plugin'] ?? 0); ?> files</span>
                    </div>
                    <div class="s3-sync-detail-item">
                        <span class="s3-sync-detail-label">S3 original files</span>
                        <span class="s3-sync-detail-value"><?php echo number_format($dashboard_stats['s3_original_files'] ?? 0); ?> files</span>
                    </div>
                    <div class="s3-sync-detail-item">
                        <span class="s3-sync-detail-label">S3 total (w/ thumbs)</span>
                        <span class="s3-sync-detail-value"><?php echo number_format($dashboard_stats['s3_total_files'] ?? 0); ?> files</span>
                    </div>
                    <div class="s3-sync-detail-item">
                        <span class="s3-sync-detail-label">Storage used</span>
                        <span class="s3-sync-detail-value"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($dashboard_stats['needs_reconciliation'] ?? false): ?>
                <!-- Reconciliation Notice -->
                <div class="s3-alert s3-alert-warning" style="margin-top: 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <strong>Sync status may be inaccurate</strong>
                        <p>
                            <?php if ($dashboard_stats['sync_source'] === 's3_estimated'): ?>
                                Files appear to exist on S3 but WordPress metadata is not set. 
                                This can happen if files were uploaded before the plugin was installed.
                            <?php else: ?>
                                There's a discrepancy between WordPress metadata and S3 bucket contents.
                            <?php endif; ?>
                            Run the Reconciliation tool to sync the state.
                        </p>
                        <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools&tab=reconciliation'); ?>" class="s3-btn s3-btn-secondary" style="margin-top: 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            Run Reconciliation
                        </a>
                    </div>
                </div>
            <?php elseif (($dashboard_stats['pending_attachments'] ?? 0) > 0 && $is_configured): ?>
                <div class="s3-sync-action">
                    <p class="s3-hint" style="margin-bottom: 12px;">
                        <?php echo esc_html($dashboard_stats['pending_attachments']); ?> files pending migration
                        <?php if (!empty($migration_stats['pending_size_formatted'])): ?>
                            (<?php echo esc_html($migration_stats['pending_size_formatted']); ?>)
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools&tab=migration'); ?>" class="s3-btn s3-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="10 8 16 12 10 16 10 8"></polygon>
                        </svg>
                        Start Migration
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Chart -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                Upload Activity (Last 7 Days)
            </h3>
        </div>
        <div class="s3-card-body">
            <div id="sparkline-container">
                <canvas id="sparkline-chart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                </svg>
                Quick Links
            </h3>
        </div>
        <div class="s3-card-body">
            <div class="s3-quick-links">
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>" class="s3-quick-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Settings
                </a>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools'); ?>" class="s3-quick-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                    Tools
                </a>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-logs'); ?>" class="s3-quick-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Logs
                </a>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-history'); ?>" class="s3-quick-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    History
                </a>
            </div>
        </div>
    </div>
</div>
