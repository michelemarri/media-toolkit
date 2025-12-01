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

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-admin-tools"></span>
                </span>
                <?php esc_html_e('Tools', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Migration and maintenance tools for your media files.', 'media-toolkit'); ?></p>
        </header>

        <!-- Tab Navigation -->
        <nav class="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-xl">
            <a href="?page=media-toolkit-tools&tab=migration" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'migration' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Migration', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-tools&tab=stats-sync" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'stats-sync' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Stats Sync', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-tools&tab=cache-sync" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'cache-sync' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Cache Headers', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-tools&tab=reconciliation" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'reconciliation' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e('Reconciliation', 'media-toolkit'); ?>
            </a>
        </nav>

    <?php if (!$is_configured): ?>
    <!-- Error Alert -->
    <div class="flex gap-4 p-5 rounded-xl border mt-alert-error">
        <span class="dashicons dashicons-warning text-red-600 flex-shrink-0"></span>
        <div>
            <strong class="block font-semibold mb-1"><?php esc_html_e('S3 Offload is not configured.', 'media-toolkit'); ?></strong>
            <p><?php printf(esc_html__('Please %sconfigure your S3 settings%s before using these tools.', 'media-toolkit'), '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings')) . '" class="underline font-medium">', '</a>'); ?></p>
        </div>
    </div>
    <?php else: ?>

    <!-- Tab Content -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm animate-fade-in">
        <?php if ($active_tab === 'migration'): ?>
            <!-- ==================== MIGRATION TAB ==================== -->
            <div class="p-6 space-y-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-format-gallery"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Total Files', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="stat-total"><?php echo esc_html($migration_stats['total_attachments']); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Migrated', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="stat-migrated"><?php echo esc_html($migration_stats['migrated_attachments']); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-clock"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="stat-pending"><?php echo esc_html($migration_stats['pending_attachments']); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-database"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Pending Size', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="stat-size"><?php echo esc_html($migration_stats['pending_size_formatted']); ?></span>
                    </div>
                </div>

                <!-- Progress -->
                <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="dashicons dashicons-chart-line text-gray-600"></span>
                            <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Migration Progress', 'media-toolkit'); ?></span>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="progress-percentage"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1 mt-progress-bar mt-progress-animated">
                            <div class="mt-progress-fill" id="migration-progress" style="width: <?php echo esc_attr($migration_stats['progress_percentage']); ?>%"></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 min-w-[45px] text-right"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
                    </div>
                </div>

                <!-- Options & Controls -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Options -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                            <span class="dashicons dashicons-admin-settings text-gray-600"></span>
                            <?php esc_html_e('Migration Options', 'media-toolkit'); ?>
                        </h4>
                        <div class="space-y-4">
                            <div>
                                <label for="batch-size" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                                <select id="batch-size" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                    <option value="10"><?php esc_html_e('10 files per batch', 'media-toolkit'); ?></option>
                                    <option value="25" selected><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                                    <option value="50"><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                                    <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('Smaller batches are safer but slower', 'media-toolkit'); ?></p>
                            </div>
                            
                            <div class="pt-2">
                                <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" id="remove-local">
                                    <span class="mt-toggle-slider"></span>
                                    <span class="text-sm font-medium text-gray-900"><?php esc_html_e('Delete local files after migration', 'media-toolkit'); ?></span>
                                </label>
                                <p class="mt-2 ml-14 text-sm text-gray-500">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded mt-badge-warning mr-1"><?php esc_html_e('Warning', 'media-toolkit'); ?></span>
                                    <?php esc_html_e('This will permanently delete local copies!', 'media-toolkit'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                            <span class="dashicons dashicons-controls-play text-gray-600"></span>
                            <?php esc_html_e('Controls', 'media-toolkit'); ?>
                        </h4>
                        <div class="flex flex-col gap-3">
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-migration">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e('Start Migration', 'media-toolkit'); ?>
                            </button>
                            
                            <div class="flex gap-2">
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-pause-migration" disabled>
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Pause', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-resume-migration" disabled>
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Resume', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-stop-migration" disabled>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="migration-status" class="hidden mt-4 space-y-2">
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Current file', 'media-toolkit'); ?></span>
                                <span class="text-sm font-medium text-gray-900 truncate max-w-[180px] font-mono" id="current-file">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
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

                <!-- Migration Log -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="dashicons dashicons-media-text text-gray-600"></span>
                            <?php esc_html_e('Migration Log', 'media-toolkit'); ?>
                        </h4>
                        <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-retry-failed" disabled>
                            <span class="dashicons dashicons-update text-sm"></span>
                            <?php esc_html_e('Retry Failed', 'media-toolkit'); ?>
                        </button>
                    </div>
                    <div class="mt-terminal">
                        <div class="mt-terminal-header">
                            <div class="flex gap-2">
                                <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                            </div>
                            <span class="mt-terminal-title"><?php esc_html_e('migration.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mt-terminal-body" id="migration-log">
                            <div class="mt-terminal-line">
                                <span class="mt-terminal-prompt">$</span>
                                <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Migration log will appear here...', 'media-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'stats-sync'): ?>
            <!-- ==================== STATS SYNC TAB ==================== -->
            <div class="p-6 space-y-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-format-gallery"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Files on S3', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900"><?php echo number_format($dashboard_stats['original_files'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-database"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Total (with thumbnails)', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-cloud"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Storage Used', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900"><?php echo esc_html($dashboard_stats['total_storage_formatted'] ?? '0 B'); ?></span>
                    </div>
                </div>

                <!-- Sync Card -->
                <div class="p-6 bg-gray-50 border border-gray-200 rounded-xl">
                    <h4 class="flex items-center gap-2 text-base font-semibold text-gray-900 mb-3">
                        <span class="dashicons dashicons-update text-gray-600"></span>
                        <?php esc_html_e('S3 Statistics Sync', 'media-toolkit'); ?>
                    </h4>
                    <p class="text-sm text-gray-600 mb-5"><?php esc_html_e('Sync statistics from S3 to get accurate file count and storage usage for the current environment.', 'media-toolkit'); ?></p>
                    
                    <?php if ($s3_stats): ?>
                    <div class="space-y-2 mb-5">
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Last sync', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($s3_stats['synced_at'] ?? 'Never'); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Original files', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo number_format($s3_stats['original_files'] ?? $s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Total files', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo number_format($s3_stats['files'] ?? 0); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Storage', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?php echo size_format($s3_stats['size'] ?? 0); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-5 max-w-xs">
                        <label for="s3_sync_interval" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Auto Sync Interval', 'media-toolkit'); ?></label>
                        <select name="s3_sync_interval" id="s3_sync_interval" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                            <option value="0" <?php selected($sync_interval, 0); ?>><?php esc_html_e('Disabled', 'media-toolkit'); ?></option>
                            <option value="1" <?php selected($sync_interval, 1); ?>><?php esc_html_e('Every hour', 'media-toolkit'); ?></option>
                            <option value="6" <?php selected($sync_interval, 6); ?>><?php esc_html_e('Every 6 hours', 'media-toolkit'); ?></option>
                            <option value="12" <?php selected($sync_interval, 12); ?>><?php esc_html_e('Every 12 hours', 'media-toolkit'); ?></option>
                            <option value="24" <?php selected($sync_interval, 24); ?>><?php esc_html_e('Daily (recommended)', 'media-toolkit'); ?></option>
                            <option value="168" <?php selected($sync_interval, 168); ?>><?php esc_html_e('Weekly', 'media-toolkit'); ?></option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('How often to automatically query S3 for statistics.', 'media-toolkit'); ?></p>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-2 px-5 py-3 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-sync-stats">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Sync Now', 'media-toolkit'); ?>
                        </button>
                        <span id="sync-status" class="text-sm text-gray-500"></span>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'cache-sync'): ?>
            <!-- ==================== CACHE SYNC TAB ==================== -->
            <div class="p-6 space-y-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-format-gallery"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Files to Update', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900" id="cache-total-files"><?php echo number_format($dashboard_stats['total_files'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Processed', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900" id="cache-processed-files">0</span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Failed', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-3xl font-bold text-gray-900" id="cache-failed-files">0</span>
                    </div>
                </div>

                <!-- Progress -->
                <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="dashicons dashicons-chart-line text-gray-600"></span>
                            <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Cache Headers Update Progress', 'media-toolkit'); ?></span>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="cache-progress-percentage">0%</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1 mt-progress-bar mt-progress-animated">
                            <div class="mt-progress-fill" id="cache-progress-bar" style="width: 0%"></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 min-w-[45px] text-right">0%</span>
                    </div>
                </div>

                <!-- Options & Controls -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Options -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-3">
                            <span class="dashicons dashicons-admin-settings text-gray-600"></span>
                            <?php esc_html_e('Cache-Control Settings', 'media-toolkit'); ?>
                        </h4>
                        <p class="text-sm text-gray-600 mb-4"><?php esc_html_e('Update Cache-Control headers on all files already uploaded to S3 for the current environment.', 'media-toolkit'); ?></p>
                        
                        <div>
                            <label for="cache_control_value" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Cache-Control Value', 'media-toolkit'); ?></label>
                            <select id="cache_control_value" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <option value="0" <?php selected($cache_control, 0); ?>><?php esc_html_e('No cache (no-cache, no-store)', 'media-toolkit'); ?></option>
                                <option value="86400" <?php selected($cache_control, 86400); ?>><?php esc_html_e('1 day', 'media-toolkit'); ?></option>
                                <option value="604800" <?php selected($cache_control, 604800); ?>><?php esc_html_e('1 week', 'media-toolkit'); ?></option>
                                <option value="2592000" <?php selected($cache_control, 2592000); ?>><?php esc_html_e('1 month', 'media-toolkit'); ?></option>
                                <option value="31536000" <?php selected($cache_control, 31536000); ?>><?php esc_html_e('1 year — Recommended', 'media-toolkit'); ?></option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('This will be applied to all existing files on S3.', 'media-toolkit'); ?></p>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                            <span class="dashicons dashicons-controls-play text-gray-600"></span>
                            <?php esc_html_e('Controls', 'media-toolkit'); ?>
                        </h4>
                        <div class="flex flex-col gap-3">
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-cache-sync">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e('Start Cache Update', 'media-toolkit'); ?>
                            </button>
                            <button type="button" class="hidden w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-base font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all" id="btn-cancel-cache-sync">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                            </button>
                        </div>
                        
                        <div id="cache-sync-status" class="hidden mt-4 space-y-2">
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="cache-status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900">
                                    <span id="cache-current-count">0</span> / <span id="cache-total-count">0</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cache Sync Log -->
                <div>
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-3">
                        <span class="dashicons dashicons-media-text text-gray-600"></span>
                        <?php esc_html_e('Update Log', 'media-toolkit'); ?>
                    </h4>
                    <div class="mt-terminal">
                        <div class="mt-terminal-header">
                            <div class="flex gap-2">
                                <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                            </div>
                            <span class="mt-terminal-title"><?php esc_html_e('cache-sync.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mt-terminal-body" id="cache-sync-log">
                            <div class="mt-terminal-line">
                                <span class="mt-terminal-prompt">$</span>
                                <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Cache update log will appear here...', 'media-toolkit'); ?></span>
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
            <div class="p-6 space-y-6">
                <!-- Info Alert -->
                <div class="flex gap-4 p-5 rounded-xl border mt-alert-info">
                    <span class="dashicons dashicons-info text-blue-600 flex-shrink-0"></span>
                    <div>
                        <strong class="block font-semibold mb-1"><?php esc_html_e('What is Reconciliation?', 'media-toolkit'); ?></strong>
                        <p class="text-sm opacity-90"><?php esc_html_e('This tool compares files on S3 with WordPress attachments and syncs the metadata. Use it when files were uploaded to S3 before the plugin was installed, or when the sync status appears incorrect.', 'media-toolkit'); ?></p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-format-gallery"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('WP Attachments', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="recon-wp-attachments"><?php echo number_format($reconciliation_stats['total_attachments'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Marked as Migrated', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="recon-marked"><?php echo number_format($reconciliation_stats['marked_migrated'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-cloud-upload"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('S3 Original Files', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="recon-s3-files"><?php echo number_format($reconciliation_stats['s3_original_files'] ?? 0); ?></span>
                    </div>
                    
                    <div class="p-5 <?php echo ($reconciliation_stats['has_discrepancy'] ?? false) ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200'; ?> border rounded-2xl">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-200 text-gray-600">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <span class="text-sm text-gray-500"><?php esc_html_e('Discrepancy', 'media-toolkit'); ?></span>
                        </div>
                        <span class="block text-2xl font-bold text-gray-900" id="recon-discrepancy"><?php echo number_format($reconciliation_stats['discrepancy'] ?? 0); ?></span>
                    </div>
                </div>

                <!-- Progress -->
                <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="dashicons dashicons-chart-line text-gray-600"></span>
                            <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Reconciliation Progress', 'media-toolkit'); ?></span>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="recon-progress-percentage"><?php echo esc_html($reconciliation_stats['progress_percentage'] ?? 0); ?>%</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1 mt-progress-bar mt-progress-animated">
                            <div class="mt-progress-fill" id="recon-progress-bar" style="width: <?php echo esc_attr($reconciliation_stats['progress_percentage'] ?? 0); ?>%"></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 min-w-[45px] text-right"><?php echo esc_html($reconciliation_stats['progress_percentage'] ?? 0); ?>%</span>
                    </div>
                </div>

                <!-- Scan & Controls -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Scan Preview -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-3">
                            <span class="dashicons dashicons-search text-gray-600"></span>
                            <?php esc_html_e('Scan Preview', 'media-toolkit'); ?>
                        </h4>
                        <p class="text-sm text-gray-600 mb-4"><?php esc_html_e('Scan S3 to see how many files match WordPress attachments before running reconciliation.', 'media-toolkit'); ?></p>
                        
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-xs transition-all" id="btn-scan-s3">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Scan S3', 'media-toolkit'); ?>
                        </button>
                        
                        <div id="scan-results" class="hidden mt-4 space-y-2">
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('S3 Original Files', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="scan-s3-files">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('WordPress Attachments', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="scan-wp-files">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Matching Files', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="scan-matches">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Not Found on S3', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="scan-not-found">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Would be Marked', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-green-600" id="scan-would-mark">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                            <span class="dashicons dashicons-controls-play text-gray-600"></span>
                            <?php esc_html_e('Controls', 'media-toolkit'); ?>
                        </h4>
                        <div class="mb-4">
                            <label for="recon-batch-size" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                            <select id="recon-batch-size" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <option value="25"><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                                <option value="50" selected><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                                <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                            </select>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-start-reconciliation">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php esc_html_e('Start Reconciliation', 'media-toolkit'); ?>
                            </button>
                            
                            <div class="flex gap-2">
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-pause-reconciliation" disabled>
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Pause', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-resume-reconciliation" disabled>
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Resume', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-stop-reconciliation" disabled>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="recon-status" class="hidden mt-4 space-y-2">
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900" id="recon-status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Current file', 'media-toolkit'); ?></span>
                                <span class="text-sm font-medium text-gray-900 truncate max-w-[180px] font-mono" id="recon-current-file">-</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <span class="text-sm text-gray-500"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                <span class="text-sm font-semibold text-gray-900">
                                    <span id="recon-processed-count">0</span> / <span id="recon-total-count">0</span>
                                    <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded mt-badge-success" id="recon-found-badge">
                                        <span id="recon-found-count">0</span> <?php esc_html_e('found', 'media-toolkit'); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reconciliation Log -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="dashicons dashicons-media-text text-gray-600"></span>
                            <?php esc_html_e('Reconciliation Log', 'media-toolkit'); ?>
                        </h4>
                        <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" id="btn-clear-metadata" title="<?php esc_attr_e('Clear all migration metadata', 'media-toolkit'); ?>">
                            <span class="dashicons dashicons-trash text-sm"></span>
                            <?php esc_html_e('Reset Metadata', 'media-toolkit'); ?>
                        </button>
                    </div>
                    <div class="mt-terminal">
                        <div class="mt-terminal-header">
                            <div class="flex gap-2">
                                <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                                <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                            </div>
                            <span class="mt-terminal-title"><?php esc_html_e('reconciliation.log', 'media-toolkit'); ?></span>
                        </div>
                        <div class="mt-terminal-body" id="recon-log">
                            <div class="mt-terminal-line">
                                <span class="mt-terminal-prompt">$</span>
                                <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Reconciliation log will appear here...', 'media-toolkit'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <?php endif; ?>

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

<!-- Confirmation Modal -->
<div id="confirm-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900" id="confirm-title"><?php esc_html_e('Confirm Action', 'media-toolkit'); ?></h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6">
            <p id="confirm-message" class="text-sm text-gray-600"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
            <button type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all" id="btn-confirm-no"><?php esc_html_e('Cancel', 'media-toolkit'); ?></button>
            <button type="button" class="inline-flex items-center px-5 py-2 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-confirm-yes"><?php esc_html_e('Yes, Continue', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>
