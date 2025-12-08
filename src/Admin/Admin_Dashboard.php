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
            echo '<p>Storage is not configured. <a href="' . admin_url('admin.php?page=media-toolkit-settings') . '">Configure now</a></p>';
            return;
        }

        $stats = $this->stats->get_dashboard_stats();
        $migration_stats = $this->stats->get_migration_stats();
        ?>
        <div class="mt-widget" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                <div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 8px;">
                    <span style="display: block; font-size: 24px; font-weight: 700; color: #111827;"><?php echo esc_html($stats['total_files']); ?></span>
                    <span style="font-size: 12px; color: #6b7280;"><?php esc_html_e('Files on Cloud', 'media-toolkit'); ?></span>
                </div>
                <div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 8px;">
                    <span style="display: block; font-size: 24px; font-weight: 700; color: #111827;"><?php echo esc_html($stats['total_storage_formatted']); ?></span>
                    <span style="font-size: 12px; color: #6b7280;"><?php esc_html_e('Storage Used', 'media-toolkit'); ?></span>
                </div>
                <div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 8px;">
                    <span style="display: block; font-size: 24px; font-weight: 700; color: #111827;"><?php echo esc_html($stats['files_today']); ?></span>
                    <span style="font-size: 12px; color: #6b7280;"><?php esc_html_e('Uploaded Today', 'media-toolkit'); ?></span>
                </div>
                <div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 8px;">
                    <span style="display: block; font-size: 24px; font-weight: 700; color: #111827;"><?php echo esc_html($stats['errors_last_7_days']); ?></span>
                    <span style="font-size: 12px; color: #6b7280;"><?php esc_html_e('Errors (7d)', 'media-toolkit'); ?></span>
                </div>
            </div>
            
            <?php if ($migration_stats['pending_attachments'] > 0): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; margin-bottom: 16px;">
                <p style="margin: 0; color: #92400e; font-size: 13px;">
                    <strong><?php echo esc_html($migration_stats['pending_attachments']); ?></strong> 
                    <?php esc_html_e('files pending migration', 'media-toolkit'); ?> 
                    (<?php echo esc_html($migration_stats['pending_size_formatted']); ?>)
                </p>
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-migration'); ?>" class="button button-small">
                    <?php esc_html_e('Start Migration', 'media-toolkit'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <?php
                $connection = $stats['connection_status'];
                if ($connection['connected'] === true):
                ?>
                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; font-weight: 500; background: #dcfce7; color: #166534; border-radius: 9999px;">● <?php esc_html_e('Connected', 'media-toolkit'); ?></span>
                <?php elseif ($connection['connected'] === false): ?>
                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; font-weight: 500; background: #fee2e2; color: #991b1b; border-radius: 9999px;">● <?php esc_html_e('Disconnected', 'media-toolkit'); ?></span>
                <?php else: ?>
                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; font-weight: 500; background: #fef3c7; color: #92400e; border-radius: 9999px;">● <?php esc_html_e('Unknown', 'media-toolkit'); ?></span>
                <?php endif; ?>
                
                <?php if ($connection['checked_at']): ?>
                    <small style="color: #6b7280; font-size: 12px;"><?php esc_html_e('Last checked:', 'media-toolkit'); ?> <?php echo esc_html($connection['checked_at']); ?></small>
                <?php endif; ?>
            </div>
            
            <p style="margin: 0; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
                <a href="<?php echo admin_url('admin.php?page=media-toolkit'); ?>" style="color: #111827; text-decoration: none;"><?php esc_html_e('Dashboard', 'media-toolkit'); ?></a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-settings'); ?>" style="color: #111827; text-decoration: none;"><?php esc_html_e('Settings', 'media-toolkit'); ?></a> | 
                <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools'); ?>" style="color: #111827; text-decoration: none;"><?php esc_html_e('Storage Tools', 'media-toolkit'); ?></a>
            </p>
        </div>
        <?php
    }
}

