<?php
/**
 * Conversion Manager
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Storage\StorageInterface;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Optimizer\Converters\WebPConverter;
use Metodo\MediaToolkit\Optimizer\Converters\AvifConverter;
use Metodo\MediaToolkit\Optimizer\Converters\ConversionResult;

/**
 * Manages image format conversions (WebP, AVIF) with cloud storage integration
 */
final class ConversionManager
{
    private const SETTINGS_KEY = 'media_toolkit_conversion_settings';
    private const WEBP_META_KEY = '_media_toolkit_webp_key';
    private const AVIF_META_KEY = '_media_toolkit_avif_key';

    private ?Logger $logger;
    private ?Settings $settings;
    private ?StorageInterface $storage;
    private ?History $history;
    private OptimizerManager $optimizerManager;
    private ?WebPConverter $webpConverter = null;
    private ?AvifConverter $avifConverter = null;

    public function __construct(
        OptimizerManager $optimizerManager,
        ?Logger $logger = null,
        ?Settings $settings = null,
        ?StorageInterface $storage = null,
        ?History $history = null
    ) {
        $this->optimizerManager = $optimizerManager;
        $this->logger = $logger;
        $this->settings = $settings;
        $this->storage = $storage;
        $this->history = $history;
    }

    /**
     * Get conversion settings
     *
     * @return array{webp_enabled: bool, avif_enabled: bool, webp_quality: int, avif_quality: int, keep_original: bool}
     */
    public function getSettings(): array
    {
        $defaults = [
            'webp_enabled' => false,
            'avif_enabled' => false,
            'webp_quality' => 80,
            'avif_quality' => 50,
            'keep_original' => true, // Always keep original format alongside converted
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /**
     * Save conversion settings
     */
    public function saveSettings(array $settings): bool
    {
        $sanitized = [
            'webp_enabled' => (bool) ($settings['webp_enabled'] ?? false),
            'avif_enabled' => (bool) ($settings['avif_enabled'] ?? false),
            'webp_quality' => max(1, min(100, (int) ($settings['webp_quality'] ?? 80))),
            'avif_quality' => max(1, min(100, (int) ($settings['avif_quality'] ?? 50))),
            'keep_original' => true, // Always true, we don't delete originals
        ];

        return update_option(self::SETTINGS_KEY, $sanitized);
    }

    /**
     * Get WebP converter instance
     */
    public function getWebPConverter(): WebPConverter
    {
        if ($this->webpConverter === null) {
            $this->webpConverter = new WebPConverter($this->optimizerManager, $this->logger);
        }

        return $this->webpConverter;
    }

    /**
     * Get AVIF converter instance
     */
    public function getAvifConverter(): AvifConverter
    {
        if ($this->avifConverter === null) {
            $this->avifConverter = new AvifConverter($this->optimizerManager, $this->logger);
        }

        return $this->avifConverter;
    }

    /**
     * Check if WebP conversion is available
     */
    public function isWebPAvailable(): bool
    {
        return $this->getWebPConverter()->isAvailable();
    }

    /**
     * Check if AVIF conversion is available
     */
    public function isAvifAvailable(): bool
    {
        return $this->getAvifConverter()->isAvailable();
    }

    /**
     * Convert an attachment to modern formats based on settings
     *
     * @param int $attachmentId Attachment ID
     * @param string|null $filePath Optional file path (uses get_attached_file if null)
     * @return array{webp?: ConversionResult, avif?: ConversionResult}
     */
    public function convertAttachment(int $attachmentId, ?string $filePath = null): array
    {
        $settings = $this->getSettings();
        $results = [];

        if ($filePath === null) {
            $filePath = get_attached_file($attachmentId);
        }

        if ($filePath === false || !file_exists($filePath)) {
            return $results;
        }

        $mimeType = get_post_mime_type($attachmentId);
        
        // Only convert JPEG and PNG
        if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return $results;
        }

        // WebP conversion
        if ($settings['webp_enabled'] && $this->isWebPAvailable()) {
            $webpResult = $this->convertToWebP($attachmentId, $filePath, $settings);
            if ($webpResult !== null) {
                $results['webp'] = $webpResult;
            }
        }

        // AVIF conversion
        if ($settings['avif_enabled'] && $this->isAvifAvailable()) {
            $avifResult = $this->convertToAvif($attachmentId, $filePath, $settings);
            if ($avifResult !== null) {
                $results['avif'] = $avifResult;
            }
        }

        return $results;
    }

    /**
     * Convert attachment to WebP
     */
    private function convertToWebP(int $attachmentId, string $filePath, array $settings): ?ConversionResult
    {
        $webpPath = $this->generateConversionPath($filePath, 'webp');
        
        // Check if already converted
        $existingKey = get_post_meta($attachmentId, self::WEBP_META_KEY, true);
        if (!empty($existingKey)) {
            $this->log('info', "WebP already exists for attachment {$attachmentId}");
            return null;
        }

        $converter = $this->getWebPConverter();
        $result = $converter->convert($filePath, $webpPath, [
            'quality' => $settings['webp_quality'],
        ]);

        if (!$result->success) {
            $this->log('error', "WebP conversion failed: " . ($result->error ?? 'Unknown error'));
            return $result;
        }

        // Upload to cloud storage if configured
        $webpKey = null;
        $s3Key = get_post_meta($attachmentId, '_media_toolkit_key', true);
        
        if (!empty($s3Key) && $this->storage !== null) {
            $webpKey = $this->generateConversionKey($s3Key, 'webp');
            
            // Upload the WebP file
            $uploadResult = $this->uploadConvertedFile($webpPath, $webpKey, $attachmentId);
            
            if ($uploadResult) {
                update_post_meta($attachmentId, self::WEBP_META_KEY, $webpKey);
                $this->log('success', "WebP uploaded to cloud: {$webpKey}");
            } else {
                $this->log('warning', "WebP created locally but failed to upload to cloud");
            }
        } else {
            // Save local path reference
            update_post_meta($attachmentId, self::WEBP_META_KEY, $webpPath);
        }

        // Record in history
        $this->history?->record(
            HistoryAction::CONVERTED_WEBP,
            $attachmentId,
            $webpPath,
            $webpKey,
            $result->convertedSize,
            [
                'original_size' => $result->originalSize,
                'percent_saved' => $result->getPercentSaved(),
            ]
        );

        $this->log('success', "Created WebP for attachment {$attachmentId}: saved " . $result->getPercentSaved() . "%");

        return $result;
    }

    /**
     * Convert attachment to AVIF
     */
    private function convertToAvif(int $attachmentId, string $filePath, array $settings): ?ConversionResult
    {
        $avifPath = $this->generateConversionPath($filePath, 'avif');
        
        // Check if already converted
        $existingKey = get_post_meta($attachmentId, self::AVIF_META_KEY, true);
        if (!empty($existingKey)) {
            $this->log('info', "AVIF already exists for attachment {$attachmentId}");
            return null;
        }

        $converter = $this->getAvifConverter();
        $result = $converter->convert($filePath, $avifPath, [
            'quality' => $settings['avif_quality'],
        ]);

        if (!$result->success) {
            $this->log('error', "AVIF conversion failed: " . ($result->error ?? 'Unknown error'));
            return $result;
        }

        // Upload to cloud storage if configured
        $avifKey = null;
        $s3Key = get_post_meta($attachmentId, '_media_toolkit_key', true);
        
        if (!empty($s3Key) && $this->storage !== null) {
            $avifKey = $this->generateConversionKey($s3Key, 'avif');
            
            // Upload the AVIF file
            $uploadResult = $this->uploadConvertedFile($avifPath, $avifKey, $attachmentId);
            
            if ($uploadResult) {
                update_post_meta($attachmentId, self::AVIF_META_KEY, $avifKey);
                $this->log('success', "AVIF uploaded to cloud: {$avifKey}");
            } else {
                $this->log('warning', "AVIF created locally but failed to upload to cloud");
            }
        } else {
            // Save local path reference
            update_post_meta($attachmentId, self::AVIF_META_KEY, $avifPath);
        }

        // Record in history
        $this->history?->record(
            HistoryAction::CONVERTED_AVIF,
            $attachmentId,
            $avifPath,
            $avifKey,
            $result->convertedSize,
            [
                'original_size' => $result->originalSize,
                'percent_saved' => $result->getPercentSaved(),
            ]
        );

        $this->log('success', "Created AVIF for attachment {$attachmentId}: saved " . $result->getPercentSaved() . "%");

        return $result;
    }

    /**
     * Upload converted file to cloud storage
     */
    private function uploadConvertedFile(string $localPath, string $key, int $attachmentId): bool
    {
        if ($this->storage === null) {
            return false;
        }

        // The storage interface expects the file path, we need to upload with custom key
        // For now, we'll use a workaround by temporarily setting the key
        try {
            $result = $this->storage->upload_file($localPath, $attachmentId);
            return $result->success;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to upload converted file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get WebP URL for an attachment
     */
    public function getWebPUrl(int $attachmentId): ?string
    {
        $webpKey = get_post_meta($attachmentId, self::WEBP_META_KEY, true);
        
        if (empty($webpKey)) {
            return null;
        }

        // If it's a full path (local), convert to URL
        if (strpos($webpKey, '/') === 0) {
            $uploadDir = wp_upload_dir();
            return str_replace($uploadDir['basedir'], $uploadDir['baseurl'], $webpKey);
        }

        // If it's a storage key, use CDN/storage URL
        if ($this->settings !== null) {
            $cdnUrl = $this->settings->get_cdn_url();
            if ($cdnUrl !== null) {
                return rtrim($cdnUrl, '/') . '/' . ltrim($webpKey, '/');
            }
        }

        return null;
    }

    /**
     * Get AVIF URL for an attachment
     */
    public function getAvifUrl(int $attachmentId): ?string
    {
        $avifKey = get_post_meta($attachmentId, self::AVIF_META_KEY, true);
        
        if (empty($avifKey)) {
            return null;
        }

        // If it's a full path (local), convert to URL
        if (strpos($avifKey, '/') === 0) {
            $uploadDir = wp_upload_dir();
            return str_replace($uploadDir['basedir'], $uploadDir['baseurl'], $avifKey);
        }

        // If it's a storage key, use CDN/storage URL
        if ($this->settings !== null) {
            $cdnUrl = $this->settings->get_cdn_url();
            if ($cdnUrl !== null) {
                return rtrim($cdnUrl, '/') . '/' . ltrim($avifKey, '/');
            }
        }

        return null;
    }

    /**
     * Delete converted versions for an attachment
     */
    public function deleteConvertedVersions(int $attachmentId): void
    {
        // Delete WebP
        $webpKey = get_post_meta($attachmentId, self::WEBP_META_KEY, true);
        if (!empty($webpKey)) {
            if (strpos($webpKey, '/') === 0 && file_exists($webpKey)) {
                @unlink($webpKey);
            } elseif ($this->storage !== null) {
                $this->storage->delete_file($webpKey, $attachmentId);
            }
            delete_post_meta($attachmentId, self::WEBP_META_KEY);
        }

        // Delete AVIF
        $avifKey = get_post_meta($attachmentId, self::AVIF_META_KEY, true);
        if (!empty($avifKey)) {
            if (strpos($avifKey, '/') === 0 && file_exists($avifKey)) {
                @unlink($avifKey);
            } elseif ($this->storage !== null) {
                $this->storage->delete_file($avifKey, $attachmentId);
            }
            delete_post_meta($attachmentId, self::AVIF_META_KEY);
        }
    }

    /**
     * Generate conversion file path
     * photo.jpg -> photo.webp
     */
    private function generateConversionPath(string $originalPath, string $format): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;
    }

    /**
     * Generate conversion storage key
     * uploads/2024/01/photo.jpg -> uploads/2024/01/photo.webp
     */
    private function generateConversionKey(string $originalKey, string $format): string
    {
        $pathInfo = pathinfo($originalKey);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;
    }

    /**
     * Get conversion statistics
     */
    public function getStats(): array
    {
        global $wpdb;

        $webpCount = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::WEBP_META_KEY
            )
        );

        $avifCount = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::AVIF_META_KEY
            )
        );

        return [
            'webp_count' => $webpCount,
            'avif_count' => $avifCount,
            'webp_available' => $this->isWebPAvailable(),
            'avif_available' => $this->isAvifAvailable(),
        ];
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        match ($level) {
            'info' => $this->logger->info('conversion', $message),
            'success' => $this->logger->success('conversion', $message),
            'warning' => $this->logger->warning('conversion', $message),
            'error' => $this->logger->error('conversion', $message),
            default => $this->logger->info('conversion', $message),
        };
    }
}

