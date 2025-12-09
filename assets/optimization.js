/**
 * Media Toolkit - Optimization JavaScript
 * Uses the BatchProcessor component for batch operations
 */

(function ($) {
    'use strict';

    // Initialize batch total saved globally to prevent NaN on page reload
    window.batchTotalSaved = 0;

    // Initialize optimization processor when document is ready
    $(document).ready(function () {
        // Only initialize if we're on the optimize page (Optimize tab)
        if (!$('#btn-start-optimization').length) {
            return;
        }

        const optimization = new BatchProcessor({
            name: 'optimization',
            batchInterval: 2000,
            actions: {
                start: 'media_toolkit_optimization_start',
                process: 'media_toolkit_optimization_process_batch',
                pause: 'media_toolkit_optimization_pause',
                resume: 'media_toolkit_optimization_resume',
                stop: 'media_toolkit_optimization_stop',
                status: 'media_toolkit_optimization_get_status'
            },
            selectors: {
                startBtn: '#btn-start-optimization',
                pauseBtn: '#btn-pause-optimization',
                resumeBtn: '#btn-resume-optimization',
                stopBtn: '#btn-stop-optimization',
                progressBar: '#optimization-progress',
                progressText: '#progress-percentage',
                statusPanel: '#optimization-status',
                logContainer: '#optimization-log',
                modal: '#confirm-modal'
            },
            confirmStopMessage: 'Are you sure you want to stop the optimization? Progress will be saved and you can resume later.',

            // Get start options from form
            getStartOptions: function () {
                return {
                    batch_size: parseInt($('#batch-size').val()) || 25,
                    jpeg_quality: parseInt($('#jpeg-quality').val()) || 82,
                    png_compression: parseInt($('#png-compression').val()) || 6,
                    strip_metadata: $('#strip-metadata').is(':checked') ? 'true' : 'false',
                    max_file_size_mb: parseInt($('#max-file-size').val()) || 10
                };
            },

            // Reset batch stats when starting
            onStart: function () {
                window.batchTotalSaved = 0;
                $('#batch-bytes-saved').text('0 B');
                $('#batch-progress-bar').css('width', '0%');
                $('#batch-progress-percentage').text('0%');
            },

            // Update stats when status updates
            onStatusUpdate: function (state) {
                // Update status panel counts
                $('#processed-count').text(state.processed || 0);
                $('#total-count').text(state.total_files || 0);
                $('#failed-count').text(state.failed || 0);

                // Update batch progress bar
                const total = state.total_files || 0;
                const processed = state.processed || 0;
                const batchProgress = total > 0 ? Math.round((processed / total) * 100) : 0;
                $('#batch-progress-bar').css('width', batchProgress + '%');
                $('#batch-progress-percentage').text(batchProgress + '%');

                // Show/hide failed badge
                if (state.failed > 0) {
                    $('#failed-badge').removeClass('hidden').show();
                } else {
                    $('#failed-badge').addClass('hidden').hide();
                }

                // Update status text
                if (state.status) {
                    $('#status-text').text(state.status.charAt(0).toUpperCase() + state.status.slice(1));
                }
            },

            // Custom stats update and detailed logging
            onBatchComplete: function (result) {
                if (result.stats) {
                    updateOptimizationStats(result.stats);
                }

                // Update batch bytes saved from current session
                if (result.batch_bytes_saved !== undefined) {
                    window.batchTotalSaved = (window.batchTotalSaved || 0) + result.batch_bytes_saved;
                    $('#batch-bytes-saved').text(formatBytes(window.batchTotalSaved));
                }

                // Show detailed results for each image in the log
                if (result.batch_results && result.batch_results.length > 0) {
                    result.batch_results.forEach(function (item) {
                        logImageResult(item);
                    });

                    // Also refresh the failed images list if there were failures
                    if (result.batch_failed > 0) {
                        loadFailedImages();
                    }
                }
            },

            // On complete, refresh stats
            onComplete: function (state) {
                // Refresh final stats
                $.ajax({
                    url: mediaToolkit.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'media_toolkit_optimization_get_status',
                        nonce: mediaToolkit.nonce
                    },
                    success: function (response) {
                        if (response.success && response.data.stats) {
                            updateOptimizationStats(response.data.stats);
                        }
                    }
                });
            }
        });

        // Format bytes to human readable
        function formatBytes(bytes, decimals = 2) {
            // Handle invalid values
            if (bytes === undefined || bytes === null || isNaN(bytes) || bytes < 0) {
                return '0 B';
            }
            if (bytes === 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            // Ensure i is within valid range
            const safeIndex = Math.min(Math.max(i, 0), sizes.length - 1);
            return parseFloat((bytes / Math.pow(k, safeIndex)).toFixed(dm)) + ' ' + sizes[safeIndex];
        }

        // Update stats cards
        function updateOptimizationStats(stats) {
            $('#stat-total_images').text(stats.total_images || 0);
            $('#stat-optimized_images').text(stats.optimized_images || 0);
            $('#stat-pending_images').text(stats.pending_images || 0);
            $('#stat-total_saved_formatted').text(stats.total_saved_formatted || '0 B');

            // Update average savings percentage if available
            if (stats.average_savings_percent !== undefined) {
                $('#stat-average_savings_percent').text(stats.average_savings_percent + '%');
            }

            // Update progress bar
            const progress = stats.progress_percentage || 0;
            $('#optimization-progress').css('width', progress + '%');
            $('#progress-percentage').text(progress + '%');
        }

        // Store reference globally if needed
        window.MediaToolkitOptimization = optimization;

        // ==================== Failed Images Section ====================

        let failedImagesPage = 1;
        const failedImagesPerPage = 20;

        // Load failed images on page load
        loadFailedImages();

        // Refresh failed images button
        $('#btn-refresh-failed').on('click', function () {
            failedImagesPage = 1;
            loadFailedImages();
        });

        // Reset failed images button
        $('#btn-reset-failed').on('click', function () {
            if (!confirm('Are you sure you want to retry all failed images? They will be set back to pending status.')) {
                return;
            }

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update animate-spin text-sm"></span> Resetting...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_reset_failed_optimization',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $btn.html('<span class="dashicons dashicons-yes text-sm"></span> Done!');
                        setTimeout(() => {
                            $btn.html(originalHtml).prop('disabled', true);
                            failedImagesPage = 1;
                            loadFailedImages();
                            // Refresh stats
                            $('#btn-rebuild-stats').trigger('click');
                        }, 1500);
                    } else {
                        $btn.html('<span class="dashicons dashicons-warning text-sm"></span> Error');
                        setTimeout(() => $btn.html(originalHtml).prop('disabled', false), 2000);
                    }
                },
                error: function () {
                    $btn.html('<span class="dashicons dashicons-warning text-sm"></span> Error');
                    setTimeout(() => $btn.html(originalHtml).prop('disabled', false), 2000);
                }
            });
        });

        // Pagination buttons
        $('#btn-failed-prev').on('click', function () {
            if (failedImagesPage > 1) {
                failedImagesPage--;
                loadFailedImages();
            }
        });

        $('#btn-failed-next').on('click', function () {
            failedImagesPage++;
            loadFailedImages();
        });

        function loadFailedImages() {
            const $list = $('#failed-images-list');
            const $loading = $('#failed-images-loading');
            const $empty = $('#failed-images-empty');
            const $pagination = $('#failed-images-pagination');
            const $count = $('#failed-images-count');
            const $resetBtn = $('#btn-reset-failed');

            $loading.removeClass('hidden').show();
            $empty.addClass('hidden').hide();
            $list.find('.failed-image-item').remove();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_optimization_records',
                    nonce: mediaToolkit.nonce,
                    status: 'failed',
                    page: failedImagesPage,
                    per_page: failedImagesPerPage
                },
                success: function (response) {
                    $loading.addClass('hidden').hide();

                    if (response.success) {
                        const data = response.data;
                        const records = data.records || [];
                        const total = data.total || 0;

                        $count.text(total);

                        if (records.length === 0) {
                            $empty.removeClass('hidden').show();
                            $pagination.addClass('hidden').hide();
                            $resetBtn.prop('disabled', true);
                            return;
                        }

                        $resetBtn.prop('disabled', false);

                        // Render failed images
                        records.forEach(function (record) {
                            const editUrl = `/wp-admin/post.php?post=${record.attachment_id}&action=edit`;
                            const $item = $(`
                                <div class="failed-image-item flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-100 text-red-600 flex-shrink-0">
                                            <span class="dashicons dashicons-format-image"></span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900 truncate">${escapeHtml(record.file_name)}</span>
                                                <span class="text-xs text-gray-500">(ID: ${record.attachment_id})</span>
                                            </div>
                                            <p class="text-xs text-red-600 mt-0.5 truncate" title="${escapeHtml(record.error_message || 'Unknown error')}">
                                                <span class="dashicons dashicons-warning text-red-500" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                ${escapeHtml(record.error_message || 'Unknown error')}
                                            </p>
                                        </div>
                                    </div>
                                    <a href="${editUrl}" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all flex-shrink-0 ml-3">
                                        <span class="dashicons dashicons-edit text-sm"></span>
                                        Edit
                                    </a>
                                </div>
                            `);
                            $list.append($item);
                        });

                        // Update pagination
                        const totalPages = data.total_pages || 1;
                        if (totalPages > 1) {
                            $pagination.removeClass('hidden').show();
                            $('#failed-images-info').text(`Page ${failedImagesPage} of ${totalPages} (${total} failed images)`);
                            $('#btn-failed-prev').prop('disabled', failedImagesPage <= 1);
                            $('#btn-failed-next').prop('disabled', failedImagesPage >= totalPages);
                        } else {
                            $pagination.addClass('hidden').hide();
                        }
                    } else {
                        $empty.removeClass('hidden').show().find('p').text('Error loading failed images');
                    }
                },
                error: function () {
                    $loading.addClass('hidden').hide();
                    $empty.removeClass('hidden').show().find('p').text('Error loading failed images');
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ==================== End Failed Images Section ====================

        // ==================== Detailed Image Log ====================

        /**
         * Log detailed result for a single image in the optimization log
         */
        function logImageResult(item) {
            const $log = $('#optimization-log');
            const timestamp = new Date().toLocaleTimeString();

            // Remove placeholder if present
            $log.find('.mt-terminal-line').first().each(function () {
                if ($(this).find('.mt-terminal-muted').text().includes('will appear here')) {
                    $(this).remove();
                }
            });

            let html = '';

            if (item.status === 'success') {
                // Success: show detailed stats
                html = `
                    <div class="mt-terminal-line mt-terminal-image-result">
                        <span class="mt-terminal-prompt">$</span>
                        <span class="mt-terminal-text">
                            <span class="mt-terminal-success">[${timestamp}]</span>
                            <span class="text-white font-medium">${escapeHtml(item.file_name)}</span>
                            <span class="text-gray-400">(ID: ${item.id})</span>
                            <span class="mt-terminal-success">✓ Optimized</span>
                        </span>
                    </div>
                    <div class="mt-terminal-line pl-6">
                        <span class="text-gray-500">├─</span>
                        <span class="text-gray-400">Size:</span>
                        <span class="text-amber-400">${item.original_size_formatted}</span>
                        <span class="text-gray-500">→</span>
                        <span class="text-emerald-400">${item.optimized_size_formatted}</span>
                        <span class="text-gray-500">|</span>
                        <span class="text-emerald-400 font-medium">Saved ${item.bytes_saved_formatted} (${item.percent_saved}%)</span>
                        ${item.thumbnails_count > 0 ? `<span class="text-gray-500">|</span><span class="text-blue-400">${item.thumbnails_count} thumbnails</span>` : ''}
                    </div>
                    <div class="mt-terminal-line pl-6 mb-2">
                        <span class="text-gray-500">└─</span>
                        <a href="${item.edit_url}" target="_blank" class="text-cyan-400 hover:text-cyan-300 hover:underline">
                            View image →
                        </a>
                    </div>
                `;
            } else if (item.status === 'skipped') {
                // Skipped
                html = `
                    <div class="mt-terminal-line mt-terminal-image-result">
                        <span class="mt-terminal-prompt">$</span>
                        <span class="mt-terminal-text">
                            <span class="mt-terminal-muted">[${timestamp}]</span>
                            <span class="text-white">${escapeHtml(item.file_name)}</span>
                            <span class="text-gray-400">(ID: ${item.id})</span>
                            <span class="mt-terminal-warning">⊘ Skipped: ${escapeHtml(item.reason || 'Unknown reason')}</span>
                        </span>
                    </div>
                    <div class="mt-terminal-line pl-6 mb-2">
                        <span class="text-gray-500">└─</span>
                        <a href="${item.edit_url}" target="_blank" class="text-cyan-400 hover:text-cyan-300 hover:underline">
                            View image →
                        </a>
                    </div>
                `;
            } else if (item.status === 'failed') {
                // Failed: show error with link to check
                html = `
                    <div class="mt-terminal-line mt-terminal-image-result">
                        <span class="mt-terminal-prompt">$</span>
                        <span class="mt-terminal-text">
                            <span class="mt-terminal-error">[${timestamp}]</span>
                            <span class="text-white">${escapeHtml(item.file_name)}</span>
                            <span class="text-gray-400">(ID: ${item.id})</span>
                            <span class="mt-terminal-error">✗ Failed</span>
                        </span>
                    </div>
                    <div class="mt-terminal-line pl-6">
                        <span class="text-gray-500">├─</span>
                        <span class="mt-terminal-error">Error: ${escapeHtml(item.error || 'Unknown error')}</span>
                    </div>
                    <div class="mt-terminal-line pl-6 mb-2">
                        <span class="text-gray-500">└─</span>
                        <a href="${item.edit_url}" target="_blank" class="text-cyan-400 hover:text-cyan-300 hover:underline font-medium">
                            Check image →
                        </a>
                        <span class="text-gray-500 text-xs ml-2">(verify if file exists and is valid)</span>
                    </div>
                `;
            }

            $log.append(html);
            $log.scrollTop($log[0].scrollHeight);
        }

        // ==================== End Detailed Image Log ====================

        // Rebuild Stats button handler
        $('#btn-rebuild-stats').on('click', function () {
            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update animate-spin text-sm"></span> ' + 'Rebuilding...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_rebuild_optimization_stats',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success && response.data.stats) {
                        updateOptimizationStats(response.data.stats);
                        $btn.html('<span class="dashicons dashicons-yes text-sm"></span> ' + 'Done!');
                        setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                    } else {
                        $btn.html('<span class="dashicons dashicons-warning text-sm"></span> ' + 'Error');
                        setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                    }
                },
                error: function () {
                    $btn.html('<span class="dashicons dashicons-warning text-sm"></span> ' + 'Error');
                    setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                }
            });
        });
    });

})(jQuery);

