<?php
/**
 * Abstract Batch Processor class for handling batch operations
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Core;

/**
 * Abstract class for batch processing operations
 * 
 * Provides common functionality for:
 * - State management (start, pause, resume, stop)
 * - Progress tracking
 * - Error handling
 * - AJAX handlers
 */
abstract class Batch_Processor
{
    protected Logger $logger;
    protected Settings $settings;
    
    /** @var string Unique identifier for this processor */
    protected string $processor_id;
    
    /** @var string Transient key for state storage */
    protected string $state_transient_key;
    
    /** @var string Option key for state backup */
    protected string $state_backup_key;
    
    /** @var int Transient TTL in seconds */
    protected int $transient_ttl = 3600;

    public function __construct(
        Logger $logger,
        Settings $settings,
        string $processor_id
    ) {
        $this->logger = $logger;
        $this->settings = $settings;
        $this->processor_id = $processor_id;
        $this->state_transient_key = "media_toolkit_{$processor_id}_state";
        $this->state_backup_key = "media_toolkit_{$processor_id}_checkpoint";

        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers for this processor
     */
    protected function register_ajax_handlers(): void
    {
        $prefix = "media_toolkit_{$this->processor_id}";
        
        add_action("wp_ajax_{$prefix}_start", [$this, 'ajax_start']);
        add_action("wp_ajax_{$prefix}_process_batch", [$this, 'ajax_process_batch']);
        add_action("wp_ajax_{$prefix}_pause", [$this, 'ajax_pause']);
        add_action("wp_ajax_{$prefix}_resume", [$this, 'ajax_resume']);
        add_action("wp_ajax_{$prefix}_stop", [$this, 'ajax_stop']);
        add_action("wp_ajax_{$prefix}_get_status", [$this, 'ajax_get_status']);
    }

    /**
     * Get current processor state
     */
    public function get_state(): array
    {
        // Try transient first
        $state = get_transient($this->state_transient_key);
        
        if ($state !== false) {
            return $state;
        }

        // Try backup
        $backup = get_option($this->state_backup_key);
        
        if (!empty($backup)) {
            set_transient($this->state_transient_key, $backup, $this->transient_ttl);
            return $backup;
        }

        return $this->get_default_state();
    }

    /**
     * Get default state structure
     */
    protected function get_default_state(): array
    {
        return [
            'status' => 'idle',
            'total_files' => 0,
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'current_batch' => 0,
            'last_item_id' => 0,
            'started_at' => 0,
            'updated_at' => 0,
            'errors' => [],
            'options' => [],
        ];
    }

    /**
     * Save processor state
     */
    protected function save_state(array $state): void
    {
        $state['updated_at'] = time();
        
        set_transient($this->state_transient_key, $state, $this->transient_ttl);
        update_option($this->state_backup_key, $state);
    }

    /**
     * Clear processor state
     */
    protected function clear_state(): void
    {
        delete_transient($this->state_transient_key);
        delete_option($this->state_backup_key);
    }

    /**
     * Start batch processing
     * 
     * @param array $options Processing options
     * @return array Initial state
     */
    public function start(array $options = []): array
    {
        $total = $this->count_pending_items($options);

        $state = array_merge($this->get_default_state(), [
            'status' => 'running',
            'total_files' => $total,
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'current_batch' => 0,
            'last_item_id' => 0,
            'started_at' => time(),
            'updated_at' => time(),
            'errors' => [],
            'options' => $options,
        ]);

        $this->save_state($state);

        $this->logger->info(
            $this->processor_id,
            "{$this->get_processor_name()} started. Total items: {$total}"
        );

        return $state;
    }

    /**
     * Process a batch of items
     */
    public function process_batch(): array
    {
        $state = $this->get_state();

        if ($state['status'] !== 'running') {
            return [
                'success' => false,
                'message' => 'Processor is not running',
                'state' => $state,
            ];
        }

        $batch_size = $state['options']['batch_size'] ?? 25;
        $items = $this->get_pending_items($batch_size, $state['last_item_id'], $state['options']);

        if (empty($items)) {
            // Processing complete
            $state['status'] = 'completed';
            $this->save_state($state);
            
            $this->logger->success(
                $this->processor_id,
                "{$this->get_processor_name()} completed. Processed: {$state['processed']}, Failed: {$state['failed']}"
            );

            return [
                'success' => true,
                'complete' => true,
                'state' => $state,
            ];
        }

        $batch_processed = 0;
        $batch_failed = 0;
        $batch_skipped = 0;
        $batch_errors = [];

        foreach ($items as $item) {
            $item_id = $this->get_item_id($item);
            $result = $this->process_item($item, $state['options']);

            if ($result['success']) {
                if ($result['skipped'] ?? false) {
                    $batch_skipped++;
                    $state['skipped']++;
                } else {
                    $batch_processed++;
                    $state['processed']++;
                }
            } else {
                $batch_failed++;
                $state['failed']++;
                $batch_errors[] = [
                    'item_id' => $item_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }

            $state['last_item_id'] = $item_id;
        }

        $state['current_batch']++;
        $state['errors'] = array_merge($state['errors'], $batch_errors);
        
        // Keep only last 50 errors
        if (count($state['errors']) > 50) {
            $state['errors'] = array_slice($state['errors'], -50);
        }

        $this->save_state($state);

        return [
            'success' => true,
            'complete' => false,
            'batch_processed' => $batch_processed,
            'batch_failed' => $batch_failed,
            'batch_skipped' => $batch_skipped,
            'batch_errors' => $batch_errors,
            'state' => $state,
        ];
    }

    /**
     * Pause processing
     */
    public function pause(): void
    {
        $state = $this->get_state();
        
        if ($state['status'] === 'running') {
            $state['status'] = 'paused';
            $this->save_state($state);
            $this->logger->info($this->processor_id, "{$this->get_processor_name()} paused");
        }
    }

    /**
     * Resume processing
     */
    public function resume(): void
    {
        $state = $this->get_state();
        
        if ($state['status'] === 'paused') {
            $state['status'] = 'running';
            $this->save_state($state);
            $this->logger->info($this->processor_id, "{$this->get_processor_name()} resumed");
        }
    }

    /**
     * Stop processing
     */
    public function stop(): void
    {
        $state = $this->get_state();
        $state['status'] = 'idle';
        $this->save_state($state);
        $this->clear_state();
        
        $this->logger->info($this->processor_id, "{$this->get_processor_name()} stopped");
    }

    /**
     * Get processor statistics
     */
    abstract public function get_stats(): array;

    /**
     * Count pending items to process
     */
    abstract protected function count_pending_items(array $options = []): int;

    /**
     * Get pending items for batch processing
     */
    abstract protected function get_pending_items(int $limit, int $after_id, array $options = []): array;

    /**
     * Process a single item
     */
    abstract protected function process_item($item, array $options = []): array;

    /**
     * Get item ID from item
     */
    abstract protected function get_item_id($item): int;

    /**
     * Get processor display name
     */
    abstract protected function get_processor_name(): string;

    // ==================== AJAX Handlers ====================

    public function ajax_start(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        try {
            $options = $this->get_start_options_from_request();
            $state = $this->start($options);

            wp_send_json_success(['state' => $state]);
        } catch (\Throwable $e) {
            $this->logger->error($this->processor_id, 'Exception in ajax_start(): ' . $e->getMessage());
            $this->logger->error($this->processor_id, 'Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error([
                'message' => 'Error starting: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function ajax_process_batch(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        try {
            $result = $this->process_batch();
            $result['stats'] = $this->get_stats();
            
            // Add retry queue count for better feedback
            if (function_exists('Metodo\MediaToolkit\media_toolkit')) {
                $plugin = \Metodo\MediaToolkit\media_toolkit();
                if ($plugin && method_exists($plugin, 'get_error_handler')) {
                    $error_handler = $plugin->get_error_handler();
                    if ($error_handler) {
                        $result['retry_queue_count'] = $error_handler->get_failed_count();
                    }
                }
            }
            
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            $this->logger->error($this->processor_id, 'Exception in ajax_process_batch(): ' . $e->getMessage());
            $this->logger->error($this->processor_id, 'Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error([
                'message' => 'Error processing batch: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function ajax_pause(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->pause();
        wp_send_json_success(['state' => $this->get_state()]);
    }

    public function ajax_resume(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $state = $this->get_state();
        
        if ($state['status'] !== 'paused') {
            wp_send_json_error([
                'message' => 'Processor is not paused',
                'state' => $state,
            ]);
            return;
        }

        $this->resume();
        
        wp_send_json_success([
            'state' => $this->get_state(),
            'stats' => $this->get_stats(),
        ]);
    }

    public function ajax_stop(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->stop();
        wp_send_json_success(['message' => "{$this->get_processor_name()} stopped"]);
    }

    public function ajax_get_status(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        wp_send_json_success([
            'state' => $this->get_state(),
            'stats' => $this->get_stats(),
        ]);
    }

    /**
     * Get start options from AJAX request
     * Override in child class to add custom options
     */
    protected function get_start_options_from_request(): array
    {
        return [
            'batch_size' => isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 25,
        ];
    }
}

