<?php
/**
 * Wasabi Storage Provider
 *
 * @package Metodo\MediaToolkit\Storage\Providers
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage\Providers;

use Metodo\MediaToolkit\Storage\AbstractObjectStorage;
use Metodo\MediaToolkit\Storage\StorageProvider;

/**
 * Wasabi storage implementation (S3-compatible)
 * 
 * Note: Wasabi has a minimum storage charge of 1TB and 90-day minimum retention.
 */
final class Wasabi extends AbstractObjectStorage
{
    /**
     * Get the storage provider type
     */
    public function get_provider(): StorageProvider
    {
        return StorageProvider::WASABI;
    }

    /**
     * Wasabi supports public-read ACL
     */
    protected function supportsPublicAcl(): bool
    {
        return true;
    }

    /**
     * Test credentials by verifying bucket access
     * Wasabi doesn't support STS
     */
    protected function testCredentials(): array
    {
        return $this->testBucketAccess();
    }

    /**
     * Extract key from direct Wasabi URL
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // Pattern: https://bucket.s3.region.wasabisys.com/key
        $pattern = sprintf(
            '#https://%s\.s3\.%s\.wasabisys\.com/(.+)#',
            preg_quote($this->config->bucket, '#'),
            preg_quote($this->config->region, '#')
        );

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Alternative pattern: https://s3.region.wasabisys.com/bucket/key
        $pattern2 = sprintf(
            '#https://s3\.%s\.wasabisys\.com/%s/(.+)#',
            preg_quote($this->config->region, '#'),
            preg_quote($this->config->bucket, '#')
        );

        if (preg_match($pattern2, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

