<?php
/**
 * History page template
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$stats = new \Metodo\MediaToolkit\Stats\Stats(
    $plugin->get_logger(),
    $plugin->get_history()
);
$history_stats = $stats->get_history_stats();

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
                <h1 class="mt-hero-title"><?php esc_html_e('Operation History', 'media-toolkit'); ?></h1>
                <p class="mt-hero-description"><?php esc_html_e('Permanent record of all S3 operations', 'media-toolkit'); ?></p>
                <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
            </div>
        </div>
        <?php else: ?>
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-backup"></span>
                </span>
                <?php esc_html_e('Operation History', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Permanent record of all S3 operations', 'media-toolkit'); ?></p>
        </header>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <span class="text-sm text-gray-500"><?php esc_html_e('Uploaded', 'media-toolkit'); ?></span>
                </div>
                <span class="block text-2xl font-bold text-gray-900" id="stat-uploaded"><?php echo esc_html($history_stats['uploaded'] ?? 0); ?></span>
            </div>
            
            <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                        <span class="dashicons dashicons-cloud-upload"></span>
                    </div>
                    <span class="text-sm text-gray-500"><?php esc_html_e('Migrated', 'media-toolkit'); ?></span>
                </div>
                <span class="block text-2xl font-bold text-gray-900" id="stat-migrated"><?php echo esc_html($history_stats['migrated'] ?? 0); ?></span>
            </div>
            
            <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <span class="text-sm text-gray-500"><?php esc_html_e('Edited', 'media-toolkit'); ?></span>
                </div>
                <span class="block text-2xl font-bold text-gray-900" id="stat-edited"><?php echo esc_html($history_stats['edited'] ?? 0); ?></span>
            </div>
            
            <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                        <span class="dashicons dashicons-trash"></span>
                    </div>
                    <span class="text-sm text-gray-500"><?php esc_html_e('Deleted', 'media-toolkit'); ?></span>
                </div>
                <span class="block text-2xl font-bold text-gray-900" id="stat-deleted"><?php echo esc_html($history_stats['deleted'] ?? 0); ?></span>
            </div>
        </div>

        <!-- Main Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <!-- Filters Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-3">
                    <select id="filter-history-action" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                        <option value=""><?php esc_html_e('All Actions', 'media-toolkit'); ?></option>
                        <option value="uploaded"><?php esc_html_e('Uploaded', 'media-toolkit'); ?></option>
                        <option value="migrated"><?php esc_html_e('Migrated', 'media-toolkit'); ?></option>
                        <option value="deleted"><?php esc_html_e('Deleted', 'media-toolkit'); ?></option>
                        <option value="edited"><?php esc_html_e('Edited', 'media-toolkit'); ?></option>
                        <option value="invalidation"><?php esc_html_e('Invalidation', 'media-toolkit'); ?></option>
                    </select>
                    
                    <input type="date" id="filter-date-from" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all" placeholder="<?php esc_attr_e('From', 'media-toolkit'); ?>">
                    <span class="text-sm text-gray-500"><?php esc_html_e('to', 'media-toolkit'); ?></span>
                    <input type="date" id="filter-date-to" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all" placeholder="<?php esc_attr_e('To', 'media-toolkit'); ?>">
                    
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-xs transition-all" id="btn-filter-history">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Apply', 'media-toolkit'); ?>
                    </button>
                </div>
                
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" id="btn-clear-history">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear All', 'media-toolkit'); ?>
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all" id="btn-export-history">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'media-toolkit'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Table -->
            <div class="mt-table-responsive overflow-x-auto">
                <table class="w-full text-sm" id="history-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-44"><?php esc_html_e('Date', 'media-toolkit'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24"><?php esc_html_e('Action', 'media-toolkit'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php esc_html_e('File Path', 'media-toolkit'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24"><?php esc_html_e('Size', 'media-toolkit'); ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36"><?php esc_html_e('User', 'media-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <span class="dashicons dashicons-backup text-5xl text-gray-300 mb-4 block"></span>
                                <p class="text-gray-600 font-medium"><?php esc_html_e('No history found', 'media-toolkit'); ?></p>
                                <span class="text-sm text-gray-400"><?php esc_html_e('Operations will be recorded here', 'media-toolkit'); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full mt-badge-info" id="history-count">0</span>
                    <span class="text-sm text-gray-500"><?php esc_html_e('total entries', 'media-toolkit'); ?></span>
                </div>
                
                <div id="history-pagination" class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center justify-center w-9 h-9 text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-prev-page" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <span id="page-info" class="text-sm text-gray-500 px-2"><?php esc_html_e('Page 1 of 1', 'media-toolkit'); ?></span>
                    <button type="button" class="inline-flex items-center justify-center w-9 h-9 text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-next-page" disabled>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
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
