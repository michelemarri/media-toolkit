<?php
/**
 * Storage Provider Enum
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

/**
 * Enum for supported storage providers
 */
enum StorageProvider: string
{
    case AWS_S3 = 'aws_s3';
    case CLOUDFLARE_R2 = 'cloudflare_r2';
    case DIGITALOCEAN_SPACES = 'digitalocean_spaces';
    case BACKBLAZE_B2 = 'backblaze_b2';
    case WASABI = 'wasabi';

    /**
     * Get human-readable label for the provider
     */
    public function label(): string
    {
        return match ($this) {
            self::AWS_S3 => 'Amazon S3',
            self::CLOUDFLARE_R2 => 'Cloudflare R2',
            self::DIGITALOCEAN_SPACES => 'DigitalOcean Spaces',
            self::BACKBLAZE_B2 => 'Backblaze B2',
            self::WASABI => 'Wasabi',
        };
    }

    /**
     * Get provider description
     */
    public function description(): string
    {
        return match ($this) {
            self::AWS_S3 => 'Industry standard object storage with global CDN options',
            self::CLOUDFLARE_R2 => 'Zero egress fees, requires CDN URL for public access',
            self::DIGITALOCEAN_SPACES => 'Simple S3-compatible storage with built-in CDN',
            self::BACKBLAZE_B2 => 'Cost-effective storage with S3 compatibility',
            self::WASABI => 'Hot cloud storage with no egress fees',
        };
    }

    /**
     * Check if provider requires CDN URL for public access
     */
    public function requires_cdn_url(): bool
    {
        return match ($this) {
            self::CLOUDFLARE_R2 => true,
            default => false,
        };
    }

    /**
     * Check if provider uses standard AWS regions
     */
    public function uses_aws_regions(): bool
    {
        return match ($this) {
            self::AWS_S3 => true,
            self::CLOUDFLARE_R2 => false,
            self::DIGITALOCEAN_SPACES => false,
            self::BACKBLAZE_B2 => false,
            self::WASABI => false,
        };
    }

    /**
     * Get available regions for the provider
     * 
     * @return array<string, string> region code => label
     */
    public function get_regions(): array
    {
        return match ($this) {
            self::AWS_S3 => [
                'us-east-1' => 'US East (N. Virginia)',
                'us-east-2' => 'US East (Ohio)',
                'us-west-1' => 'US West (N. California)',
                'us-west-2' => 'US West (Oregon)',
                'eu-west-1' => 'EU (Ireland)',
                'eu-west-2' => 'EU (London)',
                'eu-west-3' => 'EU (Paris)',
                'eu-central-1' => 'EU (Frankfurt)',
                'eu-south-1' => 'EU (Milan)',
                'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                'ap-southeast-2' => 'Asia Pacific (Sydney)',
            ],
            self::CLOUDFLARE_R2 => [
                'auto' => 'Automatic',
            ],
            self::DIGITALOCEAN_SPACES => [
                'nyc3' => 'New York 3',
                'sfo3' => 'San Francisco 3',
                'ams3' => 'Amsterdam 3',
                'sgp1' => 'Singapore 1',
                'fra1' => 'Frankfurt 1',
                'syd1' => 'Sydney 1',
            ],
            self::BACKBLAZE_B2 => [
                'us-west-004' => 'US West',
                'eu-central-003' => 'EU Central',
            ],
            self::WASABI => [
                'us-east-1' => 'US East 1 (N. Virginia)',
                'us-east-2' => 'US East 2 (N. Virginia)',
                'us-central-1' => 'US Central 1 (Texas)',
                'us-west-1' => 'US West 1 (Oregon)',
                'eu-central-1' => 'EU Central 1 (Amsterdam)',
                'eu-central-2' => 'EU Central 2 (Frankfurt)',
                'eu-west-1' => 'EU West 1 (London)',
                'eu-west-2' => 'EU West 2 (Paris)',
                'ap-northeast-1' => 'AP Northeast 1 (Tokyo)',
                'ap-northeast-2' => 'AP Northeast 2 (Osaka)',
                'ap-southeast-1' => 'AP Southeast 1 (Singapore)',
                'ap-southeast-2' => 'AP Southeast 2 (Sydney)',
            ],
        };
    }

    /**
     * Get the S3 endpoint URL for this provider
     * 
     * @param string $region Region code
     * @param string $account_id Account ID (for R2)
     * @return string|null Endpoint URL or null for AWS S3 (uses default)
     */
    public function get_endpoint(string $region, string $account_id = ''): ?string
    {
        return match ($this) {
            self::AWS_S3 => null, // Uses default AWS SDK endpoint
            self::CLOUDFLARE_R2 => "https://{$account_id}.r2.cloudflarestorage.com",
            self::DIGITALOCEAN_SPACES => "https://{$region}.digitaloceanspaces.com",
            self::BACKBLAZE_B2 => "https://s3.{$region}.backblazeb2.com",
            self::WASABI => "https://s3.{$region}.wasabisys.com",
        };
    }

    /**
     * Get credential field definitions for this provider
     * 
     * @return array<string, array{label: string, type: string, required: bool, placeholder: string}>
     */
    public function get_credential_fields(): array
    {
        $common = [
            'access_key' => [
                'label' => 'Access Key',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Your access key',
            ],
            'secret_key' => [
                'label' => 'Secret Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Your secret key',
            ],
            'bucket' => [
                'label' => 'Bucket Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'my-bucket',
            ],
        ];

        return match ($this) {
            self::AWS_S3 => array_merge($common, [
                'region' => [
                    'label' => 'Region',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select region',
                ],
            ]),
            self::CLOUDFLARE_R2 => [
                'account_id' => [
                    'label' => 'Account ID',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Your Cloudflare Account ID',
                ],
                'access_key' => $common['access_key'],
                'secret_key' => $common['secret_key'],
                'bucket' => $common['bucket'],
            ],
            self::DIGITALOCEAN_SPACES => array_merge($common, [
                'region' => [
                    'label' => 'Region',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select region',
                ],
            ]),
            self::BACKBLAZE_B2 => [
                'access_key' => [
                    'label' => 'Application Key ID',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Your Key ID',
                ],
                'secret_key' => [
                    'label' => 'Application Key',
                    'type' => 'password',
                    'required' => true,
                    'placeholder' => 'Your Application Key',
                ],
                'region' => [
                    'label' => 'Region',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select region',
                ],
                'bucket' => $common['bucket'],
            ],
            self::WASABI => array_merge($common, [
                'region' => [
                    'label' => 'Region',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select region',
                ],
            ]),
        };
    }

    /**
     * Get all providers as array for dropdowns
     * 
     * @return array<string, string>
     */
    public static function all_as_options(): array
    {
        $options = [];
        foreach (self::cases() as $provider) {
            $options[$provider->value] = $provider->label();
        }
        return $options;
    }
}

