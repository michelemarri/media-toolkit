<?php
/**
 * Migration class for batch migrating existing media to S3
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Migration;

use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Error\Error_Handler;
use Metodo\MediaToolkit\Stats\Stats;

/**
 * Handles batch migration of existing media to S3
 */
final class Migration
{
    private const STATE_TRANSIENT = 'media_toolkit_migration_state';
    private const STATE_BACKUP = 'media_toolkit_migration_checkpoint';
    private const TRANSIENT_TTL = 3600; // 1 hour

    private S3_Client $s3_client;
    private Settings $settings;
    private Logger $logger;
    private History $history;
    private Error_Handler $error_handler;

    public function __construct(
        S3_Client $s3_client,
        Settings $settings,
        Logger $logger,
        History $history,
        Error_Handler $error_handler
    ) {
        $this->s3_client = $s3_client;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->history = $history;
        $this->error_handler = $error_handler;

        // Register AJAX handlers
        add_action('wp_ajax_media_toolkit_start_migration', [$this, 'ajax_start_migration']);
        add_action('wp_ajax_media_toolkit_process_batch', [$this, 'ajax_process_batch']);
        add_action('wp_ajax_media_toolkit_pause_migration', [$this, 'ajax_pause_migration']);
        add_action('wp_ajax_media_toolkit_resume_migration', [$this, 'ajax_resume_migration']);
        add_action('wp_ajax_media_toolkit_stop_migration', [$this, 'ajax_stop_migration']);
        add_action('wp_ajax_media_toolkit_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_media_toolkit_retry_failed', [$this, 'ajax_retry_failed']);

        // Cron handler for async migration
        add_action('media_toolkit_async_migration', [$this, 'process_async_batch']);
    }

    /**
     * Get current migration state
     */
    public function get_state(): MigrationState
    {
        // Try transient first
        $state = get_transient(self::STATE_TRANSIENT);
        
        if ($state !== false) {
            return MigrationState::fromArray($state);
        }

        // Try backup
        $backup = get_option(self::STATE_BACKUP);
        
        if (!empty($backup)) {
            // Restore from backup
            set_transient(self::STATE_TRANSIENT, $backup, self::TRANSIENT_TTL);
            return MigrationState::fromArray($backup);
        }

        return new MigrationState();
    }

    /**
     * Save migration state
     */
    private function save_state(MigrationState $state): void
    {
        $state->updated_at = time();
        $data = $state->toArray();
        
        // Save to transient
        set_transient(self::STATE_TRANSIENT, $data, self::TRANSIENT_TTL);
        
        // Save backup
        update_option(self::STATE_BACKUP, $data);
    }

    /**
     * Clear migration state
     */
    private function clear_state(): void
    {
        delete_transient(self::STATE_TRANSIENT);
        delete_option(self::STATE_BACKUP);
    }

    /**
     * Get attachments to migrate
     */
    public function get_pending_attachments(int $limit = 100, int $after_id = 0): array
    {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_media_toolkit_migrated'
                WHERE p.post_type = 'attachment'
                AND (pm.meta_value IS NULL OR pm.meta_value != '1')
                AND p.ID > %d
                ORDER BY p.ID ASC
                LIMIT %d",
                $after_id,
                $limit
            )
        );
    }

    /**
     * Count total pending attachments
     */
    public function count_pending(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_media_toolkit_migrated'
                WHERE p.post_type = 'attachment'
                AND (pm.meta_value IS NULL OR pm.meta_value != %s)",
                '1'
            )
        );
    }

    /**
     * Start migration
     */
    public function start(int $batch_size = 25, bool $remove_local = false): MigrationState
    {
        $total = $this->count_pending();

        $state = new MigrationState(
            status: MigrationStatus::RUNNING,
            total_files: $total,
            processed: 0,
            failed: 0,
            current_batch: 0,
            last_attachment_id: 0,
            started_at: time(),
            updated_at: time(),
            errors: [],
            remove_local: $remove_local,
            batch_size: $batch_size,
        );

        $this->save_state($state);

        $this->logger->info('migration', "Migration started. Total files: {$total}");
        $this->history->record(HistoryAction::MIGRATION_STARTED, null, null, null, null, [
            'total_files' => $total,
            'batch_size' => $batch_size,
            'remove_local' => $remove_local,
        ]);

        return $state;
    }

    /**
     * Process a batch of files
     */
    public function process_batch(): array
    {
        $state = $this->get_state();

        if ($state->status !== MigrationStatus::RUNNING) {
            return [
                'success' => false,
                'message' => 'Migration is not running',
                'state' => $state->toArray(),
            ];
        }

        $attachments = $this->get_pending_attachments(
            $state->batch_size,
            $state->last_attachment_id
        );

        if (empty($attachments)) {
            // Migration complete
            $state->status = MigrationStatus::COMPLETED;
            $this->save_state($state);
            
            $this->logger->success(
                'migration',
                "Migration completed. Processed: {$state->processed}, Failed: {$state->failed}"
            );
            
            $this->history->record(HistoryAction::MIGRATION_COMPLETED, null, null, null, null, [
                'processed' => $state->processed,
                'failed' => $state->failed,
                'duration' => time() - $state->started_at,
            ]);

            return [
                'success' => true,
                'complete' => true,
                'state' => $state->toArray(),
            ];
        }

        $batch_processed = 0;
        $batch_failed = 0;
        $batch_errors = [];

        foreach ($attachments as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            $result = $this->migrate_attachment($attachment_id, $state->remove_local);

            if ($result['success']) {
                $batch_processed++;
                $state->processed++;
            } else {
                $batch_failed++;
                $state->failed++;
                $batch_errors[] = [
                    'attachment_id' => $attachment_id,
                    'error' => $result['error'],
                ];
            }

            $state->last_attachment_id = $attachment_id;
        }

        $state->current_batch++;
        $state->errors = array_merge($state->errors, $batch_errors);
        
        // Keep only last 50 errors
        if (count($state->errors) > 50) {
            $state->errors = array_slice($state->errors, -50);
        }

        $this->save_state($state);

        return [
            'success' => true,
            'complete' => false,
            'batch_processed' => $batch_processed,
            'batch_failed' => $batch_failed,
            'batch_errors' => $batch_errors,
            'state' => $state->toArray(),
        ];
    }

    /**
     * Migrate a single attachment
     */
    public function migrate_attachment(int $attachment_id, bool $remove_local = false): array
    {
        $file = get_attached_file($attachment_id);
        
        if (empty($file)) {
            return [
                'success' => false,
                'error' => 'No file path found',
            ];
        }

        if (!file_exists($file)) {
            return [
                'success' => false,
                'error' => 'File does not exist locally',
            ];
        }

        // Upload main file
        $result = $this->s3_client->upload_file($file, $attachment_id);

        if (!$result->success) {
            return [
                'success' => false,
                'error' => $result->error,
            ];
        }

        // Update meta
        update_post_meta($attachment_id, '_media_toolkit_key', $result->s3_key);
        update_post_meta($attachment_id, '_media_toolkit_url', $result->url);
        update_post_meta($attachment_id, '_media_toolkit_migrated', '1');

        $file_size = filesize($file) ?: 0;

        // Record in history
        $this->history->record(
            HistoryAction::MIGRATED,
            $attachment_id,
            $file,
            $result->s3_key,
            $file_size
        );

        // Upload thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!empty($metadata['sizes'])) {
            $file_dir = dirname($file);
            $thumb_keys = [];

            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $thumb_file = $file_dir . '/' . $size_data['file'];
                
                if (file_exists($thumb_file)) {
                    $thumb_result = $this->s3_client->upload_file($thumb_file, $attachment_id);
                    
                    if ($thumb_result->success) {
                        $thumb_keys[$size_name] = $thumb_result->s3_key;
                        
                        if ($remove_local) {
                            @unlink($thumb_file);
                        }
                    }
                }
            }

            update_post_meta($attachment_id, '_media_toolkit_thumb_keys', $thumb_keys);
        }

        // Remove local file if requested
        if ($remove_local) {
            @unlink($file);
        }

        $this->logger->success(
            'migration',
            'Attachment migrated to S3',
            $attachment_id,
            basename($file)
        );

        return [
            'success' => true,
            's3_key' => $result->s3_key,
            'url' => $result->url,
        ];
    }

    /**
     * Pause migration
     */
    public function pause(): void
    {
        $state = $this->get_state();
        
        if ($state->status === MigrationStatus::RUNNING) {
            $state->status = MigrationStatus::PAUSED;
            $this->save_state($state);
            $this->logger->info('migration', 'Migration paused');
        }
    }

    /**
     * Resume migration
     */
    public function resume(): void
    {
        $state = $this->get_state();
        
        if ($state->status === MigrationStatus::PAUSED) {
            $state->status = MigrationStatus::RUNNING;
            $this->save_state($state);
            $this->logger->info('migration', 'Migration resumed');
        }
    }

    /**
     * Stop migration
     */
    public function stop(): void
    {
        $state = $this->get_state();
        $state->status = MigrationStatus::IDLE;
        $this->save_state($state);
        $this->clear_state();
        
        $this->logger->info('migration', 'Migration stopped');
    }

    /**
     * Schedule async batch processing
     */
    public function schedule_async_batch(): void
    {
        if (!wp_next_scheduled('media_toolkit_async_migration')) {
            wp_schedule_single_event(time() + 5, 'media_toolkit_async_migration');
        }
    }

    /**
     * Process async batch (cron handler)
     */
    public function process_async_batch(): void
    {
        $state = $this->get_state();
        
        if ($state->status !== MigrationStatus::RUNNING) {
            return;
        }

        $result = $this->process_batch();

        // Schedule next batch if not complete
        if (!$result['complete'] && $state->status === MigrationStatus::RUNNING) {
            $this->schedule_async_batch();
        }
    }

    // AJAX Handlers

    public function ajax_start_migration(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 25;
        $remove_local = isset($_POST['remove_local']) && $_POST['remove_local'] === 'true';
        $async = isset($_POST['async']) && $_POST['async'] === 'true';

        $state = $this->start($batch_size, $remove_local);

        if ($async) {
            $this->schedule_async_batch();
        }

        wp_send_json_success(['state' => $state->toArray()]);
    }

    public function ajax_process_batch(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $result = $this->process_batch();
        
        // Add real stats from database
        $stats = new Stats($this->logger, $this->history);
        $result['stats'] = $stats->get_migration_stats();
        
        wp_send_json_success($result);
    }

    public function ajax_pause_migration(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->pause();
        wp_send_json_success(['state' => $this->get_state()->toArray()]);
    }

    public function ajax_resume_migration(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $state = $this->get_state();
        
        // Can only resume if paused
        if ($state->status !== MigrationStatus::PAUSED) {
            wp_send_json_error([
                'message' => 'Migration is not paused',
                'state' => $state->toArray(),
            ]);
            return;
        }

        $this->resume();
        
        // Get updated stats
        $stats = new Stats($this->logger, $this->history);
        
        wp_send_json_success([
            'state' => $this->get_state()->toArray(),
            'stats' => $stats->get_migration_stats(),
        ]);
    }

    public function ajax_stop_migration(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->stop();
        wp_send_json_success(['message' => 'Migration stopped']);
    }

    public function ajax_get_status(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Get real stats from database
        $stats = new Stats($this->logger, $this->history);
        $migration_stats = $stats->get_migration_stats();

        wp_send_json_success([
            'state' => $this->get_state()->toArray(),
            'pending' => $this->count_pending(),
            'stats' => $migration_stats,
        ]);
    }

    public function ajax_retry_failed(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->error_handler->retry_failed_operations();
        wp_send_json_success(['message' => 'Retry initiated']);
    }
}

