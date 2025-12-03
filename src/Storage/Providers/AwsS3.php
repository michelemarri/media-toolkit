<?php
/**
 * AWS S3 Storage Provider
 *
 * @package Metodo\MediaToolkit\Storage\Providers
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage\Providers;

use Metodo\MediaToolkit\Storage\AbstractObjectStorage;
use Metodo\MediaToolkit\Storage\StorageProvider;

use Aws\Sts\StsClient;
use Aws\Exception\AwsException;

/**
 * AWS S3 storage implementation
 */
final class AwsS3 extends AbstractObjectStorage
{
    /**
     * Get the storage provider type
     */
    public function get_provider(): StorageProvider
    {
        return StorageProvider::AWS_S3;
    }

    /**
     * AWS S3 supports public-read ACL
     */
    protected function supportsPublicAcl(): bool
    {
        return true;
    }

    /**
     * Test credentials using STS GetCallerIdentity
     */
    protected function testCredentials(): array
    {
        try {
            $stsClient = new StsClient([
                'version' => 'latest',
                'region' => $this->config->region,
                'credentials' => [
                    'key' => $this->config->accessKey,
                    'secret' => $this->config->secretKey,
                ],
            ]);

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
     * Extract key from direct S3 URL
     */
    protected function extractKeyFromDirectUrl(string $url): string
    {
        // Pattern: https://bucket.s3.region.amazonaws.com/key
        $pattern = sprintf(
            '#https://%s\.s3\.%s\.amazonaws\.com/(.+)#',
            preg_quote($this->config->bucket, '#'),
            preg_quote($this->config->region, '#')
        );

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        // Alternative pattern: https://s3.region.amazonaws.com/bucket/key
        $pattern2 = sprintf(
            '#https://s3\.%s\.amazonaws\.com/%s/(.+)#',
            preg_quote($this->config->region, '#'),
            preg_quote($this->config->bucket, '#')
        );

        if (preg_match($pattern2, $url, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

