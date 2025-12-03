<?php
/**
 * Cloudflare R2 Storage Provider
 *
 * @package Metodo\MediaToolkit\Storage\Providers
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage\Providers;

use Metodo\MediaToolkit\Storage\AbstractObjectStorage;
use Metodo\MediaToolkit\Storage\StorageProvider;

/**
 * Cloudflare R2 storage implementation
 * 
 * Note: R2 does not support public-read ACL.
 * Files are private by default and require a CDN URL or custom domain for public access.
 */
final class CloudflareR2 extends AbstractObjectStorage
{
    /**
     * Get the storage provider type
     */
    public function get_provider(): StorageProvider
    {
        return StorageProvider::CLOUDFLARE_R2;
    }

    /**
     * R2 does NOT support public-read ACL
     */
    protected function supportsPublicAcl(): bool
    {
        return false;
    }

    /**
     * R2 uses path-style endpoints
     */
    protected function usePathStyleEndpoint(): bool
    {
        return true;
    }

    /**
     * Test credentials by verifying bucket access
     * R2 doesn't support STS, so we verify via bucket access
     */
    protected function testCredentials(): array
    {
        return $this->testBucketAccess();
    }

    /**
     * Extract key from direct R2 URL
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // Pattern: https://account_id.r2.cloudflarestorage.com/bucket/key
        $pattern = sprintf(
            '#https://%s\.r2\.cloudflarestorage\.com/%s/(.+)#',
            preg_quote($this->config->accountId, '#'),
            preg_quote($this->config->bucket, '#')
        );

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Override test_connection to add R2-specific CDN requirement check
     */
    public function test_connection(): array
    {
        $results = parent::test_connection();

        // Override CDN message for R2
        if (!$this->config->hasCDN()) {
            $results['cdn'] = [
                'success' => false,
                'message' => 'Cloudflare R2 requires a CDN URL or custom domain for public file access. Files are private by default.',
            ];
        }

        return $results;
    }
}

