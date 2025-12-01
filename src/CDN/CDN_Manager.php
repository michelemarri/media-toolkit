<?php
/**
 * CDN Manager class for cache invalidation
 *
 * Supports CloudFront, Cloudflare, and other CDNs
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\CDN;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\S3\S3Config;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

/**
 * Handles CDN cache invalidation with batching
 */
final class CDN_Manager
{
    private const BATCH_OPTION = 'media_toolkit_cdn_invalidation_batch';
    private const BATCH_MAX_SIZE = 15;
    private const BATCH_INTERVAL = 300; // 5 minutes

    private Settings $settings;
    private Logger $logger;
    private ?CloudFrontClient $cloudfront_client = null;

    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Check if cache purging is available
     */
    public function is_available(): bool
    {
        $config = $this->settings->get_config();
        
        if ($config === null) {
            return false;
        }

        return match ($config->cdnProvider) {
            CDNProvider::CLOUDFRONT => !empty($config->cloudfrontDistributionId),
            CDNProvider::CLOUDFLARE => !empty($config->cloudflareZoneId) && !empty($config->cloudflareApiToken),
            default => false,
        };
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
        
        if ($batch['queued_at'] === 0) {
            $batch['queued_at'] = time();
        }

        update_option(self::BATCH_OPTION, $batch);

        if (!wp_next_scheduled('media_toolkit_batch_invalidation')) {
            wp_schedule_single_event(time() + self::BATCH_INTERVAL, 'media_toolkit_batch_invalidation');
        }

        $config = $this->settings->get_config();
        $this->logger->info(
            'invalidation',
            'Queued ' . count($paths) . ' path(s) for ' . ucfirst($config->cdnProvider->value) . ' cache purge',
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
     * Get all paths for an attachment (including thumbnails)
     */
    private function get_attachment_paths(int $attachment_id): array
    {
        $paths = [];
        
        $s3_key = get_post_meta($attachment_id, '_media_toolkit_key', true);
        if (!empty($s3_key)) {
            $paths[] = '/' . ltrim($s3_key, '/');
        }

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

        $chunks = array_chunk($paths, self::BATCH_MAX_SIZE);
        
        foreach ($chunks as $chunk) {
            $result = $this->purge($chunk);
            
            if ($result['success']) {
                $processed += count($chunk);
            } else {
                $remaining = array_slice($paths, $processed);
                update_option(self::BATCH_OPTION, [
                    'paths' => $remaining,
                    'queued_at' => time(),
                ]);
                
                wp_schedule_single_event(time() + self::BATCH_INTERVAL, 'media_toolkit_batch_invalidation');
                return;
            }
        }

        delete_option(self::BATCH_OPTION);
        
        $this->logger->success(
            'invalidation',
            "Processed batch cache purge for {$processed} paths"
        );
    }

    /**
     * Execute cache purge based on CDN provider
     */
    public function purge(array $paths): array
    {
        $config = $this->settings->get_config();
        
        if ($config === null) {
            return ['success' => false, 'message' => 'CDN not configured'];
        }

        return match ($config->cdnProvider) {
            CDNProvider::CLOUDFRONT => $this->purge_cloudfront($paths, $config),
            CDNProvider::CLOUDFLARE => $this->purge_cloudflare($paths, $config),
            default => ['success' => false, 'message' => 'No CDN cache purging configured'],
        };
    }

    /**
     * Purge CloudFront cache
     */
    private function purge_cloudfront(array $paths, S3Config $config): array
    {
        if (empty($config->cloudfrontDistributionId)) {
            return ['success' => false, 'message' => 'CloudFront Distribution ID not configured'];
        }

        $client = $this->get_cloudfront_client($config);
        
        if ($client === null) {
            return ['success' => false, 'message' => 'Failed to create CloudFront client'];
        }

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

            return ['success' => false, 'message' => $error_message];
        }
    }

    /**
     * Purge Cloudflare cache
     */
    private function purge_cloudflare(array $paths, S3Config $config): array
    {
        if (empty($config->cloudflareZoneId) || empty($config->cloudflareApiToken)) {
            return ['success' => false, 'message' => 'Cloudflare Zone ID or API Token not configured'];
        }

        // Convert paths to full URLs
        $urls = [];
        foreach ($paths as $path) {
            $url = rtrim($config->cdnUrl, '/') . '/' . ltrim($path, '/');
            $urls[] = $url;
        }

        $api_url = sprintf(
            'https://api.cloudflare.com/client/v4/zones/%s/purge_cache',
            $config->cloudflareZoneId
        );

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $config->cloudflareApiToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['files' => $urls]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error(
                'invalidation',
                'Cloudflare cache purge failed: ' . $response->get_error_message(),
                null,
                null,
                ['urls' => $urls]
            );

            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['success'])) {
            $this->logger->success(
                'invalidation',
                'Cloudflare cache purged for ' . count($urls) . ' URLs',
                null,
                null,
                ['urls' => $urls]
            );

            return [
                'success' => true,
                'message' => 'Cache purged for ' . count($urls) . ' URLs',
            ];
        }

        $error_message = $body['errors'][0]['message'] ?? 'Unknown error';
        
        $this->logger->error(
            'invalidation',
            "Cloudflare cache purge failed: {$error_message}",
            null,
            null,
            ['urls' => $urls, 'response' => $body]
        );

        return ['success' => false, 'message' => $error_message];
    }

    /**
     * Get CloudFront client
     */
    private function get_cloudfront_client(S3Config $config): ?CloudFrontClient
    {
        if ($this->cloudfront_client !== null) {
            return $this->cloudfront_client;
        }

        $this->cloudfront_client = new CloudFrontClient([
            'version' => 'latest',
            'region' => $config->region,
            'credentials' => [
                'key' => $config->accessKey,
                'secret' => $config->secretKey,
            ],
        ]);

        return $this->cloudfront_client;
    }

    /**
     * Test CDN connection
     */
    public function test_connection(): array
    {
        $config = $this->settings->get_config();
        
        if ($config === null || !$config->hasCDN()) {
            return [
                'success' => false,
                'connected' => false,
                'message' => 'CDN not configured',
            ];
        }

        // Test CDN URL is reachable
        $response = wp_remote_head($config->cdnUrl, [
            'timeout' => 10,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'connected' => false,
                'message' => 'CDN URL not reachable: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 500) {
            return [
                'success' => true,
                'connected' => true,
                'message' => 'CDN URL is reachable (HTTP ' . $status_code . ')',
                'provider' => $config->cdnProvider->value,
            ];
        }

        return [
            'success' => false,
            'connected' => false,
            'message' => 'CDN returned HTTP ' . $status_code,
        ];
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

