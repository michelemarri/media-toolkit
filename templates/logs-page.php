<?php
/**
 * Logs page template
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Metodo\MediaToolkit\Database\OptimizationTable;

// Get optimization stats for the tab badge
$opt_stats = OptimizationTable::table_exists() ? OptimizationTable::get_aggregate_stats() : null;

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
                <h1 class="mt-hero-title"><?php esc_html_e('Logs & Status', 'media-toolkit'); ?></h1>
                <p class="mt-hero-description"><?php esc_html_e('Monitor activity logs and optimization status', 'media-toolkit'); ?></p>
                <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
            </div>
        </div>
        <?php else: ?>
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-text-page"></span>
                </span>
                <?php esc_html_e('Logs & Status', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Monitor activity logs and optimization status', 'media-toolkit'); ?></p>
        </header>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <nav class="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-xl">
            <button type="button" class="logs-tab-btn flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap bg-white bg-transparent shadow-sm text-gray-900" data-tab="activity-logs">
                <span class="dashicons dashicons-text-page"></span>
                <?php esc_html_e('Activity Logs', 'media-toolkit'); ?>
            </button>
            <button type="button" class="logs-tab-btn flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap text-gray-500 bg-transparent hover:text-gray-700 hover:bg-white/50" data-tab="optimization-status">
                <span class="dashicons dashicons-images-alt2"></span>
                <?php esc_html_e('Optimization Status', 'media-toolkit'); ?>
                <?php if ($opt_stats && $opt_stats['total_records'] > 0): ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-700"><?php echo esc_html(number_format_i18n($opt_stats['total_records'])); ?></span>
                <?php endif; ?>
            </button>
        </nav>

        <!-- Tab Content Wrapper -->
        <div class="bg-gray-100 rounded-xl p-6">
            <!-- Tab: Activity Logs -->
            <div id="tab-activity-logs" class="logs-tab-content">
                <!-- Main Card -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <!-- Filters Header -->
                <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex flex-wrap items-center gap-3">
                        <select id="filter-log-level" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <option value=""><?php esc_html_e('All Levels', 'media-toolkit'); ?></option>
                            <option value="info"><?php esc_html_e('Info', 'media-toolkit'); ?></option>
                            <option value="warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></option>
                            <option value="error"><?php esc_html_e('Error', 'media-toolkit'); ?></option>
                            <option value="success"><?php esc_html_e('Success', 'media-toolkit'); ?></option>
                        </select>
                        
                        <select id="filter-log-operation" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <option value=""><?php esc_html_e('All Operations', 'media-toolkit'); ?></option>
                        </select>
                        
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-xs transition-all" id="btn-refresh-logs">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'media-toolkit'); ?>
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="auto-refresh-logs" checked>
                            <span class="mt-toggle-slider"></span>
                            <span class="text-sm font-medium text-gray-600"><?php esc_html_e('Live updates', 'media-toolkit'); ?></span>
                        </label>
                        
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-xs transition-all text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" id="btn-clear-logs">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear All', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="mt-table-responsive overflow-x-auto">
                    <table class="w-full text-sm" id="logs-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-44"><?php esc_html_e('Time', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24"><?php esc_html_e('Level', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-32"><?php esc_html_e('Operation', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-56"><?php esc_html_e('File', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php esc_html_e('Message', 'media-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody" class="divide-y divide-gray-100">
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <span class="dashicons dashicons-text-page text-5xl text-gray-300 mb-4 block"></span>
                                    <p class="text-gray-600 font-medium"><?php esc_html_e('No logs found', 'media-toolkit'); ?></p>
                                    <span class="text-sm text-gray-400"><?php esc_html_e('Activity will appear here as operations occur', 'media-toolkit'); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer -->
                <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full mt-badge-info" id="logs-count">0</span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('log entries', 'media-toolkit'); ?></span>
                    </div>
                    <div id="live-indicator" class="flex items-center gap-2 text-sm text-green-600">
                        <span class="mt-live-dot"></span>
                        <span><?php esc_html_e('Live', 'media-toolkit'); ?></span>
                    </div>
                </div>
            </div>
            </div>

            <!-- Tab: Optimization Status -->
            <div id="tab-optimization-status" class="logs-tab-content hidden">
            <?php if ($opt_stats): ?>
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 text-green-600">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-500"><?php esc_html_e('Optimized', 'media-toolkit'); ?></span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900" id="opt-stat-optimized"><?php echo esc_html(number_format_i18n($opt_stats['optimized_count'])); ?></p>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 text-amber-600">
                            <span class="dashicons dashicons-clock"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-500"><?php esc_html_e('Pending', 'media-toolkit'); ?></span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900" id="opt-stat-pending"><?php echo esc_html(number_format_i18n($opt_stats['pending_count'])); ?></p>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-100 text-red-600">
                            <span class="dashicons dashicons-warning"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-500"><?php esc_html_e('Failed', 'media-toolkit'); ?></span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900" id="opt-stat-failed"><?php echo esc_html(number_format_i18n($opt_stats['failed_count'])); ?></p>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </span>
                        <span class="text-sm font-medium text-gray-500"><?php esc_html_e('Space Saved', 'media-toolkit'); ?></span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900" id="opt-stat-saved"><?php echo esc_html(size_format($opt_stats['total_bytes_saved'])); ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?php echo esc_html(sprintf(__('%s%% average savings', 'media-toolkit'), $opt_stats['average_savings_percent'])); ?></p>
                </div>
            </div>

            <!-- Main Card -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <!-- Filters Header -->
                <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex flex-wrap items-center gap-3">
                        <select id="filter-opt-status" class="mt-select px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg outline-none transition-all">
                            <option value=""><?php esc_html_e('All Status', 'media-toolkit'); ?></option>
                            <option value="optimized"><?php esc_html_e('Optimized', 'media-toolkit'); ?></option>
                            <option value="pending"><?php esc_html_e('Pending', 'media-toolkit'); ?></option>
                            <option value="failed"><?php esc_html_e('Failed', 'media-toolkit'); ?></option>
                            <option value="skipped"><?php esc_html_e('Skipped', 'media-toolkit'); ?></option>
                        </select>
                        
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-xs transition-all" id="btn-refresh-optimization">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'media-toolkit'); ?>
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-xs transition-all text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all" id="btn-reset-failed" title="<?php esc_attr_e('Reset failed items to pending for retry', 'media-toolkit'); ?>">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <?php esc_html_e('Retry Failed', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="mt-table-responsive overflow-x-auto">
                    <table class="w-full text-sm" id="optimization-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-16"><?php esc_html_e('ID', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php esc_html_e('File', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24"><?php esc_html_e('Status', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-28"><?php esc_html_e('Original', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-28"><?php esc_html_e('Optimized', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-24"><?php esc_html_e('Saved', 'media-toolkit'); ?></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36"><?php esc_html_e('Optimized At', 'media-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="optimization-tbody" class="divide-y divide-gray-100">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <span class="dashicons dashicons-images-alt2 text-5xl text-gray-300 mb-4 block"></span>
                                    <p class="text-gray-600 font-medium"><?php esc_html_e('Loading optimization data...', 'media-toolkit'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer with Pagination -->
                <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full mt-badge-info" id="optimization-count">0</span>
                        <span class="text-sm text-gray-500"><?php esc_html_e('records', 'media-toolkit'); ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500" id="opt-page-info"><?php esc_html_e('Page 1 of 1', 'media-toolkit'); ?></span>
                        <div class="flex gap-1">
                            <button type="button" id="btn-opt-prev-page" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <span class="dashicons dashicons-arrow-left-alt2 text-sm"></span>
                            </button>
                            <button type="button" id="btn-opt-next-page" class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <span class="dashicons dashicons-arrow-right-alt2 text-sm"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Table not exists -->
            <div class="flex flex-col items-center justify-center bg-white border border-gray-200 rounded-xl p-12 text-center shadow-sm">
                <span class="dashicons dashicons-images-alt2 text-2xl text-gray-300 m-auto mb-4 block text-center"></span>
                <h4 class="text-gray-600 text-lgfont-medium m-0"><?php esc_html_e('Optimization table not found', 'media-toolkit'); ?></h4>
                <p class="text-sm text-gray-400 m-0 mb-4"><?php esc_html_e('The optimization tracking table has not been created yet. Try deactivating and reactivating the plugin.', 'media-toolkit'); ?></p>
            </div>
            <?php endif; ?>
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
