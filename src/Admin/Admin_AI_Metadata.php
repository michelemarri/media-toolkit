<?php
/**
 * Admin AI Metadata page controller
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\AI\AIManager;
use Metodo\MediaToolkit\AI\MetadataGenerator;
use function Metodo\MediaToolkit\media_toolkit;

/**
 * Handles the AI Metadata admin page
 */
final class Admin_AI_Metadata
{
    private ?AIManager $ai_manager;
    private ?MetadataGenerator $metadata_generator;

    public function __construct(
        ?AIManager $ai_manager = null,
        ?MetadataGenerator $metadata_generator = null
    ) {
        $this->ai_manager = $ai_manager;
        $this->metadata_generator = $metadata_generator;

        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers(): void
    {
        add_action('wp_ajax_media_toolkit_ai_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_media_toolkit_ai_estimate_cost', [$this, 'ajax_estimate_cost']);
        add_action('wp_ajax_media_toolkit_ai_generate_single', [$this, 'ajax_generate_single']);
    }

    /**
     * Get AI manager instance
     */
    public function get_ai_manager(): ?AIManager
    {
        return $this->ai_manager;
    }

    /**
     * Get metadata generator instance
     */
    public function get_metadata_generator(): ?MetadataGenerator
    {
        return $this->metadata_generator;
    }

    /**
     * Check if AI metadata generation is available
     */
    public function is_available(): bool
    {
        return $this->ai_manager !== null && $this->ai_manager->hasConfiguredProvider();
    }

    /**
     * Get metadata stats
     */
    public function get_stats(): array
    {
        if ($this->metadata_generator === null) {
            return [
                'total_images' => 0,
                'with_alt_text' => 0,
                'without_alt_text' => 0,
                'with_title' => 0,
                'without_title' => 0,
                'with_caption' => 0,
                'without_caption' => 0,
                'with_description' => 0,
                'without_description' => 0,
                'pct_alt_text' => 0,
                'pct_title' => 0,
                'pct_caption' => 0,
                'pct_description' => 0,
                'overall_completeness' => 0,
                'ai_generated_count' => 0,
            ];
        }

        return $this->metadata_generator->get_stats();
    }

    /**
     * Get cost estimate
     */
    public function get_cost_estimate(bool $only_empty = true): array
    {
        if ($this->metadata_generator === null || $this->ai_manager === null) {
            return [
                'total' => 0,
                'per_image' => 0,
                'provider' => '',
                'currency' => 'USD',
            ];
        }

        return $this->metadata_generator->estimate_cost($only_empty);
    }

    /**
     * Get current state
     */
    public function get_state(): array
    {
        if ($this->metadata_generator === null) {
            return [
                'status' => 'idle',
                'total_files' => 0,
                'processed' => 0,
                'failed' => 0,
            ];
        }

        return $this->metadata_generator->get_state();
    }

    /**
     * AJAX: Get metadata stats
     */
    public function ajax_get_stats(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        wp_send_json_success([
            'stats' => $this->get_stats(),
            'state' => $this->get_state(),
        ]);
    }

    /**
     * AJAX: Estimate cost for batch processing
     */
    public function ajax_estimate_cost(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $only_empty = isset($_POST['only_empty']) ? $_POST['only_empty'] === 'true' : true;

        wp_send_json_success([
            'estimate' => $this->get_cost_estimate($only_empty),
        ]);
    }

    /**
     * AJAX: Generate metadata for a single image
     */
    public function ajax_generate_single(): void
    {
        // Log to PHP error log for debugging 500 errors
        error_log('[Media Toolkit AI] ajax_generate_single called');
        
        // Wrap everything in try-catch to prevent 500 errors
        try {
            check_ajax_referer('media_toolkit_nonce', 'nonce');

            if (!current_user_can('upload_files')) {
                $this->log_error('Permission denied for AI generation');
                wp_send_json_error(['message' => __('Permission denied', 'media-toolkit')]);
                return;
            }

            $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
            $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';

            error_log('[Media Toolkit AI] Processing attachment #' . $attachment_id);

            if ($attachment_id <= 0) {
                $this->log_error('Invalid attachment ID: ' . $attachment_id);
                wp_send_json_error(['message' => __('Invalid attachment ID', 'media-toolkit')]);
                return;
            }

            if (!$this->is_available()) {
                error_log('[Media Toolkit AI] No provider configured');
                $this->log_error('No AI provider configured');
                wp_send_json_error(['message' => __('No AI provider configured. Please configure at least one provider in Settings.', 'media-toolkit')]);
                return;
            }

            error_log('[Media Toolkit AI] Starting generation...');
            $this->log_info('Starting AI generation for attachment #' . $attachment_id);
            
            $result = $this->metadata_generator->generate_single($attachment_id, $overwrite);

            error_log('[Media Toolkit AI] Result: ' . wp_json_encode($result));

            if ($result['success']) {
                $this->log_info('AI generation successful for attachment #' . $attachment_id);
                wp_send_json_success($result);
            } else {
                $error_message = $result['message'] ?? $result['error'] ?? __('Unknown error occurred', 'media-toolkit');
                $this->log_error('AI generation failed for #' . $attachment_id . ': ' . $error_message);
                wp_send_json_error([
                    'message' => $error_message,
                    'error' => $error_message,
                ]);
            }
        } catch (\Metodo\MediaToolkit\AI\AIProviderException $e) {
            error_log('[Media Toolkit AI] AIProviderException: ' . $e->getMessage());
            $this->log_error('AIProviderException: ' . $e->getMessage() . ' (Type: ' . $e->getErrorType() . ')');
            wp_send_json_error([
                'message' => $e->getMessage() ?: __('AI provider error', 'media-toolkit'),
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType(),
            ]);
        } catch (\Throwable $e) {
            // Catch any error including fatal errors
            $error_msg = sprintf(
                '%s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            error_log('[Media Toolkit AI] FATAL: ' . $error_msg);
            error_log('[Media Toolkit AI] Stack trace: ' . $e->getTraceAsString());
            $this->log_error('Fatal error: ' . $error_msg);
            wp_send_json_error([
                'message' => $e->getMessage() ?: __('Unexpected error occurred', 'media-toolkit'),
                'error' => $error_msg,
            ]);
        }
    }

    /**
     * Log info message
     */
    private function log_info(string $message): void
    {
        error_log('[Media Toolkit AI] INFO: ' . $message);
        media_toolkit()->get_logger()?->info('ai_metadata', $message);
    }

    /**
     * Log error message
     */
    private function log_error(string $message): void
    {
        error_log('[Media Toolkit AI] ERROR: ' . $message);
        media_toolkit()->get_logger()?->error('ai_metadata', $message);
    }
}

