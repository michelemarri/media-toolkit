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

<div class="wrap mds-wrap">
    <div class="mds-page">
        <header class="mds-page-header">
            <h1 class="mds-page-title">
                <span class="mds-logo">
                    <span class="dashicons dashicons-admin-settings"></span>
                </span>
                <?php esc_html_e('Settings', 'media-toolkit'); ?>
            </h1>
            <p class="mds-description">
                <?php esc_html_e('Configure AWS S3 and CDN settings for your media files.', 'media-toolkit'); ?>
            </p>
        </header>

        <!-- Tab Navigation -->
        <nav class="mds-tabs-nav">
        <a href="?page=media-toolkit-settings&tab=environment" class="mds-tab-link <?php echo $active_tab === 'environment' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-site-alt3"></span>
            <?php esc_html_e('Environment', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-settings&tab=credentials" class="mds-tab-link <?php echo $active_tab === 'credentials' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-lock"></span>
            <?php esc_html_e('Credentials', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-settings&tab=cdn" class="mds-tab-link <?php echo $active_tab === 'cdn' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span>
            <?php esc_html_e('CDN', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-settings&tab=file-options" class="mds-tab-link <?php echo $active_tab === 'file-options' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('File Options', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-settings&tab=general" class="mds-tab-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('General', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-settings&tab=update" class="mds-tab-link <?php echo $active_tab === 'update' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Update', 'media-toolkit'); ?>
        </a>
    </nav>

    <div class="mds-tab-content">
        <?php if ($active_tab === 'environment'): ?>
            <!-- ==================== ENVIRONMENT TAB ==================== -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3>
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <?php esc_html_e('Active Environment', 'media-toolkit'); ?>
                    </h3>
                </div>
                <div class="mds-card-body">
                    <p class="mds-text-secondary">
                        <?php esc_html_e('Select the active environment. Files will be stored in a separate folder for each environment.', 'media-toolkit'); ?>
                    </p>
                    <p class="mds-text-secondary">
                        <?php esc_html_e('Current path:', 'media-toolkit'); ?> 
                        <code class="mds-code">bucket/media/<strong id="env-preview"><?php echo esc_html($active_environment); ?></strong>/wp-content/uploads/...</code>
                    </p>
                    
                    <form id="s3-environment-form">
                        <div class="mds-form-group" style="margin-top: 20px; max-width: 300px;">
                            <label for="active-environment" class="mds-label"><?php esc_html_e('Environment', 'media-toolkit'); ?></label>
                            <select name="active_environment" id="active-environment" class="mds-select">
                                <?php foreach (Environment::cases() as $env): ?>
                                    <option value="<?php echo esc_attr($env->value); ?>" <?php selected($active_environment, $env->value); ?>>
                                        <?php echo esc_html(ucfirst($env->value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="mds-help"><?php esc_html_e('Different environments allow you to separate development, staging, and production files.', 'media-toolkit'); ?></span>
                        </div>
                        
                        <div class="mds-actions" style="margin-top: 24px;">
                            <button type="button" class="mds-btn mds-btn-primary" id="btn-save-environment">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save Environment', 'media-toolkit'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'credentials'): ?>
            <!-- ==================== CREDENTIALS TAB ==================== -->
            <div class="mds-card" id="s3-credentials-panel">
                <div class="mds-card-header">
                    <h3>
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('AWS Credentials', 'media-toolkit'); ?>
                    </h3>
                    <?php if ($is_configured): ?>
                        <span class="mds-badge mds-badge-success"><?php esc_html_e('Configured', 'media-toolkit'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="mds-card-body">
                    <p class="mds-text-secondary"><?php esc_html_e('Configure your AWS credentials for S3 access. A single bucket is shared across all environments.', 'media-toolkit'); ?></p>
                    
                    <form id="s3-credentials-form">
                        <div class="mds-form-grid">
                            <div class="mds-form-group">
                                <label for="access_key" class="mds-label"><?php esc_html_e('AWS Access Key', 'media-toolkit'); ?></label>
                                <input type="text" name="access_key" id="access_key" 
                                       value="<?php echo esc_attr($credentials['access_key'] ?? ''); ?>"
                                       class="mds-input" autocomplete="off"
                                       placeholder="AKIAIOSFODNN7EXAMPLE">
                            </div>
                            
                            <div class="mds-form-group">
                                <label for="secret_key" class="mds-label"><?php esc_html_e('AWS Secret Key', 'media-toolkit'); ?></label>
                                <input type="password" name="secret_key" id="secret_key" 
                                       value="<?php echo esc_attr($credentials['secret_key'] ?? ''); ?>"
                                       class="mds-input" autocomplete="off"
                                       placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY">
                            </div>
                            
                            <div class="mds-form-group">
                                <label for="region" class="mds-label"><?php esc_html_e('AWS Region', 'media-toolkit'); ?></label>
                                <select name="region" id="region" class="mds-select">
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
                            
                            <div class="mds-form-group">
                                <label for="bucket" class="mds-label"><?php esc_html_e('S3 Bucket Name', 'media-toolkit'); ?></label>
                                <input type="text" name="bucket" id="bucket" 
                                       value="<?php echo esc_attr($credentials['bucket'] ?? ''); ?>"
                                       class="mds-input"
                                       placeholder="my-bucket-name">
                            </div>
                        </div>
                        
                        <div class="mds-actions" style="margin-top: 24px;">
                            <button type="button" class="mds-btn mds-btn-primary" id="btn-save-credentials">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save Credentials', 'media-toolkit'); ?>
                            </button>
                            <button type="button" class="mds-btn mds-btn-secondary" id="btn-test-credentials" disabled>
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <?php esc_html_e('Test Connection', 'media-toolkit'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'cdn'): ?>
            <!-- ==================== CDN TAB ==================== -->
            <div class="mds-card" id="cdn-settings-panel">
                <div class="mds-card-header">
                    <h3>
                        <span class="dashicons dashicons-networking"></span>
                        <?php esc_html_e('CDN Settings', 'media-toolkit'); ?>
                    </h3>
                </div>
                <div class="mds-card-body">
                    <p class="mds-text-secondary"><?php esc_html_e('Configure your CDN for serving files (Cloudflare, CloudFront, or custom)', 'media-toolkit'); ?></p>
                    
                    <form id="s3-cdn-form">
                        <div class="mds-form-grid">
                            <div class="mds-form-group">
                                <label for="cdn_provider" class="mds-label"><?php esc_html_e('CDN Provider', 'media-toolkit'); ?></label>
                                <select name="cdn_provider" id="cdn_provider" class="mds-select">
                                    <option value="none" <?php selected($credentials['cdn_provider'] ?? 'none', 'none'); ?>><?php esc_html_e('None (direct S3 URLs)', 'media-toolkit'); ?></option>
                                    <option value="cloudflare" <?php selected($credentials['cdn_provider'] ?? '', 'cloudflare'); ?>>Cloudflare</option>
                                    <option value="cloudfront" <?php selected($credentials['cdn_provider'] ?? '', 'cloudfront'); ?>>CloudFront</option>
                                    <option value="other" <?php selected($credentials['cdn_provider'] ?? '', 'other'); ?>><?php esc_html_e('Other CDN', 'media-toolkit'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mds-form-group">
                                <label for="cdn_url" class="mds-label"><?php esc_html_e('CDN URL', 'media-toolkit'); ?></label>
                                <input type="url" name="cdn_url" id="cdn_url" 
                                       value="<?php echo esc_attr($credentials['cdn_url'] ?? ''); ?>"
                                       class="mds-input"
                                       placeholder="https://media.example.com">
                                <span class="mds-help"><?php esc_html_e('The public URL to access your files through the CDN', 'media-toolkit'); ?></span>
                            </div>
                        </div>

                        <!-- Cloudflare Settings -->
                        <div id="cloudflare-settings" class="mds-section" style="display: none;">
                            <h4 class="mds-section-title">
                                <span class="dashicons dashicons-cloud"></span>
                                <?php esc_html_e('Cloudflare Cache Purge', 'media-toolkit'); ?>
                            </h4>
                            <p class="mds-text-secondary"><?php esc_html_e('Optional. Required only for automatic cache purging when files are updated/deleted.', 'media-toolkit'); ?></p>
                            <div class="mds-form-grid">
                                <div class="mds-form-group">
                                    <label for="cloudflare_zone_id" class="mds-label"><?php esc_html_e('Zone ID', 'media-toolkit'); ?></label>
                                    <input type="text" name="cloudflare_zone_id" id="cloudflare_zone_id" 
                                           value="<?php echo esc_attr($credentials['cloudflare_zone_id'] ?? ''); ?>"
                                           class="mds-input"
                                           placeholder="abc123def456...">
                                    <span class="mds-help"><?php esc_html_e('Found in Cloudflare Dashboard → Your site → Overview', 'media-toolkit'); ?></span>
                                </div>
                                
                                <div class="mds-form-group">
                                    <label for="cloudflare_api_token" class="mds-label"><?php esc_html_e('API Token', 'media-toolkit'); ?></label>
                                    <input type="password" name="cloudflare_api_token" id="cloudflare_api_token" 
                                           value="<?php echo esc_attr($credentials['cloudflare_api_token'] ?? ''); ?>"
                                           class="mds-input" autocomplete="off">
                                    <span class="mds-help"><?php esc_html_e('Create a token with "Zone.Cache Purge" permission', 'media-toolkit'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- CloudFront Settings -->
                        <div id="cloudfront-settings" class="mds-section" style="display: none;">
                            <h4 class="mds-section-title">
                                <span class="dashicons dashicons-cloud"></span>
                                <?php esc_html_e('CloudFront Cache Invalidation', 'media-toolkit'); ?>
                            </h4>
                            <div class="mds-form-grid">
                                <div class="mds-form-group">
                                    <label for="cloudfront_distribution_id" class="mds-label"><?php esc_html_e('Distribution ID', 'media-toolkit'); ?></label>
                                    <input type="text" name="cloudfront_distribution_id" id="cloudfront_distribution_id" 
                                           value="<?php echo esc_attr($credentials['cloudfront_distribution_id'] ?? ''); ?>"
                                           class="mds-input"
                                           placeholder="E1A2B3C4D5F6G7">
                                    <span class="mds-help"><?php esc_html_e('Required for cache invalidation', 'media-toolkit'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mds-actions" style="margin-top: 24px;">
                            <button type="button" class="mds-btn mds-btn-primary" id="btn-save-cdn">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save CDN Settings', 'media-toolkit'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'file-options'): ?>
            <!-- ==================== FILE OPTIONS TAB ==================== -->
            <?php 
            $content_disposition = $settings ? $settings->get_content_disposition_settings() : [];
            $file_type_categories = \Metodo\MediaToolkit\Core\Settings::FILE_TYPE_CATEGORIES;
            ?>
            
            <form id="s3-file-options-form">
                <!-- Cache-Control Section -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3>
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Cache-Control', 'media-toolkit'); ?>
                        </h3>
                    </div>
                    <div class="mds-card-body">
                        <p class="mds-text-secondary"><?php esc_html_e('Configure browser caching behavior for uploaded files.', 'media-toolkit'); ?></p>
                        
                        <div class="mds-form-group" style="max-width: 500px;">
                            <label for="cache_control" class="mds-label"><?php esc_html_e('Cache-Control for New Uploads', 'media-toolkit'); ?></label>
                            <select name="cache_control" id="cache_control" class="mds-select">
                                <option value="0" <?php selected($cache_control, 0); ?>><?php esc_html_e('No cache (no-cache, no-store)', 'media-toolkit'); ?></option>
                                <option value="86400" <?php selected($cache_control, 86400); ?>><?php esc_html_e('1 day (86,400 seconds)', 'media-toolkit'); ?></option>
                                <option value="604800" <?php selected($cache_control, 604800); ?>><?php esc_html_e('1 week (604,800 seconds)', 'media-toolkit'); ?></option>
                                <option value="2592000" <?php selected($cache_control, 2592000); ?>><?php esc_html_e('1 month (2,592,000 seconds)', 'media-toolkit'); ?></option>
                                <option value="31536000" <?php selected($cache_control, 31536000); ?>><?php esc_html_e('1 year (31,536,000 seconds) — Recommended', 'media-toolkit'); ?></option>
                            </select>
                            <span class="mds-help">
                                <?php 
                                printf(
                                    esc_html__('Sets the Cache-Control header on new uploaded files. To update existing files, go to %s.', 'media-toolkit'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-tools&tab=cache-sync')) . '">' . esc_html__('Tools → Cache Headers', 'media-toolkit') . '</a>'
                                );
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Content-Disposition Section -->
                <div class="mds-card">
                    <div class="mds-card-header">
                        <h3>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Content-Disposition', 'media-toolkit'); ?>
                        </h3>
                    </div>
                    <div class="mds-card-body">
                        <p class="mds-text-secondary">
                            <?php esc_html_e('Configure how browsers handle files when users click on direct links. This setting controls whether files are displayed in the browser or downloaded automatically.', 'media-toolkit'); ?>
                        </p>
                        
                        <!-- Explanation Box -->
                        <div class="mds-cards-grid" style="margin: 20px 0;">
                            <div class="mds-card">
                                <div class="mds-card-body">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                        <span class="dashicons dashicons-visibility" style="color: var(--mds-info);"></span>
                                        <strong><?php esc_html_e('Inline', 'media-toolkit'); ?></strong>
                                    </div>
                                    <p class="mds-text-secondary"><?php esc_html_e('File opens directly in the browser (if supported)', 'media-toolkit'); ?></p>
                                    <ul class="mds-list mds-list-check" style="margin-top: 12px;">
                                        <li><?php esc_html_e('Better user experience for previewing', 'media-toolkit'); ?></li>
                                        <li><?php esc_html_e('Images/PDFs/videos display in browser', 'media-toolkit'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mds-card">
                                <div class="mds-card-body">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                        <span class="dashicons dashicons-download" style="color: var(--mds-success);"></span>
                                        <strong><?php esc_html_e('Attachment', 'media-toolkit'); ?></strong>
                                    </div>
                                    <p class="mds-text-secondary"><?php esc_html_e('File downloads automatically with original filename', 'media-toolkit'); ?></p>
                                    <ul class="mds-list mds-list-check" style="margin-top: 12px;">
                                        <li><?php esc_html_e('One-click download for users', 'media-toolkit'); ?></li>
                                        <li><?php esc_html_e('Preserves original filename', 'media-toolkit'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <h4 class="mds-section-title" style="margin-top: 24px;">
                            <span class="dashicons dashicons-media-default"></span>
                            <?php esc_html_e('Settings by File Type', 'media-toolkit'); ?>
                        </h4>
                        
                        <div class="mds-table-responsive">
                            <table class="mds-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('File Type', 'media-toolkit'); ?></th>
                                        <th><?php esc_html_e('Description', 'media-toolkit'); ?></th>
                                        <th style="width: 200px;"><?php esc_html_e('Behavior', 'media-toolkit'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($file_type_categories as $type => $config): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($config['label']); ?></strong></td>
                                        <td class="mds-text-secondary"><?php echo esc_html($config['description']); ?></td>
                                        <td>
                                            <select name="content_disposition_<?php echo esc_attr($type); ?>" 
                                                    id="content_disposition_<?php echo esc_attr($type); ?>" 
                                                    class="mds-select mds-select-sm">
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
                        
                        <p class="mds-help" style="margin-top: 16px;">
                            <strong><?php esc_html_e('Tip:', 'media-toolkit'); ?></strong> 
                            <?php esc_html_e('Use "Attachment" for files that users typically want to download (like ZIP archives), and "Inline" for files they usually want to preview (like images and PDFs).', 'media-toolkit'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="mds-actions">
                    <button type="button" class="mds-btn mds-btn-primary" id="btn-save-file-options">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save File Options', 'media-toolkit'); ?>
                    </button>
                </div>
            </form>

        <?php elseif ($active_tab === 'general'): ?>
            <!-- ==================== GENERAL TAB ==================== -->
            <div class="mds-card">
                <div class="mds-card-header">
                    <h3>
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('General Options', 'media-toolkit'); ?>
                    </h3>
                </div>
                <div class="mds-card-body">
                    <form id="s3-general-form">
                        <div class="mds-form-group">
                            <label class="mds-toggle">
                                <input type="checkbox" name="remove_local" id="remove_local" value="true"
                                       <?php checked($settings ? $settings->should_remove_local_files() : false); ?>>
                                <span class="mds-toggle-slider"></span>
                                <span class="mds-toggle-label">
                                    <strong><?php esc_html_e('Delete local files after uploading to S3', 'media-toolkit'); ?></strong>
                                </span>
                            </label>
                            <p class="mds-help" style="margin-left: 52px;">
                                <span class="mds-badge mds-badge-warning"><?php esc_html_e('Warning', 'media-toolkit'); ?></span>
                                <?php esc_html_e('This saves disk space but means files only exist on S3', 'media-toolkit'); ?>
                            </p>
                        </div>
                        
                        <div class="mds-form-group" style="margin-top: 20px;">
                            <label class="mds-toggle">
                                <input type="checkbox" name="remove_on_uninstall" id="remove_on_uninstall" value="true"
                                       <?php checked($settings ? $settings->should_remove_on_uninstall() : false); ?>>
                                <span class="mds-toggle-slider"></span>
                                <span class="mds-toggle-label">
                                    <strong><?php esc_html_e('Delete all plugin data when uninstalling', 'media-toolkit'); ?></strong>
                                </span>
                            </label>
                            <p class="mds-help" style="margin-left: 52px;">
                                <?php esc_html_e('Files on S3 will NOT be deleted', 'media-toolkit'); ?>
                            </p>
                        </div>
                        
                        <div class="mds-actions" style="margin-top: 24px;">
                            <button type="button" class="mds-btn mds-btn-primary" id="btn-save-general">
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
            
            <div class="mds-tab-panel" id="tab-update">
                <!-- Section: Plugin Updates -->
                <div class="mds-section">
                    <h2 class="mds-section-title"><?php esc_html_e('Plugin Updates', 'media-toolkit'); ?></h2>
                    <p class="mds-section-description"><?php esc_html_e('Configure automatic updates from GitHub repository.', 'media-toolkit'); ?></p>
                </div>

                <!-- Cards Grid: Current Version + GitHub Authentication -->
                <div class="mds-cards-grid">
                    <!-- Current Version Card -->
                    <div class="mds-card">
                        <div class="mds-card-header">
                            <span class="dashicons dashicons-info"></span>
                            <h3><?php esc_html_e('Current Version', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="mds-card-body">
                            <div class="mds-version-info">
                                <div class="mds-info-grid">
                                    <div class="mds-info-item">
                                        <span class="mds-info-label"><?php esc_html_e('Installed Version', 'media-toolkit'); ?></span>
                                        <span class="mds-info-value mds-version-badge">
                                            <span class="mds-badge mds-badge-primary">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
                                        </span>
                                    </div>
                                    <div class="mds-info-item">
                                        <span class="mds-info-label"><?php esc_html_e('Repository', 'media-toolkit'); ?></span>
                                        <span class="mds-info-value">
                                            <a href="https://github.com/michelemarri/media-toolkit" target="_blank" rel="noopener">
                                                michelemarri/media-toolkit
                                                <span class="dashicons dashicons-external"></span>
                                            </a>
                                        </span>
                                    </div>
                                    <div class="mds-info-item">
                                        <span class="mds-info-label"><?php esc_html_e('Last Check', 'media-toolkit'); ?></span>
                                        <span class="mds-info-value">
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
                        </div>
                    </div>

                    <!-- GitHub Authentication Card -->
                    <div class="mds-card">
                        <div class="mds-card-header">
                            <span class="dashicons dashicons-lock"></span>
                            <h3><?php esc_html_e('GitHub Authentication', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="mds-card-body">
                            <?php if ($token_via_constant): ?>
                                <div class="mds-notice mds-notice-info">
                                    <span class="dashicons dashicons-info"></span>
                                    <div>
                                        <strong><?php esc_html_e('Token configured via wp-config.php', 'media-toolkit'); ?></strong>
                                        <p><?php esc_html_e('Your GitHub token is defined using the MEDIA_TOOLKIT_GITHUB_TOKEN constant. This is the most secure method.', 'media-toolkit'); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mds-field mds-field-text">
                                    <label class="mds-field-label" for="github_token">
                                        <?php esc_html_e('GitHub Personal Access Token', 'media-toolkit'); ?>
                                    </label>
                                    
                                    <div class="mds-token-input-wrapper">
                                        <input type="password"
                                               name="github_token"
                                               id="github_token"
                                               value="<?php echo $has_token ? '••••••••••••••••' : ''; ?>"
                                               placeholder="<?php esc_attr_e('ghp_xxxxxxxxxxxxxxxxxxxx', 'media-toolkit'); ?>"
                                               class="mds-input mds-input-token"
                                               autocomplete="off">
                                        <button type="button" class="mds-btn mds-btn-icon mds-toggle-password" data-target="github_token" id="btn-toggle-password">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                    </div>

                                    <?php if ($has_token): ?>
                                        <div class="mds-token-status mds-token-status-active">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Token configured and encrypted', 'media-toolkit'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <span class="mds-field-description">
                                        <?php
                                        printf(
                                            /* translators: %s: GitHub link */
                                            esc_html__('Required for private repositories. %s', 'media-toolkit'),
                                            '<a href="https://github.com/settings/tokens?type=beta" target="_blank" rel="noopener">' . 
                                            esc_html__('Create a token on GitHub', 'media-toolkit') . 
                                            ' <span class="dashicons dashicons-external"></span></a>'
                                        );
                                        ?>
                                    </span>
                                </div>

                                <?php if ($has_token): ?>
                                <div class="mds-field">
                                    <button type="button" class="mds-btn mds-btn-danger mds-btn-sm" id="btn-remove-token">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e('Remove Token', 'media-toolkit'); ?>
                                    </button>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section: Update Settings -->
                <div class="mds-section">
                    <h2 class="mds-section-title"><?php esc_html_e('Update Settings', 'media-toolkit'); ?></h2>
                    <p class="mds-section-description"><?php esc_html_e('Configure how the plugin handles updates.', 'media-toolkit'); ?></p>
                </div>

                <!-- Cards Grid: Auto-Update + Manual Check -->
                <div class="mds-cards-grid">
                    <!-- Auto-Update Card -->
                    <div class="mds-card">
                        <div class="mds-card-header">
                            <span class="dashicons dashicons-update-alt"></span>
                            <h3><?php esc_html_e('Auto-Update', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="mds-card-body">
                            <div class="mds-field mds-field-toggle">
                                <label class="mds-toggle">
                                    <input type="checkbox"
                                           name="auto_update"
                                           id="auto_update"
                                           value="1"
                                           <?php checked(!empty($update_settings['auto_update'])); ?>>
                                    <span class="mds-toggle-slider"></span>
                                </label>
                                <div class="mds-field-content">
                                    <span class="mds-field-label"><?php esc_html_e('Enable Auto-Updates', 'media-toolkit'); ?></span>
                                    <span class="mds-field-description"><?php esc_html_e('Automatically update the plugin when a new version is available.', 'media-toolkit'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Check Card -->
                    <div class="mds-card">
                        <div class="mds-card-header">
                            <span class="dashicons dashicons-search"></span>
                            <h3><?php esc_html_e('Manual Check', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="mds-card-body">
                            <p class="mds-info">
                                <?php esc_html_e('Force a check for available updates from the GitHub repository.', 'media-toolkit'); ?>
                            </p>
                            
                            <button type="button" class="mds-btn mds-btn-secondary" id="btn-check-updates">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Check for Updates', 'media-toolkit'); ?>
                            </button>
                            
                            <div id="update-check-result" class="mds-update-result" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <!-- Section: Security Information -->
                <div class="mds-section">
                    <h2 class="mds-section-title"><?php esc_html_e('Security Information', 'media-toolkit'); ?></h2>
                    <p class="mds-section-description"><?php esc_html_e('How your GitHub token is protected.', 'media-toolkit'); ?></p>
                </div>

                <!-- Security Card (single column) -->
                <div class="mds-cards-grid mds-cards-grid-single">
                    <div class="mds-card">
                        <div class="mds-card-header">
                            <span class="dashicons dashicons-shield"></span>
                            <h3><?php esc_html_e('Token Security', 'media-toolkit'); ?></h3>
                        </div>
                        <div class="mds-card-body">
                            <div class="mds-security-info">
                                <ul class="mds-security-list">
                                    <li>
                                        <span class="dashicons dashicons-yes"></span>
                                        <strong><?php esc_html_e('AES-256-CBC Encryption', 'media-toolkit'); ?></strong>
                                        <span><?php esc_html_e('Your token is encrypted using industry-standard AES-256-CBC encryption before being stored.', 'media-toolkit'); ?></span>
                                    </li>
                                    <li>
                                        <span class="dashicons dashicons-yes"></span>
                                        <strong><?php esc_html_e('Unique Encryption Key', 'media-toolkit'); ?></strong>
                                        <span><?php esc_html_e('The encryption uses your WordPress AUTH_KEY, making it unique to your installation.', 'media-toolkit'); ?></span>
                                    </li>
                                    <li>
                                        <span class="dashicons dashicons-yes"></span>
                                        <strong><?php esc_html_e('Never Displayed', 'media-toolkit'); ?></strong>
                                        <span><?php esc_html_e('The original token is never displayed after being saved. Only a masked placeholder is shown.', 'media-toolkit'); ?></span>
                                    </li>
                                    <li>
                                        <span class="dashicons dashicons-info"></span>
                                        <strong><?php esc_html_e('Alternative Method', 'media-toolkit'); ?></strong>
                                        <span>
                                            <?php esc_html_e('For maximum security, you can define the token in wp-config.php:', 'media-toolkit'); ?>
                                            <code>define('MEDIA_TOOLKIT_GITHUB_TOKEN', 'your-token');</code>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="mds-actions">
                    <button type="button" class="mds-btn mds-btn-primary" id="btn-save-update-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'media-toolkit'); ?>
                    </button>
                </div>
            </div>

        <?php endif; ?>
        </div>

        <footer class="mds-footer">
        <p>
            <?php
            printf(
                /* translators: %s: Metodo.dev link */
                esc_html__('Developed by %s', 'media-toolkit'),
                '<a href="https://metodo.dev" target="_blank" rel="noopener">Michele Marri - Metodo.dev</a>'
            );
            ?>
            &bull;
            <?php
            printf(
                /* translators: %s: version number */
                esc_html__('Version %s', 'media-toolkit'),
                MEDIA_TOOLKIT_VERSION
            );
            ?>
        </p>
        </footer>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="test-connection-modal" class="mds-modal-overlay" style="display:none;">
    <div class="mds-modal">
        <div class="mds-modal-header">
            <h3 class="mds-modal-title"><?php esc_html_e('Testing Connection...', 'media-toolkit'); ?></h3>
            <button type="button" class="mds-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="mds-modal-body">
            <div id="test-results"></div>
        </div>
    </div>
</div>
