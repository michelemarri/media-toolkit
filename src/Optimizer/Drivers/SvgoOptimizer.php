<?php
/**
 * SVGO Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer\Drivers;

use Metodo\MediaToolkit\Optimizer\AbstractOptimizer;
use Metodo\MediaToolkit\Optimizer\OptimizerResult;

/**
 * Image optimizer using SVGO (Node.js SVG Optimizer)
 * Industry standard for SVG optimization
 */
final class SvgoOptimizer extends AbstractOptimizer
{
    protected array $supportedFormats = ['svg'];
    protected int $priority = 80; // Only optimizer for SVG

    public function getId(): string
    {
        return 'svgo';
    }

    public function getName(): string
    {
        return 'SVGO';
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (!$this->canExecuteCommands()) {
            $this->available = false;
            return false;
        }

        // SVGO can be installed globally or via npx
        $this->binaryPath = $this->findSvgo();
        $this->available = $this->binaryPath !== null;

        return $this->available;
    }

    /**
     * Find SVGO binary
     */
    private function findSvgo(): ?string
    {
        // Try global installation first
        $svgo = $this->findBinary('svgo');
        if ($svgo !== null) {
            return $svgo;
        }

        // Try common npm global paths
        $possiblePaths = [
            '/usr/local/bin/svgo',
            '/usr/bin/svgo',
            getenv('HOME') . '/.npm-global/bin/svgo',
            getenv('HOME') . '/node_modules/.bin/svgo',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Check if npx is available (can run svgo without global install)
        $npx = $this->findBinary('npx');
        if ($npx !== null) {
            // Verify svgo is available via npx
            $result = $this->executeCommand($npx, ['--yes', 'svgo', '--version']);
            if ($result['return_code'] === 0) {
                return 'npx --yes svgo';
            }
        }

        return null;
    }

    protected function detectVersion(): ?string
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return null;
        }

        // Handle npx case
        if (str_starts_with($this->binaryPath, 'npx')) {
            $result = $this->executeCommand('npx', ['--yes', 'svgo', '--version']);
        } else {
            $result = $this->executeCommand($this->binaryPath, ['--version']);
        }
        
        // 3.2.0
        if (preg_match('/(\d+\.\d+\.\d+)/', $result['output'], $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function optimize(string $sourcePath, string $destinationPath, array $options = []): OptimizerResult
    {
        if (!$this->isAvailable() || $this->binaryPath === null) {
            return OptimizerResult::failure('SVGO is not available', $this->getId());
        }

        $error = $this->validateSourceFile($sourcePath);
        if ($error !== null) {
            return OptimizerResult::failure($error, $this->getId());
        }

        if (!$this->ensureDirectoryExists($destinationPath)) {
            return OptimizerResult::failure('Cannot create destination directory', $this->getId());
        }

        $originalSize = $this->getFileSize($sourcePath);

        // Build command
        if (str_starts_with($this->binaryPath, 'npx')) {
            $result = $this->runWithNpx($sourcePath, $destinationPath, $options);
        } else {
            $result = $this->runDirect($sourcePath, $destinationPath, $options);
        }

        if (!$result['success']) {
            return OptimizerResult::failure('SVGO failed: ' . ($result['error'] ?? 'Unknown error'), $this->getId(), $originalSize);
        }

        $optimizedSize = $this->getFileSize($destinationPath);

        $this->log('success', "SVGO optimized: {$originalSize} -> {$optimizedSize}");

        return OptimizerResult::success($originalSize, $optimizedSize, $this->getId());
    }

    /**
     * Run SVGO directly
     */
    private function runDirect(string $sourcePath, string $destinationPath, array $options): array
    {
        $args = $this->buildArguments($options);
        
        // Input file
        $args[] = '--input';
        $args[] = $sourcePath;
        
        // Output file
        $args[] = '--output';
        $args[] = $destinationPath;

        $result = $this->executeCommand($this->binaryPath, $args);

        return [
            'success' => $result['return_code'] === 0,
            'error' => $result['output'],
        ];
    }

    /**
     * Run SVGO via npx
     */
    private function runWithNpx(string $sourcePath, string $destinationPath, array $options): array
    {
        $args = ['--yes', 'svgo'];
        $args = array_merge($args, $this->buildArguments($options));
        
        // Input file
        $args[] = '--input';
        $args[] = $sourcePath;
        
        // Output file
        $args[] = '--output';
        $args[] = $destinationPath;

        $result = $this->executeCommand('npx', $args);

        return [
            'success' => $result['return_code'] === 0,
            'error' => $result['output'],
        ];
    }

    /**
     * Build command line arguments
     */
    private function buildArguments(array $options): array
    {
        $args = [];

        // Multipass (run multiple optimization passes)
        if ($options['multipass'] ?? true) {
            $args[] = '--multipass';
        }

        // Precision for numbers (default 3)
        $precision = $options['precision'] ?? 3;
        $args[] = '--precision';
        $args[] = (string) $precision;

        // Pretty output (for debugging)
        if ($options['pretty'] ?? false) {
            $args[] = '--pretty';
        }

        // Quiet mode
        $args[] = '--quiet';

        return $args;
    }

    public function getInstallInstructions(): string
    {
        return __(
            'Install SVGO:\n' .
            '• Global: npm install -g svgo\n' .
            '• Or use npx (requires Node.js): npx svgo\n' .
            '• Node.js required: https://nodejs.org',
            'media-toolkit'
        );
    }
}

