/**
 * Media Toolkit - Settings JavaScript
 */

(function ($) {
    'use strict';

    const MediaToolkit = {
        autoRefreshInterval: null,

        init: function () {
            this.bindEvents();
            this.loadInitialData();
        },

        bindEvents: function () {
            // Tab-based save buttons
            $('#btn-save-environment').on('click', this.saveEnvironment.bind(this));
            $('#btn-save-credentials').on('click', this.saveCredentials.bind(this));
            $('#btn-save-cdn').on('click', this.saveCDNSettings.bind(this));
            $('#btn-save-file-options').on('click', this.saveFileOptions.bind(this));
            $('#btn-save-general').on('click', this.saveGeneralOptions.bind(this));

            // Legacy save button (for backwards compatibility with environment)
            $('#btn-save-active-env').on('click', this.saveEnvironment.bind(this));

            // Test connection from credentials tab
            $('#btn-test-credentials').on('click', this.testCredentials.bind(this));
            $('#btn-test-settings').on('click', this.testSettings.bind(this));

            // Enable/disable test button based on form fields
            this.setupTestButtonValidation();

            // Update environment preview
            $('#active-environment').on('change', function () {
                $('#env-preview').text($(this).val());
            });

            // CDN provider toggle
            $('#cdn_provider').on('change', this.toggleCDNSettings.bind(this));
            this.toggleCDNSettings();

            // Storage provider toggle
            $('#storage_provider').on('change', this.toggleStorageProvider.bind(this));
            this.toggleStorageProvider();

            // Sync storage stats (Tools page)
            $('#btn-sync-stats').on('click', this.syncStorageStats.bind(this));

            // Cache sync (Tools page)
            $('#btn-start-cache-sync').on('click', this.startCacheSync.bind(this));
            $('#btn-cancel-cache-sync').on('click', this.cancelCacheSync.bind(this));

            // Sync interval change (Tools page)
            $('#s3_sync_interval').on('change', this.saveSyncInterval.bind(this));

            // Activity tab
            $('#btn-refresh-logs').on('click', this.loadLogs.bind(this));
            $('#btn-clear-logs').on('click', this.clearLogs.bind(this));
            $('#btn-filter-history').on('click', this.loadHistory.bind(this));
            $('#btn-export-history').on('click', this.exportHistory.bind(this));
            $('#btn-clear-history').on('click', this.clearHistory.bind(this));

            // Logs page tabs (Activity Logs + Optimization Status)
            $('.logs-tab-btn').on('click', this.switchLogsTab.bind(this));

            // Optimization tab events
            $('#btn-refresh-optimization').on('click', this.loadOptimizationRecords.bind(this));
            $('#btn-reset-failed').on('click', this.resetFailedOptimization.bind(this));
            $('#filter-opt-status').on('change', this.loadOptimizationRecords.bind(this));
            $('#btn-opt-prev-page').on('click', () => this.changeOptimizationPage(-1));
            $('#btn-opt-next-page').on('click', () => this.changeOptimizationPage(1));

            // Auto-refresh toggle
            $('#auto-refresh-logs').on('change', this.toggleAutoRefresh.bind(this));

            // Pagination
            $('#btn-prev-page').on('click', () => this.changePage(-1));
            $('#btn-next-page').on('click', () => this.changePage(1));

            // Filters
            $('#filter-log-level, #filter-log-operation').on('change', this.loadLogs.bind(this));

            // Modal close
            $('.modal-close').on('click', this.closeModal);
            $(document).on('click', '.mt-modal-overlay', function (e) {
                if ($(e.target).hasClass('mt-modal-overlay')) {
                    MediaToolkit.closeModal();
                }
            });

            // Update tab handlers
            $('#btn-toggle-password').on('click', this.togglePasswordVisibility.bind(this));
            $('#btn-save-update-settings').on('click', this.saveUpdateSettings.bind(this));
            $('#btn-remove-token').on('click', this.removeGitHubToken.bind(this));
            $('#btn-check-updates').on('click', this.checkForUpdates.bind(this));

            // Import/Export handlers
            $('#btn-export-settings').on('click', this.exportSettings.bind(this));
            $('#btn-import-settings').on('click', this.importSettings.bind(this));
            this.setupImportDropZone();

            // Resize settings handlers
            $('#btn-save-resize-settings').on('click', this.saveResizeSettings.bind(this));
            $('#resize-jpeg-quality').on('input', function () {
                $('#resize-jpeg-quality-value').text($(this).val());
            });

            // Resize presets
            $('.resize-preset').on('click', function () {
                const width = $(this).data('width');
                const height = $(this).data('height');
                $('#resize-max-width').val(width);
                $('#resize-max-height').val(height);
            });

            // Optimization settings sliders update
            $('#jpeg-quality').on('input', function () {
                $('#jpeg-quality-value').text($(this).val());
            });
            $('#png-compression').on('input', function () {
                $('#png-compression-value').text($(this).val());
            });

            // Save optimization settings (Optimize page)
            $('#btn-save-optimize-settings').on('click', this.saveOptimizationSettings.bind(this));
        },

        loadInitialData: function () {
            // Check if on logs page (with tabs)
            if ($('#logs-table').length && $('#optimization-table').length) {
                this.loadLogs();
                this.toggleAutoRefresh();
                // Don't load optimization yet - will load when tab is clicked
            }
            // Check if on logs page (legacy without optimization tab)
            else if ($('#logs-table').length && !$('#history-table').length) {
                this.loadLogs();
                this.toggleAutoRefresh();
            }

            // Check if on history page
            if ($('#history-table').length && !$('#logs-table').length) {
                this.loadHistory();
            }

            // Check if on activity tab (both tables present)
            if ($('#logs-table').length && $('#history-table').length && !$('#optimization-table').length) {
                this.loadLogs();
                this.loadHistory();
                this.toggleAutoRefresh();
            }

            // Check if on dashboard tab
            if ($('#sparkline-chart').length) {
                this.loadDashboardStats();
            }
        },

        // Setup validation for test button
        setupTestButtonValidation: function () {
            const checkFields = function () {
                const provider = $('#storage_provider').val() || 'aws_s3';
                const accessKey = $('#access_key').val();
                const secretKey = $('#secret_key').val();
                const bucket = $('#bucket').val();

                // R2 requires account_id instead of region
                let isValid = accessKey && secretKey && bucket;

                if (provider === 'cloudflare_r2') {
                    const accountId = $('#account_id').val();
                    isValid = isValid && accountId;
                } else {
                    const region = $('#region').val();
                    isValid = isValid && region;
                }

                $('#btn-save-credentials').prop('disabled', !isValid);
                $('#btn-test-credentials').prop('disabled', !isValid);
                $('#btn-test-settings').prop('disabled', !isValid);
            };

            // Check on load
            checkFields();

            // Check on input change
            $('#storage-credentials-panel input, #storage-credentials-panel select').on('input change', checkFields);
            // Legacy panel support
            $('#s3-credentials-panel input, #s3-credentials-panel select').on('input change', checkFields);
        },

        // Toggle storage provider specific fields
        toggleStorageProvider: function () {
            const provider = $('#storage_provider').val() || 'aws_s3';
            const providers = window.mediaToolkit?.providers || {};
            const providerInfo = providers[provider] || {};

            // Update description
            if (providerInfo.description) {
                $('#provider-description').text(providerInfo.description);
            }

            // Show/hide R2 warning
            if (provider === 'cloudflare_r2') {
                $('#r2-cdn-warning').removeClass('hidden');
                $('#field-account-id').removeClass('hidden');
                $('#field-region').addClass('hidden');
            } else {
                $('#r2-cdn-warning').addClass('hidden');
                $('#field-account-id').addClass('hidden');
                $('#field-region').removeClass('hidden');
            }

            // Update regions dropdown based on provider
            this.updateRegionsForProvider(provider, providerInfo.regions || {});

            // Update field labels for B2
            if (provider === 'backblaze_b2') {
                $('#label-access-key').text('Application Key ID');
                $('#label-secret-key').text('Application Key');
            } else {
                $('#label-access-key').text('Access Key');
                $('#label-secret-key').text('Secret Key');
            }

            // Re-check validation
            this.setupTestButtonValidation();
        },

        // Update regions dropdown for the selected provider
        updateRegionsForProvider: function (provider, regions) {
            const $select = $('#region');
            const currentValue = $select.val();

            $select.empty();
            $select.append('<option value="">' + (window.mediaToolkit?.i18n?.selectRegion || 'Select Region') + '</option>');

            for (const [code, label] of Object.entries(regions)) {
                const selected = (code === currentValue) ? ' selected' : '';
                $select.append(`<option value="${code}"${selected}>${label}</option>`);
            }
        },

        // Toggle CDN-specific settings based on provider
        toggleCDNSettings: function () {
            const provider = $('#cdn_provider').val();

            $('#cloudflare-settings').hide();
            $('#cloudfront-settings').hide();

            if (provider === 'cloudflare') {
                $('#cloudflare-settings').show();
            } else if (provider === 'cloudfront') {
                $('#cloudfront-settings').show();
            }
        },

        // Save Environment (Tab 1)
        saveEnvironment: function () {
            const $btn = $('#btn-save-environment, #btn-save-active-env');
            const activeEnv = $('#active-environment').val();

            $btn.prop('disabled', true);
            $btn.find('svg').parent().contents().filter(function () { return this.nodeType === 3; }).first().replaceWith(' Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_save_active_env',
                    nonce: mediaToolkit.nonce,
                    active_environment: activeEnv
                },
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Environment saved! Page will reload...', 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        // Save Credentials (Tab 2)
        saveCredentials: function () {
            const $btn = $('#btn-save-credentials');
            const provider = $('#storage_provider').val() || 'aws_s3';

            const data = {
                action: 'media_toolkit_save_credentials',
                nonce: mediaToolkit.nonce,
                storage_provider: provider,
                access_key: $('#access_key').val(),
                secret_key: $('#secret_key').val(),
                region: $('#region').val(),
                bucket: $('#bucket').val(),
                account_id: $('#account_id').val() || ''
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Configuration saved successfully!', 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save configuration', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save CDN Settings (Tab 3)
        saveCDNSettings: function () {
            const $btn = $('#btn-save-cdn');

            const data = {
                action: 'media_toolkit_save_cdn',
                nonce: mediaToolkit.nonce,
                cdn_url: $('#cdn_url').val(),
                cdn_provider: $('#cdn_provider').val(),
                cloudflare_zone_id: $('#cloudflare_zone_id').val(),
                cloudflare_api_token: $('#cloudflare_api_token').val(),
                cloudfront_distribution_id: $('#cloudfront_distribution_id').val()
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('CDN settings saved successfully!', 'success');
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save CDN settings', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save File Options (Tab 4)
        saveFileOptions: function () {
            const $btn = $('#btn-save-file-options');

            const data = {
                action: 'media_toolkit_save_file_options',
                nonce: mediaToolkit.nonce,
                cache_control: $('#cache_control').val(),
                // Content-Disposition settings by file type
                content_disposition_image: $('#content_disposition_image').val(),
                content_disposition_pdf: $('#content_disposition_pdf').val(),
                content_disposition_video: $('#content_disposition_video').val(),
                content_disposition_archive: $('#content_disposition_archive').val()
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('File options saved successfully!', 'success');
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save file options', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save General Options (Tab 5)
        saveGeneralOptions: function () {
            const $btn = $('#btn-save-general');

            const data = {
                action: 'media_toolkit_save_general',
                nonce: mediaToolkit.nonce,
                remove_local: $('#remove_local').is(':checked') ? 'true' : 'false',
                remove_on_uninstall: $('#remove_on_uninstall').is(':checked') ? 'true' : 'false'
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Options saved successfully!', 'success');
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save options', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Test Credentials (from Credentials tab)
        testCredentials: function () {
            const $btn = $('#btn-test-credentials');
            const $modal = $('#test-connection-modal');
            const $results = $('#test-results');

            const data = {
                action: 'media_toolkit_test_env_connection',
                nonce: mediaToolkit.nonce,
                access_key: $('#access_key').val(),
                secret_key: $('#secret_key').val(),
                region: $('#region').val(),
                bucket: $('#bucket').val(),
                cdn_url: ''
            };

            // Validate required fields
            if (!data.access_key || !data.secret_key || !data.region || !data.bucket) {
                MediaToolkit.showNotice('Please fill in Access Key, Secret Key, Region and Bucket', 'error');
                return;
            }

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Testing...');
            $results.html('<div class="text-center py-8 text-gray-500">Running connection tests...</div>');
            $modal.show();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    MediaToolkit.showTestResults($results, response);
                },
                error: function () {
                    $results.html('<div class="flex gap-4 p-5 rounded-xl border mt-alert-error"><span class="dashicons dashicons-warning text-red-600"></span><p>Connection test failed. Please try again.</p></div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Test connection from settings (uses form values)
        testSettings: function () {
            const $btn = $('#btn-test-settings');
            const $modal = $('#test-connection-modal');
            const $results = $('#test-results');

            const data = {
                action: 'media_toolkit_test_env_connection',
                nonce: mediaToolkit.nonce,
                access_key: $('#access_key').val(),
                secret_key: $('#secret_key').val(),
                region: $('#region').val(),
                bucket: $('#bucket').val(),
                cdn_url: $('#cdn_url').val()
            };

            // Validate required fields
            if (!data.access_key || !data.secret_key || !data.region || !data.bucket) {
                MediaToolkit.showNotice('Please fill in Access Key, Secret Key, Region and Bucket', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Testing...');
            $results.html('<div class="text-center py-8 text-gray-500">Running connection tests...</div>');
            $modal.show();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    MediaToolkit.showTestResults($results, response);
                },
                error: function () {
                    $results.html('<div class="flex gap-4 p-5 rounded-xl border mt-alert-error"><span class="dashicons dashicons-warning text-red-600"></span><p>Connection test failed. Please try again.</p></div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        },

        // Sync storage statistics
        syncStorageStats: function () {
            const $btn = $('#btn-sync-stats');
            const $status = $('#sync-status');

            $btn.prop('disabled', true);
            $status.text('Syncing...').css('color', '#666');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_sync_s3_stats',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const stats = response.data.stats_formatted;
                        $status.html(`✓ Synced: ${stats.original_files} files (${stats.files} total with thumbnails), ${stats.size}`).css('color', '#00a32a');

                        // Reload page after 2 seconds to refresh all stats
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.text('✗ ' + (response.data?.message || 'Sync failed')).css('color', '#d63638');
                    }
                },
                error: function () {
                    $status.text('✗ Connection error').css('color', '#d63638');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        },

        // Cache sync state (for Tools page)
        cacheSyncState: {
            isRunning: false,
            isCancelled: false,
            totalFiles: 0,
            totalProcessed: 0,
            totalSuccess: 0,
            totalFailed: 0,
            continuationToken: null
        },

        // Start cache sync - first count total files
        startCacheSync: function () {
            const self = this;

            if (!confirm('This will update Cache-Control headers on ALL files in cloud storage for the current environment. This operation may take a while. Continue?')) {
                return;
            }

            // Reset state
            this.cacheSyncState = {
                isRunning: true,
                isCancelled: false,
                totalFiles: 0,
                totalProcessed: 0,
                totalSuccess: 0,
                totalFailed: 0,
                continuationToken: null
            };

            // Update UI - show counting phase
            $('#btn-start-cache-sync').prop('disabled', true).hide();
            $('#btn-cancel-cache-sync').show();
            $('#cache-sync-status').show();
            $('#cache-status-text').text('Counting files...');
            $('#cache-progress-bar').css('width', '0%');
            $('#cache-progress-percentage').text('0%');
            this.addCacheSyncLog('Starting cache headers update...', 'info');

            // First, count total files
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_count_s3_files',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.cacheSyncState.totalFiles = response.data.total_files;
                        $('#cache-total-files').text(response.data.total_files.toLocaleString());
                        $('#cache-total-count').text(response.data.total_files.toLocaleString());
                        self.addCacheSyncLog(`Found ${response.data.total_files.toLocaleString()} files to process`, 'success');

                        // Start processing
                        self.processCacheSyncBatch();
                    } else {
                        self.failCacheSync(response.data?.message || 'Failed to count files');
                    }
                },
                error: function () {
                    self.failCacheSync('Connection error while counting files');
                }
            });
        },

        // Cancel cache sync
        cancelCacheSync: function () {
            this.cacheSyncState.isCancelled = true;
            this.cacheSyncState.isRunning = false;

            $('#btn-cancel-cache-sync').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
            $('#cache-status-text').text('Cancelled');
            this.addCacheSyncLog(`Cancelled. Processed ${this.cacheSyncState.totalProcessed} files.`, 'warning');
        },

        // Process batch of files for cache sync
        processCacheSyncBatch: function () {
            const self = this;
            const state = this.cacheSyncState;

            if (state.isCancelled) {
                return;
            }

            const cacheMaxAge = $('#cache_control_value').val();

            $('#cache-status-text').text('Processing...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_apply_cache_headers',
                    nonce: mediaToolkit.nonce,
                    cache_max_age: cacheMaxAge,
                    continuation_token: state.continuationToken || ''
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        // Update state
                        state.totalProcessed += data.processed;
                        state.totalSuccess += data.success;
                        state.totalFailed += data.failed;
                        state.continuationToken = data.continuation_token;

                        // Calculate progress percentage
                        const percentage = state.totalFiles > 0
                            ? Math.round((state.totalProcessed / state.totalFiles) * 100)
                            : 0;

                        // Update UI
                        $('#cache-processed-files').text(state.totalSuccess.toLocaleString());
                        $('#cache-failed-files').text(state.totalFailed.toLocaleString());
                        $('#cache-current-count').text(state.totalProcessed.toLocaleString());
                        $('#cache-progress-bar').css('width', percentage + '%');
                        $('#cache-progress-percentage').text(percentage + '%');

                        if (data.has_more) {
                            // Continue with next batch
                            setTimeout(function () {
                                self.processCacheSyncBatch();
                            }, 100);
                        } else {
                            // Complete
                            self.completeCacheSync();
                        }
                    } else {
                        self.failCacheSync(response.data?.message || 'Unknown error');
                    }
                },
                error: function (xhr, status, error) {
                    self.failCacheSync('Connection error: ' + error);
                }
            });
        },

        // Complete cache sync
        completeCacheSync: function () {
            const state = this.cacheSyncState;
            state.isRunning = false;

            $('#cache-progress-bar').css('width', '100%');
            $('#cache-progress-percentage').text('100%');
            $('#cache-status-text').html('<span class="text-green-600">Complete!</span>');

            this.addCacheSyncLog(`✓ Complete! Updated ${state.totalSuccess.toLocaleString()} files` +
                (state.totalFailed > 0 ? ` (${state.totalFailed} failed)` : ''), 'success');

            $('#btn-cancel-cache-sync').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
        },

        // Fail cache sync
        failCacheSync: function (message) {
            const state = this.cacheSyncState;
            state.isRunning = false;

            $('#cache-status-text').html('<span class="text-red-600">Error</span>');
            this.addCacheSyncLog(`✗ Error: ${message}`, 'error');

            if (state.totalProcessed > 0) {
                this.addCacheSyncLog(`Processed ${state.totalProcessed} files before error.`, 'warning');
            }

            $('#btn-cancel-cache-sync').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
        },

        // Add log entry to cache sync log
        addCacheSyncLog: function (message, type = 'info') {
            const $log = $('#cache-sync-log');
            const timestamp = new Date().toLocaleTimeString();

            // Remove placeholder if exists
            $log.find('.mt-terminal-muted').first().remove();

            const typeClass = {
                'info': '',
                'success': 'mt-terminal-success',
                'warning': 'mt-terminal-warning',
                'error': 'mt-terminal-error'
            }[type] || '';

            $log.append(`
                <div class="mt-terminal-line">
                    <span class="mt-terminal-prompt">$</span>
                    <span class="mt-terminal-text ${typeClass}">[${timestamp}] ${message}</span>
                </div>
            `);

            // Scroll to bottom
            $log.scrollTop($log[0].scrollHeight);
        },

        // Save sync interval from Tools page
        saveSyncInterval: function () {
            const interval = $('#s3_sync_interval').val();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_save_sync_interval',
                    nonce: mediaToolkit.nonce,
                    s3_sync_interval: interval
                },
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Sync interval saved', 'success');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('Failed to save sync interval', 'error');
                }
            });
        },

        // Show test results in modal
        showTestResults: function ($results, response) {
            if (response.success) {
                const results = response.data.results;
                const icons = {
                    credentials: 'admin-network',
                    bucket: 'database',
                    permissions: 'lock',
                    cdn: 'networking'
                };

                let html = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';
                let index = 0;

                for (const [key, result] of Object.entries(results)) {
                    const icon = icons[key] || 'yes-alt';
                    const title = key.charAt(0).toUpperCase() + key.slice(1);

                    // Determine status: success, error, or info (not configured)
                    const isNotConfigured = result.message && result.message.toLowerCase().includes('not configured');
                    let statusClass, bgClass, iconColor;

                    if (!result.success) {
                        statusClass = 'text-red-600';
                        bgClass = 'bg-red-50 border-red-200';
                        iconColor = 'text-red-500';
                    } else if (isNotConfigured) {
                        statusClass = 'text-blue-600';
                        bgClass = 'bg-blue-50 border-blue-200';
                        iconColor = 'text-blue-500';
                    } else {
                        statusClass = 'text-green-600';
                        bgClass = 'bg-green-50 border-green-200';
                        iconColor = 'text-green-500';
                    }

                    html += `
                        <div class="mt-test-card p-5 border rounded-xl ${bgClass}" style="animation-delay: ${index * 0.1}s">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white">
                                    <span class="dashicons dashicons-${icon} ${iconColor}"></span>
                                </div>
                                ${!isNotConfigured ? `
                                <span class="dashicons dashicons-${result.success ? 'yes-alt' : 'warning'} ${statusClass}"></span>
                                ` : ''}
                            </div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-1">${title}</h4>
                            <p class="text-sm text-gray-600">${result.message}</p>
                        </div>
                    `;
                    index++;
                }

                html += '</div>';

                $results.html(html);
            } else {
                $results.html(`
                    <div class="flex gap-4 p-5 rounded-xl border mt-alert-error">
                        <span class="dashicons dashicons-warning text-red-600"></span>
                        <p class="text-gray-700">${response.data?.message || 'Test failed'}</p>
                    </div>
                `);
            }
        },

        // Load Logs
        loadLogs: function () {
            const $tbody = $('#logs-tbody');
            const level = $('#filter-log-level').val();
            const operation = $('#filter-log-operation').val();

            $tbody.html('<tr><td colspan="5" class="text-center py-8 text-gray-500">Loading logs...</td></tr>');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_logs',
                    nonce: mediaToolkit.nonce,
                    page: 1,
                    per_page: 100,
                    level: level,
                    operation: operation
                },
                success: function (response) {
                    if (response.success) {
                        const logs = response.data.logs;
                        $('#logs-count').text(response.data.total);

                        // Update operations filter
                        const $opFilter = $('#filter-log-operation');
                        const currentOp = $opFilter.val();
                        $opFilter.find('option:not(:first)').remove();
                        response.data.operations.forEach(function (op) {
                            $opFilter.append(`<option value="${op}">${op}</option>`);
                        });
                        $opFilter.val(currentOp);

                        if (logs.length === 0) {
                            $tbody.html(`<tr><td colspan="5" class="px-6 py-12 text-center">
                                <span class="dashicons dashicons-media-text text-5xl text-gray-300 mb-4 block"></span>
                                <p class="text-gray-600 font-medium">No logs found</p>
                                <span class="text-sm text-gray-400">Activity will appear here as operations occur</span>
                            </td></tr>`);
                            return;
                        }

                        let html = '';
                        logs.forEach(function (log) {
                            const levelClass = {
                                'error': 'mt-badge-error',
                                'warning': 'mt-badge-warning',
                                'success': 'mt-badge-success',
                                'info': 'mt-badge-info'
                            }[log.level] || 'mt-badge-neutral';
                            const dateObj = new Date(log.timestamp);
                            const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                            html += `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span class="block text-sm text-gray-900">${dateStr}</span>
                                        <span class="block text-xs text-gray-500">${timeStr}</span>
                                    </td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full ${levelClass}">${log.level}</span></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full mt-badge-neutral">${MediaToolkit.escapeHtml(log.operation)}</span></td>
                                    <td class="px-4 py-3"><span class="text-sm text-gray-600 font-mono truncate max-w-[200px] block" title="${MediaToolkit.escapeHtml(log.file_name || '-')}">${MediaToolkit.escapeHtml(log.file_name || '-')}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${MediaToolkit.escapeHtml(log.message)}</td>
                                </tr>
                            `;
                        });

                        $tbody.html(html);
                    }
                }
            });
        },

        // Clear Logs
        clearLogs: function () {
            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_clear_logs',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.loadLogs();
                        MediaToolkit.showNotice('Logs cleared', 'success');
                    }
                }
            });
        },

        // History pagination state
        historyPage: 1,
        historyTotalPages: 1,

        // Load History
        loadHistory: function () {
            const $tbody = $('#history-tbody');
            const action = $('#filter-history-action').val();
            const dateFrom = $('#filter-date-from').val();
            const dateTo = $('#filter-date-to').val();

            $tbody.html('<tr><td colspan="5" class="text-center py-8 text-gray-500">Loading history...</td></tr>');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_history',
                    nonce: mediaToolkit.nonce,
                    page: this.historyPage,
                    per_page: 50,
                    action_filter: action,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function (response) {
                    if (response.success) {
                        const history = response.data.history;
                        MediaToolkit.historyTotalPages = response.data.total_pages;

                        $('#history-count').text(response.data.total);
                        $('#page-info').text(`Page ${response.data.page} of ${response.data.total_pages}`);

                        $('#btn-prev-page').prop('disabled', response.data.page <= 1);
                        $('#btn-next-page').prop('disabled', response.data.page >= response.data.total_pages);

                        if (history.length === 0) {
                            $tbody.html(`<tr><td colspan="5" class="px-6 py-12 text-center">
                                <span class="dashicons dashicons-clock text-5xl text-gray-300 mb-4 block"></span>
                                <p class="text-gray-600 font-medium">No history found</p>
                                <span class="text-sm text-gray-400">Operations will be recorded here</span>
                            </td></tr>`);
                            return;
                        }

                        let html = '';
                        history.forEach(function (item) {
                            const dateObj = new Date(item.timestamp);
                            const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                            const actionClasses = {
                                'uploaded': 'mt-badge-success',
                                'migrated': 'mt-badge-info',
                                'deleted': 'mt-badge-error',
                                'edited': 'mt-badge-warning'
                            };
                            const actionClass = actionClasses[item.action] || 'mt-badge-neutral';

                            const userName = item.user_name || 'System';
                            const userInitial = userName.charAt(0).toUpperCase();

                            html += `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span class="block text-sm text-gray-900">${dateStr}</span>
                                        <span class="block text-xs text-gray-500">${timeStr}</span>
                                    </td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full ${actionClass}">${item.action}</span></td>
                                    <td class="px-4 py-3"><span class="text-sm text-gray-600 font-mono truncate max-w-[200px] block" title="${MediaToolkit.escapeHtml(item.file_path || item.s3_key || '-')}">${MediaToolkit.escapeHtml(item.file_path || item.s3_key || '-')}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${item.file_size ? MediaToolkit.formatBytes(item.file_size) : '-'}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="flex items-center justify-center w-7 h-7 text-xs font-semibold text-white bg-gray-700 rounded-full">${userInitial}</span>
                                            <span class="text-sm text-gray-600">${MediaToolkit.escapeHtml(userName)}</span>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        $tbody.html(html);
                    }
                }
            });
        },

        // Change history page
        changePage: function (delta) {
            this.historyPage = Math.max(1, Math.min(this.historyPage + delta, this.historyTotalPages));
            this.loadHistory();
        },

        // Clear History
        clearHistory: function () {
            if (!confirm('Are you sure you want to clear all history? This action cannot be undone.')) {
                return;
            }

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_clear_history',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        MediaToolkit.loadHistory();
                        MediaToolkit.showNotice('History cleared successfully', 'success');
                        // Update stat cards to 0
                        $('#stat-uploaded, #stat-migrated, #stat-edited, #stat-deleted').text('0');
                    } else {
                        MediaToolkit.showNotice('Failed to clear history', 'error');
                    }
                },
                error: () => {
                    MediaToolkit.showNotice('An error occurred', 'error');
                }
            });
        },

        // Export History
        exportHistory: function () {
            const action = $('#filter-history-action').val();
            const dateFrom = $('#filter-date-from').val();
            const dateTo = $('#filter-date-to').val();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_export_history',
                    nonce: mediaToolkit.nonce,
                    action_filter: action,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function (response) {
                    if (response.success) {
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    }
                }
            });
        },

        // Toggle auto-refresh
        toggleAutoRefresh: function () {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
            }

            if ($('#auto-refresh-logs').is(':checked')) {
                this.autoRefreshInterval = setInterval(() => {
                    this.loadLogs();
                }, 10000);
            }
        },

        // Switch logs page tabs
        switchLogsTab: function (e) {
            const $btn = $(e.currentTarget);
            const tabId = $btn.data('tab');

            // Update tab buttons - remove active state from all
            $('.logs-tab-btn')
                .removeClass('bg-white shadow-sm text-gray-900')
                .addClass('text-gray-500 hover:text-gray-700 hover:bg-white/50');

            // Add active state to clicked button
            $btn
                .removeClass('text-gray-500 hover:text-gray-700 hover:bg-white/50')
                .addClass('bg-white shadow-sm text-gray-900');

            // Update tab content
            $('.logs-tab-content').addClass('hidden');
            $('#tab-' + tabId).removeClass('hidden');

            // Load data for the selected tab
            if (tabId === 'optimization-status' && !this.optimizationLoaded) {
                this.loadOptimizationRecords();
                this.optimizationLoaded = true;
            }
        },

        // Optimization pagination state
        optimizationPage: 1,
        optimizationTotalPages: 1,
        optimizationLoaded: false,

        // Load optimization records
        loadOptimizationRecords: function () {
            const $tbody = $('#optimization-tbody');
            const status = $('#filter-opt-status').val();

            $tbody.html('<tr><td colspan="7" class="text-center py-8 text-gray-500">Loading optimization data...</td></tr>');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_optimization_records',
                    nonce: mediaToolkit.nonce,
                    page: this.optimizationPage,
                    per_page: 50,
                    status: status
                },
                success: (response) => {
                    if (response.success) {
                        const records = response.data.records;
                        this.optimizationTotalPages = response.data.total_pages;

                        // Update stats
                        const stats = response.data.stats;
                        $('#opt-stat-optimized').text(stats.optimized_count.toLocaleString());
                        $('#opt-stat-pending').text(stats.pending_count.toLocaleString());
                        $('#opt-stat-failed').text(stats.failed_count.toLocaleString());
                        $('#opt-stat-saved').text(stats.total_bytes_saved_formatted);

                        // Update count and pagination
                        $('#optimization-count').text(response.data.total);
                        $('#opt-page-info').text(`Page ${response.data.page} of ${response.data.total_pages || 1}`);
                        $('#btn-opt-prev-page').prop('disabled', response.data.page <= 1);
                        $('#btn-opt-next-page').prop('disabled', response.data.page >= response.data.total_pages);

                        if (records.length === 0) {
                            $tbody.html(`<tr><td colspan="7" class="px-6 py-12 text-center">
                                <span class="dashicons dashicons-images-alt2 w-14 h-14 text-5xl text-gray-300 m-auto mb-4 block"></span>
                                <p class="text-gray-600 font-medium">No optimization records found</p>
                                <span class="text-sm text-gray-400">Run optimization from the Optimize page to see records here</span>
                            </td></tr>`);
                            return;
                        }

                        let html = '';
                        records.forEach((record) => {
                            const statusClasses = {
                                'optimized': 'mt-badge-success',
                                'pending': 'mt-badge-warning',
                                'failed': 'mt-badge-error',
                                'skipped': 'mt-badge-neutral'
                            };
                            const statusClass = statusClasses[record.status] || 'mt-badge-neutral';

                            let savedDisplay = '-';
                            if (record.status === 'optimized' && record.percent_saved > 0) {
                                savedDisplay = `<span class="text-green-600 font-medium">${record.percent_saved.toFixed(1)}%</span>`;
                            }

                            let optimizedAtDisplay = '-';
                            if (record.optimized_at) {
                                const dateObj = new Date(record.optimized_at);
                                optimizedAtDisplay = `<span class="block text-sm text-gray-900">${dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>
                                    <span class="block text-xs text-gray-500">${dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>`;
                            }

                            const errorTitle = record.error_message ? ` title="${MediaToolkit.escapeHtml(record.error_message)}"` : '';

                            html += `
                                <tr class="hover:bg-gray-50"${errorTitle}>
                                    <td class="px-4 py-3 text-sm text-gray-500">${record.attachment_id}</td>
                                    <td class="px-4 py-3"><span class="text-sm text-gray-900 font-medium truncate max-w-[250px] block" title="${MediaToolkit.escapeHtml(record.file_name)}">${MediaToolkit.escapeHtml(record.file_name)}</span></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full ${statusClass}">${record.status}</span></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600 font-mono">${record.original_size_formatted}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600 font-mono">${record.optimized_size_formatted}</td>
                                    <td class="px-4 py-3 text-right text-sm">${savedDisplay}</td>
                                    <td class="px-4 py-3">${optimizedAtDisplay}</td>
                                </tr>
                            `;
                        });

                        $tbody.html(html);
                    } else {
                        $tbody.html(`<tr><td colspan="7" class="px-6 py-12 text-center">
                            <span class="dashicons dashicons-warning text-5xl text-red-300 mb-4 block"></span>
                            <p class="text-gray-600 font-medium">Error loading data</p>
                            <span class="text-sm text-gray-400">${response.data?.message || 'Unknown error'}</span>
                        </td></tr>`);
                    }
                },
                error: () => {
                    $tbody.html(`<tr><td colspan="7" class="px-6 py-12 text-center">
                        <span class="dashicons dashicons-warning text-5xl text-red-300 mb-4 block"></span>
                        <p class="text-gray-600 font-medium">Connection error</p>
                        <span class="text-sm text-gray-400">Please try again</span>
                    </td></tr>`);
                }
            });
        },

        // Change optimization page
        changeOptimizationPage: function (delta) {
            this.optimizationPage = Math.max(1, Math.min(this.optimizationPage + delta, this.optimizationTotalPages));
            this.loadOptimizationRecords();
        },

        // Reset failed optimization records
        resetFailedOptimization: function () {
            if (!confirm('Reset all failed optimization records to pending status? They will be retried on the next optimization run.')) {
                return;
            }

            const $btn = $('#btn-reset-failed');
            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Resetting...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_reset_failed_optimization',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        MediaToolkit.showNotice(response.data.message, 'success');
                        this.loadOptimizationRecords();
                    } else {
                        MediaToolkit.showNotice(response.data?.message || 'Failed to reset records', 'error');
                    }
                },
                error: () => {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Load Dashboard Stats
        loadDashboardStats: function () {
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_dashboard_stats',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Update stats
                        $('#stat-total-files').text(response.data.stats.total_files);
                        $('#stat-storage').text(response.data.stats.total_storage_formatted);
                        $('#stat-today').text(response.data.stats.files_today);
                        $('#stat-errors').text(response.data.stats.errors_last_7_days);

                        // Draw sparkline
                        MediaToolkit.drawSparkline(response.data.sparkline);
                    }
                }
            });
        },

        // Draw sparkline chart
        drawSparkline: function (sparklineData) {
            const canvas = document.getElementById('sparkline-chart');
            if (!canvas) return;

            // Support both old format (array) and new format (object with labels/values)
            const data = sparklineData.values || sparklineData;
            const labels = sparklineData.labels || [];

            const ctx = canvas.getContext('2d');
            const dpr = window.devicePixelRatio || 1;
            const width = canvas.parentElement.offsetWidth;
            const height = 120; // Increased height to accommodate labels

            // Scale canvas for high-DPI displays
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            ctx.scale(dpr, dpr);

            const max = Math.max(...data, 1);
            const paddingTop = 25; // Space for values
            const paddingBottom = 25; // Space for day labels
            const paddingX = 30;
            const chartWidth = width - (paddingX * 2);
            const chartHeight = height - paddingTop - paddingBottom;
            const stepX = data.length > 1 ? chartWidth / (data.length - 1) : chartWidth;

            // Clear (use logical dimensions, not canvas dimensions)
            ctx.clearRect(0, 0, width, height);

            // Draw horizontal grid lines
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.06)';
            ctx.lineWidth = 1;
            for (let i = 0; i <= 3; i++) {
                const y = paddingTop + (chartHeight / 3) * i;
                ctx.beginPath();
                ctx.moveTo(paddingX, y);
                ctx.lineTo(width - paddingX, y);
                ctx.stroke();
            }

            // Draw gradient fill
            ctx.beginPath();
            ctx.moveTo(paddingX, height - paddingBottom);

            data.forEach((value, i) => {
                const x = paddingX + (i * stepX);
                const y = height - paddingBottom - ((value / max) * chartHeight);
                ctx.lineTo(x, y);
            });

            ctx.lineTo(width - paddingX, height - paddingBottom);
            ctx.closePath();

            const gradient = ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, 'rgba(31, 41, 55, 0.15)');
            gradient.addColorStop(1, 'rgba(31, 41, 55, 0)');
            ctx.fillStyle = gradient;
            ctx.fill();

            // Draw line
            ctx.beginPath();
            data.forEach((value, i) => {
                const x = paddingX + (i * stepX);
                const y = height - paddingBottom - ((value / max) * chartHeight);
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.strokeStyle = '#1f2937';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Draw dots and values
            ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';

            data.forEach((value, i) => {
                const x = paddingX + (i * stepX);
                const y = height - paddingBottom - ((value / max) * chartHeight);

                // Draw dot
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fillStyle = '#fff';
                ctx.fill();
                ctx.strokeStyle = '#1f2937';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Draw value above the dot
                ctx.fillStyle = '#1f2937';
                ctx.fillText(value.toString(), x, y - 10);

                // Draw day label below the chart
                if (labels[i]) {
                    ctx.fillStyle = '#6b7280';
                    ctx.fillText(labels[i], x, height - 6);
                }
            });
        },

        // Toggle password visibility
        togglePasswordVisibility: function () {
            const $input = $('#github_token');
            const $btn = $('#btn-toggle-password');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $btn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        // Save Update Settings
        saveUpdateSettings: function () {
            const $btn = $('#btn-save-update-settings');

            const data = {
                action: 'media_toolkit_save_update_settings',
                nonce: mediaToolkit.nonce,
                github_token: $('#github_token').val(),
                auto_update: $('#auto_update').is(':checked') ? '1' : ''
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice(response.data.message, 'success');
                        // Reload to reflect token status
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save settings', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Remove GitHub Token
        removeGitHubToken: function () {
            if (!confirm('Are you sure you want to remove the GitHub token? This will disable automatic updates from private repositories.')) {
                return;
            }

            const $btn = $('#btn-remove-token');

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Removing...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_remove_github_token',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice(response.data.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to remove token', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Check for Updates
        checkForUpdates: function () {
            const $btn = $('#btn-check-updates');
            const $result = $('#update-check-result');

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Checking...');
            $result.hide();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_check_updates',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;
                        let html = '';

                        if (data.update_available) {
                            html = `
                                <div class="flex gap-4 p-4 rounded-lg mt-alert-success">
                                    <span class="dashicons dashicons-yes-alt text-green-600"></span>
                                    <div>
                                        <strong class="block text-green-800">${data.message}</strong>
                                        <p class="mt-2"><a href="${data.update_url}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg">Update Now</a></p>
                                    </div>
                                </div>
                            `;
                        } else {
                            html = `
                                <div class="flex gap-4 p-4 rounded-lg mt-alert-info">
                                    <span class="dashicons dashicons-yes text-blue-600"></span>
                                    <div>
                                        <strong class="block text-blue-800">${data.message}</strong>
                                        <p class="mt-1 text-sm text-blue-600">Current version: v${data.current_version}</p>
                                    </div>
                                </div>
                            `;
                        }

                        $result.html(html).show();
                    } else {
                        $result.html(`
                            <div class="flex gap-4 p-4 rounded-lg mt-alert-error">
                                <span class="dashicons dashicons-warning text-red-600"></span>
                                <div>
                                    <strong class="block text-red-800">Check failed</strong>
                                    <p class="mt-1 text-sm text-red-600">${response.data?.message || 'Unknown error'}</p>
                                </div>
                            </div>
                        `).show();
                    }
                },
                error: function () {
                    $result.html(`
                        <div class="flex gap-4 p-4 rounded-lg mt-alert-error">
                            <strong class="text-red-800">Connection error</strong>
                            <p class="text-sm text-red-600">Please try again later.</p>
                        </div>
                    `).show();
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Import file storage
        importFileData: null,

        // Setup Import Drop Zone
        setupImportDropZone: function () {
            const self = this;
            const $dropZone = $('#import-drop-zone');
            const $fileInput = $('#import-file-input');

            if (!$dropZone.length) return;

            // Click to browse
            $dropZone.on('click', function () {
                $fileInput.trigger('click');
            });

            // File input change
            $fileInput.on('change', function () {
                if (this.files && this.files[0]) {
                    self.handleImportFile(this.files[0]);
                }
            });

            // Drag events
            $dropZone.on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('border-gray-500 bg-gray-50');
            });

            $dropZone.on('dragleave drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('border-gray-500 bg-gray-50');
            });

            $dropZone.on('drop', function (e) {
                const files = e.originalEvent.dataTransfer.files;
                if (files && files[0]) {
                    self.handleImportFile(files[0]);
                }
            });

            // Remove file button
            $('#btn-remove-import-file').on('click', function (e) {
                e.stopPropagation();
                self.clearImportFile();
            });
        },

        // Handle Import File
        handleImportFile: function (file) {
            const self = this;
            const $preview = $('#import-file-preview');
            const $result = $('#import-result');

            // Validate file type
            if (!file.name.endsWith('.json')) {
                $result.removeClass('hidden').html(`
                    <div class="flex gap-3 text-red-700 bg-red-50">
                        <span class="dashicons dashicons-warning"></span>
                        <span>Invalid file type. Please select a .json file.</span>
                    </div>
                `);
                return;
            }

            // Read file
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = JSON.parse(e.target.result);

                    // Validate structure
                    if (!data.export_format || !data.options) {
                        throw new Error('Invalid export file format');
                    }

                    // Store data
                    self.importFileData = data;

                    // Show preview
                    $('#import-file-name').text(file.name);
                    $('#import-file-info').text(`v${data.plugin_version || 'unknown'} • ${new Date(data.exported_at).toLocaleDateString()} • ${Object.keys(data.options).length} settings`);
                    $preview.removeClass('hidden');
                    $('#import-drop-zone').addClass('hidden');
                    $('#btn-import-settings').prop('disabled', false);
                    $result.addClass('hidden');

                } catch (err) {
                    $result.removeClass('hidden').html(`
                        <div class="flex gap-3 text-red-700 bg-red-50">
                            <span class="dashicons dashicons-warning"></span>
                            <span>Invalid JSON file: ${err.message}</span>
                        </div>
                    `);
                    self.clearImportFile();
                }
            };
            reader.readAsText(file);
        },

        // Clear Import File
        clearImportFile: function () {
            this.importFileData = null;
            $('#import-file-preview').addClass('hidden');
            $('#import-drop-zone').removeClass('hidden');
            $('#import-file-input').val('');
            $('#btn-import-settings').prop('disabled', true);
            $('#import-result').addClass('hidden');
        },

        // Export Settings
        exportSettings: function () {
            const $btn = $('#btn-export-settings');

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Exporting...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_export_settings',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Create download
                        const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);

                        MediaToolkit.showNotice('Settings exported successfully!', 'success');
                    } else {
                        MediaToolkit.showNotice(response.data?.message || 'Export failed', 'error');
                    }
                },
                error: function () {
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Import Settings
        importSettings: function () {
            const self = this;
            const $btn = $('#btn-import-settings');
            const $result = $('#import-result');

            if (!this.importFileData) {
                $result.removeClass('hidden').html(`
                    <div class="flex gap-3 text-red-700 bg-red-50">
                        <span class="dashicons dashicons-warning"></span>
                        <span>Please select a file to import.</span>
                    </div>
                `);
                return;
            }

            const merge = $('#import_merge').is(':checked');

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Importing...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_import_settings',
                    nonce: mediaToolkit.nonce,
                    import_data: JSON.stringify(this.importFileData),
                    merge: merge ? '1' : ''
                },
                success: function (response) {
                    if (response.success) {
                        $result.removeClass('hidden').html(`
                            <div class="flex gap-3 text-green-700 bg-green-50">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span>${response.data.message}</span>
                            </div>
                        `);

                        // Clear file and reload after 2 seconds
                        self.clearImportFile();
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.removeClass('hidden').html(`
                            <div class="flex gap-3 text-red-700 bg-red-50">
                                <span class="dashicons dashicons-warning"></span>
                                <span>${response.data?.message || 'Import failed'}</span>
                            </div>
                        `);
                    }
                },
                error: function () {
                    $result.removeClass('hidden').html(`
                        <div class="flex gap-3 text-red-700 bg-red-50">
                            <span class="dashicons dashicons-warning"></span>
                            <span>An error occurred. Please try again.</span>
                        </div>
                    `);
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save Resize Settings
        saveResizeSettings: function () {
            const $btn = $('#btn-save-resize-settings');
            const $status = $('#resize-settings-status');

            const data = {
                action: 'media_toolkit_save_resize_settings',
                nonce: mediaToolkit.nonce,
                enabled: $('#resize-enabled').is(':checked') ? 'true' : 'false',
                max_width: $('#resize-max-width').val(),
                max_height: $('#resize-max-height').val(),
                jpeg_quality: $('#resize-jpeg-quality').val(),
                convert_bmp_to_jpg: $('#resize-convert-bmp').is(':checked') ? 'true' : 'false'
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');
            $status.text('');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        $status.text('✓ Saved').css('color', '#00a32a');
                        MediaToolkit.showNotice('Resize settings saved successfully!', 'success');

                        // Reload after a short delay to update stats
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        $status.text('✗ Error').css('color', '#d63638');
                        MediaToolkit.showNotice(response.data?.message || 'Failed to save settings', 'error');
                    }
                },
                error: function () {
                    $status.text('✗ Error').css('color', '#d63638');
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save Optimization Settings (Optimize page)
        saveOptimizationSettings: function () {
            const $btn = $('#btn-save-settings');
            const $status = $('#settings-status');

            const data = {
                action: 'media_toolkit_save_optimize_settings',
                nonce: mediaToolkit.nonce,
                jpeg_quality: $('#jpeg-quality').val(),
                png_compression: $('#png-compression').val(),
                max_file_size_mb: $('#max-file-size').val(),
                strip_metadata: $('#strip-metadata').is(':checked') ? 'true' : 'false'
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Saving...');
            $status.text('');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        $status.text('✓ Saved').css('color', '#00a32a');
                        MediaToolkit.showNotice('Optimization settings saved!', 'success');
                    } else {
                        $status.text('✗ Error').css('color', '#d63638');
                        MediaToolkit.showNotice(response.data?.message || 'Failed to save settings', 'error');
                    }
                },
                error: function () {
                    $status.text('✗ Error').css('color', '#d63638');
                    MediaToolkit.showNotice('An error occurred. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Close modal
        closeModal: function () {
            $('.mt-modal-overlay').hide();
        },

        // Show notification
        showNotice: function (message, type) {
            const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.mt-wrap h1').after($notice);

            setTimeout(function () {
                $notice.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility: Format date
        formatDate: function (dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString();
        },

        // Utility: Format bytes
        formatBytes: function (bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // Utility: Escape HTML
        escapeHtml: function (str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        MediaToolkit.init();
    });

})(jQuery);
