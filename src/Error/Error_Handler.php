<?php
/**
 * Error Handler class for managing errors and retries
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Error;

use Metodo\MediaToolkit\Core\Logger;

use function Metodo\MediaToolkit\media_toolkit;

/**
 * Handles errors, retries, and failed operation management
 */
final class Error_Handler
{
    private const MAX_RETRIES = 5;
    private const BASE_DELAY_MS = 100;
    private const FAILED_OPS_OPTION = 'media_toolkit_failed_operations';

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check if an error is retryable
     */
    public function is_retryable(string $error_code): bool
    {
        return RetryableError::tryFrom($error_code) !== null;
    }

    /**
     * Calculate delay for retry (exponential backoff)
     */
    public function get_retry_delay(int $attempt): int
    {
        // Exponential backoff: 100ms, 200ms, 400ms, 800ms, 1600ms
        return self::BASE_DELAY_MS * (2 ** ($attempt - 1));
    }

    /**
     * Execute with retry logic
     *
     * @template T
     * @param callable(): T $operation
     * @param string $operation_name
     * @param int|null $attachment_id
     * @param string|null $file_path
     * @return T
     * @throws \Exception
     */
    public function execute_with_retry(
        callable $operation,
        string $operation_name,
        ?int $attachment_id = null,
        ?string $file_path = null
    ): mixed {
        $last_exception = null;
        $max_retries = 3;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            try {
                return $operation();
            } catch (\Aws\Exception\AwsException $e) {
                $error_code = $e->getAwsErrorCode() ?? 'Unknown';
                $last_exception = $e;

                $this->logger->warning(
                    $operation_name,
                    "Attempt {$attempt} failed: {$e->getMessage()}",
                    $attachment_id,
                    $file_path,
                    ['error_code' => $error_code, 'attempt' => $attempt]
                );

                if (!$this->is_retryable($error_code) || $attempt === $max_retries) {
                    break;
                }

                // Wait before retry
                usleep($this->get_retry_delay($attempt) * 1000);
            } catch (\Exception $e) {
                $last_exception = $e;
                
                $this->logger->error(
                    $operation_name,
                    "Non-retryable error: {$e->getMessage()}",
                    $attachment_id,
                    $file_path
                );
                
                break;
            }
        }

        throw $last_exception;
    }

    /**
     * Record a failed operation for later retry
     */
    public function record_failed_operation(
        string $operation,
        int $attachment_id,
        string $file_path,
        string $error_code,
        string $error_message
    ): void {
        $failed_ops = $this->get_failed_operations();
        
        // Check if already exists
        $key = "{$operation}_{$attachment_id}";
        
        if (isset($failed_ops[$key])) {
            // $failed_ops[$key] is already a FailedOperation object from get_failed_operations()
            $existing = $failed_ops[$key];
            $failed_ops[$key] = new FailedOperation(
                operation: $operation,
                attachment_id: $attachment_id,
                file_path: $file_path,
                error_code: $error_code,
                error_message: $error_message,
                retry_count: $existing->retry_count + 1,
                created_at: $existing->created_at,
            );
        } else {
            $failed_ops[$key] = new FailedOperation(
                operation: $operation,
                attachment_id: $attachment_id,
                file_path: $file_path,
                error_code: $error_code,
                error_message: $error_message,
                retry_count: 1,
                created_at: time(),
            );
        }

        // Convert to array for storage
        $stored = array_map(
            fn($op) => $op instanceof FailedOperation ? $op->toArray() : $op,
            $failed_ops
        );

        update_option(self::FAILED_OPS_OPTION, $stored);

        $this->logger->error(
            $operation,
            "Operation failed and queued for retry: {$error_message}",
            $attachment_id,
            $file_path,
            ['error_code' => $error_code]
        );
    }

    /**
     * Get all failed operations
     *
     * @return array<string, FailedOperation>
     */
    public function get_failed_operations(): array
    {
        $stored = get_option(self::FAILED_OPS_OPTION, []);
        
        if (!is_array($stored)) {
            return [];
        }

        $operations = [];
        foreach ($stored as $key => $data) {
            if (is_array($data)) {
                $operations[$key] = FailedOperation::fromArray($data);
            }
        }

        return $operations;
    }

    /**
     * Get failed operations count
     */
    public function get_failed_count(): int
    {
        return count($this->get_failed_operations());
    }

    /**
     * Remove a failed operation (after successful retry)
     */
    public function remove_failed_operation(string $operation, int $attachment_id): void
    {
        $failed_ops = get_option(self::FAILED_OPS_OPTION, []);
        $key = "{$operation}_{$attachment_id}";
        
        if (isset($failed_ops[$key])) {
            unset($failed_ops[$key]);
            update_option(self::FAILED_OPS_OPTION, $failed_ops);
        }
    }

    /**
     * Clear all failed operations
     */
    public function clear_failed_operations(): void
    {
        delete_option(self::FAILED_OPS_OPTION);
    }

    /**
     * Retry failed operations (called by cron)
     */
    public function retry_failed_operations(): void
    {
        $failed_ops = $this->get_failed_operations();
        
        if (empty($failed_ops)) {
            return;
        }

        $plugin = media_toolkit();
        $storage = $plugin->get_storage();
        
        if ($storage === null) {
            return;
        }

        foreach ($failed_ops as $key => $op) {
            // Skip if max retries reached
            if ($op->retry_count >= self::MAX_RETRIES) {
                $this->logger->error(
                    $op->operation,
                    "Max retries ({$op->retry_count}) reached, operation abandoned",
                    $op->attachment_id,
                    $op->file_path
                );
                
                // Notify admin
                $this->notify_admin_failure($op);
                
                // Remove from queue
                $this->remove_failed_operation($op->operation, $op->attachment_id);
                continue;
            }

            try {
                // Retry based on operation type
                switch ($op->operation) {
                    case 'upload':
                        if (file_exists($op->file_path)) {
                            $storage->upload_file($op->file_path, $op->attachment_id);
                        }
                        break;
                    
                    case 'delete':
                        $storage->delete_file($op->file_path);
                        break;
                    
                    default:
                        $this->logger->warning(
                            'retry',
                            "Unknown operation type: {$op->operation}",
                            $op->attachment_id
                        );
                        continue 2;
                }

                // Success - remove from failed operations
                $this->remove_failed_operation($op->operation, $op->attachment_id);
                
                $this->logger->success(
                    'retry',
                    "Successfully retried {$op->operation} operation",
                    $op->attachment_id,
                    $op->file_path
                );

            } catch (\Exception $e) {
                // Update retry count
                $this->record_failed_operation(
                    $op->operation,
                    $op->attachment_id,
                    $op->file_path,
                    $e instanceof \Aws\Exception\AwsException ? ($e->getAwsErrorCode() ?? 'Unknown') : 'Exception',
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Notify admin about permanently failed operation
     */
    private function notify_admin_failure(FailedOperation $operation): void
    {
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return;
        }

        $subject = '[Media S3 Offload] Operation Failed After Max Retries';
        
        $message = sprintf(
            "An S3 operation has failed after %d retry attempts.\n\n" .
            "Operation: %s\n" .
            "File: %s\n" .
            "Attachment ID: %d\n" .
            "Error: %s - %s\n\n" .
            "Please check the plugin logs for more details.",
            self::MAX_RETRIES,
            $operation->operation,
            $operation->file_path,
            $operation->attachment_id,
            $operation->error_code,
            $operation->error_message
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Handle exception and determine if it's a configuration error
     */
    public function is_configuration_error(\Exception $e): bool
    {
        if (!$e instanceof \Aws\Exception\AwsException) {
            return false;
        }

        $config_errors = [
            'InvalidAccessKeyId',
            'SignatureDoesNotMatch',
            'NoSuchBucket',
            'AccessDenied',
            'InvalidBucketName',
        ];

        return in_array($e->getAwsErrorCode(), $config_errors, true);
    }

    /**
     * Get human-readable error message
     */
    public function get_friendly_error_message(\Exception $e): string
    {
        if (!$e instanceof \Aws\Exception\AwsException) {
            return $e->getMessage();
        }

        $error_code = $e->getAwsErrorCode();

        return match ($error_code) {
            'InvalidAccessKeyId' => 'Invalid AWS Access Key. Please check your credentials.',
            'SignatureDoesNotMatch' => 'Invalid AWS Secret Key. Please check your credentials.',
            'NoSuchBucket' => 'The specified S3 bucket does not exist.',
            'AccessDenied' => 'Access denied. Please check your IAM permissions.',
            'InvalidBucketName' => 'Invalid bucket name. Bucket names must follow AWS naming rules.',
            'RequestTimeout' => 'Request timed out. Please try again.',
            'ServiceUnavailable' => 'AWS S3 service is temporarily unavailable. Please try again later.',
            'ThrottlingException' => 'Too many requests. Please wait and try again.',
            'SlowDown' => 'Request rate too high. Please reduce the request rate.',
            default => $e->getMessage(),
        };
    }
}

