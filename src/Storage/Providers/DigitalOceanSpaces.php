<?php
/**
 * DigitalOcean Spaces Storage Provider
 *
 * @package Metodo\MediaToolkit\Storage\Providers
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage\Providers;

use Metodo\MediaToolkit\Storage\AbstractObjectStorage;
use Metodo\MediaToolkit\Storage\StorageProvider;

/**
 * DigitalOcean Spaces storage implementation
 */
final class DigitalOceanSpaces extends AbstractObjectStorage
{
    /**
     * Get the storage provider type
     */
    public function get_provider(): StorageProvider
    {
        return StorageProvider::DIGITALOCEAN_SPACES;
    }

    /**
     * DigitalOcean Spaces supports public-read ACL
     */
    protected function supportsPublicAcl(): bool
    {
        return true;
    }

    /**
     * Test credentials by verifying bucket access
     * Spaces doesn't support STS
     */
    protected function testCredentials(): array
    {
        return $this->testBucketAccess();
    }

    /**
     * Extract key from direct Spaces URL
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // Pattern: https://bucket.region.digitaloceanspaces.com/key
        $pattern = sprintf(
            '#https://%s\.%s\.digitaloceanspaces\.com/(.+)#',
            preg_quote($this->config->bucket, '#'),
            preg_quote($this->config->region, '#')
        );

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Alternative CDN pattern: https://bucket.region.cdn.digitaloceanspaces.com/key
        $pattern2 = sprintf(
            '#https://%s\.%s\.cdn\.digitaloceanspaces\.com/(.+)#',
            preg_quote($this->config->bucket, '#'),
            preg_quote($this->config->region, '#')
        );

        if (preg_match($pattern2, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

