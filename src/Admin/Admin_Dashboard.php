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
        <div class="mds-widget">
            <div class="mds-widget-stats">
                <div class="mds-widget-stat">
                    <span class="mds-widget-stat-value"><?php echo esc_html($stats['total_files']); ?></span>
                    <span class="mds-widget-stat-label"><?php esc_html_e('Files on S3', 'media-toolkit'); ?></span>
                </div>
                <div class="mds-widget-stat">
                    <span class="mds-widget-stat-value"><?php echo esc_html($stats['total_storage_formatted']); ?></span>
                    <span class="mds-widget-stat-label"><?php esc_html_e('Storage Used', 'media-toolkit'); ?></span>
                </div>
                <div class="mds-widget-stat">
                    <span class="mds-widget-stat-value"><?php echo esc_html($stats['files_today']); ?></span>
                    <span class="mds-widget-stat-label"><?php esc_html_e('Uploaded Today', 'media-toolkit'); ?></span>
                </div>
                <div class="mds-widget-stat">
                    <span class="mds-widget-stat-value"><?php echo esc_html($stats['errors_last_7_days']); ?></span>
                    <span class="mds-widget-stat-label"><?php esc_html_e('Errors (7d)', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <?php if ($migration_stats['pending_attachments'] > 0): ?>
            <div class="mds-alert mds-alert-warning mds-alert-sm">
                <p>
                    <strong><?php echo esc_html($migration_stats['pending_attachments']); ?></strong> 
                    <?php esc_html_e('files pending migration', 'media-toolkit'); ?> 
                    (<?php echo esc_html($migration_stats['pending_size_formatted']); ?>)
                </p>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-migration'); ?>" class="button button-small">
                    <?php esc_html_e('Start Migration', 'media-toolkit'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="mds-widget-status">
                <?php
                $connection = $stats['connection_status'];
                if ($connection['connected'] === true):
                ?>
                    <span class="mds-badge mds-badge-success">● <?php esc_html_e('Connected', 'media-toolkit'); ?></span>
                <?php elseif ($connection['connected'] === false): ?>
                    <span class="mds-badge mds-badge-error">● <?php esc_html_e('Disconnected', 'media-toolkit'); ?></span>
                <?php else: ?>
                    <span class="mds-badge mds-badge-warning">● <?php esc_html_e('Unknown', 'media-toolkit'); ?></span>
                <?php endif; ?>
                
                <?php if ($connection['checked_at']): ?>
                    <small class="mds-text-muted"><?php esc_html_e('Last checked:', 'media-toolkit'); ?> <?php echo esc_html($connection['checked_at']); ?></small>
                <?php endif; ?>
            </div>
            
            <p class="mds-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=media-toolkit'); ?>"><?php esc_html_e('Dashboard', 'media-toolkit'); ?></a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>"><?php esc_html_e('Settings', 'media-toolkit'); ?></a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools'); ?>"><?php esc_html_e('Tools', 'media-toolkit'); ?></a>
            </p>
        </div>
        <?php
    }
}

