<?php
/**
 * Migration page template
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

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
    <?php if ($hasBanner): ?>
    <!-- Hero Banner -->
    <div class="mt-hero">
        <img src="<?php echo esc_url($bannerUrl); ?>" alt="Media Toolkit" class="mt-hero-banner">
        <div class="mt-hero-overlay">
            <h1 class="mt-hero-title"><?php esc_html_e('Media Migration', 'media-toolkit'); ?></h1>
            <p class="mt-hero-description"><?php esc_html_e('Migrate existing media files to Amazon S3', 'media-toolkit'); ?></p>
            <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
        </div>
    </div>
    <?php else: ?>
    <!-- Header -->
    <header>
        <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
            <span class="mt-logo">
                <span class="dashicons dashicons-upload"></span>
            </span>
            <?php esc_html_e('Media Migration', 'media-toolkit'); ?>
        </h1>
        <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Migrate existing media files to Amazon S3', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <?php if (!$is_configured): ?>
    <!-- Error Alert -->
    <div class="flex gap-4 p-5 rounded-xl border mt-alert-error">
        <span class="dashicons dashicons-warning text-red-600 flex-shrink-0"></span>
        <div>
            <strong class="block font-semibold mb-1"><?php esc_html_e('S3 Offload is not configured.', 'media-toolkit'); ?></strong>
            <p><?php printf(esc_html__('Please %sconfigure your S3 settings%s before migrating.', 'media-toolkit'), '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings')) . '" class="underline font-medium">', '</a>'); ?></p>
        </div>
    </div>
    <?php else: ?>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-format-gallery"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Total Files', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-total"><?php echo esc_html($migration_stats['total_attachments']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Migrated', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-migrated"><?php echo esc_html($migration_stats['migrated_attachments']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-pending"><?php echo esc_html($migration_stats['pending_attachments']); ?></span>
        </div>
        
        <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <span class="text-sm text-gray-500"><?php esc_html_e('Pending Size', 'media-toolkit'); ?></span>
            </div>
            <span class="block text-2xl font-bold text-gray-900" id="stat-size"><?php echo esc_html($migration_stats['pending_size_formatted']); ?></span>
        </div>
    </div>

    <!-- Progress Card -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-chart-line text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Migration Progress', 'media-toolkit'); ?></h3>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold text-white bg-gray-800 rounded-full" id="progress-percentage"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4">
                <div class="flex-1 mt-progress-bar mt-progress-animated">
                    <div class="mt-progress-fill" id="migration-progress" style="width: <?php echo esc_attr($migration_stats['progress_percentage']); ?>%"></div>
                </div>
                <span class="text-sm font-semibold text-gray-900 min-w-[45px] text-right"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
            </div>
        </div>
    </div>

    <!-- Options & Controls Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Options Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <span class="dashicons dashicons-admin-settings text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Migration Options', 'media-toolkit'); ?></h3>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="batch-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                    <select id="batch-size" class="w-full max-w-md px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                        <option value="10"><?php esc_html_e('10 files per batch', 'media-toolkit'); ?></option>
                        <option value="25" selected><?php esc_html_e('25 files per batch', 'media-toolkit'); ?></option>
                        <option value="50"><?php esc_html_e('50 files per batch', 'media-toolkit'); ?></option>
                        <option value="100"><?php esc_html_e('100 files per batch', 'media-toolkit'); ?></option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Smaller batches are safer but slower', 'media-toolkit'); ?></p>
                </div>
                
                <div>
                    <label for="migration-mode" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Mode', 'media-toolkit'); ?></label>
                    <select id="migration-mode" class="w-full max-w-md px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                        <option value="sync"><?php esc_html_e('Synchronous (browser must stay open)', 'media-toolkit'); ?></option>
                        <option value="async"><?php esc_html_e('Asynchronous (runs in background)', 'media-toolkit'); ?></option>
                    </select>
                    <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Async mode uses WordPress cron', 'media-toolkit'); ?></p>
                </div>
                
                <div class="pt-2">
                    <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="remove-local">
                        <span class="mt-toggle-slider"></span>
                        <span class="text-sm font-semibold text-gray-900"><?php esc_html_e('Delete local files after migration', 'media-toolkit'); ?></span>
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
                
                <div id="migration-status" class="hidden mt-5">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                            <span class="text-sm font-semibold text-gray-900" id="status-text"><?php esc_html_e('Idle', 'media-toolkit'); ?></span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-500"><?php esc_html_e('Current file', 'media-toolkit'); ?></span>
                            <span class="text-sm font-medium text-gray-900 truncate max-w-[200px] font-mono" id="current-file">-</span>
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

    <!-- Migration Log -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <span class="dashicons dashicons-media-text text-gray-700"></span>
                <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Migration Log', 'media-toolkit'); ?></h3>
            </div>
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
