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
            overwrite: false,
            backgroundMode: false
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
            $('#ai-background-mode').on('change', this.updateBackgroundMode.bind(this));
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
            const self = this;
            
            // Load initial estimate
            this.refreshEstimate();

            // Check if there's an active process and resume monitoring
            this.checkActiveProcess();
        },

        /**
         * Check if there's an active background process and resume monitoring
         */
        checkActiveProcess: function () {
            const self = this;

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_get_status',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const state = response.data.state || response.data;
                        
                        // If there's an active process, resume monitoring
                        if (state.status === 'running' || state.status === 'paused') {
                            self.state.isRunning = state.status === 'running';
                            self.state.isPaused = state.status === 'paused';
                            self.state.backgroundMode = !!(state.options && state.options.background_mode);
                            
                            self.updateProgress(state);
                            self.updateButtonStates();
                            $('#ai-generation-status').removeClass('hidden');
                            
                            if (self.state.backgroundMode) {
                                self.addLog('📡 Reconnected to background process', 'info');
                            } else {
                                self.addLog('📡 Reconnected to active process', 'info');
                            }
                            
                            // Start polling or processing based on mode
                            if (state.status === 'running') {
                                if (self.state.backgroundMode) {
                                    self.pollStatus();
                                } else {
                                    self.processBatch();
                                }
                            }
                        }
                    }
                }
            });
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

        updateBackgroundMode: function () {
            this.state.backgroundMode = $('#ai-background-mode').is(':checked');
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

            // Build confirmation message
            let confirmMsg = 'This will start generating AI metadata. Estimated cost: $' + $('#estimate-cost').text().replace('$', '') + '.';
            if (this.state.backgroundMode) {
                confirmMsg += '\n\n⚡ Background mode enabled: processing will continue even if you close the browser.';
            }
            confirmMsg += '\n\nContinue?';

            if (!confirm(confirmMsg)) {
                return;
            }

            this.state.isRunning = true;
            this.state.isPaused = false;

            // Update UI
            this.updateButtonStates();
            $('#ai-generation-status').removeClass('hidden');
            
            if (this.state.backgroundMode) {
                this.addLog('Starting AI metadata generation in BACKGROUND mode...', 'info');
            } else {
                this.addLog('Starting AI metadata generation...', 'info');
            }

            // Call batch processor start
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_start',
                    nonce: mediaToolkit.nonce,
                    batch_size: this.state.batchSize,
                    only_empty_fields: this.state.onlyEmpty ? 'true' : 'false',
                    overwrite: this.state.overwrite ? 'true' : 'false',
                    background_mode: this.state.backgroundMode ? 'true' : 'false'
                },
                success: function (response) {
                    if (response.success) {
                        const state = response.data.state || response.data;
                        self.updateProgress(state);
                        self.addLog('Batch started: ' + state.total_files + ' images to process', 'success');
                        
                        if (self.state.backgroundMode) {
                            // In background mode, just poll for status updates
                            self.addLog('🔄 Background processing started - you can close this page', 'success');
                            self.pollStatus();
                        } else {
                            // In foreground mode, process batches via AJAX
                            self.processBatch();
                        }
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
                        const data = response.data;
                        const state = data.state || data;
                        self.updateProgress(state);

                        // Log batch results
                        if (data.batch_processed > 0) {
                            self.addLog('Processed ' + data.batch_processed + ' images (Total: ' + state.processed + '/' + state.total_files + ')', 'info');
                        }

                        // Log batch errors
                        if (data.batch_failed > 0) {
                            self.addLog('⚠️ ' + data.batch_failed + ' failed in this batch', 'warning');
                        }
                        
                        // Log detailed errors
                        if (data.batch_errors && data.batch_errors.length > 0) {
                            data.batch_errors.forEach(function(err) {
                                self.addLog('❌ #' + err.item_id + ': ' + (err.error || 'Unknown error'), 'error');
                            });
                        }

                        // Check if complete
                        if (state.status === 'completed') {
                            let completeMsg = '✓ Generation complete! ' + state.processed + ' images processed.';
                            if (state.failed > 0) {
                                completeMsg += ' (' + state.failed + ' failed)';
                            }
                            self.addLog(completeMsg, 'success');
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

        /**
         * Poll for status updates in background mode
         * This doesn't trigger processing, just checks the current state
         */
        pollStatus: function () {
            const self = this;

            if (!this.state.isRunning || this.state.isPaused) {
                return;
            }

            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_ai_metadata_get_status',
                    nonce: mediaToolkit.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const state = response.data.state || response.data;
                        const prevProcessed = parseInt($('#ai-processed-count').text(), 10) || 0;
                        const prevFailed = parseInt($('#ai-failed-count').text(), 10) || 0;
                        
                        self.updateProgress(state);

                        // Log progress if changed
                        if (state.processed > prevProcessed) {
                            self.addLog('Background progress: ' + state.processed + '/' + state.total_files + ' images', 'info');
                        }
                        
                        // Log new failures
                        if (state.failed > prevFailed) {
                            const newFailed = state.failed - prevFailed;
                            self.addLog('⚠️ ' + newFailed + ' new failure(s) - Total failed: ' + state.failed, 'warning');
                        }

                        // Check if complete
                        if (state.status === 'completed') {
                            let completeMsg = '✓ Background generation complete! ' + state.processed + ' images processed.';
                            if (state.failed > 0) {
                                completeMsg += ' (' + state.failed + ' failed)';
                            }
                            self.addLog(completeMsg, 'success');
                            self.state.isRunning = false;
                            self.updateButtonStates();
                            return;
                        }

                        // Check if stopped/paused externally
                        if (state.status === 'idle' || state.status === 'paused') {
                            self.addLog('Processing ' + state.status, 'warning');
                            self.state.isRunning = state.status !== 'idle';
                            self.state.isPaused = state.status === 'paused';
                            self.updateButtonStates();
                            return;
                        }

                        // Continue polling (every 5 seconds for background mode)
                        if (self.state.isRunning && !self.state.isPaused) {
                            setTimeout(function () {
                                self.pollStatus();
                            }, 5000);
                        }
                    }
                },
                error: function () {
                    // Retry polling after delay
                    setTimeout(function () {
                        if (self.state.isRunning && !self.state.isPaused) {
                            self.pollStatus();
                        }
                    }, 10000);
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
            const self = this;
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
                success: function () {
                    if (self.state.backgroundMode) {
                        // In background mode, just poll for status
                        self.pollStatus();
                    } else {
                        // In foreground mode, process batches
                        self.processBatch();
                    }
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

