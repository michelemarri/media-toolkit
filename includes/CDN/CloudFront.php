<?php
/**
 * CloudFront class for cache invalidation
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\CDN;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;

use function Metodo\MediaToolkit\media_toolkit;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

/**
 * Handles CloudFront cache invalidation with batching
 */
final class CloudFront
{
    private const BATCH_OPTION = 'media_toolkit_cf_invalidation_batch';
    private const BATCH_MAX_SIZE = 15; // Max paths per invalidation (AWS allows up to 15 for free)
    private const BATCH_INTERVAL = 300; // 5 minutes between batch processing

    private Settings $settings;
    private Logger $logger;
    private ?CloudFrontClient $client = null;

    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Get or create CloudFront client
     */
    private function get_client(): ?CloudFrontClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = $this->settings->get_config();
        
        if ($config === null || !$config->isValid() || empty($config->cloudfrontDistributionId)) {
            return null;
        }

        $this->client = new CloudFrontClient([
            'version' => 'latest',
            'region' => $config->region,
            'credentials' => [
                'key' => $config->accessKey,
                'secret' => $config->secretKey,
            ],
        ]);

        return $this->client;
    }

    /**
     * Check if CloudFront invalidation is available
     */
    public function is_available(): bool
    {
        $config = $this->settings->get_config();
        return $config !== null && !empty($config->cloudfrontDistributionId);
    }

    /**
     * Queue paths for invalidation (batched)
     */
    public function queue_invalidation(array $paths): void
    {
        if (empty($paths) || !$this->is_available()) {
            return;
        }

        $batch = get_option(self::BATCH_OPTION, [
            'paths' => [],
            'queued_at' => 0,
        ]);

        // Add new paths (dedupe)
        $batch['paths'] = array_unique(array_merge($batch['paths'], $paths));
        
        // Set queue time if this is a new batch
        if ($batch['queued_at'] === 0) {
            $batch['queued_at'] = time();
        }

        update_option(self::BATCH_OPTION, $batch);

        // Schedule batch processing if not already scheduled
        if (!wp_next_scheduled('media_toolkit_batch_invalidation')) {
            wp_schedule_single_event(time() + self::BATCH_INTERVAL, 'media_toolkit_batch_invalidation');
        }

        $this->logger->info(
            'invalidation',
            'Queued ' . count($paths) . ' path(s) for CloudFront invalidation',
            null,
            null,
            ['paths' => $paths]
        );
    }

    /**
     * Queue invalidation for an attachment and all its sizes
     */
    public function queue_attachment_invalidation(int $attachment_id): void
    {
        $paths = $this->get_attachment_paths($attachment_id);
        
        if (!empty($paths)) {
            $this->queue_invalidation($paths);
        }
    }

    /**
     * Get all S3 paths for an attachment (including thumbnails)
     */
    private function get_attachment_paths(int $attachment_id): array
    {
        $paths = [];
        
        // Main file
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($s3_key)) {
            $paths[] = '/' . ltrim($s3_key, '/');
        }

        // Thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes']) && !empty($s3_key)) {
            $base_dir = dirname($s3_key);
            foreach ($metadata['sizes'] as $size) {
                $paths[] = '/' . $base_dir . '/' . $size['file'];
            }
        }

        return $paths;
    }

    /**
     * Process batched invalidations (called by cron)
     */
    public function process_batch_invalidation(): void
    {
        $batch = get_option(self::BATCH_OPTION, ['paths' => [], 'queued_at' => 0]);
        
        if (empty($batch['paths'])) {
            delete_option(self::BATCH_OPTION);
            return;
        }

        $paths = $batch['paths'];
        $processed = 0;

        // Process in chunks of BATCH_MAX_SIZE
        $chunks = array_chunk($paths, self::BATCH_MAX_SIZE);
        
        foreach ($chunks as $chunk) {
            $result = $this->invalidate($chunk);
            
            if ($result['success']) {
                $processed += count($chunk);
            } else {
                // Stop on error, leave remaining in queue
                $remaining = array_slice($paths, $processed);
                update_option(self::BATCH_OPTION, [
                    'paths' => $remaining,
                    'queued_at' => time(),
                ]);
                
                // Reschedule for later
                wp_schedule_single_event(time() + self::BATCH_INTERVAL, 'media_toolkit_batch_invalidation');
                return;
            }
        }

        // All processed successfully
        delete_option(self::BATCH_OPTION);
        
        $this->logger->success(
            'invalidation',
            "Processed batch invalidation for {$processed} paths"
        );
    }

    /**
     * Execute immediate invalidation
     */
    public function invalidate(array $paths): array
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null || empty($config->cloudfrontDistributionId)) {
            return [
                'success' => false,
                'message' => 'CloudFront not configured',
            ];
        }

        if (empty($paths)) {
            return [
                'success' => true,
                'message' => 'No paths to invalidate',
            ];
        }

        // Ensure paths start with /
        $paths = array_map(fn($p) => '/' . ltrim($p, '/'), $paths);

        try {
            $result = $client->createInvalidation([
                'DistributionId' => $config->cloudfrontDistributionId,
                'InvalidationBatch' => [
                    'CallerReference' => 'media-toolkit-' . time() . '-' . wp_generate_password(8, false),
                    'Paths' => [
                        'Quantity' => count($paths),
                        'Items' => $paths,
                    ],
                ],
            ]);

            $invalidation_id = $result['Invalidation']['Id'] ?? 'unknown';
            
            $this->logger->success(
                'invalidation',
                "Created CloudFront invalidation: {$invalidation_id}",
                null,
                null,
                ['paths' => $paths, 'invalidation_id' => $invalidation_id]
            );

            // Record in history
            $plugin = media_toolkit();
            $history = $plugin->get_history();
            $history?->record(
                HistoryAction::INVALIDATION,
                null,
                null,
                null,
                null,
                ['paths' => $paths, 'invalidation_id' => $invalidation_id]
            );

            return [
                'success' => true,
                'message' => "Invalidation created: {$invalidation_id}",
                'invalidation_id' => $invalidation_id,
            ];

        } catch (AwsException $e) {
            $error_message = $e->getAwsErrorMessage() ?? $e->getMessage();
            
            $this->logger->error(
                'invalidation',
                "CloudFront invalidation failed: {$error_message}",
                null,
                null,
                ['paths' => $paths, 'error' => $e->getAwsErrorCode()]
            );

            return [
                'success' => false,
                'message' => $error_message,
            ];
        }
    }

    /**
     * Invalidate with wildcard (for thumbnails)
     */
    public function invalidate_with_wildcard(string $base_path, string $filename_pattern): array
    {
        // e.g., /wp-content/uploads/2025/11/image-* for all sizes
        $wildcard_path = '/' . rtrim($base_path, '/') . '/' . $filename_pattern . '*';
        
        return $this->invalidate([$wildcard_path]);
    }

    /**
     * Get pending invalidation count
     */
    public function get_pending_count(): int
    {
        $batch = get_option(self::BATCH_OPTION, ['paths' => []]);
        return count($batch['paths'] ?? []);
    }

    /**
     * Clear pending invalidations
     */
    public function clear_pending(): void
    {
        delete_option(self::BATCH_OPTION);
        wp_clear_scheduled_hook('media_toolkit_batch_invalidation');
    }

    /**
     * Force process pending invalidations now
     */
    public function force_process(): array
    {
        $this->process_batch_invalidation();
        
        return [
            'success' => true,
            'message' => 'Batch invalidation processed',
        ];
    }
}

