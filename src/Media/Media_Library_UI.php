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
use Metodo\MediaToolkit\Database\OptimizationTable;

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
        add_filter('manage_media_columns', [$this, 'add_cloud_column']);
        add_action('manage_media_custom_column', [$this, 'render_cloud_column'], 10, 2);
        add_filter('manage_upload_sortable_columns', [$this, 'make_cloud_column_sortable']);
        add_action('pre_get_posts', [$this, 'sort_by_cloud_status']);

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
        add_action('wp_ajax_media_toolkit_download_from_cloud', [$this, 'ajax_download_from_cloud']);
        add_action('wp_ajax_media_toolkit_get_attachment_cloud_info', [$this, 'ajax_get_attachment_cloud_info']);
        add_action('wp_ajax_media_toolkit_optimize_single', [$this, 'ajax_optimize_single']);

        // Attachment details modal (Backbone/JS)
        add_filter('wp_prepare_attachment_for_js', [$this, 'add_cloud_data_for_js'], 20, 3);
        
        // Add inline script for attachment details
        add_action('admin_footer-upload.php', [$this, 'render_attachment_details_template']);
        add_action('admin_footer-post.php', [$this, 'render_attachment_details_template']);
    }

    /**
     * Add cloud column to Media Library list
     */
    public function add_cloud_column(array $columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Insert after title
            if ($key === 'title') {
                $new_columns['cloud_status'] = '<span class="dashicons dashicons-cloud" title="Cloud Status"></span>';
            }
        }
        
        return $new_columns;
    }

    /**
     * Render cloud column content
     */
    public function render_cloud_column(string $column_name, int $post_id): void
    {
        if ($column_name !== 'cloud_status') {
            return;
        }

        $is_migrated = !empty(get_post_meta($post_id, '_media_toolkit_migrated', true));
        $cloud_url = $this->get_dynamic_url($post_id);
        
        // Get optimization data from table (source of truth)
        $optimization_record = OptimizationTable::get_by_attachment($post_id);
        $is_optimized = ($optimization_record['status'] ?? '') === 'optimized';
        $bytes_saved = (int) ($optimization_record['bytes_saved'] ?? 0);

        if ($is_migrated) {
            // Green checkmark for synced
            echo '<span class="dashicons dashicons-yes-alt" style="color: #16a34a;" title="' . esc_attr__('On Cloud', 'media-toolkit') . '"></span>';
            
            // Show savings if optimized (total = main + thumbnails)
            if ($is_optimized && $bytes_saved > 0) {
                echo '<br><small style="color: #16a34a;">-' . esc_html(size_format($bytes_saved)) . '</small>';
            }
            
            // Link to CDN
            if (!empty($cloud_url)) {
                echo sprintf(
                    ' <a href="%s" target="_blank" style="color: #6b7280; text-decoration: none;" title="%s"><span class="dashicons dashicons-external"></span></a>',
                    esc_url($cloud_url),
                    esc_attr__('View on CDN', 'media-toolkit')
                );
            }
        } else {
            // Gray dash for local only
            echo '<span class="dashicons dashicons-minus" style="color: #9ca3af;" title="' . esc_attr__('Local only', 'media-toolkit') . '"></span>';
        }
    }

    /**
     * Make cloud column sortable
     */
    public function make_cloud_column_sortable(array $columns): array
    {
        $columns['cloud_status'] = 'cloud_status';
        return $columns;
    }

    /**
     * Sort by cloud status
     */
    public function sort_by_cloud_status(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('orderby') !== 'cloud_status') {
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
            return add_query_arg('mt_bulk_error', 'not_configured', $redirect_to);
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
            'mt_bulk_action' => $action,
            'mt_bulk_success' => $success,
            'mt_bulk_failed' => $failed,
        ], $redirect_to);
    }

    /**
     * Show bulk action notices
     */
    public function show_bulk_action_notices(): void
    {
        if (!isset($_GET['mt_bulk_action'])) {
            return;
        }

        $action = sanitize_text_field($_GET['mt_bulk_action']);
        $success = (int) ($_GET['mt_bulk_success'] ?? 0);
        $failed = (int) ($_GET['mt_bulk_failed'] ?? 0);

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

        if (isset($_GET['mt_bulk_error']) && $_GET['mt_bulk_error'] === 'not_configured') {
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
                $actions['mt_reupload'] = sprintf(
                    '<a href="#" class="mt-action-reupload" data-id="%d" data-nonce="%s">%s</a>',
                    $post->ID,
                    wp_create_nonce('media_toolkit_action_' . $post->ID),
                    __('Re-upload to Cloud', 'media-toolkit')
                );
                
                // Check if local file is missing
                $file = get_attached_file($post->ID);
                if (!file_exists($file)) {
                    $actions['mt_download'] = sprintf(
                        '<a href="#" class="mt-action-download" data-id="%d" data-nonce="%s">%s</a>',
                        $post->ID,
                        wp_create_nonce('media_toolkit_action_' . $post->ID),
                        __('Download from Cloud', 'media-toolkit')
                    );
                }
            } else {
                $actions['mt_upload'] = sprintf(
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
     * Upload attachment to cloud storage
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
     * Download attachment from cloud storage
     */
    private function download_attachment(int $attachment_id): bool
    {
        if ($this->storage === null) {
            return false;
        }

        $storage_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($storage_key)) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        
        // Create directory if needed
        $dir = dirname($file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $result = $this->storage->download_file($storage_key, $file, $attachment_id);
        
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

        wp_localize_script('media-toolkit-media-library', 'mediaToolkitMedia', [
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
                'optimizing' => __('Optimizing...', 'media-toolkit'),
                'optimized' => __('Optimized!', 'media-toolkit'),
                'optimizeError' => __('Failed to optimize image', 'media-toolkit'),
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
     * Add cloud storage data for JavaScript (attachment modal)
     */
    public function add_cloud_data_for_js(array $response, \WP_Post $attachment, array $meta): array
    {
        $attachment_id = $attachment->ID;
        
        $is_migrated = !empty(get_post_meta($attachment_id, '_media_toolkit_migrated', true));
        
        $response['cloudStorage'] = [
            'migrated' => $is_migrated,
            'storageKey' => get_post_meta($attachment_id, '_media_toolkit_key', true) ?: null,
            'storageUrl' => $this->get_dynamic_url($attachment_id),
            'localExists' => file_exists(get_attached_file($attachment_id)),
            'nonce' => wp_create_nonce('media_toolkit_action_' . $attachment_id),
        ];

        // Add optimization data for images
        if (str_starts_with($attachment->post_mime_type, 'image/')) {
            $optimization_record = OptimizationTable::get_by_attachment($attachment_id);
            $optimizer = media_toolkit()->get_image_optimizer();
            $settings_json = $optimization_record['settings_json'] ?? [];
            
            $response['optimization'] = [
                'available' => $optimizer !== null,
                'status' => $optimization_record['status'] ?? 'not_processed',
                // Total asset sizes (main + thumbnails)
                'originalSize' => (int) ($optimization_record['original_size'] ?? 0),
                'optimizedSize' => (int) ($optimization_record['optimized_size'] ?? 0),
                'bytesSaved' => (int) ($optimization_record['bytes_saved'] ?? 0),
                'percentSaved' => (float) ($optimization_record['percent_saved'] ?? 0),
                'optimizedAt' => $optimization_record['optimized_at'] ?? null,
                'errorMessage' => $optimization_record['error_message'] ?? null,
                // Main image breakdown
                'mainOriginalSize' => (int) ($settings_json['main_original_size'] ?? 0),
                'mainOptimizedSize' => (int) ($settings_json['main_optimized_size'] ?? 0),
                'mainBytesSaved' => (int) ($settings_json['main_bytes_saved'] ?? 0),
                // Thumbnails breakdown
                'thumbnailsSaved' => $this->get_thumbnails_optimization_info($attachment_id),
            ];
        }

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
     * Get thumbnails optimization info from the optimization table settings_json
     */
    private function get_thumbnails_optimization_info(int $attachment_id): array
    {
        $optimization_record = OptimizationTable::get_by_attachment($attachment_id);
        $settings_json = $optimization_record['settings_json'] ?? [];
        
        // Get data from settings_json (stored during optimization)
        $thumbnails_count = (int) ($settings_json['thumbnails_count'] ?? 0);
        $thumbnails_bytes_saved = (int) ($settings_json['thumbnails_bytes_saved'] ?? 0);
        $thumbnails_original_size = (int) ($settings_json['thumbnails_original_size'] ?? 0);
        $thumbnails_optimized_size = (int) ($settings_json['thumbnails_optimized_size'] ?? 0);
        
        // If no data in table, count thumbnails from metadata
        if ($thumbnails_count === 0) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            $thumbnails_count = !empty($metadata['sizes']) ? count($metadata['sizes']) : 0;
        }
        
        return [
            'count' => $thumbnails_count,
            'bytesSaved' => $thumbnails_bytes_saved,
            'originalSize' => $thumbnails_original_size,
            'optimizedSize' => $thumbnails_optimized_size,
        ];
    }

    /**
     * Render attachment details template for Backbone
     */
    public function render_attachment_details_template(): void
    {
        ?>
        <script type="text/html" id="tmpl-mt-offload-details">
            <# if (data.cloudStorage) { #>
            <div class="settings mt-offload-section">
                <h4 style="margin-top:0;"><span class="dashicons dashicons-cloud"></span> <?php _e('Cloud Storage', 'media-toolkit'); ?></h4>
                
                <# if (data.cloudStorage.migrated) { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="✓ <?php _e('On Cloud', 'media-toolkit'); ?><# if (data.cloudStorage.optimized) { #> · <?php _e('Optimized', 'media-toolkit'); ?><# } #>" readonly style="color: #16a34a;">
                    </label>
                    
                    <# if (data.cloudStorage.bytesSaved > 0) { #>
                    <label class="setting">
                        <span class="name"><?php _e('Savings', 'media-toolkit'); ?></span>
                        <# var percent = Math.round((data.cloudStorage.bytesSaved / data.cloudStorage.originalSize) * 100); #>
                        <input type="text" value="-{{ mediaToolkitMedia.formatBytes(data.cloudStorage.bytesSaved) }} ({{ percent }}%)" readonly style="color: #16a34a;">
                    </label>
                    <# } #>
                    
                    <label class="setting">
                        <span class="name"><?php _e('Local', 'media-toolkit'); ?></span>
                        <# if (data.cloudStorage.localExists) { #>
                            <input type="text" value="<?php _e('Available', 'media-toolkit'); ?>" readonly style="color: #16a34a;">
                        <# } else { #>
                            <input type="text" value="<?php _e('Removed', 'media-toolkit'); ?>" readonly style="color: #dc2626;">
                        <# } #>
                    </label>
                    
                    <label class="setting">
                        <span class="name">&nbsp;</span>
                        <# if (data.cloudStorage.storageUrl) { #>
                        <a href="{{ data.cloudStorage.storageUrl }}" target="_blank" class="button"><?php _e('View on CDN', 'media-toolkit'); ?></a>
                        <# } #>
                        <button type="button" class="button mt-btn-reupload" data-id="{{ data.id }}"><?php _e('Re-sync', 'media-toolkit'); ?></button>
                        <# if (!data.cloudStorage.localExists) { #>
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
            
            <# if (data.optimization) { #>
            <div class="settings mt-optimization-section">
                <h4 style="margin-top:0;"><span class="dashicons dashicons-performance"></span> <?php _e('Image Optimization', 'media-toolkit'); ?></h4>
                
                <# if (data.optimization.available) { #>
                    <# if (data.optimization.status === 'optimized') { #>
                        <#
                        // Data is now TOTAL (main + thumbnails) stored in optimization table
                        var totalBytesSaved = data.optimization.bytesSaved || 0;
                        var totalPercent = data.optimization.percentSaved || 0;
                        var mainBytesSaved = data.optimization.mainBytesSaved || 0;
                        var thumbBytesSaved = (data.optimization.thumbnailsSaved && data.optimization.thumbnailsSaved.bytesSaved) ? data.optimization.thumbnailsSaved.bytesSaved : 0;
                        #>
                        <label class="setting">
                            <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                            <input type="text" value="✓ <?php _e('Optimized', 'media-toolkit'); ?>" readonly style="color: #16a34a;">
                        </label>
                        
                        <# if (totalBytesSaved > 0) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Total Savings', 'media-toolkit'); ?></span>
                            <input type="text" value="-{{ mediaToolkitMedia.formatBytes(totalBytesSaved) }} ({{ totalPercent.toFixed ? totalPercent.toFixed(1) : totalPercent }}%)" readonly style="color: #16a34a; font-weight: 600;">
                        </label>
                        <# } #>
                        
                        <# if (mainBytesSaved > 0) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Main Image', 'media-toolkit'); ?></span>
                            <input type="text" value="{{ mediaToolkitMedia.formatBytes(data.optimization.mainOriginalSize || 0) }} → {{ mediaToolkitMedia.formatBytes(data.optimization.mainOptimizedSize || 0) }} (-{{ mediaToolkitMedia.formatBytes(mainBytesSaved) }})" readonly>
                        </label>
                        <# } #>
                        
                        <# if (data.optimization.thumbnailsSaved && data.optimization.thumbnailsSaved.count > 0) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Thumbnails', 'media-toolkit'); ?></span>
                            <# if (thumbBytesSaved > 0) { #>
                            <input type="text" value="{{ data.optimization.thumbnailsSaved.count }} <?php _e('sizes', 'media-toolkit'); ?> (-{{ mediaToolkitMedia.formatBytes(thumbBytesSaved) }})" readonly>
                            <# } else { #>
                            <input type="text" value="{{ data.optimization.thumbnailsSaved.count }} <?php _e('sizes', 'media-toolkit'); ?>" readonly>
                            <# } #>
                        </label>
                        <# } #>
                        
                        <label class="setting">
                            <span class="name">&nbsp;</span>
                            <button type="button" class="button mt-btn-reoptimize" data-id="{{ data.id }}"><?php _e('Re-optimize', 'media-toolkit'); ?></button>
                        </label>
                        
                    <# } else if (data.optimization.status === 'failed') { #>
                        <label class="setting">
                            <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                            <input type="text" value="✗ <?php _e('Failed', 'media-toolkit'); ?>" readonly style="color: #dc2626;">
                        </label>
                        
                        <# if (data.optimization.errorMessage) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Error', 'media-toolkit'); ?></span>
                            <input type="text" value="{{ data.optimization.errorMessage }}" readonly style="color: #dc2626;">
                        </label>
                        <# } #>
                        
                        <label class="setting">
                            <span class="name">&nbsp;</span>
                            <button type="button" class="button button-primary mt-btn-optimize" data-id="{{ data.id }}"><?php _e('Retry Optimization', 'media-toolkit'); ?></button>
                        </label>
                        
                    <# } else if (data.optimization.status === 'skipped') { #>
                        <label class="setting">
                            <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                            <input type="text" value="⏭ <?php _e('Skipped', 'media-toolkit'); ?>" readonly style="color: #f59e0b;">
                        </label>
                        
                        <# if (data.optimization.errorMessage) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Reason', 'media-toolkit'); ?></span>
                            <input type="text" value="{{ data.optimization.errorMessage }}" readonly style="color: #9ca3af;">
                        </label>
                        <# } #>
                        
                    <# } else { #>
                        <label class="setting">
                            <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                            <input type="text" value="<?php _e('Not optimized', 'media-toolkit'); ?>" readonly style="color: #9ca3af;">
                        </label>
                        
                        <# if (data.optimization.thumbnailsSaved && data.optimization.thumbnailsSaved.count > 0) { #>
                        <label class="setting">
                            <span class="name"><?php _e('Thumbnails', 'media-toolkit'); ?></span>
                            <input type="text" value="{{ data.optimization.thumbnailsSaved.count }} <?php _e('sizes will be optimized', 'media-toolkit'); ?>" readonly style="color: #9ca3af;">
                        </label>
                        <# } #>
                        
                        <label class="setting">
                            <span class="name">&nbsp;</span>
                            <button type="button" class="button button-primary mt-btn-optimize" data-id="{{ data.id }}"><?php _e('Optimize Now', 'media-toolkit'); ?></button>
                        </label>
                    <# } #>
                <# } else { #>
                    <label class="setting">
                        <span class="name"><?php _e('Status', 'media-toolkit'); ?></span>
                        <input type="text" value="<?php _e('Optimizer not available', 'media-toolkit'); ?>" readonly style="color: #9ca3af;">
                    </label>
                <# } #>
            </div>
            <# } #>
            
            <# if (data.aiMetadata) { #>
            <div class="settings mt-ai-section">
                <h4 style="margin-top:0;"><span class="dashicons dashicons-format-image"></span> <?php _e('AI Metadata', 'media-toolkit'); ?></h4>
                
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
     * AJAX: Upload single file to cloud storage (new upload)
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
                'storageKey' => $result['s3_key'] ?? null,
                'storageUrl' => $result['s3_url'] ?? null,
                'migrated' => true,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Re-upload file to cloud storage
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
                'storageKey' => $result['s3_key'] ?? null,
                'storageUrl' => $result['s3_url'] ?? null,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Download file from cloud storage
     */
    public function ajax_download_from_cloud(): void
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
     * AJAX: Get attachment cloud storage info
     */
    public function ajax_get_attachment_cloud_info(): void
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
        
        // Get optimization data from table (source of truth)
        $optimization_record = OptimizationTable::get_by_attachment($attachment_id);
        $is_optimized = ($optimization_record['status'] ?? '') === 'optimized';

        wp_send_json_success([
            'migrated' => $is_migrated,
            'optimized' => $is_optimized,
            'storageKey' => get_post_meta($attachment_id, '_media_toolkit_key', true) ?: null,
            'storageUrl' => $this->get_dynamic_url($attachment_id),
            'originalSize' => (int) ($optimization_record['original_size'] ?? 0),
            'optimizedSize' => (int) ($optimization_record['optimized_size'] ?? 0),
            'bytesSaved' => (int) ($optimization_record['bytes_saved'] ?? 0),
            'localExists' => file_exists(get_attached_file($attachment_id)),
        ]);
    }

    /**
     * AJAX: Optimize single attachment
     */
    public function ajax_optimize_single(): void
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

        // Check if it's an image
        $mime_type = get_post_mime_type($attachment_id);
        if (strpos($mime_type, 'image/') !== 0) {
            wp_send_json_error(['message' => 'Not an image']);
            return;
        }

        $optimizer = media_toolkit()->get_image_optimizer();
        
        if ($optimizer === null) {
            wp_send_json_error(['message' => 'Optimizer not available']);
            return;
        }

        $result = $optimizer->optimize_attachment($attachment_id);
        
        if ($result['success']) {
            // Get updated optimization data
            $optimization_record = OptimizationTable::get_by_attachment($attachment_id);
            
            // Get fresh data from table (includes breakdown in settings_json)
            $fresh_record = OptimizationTable::get_by_attachment($attachment_id);
            $settings_json = $fresh_record['settings_json'] ?? [];
            
            wp_send_json_success([
                'message' => sprintf(
                    __('Asset optimized! Total saved: %s (%s%%)', 'media-toolkit'),
                    size_format($result['bytes_saved'] ?? 0),
                    $result['percent_saved'] ?? 0
                ),
                'optimization' => [
                    'available' => true,
                    'status' => 'optimized',
                    // Total asset sizes (main + thumbnails)
                    'originalSize' => (int) ($result['original_size'] ?? 0),
                    'optimizedSize' => (int) ($result['optimized_size'] ?? 0),
                    'bytesSaved' => (int) ($result['bytes_saved'] ?? 0),
                    'percentSaved' => (float) ($result['percent_saved'] ?? 0),
                    'optimizedAt' => $fresh_record['optimized_at'] ?? null,
                    // Main image breakdown
                    'mainOriginalSize' => (int) ($settings_json['main_original_size'] ?? 0),
                    'mainOptimizedSize' => (int) ($settings_json['main_optimized_size'] ?? 0),
                    'mainBytesSaved' => (int) ($result['main_bytes_saved'] ?? $settings_json['main_bytes_saved'] ?? 0),
                    // Thumbnails breakdown
                    'thumbnailsSaved' => $this->get_thumbnails_optimization_info($attachment_id),
                ],
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error'] ?? __('Failed to optimize image', 'media-toolkit'),
            ]);
        }
    }

    /**
     * Get dynamic URL for attachment using current CDN/storage settings
     */
    private function get_dynamic_url(int $attachment_id): ?string
    {
        $storage_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        
        if (empty($storage_key)) {
            return null;
        }
        
        return $this->settings->get_file_url($storage_key);
    }
}
