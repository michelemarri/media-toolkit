/**
 * Media Toolkit - Reconciliation JavaScript
 * Uses the BatchProcessor component for batch operations
 */

(function ($) {
    'use strict';

    // Initialize reconciliation processor when document is ready
    $(document).ready(function () {
        // Only initialize if we're on the reconciliation tab
        if (!$('#btn-start-reconciliation').length) {
            return;
        }

        const reconciliation = new BatchProcessor({
            name: 'reconciliation',
            batchInterval: 2000,
            actions: {
                start: 'media_toolkit_reconciliation_start',
                process: 'media_toolkit_reconciliation_process_batch',
                pause: 'media_toolkit_reconciliation_pause',
                resume: 'media_toolkit_reconciliation_resume',
                stop: 'media_toolkit_reconciliation_stop',
                status: 'media_toolkit_reconciliation_get_status'
            },
            selectors: {
                startBtn: '#btn-start-reconciliation',
                pauseBtn: '#btn-pause-reconciliation',
                resumeBtn: '#btn-resume-reconciliation',
                stopBtn: '#btn-stop-reconciliation',
                progressBar: '#recon-progress-bar',
                progressText: '#recon-progress-percentage',
                statusPanel: '#recon-status',
                logContainer: '#recon-log',
                modal: '#confirm-modal'
            },
            confirmStopMessage: 'Are you sure you want to stop the reconciliation? Progress will be saved and you can resume later.',

            // Get start options from form
            getStartOptions: function () {
                const batchSize = parseInt($('#recon-batch-size').val()) || 50;

                return {
                    batch_size: batchSize,
                    mode: 'mark_found'
                };
            },

            // Update stats when status updates
            onStatusUpdate: function (state) {
                // Update status text
                if (state.status) {
                    $('#recon-status-text').text(state.status.charAt(0).toUpperCase() + state.status.slice(1));
                }

                // Update progress
                if (state.total_files > 0) {
                    const progress = Math.round((state.processed / state.total_files) * 100);
                    $('#recon-progress-bar').css('width', progress + '%');
                    $('#recon-progress-percentage').text(progress + '%');
                }

                // Show status panel when running
                if (state.status !== 'idle') {
                    $('#recon-status').removeClass('hidden');
                }
            },

            // Custom stats update
            onBatchComplete: function (result) {
                if (result.stats) {
                    updateReconciliationStats(result.stats);
                }
            },

            // On complete, refresh stats
            onComplete: function (state) {
                // Refresh final stats
                $.ajax({
                    url: mediaToolkit.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'media_toolkit_reconciliation_get_status',
                        nonce: mediaToolkit.nonce
                    },
                    success: function (response) {
                        if (response.success && response.data.stats) {
                            updateReconciliationStats(response.data.stats);
                        }
                    }
                });
            }
        });

        // Update stats cards
        function updateReconciliationStats(stats) {
            $('#recon-wp-attachments').text(formatNumber(stats.total_attachments || 0));
            $('#recon-marked').text(formatNumber(stats.marked_migrated || 0));
            $('#recon-s3-files').text(formatNumber(stats.s3_original_files || 0));
            $('#recon-discrepancy').text(formatNumber(stats.discrepancy || 0));

            // Update progress bar
            const progress = stats.progress_percentage || 0;
            $('#recon-progress-bar').css('width', progress + '%');
            $('#recon-progress-percentage').text(progress + '%');
        }

        // Format number with thousands separator
        function formatNumber(num) {
            return num.toLocaleString();
        }

        // Scan S3 button
        $('#btn-scan-s3').on('click', function () {
            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update animate-spin"></span> Scanning...'
            );

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_reconciliation_scan_s3',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    $btn.prop('disabled', false).html(originalText);

                    if (response.success) {
                        const data = response.data;
                        $('#scan-results').removeClass('hidden');
                        $('#scan-s3-files').text(formatNumber(data.s3_original_files || 0));
                        $('#scan-wp-files').text(formatNumber(data.wp_attachments || 0));
                        $('#scan-matches').text(formatNumber(data.matches || 0));
                        $('#scan-not-found').text(formatNumber(data.not_found_on_s3 || 0));
                        $('#scan-would-mark').text(formatNumber(data.would_be_marked || 0));

                        // Log the scan results
                        reconciliation.log('S3 scan completed', 'success');
                        reconciliation.log(`Found ${formatNumber(data.s3_original_files)} files on S3`, 'info');
                        reconciliation.log(`${formatNumber(data.matches)} files match WordPress attachments`, 'info');
                    } else {
                        reconciliation.log('Failed to scan S3: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html(originalText);
                    reconciliation.log('Failed to scan S3', 'error');
                }
            });
        });

        // Clear metadata button
        $('#btn-clear-metadata').on('click', function () {
            if (!confirm('Are you sure you want to clear ALL migration metadata? This will reset all attachments to "not migrated" state. This action cannot be undone.')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_clear_metadata',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    $btn.prop('disabled', false);

                    if (response.success) {
                        reconciliation.log('All migration metadata cleared', 'warning');
                        // Refresh stats
                        location.reload();
                    } else {
                        reconciliation.log('Failed to clear metadata: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    reconciliation.log('Failed to clear metadata', 'error');
                }
            });
        });

        // Store reference globally if needed
        window.MediaToolkitReconciliation = reconciliation;

        // ==================== Discrepancies Modal ====================

        // View discrepancies button click
        $('#btn-view-discrepancies').on('click', function () {
            showDiscrepanciesModal();
        });

        // Also handle keyboard accessibility
        $('#btn-view-discrepancies').on('keypress', function (e) {
            if (e.which === 13 || e.which === 32) {
                e.preventDefault();
                showDiscrepanciesModal();
            }
        });

        function showDiscrepanciesModal() {
            const $modal = $('#discrepancies-modal');
            const $loading = $('#discrepancies-loading');
            const $content = $('#discrepancies-content');

            // Show modal with loading state
            $modal.show();
            $loading.show();
            $content.hide();

            // Fetch discrepancies
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_get_discrepancies',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    $loading.hide();
                    $content.show();

                    if (response.success) {
                        renderDiscrepancies(response.data);
                    } else {
                        $('#discrepancies-none').show().html(
                            '<span class="dashicons dashicons-warning text-red-500 text-4xl mb-3"></span>' +
                            '<p class="text-red-600">' + (response.data?.message || 'Error loading discrepancies') + '</p>'
                        );
                    }
                },
                error: function () {
                    $loading.hide();
                    $content.show();
                    $('#discrepancies-none').show().html(
                        '<span class="dashicons dashicons-warning text-red-500 text-4xl mb-3"></span>' +
                        '<p class="text-red-600">Error loading discrepancies</p>'
                    );
                }
            });
        }

        function renderDiscrepancies(data) {
            const $notOnS3 = $('#discrepancies-not-on-s3');
            const $notMarked = $('#discrepancies-not-marked');
            const $orphans = $('#discrepancies-orphans');
            const $none = $('#discrepancies-none');
            const $summary = $('#discrepancies-summary');

            // Reset visibility
            $notOnS3.hide();
            $notMarked.hide();
            $orphans.hide();
            $none.hide();

            // Update summary
            if (data.summary) {
                $summary.show();
                $('#summary-s3-scanned').text(data.summary.s3_files_scanned.toLocaleString());
                $('#summary-s3-cached').text(data.summary.s3_files_cached.toLocaleString());
                $('#summary-wp-attachments').text(data.summary.wp_attachments.toLocaleString());
                $('#summary-wp-marked').text(data.summary.wp_marked_migrated.toLocaleString());
                $('#summary-matched').text(data.summary.matched.toLocaleString());

                // Show warning if cache differs from scan
                if (data.summary.s3_files_scanned !== data.summary.s3_files_cached) {
                    $('#cache-mismatch-warning').show();
                } else {
                    $('#cache-mismatch-warning').hide();
                }
            }

            const hasNotOnS3 = data.not_on_s3 && data.not_on_s3.length > 0;
            const hasNotMarked = data.not_marked && data.not_marked.length > 0;
            const hasOrphans = data.orphans && data.orphans.length > 0;

            if (!hasNotOnS3 && !hasNotMarked && !hasOrphans) {
                $none.show();
                return;
            }

            // Render "marked but not on S3"
            if (hasNotOnS3) {
                $notOnS3.show();
                const countText = data.not_on_s3_total > data.not_on_s3_count
                    ? `${data.not_on_s3_count} of ${data.not_on_s3_total}`
                    : data.not_on_s3_count;
                $('#count-not-on-s3').text(countText);
                $('#list-not-on-s3').html(
                    data.not_on_s3.map(item => renderDiscrepancyItem(item, 'not-on-s3')).join('')
                );
            }

            // Render "on S3 but not marked"
            if (hasNotMarked) {
                $notMarked.show();
                const countText = data.not_marked_total > data.not_marked_count
                    ? `${data.not_marked_count} of ${data.not_marked_total}`
                    : data.not_marked_count;
                $('#count-not-marked').text(countText);
                $('#list-not-marked').html(
                    data.not_marked.map(item => renderDiscrepancyItem(item, 'not-marked')).join('')
                );
            }

            // Render "orphan files on S3"
            if (hasOrphans) {
                $orphans.show();
                const countText = data.orphans_total > data.orphans_count
                    ? `${data.orphans_count} of ${data.orphans_total}`
                    : data.orphans_count;
                $('#count-orphans').text(countText);
                $('#list-orphans').html(
                    data.orphans.map(item => renderOrphanItem(item)).join('')
                );
            }
        }

        function renderOrphanItem(item) {
            const openLink = item.url
                ? `<a href="${escapeHtml(item.url)}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors" title="Open file">
                     <span class="dashicons dashicons-external text-xs"></span>
                     Open
                   </a>`
                : '';

            return `
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-100 transition-colors">
                    <span class="dashicons dashicons-media-default text-purple-400"></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 font-mono truncate">${escapeHtml(item.file)}</div>
                        <div class="text-xs text-gray-500 truncate">${escapeHtml(item.s3_key)}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">${item.size}</span>
                        ${openLink}
                    </div>
                </div>
            `;
        }

        function renderDiscrepancyItem(item, type) {
            const icon = type === 'not-on-s3'
                ? '<span class="dashicons dashicons-dismiss text-red-500"></span>'
                : '<span class="dashicons dashicons-yes text-amber-500"></span>';

            const editLink = item.edit_url
                ? `<a href="${item.edit_url}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs">Edit</a>`
                : '';

            return `
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-100 transition-colors">
                    ${icon}
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">${escapeHtml(item.title || 'Untitled')}</div>
                        <div class="text-xs text-gray-500 font-mono truncate">${escapeHtml(item.file)}</div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span>ID: ${item.id}</span>
                        ${editLink}
                    </div>
                </div>
            `;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Close discrepancies modal
        $('#discrepancies-modal .modal-close').on('click', function () {
            $('#discrepancies-modal').hide();
        });

        // Close on overlay click
        $('#discrepancies-modal').on('click', function (e) {
            if ($(e.target).hasClass('mt-modal-overlay')) {
                $(this).hide();
            }
        });
    });

})(jQuery);

