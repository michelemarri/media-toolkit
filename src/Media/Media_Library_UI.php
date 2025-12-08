<?php
/**
 * Media Library UI class for enhanced Media Library integration
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Media;

use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;

use Metodo\MediaToolkit\AI\AIManager;
use Metodo\MediaToolkit\AI\UploadHandler as AIUploadHandler;

use function Metodo\MediaToolkit\media_toolkit;

/**
 * Handles Media Library UI enhancements:
 * - Storage status column in list view
 * - Storage info in attachment details
 * - Bulk actions (re-upload, download)
 * - Quick actions
 */
final class Media_Library_UI
{
    private Settings $settings;
    private ?StorageInterface $storage;
    private ?Logger $logger;
    private ?History $history;

    public function __construct(
        Settings $settings,
        ?StorageInterface $storage = null,
        ?Logger $logger = null,
        ?History $history = null
    ) {
        $this->settings = $settings;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->history = $history;

        $this->register_hooks();
    }

    /**
     * Register all hooks
     */
    private function register_hooks(): void
    {
        // List view columns
        add_filter('manage_media_columns', [$this, 'add_s3_column']);
        add_action('manage_media_custom_column', [$this, 'render_s3_column'], 10, 2);
        add_filter('manage_upload_sortable_columns', [$this, 'make_s3_column_sortable']);
        add_action('pre_get_posts', [$this, 'sort_by_s3_status']);

        // Bulk actions
        add_filter('bulk_actions-upload', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-upload', [$this, 'handle_bulk_actions'], 10, 3);
        add_action('admin_notices', [$this, 'show_bulk_action_notices']);

        // Row actions
        add_filter('media_row_actions', [$this, 'add_row_actions'], 10, 2);

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_media_toolkit_upload_single', [$this, 'ajax_upload_single']);
        add_action('wp_ajax_media_toolkit_reupload', [$this, 'ajax_reupload']);
        add_action('wp_ajax_media_toolkit_download_from_s3', [$this, 'ajax_download_from_s3']);
        add_action('wp_ajax_media_toolkit_get_attachment_s3_info', [$this, 'ajax_get_attachment_info']);

        // Attachment details modal (Backbone/JS)
        add_filter('wp_prepare_attachment_for_js', [$this, 'add_s3_data_for_js'], 20, 3);
        
        // Add inline script for attachment details
        add_action('admin_footer-upload.php', [$this, 'render_attachment_details_template']);
        add_action('admin_footer-post.php', [$this, 'render_attachment_details_template']);
    }

    /**
     * Add S3 column to Media Library list
     */
    public function add_s3_column(array $columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Insert after title
            if ($key === 'title') {
                $new_columns['s3_status'] = '<span class="dashicons dashicons-cloud" title="Cloud Status"></span>';
            }
        }
        
        return $new_columns;
    }

    /**
     * Render S3 column content
     */
    public function render_s3_column(string $column_name, int $post_id): void
    {
        if ($column_name !== 's3_status') {
            return;
        }

        $is_migrated = !empty(get_post_meta($post_id, '_media_toolkit_migrated', true));
        $is_optimized = !empty(get_post_meta($post_id, '_media_toolkit_optimized', true));
        $s3_url = $this->get_dynamic_url($post_id);
        $bytes_saved = (int) get_post_meta($post_id, '_media_toolkit_bytes_saved', true);

        if ($is_migrated) {
            // Green checkmark for synced
            echo '<span class="dashicons dashicons-yes-alt" style="color: #16a34a;" title="' . esc_attr__('On Cloud', 'media-toolkit') . '"></span>';
            
            // Show savings if optimized
            if ($is_optimized && $bytes_saved > 0) {
                echo '<br><small style="color: #16a34a;">-' . esc_html(size_format($bytes_saved)) . '</small>';
            }
            
            // Link to CDN
            if (!empty($s3_url)) {
                echo sprintf(
                    ' <a href="%s" target="_blank" style="color: #6b7280; text-decoration: none;" title="%s"><span class="dashicons dashicons-external"></span></a>',
                    esc_url($s3_url),
                    esc_attr__('View on CDN', 'media-toolkit')
                );
            }
        } else {
            // Gray dash for local only
            echo '<span class="dashicons dashicons-minus" style="color: #9ca3af;" title="' . esc_attr__('Local only', 'media-toolkit') . '"></span>';
        }
    }

    /**
     * Make S3 column sortable
     */
    public function make_s3_column_sortable(array $columns): array
    {
        $columns['s3_status'] = 's3_status';
        return $columns;
    }

    /**
     * Sort by S3 status
     */
    public function sort_by_s3_status(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('orderby') !== 's3_status') {
            return;
        }

        $query->set('meta_key', '_media_toolkit_migrated');
        $query->set('orderby', 'meta_value');
    }

    /**
     * Add bulk actions
     */
    public function add_bulk_actions(array $actions): array
    {
        if ($this->storage !== null) {
            $actions['media_toolkit_upload'] = __('Upload to Cloud', 'media-toolkit');
            $actions['media_toolkit_reupload'] = __('Re-upload to Cloud', 'media-toolkit');
            $actions['media_toolkit_download'] = __('Download from Cloud', 'media-toolkit');
        }
        
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions(string $redirect_to, string $action, array $post_ids): string
    {
        if (!in_array($action, ['media_toolkit_upload', 'media_toolkit_reupload', 'media_toolkit_download'])) {
            return $redirect_to;
        }

        if ($this->storage === null) {
            return add_query_arg('s3_bulk_error', 'not_configured', $redirect_to);
        }

        $success = 0;
        $failed = 0;

        foreach ($post_ids as $post_id) {
            $result = match ($action) {
                'media_toolkit_upload', 'media_toolkit_reupload' => $this->upload_attachment($post_id, $action === 'media_toolkit_reupload'),
                'media_toolkit_download' => $this->download_attachment($post_id),
                default => false,
            };

            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }

        return add_query_arg([
            's3_bulk_action' => $action,
            's3_bulk_success' => $success,
            's3_bulk_failed' => $failed,
        ], $redirect_to);
    }

    /**
     * Show bulk action notices
     */
    public function show_bulk_action_notices(): void
    {
        if (!isset($_GET['s3_bulk_action'])) {
            return;
        }

        $action = sanitize_text_field($_GET['s3_bulk_action']);
        $success = (int) ($_GET['s3_bulk_success'] ?? 0);
        $failed = (int) ($_GET['s3_bulk_failed'] ?? 0);

        $action_label = match ($action) {
            'media_toolkit_upload' => 'uploaded to cloud',
            'media_toolkit_reupload' => 're-uploaded to cloud',
            'media_toolkit_download' => 'downloaded from cloud',
            default => 'processed',
        };

        if ($success > 0) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%d file(s) successfully %s.</p></div>',
                $success,
                $action_label
            );
        }

        if ($failed > 0) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%d file(s) failed to be %s.</p></div>',
                $failed,
                $action_label
            );
        }

        if (isset($_GET['s3_bulk_error']) && $_GET['s3_bulk_error'] === 'not_configured') {
            echo '<div class="notice notice-error is-dismissible"><p>Storage is not configured. Please configure your storage provider first.</p></div>';
        }
    }

    /**
     * Add row actions
     */
    public function add_row_actions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== 'attachment') {
            return $actions;
        }

        $is_migrated = !empty(get_post_meta($post->ID, '_media_toolkit_migrated', true));

        if ($this->storage !== null) {
            if ($is_migrated) {
                $actions['s3_reupload'] = sprintf(
                    '<a href="#" class="mt-action-reupload" data-id="%d" data-nonce="%s">%s</a>',
                    $post->ID,
                    wp_create_nonce('media_toolkit_action_' . $post->ID),
                    __('Re-upload to Cloud', 'media-toolkit')
                );
                
                // Check if local file is missing
                $file = get_attached_file($post->ID);
                if (!file_exists($file)) {
                    $actions['s3_download'] = sprintf(
                        '<a href="#" class="mt-action-download" data-id="%d" data-nonce="%s">%s</a>',
                        $post->ID,
                        wp_create_nonce('media_toolkit_action_' . $post->ID),
                        __('Download from Cloud', 'media-toolkit')
                    );
                }
            } else {
                $actions['s3_upload'] = sprintf(
                    '<a href="#" class="mt-action-upload" data-id="%d" data-nonce="%s">%s</a>',
                    $post->ID,
                    wp_create_nonce('media_toolkit_action_' . $post->ID),
                    __('Upload to Cloud', 'media-toolkit')
                );
            }
        }

        return $actions;
    }

    /**
     * Upload attachment to S3
     */
    private function upload_attachment(int $attachment_id, bool $force = false): bool
    {
        $upload_handler = $this->get_upload_handler();
        
        if ($upload_handler === null) {
            return false;
        }

        $result = $upload_handler->upload_attachment($attachment_id, $force);
        
        return $result['success'];
    }

    /**
     * Download attachment from S3
     */
    private function download_attachment(int $attachment_id): bool
    {
        if ($this->storage === null) {
            return false;
        }

        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($s3_key)) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        
        // Create directory if needed
        $dir = dirname($file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $result = $this->storage->download_file($s3_key, $file, $attachment_id);
        
        if ($result) {
            $this->logger?->info('media_library', 'File downloaded from storage via Media Library', $attachment_id, basename($file));
        }

        return $result;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['upload.php', 'post.php', 'post-new.php'])) {
            return;
        }

        // Inline CSS for Media Library
        $css = $this->get_inline_css();
        wp_add_inline_style('media-views', $css);

        // Enqueue our script
        wp_enqueue_script(
            'media-toolkit-media-library',
            MEDIA_TOOLKIT_URL . 'assets/media-library.js',
            ['jquery', 'media-views'],
            MEDIA_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('media-toolkit-media-library', 's3OffloadMedia', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('media_toolkit_nonce'),
            'strings' => [
                'uploading' => __('Uploading...', 'media-toolkit'),
                'downloading' => __('Downloading...', 'media-toolkit'),
                'success' => __('Success!', 'media-toolkit'),
                'error' => __('Error', 'media-toolkit'),
                'confirmReupload' => __('Re-upload this file to cloud storage?', 'media-toolkit'),
                'confirmDownload' => __('Download this file from cloud storage to local server?', 'media-toolkit'),
                'generating' => __('Generating...', 'media-toolkit'),
                'aiGenerated' => __('AI metadata generated!', 'media-toolkit'),
                'aiError' => __('Failed to generate AI metadata', 'media-toolkit'),
            ],
        ]);
    }

    /**
     * Get inline CSS - moved to admin.css, keeping method for compatibility
     */
    private function get_inline_css(): string
    {
        return '/* Styles moved to admin.css */';
    }

    /**
     * Add S3 data for JavaScript (attachment modal)
     */
    public function add_s3_data_for_js(array $response, \WP_Post $attachment, array $meta): array
    {
        $attachment_id = $attachment->ID;
        
        $is_migrated = !empty(get_post_meta($attachment_id, '_media_toolkit_migrated', true));
        $is_optimized = !empty(get_post_meta($attachment_id, '_media_toolkit_optimized', true));
        
        $response['s3Offload'] = [
            'migrated' => $is_migrated,
            'optimized' => $is_optimized,
            's3Key' => get_post_meta($attachment_id, '_media_toolkit_key', true) ?: null,
            's3Url' => $this->get_dynamic_url($attachment_id),
            'originalSize' => (int) get_post_meta($attachment_id, '_media_toolkit_original_size', true),
            'optimizedSize' => (int) get_post_meta($attachment_id, '_media_toolkit_optimized_size', true),
            'bytesSaved' => (int) get_post_meta($attachment_id, '_media_toolkit_bytes_saved', true),
            'optimizedAt' => get_post_meta($attachment_id, '_media_toolkit_optimized_at', true) ?: null,
            'localExists' => file_exists(get_attached_file($attachment_id)),
            'nonce' => wp_create_nonce('media_toolkit_action_' . $attachment_id),
        ];

        // Add AI metadata info for images
        if (str_starts_with($attachment->post_mime_type, 'image/')) {
            $ai_manager = media_toolkit()->get_ai_manager();
            $ai_generated = get_post_meta($attachment_id, '_media_toolkit_ai_generated', true);
            $ai_pending = AIUploadHandler::is_generation_pending($attachment_id);
            
            $response['aiMetadata'] = [
                'available' => $ai_manager !== null && $ai_manager->hasConfiguredProvider(),
                'generated' => !empty($ai_generated),
                'generatedAt' => $ai_generated ?: null,
                'pending' => $ai_pending,
                'provider' => get_post_meta($attachment_id, '_media_toolkit_ai_provider', true) ?: null,
                'hasAltText' => !empty(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
                'hasCaption' => !empty($attachment->post_excerpt),
                'hasDescription' => !empty($attachment->post_content),
            ];
        }

        return $response;
    }

    /**
     * Render attachment details template for Backbone
     */
    public function render_attachment_details_template(): void
    {
        ?>
        <script type="text/html" id="tmpl-mt-offload-details">
            <# if (data.s3Offload) { #>
            <div class="settings mt-offload-section">
                <h4><span class="dashicons dashicons-cloud"></span> <?php _e('Cloud Storage', 'media-toolkit'); ?></h4>
                
                <# if (data.s3Offload.migrated) { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="✓ <?php _e('On Cloud', 'media-toolkit'); ?><# if (data.s3Offload.optimized) { #> · <?php _e('Optimized', 'media-toolkit'); ?><# } #>" readonly style="color: #16a34a;">
                    </label>
                    
                    <# if (data.s3Offload.bytesSaved > 0) { #>
                    <label class="setting">
                        <span class="name"><?php _e('Savings', 'media-toolkit'); ?></span>
                        <# var percent = Math.round((data.s3Offload.bytesSaved / data.s3Offload.originalSize) * 100); #>
                        <input type="text" value="-{{ s3OffloadMedia.formatBytes(data.s3Offload.bytesSaved) }} ({{ percent }}%)" readonly style="color: #16a34a;">
                    </label>
                    <# } #>
                    
                    <label class="setting">
                        <span class="name"><?php _e('Local', 'media-toolkit'); ?></span>
                        <# if (data.s3Offload.localExists) { #>
                            <input type="text" value="<?php _e('Available', 'media-toolkit'); ?>" readonly style="color: #16a34a;">
                        <# } else { #>
                            <input type="text" value="<?php _e('Removed', 'media-toolkit'); ?>" readonly style="color: #dc2626;">
                        <# } #>
                    </label>
                    
                    <label class="setting">
                        <span class="name">&nbsp;</span>
                        <# if (data.s3Offload.s3Url) { #>
                        <a href="{{ data.s3Offload.s3Url }}" target="_blank" class="button"><?php _e('View on CDN', 'media-toolkit'); ?></a>
                        <# } #>
                        <button type="button" class="button mt-btn-reupload" data-id="{{ data.id }}"><?php _e('Re-sync', 'media-toolkit'); ?></button>
                        <# if (!data.s3Offload.localExists) { #>
                        <button type="button" class="button mt-btn-download" data-id="{{ data.id }}"><?php _e('Download', 'media-toolkit'); ?></button>
                        <# } #>
                    </label>
                    
                <# } else { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="<?php _e('Local only', 'media-toolkit'); ?>" readonly>
                    </label>
                    
                    <label class="setting">
                        <span class="name">&nbsp;</span>
                        <button type="button" class="button button-primary mt-btn-upload" data-id="{{ data.id }}"><?php _e('Upload to Cloud', 'media-toolkit'); ?></button>
                    </label>
                <# } #>
            </div>
            <# } #>
            
            <# if (data.aiMetadata) { #>
            <div class="settings mt-ai-section">
                <h4><span class="dashicons dashicons-format-image"></span> <?php _e('AI Metadata', 'media-toolkit'); ?></h4>
                
                <# if (data.aiMetadata.available) { #>
                    <# if (data.aiMetadata.pending) { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="⏳ <?php _e('AI generation pending...', 'media-toolkit'); ?>" readonly style="color: #7c3aed;">
                    </label>
                    <# } else { #>
                    <label class="setting">
                        <span class="name"><?php _e('Alt Text', 'media-toolkit'); ?></span>
                        <# if (data.aiMetadata.hasAltText) { #>
                            <input type="text" value="✓ <?php _e('Filled', 'media-toolkit'); ?>" readonly style="color: #16a34a;">
                        <# } else { #>
                            <input type="text" value="✗ <?php _e('Empty', 'media-toolkit'); ?>" readonly style="color: #dc2626;">
                        <# } #>
                    </label>
                    
                    <label class="setting">
                        <span class="name"><?php _e('Caption', 'media-toolkit'); ?></span>
                        <# if (data.aiMetadata.hasCaption) { #>
                            <input type="text" value="✓ <?php _e('Filled', 'media-toolkit'); ?>" readonly style="color: #16a34a;">
                        <# } else { #>
                            <input type="text" value="✗ <?php _e('Empty', 'media-toolkit'); ?>" readonly style="color: #dc2626;">
                        <# } #>
                    </label>
                    
                    <# if (data.aiMetadata.generated) { #>
                    <label class="setting">
                        <span class="name"><?php _e('AI Generated', 'media-toolkit'); ?></span>
                        <input type="text" value="✓ {{ data.aiMetadata.provider || 'AI' }}" readonly style="color: #7c3aed;">
                    </label>
                    <# } #>
                    <# } #>
                    
                    <label class="setting">
                        <span class="name">&nbsp;</span>
                        <# if (data.aiMetadata.pending) { #>
                        <button type="button" class="button" disabled><?php _e('Processing...', 'media-toolkit'); ?></button>
                        <# } else { #>
                        <button type="button" class="button mt-btn-generate-ai" data-id="{{ data.id }}"><?php _e('Generate with AI', 'media-toolkit'); ?></button>
                        <# } #>
                    </label>
                <# } else { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="<?php _e('No AI provider configured', 'media-toolkit'); ?>" readonly style="color: #9ca3af;">
                    </label>
                    <label class="setting">
                        <span class="name">&nbsp;</span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=media-toolkit-settings&tab=ai-providers')); ?>" class="button"><?php _e('Configure Provider', 'media-toolkit'); ?></a>
                    </label>
                <# } #>
            </div>
            <# } #>
        </script>
        <?php
    }

    /**
     * Get Upload Handler from plugin instance
     */
    private function get_upload_handler(): ?Upload_Handler
    {
        return media_toolkit()->get_upload_handler();
    }

    /**
     * AJAX: Upload single file to S3 (new upload)
     */
    public function ajax_upload_single(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
            return;
        }

        $upload_handler = $this->get_upload_handler();
        
        if ($upload_handler === null) {
            wp_send_json_error(['message' => 'Storage is not configured']);
            return;
        }

        $result = $upload_handler->upload_attachment($attachment_id);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                's3Key' => $result['s3_key'] ?? null,
                's3Url' => $result['s3_url'] ?? null,
                'migrated' => true,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Re-upload file to S3
     */
    public function ajax_reupload(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
            return;
        }

        $upload_handler = $this->get_upload_handler();
        
        if ($upload_handler === null) {
            wp_send_json_error(['message' => 'Storage is not configured']);
            return;
        }

        $result = $upload_handler->upload_attachment($attachment_id, true); // force = true
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                's3Key' => $result['s3_key'] ?? null,
                's3Url' => $result['s3_url'] ?? null,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Download file from S3
     */
    public function ajax_download_from_s3(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $result = $this->download_attachment($attachment_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'File downloaded from cloud']);
        } else {
            wp_send_json_error(['message' => 'Failed to download file from cloud']);
        }
    }

    /**
     * AJAX: Get attachment S3 info
     */
    public function ajax_get_attachment_info(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $is_migrated = !empty(get_post_meta($attachment_id, '_media_toolkit_migrated', true));
        $is_optimized = !empty(get_post_meta($attachment_id, '_media_toolkit_optimized', true));

        wp_send_json_success([
            'migrated' => $is_migrated,
            'optimized' => $is_optimized,
            's3Key' => get_post_meta($attachment_id, '_media_toolkit_key', true) ?: null,
            's3Url' => $this->get_dynamic_url($attachment_id),
            'originalSize' => (int) get_post_meta($attachment_id, '_media_toolkit_original_size', true),
            'optimizedSize' => (int) get_post_meta($attachment_id, '_media_toolkit_optimized_size', true),
            'bytesSaved' => (int) get_post_meta($attachment_id, '_media_toolkit_bytes_saved', true),
            'localExists' => file_exists(get_attached_file($attachment_id)),
        ]);
    }

    /**
     * Get dynamic URL for attachment using current CDN/storage settings
     */
    private function get_dynamic_url(int $attachment_id): ?string
    {
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($s3_key)) {
            return null;
        }
        
        return $this->settings->get_file_url($s3_key);
    }
}


