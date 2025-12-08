<?php
/**
 * Optimize page template - Image Optimization & Resizing
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
    $plugin->get_image_resizer(),
    $settings
);

$stats = $admin_optimize->get_stats();
$opt_settings = $admin_optimize->get_optimization_settings();
$capabilities = $admin_optimize->get_server_capabilities();
$state = $admin_optimize->get_state();
$resize_settings = $admin_optimize->get_resize_settings();
$resize_stats = $admin_optimize->get_resize_stats();
$optimizer_caps = $admin_optimize->get_optimizer_capabilities();
$backup_info = $admin_optimize->get_backup_info();
$conversion_info = $admin_optimize->get_conversion_info();

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

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
            <p class="mt-hero-description"><?php esc_html_e('Compress, optimize, and resize your media library images', 'media-toolkit'); ?></p>
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
        <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Compress, optimize, and resize your media library images', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <nav class="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-xl">
        <a href="?page=media-toolkit-optimize&tab=dashboard" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'dashboard' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Dashboard', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-optimize&tab=optimize" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'optimize' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-performance"></span>
            <?php esc_html_e('Optimize', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-optimize&tab=resize" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'resize' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-image-crop"></span>
            <?php esc_html_e('Resize', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-optimize&tab=settings" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'settings' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('Settings', 'media-toolkit'); ?>
        </a>
    </nav>

    <!-- Tab Content -->
    <div class="bg-gray-100 rounded-xl p-6 animate-fade-in">
        <?php if ($active_tab === 'dashboard'): ?>
            <!-- ==================== DASHBOARD TAB ==================== -->
            
            <!-- Stats Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-6">
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
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Optimized', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900" id="stat-optimized_images"><?php echo esc_html($stats['optimized_images']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 text-amber-600">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900" id="stat-pending_images"><?php echo esc_html($stats['pending_images']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 text-blue-600">
                            <span class="dashicons dashicons-database"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Space Saved', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900" id="stat-total_saved_formatted"><?php echo esc_html($stats['total_saved_formatted']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100 text-purple-600">
                            <span class="dashicons dashicons-chart-pie"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Avg. Savings', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-purple-600" id="stat-average_savings_percent"><?php echo esc_html($stats['average_savings_percent'] ?? 0); ?>%</span>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mb-6">
                <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="dashicons dashicons-chart-line text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Optimization Progress', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all" id="btn-rebuild-stats" title="<?php esc_attr_e('Rebuild statistics from database', 'media-toolkit'); ?>">
                            <span class="dashicons dashicons-update text-sm"></span>
                            <?php esc_html_e('Rebuild Stats', 'media-toolkit'); ?>
                        </button>
                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="progress-percentage"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
                    </div>
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

            <!-- Resize Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 text-blue-600">
                            <span class="dashicons dashicons-image-crop"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Images Resized', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($resize_stats['total_resized']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-green-100 text-green-600">
                            <span class="dashicons dashicons-database"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Resize Saved', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($resize_stats['total_bytes_saved_formatted']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-orange-100 text-orange-600">
                            <span class="dashicons dashicons-images-alt"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('BMP Converted', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($resize_stats['total_bmp_converted']); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl <?php echo $resize_settings['enabled'] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'; ?>">
                            <span class="dashicons <?php echo $resize_settings['enabled'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Auto-Resize', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold <?php echo $resize_settings['enabled'] ? 'text-green-600' : 'text-gray-400'; ?>">
                        <?php echo $resize_settings['enabled'] ? esc_html__('Enabled', 'media-toolkit') : esc_html__('Disabled', 'media-toolkit'); ?>
                    </span>
                </div>
            </div>

            <!-- Server Capabilities -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="dashicons dashicons-desktop text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Server Capabilities', 'media-toolkit'); ?></h3>
                    </div>
                    <?php if ($capabilities['optimization_available'] ?? false): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-green-700 bg-green-100 rounded-full">
                            <span class="dashicons dashicons-yes-alt text-sm"></span>
                            <?php esc_html_e('Optimization Ready', 'media-toolkit'); ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-red-700 bg-red-100 rounded-full">
                            <span class="dashicons dashicons-warning text-sm"></span>
                            <?php esc_html_e('Issues Detected', 'media-toolkit'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Functional Test Status -->
                    <?php if (!($capabilities['functional_test'] ?? false)): ?>
                    <div class="flex gap-3 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200">
                        <span class="dashicons dashicons-warning text-red-600 flex-shrink-0 mt-0.5"></span>
                        <div>
                            <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('Functional Test Failed', 'media-toolkit'); ?></strong>
                            <p class="text-sm opacity-90 m-0">
                                <?php echo esc_html($capabilities['functional_test_error'] ?? __('Unknown error', 'media-toolkit')); ?>
                            </p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="flex gap-3 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200">
                        <span class="dashicons dashicons-yes-alt text-green-600 flex-shrink-0 mt-0.5"></span>
                        <div>
                            <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('Functional Test Passed', 'media-toolkit'); ?></strong>
                            <p class="text-sm opacity-90 m-0">
                                <?php esc_html_e('Image optimization is working correctly on this server.', 'media-toolkit'); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Image Libraries -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3"><?php esc_html_e('Image Libraries', 'media-toolkit'); ?></h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- GD Library -->
                            <div class="flex items-center gap-4 p-4 bg-white border rounded-lg <?php echo $capabilities['gd'] ? 'border-green-200 bg-gradient-to-br from-white to-green-50' : 'border-red-200 bg-gradient-to-br from-white to-red-50'; ?>">
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg <?php echo $capabilities['gd'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                    <span class="dashicons <?php echo $capabilities['gd'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900 truncate"><?php esc_html_e('GD Library', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500 truncate">
                                        <?php echo $capabilities['gd'] 
                                            ? esc_html($capabilities['gd_version'] ?? __('Available', 'media-toolkit'))
                                            : esc_html__('Not available', 'media-toolkit'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- ImageMagick -->
                            <div class="flex items-center gap-4 p-4 bg-white border rounded-lg <?php echo $capabilities['imagick'] ? 'border-green-200 bg-gradient-to-br from-white to-green-50' : 'border-gray-200'; ?>">
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg <?php echo $capabilities['imagick'] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'; ?>">
                                    <span class="dashicons <?php echo $capabilities['imagick'] ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900 truncate"><?php esc_html_e('ImageMagick', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500 truncate" title="<?php echo esc_attr($capabilities['imagick_version'] ?? ''); ?>">
                                        <?php 
                                        if ($capabilities['imagick'] && !empty($capabilities['imagick_version'])) {
                                            // Extract just the version number for display
                                            if (preg_match('/ImageMagick\s+([\d.]+)/', $capabilities['imagick_version'], $matches)) {
                                                echo esc_html('v' . $matches[1]);
                                            } else {
                                                echo esc_html__('Available', 'media-toolkit');
                                            }
                                        } else {
                                            echo esc_html__('Not available', 'media-toolkit');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <!-- WordPress Editor -->
                            <div class="flex items-center gap-4 p-4 bg-white border border-blue-200 rounded-lg bg-gradient-to-br from-white to-blue-50">
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                                    <span class="dashicons dashicons-wordpress"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900 truncate"><?php esc_html_e('WP Image Editor', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500 truncate">
                                        <?php 
                                        $editor = $capabilities['wp_editor'] ?? 'unknown';
                                        if ($editor === 'imagick') {
                                            esc_html_e('Using ImageMagick', 'media-toolkit');
                                        } elseif ($editor === 'gd') {
                                            esc_html_e('Using GD Library', 'media-toolkit');
                                        } elseif ($editor === 'none') {
                                            esc_html_e('No editor available', 'media-toolkit');
                                        } else {
                                            esc_html_e('Unknown', 'media-toolkit');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Supported Formats -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3"><?php esc_html_e('Supported Formats', 'media-toolkit'); ?></h4>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $formats = [
                                'jpeg_support' => 'JPEG',
                                'png_support' => 'PNG',
                                'gif_support' => 'GIF',
                                'webp_support' => 'WebP',
                                'avif_support' => 'AVIF',
                            ];
                            foreach ($formats as $key => $label):
                                $supported = $capabilities[$key] ?? false;
                            ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg <?php echo $supported ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'; ?>">
                                <span class="dashicons <?php echo $supported ? 'dashicons-yes' : 'dashicons-no'; ?> text-sm"></span>
                                <?php echo esc_html($label); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Server Limits -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3"><?php esc_html_e('Server Limits', 'media-toolkit'); ?></h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <span class="dashicons dashicons-database text-gray-500"></span>
                                <div>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Memory Limit', 'media-toolkit'); ?></span>
                                    <span class="block text-sm font-semibold text-gray-900"><?php echo esc_html($capabilities['max_memory'] ?: '-'); ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <span class="dashicons dashicons-clock text-gray-500"></span>
                                <div>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Max Execution', 'media-toolkit'); ?></span>
                                    <span class="block text-sm font-semibold text-gray-900"><?php echo esc_html(($capabilities['max_execution_time'] ?: '0') . 's'); ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <span class="dashicons dashicons-upload text-gray-500"></span>
                                <div>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Upload Max Size', 'media-toolkit'); ?></span>
                                    <span class="block text-sm font-semibold text-gray-900"><?php echo esc_html($capabilities['upload_max_filesize'] ?: '-'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Optimization Tools -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mt-6">
                <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="dashicons dashicons-admin-tools text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Available Optimization Tools', 'media-toolkit'); ?></h3>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <?php 
                    $by_format = $optimizer_caps['by_format'] ?? [];
                    $all_caps = $optimizer_caps['capabilities'] ?? [];
                    $format_labels = [
                        'jpeg' => 'JPEG',
                        'png' => 'PNG',
                        'gif' => 'GIF',
                        'webp' => 'WebP',
                        'avif' => 'AVIF',
                        'svg' => 'SVG',
                    ];
                    ?>
                    
                    <!-- Tools by Format -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($by_format as $format => $info): ?>
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-bold text-gray-900"><?php echo esc_html($format_labels[$format] ?? strtoupper($format)); ?></span>
                                <?php if (!empty($info['best'])): ?>
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded">
                                    <?php echo esc_html($all_caps[$info['best']]['name'] ?? $info['best']); ?>
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-500 bg-gray-200 rounded">
                                    <?php esc_html_e('None', 'media-toolkit'); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-1.5">
                                <?php 
                                $available = $info['available'] ?? [];
                                $missing = $info['missing'] ?? [];
                                
                                foreach ($available as $opt_id):
                                    $opt = $all_caps[$opt_id] ?? null;
                                    if (!$opt) continue;
                                ?>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-green-700">
                                        <span class="dashicons dashicons-yes text-green-500" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                        <?php echo esc_html($opt['name']); ?>
                                    </span>
                                    <?php if (!empty($opt['version'])): ?>
                                    <span class="text-gray-400">v<?php echo esc_html($opt['version']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php foreach (array_slice($missing, 0, 2) as $opt_id):
                                    $opt = $all_caps[$opt_id] ?? null;
                                    if (!$opt) continue;
                                ?>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-gray-400">
                                        <span class="dashicons dashicons-minus text-gray-300" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                        <?php echo esc_html($opt['name']); ?>
                                    </span>
                                    <span class="text-gray-300"><?php esc_html_e('Not installed', 'media-toolkit'); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Recommendations -->
                    <?php 
                    $recommendations = $optimizer_caps['recommendations'] ?? [];
                    if (!empty($recommendations)): 
                    ?>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3"><?php esc_html_e('Recommendations', 'media-toolkit'); ?></h4>
                        <div class="space-y-2">
                            <?php foreach (array_slice($recommendations, 0, 3) as $rec): ?>
                            <div class="flex items-start gap-3 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                <span class="dashicons dashicons-lightbulb text-blue-500 flex-shrink-0 mt-0.5"></span>
                                <div class="min-w-0">
                                    <p class="text-sm text-blue-800 m-0"><?php echo esc_html($rec['benefit']); ?></p>
                                    <details class="mt-1">
                                        <summary class="text-xs text-blue-600 cursor-pointer hover:underline"><?php esc_html_e('Installation instructions', 'media-toolkit'); ?></summary>
                                        <pre class="mt-2 p-2 text-xs bg-white rounded border border-blue-100 overflow-x-auto whitespace-pre-wrap"><?php echo esc_html(str_replace('\n', "\n", $rec['install'])); ?></pre>
                                    </details>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'optimize'): ?>
            <!-- ==================== OPTIMIZE TAB ==================== -->
            
            <!-- Batch Processing Controls -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mb-6">
                <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <span class="dashicons dashicons-controls-play text-gray-700"></span>
                    <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Batch Optimization', 'media-toolkit'); ?></h3>
                </div>
                <div class="p-6">
                    <div class="mb-5">
                        <label for="batch-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                        <select id="batch-size" class="mt-select w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <option value="10"><?php esc_html_e('10 images per batch', 'media-toolkit'); ?></option>
                            <option value="25" selected><?php esc_html_e('25 images per batch', 'media-toolkit'); ?></option>
                            <option value="50"><?php esc_html_e('50 images per batch', 'media-toolkit'); ?></option>
                        </select>
                        <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Smaller batches are safer for shared hosting', 'media-toolkit'); ?></p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-start-optimization">
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
                        <div class="space-y-3">
                            <!-- Progress Bar -->
                            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Batch Progress', 'media-toolkit'); ?></span>
                                    <span class="inline-flex items-center px-2.5 py-1 text-sm font-bold text-white bg-gray-800 rounded-full" id="batch-progress-percentage">0%</span>
                                </div>
                                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500 ease-out" id="batch-progress-bar" style="width: 0%"></div>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs text-gray-500">
                                        <span id="processed-count"><?php echo esc_html($state['processed']); ?></span> / <span id="total-count"><?php echo esc_html($state['total_files']); ?></span> <?php esc_html_e('images', 'media-toolkit'); ?>
                                    </span>
                                    <span class="<?php echo $state['failed'] > 0 ? '' : 'hidden'; ?> px-2 py-0.5 text-xs font-medium rounded mt-badge-error" id="failed-badge">
                                        <span id="failed-count"><?php echo esc_html($state['failed']); ?></span> <?php esc_html_e('failed', 'media-toolkit'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Status & Savings Info -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100">
                                        <span class="dashicons dashicons-clock text-gray-600"></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                        <span class="text-sm font-semibold text-gray-900" id="status-text"><?php echo esc_html(ucfirst($state['status'])); ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100">
                                        <span class="dashicons dashicons-database text-emerald-600"></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-gray-500"><?php esc_html_e('Batch Saved', 'media-toolkit'); ?></span>
                                        <span class="text-sm font-semibold text-emerald-600" id="batch-bytes-saved">0 B</span>
                                    </div>
                                </div>
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

        <?php elseif ($active_tab === 'resize'): ?>
            <!-- ==================== RESIZE TAB ==================== -->
            
            <!-- Info Notice -->
            <div class="flex gap-3 p-4 rounded-xl bg-blue-50 text-blue-800 mb-6">
                <span class="dashicons dashicons-info text-blue-600 flex-shrink-0 mt-0.5"></span>
                <div>
                    <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('Automatic Image Resizing', 'media-toolkit'); ?></strong>
                    <p class="text-sm opacity-90 m-0">
                        <?php esc_html_e('Automatically resizes images (JPEG, GIF, PNG, WebP) when they are uploaded to within a given maximum width and/or height. This reduces server space usage, speeds up your website, and boosts SEO.', 'media-toolkit'); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Resize Settings Card -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                        <span class="dashicons dashicons-image-crop text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Resize Settings', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Enable Toggle -->
                        <div class="pb-4 border-b border-gray-200">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="resize-enabled" <?php checked($resize_settings['enabled']); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Enable Auto-Resize on Upload', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Automatically resize oversized images when uploaded', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                        </div>

                        <!-- Max Width -->
                        <div>
                            <label for="resize-max-width" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Max Width (pixels)', 'media-toolkit'); ?></label>
                            <input type="number" id="resize-max-width" min="0" max="10000" step="1" value="<?php echo esc_attr($resize_settings['max_width']); ?>" 
                                   class="mt-select w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Set to 0 for no width limit. Recommended: 2560 for retina displays', 'media-toolkit'); ?></p>
                        </div>

                        <!-- Max Height -->
                        <div>
                            <label for="resize-max-height" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Max Height (pixels)', 'media-toolkit'); ?></label>
                            <input type="number" id="resize-max-height" min="0" max="10000" step="1" value="<?php echo esc_attr($resize_settings['max_height']); ?>" 
                                   class="mt-select w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Set to 0 for no height limit. Recommended: 2560 for retina displays', 'media-toolkit'); ?></p>
                        </div>

                        <!-- JPEG Quality -->
                        <div>
                            <label for="resize-jpeg-quality" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('JPEG Quality', 'media-toolkit'); ?></label>
                            <div class="flex items-center gap-3">
                                <input type="range" id="resize-jpeg-quality" min="60" max="100" value="<?php echo esc_attr($resize_settings['jpeg_quality']); ?>" class="flex-1 max-w-xs">
                                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full mt-badge-info" id="resize-jpeg-quality-value"><?php echo esc_html($resize_settings['jpeg_quality']); ?></span>
                            </div>
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Quality used when resizing JPEG images. Higher = better quality, larger file.', 'media-toolkit'); ?></p>
                        </div>

                        <!-- Convert BMP to JPEG -->
                        <div class="pt-2">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="resize-convert-bmp" <?php checked($resize_settings['convert_bmp_to_jpg']); ?>>
                                <span class="mt-toggle-slider"></span>
                                <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Convert BMP to JPEG', 'media-toolkit'); ?></span>
                            </label>
                            <p class="mt-2 ml-14 text-sm text-gray-500"><?php esc_html_e('Automatically convert BMP images to JPEG for significant space savings', 'media-toolkit'); ?></p>
                        </div>

                        <div class="flex items-center gap-3 pt-3">
                            <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-resize-settings">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                            </button>
                            <span class="text-sm text-gray-500" id="resize-settings-status"></span>
                        </div>
                    </div>
                </div>

                <!-- Resize Info Card -->
                <div class="space-y-6">
                    <!-- Resize Stats -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                            <span class="dashicons dashicons-chart-bar text-gray-700"></span>
                            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Resize Statistics', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 rounded-xl text-center">
                                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($resize_stats['total_resized']); ?></span>
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Images Resized', 'media-toolkit'); ?></span>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl text-center">
                                    <span class="block text-2xl font-bold text-green-600"><?php echo esc_html($resize_stats['total_bytes_saved_formatted']); ?></span>
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Space Saved', 'media-toolkit'); ?></span>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl text-center">
                                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($resize_stats['total_bmp_converted']); ?></span>
                                    <span class="text-sm text-gray-500"><?php esc_html_e('BMP Converted', 'media-toolkit'); ?></span>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-xl text-center">
                                    <span class="block text-2xl font-bold <?php echo $resize_settings['enabled'] ? 'text-green-600' : 'text-gray-400'; ?>">
                                        <?php echo $resize_settings['enabled'] ? esc_html__('ON', 'media-toolkit') : esc_html__('OFF', 'media-toolkit'); ?>
                                    </span>
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common Presets -->
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                            <span class="dashicons dashicons-layout text-gray-700"></span>
                            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Quick Presets', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 gap-3">
                                <button type="button" class="resize-preset flex items-center justify-between p-3 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-all" data-width="1920" data-height="1920">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('Full HD', 'media-toolkit'); ?></span>
                                        <span class="text-xs text-gray-500">1920 × 1920 px</span>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt text-gray-400"></span>
                                </button>
                                <button type="button" class="resize-preset flex items-center justify-between p-3 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-all" data-width="2560" data-height="2560">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('2K / Retina', 'media-toolkit'); ?></span>
                                        <span class="text-xs text-gray-500">2560 × 2560 px</span>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt text-gray-400"></span>
                                </button>
                                <button type="button" class="resize-preset flex items-center justify-between p-3 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-all" data-width="3840" data-height="3840">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('4K Ultra HD', 'media-toolkit'); ?></span>
                                        <span class="text-xs text-gray-500">3840 × 3840 px</span>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt text-gray-400"></span>
                                </button>
                                <button type="button" class="resize-preset flex items-center justify-between p-3 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-all" data-width="1200" data-height="1200">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('Blog / Web', 'media-toolkit'); ?></span>
                                        <span class="text-xs text-gray-500">1200 × 1200 px</span>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt text-gray-400"></span>
                                </button>
                            </div>
                            <p class="mt-4 text-xs text-gray-500 text-center"><?php esc_html_e('Click a preset to apply dimensions, then save settings', 'media-toolkit'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'settings'): ?>
            <!-- ==================== SETTINGS TAB ==================== -->
            
            <!-- Info Notice -->
            <div class="flex gap-3 p-4 rounded-xl bg-blue-50 text-blue-800 mb-6">
                <span class="dashicons dashicons-info text-blue-600 flex-shrink-0 mt-0.5"></span>
                <div>
                    <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('Optimization Settings', 'media-toolkit'); ?></strong>
                    <p class="text-sm opacity-90 m-0">
                        <?php esc_html_e('These settings apply to both automatic optimization on upload and batch optimization.', 'media-toolkit'); ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Compression Settings Card -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                        <span class="dashicons dashicons-admin-settings text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Compression Settings', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Enable Optimize on Upload Toggle -->
                        <div class="pb-4 border-b border-gray-200">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="optimize-on-upload" <?php checked($opt_settings['optimize_on_upload'] ?? false); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Optimize on Upload', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Automatically compress images when uploaded (after resize, before cloud upload)', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                        </div>

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
                            <select id="max-file-size" class="mt-select w-full max-w-xs px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
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
                    </div>
                </div>

                <!-- Backup & Conversion Settings -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                        <span class="dashicons dashicons-backup text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Backup & Format Conversion', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Backup Original Toggle -->
                        <div class="pb-4 border-b border-gray-200">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="backup-enabled" <?php checked($backup_info['settings']['enabled'] ?? false); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Keep Original Backup', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Save original image as filename_original.ext before optimization', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                            <?php 
                            $backup_stats = $backup_info['stats'] ?? [];
                            if (($backup_stats['total'] ?? 0) > 0): 
                            ?>
                            <p class="mt-2 ml-14 text-xs text-gray-500">
                                <?php printf(
                                    esc_html__('%d backups stored (%s)', 'media-toolkit'),
                                    $backup_stats['total'],
                                    size_format($backup_stats['total_size'] ?? 0)
                                ); ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- WebP Conversion Toggle -->
                        <div class="pb-4 border-b border-gray-200">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="webp-enabled" <?php checked($conversion_info['settings']['webp_enabled'] ?? false); ?> <?php disabled(!($conversion_info['stats']['webp_available'] ?? false)); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Generate WebP Version', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Create .webp alongside original format (20-30% smaller)', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                            <?php if (!($conversion_info['stats']['webp_available'] ?? false)): ?>
                            <p class="mt-2 ml-14 text-xs text-amber-600">
                                <span class="dashicons dashicons-warning" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                <?php esc_html_e('WebP conversion not available. Install cwebp or enable ImageMagick WebP support.', 'media-toolkit'); ?>
                            </p>
                            <?php else: ?>
                            <div class="mt-3 ml-14">
                                <label for="webp-quality" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('WebP Quality', 'media-toolkit'); ?></label>
                                <div class="flex items-center gap-2">
                                    <input type="range" id="webp-quality" min="50" max="95" value="<?php echo esc_attr($conversion_info['settings']['webp_quality'] ?? 80); ?>" class="w-32">
                                    <span class="text-xs text-gray-600" id="webp-quality-value"><?php echo esc_html($conversion_info['settings']['webp_quality'] ?? 80); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- AVIF Conversion Toggle -->
                        <div>
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="avif-enabled" <?php checked($conversion_info['settings']['avif_enabled'] ?? false); ?> <?php disabled(!($conversion_info['stats']['avif_available'] ?? false)); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Generate AVIF Version', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Create .avif alongside original format (20-50% smaller than WebP)', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                            <?php if (!($conversion_info['stats']['avif_available'] ?? false)): ?>
                            <p class="mt-2 ml-14 text-xs text-amber-600">
                                <span class="dashicons dashicons-warning" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                <?php esc_html_e('AVIF conversion not available. Install avifenc or upgrade to ImageMagick 7+ with AVIF support.', 'media-toolkit'); ?>
                            </p>
                            <?php else: ?>
                            <div class="mt-3 ml-14">
                                <label for="avif-quality" class="block text-xs font-medium text-gray-700 mb-1"><?php esc_html_e('AVIF Quality', 'media-toolkit'); ?></label>
                                <div class="flex items-center gap-2">
                                    <input type="range" id="avif-quality" min="30" max="80" value="<?php echo esc_attr($conversion_info['settings']['avif_quality'] ?? 50); ?>" class="w-32">
                                    <span class="text-xs text-gray-600" id="avif-quality-value"><?php echo esc_html($conversion_info['settings']['avif_quality'] ?? 50); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Conversion Stats -->
                        <?php 
                        $conv_stats = $conversion_info['stats'] ?? [];
                        if (($conv_stats['webp_count'] ?? 0) > 0 || ($conv_stats['avif_count'] ?? 0) > 0): 
                        ?>
                        <div class="pt-3 border-t border-gray-200">
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <?php if (($conv_stats['webp_count'] ?? 0) > 0): ?>
                                <span><strong><?php echo esc_html($conv_stats['webp_count']); ?></strong> <?php esc_html_e('WebP files', 'media-toolkit'); ?></span>
                                <?php endif; ?>
                                <?php if (($conv_stats['avif_count'] ?? 0) > 0): ?>
                                <span><strong><?php echo esc_html($conv_stats['avif_count']); ?></strong> <?php esc_html_e('AVIF files', 'media-toolkit'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="mt-6">
                <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-optimize-settings">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                </button>
                <span class="ml-3 text-sm text-gray-500" id="settings-status"></span>
            </div>

        <?php endif; ?>
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
