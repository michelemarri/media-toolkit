<?php
/**
 * Optimization Table Manager
 * 
 * Custom database table for storing image optimization data.
 * More performant than wp_postmeta for sites with thousands of images.
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Database;

/**
 * Manages the custom optimization table
 */
final class OptimizationTable
{
    private const TABLE_NAME = 'media_toolkit_optimization';
    private const DB_VERSION = '1.0';
    private const DB_VERSION_OPTION = 'media_toolkit_optimization_db_version';

    /**
     * Get full table name with prefix
     */
    public static function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the optimization table
     */
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            attachment_id BIGINT UNSIGNED NOT NULL,
            original_size BIGINT UNSIGNED DEFAULT 0,
            optimized_size BIGINT UNSIGNED DEFAULT 0,
            bytes_saved BIGINT UNSIGNED DEFAULT 0,
            percent_saved DECIMAL(5,2) DEFAULT 0,
            status ENUM('pending','optimized','skipped','failed') DEFAULT 'pending',
            error_message VARCHAR(255) DEFAULT NULL,
            jpeg_quality TINYINT UNSIGNED DEFAULT NULL,
            png_compression TINYINT UNSIGNED DEFAULT NULL,
            strip_metadata TINYINT(1) DEFAULT NULL,
            settings_json JSON DEFAULT NULL,
            optimized_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY status (status),
            KEY optimized_at (optimized_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Drop the optimization table
     */
    public static function drop_table(): void
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Check if table exists
     */
    public static function table_exists(): bool
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        return $result === $table_name;
    }

    /**
     * Check if table needs upgrade
     */
    public static function needs_upgrade(): bool
    {
        $current_version = get_option(self::DB_VERSION_OPTION, '0');
        return version_compare($current_version, self::DB_VERSION, '<');
    }

    /**
     * Insert or update optimization record
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert (prevents race conditions)
     */
    public static function upsert(array $data): bool
    {
        global $wpdb;

        $table_name = self::get_table_name();

        $defaults = [
            'original_size' => 0,
            'optimized_size' => 0,
            'bytes_saved' => 0,
            'percent_saved' => 0,
            'status' => 'pending',
            'error_message' => null,
            'jpeg_quality' => null,
            'png_compression' => null,
            'strip_metadata' => null,
            'settings_json' => null,
            'optimized_at' => null,
        ];

        $data = array_merge($defaults, $data);

        // Validate required field
        if (empty($data['attachment_id'])) {
            return false;
        }

        // Serialize settings_json if array
        if (is_array($data['settings_json'])) {
            $data['settings_json'] = wp_json_encode($data['settings_json']);
        }

        // Handle NULL values properly for JSON column
        // wpdb->prepare converts null to empty string, but JSON columns need actual NULL
        $settings_json_value = $data['settings_json'] !== null && $data['settings_json'] !== '' 
            ? $wpdb->prepare('%s', $data['settings_json']) 
            : 'NULL';
        
        $optimized_at_value = $data['optimized_at'] !== null && $data['optimized_at'] !== ''
            ? $wpdb->prepare('%s', $data['optimized_at'])
            : 'NULL';
        
        $error_message_value = $data['error_message'] !== null
            ? $wpdb->prepare('%s', $data['error_message'])
            : 'NULL';

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        // This prevents race conditions when multiple batches process the same file
        $sql = $wpdb->prepare(
            "INSERT INTO {$table_name} 
            (attachment_id, original_size, optimized_size, bytes_saved, percent_saved, 
             status, error_message, jpeg_quality, png_compression, strip_metadata, 
             settings_json, optimized_at)
            VALUES (%d, %d, %d, %d, %f, %s, {$error_message_value}, %d, %d, %d, {$settings_json_value}, {$optimized_at_value})
            ON DUPLICATE KEY UPDATE
                original_size = VALUES(original_size),
                optimized_size = VALUES(optimized_size),
                bytes_saved = VALUES(bytes_saved),
                percent_saved = VALUES(percent_saved),
                status = VALUES(status),
                error_message = VALUES(error_message),
                jpeg_quality = VALUES(jpeg_quality),
                png_compression = VALUES(png_compression),
                strip_metadata = VALUES(strip_metadata),
                settings_json = VALUES(settings_json),
                optimized_at = VALUES(optimized_at)",
            $data['attachment_id'],
            $data['original_size'],
            $data['optimized_size'],
            $data['bytes_saved'],
            $data['percent_saved'],
            $data['status'],
            $data['jpeg_quality'] ?? 0,
            $data['png_compression'] ?? 0,
            $data['strip_metadata'] ? 1 : 0
        );

        $result = $wpdb->query($sql);
        
        return $result !== false;
    }

    /**
     * Get optimization record by attachment ID
     */
    public static function get_by_attachment(int $attachment_id): ?array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE attachment_id = %d",
                $attachment_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        // Decode settings_json
        if (!empty($row['settings_json'])) {
            $row['settings_json'] = json_decode($row['settings_json'], true);
        }

        return $row;
    }

    /**
     * Delete optimization record by attachment ID
     */
    public static function delete_by_attachment(int $attachment_id): bool
    {
        global $wpdb;

        $table_name = self::get_table_name();

        $result = $wpdb->delete(
            $table_name,
            ['attachment_id' => $attachment_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get optimization records by status
     */
    public static function get_by_status(string $status, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count records by status
     */
    public static function count_by_status(string $status): int
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                $status
            )
        );
    }

    /**
     * Get aggregate statistics
     * 
     * Single query for all stats - very efficient.
     */
    public static function get_aggregate_stats(): array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        $row = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'optimized' THEN 1 ELSE 0 END) as optimized_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
                COALESCE(SUM(original_size), 0) as total_original_size,
                COALESCE(SUM(optimized_size), 0) as total_optimized_size,
                COALESCE(SUM(bytes_saved), 0) as total_bytes_saved
            FROM {$table_name}",
            ARRAY_A
        );

        if (!$row) {
            return [
                'total_records' => 0,
                'optimized_count' => 0,
                'pending_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'total_original_size' => 0,
                'total_optimized_size' => 0,
                'total_bytes_saved' => 0,
                'average_savings_percent' => 0,
            ];
        }

        // Calculate average savings percentage
        $total_original = (int) $row['total_original_size'];
        $total_saved = (int) $row['total_bytes_saved'];
        $average_savings_percent = $total_original > 0
            ? round(($total_saved / $total_original) * 100, 1)
            : 0;

        return [
            'total_records' => (int) $row['total_records'],
            'optimized_count' => (int) $row['optimized_count'],
            'pending_count' => (int) $row['pending_count'],
            'failed_count' => (int) $row['failed_count'],
            'skipped_count' => (int) $row['skipped_count'],
            'total_original_size' => $total_original,
            'total_optimized_size' => (int) $row['total_optimized_size'],
            'total_bytes_saved' => $total_saved,
            'average_savings_percent' => $average_savings_percent,
        ];
    }

    /**
     * Get complete optimization statistics
     * 
     * CENTRALIZED SOURCE OF TRUTH for all optimization stats.
     * Use this method everywhere instead of calculating stats separately.
     * 
     * @return array{
     *     total_images: int,
     *     optimized_images: int,
     *     pending_images: int,
     *     skipped_images: int,
     *     failed_images: int,
     *     untracked_images: int,
     *     total_saved: int,
     *     total_saved_formatted: string,
     *     total_original_size: int,
     *     average_savings_percent: float,
     *     progress_percentage: float
     * }
     */
    public static function get_full_stats(): array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        // Get total images from posts table (always fresh)
        $total_images = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'"
        );

        // Get aggregate stats from optimization table (single efficient query)
        $row = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'optimized' THEN 1 ELSE 0 END) as optimized_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_in_table,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
                COALESCE(SUM(original_size), 0) as total_original_size,
                COALESCE(SUM(optimized_size), 0) as total_optimized_size,
                COALESCE(SUM(bytes_saved), 0) as total_bytes_saved
            FROM {$table_name}",
            ARRAY_A
        );

        $optimized = $row ? (int) $row['optimized_count'] : 0;
        $skipped = $row ? (int) $row['skipped_count'] : 0;
        $failed = $row ? (int) $row['failed_count'] : 0;
        $pending_in_table = $row ? (int) $row['pending_in_table'] : 0;
        $total_records = $row ? (int) $row['total_records'] : 0;
        $total_saved = $row ? (int) $row['total_bytes_saved'] : 0;
        $total_original = $row ? (int) $row['total_original_size'] : 0;

        // Untracked = images not yet in optimization table
        $untracked = max(0, $total_images - $total_records);

        // Pending = untracked + pending status in table (excludes skipped, failed, optimized)
        $pending = $untracked + $pending_in_table;

        // Progress percentage based on optimized vs total
        $progress = $total_images > 0 
            ? round(($optimized / $total_images) * 100, 1) 
            : 0;

        // Average savings percentage
        $average_savings = $total_original > 0
            ? round(($total_saved / $total_original) * 100, 1)
            : 0;

        return [
            'total_images' => $total_images,
            'optimized_images' => $optimized,
            'pending_images' => $pending,
            'skipped_images' => $skipped,
            'failed_images' => $failed,
            'untracked_images' => $untracked,
            'total_saved' => $total_saved,
            'total_saved_formatted' => size_format($total_saved),
            'total_original_size' => $total_original,
            'average_savings_percent' => $average_savings,
            'progress_percentage' => $progress,
        ];
    }

    /**
     * Get IDs of attachments not yet in optimization table
     * 
     * Efficient query to find images that haven't been processed.
     */
    public static function get_untracked_attachment_ids(int $limit = 100, int $after_id = 0): array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID 
                FROM {$wpdb->posts} p
                LEFT JOIN {$table_name} o ON p.ID = o.attachment_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%%'
                AND o.attachment_id IS NULL
                AND p.ID > %d
                ORDER BY p.ID ASC
                LIMIT %d",
                $after_id,
                $limit
            )
        ) ?: [];
    }

    /**
     * Get IDs of attachments with status 'pending'
     */
    public static function get_pending_attachment_ids(int $limit = 100, int $after_id = 0): array
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT attachment_id 
                FROM {$table_name}
                WHERE status = 'pending'
                AND attachment_id > %d
                ORDER BY attachment_id ASC
                LIMIT %d",
                $after_id,
                $limit
            )
        ) ?: [];
    }

    /**
     * Mark attachment as pending
     */
    public static function mark_pending(int $attachment_id): bool
    {
        return self::upsert([
            'attachment_id' => $attachment_id,
            'status' => 'pending',
        ]);
    }

    /**
     * Mark attachment as optimized with results
     */
    public static function mark_optimized(
        int $attachment_id,
        int $original_size,
        int $optimized_size,
        array $settings = []
    ): bool {
        $bytes_saved = max(0, $original_size - $optimized_size);
        $percent_saved = $original_size > 0
            ? round(($bytes_saved / $original_size) * 100, 2)
            : 0;

        return self::upsert([
            'attachment_id' => $attachment_id,
            'original_size' => $original_size,
            'optimized_size' => $optimized_size,
            'bytes_saved' => $bytes_saved,
            'percent_saved' => $percent_saved,
            'status' => 'optimized',
            'error_message' => null,
            'jpeg_quality' => $settings['jpeg_quality'] ?? null,
            'png_compression' => $settings['png_compression'] ?? null,
            'strip_metadata' => isset($settings['strip_metadata']) ? ($settings['strip_metadata'] ? 1 : 0) : null,
            'settings_json' => $settings,
            'optimized_at' => current_time('mysql'),
        ]);
    }

    /**
     * Mark attachment as failed
     */
    public static function mark_failed(int $attachment_id, string $error_message): bool
    {
        return self::upsert([
            'attachment_id' => $attachment_id,
            'status' => 'failed',
            'error_message' => substr($error_message, 0, 255),
        ]);
    }

    /**
     * Mark attachment as skipped
     */
    public static function mark_skipped(int $attachment_id, string $reason): bool
    {
        return self::upsert([
            'attachment_id' => $attachment_id,
            'status' => 'skipped',
            'error_message' => substr($reason, 0, 255),
        ]);
    }

    /**
     * Reset all records to pending
     * 
     * Useful for re-processing all images.
     */
    public static function reset_all(): int
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->query(
            "UPDATE {$table_name} SET 
                status = 'pending',
                optimized_at = NULL,
                error_message = NULL"
        );
    }

    /**
     * Delete all records
     */
    public static function truncate(): void
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query("TRUNCATE TABLE {$table_name}");
    }
}

