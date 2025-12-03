<?php
/**
 * Settings page template with tabbed interface
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

use Metodo\MediaToolkit\Core\Environment;

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$settings = $plugin->get_settings();

$is_configured = false;
$credentials = [];
$active_environment = 'production';
$cache_control = 31536000;

if ($settings) {
    $is_configured = $settings->is_configured();
    $credentials = $settings->get_masked_credentials();
    $active_environment = $settings->get_active_environment()->value;
    $cache_control = $settings->get_cache_control_max_age();
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'environment';
?>

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
        <!-- Header -->
        <header>
            <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
                <span class="mt-logo">
                    <span class="dashicons dashicons-admin-settings"></span>
                </span>
                <?php esc_html_e('Settings', 'media-toolkit'); ?>
            </h1>
            <p class="text-lg text-gray-500 max-w-xl">
                <?php esc_html_e('Configure AWS S3 and CDN settings for your media files.', 'media-toolkit'); ?>
            </p>
        </header>

        <!-- Tab Navigation -->
        <nav class="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-xl">
            <a href="?page=media-toolkit-settings&tab=environment" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'environment' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <?php esc_html_e('Environment', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=credentials" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'credentials' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-lock"></span>
                <?php esc_html_e('Credentials', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=cdn" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'cdn' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e('CDN', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=file-options" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'file-options' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('File Options', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=general" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'general' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('General', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=update" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'update' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Update', 'media-toolkit'); ?>
            </a>
            <a href="?page=media-toolkit-settings&tab=import-export" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'import-export' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
                <span class="dashicons dashicons-database-export"></span>
                <?php esc_html_e('Import/Export', 'media-toolkit'); ?>
            </a>
        </nav>

    <!-- Tab Content -->
    <div class="bg-gray-100 rounded-xl p-6 animate-fade-in">
        <?php if ($active_tab === 'environment'): ?>
            <!-- ==================== ENVIRONMENT TAB ==================== -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">

                <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-row items-center gap-2">
                        <span class="dashicons dashicons-admin-site-alt3 text-gray-700"></span>
                        <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Active Environment', 'media-toolkit'); ?></h3>
                    </div>
                    <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('Select the active environment. Files will be stored in a separate folder for each environment.', 'media-toolkit'); ?>
                    </p>
                </div>

                <div class="p-6">
                    <p class="text-sm text-gray-600 mt-2 mb-6">
                        <?php esc_html_e('Current path:', 'media-toolkit'); ?> 
                        <code class="px-2 py-1 text-sm bg-gray-100 text-gray-700 rounded">bucket/media/<strong id="env-preview"><?php echo esc_html($active_environment); ?></strong>/wp-content/uploads/...</code>
                    </p>
                    
                    <form id="s3-environment-form">
                        <div class="max-w-xs mb-6">
                            <label for="active-environment" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Environment', 'media-toolkit'); ?></label>
                            <select name="active_environment" id="active-environment" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <?php foreach (Environment::cases() as $env): ?>
                                    <option value="<?php echo esc_attr($env->value); ?>" <?php selected($active_environment, $env->value); ?>>
                                        <?php echo esc_html(ucfirst($env->value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Different environments allow you to separate development, staging, and production files.', 'media-toolkit'); ?></p>
                        </div>
                        
                        <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-environment">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Save Environment', 'media-toolkit'); ?>
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'credentials'): ?>
            <!-- ==================== CREDENTIALS TAB ==================== -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden" id="s3-credentials-panel">

                <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-row items-center justify-between gap-2">
                        <div class="flex flex-row items-center gap-2">
                            <span class="dashicons dashicons-lock text-gray-700"></span>
                            <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('AWS Credentials', 'media-toolkit'); ?></h3>
                        </div>
                        <?php if ($is_configured): ?>
                            <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"><?php esc_html_e('Configured', 'media-toolkit'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600 m-0">
                    <?php esc_html_e('Configure your AWS credentials for S3 access. A single bucket is shared across all environments.', 'media-toolkit'); ?>
                    </p>
                </div>

                <div class="p-6">
                    
                    <form id="s3-credentials-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="access_key" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('AWS Access Key', 'media-toolkit'); ?></label>
                            <input type="text" name="access_key" id="access_key" 
                                   value="<?php echo esc_attr($credentials['access_key'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all" autocomplete="off"
                                   placeholder="AKIAIOSFODNN7EXAMPLE">
                        </div>
                        
                        <div>
                            <label for="secret_key" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('AWS Secret Key', 'media-toolkit'); ?></label>
                            <input type="password" name="secret_key" id="secret_key" 
                                   value="<?php echo esc_attr($credentials['secret_key'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all" autocomplete="off"
                                   placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY">
                        </div>
                        
                        <div>
                            <label for="region" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('AWS Region', 'media-toolkit'); ?></label>
                            <select name="region" id="region" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <option value=""><?php esc_html_e('Select Region', 'media-toolkit'); ?></option>
                                <option value="us-east-1" <?php selected($credentials['region'] ?? '', 'us-east-1'); ?>>US East (N. Virginia)</option>
                                <option value="us-east-2" <?php selected($credentials['region'] ?? '', 'us-east-2'); ?>>US East (Ohio)</option>
                                <option value="us-west-1" <?php selected($credentials['region'] ?? '', 'us-west-1'); ?>>US West (N. California)</option>
                                <option value="us-west-2" <?php selected($credentials['region'] ?? '', 'us-west-2'); ?>>US West (Oregon)</option>
                                <option value="eu-west-1" <?php selected($credentials['region'] ?? '', 'eu-west-1'); ?>>EU (Ireland)</option>
                                <option value="eu-west-2" <?php selected($credentials['region'] ?? '', 'eu-west-2'); ?>>EU (London)</option>
                                <option value="eu-west-3" <?php selected($credentials['region'] ?? '', 'eu-west-3'); ?>>EU (Paris)</option>
                                <option value="eu-central-1" <?php selected($credentials['region'] ?? '', 'eu-central-1'); ?>>EU (Frankfurt)</option>
                                <option value="eu-south-1" <?php selected($credentials['region'] ?? '', 'eu-south-1'); ?>>EU (Milan)</option>
                                <option value="ap-northeast-1" <?php selected($credentials['region'] ?? '', 'ap-northeast-1'); ?>>Asia Pacific (Tokyo)</option>
                                <option value="ap-southeast-1" <?php selected($credentials['region'] ?? '', 'ap-southeast-1'); ?>>Asia Pacific (Singapore)</option>
                                <option value="ap-southeast-2" <?php selected($credentials['region'] ?? '', 'ap-southeast-2'); ?>>Asia Pacific (Sydney)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="bucket" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('S3 Bucket Name', 'media-toolkit'); ?></label>
                            <input type="text" name="bucket" id="bucket" 
                                   value="<?php echo esc_attr($credentials['bucket'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all"
                                   placeholder="my-bucket-name">
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-credentials">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Save Credentials', 'media-toolkit'); ?>
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed" id="btn-test-credentials" disabled>
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <?php esc_html_e('Test Connection', 'media-toolkit'); ?>
                        </button>
                    </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'cdn'): ?>
            <!-- ==================== CDN TAB ==================== -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden" id="cdn-settings-panel">

                <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-row items-center justify-between gap-2">
                        <div class="flex flex-row items-center gap-2">
                            <span class="dashicons dashicons-lock text-gray-700"></span>
                            <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('CDN Settings', 'media-toolkit'); ?></h3>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 m-0">
                    <?php esc_html_e('Configure your CDN for serving files (Cloudflare, CloudFront, or custom)', 'media-toolkit'); ?>
                    </p>
                </div>

                <div class="p-6">
                    
                    <form id="s3-cdn-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="cdn_provider" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('CDN Provider', 'media-toolkit'); ?></label>
                            <select name="cdn_provider" id="cdn_provider" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <option value="none" <?php selected($credentials['cdn_provider'] ?? 'none', 'none'); ?>><?php esc_html_e('None (direct S3 URLs)', 'media-toolkit'); ?></option>
                                <option value="cloudflare" <?php selected($credentials['cdn_provider'] ?? '', 'cloudflare'); ?>>Cloudflare</option>
                                <option value="cloudfront" <?php selected($credentials['cdn_provider'] ?? '', 'cloudfront'); ?>>CloudFront</option>
                                <option value="other" <?php selected($credentials['cdn_provider'] ?? '', 'other'); ?>><?php esc_html_e('Other CDN', 'media-toolkit'); ?></option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="cdn_url" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('CDN URL', 'media-toolkit'); ?></label>
                            <input type="url" name="cdn_url" id="cdn_url" 
                                   value="<?php echo esc_attr($credentials['cdn_url'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all"
                                   placeholder="https://media.example.com">
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('The public URL to access your files through the CDN', 'media-toolkit'); ?></p>
                        </div>
                    </div>

                    <!-- Cloudflare Settings -->
                    <div id="cloudflare-settings" class="hidden mb-6 p-5 bg-gray-50 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 m-0">
                            <span class="dashicons dashicons-cloud text-gray-600"></span>
                            <?php esc_html_e('Cloudflare Cache Purge', 'media-toolkit'); ?>
                        </h4>
                        <p class="text-sm text-gray-600 mt-2 mb-4"><?php esc_html_e('Optional. Required only for automatic cache purging when files are updated/deleted.', 'media-toolkit'); ?></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="cloudflare_zone_id" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Zone ID', 'media-toolkit'); ?></label>
                                <input type="text" name="cloudflare_zone_id" id="cloudflare_zone_id" 
                                       value="<?php echo esc_attr($credentials['cloudflare_zone_id'] ?? ''); ?>"
                                       class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all"
                                       placeholder="abc123def456...">
                                <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('Found in Cloudflare Dashboard → Your site → Overview', 'media-toolkit'); ?></p>
                            </div>
                            
                            <div>
                                <label for="cloudflare_api_token" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('API Token', 'media-toolkit'); ?></label>
                                <input type="password" name="cloudflare_api_token" id="cloudflare_api_token" 
                                       value="<?php echo esc_attr($credentials['cloudflare_api_token'] ?? ''); ?>"
                                       class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all" autocomplete="off">
                                <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('Create a token with "Zone.Cache Purge" permission', 'media-toolkit'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- CloudFront Settings -->
                    <div id="cloudfront-settings" class="hidden mb-6 p-5 bg-gray-50 rounded-xl">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="dashicons dashicons-cloud text-gray-600"></span>
                            <?php esc_html_e('CloudFront Cache Invalidation', 'media-toolkit'); ?>
                        </h4>
                        <div class="max-w-md mt-4">
                            <label for="cloudfront_distribution_id" class="block text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Distribution ID', 'media-toolkit'); ?></label>
                            <input type="text" name="cloudfront_distribution_id" id="cloudfront_distribution_id" 
                                   value="<?php echo esc_attr($credentials['cloudfront_distribution_id'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all"
                                   placeholder="E1A2B3C4D5F6G7">
                            <p class="mt-1 text-sm text-gray-500"><?php esc_html_e('Required for cache invalidation', 'media-toolkit'); ?></p>
                        </div>
                    </div>
                    
                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-cdn">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save CDN Settings', 'media-toolkit'); ?>
                    </button>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'file-options'): ?>
            <!-- ==================== FILE OPTIONS TAB ==================== -->
            <?php 
            $content_disposition = $settings ? $settings->get_content_disposition_settings() : [];
            $file_type_categories = \Metodo\MediaToolkit\Core\Settings::FILE_TYPE_CATEGORIES;
            ?>
            
            <form id="s3-file-options-form" class="space-y-6">
                <!-- Cache-Control Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-row items-center justify-between gap-2">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Cache-Control', 'media-toolkit'); ?></h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('Configure browser caching behavior for uploaded files.', 'media-toolkit'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        
                        <div class="max-w-lg">
                            <label for="cache_control" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Cache-Control for New Uploads', 'media-toolkit'); ?></label>
                            <select name="cache_control" id="cache_control" class="w-full px-4 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                <option value="0" <?php selected($cache_control, 0); ?>><?php esc_html_e('No cache (no-cache, no-store)', 'media-toolkit'); ?></option>
                                <option value="86400" <?php selected($cache_control, 86400); ?>><?php esc_html_e('1 day (86,400 seconds)', 'media-toolkit'); ?></option>
                                <option value="604800" <?php selected($cache_control, 604800); ?>><?php esc_html_e('1 week (604,800 seconds)', 'media-toolkit'); ?></option>
                                <option value="2592000" <?php selected($cache_control, 2592000); ?>><?php esc_html_e('1 month (2,592,000 seconds)', 'media-toolkit'); ?></option>
                                <option value="31536000" <?php selected($cache_control, 31536000); ?>><?php esc_html_e('1 year (31,536,000 seconds) — Recommended', 'media-toolkit'); ?></option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500">
                                <?php 
                                printf(
                                    esc_html__('Sets the Cache-Control header on new uploaded files. To update existing files, go to %s.', 'media-toolkit'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-tools&tab=cache-sync')) . '" class="font-medium text-gray-900 hover:text-accent-500">' . esc_html__('Tools → Cache Headers', 'media-toolkit') . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Content-Disposition Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-row items-center justify-between gap-2">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Content-Disposition', 'media-toolkit'); ?></h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('Configure how browsers handle files when users click on direct links. This setting controls whether files are displayed in the browser or downloaded automatically.', 'media-toolkit'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <!-- Explanation Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="dashicons dashicons-visibility text-blue-500"></span>
                                <strong class="text-sm font-semibold text-gray-900"><?php esc_html_e('Inline', 'media-toolkit'); ?></strong>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?php esc_html_e('File opens directly in the browser (if supported)', 'media-toolkit'); ?></p>
                            <ul class="text-sm text-gray-500 space-y-1">
                                <li>• <?php esc_html_e('Better user experience for previewing', 'media-toolkit'); ?></li>
                                <li>• <?php esc_html_e('Images/PDFs/videos display in browser', 'media-toolkit'); ?></li>
                            </ul>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="dashicons dashicons-download text-green-500"></span>
                                <strong class="text-sm font-semibold text-gray-900"><?php esc_html_e('Attachment', 'media-toolkit'); ?></strong>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?php esc_html_e('File downloads automatically with original filename', 'media-toolkit'); ?></p>
                            <ul class="text-sm text-gray-500 space-y-1">
                                <li>• <?php esc_html_e('One-click download for users', 'media-toolkit'); ?></li>
                                <li>• <?php esc_html_e('Preserves original filename', 'media-toolkit'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                        <span class="dashicons dashicons-media-default text-gray-600"></span>
                        <?php esc_html_e('Settings by File Type', 'media-toolkit'); ?>
                    </h4>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse border border-gray-200 rounded-lg">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php esc_html_e('File Type', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php esc_html_e('Description', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-48"><?php esc_html_e('Behavior', 'media-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($file_type_categories as $type => $config): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900"><?php echo esc_html($config['label']); ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?php echo esc_html($config['description']); ?></td>
                                    <td class="px-4 py-3">
                                        <select name="content_disposition_<?php echo esc_attr($type); ?>" 
                                                id="content_disposition_<?php echo esc_attr($type); ?>" 
                                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all">
                                            <option value="inline" <?php selected($content_disposition[$type] ?? $config['default'], 'inline'); ?>>
                                                <?php esc_html_e('Inline', 'media-toolkit'); ?>
                                            </option>
                                            <option value="attachment" <?php selected($content_disposition[$type] ?? $config['default'], 'attachment'); ?>>
                                                <?php esc_html_e('Attachment', 'media-toolkit'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                        <p class="mt-4 text-sm text-gray-500">
                            <strong><?php esc_html_e('Tip:', 'media-toolkit'); ?></strong> 
                            <?php esc_html_e('Use "Attachment" for files that users typically want to download (like ZIP archives), and "Inline" for files they usually want to preview (like images and PDFs).', 'media-toolkit'); ?>
                        </p>
                    </div>
                </div>
                
                <div>
                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-file-options">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save File Options', 'media-toolkit'); ?>
                    </button>
                </div>
            </form>

        <?php elseif ($active_tab === 'general'): ?>
            <!-- ==================== GENERAL TAB ==================== -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-row items-center justify-between gap-2">
                        <div class="flex flex-row items-center gap-2">
                            <span class="dashicons dashicons-lock text-gray-700"></span>
                            <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('General Options', 'media-toolkit'); ?></h3>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 m-0">
                    <?php esc_html_e('Configure general options for the plugin.', 'media-toolkit'); ?>
                    </p>
                </div>
                <div class="p-6">
                    <form id="s3-general-form">
                    <div class="space-y-5">
                        <div>
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="remove_local" id="remove_local" value="true"
                                       <?php checked($settings ? $settings->should_remove_local_files() : false); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900"><?php esc_html_e('Delete local files after uploading to S3', 'media-toolkit'); ?></div>
                                    <div class="text-xs text-gray-500"><?php esc_html_e('This saves disk space but means files only exist on S3', 'media-toolkit'); ?></div>
                                </div>
                            </label>
                        </div>
                        
                        <div>
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="remove_on_uninstall" id="remove_on_uninstall" value="true"
                                       <?php checked($settings ? $settings->should_remove_on_uninstall() : false); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900"><?php esc_html_e('Delete all plugin data when uninstalling', 'media-toolkit'); ?></div>
                                    <div class="text-xs text-gray-500"><?php esc_html_e('Files on S3 will NOT be deleted', 'media-toolkit'); ?></div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                        <div class="mt-6">
                            <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-general">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save Options', 'media-toolkit'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'update'): ?>
            <!-- ==================== UPDATE TAB ==================== -->
            <?php
            $update_settings = get_option('media_toolkit_update_settings', []);
            $has_token = !empty($update_settings['github_token_encrypted']);
            $token_via_constant = defined('MEDIA_TOOLKIT_GITHUB_TOKEN') && !empty(MEDIA_TOOLKIT_GITHUB_TOKEN);
            ?>
            
            <div class="space-y-6">
                <!-- Plugin Updates Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-row items-center justify-between gap-2">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Plugin Updates', 'media-toolkit'); ?></h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('Configure automatic updates from GitHub repository.', 'media-toolkit'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Current Version Card -->
                        <div class="p-5 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="dashicons dashicons-info text-gray-600"></span>
                                <h3 class="text-sm font-semibold text-gray-900 m-0"><?php esc_html_e('Current Version', 'media-toolkit'); ?></h3>
                            </div>
                            <div class="space-y-3 mt-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Installed Version', 'media-toolkit'); ?></span>
                                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-gray-200 text-gray-800">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Repository', 'media-toolkit'); ?></span>
                                    <a href="https://github.com/michelemarri/media-toolkit" target="_blank" rel="noopener" class="text-sm font-medium text-gray-900 hover:text-accent-500 inline-flex items-center gap-1">
                                        michelemarri/media-toolkit
                                        <span class="dashicons dashicons-external text-xs"></span>
                                    </a>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500"><?php esc_html_e('Last Check', 'media-toolkit'); ?></span>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php
                                        $last_check = get_site_transient('update_plugins');
                                        if ($last_check && isset($last_check->last_checked)) {
                                            echo esc_html(human_time_diff($last_check->last_checked) . ' ' . __('ago', 'media-toolkit'));
                                        } else {
                                            esc_html_e('Never', 'media-toolkit');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- GitHub Authentication Card -->
                        <div class="p-5 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-600"></span>
                                <h3 class="text-sm font-semibold text-gray-900 m-0"><?php esc_html_e('GitHub Authentication', 'media-toolkit'); ?></h3>
                            </div>
                            <?php if ($token_via_constant): ?>
                                <div class="flex gap-3 p-4 rounded-lg bg-blue-50 text-blue-800 mt-4">
                                    <span class="dashicons dashicons-info text-blue-600 flex-shrink-0"></span>
                                    <div>
                                        <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('Token configured via wp-config.php', 'media-toolkit'); ?></strong>
                                        <p class="text-sm opacity-90"><?php esc_html_e('Your GitHub token is defined using the MEDIA_TOOLKIT_GITHUB_TOKEN constant. This is the most secure method.', 'media-toolkit'); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4 mt-4">
                                    <div>
                                        <label for="github_token" class="block text-sm font-medium text-gray-700 mb-2">
                                            <?php esc_html_e('GitHub Personal Access Token', 'media-toolkit'); ?>
                                        </label>
                                        <div class="flex gap-2">
                                            <input type="password"
                                                   name="github_token"
                                                   id="github_token"
                                                   value="<?php echo $has_token ? '••••••••••••••••' : ''; ?>"
                                                   placeholder="<?php esc_attr_e('ghp_xxxxxxxxxxxxxxxxxxxx', 'media-toolkit'); ?>"
                                                   class="flex-1 px-4 py-2.5 text-sm font-mono bg-white border border-gray-300 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 outline-none transition-all"
                                                   autocomplete="off">
                                            <button type="button" class="inline-flex items-center justify-center w-10 h-10 text-gray-500 bg-white hover:bg-gray-50 rounded-lg transition-all" id="btn-toggle-password">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                        </div>

                                        <?php if ($has_token): ?>
                                            <div class="flex items-center gap-2 mt-2 px-3 py-2 rounded-lg bg-green-50 text-green-700">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <span class="text-sm"><?php esc_html_e('Token configured and encrypted', 'media-toolkit'); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <p class="mt-2 text-sm text-gray-500">
                                            <?php
                                            printf(
                                                esc_html__('Required for private repositories. %s', 'media-toolkit'),
                                                '<a href="https://github.com/settings/tokens?type=beta" target="_blank" rel="noopener" class="font-medium text-gray-900 hover:text-accent-500">' . 
                                                esc_html__('Create a token on GitHub', 'media-toolkit') . 
                                                ' <span class="dashicons dashicons-external text-xs"></span></a>'
                                            );
                                            ?>
                                        </p>
                                    </div>

                                    <?php if ($has_token): ?>
                                    <button type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all" id="btn-remove-token">
                                        <span class="dashicons dashicons-trash text-sm"></span>
                                        <?php esc_html_e('Remove Token', 'media-toolkit'); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Update Settings Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-row items-center justify-between gap-2">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0 m-0"><?php esc_html_e('Update Settings', 'media-toolkit'); ?></h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('Configure how the plugin handles updates.', 'media-toolkit'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Auto-Update Card -->
                        <div class="p-5 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="dashicons dashicons-update-alt text-gray-600"></span>
                                <h3 class="text-sm font-semibold text-gray-900 m-0"><?php esc_html_e('Auto-Update', 'media-toolkit'); ?></h3>
                            </div>
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer mt-4">
                                <input type="checkbox"
                                       name="auto_update"
                                       id="auto_update"
                                       value="1"
                                       <?php checked(!empty($update_settings['auto_update'])); ?>>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('Enable Auto-Updates', 'media-toolkit'); ?></span>
                                    <span class="block text-sm text-gray-500"><?php esc_html_e('Automatically update the plugin when a new version is available.', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                        </div>

                        <!-- Manual Check Card -->
                        <div class="p-5 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="dashicons dashicons-search text-gray-600"></span>
                                <h3 class="text-sm font-semibold text-gray-900 m-0"><?php esc_html_e('Manual Check', 'media-toolkit'); ?></h3>
                            </div>
                            <p class="text-sm text-gray-600 mt-4 mb-4">
                                <?php esc_html_e('Force a check for available updates from the GitHub repository.', 'media-toolkit'); ?>
                            </p>
                            
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-lg transition-all" id="btn-check-updates">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Check for Updates', 'media-toolkit'); ?>
                            </button>
                            
                            <div id="update-check-result" class="hidden mt-3 p-3 text-sm rounded-lg"></div>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Security Information Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-row items-center justify-between gap-2">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Security Information', 'media-toolkit'); ?></h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 m-0">
                        <?php esc_html_e('How your GitHub token is protected.', 'media-toolkit'); ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="p-5 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="dashicons dashicons-lock text-gray-600"></span>
                                <h4 class="text-sm font-semibold text-gray-900 m-0"><?php esc_html_e('Token Security', 'media-toolkit'); ?></h4>
                            </div>
                            <ul class="space-y-4 mt-4">
                            <li class="flex items-start gap-3">
                                <span class="dashicons dashicons-yes text-green-500 flex-shrink-0 mt-0.5"></span>
                                <div>
                                    <strong class="text-sm font-medium text-gray-900"><?php esc_html_e('AES-256-CBC Encryption', 'media-toolkit'); ?></strong>
                                    <p class="text-sm text-gray-500"><?php esc_html_e('Your token is encrypted using industry-standard AES-256-CBC encryption before being stored.', 'media-toolkit'); ?></p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="dashicons dashicons-yes text-green-500 flex-shrink-0 mt-0.5"></span>
                                <div>
                                    <strong class="text-sm font-medium text-gray-900"><?php esc_html_e('Unique Encryption Key', 'media-toolkit'); ?></strong>
                                    <p class="text-sm text-gray-500"><?php esc_html_e('The encryption uses your WordPress AUTH_KEY, making it unique to your installation.', 'media-toolkit'); ?></p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="dashicons dashicons-yes text-green-500 flex-shrink-0 mt-0.5"></span>
                                <div>
                                    <strong class="text-sm font-medium text-gray-900"><?php esc_html_e('Never Displayed', 'media-toolkit'); ?></strong>
                                    <p class="text-sm text-gray-500"><?php esc_html_e('The original token is never displayed after being saved. Only a masked placeholder is shown.', 'media-toolkit'); ?></p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="dashicons dashicons-info text-blue-500 flex-shrink-0 mt-0.5"></span>
                                <div>
                                    <strong class="text-sm font-medium text-gray-900"><?php esc_html_e('Alternative Method', 'media-toolkit'); ?></strong>
                                    <p class="text-sm text-gray-500"><?php esc_html_e('For maximum security, you can define the token in wp-config.php:', 'media-toolkit'); ?></p>
                                    <code class="block mt-2 px-3 py-2 text-xs bg-white rounded-lg font-mono overflow-x-auto">define('MEDIA_TOOLKIT_GITHUB_TOKEN', 'your-token');</code>
                                </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div>
                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-save-update-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                    </button>
                </div>
            </div>

        <?php elseif ($active_tab === 'import-export'): ?>
            <!-- ==================== IMPORT/EXPORT TAB ==================== -->
            <div class="space-y-6">
                <!-- Info Notice -->
                <div class="flex gap-3 p-4 rounded-xl bg-blue-50 text-blue-800">
                    <span class="dashicons dashicons-info text-blue-600 flex-shrink-0 mt-0.5"></span>
                    <div>
                        <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('About Import/Export', 'media-toolkit'); ?></strong>
                        <p class="text-sm opacity-90 m-0">
                            <?php esc_html_e('Export your settings to transfer them to another site or create a backup. Sensitive data (AWS credentials, API tokens) are excluded for security reasons.', 'media-toolkit'); ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Export Settings Card -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-download text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Export Settings', 'media-toolkit'); ?></h3>
                            </div>
                            <p class="text-sm text-gray-600 m-0">
                                <?php esc_html_e('Download all plugin settings as a JSON file.', 'media-toolkit'); ?>
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900 m-0 mb-3">
                                        <span class="dashicons dashicons-yes-alt text-green-500"></span>
                                        <?php esc_html_e('What will be exported', 'media-toolkit'); ?>
                                    </h4>
                                    <ul class="text-sm text-gray-600 space-y-1.5 m-0">
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-yes text-green-500 text-xs"></span>
                                            <?php esc_html_e('Active environment', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-yes text-green-500 text-xs"></span>
                                            <?php esc_html_e('Cache-Control settings', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-yes text-green-500 text-xs"></span>
                                            <?php esc_html_e('Content-Disposition settings', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-yes text-green-500 text-xs"></span>
                                            <?php esc_html_e('General options', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-yes text-green-500 text-xs"></span>
                                            <?php esc_html_e('Update preferences', 'media-toolkit'); ?>
                                        </li>
                                    </ul>
                                </div>

                                <div class="p-4 bg-amber-50 rounded-xl">
                                    <h4 class="flex items-center gap-2 text-sm font-semibold text-amber-900 m-0 mb-3">
                                        <span class="dashicons dashicons-lock text-amber-500"></span>
                                        <?php esc_html_e('Excluded for security', 'media-toolkit'); ?>
                                    </h4>
                                    <ul class="text-sm text-amber-800 space-y-1.5 m-0">
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-no text-amber-500 text-xs"></span>
                                            <?php esc_html_e('AWS credentials', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-no text-amber-500 text-xs"></span>
                                            <?php esc_html_e('GitHub tokens', 'media-toolkit'); ?>
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="dashicons dashicons-no text-amber-500 text-xs"></span>
                                            <?php esc_html_e('CDN API tokens', 'media-toolkit'); ?>
                                        </li>
                                    </ul>
                                </div>

                                <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all" id="btn-export-settings">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Export Settings', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Import Settings Card -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="flex flex-col gap-1 px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-row items-center gap-2">
                                <span class="dashicons dashicons-upload text-gray-700"></span>
                                <h3 class="text-lg font-semibold text-gray-900 m-0"><?php esc_html_e('Import Settings', 'media-toolkit'); ?></h3>
                            </div>
                            <p class="text-sm text-gray-600 m-0">
                                <?php esc_html_e('Restore settings from a previously exported JSON file.', 'media-toolkit'); ?>
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="p-4 border-2 border-dashed border-gray-300 rounded-xl hover:border-gray-400 transition-colors" id="import-drop-zone">
                                    <div class="text-center">
                                        <span class="dashicons dashicons-upload text-3xl text-gray-400 mb-2"></span>
                                        <p class="text-sm font-medium text-gray-900 mb-1"><?php esc_html_e('Drop your file here or click to browse', 'media-toolkit'); ?></p>
                                        <p class="text-xs text-gray-500"><?php esc_html_e('Accepts .json files only', 'media-toolkit'); ?></p>
                                        <input type="file" id="import-file-input" accept=".json" class="hidden">
                                    </div>
                                </div>

                                <div id="import-file-preview" class="hidden p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="dashicons dashicons-media-code text-gray-500"></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate m-0" id="import-file-name"></p>
                                                <p class="text-xs text-gray-500 m-0" id="import-file-info"></p>
                                            </div>
                                        </div>
                                        <button type="button" class="flex-shrink-0 text-gray-400 hover:text-gray-600" id="btn-remove-import-file">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="import_merge" id="import_merge" value="1">
                                        <span class="mt-toggle-slider"></span>
                                        <div>
                                            <span class="block text-sm font-medium text-gray-900"><?php esc_html_e('Merge with existing settings', 'media-toolkit'); ?></span>
                                            <span class="block text-xs text-gray-500"><?php esc_html_e('If unchecked, imported settings will completely replace existing ones.', 'media-toolkit'); ?></span>
                                        </div>
                                    </label>
                                </div>

                                <div id="import-result" class="hidden p-4 rounded-xl"></div>

                                <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed" id="btn-import-settings" disabled>
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('Import Settings', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="text-center text-sm text-gray-400 py-6">
            <p>
                <?php
                printf(
                    esc_html__('Developed by %s', 'media-toolkit'),
                    '<a href="https://metodo.dev" target="_blank" rel="noopener" class="font-medium hover:text-accent-500">Michele Marri - Metodo.dev</a>'
                );
                ?>
                &bull;
                <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
            </p>
        </footer>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="test-connection-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><?php esc_html_e('Testing Connection...', 'media-toolkit'); ?></h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6">
            <div id="test-results"></div>
        </div>
    </div>
</div>
