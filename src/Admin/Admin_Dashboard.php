<?php
/**
 * Admin Dashboard class
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\Stats\Stats;
use Metodo\MediaToolkit\Core\Settings;

/**
 * Handles WordPress dashboard widget
 */
final class Admin_Dashboard
{
    private Stats $stats;
    private Settings $settings;

    public function __construct(Stats $stats, Settings $settings)
    {
        $this->stats = $stats;
        $this->settings = $settings;

        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'media_toolkit_dashboard',
            'Media Toolkit',
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget(): void
    {
        if (!$this->settings->is_configured()) {
            echo '<p>S3 Offload is not configured. <a href="' . admin_url('admin.php?page=media-toolkit-settings') . '">Configure now</a></p>';
            return;
        }

        $stats = $this->stats->get_dashboard_stats();
        $migration_stats = $this->stats->get_migration_stats();
        ?>
        <div class="s3-offload-widget">
            <div class="s3-stats-grid">
                <div class="s3-stat">
                    <span class="s3-stat-value"><?php echo esc_html($stats['total_files']); ?></span>
                    <span class="s3-stat-label">Files on S3</span>
                </div>
                <div class="s3-stat">
                    <span class="s3-stat-value"><?php echo esc_html($stats['total_storage_formatted']); ?></span>
                    <span class="s3-stat-label">Storage Used</span>
                </div>
                <div class="s3-stat">
                    <span class="s3-stat-value"><?php echo esc_html($stats['files_today']); ?></span>
                    <span class="s3-stat-label">Uploaded Today</span>
                </div>
                <div class="s3-stat">
                    <span class="s3-stat-value"><?php echo esc_html($stats['errors_last_7_days']); ?></span>
                    <span class="s3-stat-label">Errors (7d)</span>
                </div>
            </div>
            
            <?php if ($migration_stats['pending_attachments'] > 0): ?>
            <div class="s3-migration-notice">
                <p>
                    <strong><?php echo esc_html($migration_stats['pending_attachments']); ?></strong> 
                    files pending migration 
                    (<?php echo esc_html($migration_stats['pending_size_formatted']); ?>)
                </p>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-migration'); ?>" class="button button-small">
                    Start Migration
                </a>
            </div>
            <?php endif; ?>
            
            <div class="s3-connection-status">
                <?php
                $connection = $stats['connection_status'];
                if ($connection['connected'] === true):
                ?>
                    <span class="s3-status-badge s3-status-connected">● Connected</span>
                <?php elseif ($connection['connected'] === false): ?>
                    <span class="s3-status-badge s3-status-error">● Disconnected</span>
                <?php else: ?>
                    <span class="s3-status-badge s3-status-unknown">● Unknown</span>
                <?php endif; ?>
                
                <?php if ($connection['checked_at']): ?>
                    <small>Last checked: <?php echo esc_html($connection['checked_at']); ?></small>
                <?php endif; ?>
            </div>
            
            <p class="s3-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=media-toolkit'); ?>">Dashboard</a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>">Settings</a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools'); ?>">Tools</a>
            </p>
        </div>
        
        <style>
            .s3-stats-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 15px;
            }
            .s3-stat {
                text-align: center;
                padding: 10px;
                background: #f6f7f7;
                border-radius: 4px;
            }
            .s3-stat-value {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
            }
            .s3-stat-label {
                display: block;
                font-size: 11px;
                color: #646970;
                text-transform: uppercase;
            }
            .s3-migration-notice {
                background: #fff8e5;
                border-left: 4px solid #ffb900;
                padding: 10px;
                margin-bottom: 15px;
            }
            .s3-migration-notice p {
                margin: 0 0 10px;
            }
            .s3-connection-status {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }
            .s3-status-badge {
                font-size: 12px;
                font-weight: 500;
            }
            .s3-status-connected { color: #00a32a; }
            .s3-status-error { color: #d63638; }
            .s3-status-unknown { color: #dba617; }
            .s3-widget-footer {
                margin: 0;
                padding-top: 10px;
                border-top: 1px solid #c3c4c7;
            }
        </style>
        <?php
    }
}

