<?php
/**
 * Admin Optimize page controller
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\Media\Image_Optimizer;
use Metodo\MediaToolkit\Media\Image_Resizer;
use Metodo\MediaToolkit\Core\Settings;

/**
 * Handles the Optimize admin page
 */
final class Admin_Optimize
{
    private ?Image_Optimizer $optimizer;
    private ?Image_Resizer $resizer;
    private ?Settings $settings;

    public function __construct(
        ?Image_Optimizer $optimizer = null,
        ?Image_Resizer $resizer = null,
        ?Settings $settings = null
    ) {
        $this->optimizer = $optimizer;
        $this->resizer = $resizer;
        $this->settings = $settings;
    }

    /**
     * Get optimizer instance
     */
    public function get_optimizer(): ?Image_Optimizer
    {
        return $this->optimizer;
    }

    /**
     * Get resizer instance
     */
    public function get_resizer(): ?Image_Resizer
    {
        return $this->resizer;
    }

    /**
     * Get settings instance
     */
    public function get_settings(): ?Settings
    {
        return $this->settings;
    }

    /**
     * Check if optimization is available
     */
    public function is_available(): bool
    {
        return $this->optimizer !== null;
    }

    /**
     * Get optimization stats
     */
    public function get_stats(): array
    {
        if ($this->optimizer === null) {
            return [
                'total_images' => 0,
                'optimized_images' => 0,
                'pending_images' => 0,
                'total_saved' => 0,
                'total_saved_formatted' => '0 B',
                'progress_percentage' => 0,
            ];
        }

        return $this->optimizer->get_stats();
    }

    /**
     * Get optimization settings
     */
    public function get_optimization_settings(): array
    {
        if ($this->optimizer === null) {
            return [
                'jpeg_quality' => 82,
                'png_compression' => 6,
                'strip_metadata' => true,
                'convert_to_webp' => false,
                'webp_quality' => 80,
                'skip_already_optimized' => true,
                'min_savings_percent' => 5,
                'max_file_size_mb' => 10,
            ];
        }

        return $this->optimizer->get_optimization_settings();
    }

    /**
     * Get server capabilities
     */
    public function get_server_capabilities(): array
    {
        if ($this->optimizer === null) {
            return [
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
                'webp_support' => false,
                'avif_support' => false,
                'max_memory' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ];
        }

        return $this->optimizer->get_server_capabilities();
    }

    /**
     * Get current state
     */
    public function get_state(): array
    {
        if ($this->optimizer === null) {
            return [
                'status' => 'idle',
                'total_files' => 0,
                'processed' => 0,
                'failed' => 0,
            ];
        }

        return $this->optimizer->get_state();
    }

    /**
     * Get resize settings
     */
    public function get_resize_settings(): array
    {
        if ($this->resizer === null) {
            return [
                'enabled' => false,
                'max_width' => 2560,
                'max_height' => 2560,
                'jpeg_quality' => 82,
                'convert_bmp_to_jpg' => true,
                'resize_existing' => false,
            ];
        }

        return $this->resizer->get_resize_settings();
    }

    /**
     * Get resize statistics
     */
    public function get_resize_stats(): array
    {
        if ($this->resizer === null) {
            return [
                'total_resized' => 0,
                'total_bytes_saved' => 0,
                'total_bytes_saved_formatted' => '0 B',
                'total_bmp_converted' => 0,
                'last_resize_at' => null,
            ];
        }

        return $this->resizer->get_stats();
    }

    /**
     * Check if resizer is available
     */
    public function is_resizer_available(): bool
    {
        return $this->resizer !== null;
    }
}

