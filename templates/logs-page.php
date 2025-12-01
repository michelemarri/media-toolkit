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
?>

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-text-page"></span>
                </span>
                <?php esc_html_e('Activity Logs', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Real-time operations • Auto-cleans after 24 hours', 'media-toolkit'); ?></p>
        </header>

        <!-- Main Card -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <!-- Filters Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                <div class="flex flex-wrap items-center gap-3">
                    <select id="filter-log-level" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                        <option value=""><?php esc_html_e('All Levels', 'media-toolkit'); ?></option>
                        <option value="info"><?php esc_html_e('Info', 'media-toolkit'); ?></option>
                        <option value="warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></option>
                        <option value="error"><?php esc_html_e('Error', 'media-toolkit'); ?></option>
                        <option value="success"><?php esc_html_e('Success', 'media-toolkit'); ?></option>
                    </select>
                    
                    <select id="filter-log-operation" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
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
                    
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" id="btn-clear-logs">
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
