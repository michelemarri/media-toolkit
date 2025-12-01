/**
 * Media Toolkit - Migration JavaScript
 * Uses the BatchProcessor component for batch operations
 */

(function($) {
    'use strict';

    // Initialize migration processor when document is ready
    $(document).ready(function() {
        // Only initialize if we're on the migration page
        if (!$('#btn-start-migration').length) {
            return;
        }

        const migration = new BatchProcessor({
            name: 'migration',
            batchInterval: 2000,
            actions: {
                start: 'media_toolkit_start_migration',
                process: 'media_toolkit_process_batch',
                pause: 'media_toolkit_pause_migration',
                resume: 'media_toolkit_resume_migration',
                stop: 'media_toolkit_stop_migration',
                status: 'media_toolkit_get_status',
                retry: 'media_toolkit_retry_failed'
            },
            selectors: {
                startBtn: '#btn-start-migration',
                pauseBtn: '#btn-pause-migration',
                resumeBtn: '#btn-resume-migration',
                stopBtn: '#btn-stop-migration',
                retryBtn: '#btn-retry-failed',
                progressBar: '#migration-progress',
                progressText: '#progress-percentage',
                statusPanel: '#migration-status',
                logContainer: '#migration-log',
                modal: '#confirm-modal'
            },
            confirmStopMessage: 'Are you sure you want to stop the migration? Progress will be saved and you can resume later.',
            
            // Get start options from form
            getStartOptions: function() {
                const batchSize = parseInt($('#batch-size').val());
                const mode = $('#migration-mode').val();
                const removeLocal = $('#remove-local').is(':checked');
                
                // If removing local files, require confirmation
                if (removeLocal) {
                    return {
                        batch_size: batchSize,
                        remove_local: 'true',
                        async: mode === 'async' ? 'true' : 'false',
                        _needsConfirmation: true,
                        _confirmTitle: 'Delete Local Files?',
                        _confirmMessage: 'You have chosen to delete local files after migration. This action cannot be undone. Are you sure you want to continue?'
                    };
                }
                
                return {
                    batch_size: batchSize,
                    remove_local: 'false',
                    async: mode === 'async' ? 'true' : 'false'
                };
            },
            
            // Update stats when status updates
            onStatusUpdate: function(state) {
                // Update status panel counts
                $('#processed-count').text(state.processed || 0);
                $('#total-count').text(state.total_files || 0);
                $('#failed-count').text(state.failed || 0);
                
                // Show/hide failed badge
                if (state.failed > 0) {
                    $('#failed-badge').show();
                } else {
                    $('#failed-badge').hide();
                }
                
                // Update status text
                if (state.status) {
                    $('#status-text').text(state.status.charAt(0).toUpperCase() + state.status.slice(1));
                }
            },
            
            // Custom stats update
            onBatchComplete: function(result) {
                if (result.stats) {
                    updateMigrationStats(result.stats);
                }
            },
            
            // On complete, refresh stats
            onComplete: function(state) {
                // Refresh final stats
                $.ajax({
                    url: mediaToolkit.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'media_toolkit_get_status',
                        nonce: mediaToolkit.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.stats) {
                            updateMigrationStats(response.data.stats);
                        }
                    }
                });
            }
        });

        // Update stats cards
        function updateMigrationStats(stats) {
            $('#stat-total').text(stats.total_attachments || 0);
            $('#stat-migrated').text(stats.migrated_attachments || 0);
            $('#stat-pending').text(stats.pending_attachments || 0);
            $('#stat-size').text(stats.pending_size_formatted || '0 B');
            
            // Update progress bar
            const progress = stats.progress_percentage || 0;
            $('#migration-progress').css('width', progress + '%');
            $('#progress-percentage').text(progress + '%');
        }

        // Store reference globally if needed
        window.MediaToolkitMigration = migration;
    });

})(jQuery);
