<?php
/**
 * Admin Optimize page controller
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\Media\Image_Optimizer;
use Metodo\MediaToolkit\Core\Settings;

/**
 * Handles the Optimize admin page
 */
final class Admin_Optimize
{
    private ?Image_Optimizer $optimizer;
    private ?Settings $settings;

    public function __construct(
        ?Image_Optimizer $optimizer = null,
        ?Settings $settings = null
    ) {
        $this->optimizer = $optimizer;
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
}

