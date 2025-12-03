<?php

declare(strict_types=1);

/**
 * Plugin Name:       Media Toolkit
 * Plugin URI:        https://github.com/michelemarri/media-toolkit
 * Description:       Complete media management toolkit for WordPress. Offload media to Amazon S3, CDN integration (Cloudflare, CloudFront), image optimization, and advanced tools.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Tested up to:      6.8
 * Requires PHP:      8.2
 * Author:            Michele Marri
 * Author URI:        https://metodo.dev
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       media-toolkit
 * Domain Path:       /languages
 *
 * @package Metodo\MediaToolkit
 */

namespace Metodo\MediaToolkit;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MEDIA_TOOLKIT_VERSION', '1.2.1');
define('MEDIA_TOOLKIT_PLUGIN_FILE', __FILE__);
define('MEDIA_TOOLKIT_PATH', plugin_dir_path(__FILE__));
define('MEDIA_TOOLKIT_URL', plugin_dir_url(__FILE__));
define('MEDIA_TOOLKIT_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader
 */
if (file_exists(MEDIA_TOOLKIT_PATH . 'vendor/autoload.php')) {
    require_once MEDIA_TOOLKIT_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin
 *
 * @return Plugin
 */
function media_toolkit_init(): Plugin
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new Plugin();
        $plugin->init();
    }

    return $plugin;
}

/**
 * Get plugin instance (alias for backward compatibility)
 *
 * @return Plugin
 */
function media_toolkit(): Plugin
{
    return media_toolkit_init();
}

// Initialize on plugins_loaded
add_action('plugins_loaded', __NAMESPACE__ . '\\media_toolkit_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function (): void {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<strong>Media Toolkit:</strong> This plugin requires PHP 8.2 or higher. ' .
            'You are running PHP ' . PHP_VERSION . '.',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    Plugin::activate();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function (): void {
    Plugin::deactivate();
});
