<?php
/**
 * Optimize page template - Image Compression
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

$admin_optimize = new \Metodo\MediaToolkit\Admin\Admin_Optimize(
    $plugin->get_image_optimizer(),
    $settings
);

$stats = $admin_optimize->get_stats();
$opt_settings = $admin_optimize->get_optimization_settings();
$capabilities = $admin_optimize->get_server_capabilities();
$state = $admin_optimize->get_state();

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
            <h1 class="mds-hero-title"><?php esc_html_e('Image Optimization', 'media-toolkit'); ?></h1>
            <p class="mds-hero-description"><?php esc_html_e('Compress and optimize your media library images', 'media-toolkit'); ?></p>
            <span class="mds-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
        </div>
    </div>
    <?php else: ?>
    <header class="mds-page-header">
        <h1 class="mds-page-title">
            <span class="mds-logo"><span class="dashicons dashicons-performance"></span></span>
            <?php esc_html_e('Image Optimization', 'media-toolkit'); ?>
        </h1>
        <p class="mds-description"><?php esc_html_e('Compress and optimize your media library images', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="mds-stats-grid">
        <div class="mds-stat-card">
            <div class="mds-stat-icon mds-stat-icon-primary">
                <span class="dashicons dashicons-format-image"></span>
            </div>
            <div class="mds-stat-content">
                <span class="mds-stat-value" id="stat-total_images"><?php echo esc_html($stats['total_images']); ?></span>
                <span class="mds-stat-label"><?php esc_html_e('Total Images', 'media-toolkit'); ?></span>
            </div>
        </div>
        
        <div class="mds-stat-card">
            <div class="mds-stat-icon mds-stat-icon-success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="mds-stat-content">
                <span class="mds-stat-value" id="stat-optimized_images"><?php echo esc_html($stats['optimized_images']); ?></span>
                <span class="mds-stat-label"><?php esc_html_e('Optimized', 'media-toolkit'); ?></span>
            </div>
        </div>
        
        <div class="mds-stat-card">
            <div class="mds-stat-icon mds-stat-icon-warning">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="mds-stat-content">
                <span class="mds-stat-value" id="stat-pending_images"><?php echo esc_html($stats['pending_images']); ?></span>
                <span class="mds-stat-label"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
            </div>
        </div>
        
        <div class="mds-stat-card">
            <div class="mds-stat-icon mds-stat-icon-info">
                <span class="dashicons dashicons-database"></span>
            </div>
            <div class="mds-stat-content">
                <span class="mds-stat-value" id="stat-total_saved_formatted"><?php echo esc_html($stats['total_saved_formatted']); ?></span>
                <span class="mds-stat-label"><?php esc_html_e('Space Saved', 'media-toolkit'); ?></span>
            </div>
        </div>
    </div>

    <!-- Progress -->
    <div class="mds-card">
        <div class="mds-card-header">
            <h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e('Optimization Progress', 'media-toolkit'); ?></h3>
            <span class="mds-progress-badge" id="progress-percentage"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
        </div>
        <div class="mds-card-body">
            <div class="mds-progress mds-progress-lg mds-progress-animated">
                <div class="mds-progress-bar">
                    <div class="mds-progress-fill" id="optimization-progress" style="width: <?php echo esc_attr($stats['progress_percentage']); ?>%"></div>
                </div>
                <span class="mds-progress-percent"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
            </div>
        </div>
    </div>

    <div class="mds-cards-grid">
        <!-- Compression Settings -->
        <div class="mds-card">
            <div class="mds-card-header">
                <h3><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Compression Settings', 'media-toolkit'); ?></h3>
            </div>
            <div class="mds-card-body">
                <div class="mds-form-group">
                    <label for="jpeg-quality" class="mds-label"><?php esc_html_e('JPEG Quality', 'media-toolkit'); ?></label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="range" id="jpeg-quality" class="mds-range" 
                               min="60" max="100" value="<?php echo esc_attr($opt_settings['jpeg_quality']); ?>" style="flex: 1;">
                        <span class="mds-badge mds-badge-info" id="jpeg-quality-value"><?php echo esc_html($opt_settings['jpeg_quality']); ?></span>
                    </div>
                    <span class="mds-help"><?php esc_html_e('Higher = better quality, larger file. Recommended: 75-85', 'media-toolkit'); ?></span>
                </div>
                
                <div class="mds-form-group" style="margin-top: 20px;">
                    <label for="png-compression" class="mds-label"><?php esc_html_e('PNG Compression Level', 'media-toolkit'); ?></label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="range" id="png-compression" class="mds-range" 
                               min="0" max="9" value="<?php echo esc_attr($opt_settings['png_compression']); ?>" style="flex: 1;">
                        <span class="mds-badge mds-badge-info" id="png-compression-value"><?php echo esc_html($opt_settings['png_compression']); ?></span>
                    </div>
                    <span class="mds-help"><?php esc_html_e('0 = no compression, 9 = max compression (lossless)', 'media-toolkit'); ?></span>
                </div>

                <div class="mds-form-group" style="margin-top: 20px;">
                    <label for="max-file-size" class="mds-label"><?php esc_html_e('Max File Size (MB)', 'media-toolkit'); ?></label>
                    <select id="max-file-size" class="mds-select">
                        <option value="5" <?php selected($opt_settings['max_file_size_mb'], 5); ?>>5 MB</option>
                        <option value="10" <?php selected($opt_settings['max_file_size_mb'], 10); ?>>10 MB</option>
                        <option value="20" <?php selected($opt_settings['max_file_size_mb'], 20); ?>>20 MB</option>
                        <option value="50" <?php selected($opt_settings['max_file_size_mb'], 50); ?>>50 MB</option>
                    </select>
                    <span class="mds-help"><?php esc_html_e('Files larger than this will be skipped', 'media-toolkit'); ?></span>
                </div>

                <div class="mds-form-group" style="margin-top: 20px;">
                    <label class="mds-toggle">
                        <input type="checkbox" id="strip-metadata" <?php checked($opt_settings['strip_metadata']); ?>>
                        <span class="mds-toggle-slider"></span>
                        <span class="mds-toggle-label"><strong><?php esc_html_e('Strip EXIF/Metadata', 'media-toolkit'); ?></strong></span>
                    </label>
                    <p class="mds-help" style="margin-left: 52px;"><?php esc_html_e('Remove camera info, GPS data, etc. from JPEG files', 'media-toolkit'); ?></p>
                </div>

                <div class="mds-actions" style="margin-top: 24px;">
                    <button type="button" class="mds-btn mds-btn-secondary" id="btn-save-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                    </button>
                    <span class="mds-text-secondary" id="settings-status"></span>
                </div>
            </div>
        </div>

        <!-- Batch Processing Controls -->
        <div class="mds-card">
            <div class="mds-card-header">
                <h3><span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Batch Optimization', 'media-toolkit'); ?></h3>
            </div>
            <div class="mds-card-body">
                <div class="mds-form-group">
                    <label for="batch-size" class="mds-label"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                    <select id="batch-size" class="mds-select">
                        <option value="10"><?php esc_html_e('10 images per batch', 'media-toolkit'); ?></option>
                        <option value="25" selected><?php esc_html_e('25 images per batch', 'media-toolkit'); ?></option>
                        <option value="50"><?php esc_html_e('50 images per batch', 'media-toolkit'); ?></option>
                    </select>
                    <span class="mds-help"><?php esc_html_e('Smaller batches are safer for shared hosting', 'media-toolkit'); ?></span>
                </div>

                <div class="mds-actions" style="flex-direction: column; gap: 12px; margin-top: 20px;">
                    <button type="button" class="mds-btn mds-btn-primary mds-btn-lg" id="btn-start-optimization" style="width: 100%;">
                        <span class="dashicons dashicons-performance"></span>
                        <?php esc_html_e('Start Optimization', 'media-toolkit'); ?>
                    </button>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="mds-btn mds-btn-secondary" id="btn-pause-optimization" disabled style="flex: 1;">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Pause', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="mds-btn mds-btn-secondary" id="btn-resume-optimization" disabled style="flex: 1;">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Resume', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="mds-btn mds-btn-danger" id="btn-stop-optimization" disabled style="flex: 1;">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="optimization-status" style="display: <?php echo $state['status'] !== 'idle' ? 'block' : 'none'; ?>; margin-top: 16px;">
                    <div class="mds-sync-details">
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value" id="status-text"><?php echo esc_html(ucfirst($state['status'])); ?></span>
                        </div>
                        <div class="mds-sync-detail-item">
                            <span class="mds-sync-detail-label"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                            <span class="mds-sync-detail-value">
                                <span id="processed-count"><?php echo esc_html($state['processed']); ?></span> / <span id="total-count"><?php echo esc_html($state['total_files']); ?></span>
                                <span class="mds-badge mds-badge-error" style="margin-left: 8px; display: <?php echo $state['failed'] > 0 ? 'inline-flex' : 'none'; ?>;" id="failed-badge">
                                    <span id="failed-count"><?php echo esc_html($state['failed']); ?></span> <?php esc_html_e('failed', 'media-toolkit'); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Capabilities -->
    <div class="mds-card">
        <div class="mds-card-header">
            <h3><span class="dashicons dashicons-desktop"></span> <?php esc_html_e('Server Capabilities', 'media-toolkit'); ?></h3>
        </div>
        <div class="mds-card-body">
            <div class="mds-stats-grid">
                <div class="mds-stat-card <?php echo $capabilities['gd'] ? 'mds-stat-card-success' : ''; ?>">
                    <div class="mds-stat-icon <?php echo $capabilities['gd'] ? 'mds-stat-icon-success' : 'mds-stat-icon-error'; ?>">
                        <span class="dashicons <?php echo $capabilities['gd'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value mds-stat-value-sm"><?php echo $capabilities['gd'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('GD Library', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="mds-stat-card">
                    <div class="mds-stat-icon <?php echo $capabilities['imagick'] ? 'mds-stat-icon-success' : 'mds-stat-icon-warning'; ?>">
                        <span class="dashicons <?php echo $capabilities['imagick'] ? 'dashicons-yes-alt' : 'dashicons-info'; ?>"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value mds-stat-value-sm"><?php echo $capabilities['imagick'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('ImageMagick', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="mds-stat-card">
                    <div class="mds-stat-icon <?php echo $capabilities['webp_support'] ? 'mds-stat-icon-success' : 'mds-stat-icon-warning'; ?>">
                        <span class="dashicons <?php echo $capabilities['webp_support'] ? 'dashicons-yes-alt' : 'dashicons-info'; ?>"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value mds-stat-value-sm"><?php echo $capabilities['webp_support'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('WebP Support', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="mds-stat-card">
                    <div class="mds-stat-icon mds-stat-icon-info">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div class="mds-stat-content">
                        <span class="mds-stat-value mds-stat-value-sm"><?php echo esc_html($capabilities['max_memory']); ?></span>
                        <span class="mds-stat-label"><?php esc_html_e('Memory Limit', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Log -->
    <div class="mds-card">
        <div class="mds-card-header">
            <h3><span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Optimization Log', 'media-toolkit'); ?></h3>
        </div>
        <div class="mds-card-body" style="padding: 0;">
            <div class="mds-terminal">
                <div class="mds-terminal-header">
                    <div class="mds-terminal-dots">
                        <span class="mds-terminal-dot mds-terminal-dot-red"></span>
                        <span class="mds-terminal-dot mds-terminal-dot-yellow"></span>
                        <span class="mds-terminal-dot mds-terminal-dot-green"></span>
                    </div>
                    <span class="mds-terminal-title"><?php esc_html_e('optimization.log', 'media-toolkit'); ?></span>
                </div>
                <div class="mds-terminal-body" id="optimization-log">
                    <div class="mds-terminal-line">
                        <span class="mds-terminal-prompt">$</span>
                        <span class="mds-terminal-text mds-terminal-muted"><?php esc_html_e('Optimization log will appear here...', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
