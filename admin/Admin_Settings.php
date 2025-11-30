<?php
/**
 * Admin Settings class
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Admin;

use Metodo\MediaToolkit\Core\Settings;
use Metodo\MediaToolkit\Core\Encryption;
use Metodo\MediaToolkit\Core\Logger;
use Metodo\MediaToolkit\Core\LogLevel;
use Metodo\MediaToolkit\Core\Environment;
use Metodo\MediaToolkit\S3\S3_Client;
use Metodo\MediaToolkit\CDN\CloudFront;
use Metodo\MediaToolkit\History\History;
use Metodo\MediaToolkit\History\HistoryAction;
use Metodo\MediaToolkit\Stats\Stats;

use function Metodo\MediaToolkit\media_toolkit;

/**
 * Handles admin settings page and AJAX actions
 */
final class Admin_Settings
{
    private Settings $settings;
    private Encryption $encryption;
    private ?S3_Client $s3_client;
    private ?CloudFront $cloudfront;
    private Logger $logger;
    private History $history;
    private Stats $stats;

    public function __construct(
        Settings $settings,
        Encryption $encryption,
        ?S3_Client $s3_client,
        ?CloudFront $cloudfront,
        Logger $logger,
        History $history,
        Stats $stats
    ) {
        $this->settings = $settings;
        $this->encryption = $encryption;
        $this->s3_client = $s3_client;
        $this->cloudfront = $cloudfront;
        $this->logger = $logger;
        $this->history = $history;
        $this->stats = $stats;

        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers(): void
    {
        // Tab-based save handlers
        add_action('wp_ajax_media_toolkit_save_credentials', [$this, 'ajax_save_credentials']);
        add_action('wp_ajax_media_toolkit_save_cdn', [$this, 'ajax_save_cdn']);
        add_action('wp_ajax_media_toolkit_save_file_options', [$this, 'ajax_save_file_options']);
        add_action('wp_ajax_media_toolkit_save_general', [$this, 'ajax_save_general']);
        
        // Legacy/existing handlers
        add_action('wp_ajax_media_toolkit_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_media_toolkit_save_active_env', [$this, 'ajax_save_active_env']);
        add_action('wp_ajax_media_toolkit_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_media_toolkit_test_env_connection', [$this, 'ajax_test_env_connection']);
        add_action('wp_ajax_media_toolkit_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_media_toolkit_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_media_toolkit_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_media_toolkit_export_history', [$this, 'ajax_export_history']);
        add_action('wp_ajax_media_toolkit_clear_history', [$this, 'ajax_clear_history']);
        add_action('wp_ajax_media_toolkit_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_media_toolkit_sync_s3_stats', [$this, 'ajax_sync_s3_stats']);
        add_action('wp_ajax_media_toolkit_apply_cache_headers', [$this, 'ajax_apply_cache_headers']);
        add_action('wp_ajax_media_toolkit_count_s3_files', [$this, 'ajax_count_s3_files']);
    }

    /**
     * Get current settings for display
     */
    public function get_settings_data(): array
    {
        $active_env = $this->settings->get_active_environment();

        return [
            'active_environment' => $active_env->value,
            'credentials' => $this->settings->get_masked_credentials(),
            'remove_local' => $this->settings->should_remove_local_files(),
            'remove_on_uninstall' => $this->settings->should_remove_on_uninstall(),
            'cache_control' => $this->settings->get_cache_control_max_age(),
            's3_sync_interval' => $this->settings->get_s3_sync_interval(),
            'base_path' => $this->settings->get_s3_base_path(),
            'is_configured' => $this->settings->is_configured(),
        ];
    }

    /**
     * AJAX: Save active environment
     */
    public function ajax_save_active_env(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $active_env = Environment::tryFrom(sanitize_text_field($_POST['active_environment'] ?? ''));
        
        if ($active_env === null) {
            wp_send_json_error(['message' => 'Invalid environment']);
        }

        $saved = $this->settings->set_active_environment($active_env);

        if (!$saved) {
            wp_send_json_error(['message' => 'Failed to save active environment']);
        }

        $this->logger->info('settings', "Active environment changed to {$active_env->value}");

        wp_send_json_success([
            'message' => 'Active environment saved',
            'environment' => $active_env->value,
        ]);
    }

    /**
     * AJAX: Save credentials (Tab 2)
     */
    public function ajax_save_credentials(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $access_key = sanitize_text_field($_POST['access_key'] ?? '');
        $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $bucket = sanitize_text_field($_POST['bucket'] ?? '');

        // If keys are masked (unchanged), get current values
        $current = $this->settings->get_config();
        
        if (str_contains($access_key, '•') && $current !== null) {
            $access_key = $current->accessKey;
        }
        
        if (str_contains($secret_key, '•') && $current !== null) {
            $secret_key = $current->secretKey;
        }

        // Get existing CDN settings to preserve them
        $cdn_url = $current ? $current->cdnUrl : '';
        $cdn_provider = $current ? $current->cdnProvider->value : 'none';
        $cloudflare_zone_id = $current ? $current->cloudflareZoneId : '';
        $cloudflare_api_token = $current ? $current->cloudflareApiToken : '';
        $cloudfront_dist_id = $current ? $current->cloudfrontDistributionId : '';

        $saved = $this->settings->save_config(
            $access_key,
            $secret_key,
            $region,
            $bucket,
            $cdn_url,
            $cdn_provider,
            $cloudflare_zone_id,
            $cloudflare_api_token,
            $cloudfront_dist_id
        );

        if (!$saved) {
            wp_send_json_error(['message' => 'Failed to save credentials']);
        }

        // Reset S3 client to use new settings
        $this->s3_client?->reset_client();

        // Record in history
        $this->history->record(
            HistoryAction::SETTINGS_CHANGED
        );

        $this->logger->info('settings', 'Credentials updated');

        wp_send_json_success([
            'message' => 'Credentials saved successfully',
        ]);
    }

    /**
     * AJAX: Save CDN settings (Tab 3)
     */
    public function ajax_save_cdn(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $cdn_url = esc_url_raw($_POST['cdn_url'] ?? '');
        $cdn_provider = sanitize_text_field($_POST['cdn_provider'] ?? 'none');
        $cloudflare_zone_id = sanitize_text_field($_POST['cloudflare_zone_id'] ?? '');
        $cloudflare_api_token = sanitize_text_field($_POST['cloudflare_api_token'] ?? '');
        $cloudfront_dist_id = sanitize_text_field($_POST['cloudfront_distribution_id'] ?? '');

        // Get existing credentials to preserve them
        $current = $this->settings->get_config();
        
        if ($current === null) {
            wp_send_json_error(['message' => 'Please configure AWS credentials first']);
        }

        // If API token is masked (unchanged), get current value
        if (str_contains($cloudflare_api_token, '•')) {
            $cloudflare_api_token = $current->cloudflareApiToken;
        }

        $saved = $this->settings->save_config(
            $current->accessKey,
            $current->secretKey,
            $current->region,
            $current->bucket,
            $cdn_url,
            $cdn_provider,
            $cloudflare_zone_id,
            $cloudflare_api_token,
            $cloudfront_dist_id
        );

        if (!$saved) {
            wp_send_json_error(['message' => 'Failed to save CDN settings']);
        }

        $this->logger->info('settings', 'CDN settings updated');

        wp_send_json_success([
            'message' => 'CDN settings saved successfully',
        ]);
    }

    /**
     * AJAX: Save file options (Tab 4)
     */
    public function ajax_save_file_options(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Save Cache-Control
        if (isset($_POST['cache_control'])) {
            $this->settings->set_cache_control_max_age((int) $_POST['cache_control']);
        }

        // Save Content-Disposition settings
        $content_disposition = [];
        $file_types = ['image', 'pdf', 'video', 'archive'];
        
        foreach ($file_types as $type) {
            $key = 'content_disposition_' . $type;
            if (isset($_POST[$key])) {
                $value = sanitize_text_field($_POST[$key]);
                if (in_array($value, ['inline', 'attachment'])) {
                    $content_disposition[$type] = $value;
                }
            }
        }
        
        if (!empty($content_disposition)) {
            $this->settings->set_content_disposition_settings($content_disposition);
        }

        $this->logger->info('settings', 'File options updated');

        wp_send_json_success([
            'message' => 'File options saved successfully',
        ]);
    }

    /**
     * AJAX: Save general options (Tab 5)
     */
    public function ajax_save_general(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if (isset($_POST['remove_local'])) {
            $this->settings->set_remove_local_files($_POST['remove_local'] === 'true');
        }

        if (isset($_POST['remove_on_uninstall'])) {
            $this->settings->set_remove_on_uninstall($_POST['remove_on_uninstall'] === 'true');
        }

        $this->logger->info('settings', 'General options updated');

        wp_send_json_success([
            'message' => 'Options saved successfully',
        ]);
    }

    /**
     * AJAX: Save settings (legacy - saves all at once)
     */
    public function ajax_save_settings(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $access_key = sanitize_text_field($_POST['access_key'] ?? '');
        $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $bucket = sanitize_text_field($_POST['bucket'] ?? '');
        $cdn_url = esc_url_raw($_POST['cdn_url'] ?? '');
        $cdn_provider = sanitize_text_field($_POST['cdn_provider'] ?? 'none');
        $cloudflare_zone_id = sanitize_text_field($_POST['cloudflare_zone_id'] ?? '');
        $cloudflare_api_token = sanitize_text_field($_POST['cloudflare_api_token'] ?? '');
        $cloudfront_dist_id = sanitize_text_field($_POST['cloudfront_distribution_id'] ?? '');

        // If keys are masked (unchanged), get current values
        $current = $this->settings->get_config();
        
        if (str_contains($access_key, '•') && $current !== null) {
            $access_key = $current->accessKey;
        }
        
        if (str_contains($secret_key, '•') && $current !== null) {
            $secret_key = $current->secretKey;
        }
        
        if (str_contains($cloudflare_api_token, '•') && $current !== null) {
            $cloudflare_api_token = $current->cloudflareApiToken;
        }

        $saved = $this->settings->save_config(
            $access_key,
            $secret_key,
            $region,
            $bucket,
            $cdn_url,
            $cdn_provider,
            $cloudflare_zone_id,
            $cloudflare_api_token,
            $cloudfront_dist_id
        );

        if (!$saved) {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }

        // Update other options
        if (isset($_POST['remove_local'])) {
            $this->settings->set_remove_local_files($_POST['remove_local'] === 'true');
        }

        if (isset($_POST['remove_on_uninstall'])) {
            $this->settings->set_remove_on_uninstall($_POST['remove_on_uninstall'] === 'true');
        }

        if (isset($_POST['cache_control'])) {
            $this->settings->set_cache_control_max_age((int) $_POST['cache_control']);
        }

        if (isset($_POST['s3_sync_interval'])) {
            $this->settings->set_s3_sync_interval((int) $_POST['s3_sync_interval']);
            // Reschedule cron job with new interval
            media_toolkit()->schedule_s3_sync();
        }

        // Reset S3 client to use new settings
        $this->s3_client?->reset_client();

        // Record in history
        $this->history->record(
            HistoryAction::SETTINGS_CHANGED
        );

        $this->logger->info('settings', 'Settings updated');

        wp_send_json_success([
            'message' => 'Settings saved successfully',
            'data' => $this->get_settings_data(),
        ]);
    }

    /**
     * AJAX: Test S3 connection with form values (before saving)
     */
    public function ajax_test_env_connection(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $access_key = sanitize_text_field($_POST['access_key'] ?? '');
        $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $bucket = sanitize_text_field($_POST['bucket'] ?? '');
        $cdn_url = esc_url_raw($_POST['cdn_url'] ?? '');

        // Check if keys contain masked values (dots), get real values from saved config
        $config = $this->settings->get_config();
        
        if (str_contains($access_key, '•') && $config !== null) {
            $access_key = $config->accessKey;
        }
        
        if (str_contains($secret_key, '•') && $config !== null) {
            $secret_key = $config->secretKey;
        }

        if (empty($access_key) || empty($secret_key) || empty($region) || empty($bucket)) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        // Test with provided credentials
        $results = $this->test_credentials($access_key, $secret_key, $region, $bucket, $cdn_url);

        $all_success = true;
        foreach ($results as $result) {
            if (!$result['success']) {
                $all_success = false;
                break;
            }
        }

        wp_send_json_success([
            'results' => $results,
            'success' => $all_success,
        ]);
    }

    /**
     * Test AWS credentials without saving
     */
    private function test_credentials(
        string $access_key,
        string $secret_key,
        string $region,
        string $bucket,
        string $cdn_url = ''
    ): array {
        $results = [
            'credentials' => ['success' => false, 'message' => ''],
            'bucket' => ['success' => false, 'message' => ''],
            'permissions' => ['success' => false, 'message' => ''],
            'cdn' => ['success' => false, 'message' => ''],
        ];

        // Test credentials using STS
        try {
            $stsClient = new \Aws\Sts\StsClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            $identity = $stsClient->getCallerIdentity();
            $results['credentials'] = [
                'success' => true,
                'message' => 'Credentials valid. Account: ' . $identity['Account'],
            ];
        } catch (\Aws\Exception\AwsException $e) {
            $results['credentials']['message'] = $e->getAwsErrorMessage() ?: $e->getMessage();
            return $results;
        }

        // Test bucket access
        try {
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            $s3Client->headBucket(['Bucket' => $bucket]);
            $results['bucket'] = [
                'success' => true,
                'message' => "Bucket '{$bucket}' exists and is accessible",
            ];
        } catch (\Aws\Exception\AwsException $e) {
            $results['bucket']['message'] = $e->getAwsErrorMessage() ?: $e->getMessage();
            return $results;
        }

        // Test write permissions
        try {
            $test_key = 'media-toolkit-test-' . time() . '.txt';
            
            $s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $test_key,
                'Body' => 'Media S3 Offload connection test',
                'ContentType' => 'text/plain',
            ]);

            $s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $test_key,
            ]);

            $results['permissions'] = [
                'success' => true,
                'message' => 'Write and delete permissions confirmed',
            ];
        } catch (\Aws\Exception\AwsException $e) {
            $results['permissions']['message'] = $e->getAwsErrorMessage() ?: $e->getMessage();
            return $results;
        }

        // Test CDN URL
        if (!empty($cdn_url)) {
            $response = wp_remote_head($cdn_url, [
                'timeout' => 10,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                $results['cdn']['message'] = 'CDN URL not reachable: ' . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code >= 200 && $status_code < 500) {
                    $results['cdn'] = [
                        'success' => true,
                        'message' => 'CDN URL is reachable',
                    ];
                } else {
                    $results['cdn']['message'] = "CDN returned status code: {$status_code}";
                }
            }
        } else {
            $results['cdn'] = [
                'success' => true,
                'message' => 'CDN not configured (using direct S3 URLs)',
            ];
        }

        return $results;
    }

    /**
     * AJAX: Test S3 connection
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if ($this->s3_client === null) {
            wp_send_json_error(['message' => 'S3 client not configured']);
        }

        $results = $this->s3_client->test_connection();

        // Update connection status cache
        $all_success = true;
        $messages = [];
        
        foreach ($results as $key => $result) {
            if (!$result['success']) {
                $all_success = false;
            }
            $messages[] = ucfirst($key) . ': ' . $result['message'];
        }

        $this->stats->update_connection_status($all_success, implode('; ', $messages));

        wp_send_json_success([
            'results' => $results,
            'success' => $all_success,
        ]);
    }

    /**
     * AJAX: Get logs
     */
    public function ajax_get_logs(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, max(10, (int) $_POST['per_page'])) : 50;
        $level = isset($_POST['level']) ? LogLevel::tryFrom(sanitize_text_field($_POST['level'])) : null;
        $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : null;

        $logs = $this->logger->get_logs($page, $per_page, $level, $operation ?: null);
        $total = $this->logger->get_total_count($level, $operation ?: null);

        wp_send_json_success([
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
            'operations' => $this->logger->get_operations(),
        ]);
    }

    /**
     * AJAX: Get history
     */
    public function ajax_get_history(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, max(10, (int) $_POST['per_page'])) : 50;
        $action_filter = !empty($_POST['action_filter']) ? sanitize_text_field($_POST['action_filter']) : null;
        $action = $action_filter ? HistoryAction::tryFrom($action_filter) : null;
        $date_from = !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;

        $history = $this->history->get_history($page, $per_page, $action, $date_from, $date_to);
        $total = $this->history->get_total_count($action);

        wp_send_json_success([
            'history' => $history,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->logger->clear_all();
        
        wp_send_json_success(['message' => 'Logs cleared']);
    }

    /**
     * AJAX: Export history
     */
    public function ajax_export_history(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $action = isset($_POST['action_filter']) ? HistoryAction::tryFrom(sanitize_text_field($_POST['action_filter'])) : null;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;

        $csv = $this->history->export_csv($action, $date_from, $date_to);
        
        wp_send_json_success([
            'csv' => $csv,
            'filename' => 's3-offload-history-' . date('Y-m-d') . '.csv',
        ]);
    }

    /**
     * AJAX: Clear history
     */
    public function ajax_clear_history(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->history->clear_all();
        
        wp_send_json_success(['message' => 'History cleared']);
    }

    /**
     * AJAX: Get dashboard stats
     */
    public function ajax_get_dashboard_stats(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        wp_send_json_success([
            'stats' => $this->stats->get_dashboard_stats(),
            'migration' => $this->stats->get_migration_stats(),
            'sparkline' => $this->stats->get_sparkline_data(),
        ]);
    }

    /**
     * AJAX: Sync S3 stats manually
     */
    public function ajax_sync_s3_stats(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if ($this->s3_client === null) {
            wp_send_json_error(['message' => 'S3 client not configured']);
        }

        $stats = $this->s3_client->get_bucket_stats();
        
        if ($stats === null) {
            wp_send_json_error(['message' => 'Failed to retrieve S3 statistics']);
        }

        $this->settings->save_s3_stats($stats);
        $this->stats->clear_cache();

        wp_send_json_success([
            'message' => 'S3 statistics synced successfully',
            'stats' => $stats,
            'stats_formatted' => [
                'files' => number_format($stats['files']),
                'original_files' => number_format($stats['original_files'] ?? $stats['files']),
                'size' => size_format($stats['size']),
                'original_size' => size_format($stats['original_size'] ?? $stats['size']),
                'synced_at' => $stats['synced_at'],
            ],
        ]);
    }

    /**
     * AJAX: Apply cache headers to existing S3 files (batch operation)
     */
    public function ajax_apply_cache_headers(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if ($this->s3_client === null) {
            wp_send_json_error(['message' => 'S3 client not configured']);
        }

        $cache_max_age = isset($_POST['cache_max_age']) ? (int) $_POST['cache_max_age'] : $this->settings->get_cache_control_max_age();
        $continuation_token = !empty($_POST['continuation_token']) ? sanitize_text_field($_POST['continuation_token']) : null;
        $batch_size = 50; // Process 50 files per request

        // Get batch of objects
        $result = $this->s3_client->list_objects_batch($batch_size, $continuation_token);

        if ($result === null) {
            wp_send_json_error(['message' => 'Failed to list S3 objects']);
        }

        $keys = $result['keys'];
        $next_token = $result['next_token'];
        $is_truncated = $result['is_truncated'];

        // Update metadata for this batch
        $batch_result = $this->s3_client->update_objects_metadata_batch($keys, $cache_max_age);

        // Log the operation
        $this->logger->info('cache_headers', sprintf(
            'Applied cache headers to %d files (%d failed)',
            $batch_result['success'],
            $batch_result['failed']
        ));

        wp_send_json_success([
            'processed' => count($keys),
            'success' => $batch_result['success'],
            'failed' => $batch_result['failed'],
            'has_more' => $is_truncated && !empty($next_token),
            'continuation_token' => $next_token,
        ]);
    }

    /**
     * AJAX: Count total S3 files (for progress calculation)
     */
    public function ajax_count_s3_files(): void
    {
        check_ajax_referer('media_toolkit_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        if ($this->s3_client === null) {
            wp_send_json_error(['message' => 'S3 client not configured']);
        }

        // Get cached stats if available
        $cached_stats = $this->settings->get_cached_s3_stats();
        
        if ($cached_stats !== null && !empty($cached_stats['files'])) {
            wp_send_json_success([
                'total_files' => $cached_stats['files'],
                'from_cache' => true,
            ]);
            return;
        }

        // Otherwise count from S3
        $stats = $this->s3_client->get_bucket_stats();
        
        if ($stats === null) {
            wp_send_json_error(['message' => 'Failed to count S3 files']);
        }

        // Save stats
        $this->settings->save_s3_stats($stats);

        wp_send_json_success([
            'total_files' => $stats['files'],
            'from_cache' => false,
        ]);
    }
}

