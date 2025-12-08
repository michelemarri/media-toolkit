<?php
/**
 * Optimizer Manager
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Optimizer\Drivers\GDOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\ImagickOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\MozjpegOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\JpegoptimOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\PngquantOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\OptipngOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\OxipngOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\CwebpOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\AvifencOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\GifsicleOptimizer;
use Metodo\MediaToolkit\Optimizer\Drivers\SvgoOptimizer;

/**
 * Manages all optimizer drivers and selects the best one for each format
 */
final class OptimizerManager
{
    /** @var array<string, OptimizerInterface> All registered optimizers */
    private array $optimizers = [];

    /** @var array<string, OptimizerInterface[]> Optimizers grouped by format */
    private array $optimizersByFormat = [];

    /** @var array<string, array{available: bool, version: ?string}>|null Cached capabilities */
    private ?array $capabilitiesCache = null;

    private ?Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->registerBuiltInOptimizers();
    }

    /**
     * Register all built-in optimizers
     */
    private function registerBuiltInOptimizers(): void
    {
        // Register optimizers in priority order (lower priority first, higher priority last)
        // This way, when we sort by priority, higher priority comes first
        
        // Built-in PHP optimizers (lowest priority, always available)
        $this->register(new GDOptimizer($this->logger));
        $this->register(new ImagickOptimizer($this->logger));
        
        // CLI-based optimizers (higher priority, may not be available)
        // JPEG
        $this->register(new JpegoptimOptimizer($this->logger));
        $this->register(new MozjpegOptimizer($this->logger));
        
        // PNG
        $this->register(new OptipngOptimizer($this->logger));
        $this->register(new OxipngOptimizer($this->logger));
        $this->register(new PngquantOptimizer($this->logger));
        
        // WebP
        $this->register(new CwebpOptimizer($this->logger));
        
        // AVIF
        $this->register(new AvifencOptimizer($this->logger));
        
        // GIF
        $this->register(new GifsicleOptimizer($this->logger));
        
        // SVG
        $this->register(new SvgoOptimizer($this->logger));
    }

    /**
     * Register an optimizer
     */
    public function register(OptimizerInterface $optimizer): void
    {
        $this->optimizers[$optimizer->getId()] = $optimizer;
        
        // Index by supported formats
        foreach ($optimizer->getSupportedFormats() as $format) {
            if (!isset($this->optimizersByFormat[$format])) {
                $this->optimizersByFormat[$format] = [];
            }
            $this->optimizersByFormat[$format][] = $optimizer;
        }

        // Clear capabilities cache
        $this->capabilitiesCache = null;
    }

    /**
     * Get the best available optimizer for a format
     *
     * @param string $format Image format (e.g., 'jpeg', 'png')
     * @return OptimizerInterface|null Best available optimizer or null if none available
     */
    public function getBestOptimizer(string $format): ?OptimizerInterface
    {
        $format = $this->normalizeFormat($format);
        
        if (!isset($this->optimizersByFormat[$format])) {
            return null;
        }

        // Sort by priority (highest first) and return first available
        $optimizers = $this->optimizersByFormat[$format];
        usort($optimizers, fn($a, $b) => $b->getPriority() - $a->getPriority());

        foreach ($optimizers as $optimizer) {
            if ($optimizer->isAvailable()) {
                return $optimizer;
            }
        }

        return null;
    }

    /**
     * Get all available optimizers for a format
     *
     * @param string $format Image format
     * @return OptimizerInterface[] Available optimizers sorted by priority
     */
    public function getAvailableOptimizers(string $format): array
    {
        $format = $this->normalizeFormat($format);
        
        if (!isset($this->optimizersByFormat[$format])) {
            return [];
        }

        $available = array_filter(
            $this->optimizersByFormat[$format],
            fn($optimizer) => $optimizer->isAvailable()
        );

        // Sort by priority (highest first)
        usort($available, fn($a, $b) => $b->getPriority() - $a->getPriority());

        return $available;
    }

    /**
     * Get a specific optimizer by ID
     */
    public function getOptimizer(string $id): ?OptimizerInterface
    {
        return $this->optimizers[$id] ?? null;
    }

    /**
     * Get all registered optimizers
     *
     * @return array<string, OptimizerInterface>
     */
    public function getAllOptimizers(): array
    {
        return $this->optimizers;
    }

    /**
     * Optimize an image using the best available optimizer
     *
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path to save optimized image
     * @param array<string, mixed> $options Optimization options
     * @return OptimizerResult Optimization result
     */
    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        $format = $this->getFormatFromPath($sourcePath);
        $optimizer = $this->getBestOptimizer($format);

        if ($optimizer === null) {
            return OptimizerResult::failure(
                "No optimizer available for format: {$format}",
                'none',
                $this->getFileSize($sourcePath)
            );
        }

        $this->log('info', "Using {$optimizer->getName()} for {$format} optimization");

        return $optimizer->optimize($sourcePath, $destinationPath, $options);
    }

    /**
     * Get server capabilities - which optimizers are available
     *
     * @return array<string, array{id: string, name: string, available: bool, version: ?string, priority: int, formats: array, install_instructions: string}>
     */
    public function getCapabilities(): array
    {
        if ($this->capabilitiesCache !== null) {
            return $this->capabilitiesCache;
        }

        $capabilities = [];

        foreach ($this->optimizers as $id => $optimizer) {
            $capabilities[$id] = [
                'id' => $id,
                'name' => $optimizer->getName(),
                'available' => $optimizer->isAvailable(),
                'version' => $optimizer->isAvailable() ? $optimizer->getVersion() : null,
                'priority' => $optimizer->getPriority(),
                'formats' => $optimizer->getSupportedFormats(),
                'binary_path' => $optimizer->getBinaryPath(),
                'install_instructions' => $optimizer->getInstallInstructions(),
            ];
        }

        $this->capabilitiesCache = $capabilities;

        return $capabilities;
    }

    /**
     * Get capabilities summary grouped by format
     *
     * @return array<string, array{best: ?string, available: array<string>, missing: array<string>}>
     */
    public function getCapabilitiesByFormat(): array
    {
        $summary = [];
        $allFormats = ['jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

        foreach ($allFormats as $format) {
            $available = [];
            $missing = [];
            $best = null;

            if (isset($this->optimizersByFormat[$format])) {
                $optimizers = $this->optimizersByFormat[$format];
                usort($optimizers, fn($a, $b) => $b->getPriority() - $a->getPriority());

                foreach ($optimizers as $optimizer) {
                    if ($optimizer->isAvailable()) {
                        $available[] = $optimizer->getId();
                        if ($best === null) {
                            $best = $optimizer->getId();
                        }
                    } else {
                        $missing[] = $optimizer->getId();
                    }
                }
            }

            $summary[$format] = [
                'best' => $best,
                'available' => $available,
                'missing' => $missing,
            ];
        }

        return $summary;
    }

    /**
     * Get recommendations for improving optimization
     *
     * @return array<array{tool: string, benefit: string, install: string}>
     */
    public function getRecommendations(): array
    {
        $recommendations = [];
        $capabilities = $this->getCapabilities();

        // JPEG recommendations
        if (!($capabilities['mozjpeg']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'mozjpeg',
                'benefit' => __('Install mozjpeg for 5-15% better JPEG compression', 'media-toolkit'),
                'install' => $this->optimizers['mozjpeg']->getInstallInstructions(),
            ];
        }

        // PNG recommendations  
        if (!($capabilities['pngquant']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'pngquant',
                'benefit' => __('Install pngquant for up to 70% smaller PNG files (lossy)', 'media-toolkit'),
                'install' => $this->optimizers['pngquant']->getInstallInstructions(),
            ];
        }

        // WebP recommendations
        if (!($capabilities['cwebp']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'cwebp',
                'benefit' => __('Install cwebp for native WebP support', 'media-toolkit'),
                'install' => $this->optimizers['cwebp']->getInstallInstructions(),
            ];
        }

        // AVIF recommendations
        if (!($capabilities['avifenc']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'avifenc',
                'benefit' => __('Install avifenc for AVIF support (20-50% smaller than WebP)', 'media-toolkit'),
                'install' => $this->optimizers['avifenc']->getInstallInstructions(),
            ];
        }

        // GIF recommendations
        if (!($capabilities['gifsicle']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'gifsicle',
                'benefit' => __('Install gifsicle for GIF optimization (supports animated GIFs)', 'media-toolkit'),
                'install' => $this->optimizers['gifsicle']->getInstallInstructions(),
            ];
        }

        // SVG recommendations
        if (!($capabilities['svgo']['available'] ?? false)) {
            $recommendations[] = [
                'tool' => 'svgo',
                'benefit' => __('Install svgo for SVG optimization', 'media-toolkit'),
                'install' => $this->optimizers['svgo']->getInstallInstructions(),
            ];
        }

        return $recommendations;
    }

    /**
     * Check if any optimizer is available
     */
    public function hasAnyOptimizer(): bool
    {
        foreach ($this->optimizers as $optimizer) {
            if ($optimizer->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if optimizers are available for a specific format
     */
    public function canOptimize(string $format): bool
    {
        return $this->getBestOptimizer($format) !== null;
    }

    /**
     * Normalize format string
     */
    private function normalizeFormat(string $format): string
    {
        $format = strtolower($format);

        return match ($format) {
            'jpg' => 'jpeg',
            default => $format,
        };
    }

    /**
     * Get format from file path
     */
    private function getFormatFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->normalizeFormat($extension);
    }

    /**
     * Get file size safely
     */
    private function getFileSize(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        clearstatcache(true, $path);
        $size = filesize($path);

        return $size !== false ? $size : 0;
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        match ($level) {
            'info' => $this->logger->info('optimizer_manager', $message, null, null, $context),
            'warning' => $this->logger->warning('optimizer_manager', $message, null, null, $context),
            'error' => $this->logger->error('optimizer_manager', $message, null, null, $context),
            default => $this->logger->info('optimizer_manager', $message, null, null, $context),
        };
    }

    /**
     * Clear capabilities cache
     */
    public function clearCache(): void
    {
        $this->capabilitiesCache = null;
    }
}

