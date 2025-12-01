<?php

declare(strict_types=1);

/**
 * Media Toolkit Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package Metodo\MediaToolkit
 * @author  Michele Marri <plugins@metodo.dev>
 * @license GPL-3.0-or-later
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user wants to remove data
$settings = get_option('media_toolkit_settings', []);

if (empty($settings['remove_on_uninstall'])) {
    // User chose to keep data, exit early
    return;
}

global $wpdb;

// Drop custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_toolkit_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}media_toolkit_history");

// Delete plugin options
delete_option('media_toolkit_settings');
delete_option('media_toolkit_db_version');
delete_option('media_toolkit_active_env');
delete_option('media_toolkit_remove_local');
delete_option('media_toolkit_remove_on_uninstall');
delete_option('media_toolkit_cache_control');
delete_option('media_toolkit_content_disposition');
delete_option('media_toolkit_sync_interval');
delete_option('media_toolkit_s3_stats');

// Delete transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_media_toolkit_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_media_toolkit_') . '%'
    )
);

// Delete post meta (optional - files remain on S3)
// Uncomment to remove all migration metadata:
// $wpdb->query(
//     $wpdb->prepare(
//         "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
//         $wpdb->esc_like('_media_toolkit_') . '%'
//     )
// );

// Clear scheduled cron events
wp_clear_scheduled_hook('media_toolkit_cleanup_logs');
wp_clear_scheduled_hook('media_toolkit_retry_failed');
wp_clear_scheduled_hook('media_toolkit_batch_invalidation');
wp_clear_scheduled_hook('media_toolkit_sync_s3_stats');

