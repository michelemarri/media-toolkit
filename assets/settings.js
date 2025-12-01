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

            // Legacy save buttons (for backwards compatibility)
            $('#btn-save-active-env').on('click', this.saveEnvironment.bind(this));
            $('#btn-save-settings').on('click', this.saveSettings.bind(this));

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

            // Sync S3 stats (Tools page)
            $('#btn-sync-stats').on('click', this.syncS3Stats.bind(this));

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

            // Auto-refresh toggle
            $('#auto-refresh-logs').on('change', this.toggleAutoRefresh.bind(this));

            // Pagination
            $('#btn-prev-page').on('click', () => this.changePage(-1));
            $('#btn-next-page').on('click', () => this.changePage(1));

            // Filters
            $('#filter-log-level, #filter-log-operation').on('change', this.loadLogs.bind(this));

            // Modal close
            $('.mds-modal-close').on('click', this.closeModal);
            $('.mds-modal-close').on('click', this.closeModal);
            $(document).on('click', '.mds-modal, .mds-modal-overlay', function (e) {
                if ($(e.target).hasClass('mds-modal') || $(e.target).hasClass('mds-modal-overlay')) {
                    MediaToolkit.closeModal();
                }
            });

            // Update tab handlers
            $('#btn-toggle-password').on('click', this.togglePasswordVisibility.bind(this));
            $('#btn-save-update-settings').on('click', this.saveUpdateSettings.bind(this));
            $('#btn-remove-token').on('click', this.removeGitHubToken.bind(this));
            $('#btn-check-updates').on('click', this.checkForUpdates.bind(this));
        },

        loadInitialData: function () {
            // Check if on logs page
            if ($('#logs-table').length && !$('#history-table').length) {
                this.loadLogs();
                this.toggleAutoRefresh();
            }

            // Check if on history page
            if ($('#history-table').length && !$('#logs-table').length) {
                this.loadHistory();
            }

            // Check if on activity tab (both tables present)
            if ($('#logs-table').length && $('#history-table').length) {
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
                const accessKey = $('#access_key').val();
                const secretKey = $('#secret_key').val();
                const region = $('#region').val();
                const bucket = $('#bucket').val();

                const isValid = accessKey && secretKey && region && bucket;
                $('#btn-test-credentials').prop('disabled', !isValid);
                $('#btn-test-settings').prop('disabled', !isValid);
            };

            // Check on load
            checkFields();

            // Check on input change
            $('#mds-credentials-panel input, #mds-credentials-panel select').on('input change', checkFields);
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

            const data = {
                action: 'media_toolkit_save_credentials',
                nonce: mediaToolkit.nonce,
                access_key: $('#access_key').val(),
                secret_key: $('#secret_key').val(),
                region: $('#region').val(),
                bucket: $('#bucket').val()
            };

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Credentials saved successfully!', 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        MediaToolkit.showNotice(response.data.message || 'Failed to save credentials', 'error');
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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Saving...');

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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Saving...');

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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Saving...');

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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Testing...');
            $results.html('<div class="mds-loading">Running connection tests...</div>');
            $modal.show();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    MediaToolkit.showTestResults($results, response);
                },
                error: function () {
                    $results.html('<div class="test-result error">Connection test failed. Please try again.</div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Save Settings
        saveSettings: function () {
            const $btn = $('#btn-save-settings');

            const data = {
                action: 'media_toolkit_save_settings',
                nonce: mediaToolkit.nonce,
                access_key: $('#access_key').val(),
                secret_key: $('#secret_key').val(),
                region: $('#region').val(),
                bucket: $('#bucket').val(),
                cdn_url: $('#cdn_url').val(),
                cdn_provider: $('#cdn_provider').val(),
                cloudflare_zone_id: $('#cloudflare_zone_id').val(),
                cloudflare_api_token: $('#cloudflare_api_token').val(),
                cloudfront_distribution_id: $('#cloudfront_distribution_id').val(),
                cache_control: $('#cache_control').val(),
                s3_sync_interval: $('#s3_sync_interval').val(),
                remove_local: $('#remove_local').is(':checked') ? 'true' : 'false',
                remove_on_uninstall: $('#remove_on_uninstall').is(':checked') ? 'true' : 'false'
            };

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        MediaToolkit.showNotice('Settings saved successfully!', 'success');
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
                    $btn.prop('disabled', false).text('Save Settings');
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
            $results.html('<div class="mds-loading">Running connection tests...</div>');
            $modal.show();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: data,
                success: function (response) {
                    MediaToolkit.showTestResults($results, response);
                },
                error: function () {
                    $results.html('<div class="test-result error">Connection test failed. Please try again.</div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        },

        // Sync S3 statistics
        syncS3Stats: function () {
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

            if (!confirm('This will update Cache-Control headers on ALL files in S3 for the current environment. This operation may take a while. Continue?')) {
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
            $('#cache-status-text').html('<span style="color: #00a32a;">Complete!</span>');

            this.addCacheSyncLog(`✓ Complete! Updated ${state.totalSuccess.toLocaleString()} files` +
                (state.totalFailed > 0 ? ` (${state.totalFailed} failed)` : ''), 'success');

            $('#btn-cancel-cache-sync').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
        },

        // Fail cache sync
        failCacheSync: function (message) {
            const state = this.cacheSyncState;
            state.isRunning = false;

            $('#cache-status-text').html('<span style="color: #d63638;">Error</span>');
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
            $log.find('.mds-terminal-muted').first().remove();

            const typeClass = {
                'info': '',
                'success': 'mds-terminal-success',
                'warning': 'mds-terminal-warning',
                'error': 'mds-terminal-error'
            }[type] || '';

            $log.append(`
                <div class="mds-terminal-line ${typeClass}">
                    <span class="mds-terminal-timestamp">[${timestamp}]</span>
                    <span>${message}</span>
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
                    action: 'media_toolkit_save_settings',
                    nonce: mediaToolkit.nonce,
                    s3_sync_interval: interval
                },
                success: function (response) {
                    // Silently save
                },
                error: function () {
                    alert('Failed to save sync interval');
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

                let html = '<div class="mds-test-results-grid">';
                let index = 0;

                for (const [key, result] of Object.entries(results)) {
                    const icon = icons[key] || 'yes-alt';
                    const title = key.charAt(0).toUpperCase() + key.slice(1);

                    // Determine status: success, error, or info (not configured)
                    const isNotConfigured = result.message && result.message.toLowerCase().includes('not configured');
                    let statusClass, statusIcon;

                    if (!result.success) {
                        statusClass = 'error';
                        statusIcon = 'warning';
                    } else if (isNotConfigured) {
                        statusClass = 'info';
                        statusIcon = 'info-outline';
                    } else {
                        statusClass = 'success';
                        statusIcon = 'yes-alt';
                    }

                    html += `
                        <div class="mds-test-card mds-test-card-${statusClass}" style="animation-delay: ${index * 0.1}s">
                            <div class="mds-test-card-header">
                                <div class="mds-test-card-icon mds-test-card-icon-${statusClass}">
                                    <span class="dashicons dashicons-${icon}"></span>
                                </div>
                                ${statusClass !== 'info' ? `
                                <span class="mds-test-card-status mds-test-card-status-${statusClass}">
                                    <span class="dashicons dashicons-${statusIcon}"></span>
                                </span>
                                ` : ''}
                            </div>
                            <div class="mds-test-card-body">
                                <h4 class="mds-test-card-title">${title}</h4>
                                <p class="mds-test-card-message">${result.message}</p>
                            </div>
                        </div>
                    `;
                    index++;
                }

                html += '</div>';

                $results.html(html);
            } else {
                $results.html(`
                    <div class="mds-test-error">
                        <span class="dashicons dashicons-warning"></span>
                        <p>${response.data?.message || 'Test failed'}</p>
                    </div>
                `);
            }
        },

        // Load Logs
        loadLogs: function () {
            const $tbody = $('#logs-tbody');
            const level = $('#filter-log-level').val();
            const operation = $('#filter-log-operation').val();

            $tbody.html('<tr><td colspan="5" class="mds-loading">Loading logs...</td></tr>');

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
                            $tbody.html(`<tr><td colspan="5">
                                <div class="mds-empty-state">
                                    <span class="dashicons dashicons-media-text"></span>
                                    <p>No logs found</p>
                                    <span>Activity will appear here as operations occur</span>
                                </div>
                            </td></tr>`);
                            return;
                        }

                        let html = '';
                        logs.forEach(function (log) {
                            const levelClass = log.level === 'error' ? 'error' : log.level === 'warning' ? 'warning' : log.level === 'success' ? 'success' : 'info';
                            const dateObj = new Date(log.timestamp);
                            const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                            html += `
                                <tr>
                                    <td>
                                        <span class="mds-timestamp">${dateStr}</span>
                                        <span class="mds-timestamp-time">${timeStr}</span>
                                    </td>
                                    <td><span class="mds-badge mds-badge-${levelClass}">${log.level}</span></td>
                                    <td><span class="mds-badge mds-badge-default">${MediaToolkit.escapeHtml(log.operation)}</span></td>
                                    <td><span class="mds-file-path" title="${MediaToolkit.escapeHtml(log.file_name || '-')}">${MediaToolkit.escapeHtml(log.file_name || '-')}</span></td>
                                    <td>${MediaToolkit.escapeHtml(log.message)}</td>
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

            $tbody.html('<tr><td colspan="5" class="mds-loading">Loading history...</td></tr>');

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
                            $tbody.html(`<tr><td colspan="5">
                                <div class="mds-empty-state">
                                    <span class="dashicons dashicons-clock"></span>
                                    <p>No history found</p>
                                    <span>Operations will be recorded here</span>
                                </div>
                            </td></tr>`);
                            return;
                        }

                        let html = '';
                        history.forEach(function (item) {
                            const dateObj = new Date(item.timestamp);
                            const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            const actionClass = item.action || 'default';
                            const userName = item.user_name || 'System';
                            const userInitial = userName.charAt(0).toUpperCase();

                            html += `
                                <tr>
                                    <td>
                                        <span class="mds-timestamp">${dateStr}</span>
                                        <span class="mds-timestamp-time">${timeStr}</span>
                                    </td>
                                    <td><span class="mds-badge mds-badge-${actionClass}">${item.action}</span></td>
                                    <td><span class="mds-file-path" title="${MediaToolkit.escapeHtml(item.file_path || item.s3_key || '-')}">${MediaToolkit.escapeHtml(item.file_path || item.s3_key || '-')}</span></td>
                                    <td>${item.file_size ? MediaToolkit.formatBytes(item.file_size) : '-'}</td>
                                    <td>
                                        <div class="mds-user-info">
                                            <span class="mds-user-avatar">${userInitial}</span>
                                            <span class="mds-user-name">${MediaToolkit.escapeHtml(userName)}</span>
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
            gradient.addColorStop(0, 'rgba(34, 113, 177, 0.2)');
            gradient.addColorStop(1, 'rgba(34, 113, 177, 0)');
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
            ctx.strokeStyle = '#2271b1';
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
                ctx.strokeStyle = '#2271b1';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Draw value above the dot
                ctx.fillStyle = '#1d2327';
                ctx.fillText(value.toString(), x, y - 10);

                // Draw day label below the chart
                if (labels[i]) {
                    ctx.fillStyle = '#646970';
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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Saving...');

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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Removing...');

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
            $btn.html('<span class="dashicons dashicons-update mds-spin"></span> Checking...');
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
                                <div style="padding: 16px; background: var(--mds-success-bg, #e7f7e7); border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--mds-success, #00a32a); font-size: 24px; width: 24px; height: 24px;"></span>
                                    <div>
                                        <strong>${data.message}</strong>
                                        <p style="margin: 4px 0 0;"><a href="${data.update_url}" class="button button-primary">Update Now</a></p>
                                    </div>
                                </div>
                            `;
                        } else {
                            html = `
                                <div style="padding: 16px; background: var(--mds-info-bg, #e7f3ff); border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                    <span class="dashicons dashicons-yes" style="color: var(--mds-info, #0073aa); font-size: 24px; width: 24px; height: 24px;"></span>
                                    <div>
                                        <strong>${data.message}</strong>
                                        <p style="margin: 4px 0 0; color: var(--mds-text-secondary, #666);">Current version: v${data.current_version}</p>
                                    </div>
                                </div>
                            `;
                        }

                        $result.html(html).show();
                    } else {
                        $result.html(`
                            <div style="padding: 16px; background: var(--mds-error-bg, #fce8e8); border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                                <span class="dashicons dashicons-warning" style="color: var(--mds-error, #d63638); font-size: 24px; width: 24px; height: 24px;"></span>
                                <div>
                                    <strong>Check failed</strong>
                                    <p style="margin: 4px 0 0;">${response.data?.message || 'Unknown error'}</p>
                                </div>
                            </div>
                        `).show();
                    }
                },
                error: function () {
                    $result.html(`
                        <div style="padding: 16px; background: var(--mds-error-bg, #fce8e8); border-radius: 8px;">
                            <strong>Connection error</strong>
                            <p style="margin: 4px 0 0;">Please try again later.</p>
                        </div>
                    `).show();
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Close modal
        closeModal: function () {
            $('.mds-modal, .mds-modal-overlay').hide();
        },

        // Show notification
        showNotice: function (message, type) {
            const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.mds-wrap h1').after($notice);

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
