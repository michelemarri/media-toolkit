<?php
/**
 * Backblaze B2 Storage Provider
 *
 * @package Metodo\MediaToolkit\Storage\Providers
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage\Providers;

use Metodo\MediaToolkit\Storage\AbstractObjectStorage;
use Metodo\MediaToolkit\Storage\StorageProvider;

/**
 * Backblaze B2 storage implementation (S3-compatible API)
 * 
 * Note: Bucket must have "S3 Compatibility" enabled in B2 settings.
 */
final class BackblazeB2 extends AbstractObjectStorage
{
    /**
     * Get the storage provider type
     */
    public function get_provider(): StorageProvider
    {
        return StorageProvider::BACKBLAZE_B2;
    }

    /**
     * B2 supports public-read ACL (when bucket is public)
     */
    protected function supportsPublicAcl(): bool
    {
        return true;
    }

    /**
     * B2 uses path-style endpoints for S3 compatibility
     */
    protected function usePathStyleEndpoint(): bool
    {
        return true;
    }

    /**
     * Test credentials by verifying bucket access
     * B2 doesn't support STS
     */
    protected function testCredentials(): array
    {
        return $this->testBucketAccess();
    }

    /**
     * Extract key from direct B2 URL
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // S3-compatible pattern: https://bucket.s3.region.backblazeb2.com/key
        $pattern = sprintf(
            '#https://%s\.s3\.%s\.backblazeb2\.com/(.+)#',
            preg_quote($this->config->bucket, '#'),
            preg_quote($this->config->region, '#')
        );

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Alternative pattern: https://s3.region.backblazeb2.com/bucket/key
        $pattern2 = sprintf(
            '#https://s3\.%s\.backblazeb2\.com/%s/(.+)#',
            preg_quote($this->config->region, '#'),
            preg_quote($this->config->bucket, '#')
        );

        if (preg_match($pattern2, $url, $matches)) {
            return $matches[1];
        }

        // Native B2 URL pattern: https://f00X.backblazeb2.com/file/bucket/key
        $pattern3 = sprintf(
            '#https://f\d+\.backblazeb2\.com/file/%s/(.+)#',
            preg_quote($this->config->bucket, '#')
        );

        if (preg_match($pattern3, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

