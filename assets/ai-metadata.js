/**
 * Media Toolkit - AI Metadata Generation JavaScript
 * 
 * Handles batch processing UI, cost estimation, and progress tracking
 */
(function ($) {
    'use strict';

    const AIMetadata = {
        state: {
            isRunning: false,
            isPaused: false,
            batchSize: 10,
            onlyEmpty: true,
            overwrite: false
        },

        init: function () {
            this.bindEvents();
            this.initializePage();
        },

        bindEvents: function () {
            // Batch controls
            $('#btn-start-ai-generation').on('click', this.startGeneration.bind(this));
            $('#btn-pause-ai-generation').on('click', this.pauseGeneration.bind(this));
            $('#btn-resume-ai-generation').on('click', this.resumeGeneration.bind(this));
            $('#btn-stop-ai-generation').on('click', this.stopGeneration.bind(this));

            // Settings
            $('#ai-batch-size').on('change', this.updateBatchSize.bind(this));
            $('#ai-only-empty').on('change', this.updateOnlyEmpty.bind(this));
            $('#ai-overwrite').on('change', this.updateOverwrite.bind(this));
            $('#btn-refresh-estimate').on('click', this.refreshEstimate.bind(this));

            // Modal
            $('.modal-close, #btn-confirm-no').on('click', this.closeModal.bind(this));
            $(document).on('click', '.mt-modal-overlay', function (e) {
                if ($(e.target).hasClass('mt-modal-overlay')) {
                    AIMetadata.closeModal();
                }
            });
        },

        initializePage: function () {
            // Load initial estimate
            this.refreshEstimate();
        },

        updateBatchSize: function () {
            this.state.batchSize = parseInt($('#ai-batch-size').val(), 10) || 10;
        },

        updateOnlyEmpty: function () {
            this.state.onlyEmpty = $('#ai-only-empty').is(':checked');
            this.refreshEstimate();
        },

        updateOverwrite: function () {
            this.state.overwrite = $('#ai-overwrite').is(':checked');
        },

        refreshEstimate: function () {
            const $btn = $('#btn-refresh-estimate');
            const $images = $('#estimate-images');
            const $cost = $('#estimate-cost');

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update animate-spin"></span> Refreshing...');

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_estimate_cost',
                    nonce: mediaToolkit.nonce,
                    only_empty: this.state.onlyEmpty ? 'true' : 'false'
                },
                success: function (response) {
                    if (response.success) {
                        const estimate = response.data.estimate;
                        $images.text(estimate.images_count ? estimate.images_count.toLocaleString() : '-');
                        $cost.text('$' + parseFloat(estimate.total).toFixed(2));
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        startGeneration: function () {
            const self = this;

            // Confirm start
            if (!confirm('This will start generating AI metadata. Estimated cost: $' + $('#estimate-cost').text().replace('$', '') + '. Continue?')) {
                return;
            }

            this.state.isRunning = true;
            this.state.isPaused = false;

            // Update UI
            this.updateButtonStates();
            $('#ai-generation-status').removeClass('hidden');
            this.addLog('Starting AI metadata generation...', 'info');

            // Call batch processor start
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_start',
                    nonce: mediaToolkit.nonce,
                    batch_size: this.state.batchSize,
                    only_empty_fields: this.state.onlyEmpty ? 'true' : 'false',
                    overwrite: this.state.overwrite ? 'true' : 'false'
                },
                success: function (response) {
                    if (response.success) {
                        self.updateProgress(response.data);
                        self.addLog('Batch started: ' + response.data.total_files + ' images to process', 'success');
                        self.processBatch();
                    } else {
                        self.addLog('Error: ' + (response.data?.message || 'Unknown error'), 'error');
                        self.stopGeneration();
                    }
                },
                error: function () {
                    self.addLog('Connection error', 'error');
                    self.stopGeneration();
                }
            });
        },

        processBatch: function () {
            const self = this;

            if (!this.state.isRunning || this.state.isPaused) {
                return;
            }

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_process_batch',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.updateProgress(response.data);

                        // Log batch results
                        if (response.data.batch_processed > 0) {
                            self.addLog('Processed ' + response.data.batch_processed + ' images (Total: ' + response.data.processed + '/' + response.data.total_files + ')', 'info');
                        }

                        // Check if complete
                        if (response.data.status === 'completed') {
                            self.addLog('✓ Generation complete! ' + response.data.total_success + ' images processed.', 'success');
                            self.state.isRunning = false;
                            self.updateButtonStates();
                            return;
                        }

                        // Continue processing
                        if (self.state.isRunning && !self.state.isPaused) {
                            setTimeout(function () {
                                self.processBatch();
                            }, 500); // Small delay between batches
                        }
                    } else {
                        self.addLog('Error: ' + (response.data?.message || 'Unknown error'), 'error');
                        
                        if (response.data?.can_continue) {
                            // Continue despite error
                            setTimeout(function () {
                                self.processBatch();
                            }, 2000);
                        } else {
                            self.stopGeneration();
                        }
                    }
                },
                error: function () {
                    self.addLog('Connection error. Retrying...', 'warning');
                    
                    // Retry after delay
                    setTimeout(function () {
                        if (self.state.isRunning && !self.state.isPaused) {
                            self.processBatch();
                        }
                    }, 5000);
                }
            });
        },

        pauseGeneration: function () {
            this.state.isPaused = true;
            this.addLog('Generation paused', 'warning');
            this.updateButtonStates();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_pause',
                    nonce: mediaToolkit.nonce
                }
            });
        },

        resumeGeneration: function () {
            this.state.isPaused = false;
            this.addLog('Generation resumed', 'info');
            this.updateButtonStates();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_resume',
                    nonce: mediaToolkit.nonce
                },
                success: () => {
                    this.processBatch();
                }
            });
        },

        stopGeneration: function () {
            const self = this;
            this.state.isRunning = false;
            this.state.isPaused = false;
            this.addLog('Generation stopped', 'warning');
            this.updateButtonStates();

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_stop',
                    nonce: mediaToolkit.nonce
                }
            });
        },

        updateProgress: function (data) {
            const total = data.total_files || 0;
            const processed = data.processed || 0;
            const failed = data.failed || 0;
            const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;

            $('#ai-progress-percentage').text(percentage + '%');
            $('#ai-progress-bar').css('width', percentage + '%');
            $('#ai-processed-count').text(processed);
            $('#ai-total-count').text(total);
            $('#ai-failed-count').text(failed);
            $('#ai-status-text').text(data.status || 'processing');

            if (failed > 0) {
                $('#ai-failed-badge').removeClass('hidden');
            }
        },

        updateButtonStates: function () {
            const $start = $('#btn-start-ai-generation');
            const $pause = $('#btn-pause-ai-generation');
            const $resume = $('#btn-resume-ai-generation');
            const $stop = $('#btn-stop-ai-generation');

            if (this.state.isRunning) {
                $start.prop('disabled', true);
                $stop.prop('disabled', false);

                if (this.state.isPaused) {
                    $pause.prop('disabled', true);
                    $resume.prop('disabled', false);
                } else {
                    $pause.prop('disabled', false);
                    $resume.prop('disabled', true);
                }
            } else {
                $start.prop('disabled', false);
                $pause.prop('disabled', true);
                $resume.prop('disabled', true);
                $stop.prop('disabled', true);
            }
        },

        addLog: function (message, type) {
            const $log = $('#ai-generation-log');
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
                    <span class="mt-terminal-text ${typeClass}">[${timestamp}] ${this.escapeHtml(message)}</span>
                </div>
            `);

            // Scroll to bottom
            $log.scrollTop($log[0].scrollHeight);
        },

        closeModal: function () {
            $('.mt-modal-overlay').hide();
        },

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
        AIMetadata.init();
    });

})(jQuery);

