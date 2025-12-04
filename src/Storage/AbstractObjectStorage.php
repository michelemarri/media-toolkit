<?php
/**
 * Abstract Object Storage - Base class for S3-compatible providers
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Error\Error_Handler;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Sts\StsClient;

/**
 * Abstract base class for S3-compatible storage providers
 * Implements common functionality using AWS SDK
 */
abstract class AbstractObjectStorage implements StorageInterface
{
    protected ?S3Client $client = null;
    protected Settings $settings;
    protected Error_Handler $error_handler;
    protected Logger $logger;
    protected StorageConfig $config;

    public function __construct(
        Settings $settings,
        Error_Handler $error_handler,
        Logger $logger,
        StorageConfig $config
    ) {
        $this->settings = $settings;
        $this->error_handler = $error_handler;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Get or create S3-compatible client
     */
    public function get_client(): ?S3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!$this->config->isValid()) {
            return null;
        }

        $clientConfig = [
            'version' => 'latest',
            'region' => $this->config->getEffectiveRegion(),
            'credentials' => [
                'key' => $this->config->accessKey,
                'secret' => $this->config->secretKey,
            ],
        ];

        // Add custom endpoint for non-AWS providers
        $endpoint = $this->config->getEndpoint();
        if ($endpoint !== null) {
            $clientConfig['endpoint'] = $endpoint;
            $clientConfig['use_path_style_endpoint'] = $this->usePathStyleEndpoint();
        }

        $this->client = new S3Client($clientConfig);

        return $this->client;
    }

    /**
     * Whether to use path-style endpoint (bucket in path vs subdomain)
     * Override in subclasses if needed
     */
    protected function usePathStyleEndpoint(): bool
    {
        return false;
    }

    /**
     * Reset client (needed when settings change)
     */
    public function reset_client(): void
    {
        $this->client = null;
    }

    /**
     * Upload a file to storage
     */
    public function upload_file(
        string $file_path,
        ?int $attachment_id = null,
        ?string $custom_key = null
    ): UploadResult {
        $client = $this->get_client();

        if ($client === null) {
            return UploadResult::failure('Storage client not configured');
        }

        if (!file_exists($file_path)) {
            return UploadResult::failure("File not found: {$file_path}");
        }

        $key = $custom_key ?? $this->generate_key($file_path);
        $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
        $filename = basename($file_path);
        $content_disposition = $this->settings->get_content_disposition_for_mime($mime_type, $filename);

        try {
            $put_params = [
                'Bucket' => $this->config->bucket,
                'Key' => $key,
                'SourceFile' => $file_path,
                'ContentType' => $mime_type,
                'CacheControl' => $this->settings->get_cache_control_header(),
            ];

            // Add ACL if provider supports it
            if ($this->supportsPublicAcl()) {
                $put_params['ACL'] = 'public-read';
            }

            // Add Content-Disposition if not inline
            if ($content_disposition !== 'inline') {
                $put_params['ContentDisposition'] = $content_disposition;
            }

            $this->error_handler->execute_with_retry(
                fn() => $client->putObject($put_params),
                'upload',
                $attachment_id,
                $file_path
            );

            $url = $this->get_file_url($key);

            return UploadResult::success($key, $url, $this->get_provider());

        } catch (\Exception $e) {
            if ($attachment_id !== null) {
                $this->error_handler->record_failed_operation(
                    'upload',
                    $attachment_id,
                    $file_path,
                    $e instanceof AwsException ? ($e->getAwsErrorCode() ?? 'Unknown') : 'Exception',
                    $e->getMessage()
                );
            }

            return UploadResult::failure(
                $this->error_handler->get_friendly_error_message($e),
                $key
            );
        }
    }

    /**
     * Whether this provider supports public-read ACL
     * Override in subclasses (R2 doesn't support ACL)
     */
    protected function supportsPublicAcl(): bool
    {
        return true;
    }

    /**
     * Delete a file from storage
     */
    public function delete_file(string $key, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();

        if ($client === null) {
            return false;
        }

        try {
            $this->error_handler->execute_with_retry(
                fn() => $client->deleteObject([
                    'Bucket' => $this->config->bucket,
                    'Key' => $key,
                ]),
                'delete',
                $attachment_id,
                $key
            );

            return true;

        } catch (\Exception $e) {
            if ($attachment_id !== null) {
                $this->error_handler->record_failed_operation(
                    'delete',
                    $attachment_id,
                    $key,
                    $e instanceof AwsException ? ($e->getAwsErrorCode() ?? 'Unknown') : 'Exception',
                    $e->getMessage()
                );
            }

            return false;
        }
    }

    /**
     * Delete multiple files from storage
     */
    public function delete_files(array $keys, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();

        if ($client === null || empty($keys)) {
            return false;
        }

        $objects = array_map(fn($key) => ['Key' => $key], $keys);

        try {
            $this->error_handler->execute_with_retry(
                fn() => $client->deleteObjects([
                    'Bucket' => $this->config->bucket,
                    'Delete' => [
                        'Objects' => $objects,
                        'Quiet' => true,
                    ],
                ]),
                'delete_batch',
                $attachment_id
            );

            return true;

        } catch (\Exception $e) {
            $this->logger->error(
                'delete_batch',
                'Failed to delete multiple files: ' . $e->getMessage(),
                $attachment_id,
                implode(', ', array_slice($keys, 0, 3)) . (count($keys) > 3 ? '...' : ''),
                ['keys_count' => count($keys), 'error' => $e->getMessage()]
            );
            return false;
        }
    }

    /**
     * Check if a file exists in storage
     */
    public function file_exists(string $key): bool
    {
        $client = $this->get_client();

        if ($client === null) {
            return false;
        }

        try {
            $client->headObject([
                'Bucket' => $this->config->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Get public URL for a file
     */
    public function get_file_url(string $key): string
    {
        return $this->config->getPublicUrl($key);
    }

    /**
     * Generate storage key from local file path
     */
    public function generate_key(string $file_path): string
    {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        if (str_starts_with($file_path, $base_dir)) {
            $relative_path = substr($file_path, strlen($base_dir) + 1);
        } else {
            $relative_path = basename($file_path);
        }

        $base_path = $this->settings->get_storage_base_path();

        return rtrim($base_path, '/') . '/' . ltrim($relative_path, '/');
    }

    /**
     * Download a file from storage to local path
     */
    public function download_file(string $key, string $local_path, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();

        if ($client === null) {
            return false;
        }

        try {
            $result = $client->getObject([
                'Bucket' => $this->config->bucket,
                'Key' => $key,
            ]);

            $dir = dirname($local_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            file_put_contents($local_path, $result['Body']);

            return true;

        } catch (AwsException $e) {
            $this->logger->error(
                'download',
                'Failed to download file: ' . $e->getMessage(),
                $attachment_id,
                $key,
                ['error_code' => $e->getAwsErrorCode(), 'local_path' => $local_path]
            );
            return false;
        }
    }

    /**
     * Copy a file within storage
     */
    public function copy_file(string $source_key, string $dest_key): bool
    {
        $client = $this->get_client();

        if ($client === null) {
            return false;
        }

        try {
            $params = [
                'Bucket' => $this->config->bucket,
                'CopySource' => $this->config->bucket . '/' . $source_key,
                'Key' => $dest_key,
            ];

            if ($this->supportsPublicAcl()) {
                $params['ACL'] = 'public-read';
            }

            $client->copyObject($params);
            return true;
        } catch (AwsException $e) {
            $this->logger->error(
                'copy',
                'Failed to copy file: ' . $e->getMessage(),
                null,
                $source_key,
                ['error_code' => $e->getAwsErrorCode(), 'dest_key' => $dest_key]
            );
            return false;
        }
    }

    /**
     * List objects in storage (paginated)
     */
    public function list_objects_batch(int $batch_size = 100, ?string $continuation_token = null): ?array
    {
        $client = $this->get_client();

        if ($client === null) {
            return null;
        }

        $base_path = $this->settings->get_storage_base_path();

        try {
            $params = [
                'Bucket' => $this->config->bucket,
                'Prefix' => $base_path,
                'MaxKeys' => $batch_size,
            ];

            if ($continuation_token !== null) {
                $params['ContinuationToken'] = $continuation_token;
            }

            $result = $client->listObjectsV2($params);

            $keys = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $keys[] = $object['Key'];
                }
            }

            return [
                'keys' => $keys,
                'next_token' => $result['NextContinuationToken'] ?? null,
                'is_truncated' => $result['IsTruncated'] ?? false,
            ];

        } catch (AwsException $e) {
            $this->logger->error(
                'list_objects',
                'Failed to list objects: ' . $e->getMessage(),
                null,
                null,
                ['error_code' => $e->getAwsErrorCode(), 'batch_size' => $batch_size]
            );
            return null;
        }
    }

    /**
     * List objects with full metadata (key + size) for reconciliation
     */
    public function list_objects_with_metadata(int $batch_size = 100, ?string $continuation_token = null): ?array
    {
        $client = $this->get_client();

        if ($client === null) {
            return null;
        }

        $base_path = $this->settings->get_storage_base_path();

        try {
            $params = [
                'Bucket' => $this->config->bucket,
                'Prefix' => $base_path,
                'MaxKeys' => $batch_size,
            ];

            if ($continuation_token !== null) {
                $params['ContinuationToken'] = $continuation_token;
            }

            $result = $client->listObjectsV2($params);

            $objects = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $objects[] = [
                        'key' => $object['Key'],
                        'size' => (int) ($object['Size'] ?? 0),
                    ];
                }
            }

            return [
                'objects' => $objects,
                'next_token' => $result['NextContinuationToken'] ?? null,
                'is_truncated' => $result['IsTruncated'] ?? false,
            ];

        } catch (AwsException $e) {
            $this->logger->error(
                'list_objects_metadata',
                'Failed to list objects with metadata: ' . $e->getMessage(),
                null,
                null,
                ['error_code' => $e->getAwsErrorCode(), 'batch_size' => $batch_size]
            );
            return null;
        }
    }

    /**
     * Update object metadata (e.g., Cache-Control)
     */
    public function update_object_metadata(string $key, int $cache_max_age): bool
    {
        $client = $this->get_client();

        if ($client === null) {
            return false;
        }

        $cache_control = $cache_max_age > 0
            ? "public, max-age={$cache_max_age}"
            : 'no-cache, no-store, must-revalidate';

        try {
            $head = $client->headObject([
                'Bucket' => $this->config->bucket,
                'Key' => $key,
            ]);

            $content_type = $head['ContentType'] ?? 'application/octet-stream';

            $params = [
                'Bucket' => $this->config->bucket,
                'Key' => $key,
                'CopySource' => urlencode($this->config->bucket . '/' . $key),
                'MetadataDirective' => 'REPLACE',
                'CacheControl' => $cache_control,
                'ContentType' => $content_type,
            ];

            if ($this->supportsPublicAcl()) {
                $params['ACL'] = 'public-read';
            }

            $client->copyObject($params);

            return true;

        } catch (AwsException $e) {
            $this->logger->error(
                'update_metadata',
                'Failed to update object metadata: ' . $e->getMessage(),
                null,
                $key,
                ['error_code' => $e->getAwsErrorCode(), 'cache_max_age' => $cache_max_age]
            );
            return false;
        }
    }

    /**
     * Update metadata for multiple objects in batch
     */
    public function update_objects_metadata_batch(array $keys, int $cache_max_age): array
    {
        $success = 0;
        $failed = 0;

        foreach ($keys as $key) {
            if ($this->update_object_metadata($key, $cache_max_age)) {
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Get storage statistics
     */
    public function get_bucket_stats(): ?array
    {
        $client = $this->get_client();

        if ($client === null) {
            return null;
        }

        $base_path = $this->settings->get_storage_base_path();
        $total_files = 0;
        $original_files = 0;
        $total_size = 0;
        $original_size = 0;
        $continuation_token = null;

        $thumbnail_pattern = '/-\d+x\d+(-[a-z0-9]+)?\.[a-zA-Z0-9]+$/';

        try {
            do {
                $params = [
                    'Bucket' => $this->config->bucket,
                    'Prefix' => $base_path,
                    'MaxKeys' => 1000,
                ];

                if ($continuation_token !== null) {
                    $params['ContinuationToken'] = $continuation_token;
                }

                $result = $client->listObjectsV2($params);

                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        $key = $object['Key'] ?? '';
                        $size = $object['Size'] ?? 0;

                        $total_files++;
                        $total_size += $size;

                        if (!preg_match($thumbnail_pattern, $key)) {
                            $original_files++;
                            $original_size += $size;
                        }
                    }
                }

                $continuation_token = $result['NextContinuationToken'] ?? null;

            } while ($result['IsTruncated'] ?? false);

            return [
                'files' => $total_files,
                'original_files' => $original_files,
                'size' => $total_size,
                'original_size' => $original_size,
                'synced_at' => current_time('mysql'),
            ];

        } catch (AwsException $e) {
            $this->logger->error(
                'bucket_stats',
                'Failed to retrieve bucket statistics: ' . $e->getMessage(),
                null,
                null,
                ['error_code' => $e->getAwsErrorCode()]
            );
            return null;
        }
    }

    /**
     * Test connection to storage provider
     */
    public function test_connection(): array
    {
        $results = [
            'credentials' => ['success' => false, 'message' => ''],
            'bucket' => ['success' => false, 'message' => ''],
            'permissions' => ['success' => false, 'message' => ''],
            'cdn' => ['success' => false, 'message' => ''],
        ];

        if (!$this->config->isValid()) {
            $results['credentials']['message'] = 'Configuration is incomplete';
            return $results;
        }

        // Test credentials
        try {
            $credentialsResult = $this->testCredentials();
            $results['credentials'] = $credentialsResult;
            
            if (!$credentialsResult['success']) {
                return $results;
            }
        } catch (\Exception $e) {
            $results['credentials']['message'] = $this->error_handler->get_friendly_error_message($e);
            return $results;
        }

        // Test bucket access
        $client = $this->get_client();

        if ($client === null) {
            $results['bucket']['message'] = 'Failed to create storage client';
            return $results;
        }

        try {
            $client->headBucket(['Bucket' => $this->config->bucket]);
            $results['bucket'] = [
                'success' => true,
                'message' => "Bucket '{$this->config->bucket}' exists and is accessible",
            ];
        } catch (AwsException $e) {
            $results['bucket']['message'] = $this->error_handler->get_friendly_error_message($e);
            return $results;
        }

        // Test write permissions
        try {
            $test_key = 'media-toolkit-test-' . time() . '.txt';

            $put_params = [
                'Bucket' => $this->config->bucket,
                'Key' => $test_key,
                'Body' => 'Media Toolkit connection test',
                'ContentType' => 'text/plain',
            ];

            if ($this->supportsPublicAcl()) {
                $put_params['ACL'] = 'public-read';
            }

            $client->putObject($put_params);

            $client->deleteObject([
                'Bucket' => $this->config->bucket,
                'Key' => $test_key,
            ]);

            $results['permissions'] = [
                'success' => true,
                'message' => 'Write and delete permissions confirmed',
            ];
        } catch (AwsException $e) {
            $results['permissions']['message'] = $this->error_handler->get_friendly_error_message($e);
            return $results;
        }

        // Test CDN URL
        if ($this->config->hasCDN()) {
            $response = wp_remote_head($this->config->cdnUrl, [
                'timeout' => 10,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                $results['cdn']['message'] = 'CDN URL not reachable: ' . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code >= 200 && $status_code < 500) {
                    $results['cdn'] = [
                        'success' => true,
                        'message' => 'CDN URL is reachable',
                    ];
                } else {
                    $results['cdn']['message'] = "CDN returned status code: {$status_code}";
                }
            }
        } elseif ($this->config->requiresCdnUrl()) {
            $results['cdn'] = [
                'success' => false,
                'message' => 'CDN URL is required for this provider but not configured',
            ];
        } else {
            $results['cdn'] = [
                'success' => true,
                'message' => 'CDN not configured (using direct storage URLs)',
            ];
        }

        return $results;
    }

    /**
     * Test credentials - can be overridden by providers
     * @return array{success: bool, message: string}
     */
    protected function testCredentials(): array
    {
        // Default implementation using STS (works for AWS S3)
        // Other providers should override this
        try {
            $stsConfig = [
                'version' => 'latest',
                'region' => $this->config->getEffectiveRegion(),
                'credentials' => [
                    'key' => $this->config->accessKey,
                    'secret' => $this->config->secretKey,
                ],
            ];

            $endpoint = $this->config->getEndpoint();
            if ($endpoint !== null) {
                // For non-AWS providers, just verify bucket access instead
                return $this->testBucketAccess();
            }

            $stsClient = new StsClient($stsConfig);
            $identity = $stsClient->getCallerIdentity();

            return [
                'success' => true,
                'message' => 'Credentials valid. Account: ' . $identity['Account'],
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => $this->error_handler->get_friendly_error_message($e),
            ];
        }
    }

    /**
     * Test bucket access as credential verification
     * Used by non-AWS providers
     */
    protected function testBucketAccess(): array
    {
        $client = $this->get_client();

        if ($client === null) {
            return [
                'success' => false,
                'message' => 'Failed to create storage client',
            ];
        }

        try {
            $client->headBucket(['Bucket' => $this->config->bucket]);
            return [
                'success' => true,
                'message' => 'Credentials valid',
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => $this->error_handler->get_friendly_error_message($e),
            ];
        }
    }

    /**
     * Get storage key from URL
     */
    public function get_key_from_url(string $url): string
    {
        // Check CDN URL
        if ($this->config->hasCDN() && str_starts_with($url, $this->config->cdnUrl)) {
            return ltrim(substr($url, strlen(rtrim($this->config->cdnUrl, '/'))), '/');
        }

        // Check direct storage URL patterns
        return $this->extractKeyFromDirectUrl($url);
    }

    /**
     * Extract key from direct storage URL
     * Override in subclasses for provider-specific patterns
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // Default implementation - try to extract from common patterns
        $parsed = parse_url($url);
        if (isset($parsed['path'])) {
            return ltrim($parsed['path'], '/');
        }
        return '';
    }
}

