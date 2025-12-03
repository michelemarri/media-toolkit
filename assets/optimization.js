/**
 * Media Toolkit - Optimization JavaScript
 * Uses the BatchProcessor component for batch operations
 */

(function($) {
    'use strict';

    // Initialize optimization processor when document is ready
    $(document).ready(function() {
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
            getStartOptions: function() {
                return {
                    batch_size: parseInt($('#batch-size').val()) || 25,
                    jpeg_quality: parseInt($('#jpeg-quality').val()) || 82,
                    png_compression: parseInt($('#png-compression').val()) || 6,
                    strip_metadata: $('#strip-metadata').is(':checked') ? 'true' : 'false',
                    max_file_size_mb: parseInt($('#max-file-size').val()) || 10
                };
            },
            
            // Reset batch stats when starting
            onStart: function() {
                window.batchTotalSaved = 0;
                $('#batch-bytes-saved').text('0 B');
                $('#batch-progress-bar').css('width', '0%');
                $('#batch-progress-percentage').text('0%');
            },
            
            // Update stats when status updates
            onStatusUpdate: function(state) {
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
            
            // Custom stats update
            onBatchComplete: function(result) {
                if (result.stats) {
                    updateOptimizationStats(result.stats);
                }
                
                // Update batch bytes saved from current session
                if (result.batch_bytes_saved !== undefined) {
                    window.batchTotalSaved = (window.batchTotalSaved || 0) + result.batch_bytes_saved;
                    $('#batch-bytes-saved').text(formatBytes(window.batchTotalSaved));
                }
            },
            
            // On complete, refresh stats
            onComplete: function(state) {
                // Refresh final stats
                $.ajax({
                    url: mediaToolkit.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'media_toolkit_optimization_get_status',
                        nonce: mediaToolkit.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.stats) {
                            updateOptimizationStats(response.data.stats);
                        }
                    }
                });
            }
        });

        // Format bytes to human readable
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
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

        // Rebuild Stats button handler
        $('#btn-rebuild-stats').on('click', function() {
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
                success: function(response) {
                    if (response.success && response.data.stats) {
                        updateOptimizationStats(response.data.stats);
                        $btn.html('<span class="dashicons dashicons-yes text-sm"></span> ' + 'Done!');
                        setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                    } else {
                        $btn.html('<span class="dashicons dashicons-warning text-sm"></span> ' + 'Error');
                        setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                    }
                },
                error: function() {
                    $btn.html('<span class="dashicons dashicons-warning text-sm"></span> ' + 'Error');
                    setTimeout(() => $btn.html(originalText).prop('disabled', false), 2000);
                }
            });
        });
    });

})(jQuery);

