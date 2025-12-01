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
                <h1 class="mds-hero-title"><?php esc_html_e('Media Toolkit', 'media-toolkit'); ?></h1>
                <p class="mds-hero-description"><?php esc_html_e('Complete media management toolkit for WordPress. S3 offloading, CDN integration, and image optimization.', 'media-toolkit'); ?></p>
                <span class="mds-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
            </div>
        </div>
        <?php else: ?>
        <header class="mds-page-header">
            <h1 class="mds-page-title">
                <span class="mds-logo">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </span>
                <?php esc_html_e('Media Toolkit', 'media-toolkit'); ?>
            </h1>
            <p class="mds-description"><?php esc_html_e('Complete media management toolkit for WordPress. S3 offloading, CDN integration, and image optimization.', 'media-toolkit'); ?></p>
        </header>
        <?php endif; ?>

        <?php if (!$is_configured): ?>
        <div class="mds-alert mds-alert-warning mds-alert-prominent">
            <span class="dashicons dashicons-admin-generic"></span>
            <div>
                <strong><?php esc_html_e('Complete Your Setup', 'media-toolkit'); ?></strong>
                <p><?php esc_html_e('Configure your AWS credentials to start offloading media files to S3. This only takes a minute.', 'media-toolkit'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=media-toolkit-settings')); ?>" class="mds-btn mds-btn-primary">
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <?php esc_html_e('Configure Now', 'media-toolkit'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Stats Cards -->
        <div class="mds-stats-grid">
            <div class="mds-stat-card mds-stat-card-lg">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-format-gallery"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo number_format($dashboard_stats['wp_attachments'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('WordPress Media Files', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card mds-stat-card-lg mds-stat-card-highlight">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo number_format($dashboard_stats['migrated_attachments'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Files on Storage', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card mds-stat-card-lg">
                <div class="mds-stat-icon mds-stat-icon-<?php echo ($dashboard_stats['sync_percentage'] ?? 0) >= 100 ? 'success' : (($dashboard_stats['sync_percentage'] ?? 0) > 0 ? 'warning' : 'error'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo esc_html($dashboard_stats['sync_percentage'] ?? 0); ?>%</span>
                    <span class="mds-stat-label"><?php esc_html_e('Synced', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card mds-stat-card-lg">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Total Storage Used', 'media-toolkit'); ?></span>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Cards -->
        <div class="mds-stats-grid">
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-media-default"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Total Files (incl. thumbnails)', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo esc_html($dashboard_stats['files_today'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Uploaded Today', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value"><?php echo esc_html($dashboard_stats['errors_last_7_days'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Errors (7 days)', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-default">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value mds-stat-value-sm"><?php echo esc_html($dashboard_stats['s3_synced_at_formatted'] ?? 'Never'); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Last Storage Sync', 'media-toolkit'); ?></span>
                </div>
            </div>
        </div>

        <!-- Activity Chart -->
        <div class="mds-card">
            <div class="mds-card-header">
                <h3>
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e('Upload Activity (Last 7 Days)', 'media-toolkit'); ?>
                </h3>
            </div>
            <div class="mds-card-body">
                <div id="sparkline-container">
                    <canvas id="sparkline-chart" height="120"></canvas>
                </div>
            </div>
        </div>

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
