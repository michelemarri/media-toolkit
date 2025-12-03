<?php
/**
 * Settings Importer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Tools;

/**
 * Imports plugin settings from JSON with support for auto-discovered options
 */
final class Importer
{
    /**
     * Option prefix for validation
     */
    private const OPTION_PREFIX = 'media_toolkit_';

    /**
     * Options that should never be imported (security sensitive or runtime data)
     */
    private const BLOCKED_OPTIONS = [
        'media_toolkit_settings', // Contains encrypted credentials
        'media_toolkit_s3_stats', // Runtime cache data
    ];

    /**
     * Import settings from data array
     *
     * @param array $data         The import data
     * @param bool  $mergeExisting Merge with existing settings
     * @return bool Success
     */
    public function import(array $data, bool $mergeExisting = false): bool
    {
        // Check for valid format
        if (!isset($data['export_format']) || !isset($data['options'])) {
            return false;
        }

        /**
         * Filter import data before processing
         *
         * @param array $data The import data
         */
        $data = apply_filters('media_toolkit_import_data', $data);

        if (!is_array($data['options'])) {
            return false;
        }

        foreach ($data['options'] as $optionName => $value) {
            // Validate option name starts with our prefix
            if (!str_starts_with($optionName, self::OPTION_PREFIX)) {
                continue;
            }

            // Skip blocked options
            if (in_array($optionName, self::BLOCKED_OPTIONS, true)) {
                continue;
            }

            // Sanitize the value
            $value = $this->sanitizeOptionValue($optionName, $value);

            if ($mergeExisting) {
                $existing = get_option($optionName, []);
                if (is_array($existing) && is_array($value)) {
                    $value = $this->deepMerge($existing, $value);
                }
            }

            update_option($optionName, $value);
        }

        /**
         * Action after import is complete
         *
         * @param array $data The imported data
         */
        do_action('media_toolkit_after_import', $data);

        return true;
    }

    /**
     * Deep merge two arrays recursively
     *
     * @param array $existing Existing array
     * @param array $new New array
     * @return array Merged array
     */
    private function deepMerge(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                $existing[$key] = $this->deepMerge($existing[$key], $value);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Import from file
     *
     * @param string $filePath The file path
     * @param bool   $merge    Merge with existing
     * @return bool Success
     */
    public function importFromFile(string $filePath, bool $merge = false): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $this->import($data, $merge);
    }

    /**
     * Sanitize option value based on option name
     *
     * @param string $optionName Option name
     * @param mixed $value Option value
     * @return mixed Sanitized value
     */
    private function sanitizeOptionValue(string $optionName, mixed $value): mixed
    {
        // For array values, sanitize recursively
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        // For scalar values, sanitize based on type
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * Recursively sanitize array values
     *
     * @param array $array Array to sanitize
     * @return array Sanitized array
     */
    private function sanitizeArray(array $array): array
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            $sanitizedKey = is_string($key) ? sanitize_key($key) : $key;

            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = sanitize_text_field($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[$sanitizedKey] = $value;
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate import file
     *
     * @param array $data Data to validate
     * @return array Validation result with 'valid' (bool) and 'errors' (array)
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check for required fields
        if (!isset($data['export_format'])) {
            $errors[] = __('Invalid export file: missing format version.', 'media-toolkit');
        }

        if (!isset($data['options']) || !is_array($data['options'])) {
            $errors[] = __('Invalid export file: missing options data.', 'media-toolkit');
        }

        // Count importable options
        $importableCount = 0;
        if (isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $optionName => $value) {
                if (str_starts_with($optionName, self::OPTION_PREFIX) && !in_array($optionName, self::BLOCKED_OPTIONS, true)) {
                    $importableCount++;
                }
            }
        }

        if ($importableCount === 0) {
            $errors[] = __('No importable settings found in the file.', 'media-toolkit');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'format' => $data['export_format'] ?? 'unknown',
            'plugin_version' => $data['plugin_version'] ?? 'unknown',
            'exported_at' => $data['exported_at'] ?? 'unknown',
            'options_count' => $importableCount,
        ];
    }
}

