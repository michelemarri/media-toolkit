<?php
/**
 * CloudSync page template
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

// Get CloudSync status
$cloud_sync = $plugin->get_cloud_sync();
$status = $cloud_sync ? $cloud_sync->analyze() : null;
$status_data = $status ? $status->toArray() : [];

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
            <h1 class="mt-hero-title"><?php esc_html_e('CloudSync', 'media-toolkit'); ?></h1>
            <p class="mt-hero-description"><?php esc_html_e('Synchronize media files with cloud storage', 'media-toolkit'); ?></p>
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
            <?php esc_html_e('CloudSync', 'media-toolkit'); ?>
        </h1>
        <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Synchronize media files with cloud storage', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <?php if (!$is_configured): ?>
    <!-- Error Alert -->
    <div class="flex gap-4 p-5 rounded-xl border mt-alert-error">
        <span class="dashicons dashicons-warning text-red-600 flex-shrink-0"></span>
        <div>
            <strong class="block font-semibold mb-1"><?php esc_html_e('Storage is not configured.', 'media-toolkit'); ?></strong>
            <p><?php printf(esc_html__('Please %sconfigure your storage provider%s before using CloudSync.', 'media-toolkit'), '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings')) . '" class="underline font-medium">', '</a>'); ?></p>
        </div>
    </div>
    <?php else: ?>

    <!-- Status Overview Card -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-chart-pie text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Sync Status', 'media-toolkit'); ?></h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full" id="status-badge">
                    <?php
                    $badge_class = 'bg-gray-100 text-gray-700';
                    $badge_text = __('Unknown', 'media-toolkit');
                    
                    if ($status) {
                        switch ($status->overallStatus) {
                            case 'synced':
                                $badge_class = 'bg-green-100 text-green-700';
                                $badge_text = __('Fully Synced', 'media-toolkit');
                                break;
                            case 'pending_sync':
                                $badge_class = 'bg-yellow-100 text-yellow-700';
                                $badge_text = __('Pending Sync', 'media-toolkit');
                                break;
                            case 'integrity_issues':
                                $badge_class = 'bg-red-100 text-red-700';
                                $badge_text = __('Integrity Issues', 'media-toolkit');
                                break;
                            case 'not_started':
                                $badge_class = 'bg-gray-100 text-gray-700';
                                $badge_text = __('Not Started', 'media-toolkit');
                                break;
                            case 'partial':
                                $badge_class = 'bg-blue-100 text-blue-700';
                                $badge_text = __('Partially Synced', 'media-toolkit');
                                break;
                        }
                    }
                    ?>
                    <span class="<?php echo esc_attr($badge_class); ?> px-3 py-1 rounded-full"><?php echo esc_html($badge_text); ?></span>
                </span>
                <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all" id="btn-refresh-status">
                    <span class="dashicons dashicons-update text-sm"></span>
                    <?php esc_html_e('Refresh', 'media-toolkit'); ?>
                </button>
            </div>
        </div>
        <div class="p-6">
            <!-- Progress Bar -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Sync Progress', 'media-toolkit'); ?></span>
                    <span class="text-sm font-bold text-gray-900" id="sync-percentage"><?php echo esc_html($status_data['sync_percentage'] ?? 0); ?>%</span>
                </div>
                <div class="mt-progress-bar mt-progress-animated">
                    <div class="mt-progress-fill" id="sync-progress" style="width: <?php echo esc_attr($status_data['sync_percentage'] ?? 0); ?>%"></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <div class="text-sm text-gray-500 mb-1"><?php esc_html_e('Total Files', 'media-toolkit'); ?></div>
                    <div class="text-xl font-bold text-gray-900" id="stat-total"><?php echo esc_html($status_data['total_attachments'] ?? 0); ?></div>
                </div>
                <div class="p-4 bg-green-50 rounded-xl">
                    <div class="text-sm text-green-600 mb-1"><?php esc_html_e('On Cloud', 'media-toolkit'); ?></div>
                    <div class="text-xl font-bold text-green-700" id="stat-migrated"><?php echo esc_html($status_data['migrated_to_cloud'] ?? 0); ?></div>
                </div>
                <div class="p-4 bg-yellow-50 rounded-xl">
                    <div class="text-sm text-yellow-600 mb-1"><?php esc_html_e('Pending', 'media-toolkit'); ?></div>
                    <div class="text-xl font-bold text-yellow-700" id="stat-pending"><?php echo esc_html($status_data['pending_migration'] ?? 0); ?></div>
                </div>
                <div class="p-4 <?php echo ($status_data['integrity_issues'] ?? 0) > 0 ? 'bg-red-50' : 'bg-gray-50'; ?> rounded-xl">
                    <div class="text-sm <?php echo ($status_data['integrity_issues'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-500'; ?> mb-1"><?php esc_html_e('Issues', 'media-toolkit'); ?></div>
                    <div class="text-xl font-bold <?php echo ($status_data['integrity_issues'] ?? 0) > 0 ? 'text-red-700' : 'text-gray-900'; ?>" id="stat-issues"><?php echo esc_html($status_data['integrity_issues'] ?? 0); ?></div>
                </div>
            </div>

            <?php if (!empty($status_data['last_sync_at'])): ?>
            <div class="mt-4 text-sm text-gray-500">
                <?php printf(esc_html__('Last sync: %s', 'media-toolkit'), esc_html($status_data['last_sync_at'])); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Optimization Status Card -->
    <?php 
    $opt_percentage = $status_data['optimization_percentage'] ?? 0;
    $opt_done = $status_data['optimized_images'] ?? 0;
    $opt_pending = $status_data['pending_optimization'] ?? 0;
    $opt_saved = $status_data['total_bytes_saved_formatted'] ?? '0 B';
    $show_optimization_card = $opt_percentage < 100;
    ?>
    <?php if ($show_optimization_card): ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-amber-50 to-amber-100 border-b border-amber-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-performance text-amber-600"></span>
                <h3 class="text-base font-semibold text-amber-900"><?php esc_html_e('Optimization Recommended', 'media-toolkit'); ?></h3>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-amber-200 text-amber-800">
                <?php echo esc_html($opt_percentage); ?>% <?php esc_html_e('Optimized', 'media-toolkit'); ?>
            </span>
        </div>
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <p class="text-sm text-gray-700 mb-3">
                        <?php printf(
                            esc_html__('%d images are not optimized. Optimizing before uploading to cloud storage will reduce bandwidth usage and storage costs.', 'media-toolkit'),
                            $opt_pending
                        ); ?>
                    </p>
                    <div class="flex items-center gap-4 text-sm text-gray-500">
                        <span><strong class="text-green-700"><?php echo esc_html($opt_done); ?></strong> <?php esc_html_e('optimized', 'media-toolkit'); ?></span>
                        <span><strong class="text-amber-700"><?php echo esc_html($opt_pending); ?></strong> <?php esc_html_e('pending', 'media-toolkit'); ?></span>
                        <?php if ($opt_saved !== '0 B'): ?>
                        <span><strong class="text-blue-700"><?php echo esc_html($opt_saved); ?></strong> <?php esc_html_e('saved', 'media-toolkit'); ?></span>
            <?php endif; ?>
                </div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=media-toolkit-optimize')); ?>" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-all flex-shrink-0">
                    <span class="dashicons dashicons-performance text-sm"></span>
                    <?php esc_html_e('Optimize Now', 'media-toolkit'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Suggested Actions Card -->
    <?php 
    // Filter out optimize_before_sync since it's already shown in the optimization card above
    $filtered_actions = array_filter($status_data['suggested_actions'] ?? [], function($action) {
        return $action['type'] !== 'optimize_before_sync';
    });
    ?>
    <?php if (!empty($filtered_actions)): ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <span class="dashicons dashicons-lightbulb text-gray-700"></span>
            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Suggested Actions', 'media-toolkit'); ?></h3>
        </div>
        <div class="p-6">
            <div class="space-y-3" id="suggested-actions">
                <?php foreach ($filtered_actions as $action): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <?php
                        $icon_class = 'dashicons-update';
                        $priority_class = 'text-gray-500';
                        
                        switch ($action['type']) {
                            case 'integrity_fix':
                                $icon_class = 'dashicons-warning';
                                $priority_class = 'text-red-500';
                                break;
                            case 'sync':
                                $icon_class = 'dashicons-cloud-upload';
                                $priority_class = 'text-yellow-500';
                                break;
                            case 'cleanup_orphans':
                                $icon_class = 'dashicons-trash';
                                $priority_class = 'text-gray-500';
                                break;
                        }
                        ?>
                        <span class="dashicons <?php echo esc_attr($icon_class); ?> <?php echo esc_attr($priority_class); ?>"></span>
                        <div>
                            <div class="font-semibold text-gray-900"><?php echo esc_html($action['title']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo esc_html($action['description']); ?></div>
                        </div>
                    </div>
                    <?php if ($action['can_auto_fix']): ?>
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all action-btn" data-action="<?php echo esc_attr($action['type']); ?>">
                        <span class="dashicons dashicons-controls-play text-sm"></span>
                        <?php esc_html_e('Fix', 'media-toolkit'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Options & Controls Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Options Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-admin-settings text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Sync Options', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="sync-mode" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Sync Mode', 'media-toolkit'); ?></label>
                    <select id="sync-mode" class="mt-select w-full max-w-md px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                        <option value="sync"><?php esc_html_e('Upload pending files to cloud', 'media-toolkit'); ?></option>
                        <option value="integrity"><?php esc_html_e('Check and fix integrity issues', 'media-toolkit'); ?></option>
                        <option value="full"><?php esc_html_e('Full sync + integrity check', 'media-toolkit'); ?></option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Choose what type of sync to perform', 'media-toolkit'); ?></p>
                </div>
                
                <div>
                    <label for="batch-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                    <select id="batch-size" class="mt-select w-full max-w-md px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                        <option value="10"><?php esc_html_e('10 files per batch', 'media-toolkit'); ?></option>
                        <option value="25" selected><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                        <option value="50"><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                        <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Smaller batches are safer but slower', 'media-toolkit'); ?></p>
                </div>
                
                <div class="pt-2">
                    <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="remove-local" <?php checked($settings->should_remove_local_files()); ?>>
                        <span class="mt-toggle-slider"></span>
                        <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Delete local files after sync', 'media-toolkit'); ?></span>
                    </label>
                    <p class="mt-2 ml-14 text-sm text-gray-500">
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded mt-badge-warning mr-1"><?php esc_html_e('Warning', 'media-toolkit'); ?></span>
                        <?php esc_html_e('This will permanently delete local copies. Make sure you have a backup!', 'media-toolkit'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Controls Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-controls-play text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Controls', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6">
                <div class="flex flex-col gap-3">
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-sync">
                        <span class="dashicons dashicons-cloud-upload"></span>
                        <?php esc_html_e('Start Sync', 'media-toolkit'); ?>
                    </button>
                    
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-pause-sync" disabled>
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php esc_html_e('Pause', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-resume-sync" disabled>
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Resume', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-stop-sync" disabled>
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="sync-status" class="hidden mt-5">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900" id="status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900">
                                <span id="processed-count">0</span> / <span id="total-count">0</span>
                                <span class="hidden ml-2 px-2 py-0.5 text-xs font-medium rounded mt-badge-error" id="failed-badge">
                                    <span id="failed-count">0</span> <?php esc_html_e('failed', 'media-toolkit'); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cache Headers Update Card -->
    <?php
    $cache_control = $settings ? $settings->get_cache_control_max_age() : 31536000;
    ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <span class="dashicons dashicons-database text-gray-700"></span>
            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Cache Headers Update', 'media-toolkit'); ?></h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-5"><?php esc_html_e('Update Cache-Control headers on all files already uploaded to cloud storage. This improves CDN caching and website performance.', 'media-toolkit'); ?></p>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Stats & Options -->
                <div class="space-y-5">
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-gray-50 rounded-lg text-center">
                            <div class="text-xs text-gray-500 mb-1"><?php esc_html_e('Total Files', 'media-toolkit'); ?></div>
                            <div class="text-lg font-bold text-gray-900" id="cache-total-files">-</div>
                        </div>
                        <div class="p-3 bg-green-50 rounded-lg text-center">
                            <div class="text-xs text-green-600 mb-1"><?php esc_html_e('Updated', 'media-toolkit'); ?></div>
                            <div class="text-lg font-bold text-green-700" id="cache-processed-files">0</div>
                        </div>
                        <div class="p-3 bg-red-50 rounded-lg text-center">
                            <div class="text-xs text-red-600 mb-1"><?php esc_html_e('Failed', 'media-toolkit'); ?></div>
                            <div class="text-lg font-bold text-red-700" id="cache-failed-files">0</div>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div>
                        <label for="cache_control_value" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Cache-Control Value', 'media-toolkit'); ?></label>
                        <select id="cache_control_value" class="mt-select w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <option value="0" <?php selected($cache_control, 0); ?>><?php esc_html_e('No cache (no-cache, no-store)', 'media-toolkit'); ?></option>
                            <option value="86400" <?php selected($cache_control, 86400); ?>><?php esc_html_e('1 day', 'media-toolkit'); ?></option>
                            <option value="604800" <?php selected($cache_control, 604800); ?>><?php esc_html_e('1 week', 'media-toolkit'); ?></option>
                            <option value="2592000" <?php selected($cache_control, 2592000); ?>><?php esc_html_e('1 month', 'media-toolkit'); ?></option>
                            <option value="31536000" <?php selected($cache_control, 31536000); ?>><?php esc_html_e('1 year — Recommended', 'media-toolkit'); ?></option>
                        </select>
                        <p class="mt-2 text-xs text-gray-500"><?php esc_html_e('This will be applied to all existing files on cloud storage.', 'media-toolkit'); ?></p>
                    </div>
                </div>
                
                <!-- Progress & Controls -->
                <div class="space-y-4">
                    <!-- Progress -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                            <span class="text-sm font-bold text-gray-900" id="cache-progress-percentage">0%</span>
                        </div>
                        <div class="mt-progress-bar mt-progress-animated">
                            <div class="mt-progress-fill" id="cache-progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div id="cache-sync-status" class="hidden p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900" id="cache-status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900">
                                <span id="cache-current-count">0</span> / <span id="cache-total-count">0</span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Controls -->
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-cache-sync">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Start Update', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="hidden flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all" id="btn-cancel-cache-sync">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Cache Sync Log -->
            <div class="mt-5">
                <div class="mt-terminal">
                    <div class="mt-terminal-header">
                        <div class="flex gap-2">
                            <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                            <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                            <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                        </div>
                        <span class="mt-terminal-title"><?php esc_html_e('cache-headers.log', 'media-toolkit'); ?></span>
                    </div>
                    <div class="mt-terminal-body max-h-32" id="cache-sync-log">
                        <div class="mt-terminal-line">
                            <span class="mt-terminal-prompt">$</span>
                            <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Cache update log will appear here...', 'media-toolkit'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Actions -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <span class="dashicons dashicons-admin-tools text-gray-700"></span>
            <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Advanced Actions', 'media-toolkit'); ?></h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all" id="btn-deep-analyze">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Deep Analyze', 'media-toolkit'); ?>
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all" id="btn-view-discrepancies">
                    <span class="dashicons dashicons-editor-table"></span>
                    <?php esc_html_e('View Discrepancies', 'media-toolkit'); ?>
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-lg hover:bg-red-50 transition-all" id="btn-clear-metadata">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear All Metadata', 'media-toolkit'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Sync Log -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-media-text text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Sync Log', 'media-toolkit'); ?></h3>
            </div>
        </div>
        <div class="mt-terminal">
            <div class="mt-terminal-header">
                <div class="flex gap-2">
                    <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                    <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                    <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                </div>
                <span class="mt-terminal-title"><?php esc_html_e('cloudsync.log', 'media-toolkit'); ?></span>
            </div>
            <div class="mt-terminal-body" id="sync-log">
                <div class="mt-terminal-line">
                    <span class="mt-terminal-prompt">$</span>
                    <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Sync log will appear here...', 'media-toolkit'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

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

<!-- Discrepancies Modal -->
<div id="discrepancies-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal max-w-4xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><?php esc_html_e('Discrepancies Details', 'media-toolkit'); ?></h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 border-0 bg-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6 max-h-[70vh] overflow-y-auto">
            <div id="discrepancies-content">
                <div class="flex items-center justify-center py-8">
                    <span class="dashicons dashicons-update animate-spin text-gray-400 text-2xl"></span>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
            <button type="button" class="inline-flex items-center px-4 py-2 border-0 text-sm font-medium text-gray-600 bg-transparent hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close"><?php esc_html_e('Close', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>

<!-- Remove Local Warning Modal -->
<div id="remove-local-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-red-50">
            <h3 class="text-lg font-semibold text-red-700">
                <span class="dashicons dashicons-warning mr-2"></span>
                <?php esc_html_e('Warning: Risk of Data Loss', 'media-toolkit'); ?>
            </h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 border-0 bg-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-700 mb-4">
                <?php esc_html_e('If you enable this option, local files will be deleted after uploading to cloud storage.', 'media-toolkit'); ?>
            </p>
            <p class="text-sm text-gray-700 mb-4">
                <?php esc_html_e('If files are deleted from the cloud (accidentally or intentionally), it will NOT be possible to recover them.', 'media-toolkit'); ?>
            </p>
            <div class="p-4 bg-yellow-50 rounded-lg mb-4">
                <p class="text-sm text-yellow-800 font-medium"><?php esc_html_e('We recommend:', 'media-toolkit'); ?></p>
                <ul class="mt-2 text-sm text-yellow-700 list-disc list-inside">
                    <li><?php esc_html_e('Maintaining regular backups of your cloud storage', 'media-toolkit'); ?></li>
                    <li><?php esc_html_e('Enabling versioning on your S3/R2 bucket if available', 'media-toolkit'); ?></li>
                </ul>
            </div>
            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                <input type="checkbox" id="accept-risk">
                <span class="mt-toggle-slider"></span>
                <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('I understand and accept the risk', 'media-toolkit'); ?></span>
            </label>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
            <button type="button" class="inline-flex items-center px-4 py-2 border-0 text-sm font-medium text-gray-600 bg-transparent hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close" id="btn-cancel-remove-local"><?php esc_html_e('Cancel', 'media-toolkit'); ?></button>
            <button type="button" class="inline-flex items-center px-5 py-2 border-0 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" id="btn-confirm-remove-local" disabled><?php esc_html_e('Enable', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>

