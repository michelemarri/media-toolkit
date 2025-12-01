<?php
/**
 * History class for permanent activity tracking
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\History;

/**
 * Handles permanent history logging
 */
final class History
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'media_toolkit_history';
    }

    /**
     * Record a history entry
     */
    public function record(
        HistoryAction $action,
        ?int $attachment_id = null,
        ?string $file_path = null,
        ?string $s3_key = null,
        ?int $file_size = null,
        ?array $details = null
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp' => current_time('mysql'),
                'action' => $action->value,
                'attachment_id' => $attachment_id,
                'file_path' => $file_path,
                's3_key' => $s3_key,
                'file_size' => $file_size,
                'user_id' => get_current_user_id() ?: null,
                'details' => $details ? wp_json_encode($details) : null,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Get history with pagination and filters
     */
    public function get_history(
        int $page = 1,
        int $per_page = 50,
        ?HistoryAction $action = null,
        ?string $date_from = null,
        ?string $date_to = null
    ): array {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if ($action !== null) {
            $where[] = 'action = %s';
            $params[] = $action->value;
        }

        if ($date_from !== null && $date_from !== '') {
            $where[] = 'DATE(timestamp) >= %s';
            $params[] = $date_from;
        }

        if ($date_to !== null && $date_to !== '') {
            $where[] = 'DATE(timestamp) <= %s';
            $params[] = $date_to;
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        $query = "SELECT h.*, u.display_name as user_name 
                  FROM {$this->table_name} h 
                  LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID 
                  WHERE {$where_clause} 
                  ORDER BY h.timestamp DESC 
                  LIMIT %d OFFSET %d";
        
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
     * Get total count of history entries
     */
    public function get_total_count(?HistoryAction $action = null): int
    {
        global $wpdb;

        if ($action !== null) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE action = %s",
                    $action->value
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Get total files on S3 (uploaded + migrated - deleted)
     */
    public function get_total_files_on_s3(): int
    {
        global $wpdb;

        $uploaded = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action IN (%s, %s)",
                HistoryAction::UPLOADED->value,
                HistoryAction::MIGRATED->value
            )
        );

        $deleted = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action = %s",
                HistoryAction::DELETED->value
            )
        );

        return max(0, $uploaded - $deleted);
    }

    /**
     * Get total storage used (bytes)
     */
    public function get_total_storage_used(): int
    {
        global $wpdb;

        $uploaded = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(file_size), 0) FROM {$this->table_name} WHERE action IN (%s, %s)",
                HistoryAction::UPLOADED->value,
                HistoryAction::MIGRATED->value
            )
        );

        $deleted = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(file_size), 0) FROM {$this->table_name} WHERE action = %s",
                HistoryAction::DELETED->value
            )
        );

        return max(0, $uploaded - $deleted);
    }

    /**
     * Get files uploaded today
     */
    public function get_files_uploaded_today(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE action IN (%s, %s) AND DATE(timestamp) = CURDATE()",
                HistoryAction::UPLOADED->value,
                HistoryAction::MIGRATED->value
            )
        );
    }

    /**
     * Get migrated files count
     */
    public function get_migrated_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action = %s",
                HistoryAction::MIGRATED->value
            )
        );
    }

    /**
     * Get last upload timestamp
     */
    public function get_last_upload(): ?string
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(timestamp) FROM {$this->table_name} WHERE action IN (%s, %s)",
                HistoryAction::UPLOADED->value,
                HistoryAction::MIGRATED->value
            )
        );
    }

    /**
     * Get uploads per day for last N days
     */
    public function get_uploads_per_day(int $days = 7): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(timestamp) as date, COUNT(*) as count 
                FROM {$this->table_name} 
                WHERE action IN (%s, %s) 
                AND timestamp >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                GROUP BY DATE(timestamp) 
                ORDER BY date ASC",
                HistoryAction::UPLOADED->value,
                HistoryAction::MIGRATED->value,
                $days
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Check if an attachment has been migrated
     */
    public function is_migrated(int $attachment_id): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE attachment_id = %d AND action = %s",
                $attachment_id,
                HistoryAction::MIGRATED->value
            )
        );

        return $count > 0;
    }

    /**
     * Get count of entries by action type
     */
    public function get_action_count(string $action): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action = %s",
                $action
            )
        );
    }

    /**
     * Clear all history entries
     */
    public function clear_all(): bool
    {
        global $wpdb;
        
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}") !== false;
    }

    /**
     * Export history as CSV
     */
    public function export_csv(?HistoryAction $action = null, ?string $date_from = null, ?string $date_to = null): string
    {
        $history = $this->get_history(1, PHP_INT_MAX, $action, $date_from, $date_to);
        
        $csv = "Date,Action,File Path,S3 Key,File Size,User\n";
        
        foreach ($history as $entry) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $entry['timestamp'],
                $entry['action'],
                $entry['file_path'] ?? '',
                $entry['s3_key'] ?? '',
                $entry['file_size'] ?? '',
                $entry['user_name'] ?? ''
            );
        }
        
        return $csv;
    }
}
