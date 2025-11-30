<?php
/**
 * Plugin Name: Media Toolkit
 * Plugin URI: https://metodo.dev
 * Description: Complete media management toolkit for WordPress. Offload media to Amazon S3, CDN integration (Cloudflare, CloudFront), image optimization, and advanced tools.
 * Version: 1.0.0
 * Author: Metodo
 * Author URI: https://metodo.dev
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: media-toolkit
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.0
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit;

use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\S3\S3Config;
use Metodo\MediaToolkit\S3\UploadResult;
use Metodo\MediaToolkit\CDN\CDN_Manager;
use Metodo\MediaToolkit\CDN\CDNProvider;
use Metodo\MediaToolkit\CDN\CloudFront;
use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Environment;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Core\LogLevel;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Error\Error_Handler;
use Metodo\MediaToolkit\Error\FailedOperation;
use Metodo\MediaToolkit\Error\RetryableError;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Media\Image_Editor;
use Metodo\MediaToolkit\Media\Image_Optimizer;
use Metodo\MediaToolkit\Media\Media_Library;
use Metodo\MediaToolkit\Media\Media_Library_UI;
use Metodo\MediaToolkit\Media\Upload_Handler;
use Metodo\MediaToolkit\Migration\Batch_Processor;
use Metodo\MediaToolkit\Migration\Migration;
use Metodo\MediaToolkit\Migration\MigrationState;
use Metodo\MediaToolkit\Migration\MigrationStatus;
use Metodo\MediaToolkit\Migration\Reconciliation;
use Metodo\MediaToolkit\Stats\Stats;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', function (): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Media Toolkit:</strong> This plugin requires PHP 8.1 or higher. ';
        echo 'You are running PHP ' . PHP_VERSION . '.';
        echo '</p></div>';
    });
    return;
}

// Plugin constants
define('MEDIA_TOOLKIT_VERSION', '1.0.0');
define('MEDIA_TOOLKIT_PATH', plugin_dir_path(__FILE__));
define('MEDIA_TOOLKIT_URL', plugin_dir_url(__FILE__));
define('MEDIA_TOOLKIT_BASENAME', plugin_basename(__FILE__));

// Autoload Composer dependencies
$autoload = MEDIA_TOOLKIT_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Main plugin class
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private ?S3_Client $s3_client = null;
    private ?CloudFront $cloudfront = null;
    private ?CDN_Manager $cdn_manager = null;
    private ?Encryption $encryption = null;
    private ?Settings $settings = null;
    private ?Logger $logger = null;
    private ?History $history = null;
    private ?Stats $stats = null;
    private ?Error_Handler $error_handler = null;
    private ?Upload_Handler $upload_handler = null;
    private ?Image_Editor $image_editor = null;
    private ?Media_Library $media_library = null;
    private ?Media_Library_UI $media_library_ui = null;
    private ?Migration $migration = null;
    private ?Image_Optimizer $image_optimizer = null;
    private ?Reconciliation $reconciliation = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        // Initialize core components
        add_action('plugins_loaded', [$this, 'load_components']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Cron job handlers
        add_action('media_toolkit_cleanup_logs', [$this, 'cleanup_logs']);
        add_action('media_toolkit_retry_failed', [$this, 'retry_failed_operations']);
        add_action('media_toolkit_batch_invalidation', [$this, 'process_batch_invalidation']);
        add_action('media_toolkit_sync_s3_stats', [$this, 'sync_s3_stats']);
        
        // Add custom cron intervals (must be registered before scheduling)
        add_filter('cron_schedules', function (array $schedules): array {
            $schedules['fifteen_minutes'] = [
                'interval' => 900,
                'display' => 'Every 15 minutes',
            ];
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display' => 'Every 5 minutes',
            ];
            return $schedules;
        });
        
        // Schedule cron jobs (after cron_schedules filter is registered)
        add_action('init', [$this, 'schedule_cron_jobs']);
    }
    
    /**
     * Schedule cron jobs if not already scheduled
     */
    public function schedule_cron_jobs(): void
    {
        if (!wp_next_scheduled('media_toolkit_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'media_toolkit_cleanup_logs');
        }
        if (!wp_next_scheduled('media_toolkit_retry_failed')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'media_toolkit_retry_failed');
        }
        
        // Schedule S3 stats sync based on settings
        $this->schedule_s3_sync();
    }

    /**
     * Schedule or unschedule S3 stats sync based on interval setting
     */
    public function schedule_s3_sync(): void
    {
        $hook = 'media_toolkit_sync_s3_stats';
        $interval = $this->settings?->get_s3_sync_interval() ?? 24;
        
        // Clear existing schedule
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        
        // Schedule new if interval > 0
        if ($interval > 0 && $this->settings?->is_configured()) {
            $recurrence = $interval <= 1 ? 'hourly' : ($interval <= 12 ? 'twicedaily' : 'daily');
            wp_schedule_event(time() + 60, $recurrence, $hook);
        }
    }

    public function load_components(): void
    {
        // Core services
        $this->encryption = new Encryption();
        $this->settings = new Settings($this->encryption);
        $this->logger = new Logger();
        $this->history = new History();
        $this->stats = new Stats($this->logger, $this->history, $this->settings);
        $this->error_handler = new Error_Handler($this->logger);
        
        // Only initialize S3 if configured
        if ($this->settings->is_configured()) {
            $this->s3_client = new S3_Client($this->settings, $this->error_handler, $this->logger);
            $this->cloudfront = new CloudFront($this->settings, $this->logger);
            $this->cdn_manager = new CDN_Manager($this->settings, $this->logger);
            
            // Media handlers
            $this->upload_handler = new Upload_Handler(
                $this->s3_client,
                $this->settings,
                $this->logger,
                $this->history,
                $this->error_handler,
                $this->cdn_manager
            );
            
            $this->image_editor = new Image_Editor(
                $this->s3_client,
                $this->cdn_manager,
                $this->logger,
                $this->history,
                $this->settings
            );
            
            $this->media_library = new Media_Library(
                $this->s3_client,
                $this->settings
            );
            
            $this->migration = new Migration(
                $this->s3_client,
                $this->settings,
                $this->logger,
                $this->history,
                $this->error_handler
            );
            
            $this->image_optimizer = new Image_Optimizer(
                $this->logger,
                $this->settings,
                $this->s3_client,
                $this->history
            );
            
            $this->reconciliation = new Reconciliation(
                $this->s3_client,
                $this->settings,
                $this->logger,
                $this->history
            );
            
            // Media Library UI (admin only)
            if (is_admin()) {
                $this->media_library_ui = new Media_Library_UI(
                    $this->settings,
                    $this->s3_client,
                    $this->logger,
                    $this->history
                );
            }
        } else {
            // Image optimizer can work without S3 (local optimization only)
            $this->image_optimizer = new Image_Optimizer(
                $this->logger,
                $this->settings,
                null,
                $this->history
            );
            
            // Media Library UI still works to show status (without S3 actions)
            if (is_admin()) {
                $this->media_library_ui = new Media_Library_UI(
                    $this->settings,
                    null,
                    $this->logger,
                    $this->history
                );
            }
        }
        
        // Register AJAX handler for clearing metadata (needs to work even if S3 not configured)
        add_action('wp_ajax_media_toolkit_clear_migration_metadata', [$this, 'ajax_clear_migration_metadata']);
        
        // Admin components
        if (is_admin()) {
            new Admin\Admin_Settings(
                $this->settings,
                $this->encryption,
                $this->s3_client,
                $this->cloudfront,
                $this->logger,
                $this->history,
                $this->stats
            );
            
            new Admin\Admin_Migration($this->migration, $this->stats);
            new Admin\Admin_Dashboard($this->stats, $this->settings);
        }
    }

    /**
     * Plugin activation
     */
    public static function activate(): void
    {
        // Create database tables (suppress any output)
        ob_start();
        self::create_tables();
        ob_end_clean();
        
        // Save plugin version
        update_option('media_toolkit_db_version', MEDIA_TOOLKIT_VERSION);
        
        // Note: Cron jobs are scheduled in init() when the plugin loads
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate(): void
    {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('media_toolkit_cleanup_logs');
        wp_clear_scheduled_hook('media_toolkit_retry_failed');
        wp_clear_scheduled_hook('media_toolkit_batch_invalidation');
        wp_clear_scheduled_hook('media_toolkit_sync_s3_stats');
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall(): void
    {
        // Check if user wants to remove data
        $settings = get_option('media_toolkit_settings', []);
        
        if (!empty($settings['remove_on_uninstall'])) {
            global $wpdb;
            
            // Drop tables
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_toolkit_logs");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_toolkit_history");
            
            // Delete options
            delete_option('media_toolkit_settings');
            delete_option('media_toolkit_db_version');
            
            // Delete transients
            delete_transient('media_toolkit_migration_state');
            delete_transient('media_toolkit_invalidation_queue');
            
            // Delete post meta (optional - files remain on S3)
            // $wpdb->delete($wpdb->postmeta, ['meta_key' => '_media_toolkit_migrated']);
        }
    }

    private static function create_tables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Logs table
        $logs_table = $wpdb->prefix . 'media_toolkit_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(20) NOT NULL,
            operation VARCHAR(50) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            file_name VARCHAR(255) NULL,
            message TEXT NOT NULL,
            context JSON NULL,
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level),
            INDEX idx_attachment (attachment_id)
        ) $charset_collate;";
        
        // History table
        $history_table = $wpdb->prefix . 'media_toolkit_history';
        $history_sql = "CREATE TABLE $history_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            action VARCHAR(50) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            file_path VARCHAR(500) NULL,
            s3_key VARCHAR(500) NULL,
            file_size BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            details JSON NULL,
            INDEX idx_timestamp (timestamp),
            INDEX idx_action (action),
            INDEX idx_attachment (attachment_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($logs_sql);
        dbDelta($history_sql);
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'Media Toolkit',
            'Media Toolkit',
            'manage_options',
            'media-toolkit',
            [$this, 'render_dashboard_page'],
            'dashicons-cloud-upload',
            80
        );
        
        add_submenu_page(
            'media-toolkit',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'media-toolkit',
            [$this, 'render_dashboard_page']
        );
        
        add_submenu_page(
            'media-toolkit',
            'Settings',
            'Settings',
            'manage_options',
            'media-toolkit-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'media-toolkit',
            'Tools',
            'Tools',
            'manage_options',
            'media-toolkit-tools',
            [$this, 'render_tools_page']
        );
        
        add_submenu_page(
            'media-toolkit',
            'Optimize',
            'Optimize',
            'manage_options',
            'media-toolkit-optimize',
            [$this, 'render_optimize_page']
        );
        
        add_submenu_page(
            'media-toolkit',
            'Logs',
            'Logs',
            'manage_options',
            'media-toolkit-logs',
            [$this, 'render_logs_page']
        );
        
        add_submenu_page(
            'media-toolkit',
            'History',
            'History',
            'manage_options',
            'media-toolkit-history',
            [$this, 'render_history_page']
        );
    }

    public function render_dashboard_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/dashboard-page.php';
    }

    public function render_settings_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/settings-page.php';
    }

    public function render_tools_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/tools-page.php';
    }

    public function render_logs_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/logs-page.php';
    }

    public function render_history_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/history-page.php';
    }

    public function render_optimize_page(): void
    {
        include MEDIA_TOOLKIT_PATH . 'templates/optimize-page.php';
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (!str_contains($hook, 'media-toolkit')) {
            return;
        }
        
        wp_enqueue_style(
            'media-toolkit-admin',
            MEDIA_TOOLKIT_URL . 'admin/assets/admin.css',
            [],
            MEDIA_TOOLKIT_VERSION
        );
        
        wp_enqueue_script(
            'media-toolkit-settings',
            MEDIA_TOOLKIT_URL . 'admin/assets/settings.js',
            ['jquery'],
            MEDIA_TOOLKIT_VERSION,
            true
        );
        
        // Batch processor component (for migration, optimization, etc.)
        wp_enqueue_script(
            'media-toolkit-batch-processor',
            MEDIA_TOOLKIT_URL . 'admin/assets/batch-processor.js',
            ['jquery', 'media-toolkit-settings'],
            MEDIA_TOOLKIT_VERSION,
            true
        );
        
        $localize_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('media_toolkit_nonce'),
            'page' => $hook,
        ];
        
        wp_localize_script('media-toolkit-settings', 'mediaToolkit', $localize_data);
        
        if (str_contains($hook, 'migration')) {
            wp_enqueue_script(
                'media-toolkit-migration',
                MEDIA_TOOLKIT_URL . 'admin/assets/migration.js',
                ['jquery', 'media-toolkit-settings'],
                MEDIA_TOOLKIT_VERSION,
                true
            );
        }
    }

    public function cleanup_logs(): void
    {
        $this->logger?->cleanup_old_logs();
    }

    public function retry_failed_operations(): void
    {
        $this->error_handler?->retry_failed_operations();
    }

    public function process_batch_invalidation(): void
    {
        $this->cdn_manager?->process_batch_invalidation();
    }

    /**
     * Sync S3 bucket statistics
     */
    public function sync_s3_stats(): void
    {
        if ($this->s3_client === null || $this->settings === null) {
            return;
        }

        $stats = $this->s3_client->get_bucket_stats();
        
        if ($stats !== null) {
            $this->settings->save_s3_stats($stats);
            $this->logger?->info('sync', 'S3 stats synced: ' . $stats['files'] . ' files, ' . size_format($stats['size']));
            
            // Clear stats cache to refresh dashboard
            $this->stats?->clear_cache();
        } else {
            $this->logger?->warning('sync', 'Failed to sync S3 stats');
        }
    }

    // Getters for components
    public function get_s3_client(): ?S3_Client
    {
        return $this->s3_client;
    }

    public function get_settings(): ?Settings
    {
        return $this->settings;
    }

    public function get_logger(): ?Logger
    {
        return $this->logger;
    }

    public function get_history(): ?History
    {
        return $this->history;
    }

    public function get_cdn_manager(): ?CDN_Manager
    {
        return $this->cdn_manager;
    }

    public function get_cloudfront(): ?CloudFront
    {
        return $this->cloudfront;
    }

    public function get_image_optimizer(): ?Image_Optimizer
    {
        return $this->image_optimizer;
    }

    public function get_reconciliation(): ?Reconciliation
    {
        return $this->reconciliation;
    }

    public function get_media_library_ui(): ?Media_Library_UI
    {
        return $this->media_library_ui;
    }

    public function get_upload_handler(): ?Upload_Handler
    {
        return $this->upload_handler;
    }

    /**
     * AJAX handler for clearing all migration metadata
     */
    public function ajax_clear_migration_metadata(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)",
                '_media_toolkit_migrated',
                '_media_toolkit_key',
                '_media_toolkit_url',
                '_media_toolkit_thumb_keys'
            )
        );

        $this->logger?->info('reconciliation', "Cleared migration metadata from {$deleted} records");

        // Clear stats cache
        delete_transient('media_toolkit_stats_cache');

        wp_send_json_success(['deleted' => $deleted]);
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Plugin::class, 'uninstall']);

// Initialize plugin
Plugin::instance();

// Helper function to access plugin instance
function media_toolkit(): Plugin
{
    return Plugin::instance();
}

