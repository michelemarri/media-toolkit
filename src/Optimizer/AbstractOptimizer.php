<?php
/**
 * Abstract Optimizer
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Optimizer;

use Metodo\MediaToolkit\Core\Logger;

/**
 * Base class for image optimization drivers
 */
abstract class AbstractOptimizer implements OptimizerInterface
{
    protected ?Logger $logger;
    protected ?string $binaryPath = null;
    protected ?string $version = null;
    protected ?bool $available = null;

    /** @var array<string> Supported formats for this optimizer */
    protected array $supportedFormats = [];

    /** @var int Priority (higher = better) */
    protected int $priority = 0;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    public function supportsFormat(string $format): bool
    {
        $format = strtolower($format);
        
        // Normalize jpeg/jpg
        if ($format === 'jpg') {
            $format = 'jpeg';
        }

        return in_array($format, $this->supportedFormats, true);
    }

    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getBinaryPath(): ?string
    {
        return $this->binaryPath;
    }

    public function getVersion(): ?string
    {
        if ($this->version === null && $this->isAvailable()) {
            $this->version = $this->detectVersion();
        }

        return $this->version;
    }

    /**
     * Detect the version of the optimizer tool
     */
    abstract protected function detectVersion(): ?string;

    /**
     * Execute a shell command safely
     *
     * @param string $command Command to execute
     * @param array<string> $args Arguments (will be escaped)
     * @return array{output: string, return_code: int}
     */
    protected function executeCommand(string $command, array $args = []): array
    {
        // Build escaped command
        $escapedArgs = array_map('escapeshellarg', $args);
        $fullCommand = $command . ' ' . implode(' ', $escapedArgs) . ' 2>&1';

        $output = [];
        $returnCode = 0;
        
        exec($fullCommand, $output, $returnCode);

        return [
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ];
    }

    /**
     * Find binary path using 'which' command
     */
    protected function findBinary(string $name): ?string
    {
        // Check common paths first
        $commonPaths = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/opt/homebrew/bin/' . $name,
            '/opt/local/bin/' . $name,
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Fall back to 'which' command
        $result = $this->executeCommand('which', [$name]);
        
        if ($result['return_code'] === 0 && !empty(trim($result['output']))) {
            $path = trim($result['output']);
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if shell_exec and exec are available
     */
    protected function canExecuteCommands(): bool
    {
        $disabledFunctions = explode(',', ini_get('disable_functions') ?: '');
        $disabledFunctions = array_map('trim', $disabledFunctions);

        return !in_array('exec', $disabledFunctions, true) 
            && !in_array('shell_exec', $disabledFunctions, true)
            && function_exists('exec')
            && function_exists('shell_exec');
    }

    /**
     * Log a message
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $context['optimizer'] = $this->getId();

        match ($level) {
            'info' => $this->logger->info('optimizer', $message, null, null, $context),
            'warning' => $this->logger->warning('optimizer', $message, null, null, $context),
            'error' => $this->logger->error('optimizer', $message, null, null, $context),
            'success' => $this->logger->success('optimizer', $message, null, null, $context),
            default => $this->logger->info('optimizer', $message, null, null, $context),
        };
    }

    /**
     * Get file size safely
     */
    protected function getFileSize(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        clearstatcache(true, $path);
        $size = filesize($path);

        return $size !== false ? $size : 0;
    }

    /**
     * Validate source file exists and is readable
     */
    protected function validateSourceFile(string $path): ?string
    {
        if (!file_exists($path)) {
            return 'Source file does not exist';
        }

        if (!is_readable($path)) {
            return 'Source file is not readable';
        }

        if (filesize($path) === 0) {
            return 'Source file is empty';
        }

        return null;
    }

    /**
     * Ensure destination directory exists
     */
    protected function ensureDirectoryExists(string $filePath): bool
    {
        $dir = dirname($filePath);

        if (is_dir($dir)) {
            return true;
        }

        return wp_mkdir_p($dir);
    }

    /**
     * Get default installation instructions
     */
    public function getInstallInstructions(): string
    {
        return __('Please contact your hosting provider for installation instructions.', 'media-toolkit');
    }

    /**
     * Create a temporary copy of the file for optimization
     */
    protected function createTempCopy(string $sourcePath): ?string
    {
        $tempFile = wp_tempnam('optimizer_');
        
        if (!$tempFile) {
            return null;
        }

        if (!copy($sourcePath, $tempFile)) {
            @unlink($tempFile);
            return null;
        }

        return $tempFile;
    }

    /**
     * Get format from file path or mime type
     */
    protected function getFormatFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            'avif' => 'avif',
            'svg' => 'svg',
            default => $extension,
        };
    }
}

