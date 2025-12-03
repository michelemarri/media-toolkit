<?php
/**
 * Storage Configuration DTO
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

use Metodo\MediaToolkit\CDN\CDNProvider;

/**
 * Data Transfer Object for storage configuration
 * Works with all S3-compatible providers
 */
readonly class StorageConfig
{
    public function __construct(
        public StorageProvider $provider,
        public string $accessKey,
        public string $secretKey,
        public string $bucket,
        public string $region = '',
        public string $accountId = '', // For Cloudflare R2
        public string $cdnUrl = '',
        public CDNProvider $cdnProvider = CDNProvider::NONE,
        public string $cloudflareZoneId = '',
        public string $cloudflareApiToken = '',
        public string $cloudfrontDistributionId = '',
    ) {}

    /**
     * Check if the configuration is valid
     */
    public function isValid(): bool
    {
        // Common validation
        if (empty($this->accessKey) || empty($this->secretKey) || empty($this->bucket)) {
            return false;
        }

        // Provider-specific validation
        return match ($this->provider) {
            StorageProvider::AWS_S3,
            StorageProvider::DIGITALOCEAN_SPACES,
            StorageProvider::BACKBLAZE_B2,
            StorageProvider::WASABI => !empty($this->region),
            StorageProvider::CLOUDFLARE_R2 => !empty($this->accountId),
        };
    }

    /**
     * Check if CDN is configured
     */
    public function hasCDN(): bool
    {
        return !empty($this->cdnUrl) && $this->cdnProvider !== CDNProvider::NONE;
    }

    /**
     * Check if provider requires CDN URL
     */
    public function requiresCdnUrl(): bool
    {
        return $this->provider->requires_cdn_url();
    }

    /**
     * Validate CDN requirement
     */
    public function isCdnRequirementMet(): bool
    {
        if (!$this->requiresCdnUrl()) {
            return true;
        }
        return !empty($this->cdnUrl);
    }

    /**
     * Get the S3 endpoint for this configuration
     */
    public function getEndpoint(): ?string
    {
        return $this->provider->get_endpoint($this->region, $this->accountId);
    }

    /**
     * Get region for SDK (R2 uses 'auto')
     */
    public function getEffectiveRegion(): string
    {
        if ($this->provider === StorageProvider::CLOUDFLARE_R2) {
            return 'auto';
        }
        return $this->region;
    }

    /**
     * Get direct storage URL (without CDN)
     */
    public function getDirectUrl(string $key): string
    {
        $key = ltrim($key, '/');

        return match ($this->provider) {
            StorageProvider::AWS_S3 => sprintf(
                'https://%s.s3.%s.amazonaws.com/%s',
                $this->bucket,
                $this->region,
                $key
            ),
            StorageProvider::CLOUDFLARE_R2 => sprintf(
                'https://%s.r2.cloudflarestorage.com/%s/%s',
                $this->accountId,
                $this->bucket,
                $key
            ),
            StorageProvider::DIGITALOCEAN_SPACES => sprintf(
                'https://%s.%s.digitaloceanspaces.com/%s',
                $this->bucket,
                $this->region,
                $key
            ),
            StorageProvider::BACKBLAZE_B2 => sprintf(
                'https://%s.s3.%s.backblazeb2.com/%s',
                $this->bucket,
                $this->region,
                $key
            ),
            StorageProvider::WASABI => sprintf(
                'https://%s.s3.%s.wasabisys.com/%s',
                $this->bucket,
                $this->region,
                $key
            ),
        };
    }

    /**
     * Get public URL (CDN if configured, otherwise direct)
     */
    public function getPublicUrl(string $key): string
    {
        if ($this->hasCDN()) {
            $baseUrl = rtrim($this->cdnUrl, '/');
            $key = ltrim($key, '/');
            return "{$baseUrl}/{$key}";
        }

        // R2 without CDN won't work publicly, but return the direct URL anyway
        return $this->getDirectUrl($key);
    }

    /**
     * Create from array (for loading from database)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: StorageProvider::tryFrom($data['provider'] ?? 'aws_s3') ?? StorageProvider::AWS_S3,
            accessKey: $data['access_key'] ?? '',
            secretKey: $data['secret_key'] ?? '',
            bucket: $data['bucket'] ?? '',
            region: $data['region'] ?? '',
            accountId: $data['account_id'] ?? '',
            cdnUrl: $data['cdn_url'] ?? '',
            cdnProvider: CDNProvider::tryFrom($data['cdn_provider'] ?? '') ?? CDNProvider::NONE,
            cloudflareZoneId: $data['cloudflare_zone_id'] ?? '',
            cloudflareApiToken: $data['cloudflare_api_token'] ?? '',
            cloudfrontDistributionId: $data['cloudfront_distribution_id'] ?? '',
        );
    }

    /**
     * Convert to array (for saving to database)
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'access_key' => $this->accessKey,
            'secret_key' => $this->secretKey,
            'bucket' => $this->bucket,
            'region' => $this->region,
            'account_id' => $this->accountId,
            'cdn_url' => $this->cdnUrl,
            'cdn_provider' => $this->cdnProvider->value,
            'cloudflare_zone_id' => $this->cloudflareZoneId,
            'cloudflare_api_token' => $this->cloudflareApiToken,
            'cloudfront_distribution_id' => $this->cloudfrontDistributionId,
        ];
    }
}

