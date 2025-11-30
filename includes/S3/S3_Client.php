<?php
/**
 * S3 Client wrapper class
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\S3;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Error\Error_Handler;

use Aws\S3\S3Client as AwsS3Client;
use Aws\Exception\AwsException;
use Aws\Sts\StsClient;

/**
 * Wrapper for AWS S3 operations with retry support
 */
final class S3_Client
{
    private ?AwsS3Client $client = null;
    private Settings $settings;
    private Error_Handler $error_handler;
    private Logger $logger;

    public function __construct(Settings $settings, Error_Handler $error_handler, Logger $logger)
    {
        $this->settings = $settings;
        $this->error_handler = $error_handler;
        $this->logger = $logger;
    }

    /**
     * Get or create S3 client
     */
    public function get_client(): ?AwsS3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = $this->settings->get_config();
        
        if ($config === null || !$config->isValid()) {
            return null;
        }

        $this->client = new AwsS3Client([
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
     * Reset client (needed when settings change)
     */
    public function reset_client(): void
    {
        $this->client = null;
    }

    /**
     * Test S3 connection
     */
    public function test_connection(): array
    {
        $results = [
            'credentials' => ['success' => false, 'message' => ''],
            'bucket' => ['success' => false, 'message' => ''],
            'permissions' => ['success' => false, 'message' => ''],
            'cdn' => ['success' => false, 'message' => ''],
        ];

        $config = $this->settings->get_config();
        
        if ($config === null || !$config->isValid()) {
            $results['credentials']['message'] = 'Configuration is incomplete';
            return $results;
        }

        // Test credentials using STS
        try {
            $stsClient = new StsClient([
                'version' => 'latest',
                'region' => $config->region,
                'credentials' => [
                    'key' => $config->accessKey,
                    'secret' => $config->secretKey,
                ],
            ]);

            $identity = $stsClient->getCallerIdentity();
            $results['credentials'] = [
                'success' => true,
                'message' => 'Credentials valid. Account: ' . $identity['Account'],
            ];
        } catch (AwsException $e) {
            $results['credentials']['message'] = $this->error_handler->get_friendly_error_message($e);
            return $results;
        }

        // Test bucket access
        $client = $this->get_client();
        
        if ($client === null) {
            $results['bucket']['message'] = 'Failed to create S3 client';
            return $results;
        }

        try {
            $client->headBucket(['Bucket' => $config->bucket]);
            $results['bucket'] = [
                'success' => true,
                'message' => "Bucket '{$config->bucket}' exists and is accessible",
            ];
        } catch (AwsException $e) {
            $results['bucket']['message'] = $this->error_handler->get_friendly_error_message($e);
            return $results;
        }

        // Test write permissions
        try {
            $test_key = 'media-toolkit-test-' . time() . '.txt';
            
            $client->putObject([
                'Bucket' => $config->bucket,
                'Key' => $test_key,
                'Body' => 'Media S3 Offload connection test',
                'ContentType' => 'text/plain',
            ]);

            // Delete test file
            $client->deleteObject([
                'Bucket' => $config->bucket,
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

        // Test CDN URL (if configured)
        if ($config->hasCDN()) {
            $response = wp_remote_head($config->cdnUrl, [
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
                        'message' => 'CDN URL is reachable (' . ucfirst($config->cdnProvider->value) . ')',
                    ];
                } else {
                    $results['cdn']['message'] = "CDN returned status code: {$status_code}";
                }
            }
        } else {
            $results['cdn'] = [
                'success' => true,
                'message' => 'CDN not configured (using direct S3 URLs)',
            ];
        }

        return $results;
    }

    /**
     * Upload a file to S3
     */
    public function upload_file(
        string $file_path,
        ?int $attachment_id = null,
        ?string $custom_key = null
    ): UploadResult {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return new UploadResult(
                success: false,
                s3_key: '',
                url: '',
                error: 'S3 client not configured'
            );
        }

        if (!file_exists($file_path)) {
            return new UploadResult(
                success: false,
                s3_key: '',
                url: '',
                error: "File not found: {$file_path}"
            );
        }

        // Generate S3 key preserving WordPress structure
        $s3_key = $custom_key ?? $this->generate_s3_key($file_path);
        
        // Get mime type and filename for Content-Disposition
        $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
        $filename = basename($file_path);
        $content_disposition = $this->settings->get_content_disposition_for_mime($mime_type, $filename);

        try {
            // Build putObject params
            $put_params = [
                'Bucket' => $config->bucket,
                'Key' => $s3_key,
                'SourceFile' => $file_path,
                'ContentType' => $mime_type,
                'ACL' => 'public-read',
                'CacheControl' => $this->settings->get_cache_control_header(),
            ];
            
            // Add Content-Disposition if not inline (S3 default is inline)
            if ($content_disposition !== 'inline') {
                $put_params['ContentDisposition'] = $content_disposition;
            }
            
            $result = $this->error_handler->execute_with_retry(
                fn() => $client->putObject($put_params),
                'upload',
                $attachment_id,
                $file_path
            );

            // Get URL (prefer CDN if configured)
            $url = $this->settings->get_file_url($s3_key);

            return new UploadResult(
                success: true,
                s3_key: $s3_key,
                url: $url,
            );

        } catch (\Exception $e) {
            // Record for later retry
            if ($attachment_id !== null) {
                $this->error_handler->record_failed_operation(
                    'upload',
                    $attachment_id,
                    $file_path,
                    $e instanceof AwsException ? ($e->getAwsErrorCode() ?? 'Unknown') : 'Exception',
                    $e->getMessage()
                );
            }

            return new UploadResult(
                success: false,
                s3_key: $s3_key,
                url: '',
                error: $this->error_handler->get_friendly_error_message($e)
            );
        }
    }

    /**
     * Delete a file from S3
     */
    public function delete_file(string $s3_key, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return false;
        }

        try {
            $this->error_handler->execute_with_retry(
                fn() => $client->deleteObject([
                    'Bucket' => $config->bucket,
                    'Key' => $s3_key,
                ]),
                'delete',
                $attachment_id,
                $s3_key
            );

            return true;

        } catch (\Exception $e) {
            if ($attachment_id !== null) {
                $this->error_handler->record_failed_operation(
                    'delete',
                    $attachment_id,
                    $s3_key,
                    $e instanceof AwsException ? ($e->getAwsErrorCode() ?? 'Unknown') : 'Exception',
                    $e->getMessage()
                );
            }

            return false;
        }
    }

    /**
     * Delete multiple files from S3
     */
    public function delete_files(array $s3_keys, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null || empty($s3_keys)) {
            return false;
        }

        $objects = array_map(fn($key) => ['Key' => $key], $s3_keys);

        try {
            $this->error_handler->execute_with_retry(
                fn() => $client->deleteObjects([
                    'Bucket' => $config->bucket,
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
                implode(', ', array_slice($s3_keys, 0, 3)) . (count($s3_keys) > 3 ? '...' : ''),
                ['keys_count' => count($s3_keys), 'error' => $e->getMessage()]
            );
            return false;
        }
    }

    /**
     * Download a file from S3 to local path
     */
    public function download_file(string $s3_key, string $local_path, ?int $attachment_id = null): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return false;
        }

        try {
            $result = $client->getObject([
                'Bucket' => $config->bucket,
                'Key' => $s3_key,
            ]);

            // Ensure directory exists
            $dir = dirname($local_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            // Write file
            file_put_contents($local_path, $result['Body']);
            
            return true;

        } catch (AwsException $e) {
            $this->logger->error(
                'download',
                'Failed to download file from S3: ' . $e->getMessage(),
                $attachment_id,
                $s3_key,
                ['error_code' => $e->getAwsErrorCode(), 'local_path' => $local_path]
            );
            return false;
        }
    }

    /**
     * Check if a file exists on S3
     */
    public function file_exists(string $s3_key): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return false;
        }

        try {
            $client->headObject([
                'Bucket' => $config->bucket,
                'Key' => $s3_key,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Copy a file on S3
     */
    public function copy_file(string $source_key, string $dest_key): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return false;
        }

        try {
            $client->copyObject([
                'Bucket' => $config->bucket,
                'CopySource' => $config->bucket . '/' . $source_key,
                'Key' => $dest_key,
                'ACL' => 'public-read',
            ]);
            return true;
        } catch (AwsException $e) {
            $this->logger->error(
                'copy',
                'Failed to copy file on S3: ' . $e->getMessage(),
                null,
                $source_key,
                ['error_code' => $e->getAwsErrorCode(), 'dest_key' => $dest_key]
            );
            return false;
        }
    }

    /**
     * Get file URL
     */
    public function get_file_url(string $s3_key): string
    {
        return $this->settings->get_file_url($s3_key);
    }

    /**
     * Generate S3 key from local file path
     */
    public function generate_s3_key(string $file_path): string
    {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        // Get relative path from uploads directory
        if (str_starts_with($file_path, $base_dir)) {
            $relative_path = substr($file_path, strlen($base_dir) + 1);
        } else {
            // Fallback: use filename only
            $relative_path = basename($file_path);
        }

        $base_path = $this->settings->get_s3_base_path();
        
        return rtrim($base_path, '/') . '/' . ltrim($relative_path, '/');
    }

    /**
     * Get S3 key from attachment URL
     */
    public function get_s3_key_from_url(string $url): string
    {
        $config = $this->settings->get_config();
        
        if ($config === null) {
            return '';
        }

        // Check CDN URL (Cloudflare, CloudFront, etc.)
        if ($config->hasCDN() && str_starts_with($url, $config->cdnUrl)) {
            return ltrim(substr($url, strlen(rtrim($config->cdnUrl, '/'))), '/');
        }

        // Check direct S3 URL
        $s3_base = sprintf('https://%s.s3.%s.amazonaws.com/', $config->bucket, $config->region);
        if (str_starts_with($url, $s3_base)) {
            return substr($url, strlen($s3_base));
        }

        return '';
    }

    /**
     * List objects in a batch for processing
     * Returns array of keys and continuation token
     */
    public function list_objects_batch(int $batch_size = 100, ?string $continuation_token = null): ?array
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return null;
        }

        $base_path = $this->settings->get_s3_base_path();

        try {
            $params = [
                'Bucket' => $config->bucket,
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
                'Failed to list S3 objects: ' . $e->getMessage(),
                null,
                null,
                ['error_code' => $e->getAwsErrorCode(), 'batch_size' => $batch_size]
            );
            return null;
        }
    }

    /**
     * Update metadata (Cache-Control) for an existing S3 object
     * S3 requires copying the object to itself with new metadata
     */
    public function update_object_metadata(string $key, int $cache_max_age): bool
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return false;
        }

        // Build Cache-Control header
        $cache_control = $cache_max_age > 0 
            ? "public, max-age={$cache_max_age}" 
            : 'no-cache, no-store, must-revalidate';

        try {
            // Get current object to preserve ContentType
            $head = $client->headObject([
                'Bucket' => $config->bucket,
                'Key' => $key,
            ]);

            $content_type = $head['ContentType'] ?? 'application/octet-stream';

            // Copy object to itself with new metadata
            $client->copyObject([
                'Bucket' => $config->bucket,
                'Key' => $key,
                'CopySource' => urlencode($config->bucket . '/' . $key),
                'MetadataDirective' => 'REPLACE',
                'CacheControl' => $cache_control,
                'ContentType' => $content_type,
                'ACL' => 'public-read',
            ]);

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
     * Returns count of successful and failed updates
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

        return [
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Get bucket statistics directly from S3
     * Returns file count and total size for the configured base path
     * Separates original files from thumbnails/versions
     */
    public function get_bucket_stats(): ?array
    {
        $client = $this->get_client();
        $config = $this->settings->get_config();

        if ($client === null || $config === null) {
            return null;
        }

        $base_path = $this->settings->get_s3_base_path();
        $total_files = 0;
        $original_files = 0;
        $total_size = 0;
        $original_size = 0;
        $continuation_token = null;

        // Pattern to match WordPress thumbnail files: -123x456.ext or -123x456-suffix.ext
        $thumbnail_pattern = '/-\d+x\d+(-[a-z0-9]+)?\.[a-zA-Z0-9]+$/';

        try {
            do {
                $params = [
                    'Bucket' => $config->bucket,
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
                        
                        // Check if this is an original file (not a thumbnail)
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
}

