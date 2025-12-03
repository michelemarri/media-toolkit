<?php
/**
 * Storage Factory - Creates appropriate storage provider instance
 *
 * @package Metodo\MediaToolkit\Storage
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Storage;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Error\Error_Handler;
use Metodo\MediaToolkit\Storage\Providers\AwsS3;
use Metodo\MediaToolkit\Storage\Providers\CloudflareR2;
use Metodo\MediaToolkit\Storage\Providers\DigitalOceanSpaces;
use Metodo\MediaToolkit\Storage\Providers\BackblazeB2;
use Metodo\MediaToolkit\Storage\Providers\Wasabi;

/**
 * Factory class for creating storage provider instances
 */
final class StorageFactory
{
    /**
     * Create a storage provider instance based on configuration
     *
     * @param Settings $settings Plugin settings
     * @param Error_Handler $error_handler Error handler
     * @param Logger $logger Logger
     * @return StorageInterface|null Storage provider or null if not configured
     */
    public static function create(
        Settings $settings,
        Error_Handler $error_handler,
        Logger $logger
    ): ?StorageInterface {
        $config = $settings->get_storage_config();

        if ($config === null || !$config->isValid()) {
            return null;
        }

        return self::createFromConfig($config, $settings, $error_handler, $logger);
    }

    /**
     * Create a storage provider instance from a specific config
     *
     * @param StorageConfig $config Storage configuration
     * @param Settings $settings Plugin settings
     * @param Error_Handler $error_handler Error handler
     * @param Logger $logger Logger
     * @return StorageInterface Storage provider
     */
    public static function createFromConfig(
        StorageConfig $config,
        Settings $settings,
        Error_Handler $error_handler,
        Logger $logger
    ): StorageInterface {
        return match ($config->provider) {
            StorageProvider::AWS_S3 => new AwsS3($settings, $error_handler, $logger, $config),
            StorageProvider::CLOUDFLARE_R2 => new CloudflareR2($settings, $error_handler, $logger, $config),
            StorageProvider::DIGITALOCEAN_SPACES => new DigitalOceanSpaces($settings, $error_handler, $logger, $config),
            StorageProvider::BACKBLAZE_B2 => new BackblazeB2($settings, $error_handler, $logger, $config),
            StorageProvider::WASABI => new Wasabi($settings, $error_handler, $logger, $config),
        };
    }

    /**
     * Create a storage provider for a specific provider type (for migration)
     *
     * @param StorageProvider $provider Provider type
     * @param array $credentials Credentials array
     * @param Settings $settings Plugin settings
     * @param Error_Handler $error_handler Error handler
     * @param Logger $logger Logger
     * @return StorageInterface|null Storage provider or null if invalid config
     */
    public static function createForProvider(
        StorageProvider $provider,
        array $credentials,
        Settings $settings,
        Error_Handler $error_handler,
        Logger $logger
    ): ?StorageInterface {
        $config = StorageConfig::fromArray(array_merge(
            $credentials,
            ['provider' => $provider->value]
        ));

        if (!$config->isValid()) {
            return null;
        }

        return self::createFromConfig($config, $settings, $error_handler, $logger);
    }

    /**
     * Get all available providers
     *
     * @return array<StorageProvider>
     */
    public static function getAvailableProviders(): array
    {
        return StorageProvider::cases();
    }

    /**
     * Get provider info for UI display
     *
     * @return array<string, array{label: string, description: string, requires_cdn: bool, regions: array}>
     */
    public static function getProvidersInfo(): array
    {
        $info = [];

        foreach (StorageProvider::cases() as $provider) {
            $info[$provider->value] = [
                'label' => $provider->label(),
                'description' => $provider->description(),
                'requires_cdn' => $provider->requires_cdn_url(),
                'regions' => $provider->get_regions(),
                'fields' => $provider->get_credential_fields(),
            ];
        }

        return $info;
    }
}

