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

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
    <?php if ($hasBanner): ?>
    <!-- Hero Banner -->
    <div class="mt-hero">
        <img src="<?php echo esc_url($bannerUrl); ?>" alt="Media Toolkit" class="mt-hero-banner">
        <div class="mt-hero-overlay">
            <h1 class="mt-hero-title"><?php esc_html_e('Image Optimization', 'media-toolkit'); ?></h1>
            <p class="mt-hero-description"><?php esc_html_e('Compress and optimize your media library images', 'media-toolkit'); ?></p>
            <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
        </div>
    </div>
    <?php else: ?>
    <!-- Header -->
    <header>
        <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
            <span class="mt-logo">
                <span class="dashicons dashicons-performance"></span>
            </span>
            <?php esc_html_e('Image Optimization', 'media-toolkit'); ?>
        </h1>
        <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Compress and optimize your media library images', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-format-image"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Total Images', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-total_images"><?php echo esc_html($stats['total_images']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Optimized', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-optimized_images"><?php echo esc_html($stats['optimized_images']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-pending_images"><?php echo esc_html($stats['pending_images']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Space Saved', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-total_saved_formatted"><?php echo esc_html($stats['total_saved_formatted']); ?></span>
        </div>
    </div>

    <!-- Progress Card -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-chart-line text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Optimization Progress', 'media-toolkit'); ?></h3>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="progress-percentage"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4">
                <div class="flex-1 mt-progress-bar mt-progress-animated">
                    <div class="mt-progress-fill" id="optimization-progress" style="width: <?php echo esc_attr($stats['progress_percentage']); ?>%"></div>
                </div>
                <span class="text-sm font-semibold text-gray-900 min-w-[45px] text-right"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
            </div>
        </div>
    </div>

    <!-- Settings & Controls Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Compression Settings Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-admin-settings text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Compression Settings', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="jpeg-quality" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('JPEG Quality', 'media-toolkit'); ?></label>
                    <div class="flex items-center gap-3">
                        <input type="range" id="jpeg-quality" min="60" max="100" value="<?php echo esc_attr($opt_settings['jpeg_quality']); ?>" class="flex-1">
                        <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full mt-badge-info" id="jpeg-quality-value"><?php echo esc_html($opt_settings['jpeg_quality']); ?></span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Higher = better quality, larger file. Recommended: 75-85', 'media-toolkit'); ?></p>
                </div>
                
                <div>
                    <label for="png-compression" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('PNG Compression Level', 'media-toolkit'); ?></label>
                    <div class="flex items-center gap-3">
                        <input type="range" id="png-compression" min="0" max="9" value="<?php echo esc_attr($opt_settings['png_compression']); ?>" class="flex-1">
                        <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full mt-badge-info" id="png-compression-value"><?php echo esc_html($opt_settings['png_compression']); ?></span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('0 = no compression, 9 = max compression (lossless)', 'media-toolkit'); ?></p>
                </div>

                <div>
                    <label for="max-file-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Max File Size (MB)', 'media-toolkit'); ?></label>
                    <select id="max-file-size" class="w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                        <option value="5" <?php selected($opt_settings['max_file_size_mb'], 5); ?>>5 MB</option>
                        <option value="10" <?php selected($opt_settings['max_file_size_mb'], 10); ?>>10 MB</option>
                        <option value="20" <?php selected($opt_settings['max_file_size_mb'], 20); ?>>20 MB</option>
                        <option value="50" <?php selected($opt_settings['max_file_size_mb'], 50); ?>>50 MB</option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Files larger than this will be skipped', 'media-toolkit'); ?></p>
                </div>

                <div class="pt-2">
                    <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="strip-metadata" <?php checked($opt_settings['strip_metadata']); ?>>
                        <span class="mt-toggle-slider"></span>
                        <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Strip EXIF/Metadata', 'media-toolkit'); ?></span>
                    </label>
                    <p class="mt-2 ml-14 text-sm text-gray-500"><?php esc_html_e('Remove camera info, GPS data, etc. from JPEG files', 'media-toolkit'); ?></p>
                </div>

                <div class="flex items-center gap-3 pt-3">
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-xs transition-all" id="btn-save-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                    </button>
                    <span class="text-sm text-gray-500" id="settings-status"></span>
                </div>
            </div>
        </div>

        <!-- Batch Processing Controls -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-controls-play text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Batch Optimization', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6">
                <div class="mb-5">
                    <label for="batch-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                    <select id="batch-size" class="w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                        <option value="10"><?php esc_html_e('10 images per batch', 'media-toolkit'); ?></option>
                        <option value="25" selected><?php esc_html_e('25 images per batch', 'media-toolkit'); ?></option>
                        <option value="50"><?php esc_html_e('50 images per batch', 'media-toolkit'); ?></option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Smaller batches are safer for shared hosting', 'media-toolkit'); ?></p>
                </div>

                <div class="flex flex-col gap-3">
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-optimization">
                        <span class="dashicons dashicons-performance"></span>
                        <?php esc_html_e('Start Optimization', 'media-toolkit'); ?>
                    </button>
                    
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-pause-optimization" disabled>
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Pause', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-resume-optimization" disabled>
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Resume', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-stop-optimization" disabled>
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="optimization-status" class="<?php echo $state['status'] !== 'idle' ? '' : 'hidden'; ?> mt-5">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900" id="status-text"><?php echo esc_html(ucfirst($state['status'])); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900">
                                <span id="processed-count"><?php echo esc_html($state['processed']); ?></span> / <span id="total-count"><?php echo esc_html($state['total_files']); ?></span>
                                <span class="<?php echo $state['failed'] > 0 ? '' : 'hidden'; ?> ml-2 px-2 py-0.5 text-xs font-medium rounded mt-badge-error" id="failed-badge">
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
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <span class="dashicons dashicons-desktop text-gray-700"></span>
            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Server Capabilities', 'media-toolkit'); ?></h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="flex items-center gap-4 p-5 bg-white border rounded-lg <?php echo $capabilities['gd'] ? 'border-green-200 bg-gradient-to-br from-white to-green-50' : 'border-gray-200'; ?>">
                    <div class="flex items-center justify-center w-11 h-11 rounded-lg <?php echo $capabilities['gd'] ? 'mt-stat-icon-success' : 'mt-stat-icon-error'; ?>">
                        <span class="dashicons <?php echo $capabilities['gd'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    </div>
                    <div>
                        <span class="block text-lg font-semibold text-gray-900"><?php echo $capabilities['gd'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('GD Library', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-5 bg-white border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-center w-11 h-11 rounded-lg <?php echo $capabilities['imagick'] ? 'mt-stat-icon-success' : 'mt-stat-icon-warning'; ?>">
                        <span class="dashicons <?php echo $capabilities['imagick'] ? 'dashicons-yes-alt' : 'dashicons-info'; ?>"></span>
                    </div>
                    <div>
                        <span class="block text-lg font-semibold text-gray-900"><?php echo $capabilities['imagick'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('ImageMagick', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-5 bg-white border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-center w-11 h-11 rounded-lg <?php echo $capabilities['webp_support'] ? 'mt-stat-icon-success' : 'mt-stat-icon-warning'; ?>">
                        <span class="dashicons <?php echo $capabilities['webp_support'] ? 'dashicons-yes-alt' : 'dashicons-info'; ?>"></span>
                    </div>
                    <div>
                        <span class="block text-lg font-semibold text-gray-900"><?php echo $capabilities['webp_support'] ? __('Available', 'media-toolkit') : __('Not available', 'media-toolkit'); ?></span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('WebP Support', 'media-toolkit'); ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-5 bg-white border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-center w-11 h-11 rounded-lg mt-stat-icon-info">
                        <span class="dashicons dashicons-info"></span>
                    </div>
                    <div>
                        <span class="block text-lg font-semibold text-gray-900"><?php echo esc_html($capabilities['max_memory']); ?></span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Memory Limit', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Log -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <span class="dashicons dashicons-media-text text-gray-700"></span>
            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Optimization Log', 'media-toolkit'); ?></h3>
        </div>
        <div class="mt-terminal">
            <div class="mt-terminal-header">
                <div class="flex gap-2">
                    <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                    <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                    <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                </div>
                <span class="mt-terminal-title"><?php esc_html_e('optimization.log', 'media-toolkit'); ?></span>
            </div>
            <div class="mt-terminal-body" id="optimization-log">
                <div class="mt-terminal-line">
                    <span class="mt-terminal-prompt">$</span>
                    <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Optimization log will appear here...', 'media-toolkit'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center text-sm text-gray-400 py-6">
        <p>
            <?php printf(esc_html__('Developed by %s', 'media-toolkit'), '<a href="https://metodo.dev" target="_blank" rel="noopener" class="font-medium hover:text-accent-500">Michele Marri - Metodo.dev</a>'); ?>
            &bull;
            <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
        </p>
    </footer>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900" id="confirm-title"><?php esc_html_e('Confirm Action', 'media-toolkit'); ?></h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 border-0 bg-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6">
            <p id="confirm-message" class="text-sm text-gray-600"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
            <button type="button" class="inline-flex items-center px-4 py-2 border-0 text-sm font-medium text-gray-600 bg-transparent hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all cursor-pointer" id="btn-confirm-no"><?php esc_html_e('Cancel', 'media-toolkit'); ?></button>
            <button type="button" class="inline-flex items-center px-5 py-2 border-0 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all cursor-pointer" id="btn-confirm-yes"><?php esc_html_e('Yes, Continue', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>
