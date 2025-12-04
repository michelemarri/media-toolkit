<?php
/**
 * Settings class for managing plugin configuration
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Core;

use Metodo\MediaToolkit\CDN\CDNProvider;
use Metodo\MediaToolkit\Storage\StorageConfig;
use Metodo\MediaToolkit\Storage\StorageProvider;

/**
 * Manages plugin settings with encryption
 */
final class Settings
{
    private const OPTION_KEY = 'media_toolkit_settings';
    private const ACTIVE_ENV_KEY = 'media_toolkit_active_env';
    
    private Encryption $encryption;
    private array $settings;

    public function __construct(Encryption $encryption)
    {
        $this->encryption = $encryption;
        $this->settings = $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Save settings to database
     */
    private function save_settings(): bool
    {
        return update_option(self::OPTION_KEY, $this->settings);
    }

    /**
     * Get active environment
     */
    public function get_active_environment(): Environment
    {
        $env = get_option(self::ACTIVE_ENV_KEY, Environment::PRODUCTION->value);
        return Environment::tryFrom($env) ?? Environment::PRODUCTION;
    }

    /**
     * Set active environment
     */
    public function set_active_environment(Environment $environment): bool
    {
        $current = get_option(self::ACTIVE_ENV_KEY);
        if ($current === $environment->value) {
            return true;
        }
        return update_option(self::ACTIVE_ENV_KEY, $environment->value);
    }

    /**
     * Get current storage provider
     */
    public function get_storage_provider(): StorageProvider
    {
        $provider = $this->settings['provider'] ?? 'aws_s3';
        return StorageProvider::tryFrom($provider) ?? StorageProvider::AWS_S3;
    }

    /**
     * Get storage configuration (new multi-provider format)
     */
    public function get_storage_config(): ?StorageConfig
    {
        if (empty($this->settings)) {
            return null;
        }

        $provider = $this->get_storage_provider();

        return new StorageConfig(
            provider: $provider,
            accessKey: $this->encryption->decrypt($this->settings['access_key'] ?? ''),
            secretKey: $this->encryption->decrypt($this->settings['secret_key'] ?? ''),
            bucket: $this->settings['bucket'] ?? '',
            region: $this->settings['region'] ?? '',
            accountId: $this->settings['account_id'] ?? '',
            cdnUrl: $this->settings['cdn_url'] ?? '',
            cdnProvider: CDNProvider::tryFrom($this->settings['cdn_provider'] ?? '') ?? CDNProvider::NONE,
            cloudflareZoneId: $this->settings['cloudflare_zone_id'] ?? '',
            cloudflareApiToken: $this->encryption->decrypt($this->settings['cloudflare_api_token'] ?? ''),
            cloudfrontDistributionId: $this->settings['cloudfront_distribution_id'] ?? '',
        );
    }

    /**
     * Save storage configuration (new multi-provider format)
     */
    public function save_storage_config(
        StorageProvider $provider,
        string $accessKey,
        string $secretKey,
        string $bucket,
        string $region = '',
        string $accountId = '',
        string $cdnUrl = '',
        string $cdnProvider = 'none',
        string $cloudflareZoneId = '',
        string $cloudflareApiToken = '',
        string $cloudfrontDistributionId = ''
    ): bool {
        $this->settings = [
            'provider' => $provider->value,
            'access_key' => $this->encryption->encrypt($accessKey),
            'secret_key' => $this->encryption->encrypt($secretKey),
            'bucket' => sanitize_text_field($bucket),
            'region' => sanitize_text_field($region),
            'account_id' => sanitize_text_field($accountId),
            'cdn_url' => esc_url_raw($cdnUrl),
            'cdn_provider' => sanitize_text_field($cdnProvider),
            'cloudflare_zone_id' => sanitize_text_field($cloudflareZoneId),
            'cloudflare_api_token' => $this->encryption->encrypt($cloudflareApiToken),
            'cloudfront_distribution_id' => sanitize_text_field($cloudfrontDistributionId),
        ];

        return $this->save_settings();
    }

    /**
     * Check if plugin is configured
     */
    public function is_configured(): bool
    {
        $config = $this->get_storage_config();
        return $config !== null && $config->isValid();
    }

    /**
     * Get masked credentials for display
     */
    public function get_masked_credentials(): array
    {
        $config = $this->get_storage_config();
        
        if ($config === null) {
            return [
                'provider' => 'aws_s3',
                'access_key' => '',
                'secret_key' => '',
                'region' => '',
                'bucket' => '',
                'account_id' => '',
                'cdn_url' => '',
                'cdn_provider' => 'none',
                'cloudflare_zone_id' => '',
                'cloudflare_api_token' => '',
                'cloudfront_distribution_id' => '',
            ];
        }

        return [
            'provider' => $config->provider->value,
            'access_key' => $this->encryption->mask($config->accessKey),
            'secret_key' => $this->encryption->mask($config->secretKey),
            'region' => $config->region,
            'bucket' => $config->bucket,
            'account_id' => $config->accountId,
            'cdn_url' => $config->cdnUrl,
            'cdn_provider' => $config->cdnProvider->value,
            'cloudflare_zone_id' => $config->cloudflareZoneId,
            'cloudflare_api_token' => $this->encryption->mask($config->cloudflareApiToken),
            'cloudfront_distribution_id' => $config->cloudfrontDistributionId,
        ];
    }

    /**
     * Get storage base path including environment folder
     * Structure: media/{environment}/wp-content/uploads
     */
    public function get_storage_base_path(): string
    {
        $env = $this->get_active_environment();
        return 'media/' . $env->value . '/wp-content/uploads';
    }

    /**
     * Get public URL for file (CDN or direct storage)
     */
    public function get_file_url(string $key): string
    {
        $config = $this->get_storage_config();
        
        if ($config === null) {
            return '';
        }

        return $config->getPublicUrl($key);
    }

    /**
     * Should remove local files after migration
     */
    public function should_remove_local_files(): bool
    {
        return (bool) get_option('media_toolkit_remove_local', false);
    }

    /**
     * Set remove local files option
     */
    public function set_remove_local_files(bool $remove): bool
    {
        return update_option('media_toolkit_remove_local', $remove);
    }

    /**
     * Should remove data on uninstall
     */
    public function should_remove_on_uninstall(): bool
    {
        return (bool) get_option('media_toolkit_remove_on_uninstall', false);
    }

    /**
     * Set remove on uninstall option
     */
    public function set_remove_on_uninstall(bool $remove): bool
    {
        return update_option('media_toolkit_remove_on_uninstall', $remove);
    }

    /**
     * Get Cache-Control max-age value in seconds
     * Default: 1 year (31536000 seconds)
     */
    public function get_cache_control_max_age(): int
    {
        return (int) get_option('media_toolkit_cache_control', 31536000);
    }

    /**
     * Set Cache-Control max-age value
     */
    public function set_cache_control_max_age(int $seconds): bool
    {
        return update_option('media_toolkit_cache_control', max(0, $seconds));
    }

    /**
     * Get Cache-Control header string
     */
    public function get_cache_control_header(): string
    {
        $max_age = $this->get_cache_control_max_age();
        
        if ($max_age <= 0) {
            return 'no-cache, no-store, must-revalidate';
        }
        
        return sprintf('public, max-age=%d, immutable', $max_age);
    }

    /**
     * File type categories for Content-Disposition settings
     */
    public const FILE_TYPE_CATEGORIES = [
        'image' => [
            'label' => 'Images',
            'description' => 'JPG, PNG, GIF, WebP, SVG, etc.',
            'mime_prefixes' => ['image/'],
            'default' => 'inline',
        ],
        'pdf' => [
            'label' => 'PDF Documents',
            'description' => 'PDF files',
            'mime_types' => ['application/pdf'],
            'default' => 'inline',
        ],
        'video' => [
            'label' => 'Videos',
            'description' => 'MP4, WebM, MOV, etc.',
            'mime_prefixes' => ['video/'],
            'default' => 'inline',
        ],
        'archive' => [
            'label' => 'Archives',
            'description' => 'ZIP, RAR, TAR, GZ, etc.',
            'mime_types' => [
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/x-tar',
                'application/gzip',
                'application/x-7z-compressed',
            ],
            'default' => 'attachment',
        ],
    ];

    /**
     * Get Content-Disposition settings for all file types
     */
    public function get_content_disposition_settings(): array
    {
        $settings = get_option('media_toolkit_content_disposition', []);
        
        // Merge with defaults
        $defaults = [];
        foreach (self::FILE_TYPE_CATEGORIES as $type => $config) {
            $defaults[$type] = $config['default'];
        }
        
        return array_merge($defaults, is_array($settings) ? $settings : []);
    }

    /**
     * Get Content-Disposition for a specific file type category
     */
    public function get_content_disposition(string $file_type): string
    {
        $settings = $this->get_content_disposition_settings();
        return $settings[$file_type] ?? 'inline';
    }

    /**
     * Set Content-Disposition settings for all file types
     */
    public function set_content_disposition_settings(array $settings): bool
    {
        $sanitized = [];
        foreach (self::FILE_TYPE_CATEGORIES as $type => $config) {
            if (isset($settings[$type])) {
                $sanitized[$type] = in_array($settings[$type], ['inline', 'attachment']) 
                    ? $settings[$type] 
                    : $config['default'];
            }
        }
        
        return update_option('media_toolkit_content_disposition', $sanitized);
    }

    /**
     * Get Content-Disposition header for a given mime type
     * Returns the header value based on file type category settings
     */
    public function get_content_disposition_for_mime(string $mime_type, string $filename): string
    {
        $disposition = 'inline'; // Default
        
        // Determine file type category
        foreach (self::FILE_TYPE_CATEGORIES as $type => $config) {
            // Check exact mime types
            if (isset($config['mime_types']) && in_array($mime_type, $config['mime_types'])) {
                $disposition = $this->get_content_disposition($type);
                break;
            }
            
            // Check mime type prefixes
            if (isset($config['mime_prefixes'])) {
                foreach ($config['mime_prefixes'] as $prefix) {
                    if (str_starts_with($mime_type, $prefix)) {
                        $disposition = $this->get_content_disposition($type);
                        break 2;
                    }
                }
            }
        }
        
        // Build the header value
        $safe_filename = rawurlencode($filename);
        
        if ($disposition === 'attachment') {
            return sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $filename, $safe_filename);
        }
        
        return 'inline';
    }

    /**
     * Get storage stats sync interval in hours (0 = disabled)
     */
    public function get_storage_sync_interval(): int
    {
        return (int) get_option('media_toolkit_sync_interval', 24);
    }

    /**
     * Set storage stats sync interval
     */
    public function set_storage_sync_interval(int $hours): bool
    {
        return update_option('media_toolkit_sync_interval', max(0, $hours));
    }

    /**
     * Get cached storage bucket stats
     */
    public function get_cached_storage_stats(): ?array
    {
        $stats = get_option('media_toolkit_storage_stats');
        return is_array($stats) ? $stats : null;
    }

    /**
     * Save storage bucket stats
     */
    public function save_storage_stats(array $stats): bool
    {
        return update_option('media_toolkit_storage_stats', $stats);
    }

    /**
     * Delete all settings (used on uninstall)
     */
    public function delete_all_settings(): void
    {
        delete_option(self::OPTION_KEY);
        delete_option(self::ACTIVE_ENV_KEY);
        delete_option('media_toolkit_remove_local');
        delete_option('media_toolkit_remove_on_uninstall');
        delete_option('media_toolkit_cache_control');
        delete_option('media_toolkit_content_disposition');
        delete_option('media_toolkit_sync_interval');
        delete_option('media_toolkit_storage_stats');
    }

    /**
     * Check if there are files migrated to any provider
     */
    public function has_migrated_files(): bool
    {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_media_toolkit_migrated' AND meta_value = '1'"
        );
        
        return (int) $count > 0;
    }

    /**
     * Get count of files migrated per provider
     */
    public function get_migrated_files_by_provider(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT pm2.meta_value as provider, COUNT(*) as count
             FROM {$wpdb->postmeta} pm1
             LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_media_toolkit_provider'
             WHERE pm1.meta_key = '_media_toolkit_migrated' AND pm1.meta_value = '1'
             GROUP BY pm2.meta_value",
            ARRAY_A
        );
        
        $counts = [];
        foreach ($results as $row) {
            // Files without provider meta are assumed to be AWS S3 (legacy)
            $provider = $row['provider'] ?: 'aws_s3';
            $counts[$provider] = (int) $row['count'];
        }
        
        return $counts;
    }
}
