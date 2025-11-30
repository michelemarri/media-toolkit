<?php
/**
 * S3 configuration value object
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\S3;

use Metodo\MediaToolkit\CDN\CDNProvider;

readonly class S3Config
{
    public function __construct(
        public string $accessKey,
        public string $secretKey,
        public string $region,
        public string $bucket,
        public string $cdnUrl = '',
        public CDNProvider $cdnProvider = CDNProvider::NONE,
        public string $cloudflareZoneId = '',
        public string $cloudflareApiToken = '',
        public string $cloudfrontDistributionId = '',
    ) {}

    public function isValid(): bool
    {
        return !empty($this->accessKey)
            && !empty($this->secretKey)
            && !empty($this->region)
            && !empty($this->bucket);
    }

    public function hasCDN(): bool
    {
        return !empty($this->cdnUrl) && $this->cdnProvider !== CDNProvider::NONE;
    }
}

