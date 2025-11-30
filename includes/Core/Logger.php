<?php
/**
 * Logger class for plugin activity logging
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Core;

/**
 * Handles logging to database with auto-cleanup
 */
final class Logger
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'media_toolkit_logs';
    }

    /**
     * Log an entry
     */
    public function log(
        LogLevel $level,
        string $operation,
        string $message,
        ?int $attachment_id = null,
        ?string $file_name = null,
        ?array $context = null
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp' => current_time('mysql'),
                'level' => $level->value,
                'operation' => $operation,
                'attachment_id' => $attachment_id,
                'file_name' => $file_name,
                'message' => $message,
                'context' => $context ? wp_json_encode($context) : null,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Shorthand methods for different log levels
     */
    public function info(string $operation, string $message, ?int $attachment_id = null, ?string $file_name = null, ?array $context = null): bool
    {
        return $this->log(LogLevel::INFO, $operation, $message, $attachment_id, $file_name, $context);
    }

    public function warning(string $operation, string $message, ?int $attachment_id = null, ?string $file_name = null, ?array $context = null): bool
    {
        return $this->log(LogLevel::WARNING, $operation, $message, $attachment_id, $file_name, $context);
    }

    public function error(string $operation, string $message, ?int $attachment_id = null, ?string $file_name = null, ?array $context = null): bool
    {
        return $this->log(LogLevel::ERROR, $operation, $message, $attachment_id, $file_name, $context);
    }

    public function success(string $operation, string $message, ?int $attachment_id = null, ?string $file_name = null, ?array $context = null): bool
    {
        return $this->log(LogLevel::SUCCESS, $operation, $message, $attachment_id, $file_name, $context);
    }

    /**
     * Get logs with pagination and filters
     */
    public function get_logs(
        int $page = 1,
        int $per_page = 50,
        ?LogLevel $level = null,
        ?string $operation = null,
        ?string $date_from = null,
        ?string $date_to = null
    ): array {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if ($level !== null) {
            $where[] = 'level = %s';
            $params[] = $level->value;
        }

        if ($operation !== null) {
            $where[] = 'operation = %s';
            $params[] = $operation;
        }

        if ($date_from !== null) {
            $where[] = 'timestamp >= %s';
            $params[] = $date_from;
        }

        if ($date_to !== null) {
            $where[] = 'timestamp <= %s';
            $params[] = $date_to;
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        if (count($params) > 2) {
            $prepared = $wpdb->prepare($query, ...$params);
        } else {
            $prepared = $wpdb->prepare($query, $per_page, $offset);
        }

        return $wpdb->get_results($prepared, ARRAY_A) ?: [];
    }

    /**
     * Get total count of logs with filters
     */
    public function get_total_count(
        ?LogLevel $level = null,
        ?string $operation = null
    ): int {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if ($level !== null) {
            $where[] = 'level = %s';
            $params[] = $level->value;
        }

        if ($operation !== null) {
            $where[] = 'operation = %s';
            $params[] = $operation;
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        if (!empty($params)) {
            $prepared = $wpdb->prepare($query, ...$params);
            return (int) $wpdb->get_var($prepared);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get recent logs (for real-time display)
     */
    public function get_recent(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY timestamp DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get error count for last N days
     */
    public function get_error_count_last_days(int $days = 7): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE level = %s AND timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                LogLevel::ERROR->value,
                $days
            )
        );
    }

    /**
     * Clean up logs older than 24 hours
     */
    public function cleanup_old_logs(): int
    {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Clear all logs
     */
    public function clear_all(): bool
    {
        global $wpdb;

        return $wpdb->query("TRUNCATE TABLE {$this->table_name}") !== false;
    }

    /**
     * Get available operations for filtering
     */
    public function get_operations(): array
    {
        global $wpdb;

        $operations = $wpdb->get_col(
            "SELECT DISTINCT operation FROM {$this->table_name} ORDER BY operation"
        );

        return $operations ?: [];
    }
}
