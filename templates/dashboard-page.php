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

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
        
        <?php if ($hasBanner): ?>
        <!-- Hero Banner -->
        <div class="mt-hero">
            <img src="<?php echo esc_url($bannerUrl); ?>" alt="Media Toolkit" class="mt-hero-banner">
            <div class="mt-hero-overlay">
                <h1 class="mt-hero-title"><?php esc_html_e('Media Toolkit', 'media-toolkit'); ?></h1>
                <p class="mt-hero-description"><?php esc_html_e('Complete media management toolkit for WordPress. Cloud storage offloading, CDN integration, and image optimization.', 'media-toolkit'); ?></p>
                <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
            </div>
        </div>
        <?php else: ?>
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </span>
                <?php esc_html_e('Media Toolkit', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Complete media management toolkit for WordPress. Cloud storage offloading, CDN integration, and image optimization.', 'media-toolkit'); ?></p>
        </header>
        <?php endif; ?>

        <?php if (!$is_configured): ?>
        <!-- Setup Alert -->
        <div class="flex gap-4 p-6 rounded-xl border-l-4 border-amber-400 mt-alert-warning">
            <span class="dashicons dashicons-admin-generic text-amber-600 flex-shrink-0"></span>
            <div>
                <strong class="block text-lg font-semibold mb-2"><?php esc_html_e('Complete Your Setup', 'media-toolkit'); ?></strong>
                <p class="mb-4 opacity-90"><?php esc_html_e('Configure your storage provider to start offloading media files to the cloud. This only takes a minute.', 'media-toolkit'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=media-toolkit-settings')); ?>" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm transition-all">
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <?php esc_html_e('Configure Now', 'media-toolkit'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex flex-col gap-4 bg-gray-100 rounded-xl p-6">
            <!-- Main Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-format-gallery"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('WordPress Media Files', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-3xl font-bold text-gray-900"><?php echo number_format($dashboard_stats['wp_attachments'] ?? 0); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-cloud-upload"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Files on Storage', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-3xl font-bold text-gray-900"><?php echo number_format($dashboard_stats['migrated_attachments'] ?? 0); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <?php 
                    $sync_pct = $dashboard_stats['sync_percentage'] ?? 0;
                    $icon_bg = $sync_pct >= 100 ? 'bg-green-100 text-green-600' : ($sync_pct > 0 ? 'bg-amber-100 text-amber-600' : 'bg-red-100 text-red-600');
                    ?>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl <?php echo esc_attr($icon_bg); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Synced', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-3xl font-bold text-gray-900"><?php echo esc_html($sync_pct); ?>%</span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-database"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Total Storage Used', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-3xl font-bold text-gray-900"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                </div>
            </div>

            <!-- Secondary Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-media-default"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Total Files (incl. thumbnails)', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Uploaded Today', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($dashboard_stats['files_today'] ?? 0); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Errors (7 days)', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($dashboard_stats['errors_last_7_days'] ?? 0); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-update"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Last Storage Sync', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html($dashboard_stats['s3_synced_at_formatted'] ?? 'Never'); ?></span>
                </div>
            </div>
        </div>

        <!-- Activity Chart Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-chart-line text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Upload Activity (Last 7 Days)', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6">
                <div id="sparkline-container">
                    <canvas id="sparkline-chart" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-sm text-gray-400 py-6">
            <p>
                <?php
                printf(
                    esc_html__('Developed by %s', 'media-toolkit'),
                    '<a href="https://metodo.dev" target="_blank" rel="noopener" class="font-medium hover:text-accent-500">Michele Marri - Metodo.dev</a>'
                );
                ?>
                &bull;
                <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
            </p>
        </footer>

    </div>
</div>
