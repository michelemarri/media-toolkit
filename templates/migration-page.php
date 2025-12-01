<?php
/**
 * Migration page template - Modern UI
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
    $plugin->get_history()
);
$migration_stats = $stats->get_migration_stats();

$bannerPath = MEDIA_TOOLKIT_PATH . 'assets/images/banner-1544x500.png';
$bannerUrl = MEDIA_TOOLKIT_URL . 'assets/images/banner-1544x500.png';
$hasBanner = file_exists($bannerPath);
?>

<div class="wrap mds-wrap">
    <div class="mds-page">
    <?php if ($hasBanner): ?>
    <div class="mds-hero">
        <img src="<?php echo esc_url($bannerUrl); ?>" alt="Media Toolkit" class="mds-hero-banner">
        <div class="mds-hero-overlay">
            <h1 class="mds-hero-title"><?php esc_html_e('Media Migration', 'media-toolkit'); ?></h1>
            <p class="mds-hero-description"><?php esc_html_e('Migrate existing media files to Amazon S3', 'media-toolkit'); ?></p>
            <span class="mds-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
        </div>
    </div>
    <?php else: ?>
    <header class="mds-page-header">
        <h1 class="mds-page-title">
            <span class="mds-logo"><span class="dashicons dashicons-upload"></span></span>
            <?php esc_html_e('Media Migration', 'media-toolkit'); ?>
        </h1>
        <p class="mds-description"><?php esc_html_e('Migrate existing media files to Amazon S3', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <?php if (!$is_configured): ?>
    <div class="mds-alert mds-alert-error">
        <span class="dashicons dashicons-warning"></span>
        <div>
            <strong><?php esc_html_e('S3 Offload is not configured.', 'media-toolkit'); ?></strong>
            <p><?php printf(esc_html__('Please %sconfigure your S3 settings%s before migrating.', 'media-toolkit'), '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings')) . '">', '</a>'); ?></p>
        </div>
    </div>
    <?php else: ?>

    <!-- Stats Row -->
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
                
                <div class="mds-form-group" style="margin-top: 16px;">
                    <label for="migration-mode" class="mds-label"><?php esc_html_e('Mode', 'media-toolkit'); ?></label>
                    <select id="migration-mode" class="mds-select">
                        <option value="sync"><?php esc_html_e('Synchronous (browser must stay open)', 'media-toolkit'); ?></option>
                        <option value="async"><?php esc_html_e('Asynchronous (runs in background)', 'media-toolkit'); ?></option>
                    </select>
                    <span class="mds-help"><?php esc_html_e('Async mode uses WordPress cron', 'media-toolkit'); ?></span>
                </div>
                
                <div class="mds-form-group" style="margin-top: 20px;">
                    <label class="mds-toggle">
                        <input type="checkbox" id="remove-local">
                        <span class="mds-toggle-slider"></span>
                        <span class="mds-toggle-label"><strong><?php esc_html_e('Delete local files after migration', 'media-toolkit'); ?></strong></span>
                    </label>
                    <p class="mds-help" style="margin-left: 52px;">
                        <span class="mds-badge mds-badge-warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></span>
                        <?php esc_html_e('This will permanently delete local copies. Make sure you have a backup!', 'media-toolkit'); ?>
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

    <?php endif; ?>

    <footer class="mds-footer">
        <p>
            <?php printf(esc_html__('Developed by %s', 'media-toolkit'), '<a href="https://metodo.dev" target="_blank" rel="noopener">Michele Marri - Metodo.dev</a>'); ?>
            &bull;
            <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
        </p>
    </footer>
    </div><!-- /.mds-page -->
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
