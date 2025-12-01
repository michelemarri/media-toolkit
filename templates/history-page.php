<?php
/**
 * History page template - Modern UI
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
?>

<div class="wrap mds-wrap">
    <div class="mds-page">
        <header class="mds-page-header">
            <h1 class="mds-page-title">
                <span class="mds-logo"><span class="dashicons dashicons-backup"></span></span>
                <?php esc_html_e('Operation History', 'media-toolkit'); ?>
            </h1>
            <p class="mds-description"><?php esc_html_e('Permanent record of all S3 operations', 'media-toolkit'); ?></p>
        </header>

        <div class="mds-stats-grid">
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-success">
                    <span class="dashicons dashicons-upload"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value" id="stat-uploaded"><?php echo esc_html($history_stats['uploaded'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Uploaded', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-info">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value" id="stat-migrated"><?php echo esc_html($history_stats['migrated'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Migrated', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-warning">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value" id="stat-edited"><?php echo esc_html($history_stats['edited'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Edited', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <div class="mds-stat-card">
                <div class="mds-stat-icon mds-stat-icon-error">
                    <span class="dashicons dashicons-trash"></span>
                </div>
                <div class="mds-stat-content">
                    <span class="mds-stat-value" id="stat-deleted"><?php echo esc_html($history_stats['deleted'] ?? 0); ?></span>
                    <span class="mds-stat-label"><?php esc_html_e('Deleted', 'media-toolkit'); ?></span>
                </div>
            </div>
        </div>

        <div class="mds-card">
            <div class="mds-card-header mds-cluster mds-cluster-between">
                <div class="mds-cluster">
                    <select id="filter-history-action" class="mds-select mds-select-auto">
                        <option value=""><?php esc_html_e('All Actions', 'media-toolkit'); ?></option>
                        <option value="uploaded"><?php esc_html_e('Uploaded', 'media-toolkit'); ?></option>
                        <option value="migrated"><?php esc_html_e('Migrated', 'media-toolkit'); ?></option>
                        <option value="deleted"><?php esc_html_e('Deleted', 'media-toolkit'); ?></option>
                        <option value="edited"><?php esc_html_e('Edited', 'media-toolkit'); ?></option>
                        <option value="invalidation"><?php esc_html_e('Invalidation', 'media-toolkit'); ?></option>
                    </select>
                    
                    <input type="date" id="filter-date-from" class="mds-input mds-input-auto" placeholder="<?php esc_attr_e('From', 'media-toolkit'); ?>">
                    <span class="mds-text-secondary"><?php esc_html_e('to', 'media-toolkit'); ?></span>
                    <input type="date" id="filter-date-to" class="mds-input mds-input-auto" placeholder="<?php esc_attr_e('To', 'media-toolkit'); ?>">
                    
                    <button type="button" class="mds-btn mds-btn-secondary" id="btn-filter-history">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Apply', 'media-toolkit'); ?>
                    </button>
                </div>
                
                <div class="mds-cluster">
                    <button type="button" class="mds-btn mds-btn-ghost" id="btn-clear-history">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear All', 'media-toolkit'); ?>
                    </button>
                    <button type="button" class="mds-btn mds-btn-primary" id="btn-export-history">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'media-toolkit'); ?>
                    </button>
                </div>
            </div>
            
            <div class="mds-table-responsive">
                <table class="mds-table" id="history-table">
                    <thead>
                        <tr>
                            <th class="mds-w-datetime"><?php esc_html_e('Date', 'media-toolkit'); ?></th>
                            <th class="mds-w-badge"><?php esc_html_e('Action', 'media-toolkit'); ?></th>
                            <th><?php esc_html_e('File Path', 'media-toolkit'); ?></th>
                            <th class="mds-w-size"><?php esc_html_e('Size', 'media-toolkit'); ?></th>
                            <th class="mds-w-user"><?php esc_html_e('User', 'media-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <tr>
                            <td colspan="5" class="mds-table-empty">
                                <span class="dashicons dashicons-backup mds-empty-icon"></span>
                                <p><?php esc_html_e('No history found', 'media-toolkit'); ?></p>
                                <span class="mds-text-tertiary"><?php esc_html_e('Operations will be recorded here', 'media-toolkit'); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mds-card-footer mds-flex-between">
                <div class="mds-cluster">
                    <span class="mds-badge mds-badge-info" id="history-count">0</span>
                    <span class="mds-text-secondary"><?php esc_html_e('total entries', 'media-toolkit'); ?></span>
                </div>
                
                <div id="history-pagination" class="mds-cluster">
                    <button type="button" class="mds-btn mds-btn-ghost mds-btn-sm" id="btn-prev-page" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <span id="page-info" class="mds-text-secondary mds-text-sm"><?php esc_html_e('Page 1 of 1', 'media-toolkit'); ?></span>
                    <button type="button" class="mds-btn mds-btn-ghost mds-btn-sm" id="btn-next-page" disabled>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
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
