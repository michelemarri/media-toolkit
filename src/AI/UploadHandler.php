<?php
/**
 * AI Upload Handler - Schedules AI metadata generation on image upload
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\AI;

use Metodo\MediaToolkit\Core\Logger;

/**
 * Handles automatic AI metadata generation on upload
 */
final class UploadHandler
{
    private AIManager $ai_manager;
    private MetadataGenerator $metadata_generator;
    private ?Logger $logger;

    /** @var string Cron hook name */
    private const CRON_HOOK = 'media_toolkit_ai_generate_on_upload';

    public function __construct(
        AIManager $ai_manager,
        MetadataGenerator $metadata_generator,
        ?Logger $logger = null
    ) {
        $this->ai_manager = $ai_manager;
        $this->metadata_generator = $metadata_generator;
        $this->logger = $logger;

        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void
    {
        // Hook into attachment metadata generation (after thumbnails are created)
        add_filter('wp_generate_attachment_metadata', [$this, 'on_attachment_metadata'], 100, 2);

        // Register cron hook for async processing
        add_action(self::CRON_HOOK, [$this, 'process_scheduled_generation']);
    }

    /**
     * Called after attachment metadata is generated
     * Schedules AI metadata generation if enabled
     *
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @return array Unmodified metadata
     */
    public function on_attachment_metadata(array $metadata, int $attachment_id): array
    {
        // Check if feature is enabled
        if (!$this->ai_manager->isGenerateOnUploadEnabled()) {
            return $metadata;
        }

        // Verify this is an image
        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
            return $metadata;
        }

        // Check image dimensions
        if (!$this->is_image_large_enough($metadata)) {
            $this->logger?->info(
                'ai_upload',
                sprintf('Skipping AI generation for #%d: image too small', $attachment_id)
            );
            return $metadata;
        }

        // Check if metadata already exists (avoid regenerating on re-uploads)
        if ($this->has_existing_metadata($attachment_id)) {
            $this->logger?->info(
                'ai_upload',
                sprintf('Skipping AI generation for #%d: metadata already exists', $attachment_id)
            );
            return $metadata;
        }

        // Schedule async generation (5 seconds delay to ensure upload is complete)
        $scheduled = wp_schedule_single_event(
            time() + 5,
            self::CRON_HOOK,
            [$attachment_id]
        );

        if ($scheduled) {
            // Mark as pending
            update_post_meta($attachment_id, '_media_toolkit_ai_pending', time());
            
            $this->logger?->info(
                'ai_upload',
                sprintf('Scheduled AI metadata generation for #%d', $attachment_id)
            );
        }

        return $metadata;
    }

    /**
     * Process scheduled AI generation (cron callback)
     *
     * @param int $attachment_id Attachment ID
     */
    public function process_scheduled_generation(int $attachment_id): void
    {
        // Verify attachment still exists
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            $this->logger?->warning(
                'ai_upload',
                sprintf('Attachment #%d no longer exists, skipping AI generation', $attachment_id)
            );
            delete_post_meta($attachment_id, '_media_toolkit_ai_pending');
            return;
        }

        // Check if still pending (might have been manually generated)
        if (!get_post_meta($attachment_id, '_media_toolkit_ai_pending', true)) {
            return;
        }

        $this->logger?->info(
            'ai_upload',
            sprintf('Processing AI metadata generation for #%d', $attachment_id)
        );

        try {
            // Generate metadata
            $result = $this->metadata_generator->generate_single($attachment_id, false);

            // Remove pending flag
            delete_post_meta($attachment_id, '_media_toolkit_ai_pending');

            if ($result['success'] && !($result['skipped'] ?? false)) {
                $this->logger?->success(
                    'ai_upload',
                    sprintf(
                        'AI metadata generated for #%d using %s',
                        $attachment_id,
                        $result['metadata']['provider'] ?? 'AI'
                    )
                );
            } elseif ($result['skipped'] ?? false) {
                $this->logger?->info(
                    'ai_upload',
                    sprintf('AI generation skipped for #%d: all fields already filled', $attachment_id)
                );
            } else {
                $this->logger?->error(
                    'ai_upload',
                    sprintf('AI generation failed for #%d: %s', $attachment_id, $result['message'] ?? 'Unknown error')
                );
            }
        } catch (\Exception $e) {
            delete_post_meta($attachment_id, '_media_toolkit_ai_pending');
            
            $this->logger?->error(
                'ai_upload',
                sprintf('AI generation error for #%d: %s', $attachment_id, $e->getMessage())
            );
        }
    }

    /**
     * Check if image meets minimum size requirements
     *
     * @param array $metadata Attachment metadata
     * @return bool True if large enough
     */
    private function is_image_large_enough(array $metadata): bool
    {
        $min_size = $this->ai_manager->getMinImageSize();
        
        $width = $metadata['width'] ?? 0;
        $height = $metadata['height'] ?? 0;

        return $width >= $min_size && $height >= $min_size;
    }

    /**
     * Check if attachment already has meaningful metadata
     *
     * @param int $attachment_id Attachment ID
     * @return bool True if metadata exists
     */
    private function has_existing_metadata(int $attachment_id): bool
    {
        // Check if already AI generated
        if (get_post_meta($attachment_id, '_media_toolkit_ai_generated', true)) {
            return true;
        }

        // Check if alt text is filled (not just the filename)
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($alt)) {
            // Check it's not just the filename
            $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
            if ($alt !== $filename && !preg_match('/^[a-zA-Z0-9_-]+$/', $alt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an attachment has AI generation pending
     *
     * @param int $attachment_id Attachment ID
     * @return bool True if pending
     */
    public static function is_generation_pending(int $attachment_id): bool
    {
        return !empty(get_post_meta($attachment_id, '_media_toolkit_ai_pending', true));
    }
}

