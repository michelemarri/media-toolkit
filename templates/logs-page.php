<?php
/**
 * Logs page template - Modern UI
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mds-wrap">
    <div class="mds-page">
        <header class="mds-page-header">
            <h1 class="mds-page-title">
                <span class="mds-logo"><span class="dashicons dashicons-text-page"></span></span>
                <?php esc_html_e('Activity Logs', 'media-toolkit'); ?>
            </h1>
            <p class="mds-description"><?php esc_html_e('Real-time operations • Auto-cleans after 24 hours', 'media-toolkit'); ?></p>
        </header>

        <div class="mds-card">
            <div class="mds-card-header mds-cluster mds-cluster-between">
                <div class="mds-cluster">
                    <select id="filter-log-level" class="mds-select mds-select-auto">
                        <option value=""><?php esc_html_e('All Levels', 'media-toolkit'); ?></option>
                        <option value="info"><?php esc_html_e('Info', 'media-toolkit'); ?></option>
                        <option value="warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></option>
                        <option value="error"><?php esc_html_e('Error', 'media-toolkit'); ?></option>
                        <option value="success"><?php esc_html_e('Success', 'media-toolkit'); ?></option>
                    </select>
                    
                    <select id="filter-log-operation" class="mds-select mds-select-auto">
                        <option value=""><?php esc_html_e('All Operations', 'media-toolkit'); ?></option>
                    </select>
                    
                    <button type="button" class="mds-btn mds-btn-secondary" id="btn-refresh-logs">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'media-toolkit'); ?>
                    </button>
                </div>
                
                <div class="mds-cluster">
                    <label class="mds-toggle">
                        <input type="checkbox" id="auto-refresh-logs" checked>
                        <span class="mds-toggle-slider"></span>
                        <span class="mds-toggle-label"><?php esc_html_e('Live updates', 'media-toolkit'); ?></span>
                    </label>
                    
                    <button type="button" class="mds-btn mds-btn-ghost" id="btn-clear-logs">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear All', 'media-toolkit'); ?>
                    </button>
                </div>
            </div>
            
            <div class="mds-table-responsive">
                <table class="mds-table" id="logs-table">
                    <thead>
                        <tr>
                            <th class="mds-w-datetime"><?php esc_html_e('Time', 'media-toolkit'); ?></th>
                            <th class="mds-w-badge"><?php esc_html_e('Level', 'media-toolkit'); ?></th>
                            <th class="mds-w-label"><?php esc_html_e('Operation', 'media-toolkit'); ?></th>
                            <th class="mds-w-file"><?php esc_html_e('File', 'media-toolkit'); ?></th>
                            <th><?php esc_html_e('Message', 'media-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="logs-tbody">
                        <tr>
                            <td colspan="5" class="mds-table-empty">
                                <span class="dashicons dashicons-text-page mds-empty-icon"></span>
                                <p><?php esc_html_e('No logs found', 'media-toolkit'); ?></p>
                                <span class="mds-text-tertiary"><?php esc_html_e('Activity will appear here as operations occur', 'media-toolkit'); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mds-card-footer mds-flex-between">
                <div class="mds-cluster">
                    <span class="mds-badge mds-badge-info" id="logs-count">0</span>
                    <span class="mds-text-secondary"><?php esc_html_e('log entries', 'media-toolkit'); ?></span>
                </div>
                <div id="live-indicator" class="mds-live-indicator">
                    <span class="mds-live-dot"></span>
                    <span><?php esc_html_e('Live', 'media-toolkit'); ?></span>
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
    </div>
</div>
