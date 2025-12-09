/**
 * CloudSync JavaScript
 * Handles the CloudSync UI interactions
 */
(function($) {
    'use strict';

    // Check if we're on the CloudSync page
    if (!$('#btn-start-sync').length) {
        return;
    }

    const CloudSync = {
        state: {
            isRunning: false,
            isPaused: false,
            mode: 'sync'
        },

        cacheSyncState: {
            isRunning: false,
            isCancelled: false,
            totalFiles: 0,
            totalProcessed: 0,
            totalSuccess: 0,
            totalFailed: 0,
            continuationToken: null
        },

        init: function() {
            this.bindEvents();
            this.loadInitialStatus();
        },

        bindEvents: function() {
            // Main controls
            $('#btn-start-sync').on('click', () => this.startSync());
            $('#btn-pause-sync').on('click', () => this.pauseSync());
            $('#btn-resume-sync').on('click', () => this.resumeSync());
            $('#btn-stop-sync').on('click', () => this.stopSync());
            
            // Status refresh
            $('#btn-refresh-status').on('click', () => this.refreshStatus());
            
            // Advanced actions
            $('#btn-deep-analyze').on('click', () => this.deepAnalyze());
            $('#btn-view-discrepancies').on('click', () => this.viewDiscrepancies());
            $('#btn-clear-metadata').on('click', () => this.clearMetadata());
            
            // Cache headers update
            $('#btn-start-cache-sync').on('click', () => this.startCacheSync());
            $('#btn-cancel-cache-sync').on('click', () => this.cancelCacheSync());
            
            // Action buttons in suggested actions
            $(document).on('click', '.action-btn', (e) => {
                const action = $(e.currentTarget).data('action');
                this.executeAction(action);
            });
            
            // Remove local warning
            $('#remove-local').on('change', (e) => this.handleRemoveLocalChange(e));
            $('#accept-risk').on('change', (e) => {
                $('#btn-confirm-remove-local').prop('disabled', !e.target.checked);
            });
            $('#btn-confirm-remove-local').on('click', () => this.confirmRemoveLocal());
            $('#btn-cancel-remove-local').on('click', () => this.cancelRemoveLocal());
            
            // Modal close buttons
            $('.modal-close').on('click', function() {
                $(this).closest('.mt-modal-overlay').hide();
            });
            
            // Confirmation modal
            $('#btn-confirm-no').on('click', () => $('#confirm-modal').hide());
        },

        loadInitialStatus: function() {
            this.refreshStatus(false);
        },

        refreshStatus: function(showDeep = false) {
            const $btn = $('#btn-refresh-status');
            $btn.prop('disabled', true).find('.dashicons').addClass('animate-spin');
            
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_analyze',
                    nonce: mediaToolkit.nonce,
                    deep: showDeep ? 'true' : 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatusUI(response.data);
                        if (showDeep) {
                            this.log('Deep analysis completed', 'success');
                        }
                    } else {
                        this.log('Error: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.log('Request failed: ' + error, 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('animate-spin');
                }
            });
        },

        updateStatusUI: function(data) {
            // Update stats
            $('#stat-total').text(data.total_attachments || 0);
            $('#stat-migrated').text(data.migrated_to_cloud || 0);
            $('#stat-pending').text(data.pending_migration || 0);
            $('#stat-issues').text(data.integrity_issues || 0);
            
            // Update progress
            const percentage = data.sync_percentage || 0;
            $('#sync-percentage').text(percentage + '%');
            $('#sync-progress').css('width', percentage + '%');
            
            // Update status badge
            this.updateStatusBadge(data.overall_status);
            
            // Update issues styling
            const $issuesCard = $('#stat-issues').closest('.rounded-xl');
            if (data.integrity_issues > 0) {
                $issuesCard.removeClass('bg-gray-50').addClass('bg-red-50');
                $issuesCard.find('.text-gray-500').removeClass('text-gray-500').addClass('text-red-600');
                $issuesCard.find('.text-gray-900').removeClass('text-gray-900').addClass('text-red-700');
            } else {
                $issuesCard.removeClass('bg-red-50').addClass('bg-gray-50');
                $issuesCard.find('.text-red-600').removeClass('text-red-600').addClass('text-gray-500');
                $issuesCard.find('.text-red-700').removeClass('text-red-700').addClass('text-gray-900');
            }

            // Update optimization stats
            this.updateOptimizationUI(data);
        },

        updateOptimizationUI: function(data) {
            const optPercentage = data.optimization_percentage || 0;
            const optTotal = data.total_images || 0;
            const optDone = data.optimized_images || 0;
            const optPending = data.pending_optimization || 0;
            const optSaved = data.total_bytes_saved_formatted || '0 B';
            const avgSavings = data.average_savings_percent || 0;

            // Update optimization progress
            $('#optimization-percentage').text(optPercentage + '%');
            $('#optimization-progress').css('width', optPercentage + '%');

            // Update optimization progress bar color
            const $progressFill = $('#optimization-progress');
            $progressFill.removeClass('bg-green-500 bg-amber-500 bg-gray-300');
            if (optPercentage === 100) {
                $progressFill.addClass('bg-green-500');
            } else if (optPercentage > 0) {
                $progressFill.addClass('bg-amber-500');
            } else {
                $progressFill.addClass('bg-gray-300');
            }

            // Update optimization stats
            $('#opt-stat-total').text(optTotal);
            $('#opt-stat-done').text(optDone);
            $('#opt-stat-pending').text(optPending);
            $('#opt-stat-saved').text(optSaved);

            // Update pending card styling
            const $pendingCard = $('#opt-stat-pending').closest('.rounded-xl');
            if (optPending > 0) {
                $pendingCard.removeClass('bg-gray-50').addClass('bg-yellow-50');
                $pendingCard.find('div:first').removeClass('text-gray-500').addClass('text-yellow-600');
                $pendingCard.find('.font-bold').removeClass('text-gray-900').addClass('text-yellow-700');
            } else {
                $pendingCard.removeClass('bg-yellow-50').addClass('bg-gray-50');
                $pendingCard.find('div:first').removeClass('text-yellow-600').addClass('text-gray-500');
                $pendingCard.find('.font-bold').removeClass('text-yellow-700').addClass('text-gray-900');
            }
        },

        updateStatusBadge: function(status) {
            let badgeClass = 'bg-gray-100 text-gray-700';
            let badgeText = 'Unknown';
            
            switch (status) {
                case 'synced':
                    badgeClass = 'bg-green-100 text-green-700';
                    badgeText = 'Fully Synced';
                    break;
                case 'pending_sync':
                    badgeClass = 'bg-yellow-100 text-yellow-700';
                    badgeText = 'Pending Sync';
                    break;
                case 'integrity_issues':
                    badgeClass = 'bg-red-100 text-red-700';
                    badgeText = 'Integrity Issues';
                    break;
                case 'not_started':
                    badgeClass = 'bg-gray-100 text-gray-700';
                    badgeText = 'Not Started';
                    break;
                case 'partial':
                    badgeClass = 'bg-blue-100 text-blue-700';
                    badgeText = 'Partially Synced';
                    break;
            }
            
            $('#status-badge span')
                .removeClass()
                .addClass(badgeClass + ' px-3 py-1 rounded-full')
                .text(badgeText);
        },

        startSync: function() {
            const mode = $('#sync-mode').val();
            const batchSize = $('#batch-size').val();
            const removeLocal = $('#remove-local').is(':checked');
            
            this.state.mode = mode;
            this.log('Starting sync in ' + mode + ' mode...', 'info');
            
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_start',
                    nonce: mediaToolkit.nonce,
                    mode: mode,
                    batch_size: batchSize,
                    remove_local: removeLocal ? 'true' : 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.state.isRunning = true;
                        this.state.isPaused = false;
                        this.updateControlsState();
                        this.updateStateUI(response.data.state);
                        this.log('Sync started. Total items: ' + response.data.state.total_files, 'success');
                        this.processBatch();
                    } else {
                        this.log('Error starting sync: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.log('Request failed: ' + error, 'error');
                }
            });
        },

        processBatch: function() {
            if (!this.state.isRunning || this.state.isPaused) {
                return;
            }
            
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_process_batch',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStateUI(response.data.state);
                        
                        // Log batch results
                        if (response.data.batch_processed > 0) {
                            this.log('Processed ' + response.data.batch_processed + ' files', 'success');
                        }
                        if (response.data.batch_failed > 0) {
                            this.log(response.data.batch_failed + ' failed in this batch', 'warning');
                            
                            // Log individual errors
                            if (response.data.batch_errors) {
                                response.data.batch_errors.forEach(err => {
                                    this.log('❌ #' + err.item_id + ': ' + err.error, 'error');
                                });
                            }
                        }
                        
                        // Update stats
                        if (response.data.stats) {
                            this.updateStatusUI(response.data.stats);
                        }
                        
                        if (response.data.complete) {
                            this.state.isRunning = false;
                            this.updateControlsState();
                            this.log('Sync completed!', 'success');
                            this.refreshStatus();
                        } else {
                            // Continue processing
                            setTimeout(() => this.processBatch(), 100);
                        }
                    } else {
                        this.log('Error: ' + (response.data?.message || 'Unknown error'), 'error');
                        this.state.isRunning = false;
                        this.updateControlsState();
                    }
                },
                error: (xhr, status, error) => {
                    this.log('Request failed: ' + error, 'error');
                    this.state.isRunning = false;
                    this.updateControlsState();
                }
            });
        },

        pauseSync: function() {
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_pause',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state.isPaused = true;
                        this.updateControlsState();
                        this.log('Sync paused', 'info');
                    }
                }
            });
        },

        resumeSync: function() {
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_resume',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state.isPaused = false;
                        this.updateControlsState();
                        this.log('Sync resumed', 'info');
                        this.processBatch();
                    }
                }
            });
        },

        stopSync: function() {
            this.showConfirmModal(
                'Stop Sync',
                'Are you sure you want to stop the sync? Progress will be lost.',
                () => {
                    $.ajax({
                        url: mediaToolkit.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'media_toolkit_cloudsync_stop',
                            nonce: mediaToolkit.nonce
                        },
                        success: (response) => {
                            this.state.isRunning = false;
                            this.state.isPaused = false;
                            this.updateControlsState();
                            this.log('Sync stopped', 'warning');
                        }
                    });
                }
            );
        },

        updateControlsState: function() {
            const isRunning = this.state.isRunning;
            const isPaused = this.state.isPaused;
            
            $('#btn-start-sync').prop('disabled', isRunning);
            $('#btn-pause-sync').prop('disabled', !isRunning || isPaused);
            $('#btn-resume-sync').prop('disabled', !isPaused);
            $('#btn-stop-sync').prop('disabled', !isRunning);
            
            // Show/hide status section
            $('#sync-status').toggle(isRunning || isPaused);
            
            // Update status text
            if (isRunning && !isPaused) {
                $('#status-text').text('Running');
            } else if (isPaused) {
                $('#status-text').text('Paused');
            } else {
                $('#status-text').text('Idle');
            }
        },

        updateStateUI: function(state) {
            if (!state) return;
            
            $('#processed-count').text(state.processed || 0);
            $('#total-count').text(state.total_files || 0);
            
            if (state.failed > 0) {
                $('#failed-badge').removeClass('hidden');
                $('#failed-count').text(state.failed);
            } else {
                $('#failed-badge').addClass('hidden');
            }
        },

        deepAnalyze: function() {
            this.log('Starting deep analysis (scanning cloud storage)...', 'info');
            this.refreshStatus(true);
        },

        viewDiscrepancies: function() {
            $('#discrepancies-modal').show();
            
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'media_toolkit_cloudsync_get_discrepancies',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderDiscrepancies(response.data);
                    } else {
                        $('#discrepancies-content').html(
                            '<div class="text-red-600">Error: ' + (response.data?.message || 'Unknown error') + '</div>'
                        );
                    }
                },
                error: () => {
                    $('#discrepancies-content').html(
                        '<div class="text-red-600">Failed to load discrepancies</div>'
                    );
                }
            });
        },

        renderDiscrepancies: function(data) {
            let html = '<div class="space-y-6">';
            
            // Summary
            html += '<div class="p-4 bg-gray-50 rounded-lg">';
            html += '<h4 class="font-semibold text-gray-900 mb-2">Summary</h4>';
            html += '<div class="grid grid-cols-3 gap-4 text-sm">';
            html += '<div><span class="text-gray-500">Cloud files:</span> ' + data.summary.cloud_files_scanned + '</div>';
            html += '<div><span class="text-gray-500">WP attachments:</span> ' + data.summary.wp_attachments + '</div>';
            html += '<div><span class="text-gray-500">Matched:</span> ' + data.summary.matched + '</div>';
            html += '</div></div>';
            
            // Not on cloud
            if (data.not_on_cloud_total > 0) {
                html += '<div>';
                html += '<h4 class="font-semibold text-red-600 mb-2">';
                html += '<span class="dashicons dashicons-warning mr-1"></span>';
                html += 'Marked as migrated but not on cloud (' + data.not_on_cloud_total + ')</h4>';
                html += '<div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">';
                html += '<table class="w-full text-sm">';
                html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">File</th><th class="px-3 py-2 text-left">Local</th></tr></thead>';
                html += '<tbody>';
                data.not_on_cloud.forEach(item => {
                    html += '<tr class="border-t border-gray-100">';
                    html += '<td class="px-3 py-2">#' + item.id + '</td>';
                    html += '<td class="px-3 py-2 truncate max-w-xs" title="' + item.file + '">' + item.file + '</td>';
                    html += '<td class="px-3 py-2">' + (item.local_exists ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
            }
            
            // Not marked
            if (data.not_marked_total > 0) {
                html += '<div>';
                html += '<h4 class="font-semibold text-yellow-600 mb-2">';
                html += '<span class="dashicons dashicons-info mr-1"></span>';
                html += 'On cloud but not marked in WordPress (' + data.not_marked_total + ')</h4>';
                html += '<div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">';
                html += '<table class="w-full text-sm">';
                html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">File</th></tr></thead>';
                html += '<tbody>';
                data.not_marked.forEach(item => {
                    html += '<tr class="border-t border-gray-100">';
                    html += '<td class="px-3 py-2">#' + item.id + '</td>';
                    html += '<td class="px-3 py-2 truncate max-w-xs" title="' + item.file + '">' + item.file + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
            }
            
            // Orphans
            if (data.orphans_total > 0) {
                html += '<div>';
                html += '<h4 class="font-semibold text-gray-600 mb-2">';
                html += '<span class="dashicons dashicons-trash mr-1"></span>';
                html += 'Orphan files on cloud (' + data.orphans_total + ')</h4>';
                html += '<div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg">';
                html += '<table class="w-full text-sm">';
                html += '<thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">File</th><th class="px-3 py-2 text-left">Size</th></tr></thead>';
                html += '<tbody>';
                data.orphans.forEach(item => {
                    html += '<tr class="border-t border-gray-100">';
                    html += '<td class="px-3 py-2 truncate max-w-xs" title="' + item.file + '">' + item.file + '</td>';
                    html += '<td class="px-3 py-2">' + item.size + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
            }
            
            if (data.not_on_cloud_total === 0 && data.not_marked_total === 0 && data.orphans_total === 0) {
                html += '<div class="text-center py-8 text-green-600">';
                html += '<span class="dashicons dashicons-yes-alt text-4xl mb-2"></span>';
                html += '<p class="font-semibold">No discrepancies found!</p>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $('#discrepancies-content').html(html);
        },

        clearMetadata: function() {
            this.showConfirmModal(
                'Clear All Metadata',
                'This will remove all migration metadata from WordPress. Files on cloud storage will NOT be deleted. This action cannot be undone.',
                () => {
                    $.ajax({
                        url: mediaToolkit.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'media_toolkit_cloudsync_clear_metadata',
                            nonce: mediaToolkit.nonce
                        },
                        success: (response) => {
                            if (response.success) {
                                this.log('Cleared metadata from ' + response.data.deleted + ' records', 'success');
                                this.refreshStatus();
                            } else {
                                this.log('Error: ' + (response.data?.message || 'Unknown error'), 'error');
                            }
                        }
                    });
                }
            );
        },

        executeAction: function(action) {
            switch (action) {
                case 'integrity_fix':
                    $('#sync-mode').val('integrity');
                    this.startSync();
                    break;
                case 'sync':
                    $('#sync-mode').val('sync');
                    this.startSync();
                    break;
                case 'cleanup_orphans':
                    this.log('Orphan cleanup not yet implemented', 'warning');
                    break;
            }
        },

        handleRemoveLocalChange: function(e) {
            if (e.target.checked) {
                e.target.checked = false;
                $('#remove-local-modal').show();
            }
        },

        confirmRemoveLocal: function() {
            $('#remove-local').prop('checked', true);
            $('#remove-local-modal').hide();
            $('#accept-risk').prop('checked', false);
            $('#btn-confirm-remove-local').prop('disabled', true);
        },

        cancelRemoveLocal: function() {
            $('#remove-local').prop('checked', false);
            $('#remove-local-modal').hide();
            $('#accept-risk').prop('checked', false);
            $('#btn-confirm-remove-local').prop('disabled', true);
        },

        showConfirmModal: function(title, message, onConfirm) {
            $('#confirm-title').text(title);
            $('#confirm-message').text(message);
            $('#confirm-modal').show();
            
            $('#btn-confirm-yes').off('click').on('click', () => {
                $('#confirm-modal').hide();
                onConfirm();
            });
        },

        log: function(message, type = 'info') {
            const $log = $('#sync-log');
            const timestamp = new Date().toLocaleTimeString();
            
            let icon = '';
            let colorClass = 'mt-terminal-muted';
            
            switch (type) {
                case 'success':
                    icon = '✓';
                    colorClass = 'mt-terminal-success';
                    break;
                case 'error':
                    icon = '✗';
                    colorClass = 'mt-terminal-error';
                    break;
                case 'warning':
                    icon = '⚠️';
                    colorClass = 'mt-terminal-warning';
                    break;
                default:
                    icon = '→';
            }
            
            const $line = $('<div class="mt-terminal-line">' +
                '<span class="mt-terminal-timestamp">[' + timestamp + ']</span> ' +
                '<span class="mt-terminal-text ' + colorClass + '">' + icon + ' ' + message + '</span>' +
                '</div>');
            
            $log.append($line);
            $log.scrollTop($log[0].scrollHeight);
        },

        // ========== Cache Headers Sync ==========
        
        startCacheSync: function() {
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

            // Update UI
            $('#btn-start-cache-sync').prop('disabled', true).hide();
            $('#btn-cancel-cache-sync').removeClass('hidden').show();
            $('#cache-sync-status').removeClass('hidden').show();
            $('#cache-status-text').text('Counting files...');
            $('#cache-progress-bar').css('width', '0%');
            $('#cache-progress-percentage').text('0%');
            this.addCacheSyncLog('Starting cache headers update...', 'info');

            // First, count total files
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_count_storage_files',
                    nonce: mediaToolkit.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.cacheSyncState.totalFiles = response.data.total_files;
                        $('#cache-total-files').text(response.data.total_files.toLocaleString());
                        $('#cache-total-count').text(response.data.total_files.toLocaleString());
                        this.addCacheSyncLog('Found ' + response.data.total_files.toLocaleString() + ' files to process', 'success');
                        this.processCacheSyncBatch();
                    } else {
                        this.failCacheSync(response.data?.message || 'Failed to count files');
                    }
                },
                error: () => {
                    this.failCacheSync('Connection error while counting files');
                }
            });
        },

        cancelCacheSync: function() {
            this.cacheSyncState.isCancelled = true;
            this.cacheSyncState.isRunning = false;

            $('#btn-cancel-cache-sync').addClass('hidden').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
            $('#cache-status-text').text('Cancelled');
            this.addCacheSyncLog('Cancelled. Processed ' + this.cacheSyncState.totalProcessed + ' files.', 'warning');
        },

        processCacheSyncBatch: function() {
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
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        const previousProcessed = state.totalProcessed;

                        // Update state
                        state.totalProcessed += data.processed;
                        state.totalSuccess += data.success;
                        state.totalFailed += data.failed;
                        state.continuationToken = data.continuation_token;

                        // Calculate progress
                        const percentage = state.totalFiles > 0
                            ? Math.round((state.totalProcessed / state.totalFiles) * 100)
                            : 0;

                        // Update UI
                        $('#cache-processed-files').text(state.totalSuccess.toLocaleString());
                        $('#cache-failed-files').text(state.totalFailed.toLocaleString());
                        $('#cache-current-count').text(state.totalProcessed.toLocaleString());
                        $('#cache-progress-bar').css('width', percentage + '%');
                        $('#cache-progress-percentage').text(percentage + '%');

                        // Log progress every 100 files
                        const logInterval = 100;
                        const previousMilestone = Math.floor(previousProcessed / logInterval);
                        const currentMilestone = Math.floor(state.totalProcessed / logInterval);
                        if (currentMilestone > previousMilestone) {
                            const failedText = state.totalFailed > 0 ? ', ' + state.totalFailed + ' failed' : '';
                            this.addCacheSyncLog('Processed ' + state.totalProcessed.toLocaleString() + ' / ' + state.totalFiles.toLocaleString() + ' files (' + percentage + '%' + failedText + ')', 'info');
                        }

                        if (data.has_more) {
                            setTimeout(() => this.processCacheSyncBatch(), 100);
                        } else {
                            this.completeCacheSync();
                        }
                    } else {
                        this.failCacheSync(response.data?.message || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    this.failCacheSync('Connection error: ' + error);
                }
            });
        },

        completeCacheSync: function() {
            const state = this.cacheSyncState;
            state.isRunning = false;

            $('#cache-progress-bar').css('width', '100%');
            $('#cache-progress-percentage').text('100%');
            $('#cache-status-text').html('<span class="text-green-600">Complete!</span>');

            this.addCacheSyncLog('✓ Complete! Updated ' + state.totalSuccess.toLocaleString() + ' files' +
                (state.totalFailed > 0 ? ' (' + state.totalFailed + ' failed)' : ''), 'success');

            $('#btn-cancel-cache-sync').addClass('hidden').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
        },

        failCacheSync: function(message) {
            const state = this.cacheSyncState;
            state.isRunning = false;

            $('#cache-status-text').html('<span class="text-red-600">Error</span>');
            this.addCacheSyncLog('✗ Error: ' + message, 'error');

            if (state.totalProcessed > 0) {
                this.addCacheSyncLog('Processed ' + state.totalProcessed + ' files before error.', 'warning');
            }

            $('#btn-cancel-cache-sync').addClass('hidden').hide();
            $('#btn-start-cache-sync').prop('disabled', false).show();
        },

        addCacheSyncLog: function(message, type = 'info') {
            const $log = $('#cache-sync-log');
            const timestamp = new Date().toLocaleTimeString();

            // Remove placeholder if exists
            $log.find('.mt-terminal-muted').first().parent().remove();

            const typeClass = {
                'info': '',
                'success': 'mt-terminal-success',
                'warning': 'mt-terminal-warning',
                'error': 'mt-terminal-error'
            }[type] || '';

            $log.append(
                '<div class="mt-terminal-line">' +
                '<span class="mt-terminal-prompt">$</span> ' +
                '<span class="mt-terminal-text ' + typeClass + '">[' + timestamp + '] ' + message + '</span>' +
                '</div>'
            );

            $log.scrollTop($log[0].scrollHeight);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        CloudSync.init();
    });

})(jQuery);

