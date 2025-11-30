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

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-settings">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
            <div>
                <h1>Settings</h1>
                <p class="s3-subtitle">Configure AWS S3 and CDN settings</p>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="s3-tabs">
        <a href="?page=media-toolkit-settings&tab=environment" class="s3-tab <?php echo $active_tab === 'environment' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4"></circle>
                <line x1="1.05" y1="12" x2="7" y2="12"></line>
                <line x1="17.01" y1="12" x2="22.96" y2="12"></line>
            </svg>
            Environment
        </a>
        <a href="?page=media-toolkit-settings&tab=credentials" class="s3-tab <?php echo $active_tab === 'credentials' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Credentials
        </a>
        <a href="?page=media-toolkit-settings&tab=cdn" class="s3-tab <?php echo $active_tab === 'cdn' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            CDN
        </a>
        <a href="?page=media-toolkit-settings&tab=file-options" class="s3-tab <?php echo $active_tab === 'file-options' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            File Options
        </a>
        <a href="?page=media-toolkit-settings&tab=general" class="s3-tab <?php echo $active_tab === 'general' ? 's3-tab-active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
            General
        </a>
    </div>

    <div class="s3-tab-content">
        <?php if ($active_tab === 'environment'): ?>
            <!-- ==================== ENVIRONMENT TAB ==================== -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="4"></circle>
                            <line x1="1.05" y1="12" x2="7" y2="12"></line>
                            <line x1="17.01" y1="12" x2="22.96" y2="12"></line>
                        </svg>
                        Active Environment
                    </h3>
                </div>
                <div class="s3-card-body">
                    <p class="s3-muted-text">
                        Select the active environment. Files will be stored in a separate folder for each environment.
                    </p>
                    <p class="s3-muted-text">
                        Current path: <code class="s3-code">bucket/media/<strong id="env-preview"><?php echo esc_html($active_environment); ?></strong>/wp-content/uploads/...</code>
                    </p>
                    
                    <form id="s3-environment-form">
                        <div class="s3-form-group" style="margin-top: 20px;">
                            <label for="active-environment" class="s3-label">Environment</label>
                            <div class="s3-select-wrapper" style="max-width: 300px;">
                                <select name="active_environment" id="active-environment" class="s3-select" style="width: 100%;">
                                    <?php foreach (Environment::cases() as $env): ?>
                                        <option value="<?php echo esc_attr($env->value); ?>" <?php selected($active_environment, $env->value); ?>>
                                            <?php echo esc_html(ucfirst($env->value)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="s3-help">Different environments allow you to separate development, staging, and production files.</span>
                        </div>
                        
                        <div class="s3-form-actions">
                            <button type="button" class="s3-btn s3-btn-primary" id="btn-save-environment">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Environment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'credentials'): ?>
            <!-- ==================== CREDENTIALS TAB ==================== -->
            <div class="s3-card-panel" id="s3-credentials-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        AWS Credentials
                        <?php if ($is_configured): ?>
                            <span class="s3-badge s3-badge-success">Configured</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="s3-card-body">
                    <p class="s3-muted-text">Configure your AWS credentials for S3 access. A single bucket is shared across all environments.</p>
                    
                    <form id="s3-credentials-form">
                        <div class="s3-form-grid">
                            <div class="s3-form-group">
                                <label for="access_key" class="s3-label">AWS Access Key</label>
                                <input type="text" name="access_key" id="access_key" 
                                       value="<?php echo esc_attr($credentials['access_key'] ?? ''); ?>"
                                       class="s3-input s3-input-full" autocomplete="off"
                                       placeholder="AKIAIOSFODNN7EXAMPLE">
                            </div>
                            
                            <div class="s3-form-group">
                                <label for="secret_key" class="s3-label">AWS Secret Key</label>
                                <input type="password" name="secret_key" id="secret_key" 
                                       value="<?php echo esc_attr($credentials['secret_key'] ?? ''); ?>"
                                       class="s3-input s3-input-full" autocomplete="off"
                                       placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY">
                            </div>
                            
                            <div class="s3-form-group">
                                <label for="region" class="s3-label">AWS Region</label>
                                <div class="s3-select-wrapper s3-select-full">
                                    <select name="region" id="region" class="s3-select">
                                        <option value="">Select Region</option>
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
                            </div>
                            
                            <div class="s3-form-group">
                                <label for="bucket" class="s3-label">S3 Bucket Name</label>
                                <input type="text" name="bucket" id="bucket" 
                                       value="<?php echo esc_attr($credentials['bucket'] ?? ''); ?>"
                                       class="s3-input s3-input-full"
                                       placeholder="my-bucket-name">
                            </div>
                        </div>
                        
                        <div class="s3-form-actions">
                            <button type="button" class="s3-btn s3-btn-primary" id="btn-save-credentials">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Credentials
                            </button>
                            <button type="button" class="s3-btn s3-btn-secondary" id="btn-test-credentials" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                Test Connection
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'cdn'): ?>
            <!-- ==================== CDN TAB ==================== -->
            <div class="s3-card-panel" id="cdn-settings-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        CDN Settings
                    </h3>
                </div>
                <div class="s3-card-body">
                    <p class="s3-muted-text">Configure your CDN for serving files (Cloudflare, CloudFront, or custom)</p>
                    
                    <form id="s3-cdn-form">
                        <div class="s3-form-grid">
                            <div class="s3-form-group">
                                <label for="cdn_provider" class="s3-label">CDN Provider</label>
                                <div class="s3-select-wrapper s3-select-full">
                                    <select name="cdn_provider" id="cdn_provider" class="s3-select">
                                        <option value="none" <?php selected($credentials['cdn_provider'] ?? 'none', 'none'); ?>>None (direct S3 URLs)</option>
                                        <option value="cloudflare" <?php selected($credentials['cdn_provider'] ?? '', 'cloudflare'); ?>>Cloudflare</option>
                                        <option value="cloudfront" <?php selected($credentials['cdn_provider'] ?? '', 'cloudfront'); ?>>CloudFront</option>
                                        <option value="other" <?php selected($credentials['cdn_provider'] ?? '', 'other'); ?>>Other CDN</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="s3-form-group">
                                <label for="cdn_url" class="s3-label">CDN URL</label>
                                <input type="url" name="cdn_url" id="cdn_url" 
                                       value="<?php echo esc_attr($credentials['cdn_url'] ?? ''); ?>"
                                       class="s3-input s3-input-full"
                                       placeholder="https://media.example.com">
                                <span class="s3-help">The public URL to access your files through the CDN</span>
                            </div>
                        </div>

                        <!-- Cloudflare Settings -->
                        <div id="cloudflare-settings" class="s3-subsection" style="display: none;">
                            <h4 class="s3-subsection-title">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19.73 14.87a4 4 0 0 0-3.86-3 4 4 0 0 0-2.47.77L8 10a4 4 0 0 0-4 4v2h3"></path>
                                    <path d="M10.07 19a8 8 0 0 0 3.86.73"></path>
                                    <path d="M15 21a9 9 0 1 0-4-17.56"></path>
                                </svg>
                                Cloudflare Cache Purge
                            </h4>
                            <p class="s3-muted-text">Optional. Required only for automatic cache purging when files are updated/deleted.</p>
                            <div class="s3-form-grid">
                                <div class="s3-form-group">
                                    <label for="cloudflare_zone_id" class="s3-label">Zone ID</label>
                                    <input type="text" name="cloudflare_zone_id" id="cloudflare_zone_id" 
                                           value="<?php echo esc_attr($credentials['cloudflare_zone_id'] ?? ''); ?>"
                                           class="s3-input s3-input-full"
                                           placeholder="abc123def456...">
                                    <span class="s3-help">Found in Cloudflare Dashboard → Your site → Overview</span>
                                </div>
                                
                                <div class="s3-form-group">
                                    <label for="cloudflare_api_token" class="s3-label">API Token</label>
                                    <input type="password" name="cloudflare_api_token" id="cloudflare_api_token" 
                                           value="<?php echo esc_attr($credentials['cloudflare_api_token'] ?? ''); ?>"
                                           class="s3-input s3-input-full" autocomplete="off">
                                    <span class="s3-help">Create a token with "Zone.Cache Purge" permission</span>
                                </div>
                            </div>
                        </div>

                        <!-- CloudFront Settings -->
                        <div id="cloudfront-settings" class="s3-subsection" style="display: none;">
                            <h4 class="s3-subsection-title">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                CloudFront Cache Invalidation
                            </h4>
                            <div class="s3-form-grid">
                                <div class="s3-form-group">
                                    <label for="cloudfront_distribution_id" class="s3-label">Distribution ID</label>
                                    <input type="text" name="cloudfront_distribution_id" id="cloudfront_distribution_id" 
                                           value="<?php echo esc_attr($credentials['cloudfront_distribution_id'] ?? ''); ?>"
                                           class="s3-input s3-input-full"
                                           placeholder="E1A2B3C4D5F6G7">
                                    <span class="s3-help">Required for cache invalidation</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="s3-form-actions">
                            <button type="button" class="s3-btn s3-btn-primary" id="btn-save-cdn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save CDN Settings
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
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Cache-Control
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <p class="s3-muted-text">Configure browser caching behavior for uploaded files.</p>
                        
                        <div class="s3-form-group" style="max-width: 500px;">
                            <label for="cache_control" class="s3-label">Cache-Control for New Uploads</label>
                            <div class="s3-select-wrapper s3-select-full">
                                <select name="cache_control" id="cache_control" class="s3-select">
                                    <option value="0" <?php selected($cache_control, 0); ?>>No cache (no-cache, no-store)</option>
                                    <option value="86400" <?php selected($cache_control, 86400); ?>>1 day (86,400 seconds)</option>
                                    <option value="604800" <?php selected($cache_control, 604800); ?>>1 week (604,800 seconds)</option>
                                    <option value="2592000" <?php selected($cache_control, 2592000); ?>>1 month (2,592,000 seconds)</option>
                                    <option value="31536000" <?php selected($cache_control, 31536000); ?>>1 year (31,536,000 seconds) — Recommended</option>
                                </select>
                            </div>
                            <span class="s3-help">Sets the Cache-Control header on new uploaded files. To update existing files, go to <a href="<?php echo admin_url('admin.php?page=media-toolkit-tools&tab=cache-sync'); ?>">Tools → Cache Headers</a>.</span>
                        </div>
                    </div>
                </div>

                <!-- Content-Disposition Section -->
                <div class="s3-card-panel">
                    <div class="s3-card-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Content-Disposition
                        </h3>
                    </div>
                    <div class="s3-card-body">
                        <p class="s3-muted-text">
                            Configure how browsers handle files when users click on direct links. This setting controls whether files 
                            are displayed in the browser or downloaded automatically.
                        </p>
                        
                        <!-- Explanation Box -->
                        <div class="s3-info-box" style="margin: 20px 0;">
                            <div class="s3-info-box-grid">
                                <div class="s3-info-box-item">
                                    <div class="s3-info-box-header">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--s3-info);">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                        <strong>Inline</strong>
                                    </div>
                                    <p>File opens directly in the browser (if supported)</p>
                                    <div class="s3-pros-cons">
                                        <span class="s3-pro">✓ Better user experience for previewing</span>
                                        <span class="s3-pro">✓ Images/PDFs/videos display in browser</span>
                                        <span class="s3-con">✗ User must right-click to download</span>
                                    </div>
                                </div>
                                <div class="s3-info-box-item">
                                    <div class="s3-info-box-header">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--s3-success);">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        <strong>Attachment</strong>
                                    </div>
                                    <p>File downloads automatically with original filename</p>
                                    <div class="s3-pros-cons">
                                        <span class="s3-pro">✓ One-click download for users</span>
                                        <span class="s3-pro">✓ Preserves original filename</span>
                                        <span class="s3-con">✗ Cannot preview before downloading</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h4 class="s3-subsection-title" style="margin-top: 24px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                <polyline points="13 2 13 9 20 9"></polyline>
                            </svg>
                            Settings by File Type
                        </h4>
                        
                        <div class="s3-disposition-grid">
                            <?php foreach ($file_type_categories as $type => $config): ?>
                                <div class="s3-disposition-item">
                                    <div class="s3-disposition-label">
                                        <strong><?php echo esc_html($config['label']); ?></strong>
                                        <span><?php echo esc_html($config['description']); ?></span>
                                    </div>
                                    <div class="s3-select-wrapper">
                                        <select name="content_disposition_<?php echo esc_attr($type); ?>" 
                                                id="content_disposition_<?php echo esc_attr($type); ?>" 
                                                class="s3-select">
                                            <option value="inline" <?php selected($content_disposition[$type] ?? $config['default'], 'inline'); ?>>
                                                Inline — Display in browser
                                            </option>
                                            <option value="attachment" <?php selected($content_disposition[$type] ?? $config['default'], 'attachment'); ?>>
                                                Attachment — Force download
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <p class="s3-help" style="margin-top: 16px;">
                            <strong>Tip:</strong> Use "Attachment" for files that users typically want to download (like ZIP archives), 
                            and "Inline" for files they usually want to preview (like images and PDFs).
                        </p>
                    </div>
                </div>
                
                <div class="s3-form-actions" style="margin-top: 0;">
                    <button type="button" class="s3-btn s3-btn-primary" id="btn-save-file-options">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Save File Options
                    </button>
                </div>
            </form>

        <?php elseif ($active_tab === 'general'): ?>
            <!-- ==================== GENERAL TAB ==================== -->
            <div class="s3-card-panel">
                <div class="s3-card-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="21" x2="4" y2="14"></line>
                            <line x1="4" y1="10" x2="4" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12" y2="3"></line>
                            <line x1="20" y1="21" x2="20" y2="16"></line>
                            <line x1="20" y1="12" x2="20" y2="3"></line>
                            <line x1="1" y1="14" x2="7" y2="14"></line>
                            <line x1="9" y1="8" x2="15" y2="8"></line>
                            <line x1="17" y1="16" x2="23" y2="16"></line>
                        </svg>
                        General Options
                    </h3>
                </div>
                <div class="s3-card-body">
                    <form id="s3-general-form">
                        <div class="s3-checkbox-group">
                            <label class="s3-checkbox-label s3-checkbox-warning">
                                <input type="checkbox" name="remove_local" id="remove_local" value="true"
                                       <?php checked($settings ? $settings->should_remove_local_files() : false); ?>>
                                <span class="s3-checkbox-box"></span>
                                <span class="s3-checkbox-text">
                                    <strong>Delete local files after uploading to S3</strong>
                                    <span>⚠️ This saves disk space but means files only exist on S3</span>
                                </span>
                            </label>
                            
                            <label class="s3-checkbox-label">
                                <input type="checkbox" name="remove_on_uninstall" id="remove_on_uninstall" value="true"
                                       <?php checked($settings ? $settings->should_remove_on_uninstall() : false); ?>>
                                <span class="s3-checkbox-box"></span>
                                <span class="s3-checkbox-text">
                                    <strong>Delete all plugin data when uninstalling</strong>
                                    <span>Files on S3 will NOT be deleted</span>
                                </span>
                            </label>
                        </div>
                        
                        <div class="s3-form-actions">
                            <button type="button" class="s3-btn s3-btn-primary" id="btn-save-general">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Options
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="test-connection-modal" class="s3-modal" style="display:none;">
    <div class="s3-modal-content">
        <button type="button" class="s3-modal-close">&times;</button>
        <h2>Testing Connection...</h2>
        <div id="test-results"></div>
    </div>
</div>
