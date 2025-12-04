<?php
/**
 * Settings Exporter
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Tools;

use Metodo\MediaToolkit\Core\Encryption;

/**
 * Exports plugin settings to JSON with auto-discovery
 */
final class Exporter
{
    /**
     * Option prefix for auto-discovery
     */
    private const OPTION_PREFIX = 'media_toolkit_';

    /**
     * Options to exclude from export (sensitive data, temporary data)
     */
    private const EXCLUDED_OPTIONS = [
        'media_toolkit_settings', // Contains encrypted credentials
        'media_toolkit_storage_stats', // Runtime cache data
    ];

    /**
     * Keys within options that contain sensitive data to exclude
     */
    private const SENSITIVE_KEYS = [
        'github_token',
        'github_token_encrypted',
        'access_key',
        'secret_key',
        'api_token',
        'cloudflare_api_token',
        'password',
        'token',
    ];

    private Encryption $encryption;

    public function __construct(Encryption $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Export plugin settings
     *
     * @param bool $includeHistory Include history data
     * @return array Export data
     */
    public function export(bool $includeHistory = false): array
    {
        $data = [
            'export_format' => '2.0',
            'plugin_version' => MEDIA_TOOLKIT_VERSION,
            'exported_at' => current_time('c'),
            'site_url' => home_url('/'),
            'options' => $this->discoverAndExportOptions(),
        ];

        // Optionally include history
        if (!$includeHistory) {
            unset($data['options']['media_toolkit_history']);
        }

        /**
         * Filter export data
         *
         * @param array $data The export data
         */
        return apply_filters('media_toolkit_export_data', $data);
    }

    /**
     * Auto-discover and export all plugin options
     *
     * @return array All options with media_toolkit_ prefix
     */
    private function discoverAndExportOptions(): array
    {
        global $wpdb;

        // Find all options with our prefix
        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%',
                '_transient%'
            ),
            ARRAY_A
        );

        $exported = [];

        foreach ($options as $option) {
            $optionName = $option['option_name'];

            // Skip excluded options
            if (in_array($optionName, self::EXCLUDED_OPTIONS, true)) {
                continue;
            }

            $value = maybe_unserialize($option['option_value']);

            // Sanitize sensitive data
            $value = $this->sanitizeSensitiveData($value);

            $exported[$optionName] = $value;
        }

        // Sort by key for consistent output
        ksort($exported);

        return $exported;
    }

    /**
     * Recursively sanitize sensitive data from arrays
     *
     * @param mixed $data The data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitizeSensitiveData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            // Check if key contains sensitive data indicators
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (stripos((string) $key, $sensitiveKey) !== false) {
                    // Remove sensitive data entirely
                    unset($data[$key]);
                    continue 2;
                }
            }

            // Recursively process nested arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitizeSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Get list of all exportable options (for UI display)
     *
     * @return array List of option names that would be exported
     */
    public function getExportableOptions(): array
    {
        global $wpdb;

        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%',
                '_transient%'
            )
        );

        return array_filter($options, function ($name) {
            return !in_array($name, self::EXCLUDED_OPTIONS, true);
        });
    }

    /**
     * Get excluded options info (for UI display)
     *
     * @return array Info about what's excluded and why
     */
    public function getExcludedInfo(): array
    {
        return [
            'credentials' => [
                'label' => __('AWS Credentials', 'media-toolkit'),
                'reason' => __('Security: credentials must be configured manually on each site', 'media-toolkit'),
            ],
            'github_token' => [
                'label' => __('GitHub Token', 'media-toolkit'),
                'reason' => __('Security: tokens must be configured manually on each site', 'media-toolkit'),
            ],
            'cdn_api_tokens' => [
                'label' => __('CDN API Tokens', 'media-toolkit'),
                'reason' => __('Security: API tokens must be configured manually on each site', 'media-toolkit'),
            ],
        ];
    }

    /**
     * Export to file
     *
     * @param string $filename The filename
     * @return string File path
     */
    public function exportToFile(string $filename = ''): string
    {
        if (empty($filename)) {
            $filename = 'media-toolkit-settings-' . date('Y-m-d-His') . '.json';
        }

        $data = $this->export();
        $uploadDir = wp_upload_dir();
        $filePath = $uploadDir['basedir'] . '/media-toolkit-exports/' . $filename;

        // Create directory if needed
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($filePath, wp_json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }
}

