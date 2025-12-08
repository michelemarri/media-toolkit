/**
 * Media Toolkit - Reusable Batch Processor Component
 * 
 * A flexible component for handling batch operations with progress tracking,
 * pause/resume/cancel functionality, and real-time logging.
 * 
 * Usage:
 *   const processor = new BatchProcessor({
 *       name: 'migration',
 *       actions: {
 *           start: 'media_toolkit_start_migration',
 *           process: 'media_toolkit_process_batch',
 *           pause: 'media_toolkit_pause_migration',
 *           resume: 'media_toolkit_resume_migration',
 *           stop: 'media_toolkit_stop_migration',
 *           status: 'media_toolkit_get_status',
 *           retry: 'media_toolkit_retry_failed'
 *       },
 *       selectors: {
 *           startBtn: '#btn-start',
 *           pauseBtn: '#btn-pause',
 *           resumeBtn: '#btn-resume',
 *           stopBtn: '#btn-stop',
 *           retryBtn: '#btn-retry',
 *           progressBar: '#progress-bar',
 *           progressText: '#progress-text',
 *           statusPanel: '#status-panel',
 *           logContainer: '#log-container'
 *       },
 *       onStart: (options) => {},
 *       onBatchComplete: (result) => {},
 *       onComplete: (state) => {},
 *       onError: (error) => {}
 *   });
 */

(function ($, window) {
    'use strict';

    /**
     * BatchProcessor Class
     */
    class BatchProcessor {
        constructor(config) {
            this.config = Object.assign({
                name: 'batch',
                batchInterval: 2000,
                statusPollInterval: 5000,
                actions: {},
                selectors: {},
                confirmStop: true,
                confirmStopMessage: 'Are you sure you want to stop? Progress will be saved.',
                onStart: null,
                onBatchComplete: null,
                onComplete: null,
                onError: null,
                onStatusUpdate: null,
                getStartOptions: null
            }, config);

            this.isRunning = false;
            this.isPaused = false;
            this.intervalId = null;
            this.pendingCallback = null;
            this.stopAcknowledged = false; // Prevents duplicate "Stop acknowledged" messages

            this.init();
        }

        /**
         * Initialize the processor
         */
        init() {
            this.cacheElements();
            this.bindEvents();
            this.checkCurrentStatus();
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            const s = this.config.selectors;
            this.$startBtn = $(s.startBtn);
            this.$pauseBtn = $(s.pauseBtn);
            this.$resumeBtn = $(s.resumeBtn);
            this.$stopBtn = $(s.stopBtn);
            this.$retryBtn = $(s.retryBtn);
            this.$progressBar = $(s.progressBar);
            this.$progressText = $(s.progressText);
            this.$statusPanel = $(s.statusPanel);
            this.$logContainer = $(s.logContainer);
            this.$modal = $(s.modal || '#confirm-modal');
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            if (this.$startBtn.length) {
                this.$startBtn.on('click', () => this.start());
            }
            if (this.$pauseBtn.length) {
                this.$pauseBtn.on('click', () => this.pause());
            }
            if (this.$resumeBtn.length) {
                this.$resumeBtn.on('click', () => this.resume());
            }
            if (this.$stopBtn.length) {
                this.$stopBtn.on('click', () => this.stop());
            }
            if (this.$retryBtn.length) {
                this.$retryBtn.on('click', () => this.retry());
            }

            // Modal events
            $(document).on('click', '#btn-confirm-yes', () => this.confirmAction());
            $(document).on('click', '#btn-confirm-no, .modal-close', () => this.closeModal());
        }

        /**
         * Set buttons state based on processor status
         */
        setButtonsState(state) {
            const states = {
                idle: {
                    start: true, pause: false, resume: false, stop: false, retry: false
                },
                running: {
                    start: false, pause: true, resume: false, stop: true, retry: false
                },
                paused: {
                    start: false, pause: false, resume: true, stop: true, retry: true
                },
                completed: {
                    start: true, pause: false, resume: false, stop: false, retry: false
                }
            };

            const buttonState = states[state] || states.idle;

            this.$startBtn.prop('disabled', !buttonState.start);
            this.$pauseBtn.prop('disabled', !buttonState.pause);
            this.$resumeBtn.prop('disabled', !buttonState.resume);
            this.$stopBtn.prop('disabled', !buttonState.stop);

            if (this.$retryBtn.length) {
                this.$retryBtn.prop('disabled', !buttonState.retry);
            }

            // Show/hide status panel
            if (this.$statusPanel.length) {
                if (state === 'idle') {
                    this.$statusPanel.hide();
                } else {
                    this.$statusPanel.show();
                }
            }
        }

        /**
         * Check current status on page load
         */
        checkCurrentStatus() {
            if (!this.config.actions.status) return;

            this.ajax(this.config.actions.status, {}, (response) => {
                if (response.success && response.data) {
                    if (response.data.stats) {
                        this.updateStats(response.data.stats);
                    }

                    if (response.data.state) {
                        this.updateUI(response.data.state);

                        if (response.data.state.status === 'running') {
                            this.isRunning = true;
                            this.isPaused = false;
                            this.startBatchProcessing();
                        } else if (response.data.state.status === 'paused') {
                            this.isPaused = true;
                            this.isRunning = false;
                        }
                    }
                }
            });
        }

        /**
         * Start the batch process
         */
        start() {
            let options = {};

            if (typeof this.config.getStartOptions === 'function') {
                options = this.config.getStartOptions();

                // Check if we need confirmation
                if (options._needsConfirmation) {
                    this.showConfirmModal(
                        options._confirmTitle || 'Confirm Action',
                        options._confirmMessage || 'Are you sure you want to continue?',
                        () => this.doStart(options)
                    );
                    return;
                }
            }

            this.doStart(options);
        }

        /**
         * Execute start action
         */
        doStart(options) {
            // Clean up internal properties
            delete options._needsConfirmation;
            delete options._confirmTitle;
            delete options._confirmMessage;

            // Reset stop acknowledged flag for new session
            this.stopAcknowledged = false;
            
            this.setButtonsState('running');
            this.log('Starting...', 'info');

            if (typeof this.config.onStart === 'function') {
                this.config.onStart(options);
            }

            this.ajax(this.config.actions.start, options, (response) => {
                if (response.success) {
                    this.isRunning = true;
                    this.isPaused = false;
                    this.updateUI(response.data.state);
                    this.log('Started successfully', 'success');
                    this.startBatchProcessing();
                } else {
                    this.log('Failed to start: ' + (response.data?.message || 'Unknown error'), 'error');
                    this.setButtonsState('idle');

                    if (typeof this.config.onError === 'function') {
                        this.config.onError(response.data);
                    }
                }
            }, () => {
                this.log('Failed to start', 'error');
                this.setButtonsState('idle');
            });
        }

        /**
         * Start batch processing loop
         */
        startBatchProcessing() {
            this.stopInterval();

            const processBatch = () => {
                // Check before sending request
                if (!this.isRunning || this.isPaused) {
                    return;
                }

                this.ajax(this.config.actions.process, {}, (response) => {
                    // IMPORTANT: Check again after response - user may have clicked stop while request was in flight
                    if (!this.isRunning) {
                        // Only log once (multiple in-flight requests may return after stop)
                        if (!this.stopAcknowledged) {
                            this.stopAcknowledged = true;
                            this.log('Stop acknowledged', 'warning');
                        }
                        return;
                    }

                    if (response.success) {
                        if (response.data.stats) {
                            this.updateStats(response.data.stats);
                        }

                        this.updateUI(response.data.state);

                        if (typeof this.config.onBatchComplete === 'function') {
                            this.config.onBatchComplete(response.data);
                        }

                        // Log batch results
                        if (response.data.batch_processed > 0) {
                            let successMsg = `Processed ${response.data.batch_processed} items`;
                            // Add bytes saved info if available (for optimization)
                            if (response.data.batch_bytes_saved_formatted) {
                                successMsg += ` (saved ${response.data.batch_bytes_saved_formatted})`;
                            }
                            this.log(successMsg, 'success');
                        }

                        if (response.data.batch_skipped > 0) {
                            this.log(`Skipped ${response.data.batch_skipped} items (already optimized or too large)`, 'info');
                        }

                        if (response.data.batch_failed > 0) {
                            this.log(`${response.data.batch_failed} items failed (queued for retry)`, 'warning');
                            if (response.data.batch_errors) {
                                response.data.batch_errors.forEach(err => {
                                    // Show a more user-friendly error message
                                    let errorMsg = err.error || 'Unknown error';
                                    // Simplify common error messages
                                    if (errorMsg.includes('BadDigest') || errorMsg.includes('CRC32')) {
                                        errorMsg = 'Checksum error - will retry automatically';
                                    } else if (errorMsg.includes('timeout') || errorMsg.includes('Timeout')) {
                                        errorMsg = 'Connection timeout - will retry';
                                    }
                                    this.log(`  ID ${err.item_id}: ${errorMsg}`, 'error');
                                });
                            }
                        }

                        // Show retry queue status if there are items in the queue
                        if (response.data.retry_queue_count > 0) {
                            this.log(`${response.data.retry_queue_count} operations in retry queue`, 'info');
                        }

                        // Check if complete
                        if (response.data.complete) {
                            this.handleComplete(response.data.state);
                        }
                    } else {
                        // Process was stopped or is not running - stop the interval
                        const state = response.data?.state;
                        if (state && (state.status === 'idle' || state.status === 'paused' || state.status === 'completed')) {
                            this.stopInterval();
                            this.isRunning = false;
                            this.setButtonsState(state.status);
                            this.log('Process stopped', 'warning');
                        } else {
                            this.log('Batch processing failed: ' + (response.data?.message || 'Unknown error'), 'error');
                        }
                    }
                }, () => {
                    // Check if stop was requested during error
                    if (!this.isRunning) {
                        return;
                    }
                    this.log('Batch processing error', 'error');
                });
            };

            // Process immediately then at interval
            processBatch();
            this.intervalId = setInterval(processBatch, this.config.batchInterval);
        }

        /**
         * Handle process completion
         */
        handleComplete(state) {
            this.isRunning = false;
            this.isPaused = false;
            this.stopInterval();
            this.log('Completed!', 'success');
            this.setButtonsState('completed');

            if (typeof this.config.onComplete === 'function') {
                this.config.onComplete(state);
            }
        }

        /**
         * Pause the batch process
         */
        pause() {
            // Immediately stop the interval to prevent more batches
            this.stopInterval();
            this.isPaused = true;
            this.isRunning = false;
            this.setButtonsState('paused');
            this.log('Pausing...', 'info');

            this.ajax(this.config.actions.pause, {}, (response) => {
                if (response.success) {
                    this.log('Paused', 'warning');
                } else {
                    // Restore state if pause failed
                    this.isPaused = false;
                    this.isRunning = true;
                    this.setButtonsState('running');
                    this.log('Failed to pause', 'error');
                    this.startBatchProcessing();
                }
            }, () => {
                // Restore state on error
                this.isPaused = false;
                this.isRunning = true;
                this.setButtonsState('running');
                this.log('Failed to pause', 'error');
                this.startBatchProcessing();
            });
        }

        /**
         * Resume the batch process
         */
        resume() {
            this.setButtonsState('running');
            this.log('Resuming...', 'info');

            this.ajax(this.config.actions.resume, {}, (response) => {
                if (response.success) {
                    this.isPaused = false;
                    this.isRunning = true;

                    if (response.data.stats) {
                        this.updateStats(response.data.stats);
                    }

                    this.log('Resumed', 'success');
                    this.startBatchProcessing();
                } else {
                    const msg = response.data?.message || 'Failed to resume';
                    this.log(msg, 'error');
                    this.checkCurrentStatus();
                }
            }, () => {
                this.setButtonsState('paused');
                this.log('Failed to resume', 'error');
            });
        }

        /**
         * Stop the batch process
         */
        stop() {
            if (this.config.confirmStop) {
                this.showConfirmModal(
                    'Stop Process?',
                    this.config.confirmStopMessage,
                    () => this.doStop(),
                    'Yes, Stop'
                );
            } else {
                this.doStop();
            }
        }

        /**
         * Execute stop action
         */
        doStop() {
            // Immediately stop processing to prevent race conditions
            this.isRunning = false;
            this.isPaused = false;
            this.stopInterval();
            
            // Disable all action buttons during stop
            this.$startBtn.prop('disabled', true);
            this.$pauseBtn.prop('disabled', true);
            this.$resumeBtn.prop('disabled', true);
            this.$stopBtn.prop('disabled', true);
            
            this.log('Stopping... (waiting for current batch to complete)', 'info');

            this.ajax(this.config.actions.stop, {}, (response) => {
                this.log('Stopped successfully', 'warning');
                this.setButtonsState('idle');
                this.checkCurrentStatus();
            }, () => {
                this.log('Error stopping (process may still have stopped)', 'error');
                // Even on error, keep the process stopped
                this.setButtonsState('idle');
                this.checkCurrentStatus();
            });
        }

        /**
         * Retry failed operations
         */
        retry() {
            if (!this.config.actions.retry) return;

            this.log('Retrying failed operations...', 'info');
            this.$retryBtn.prop('disabled', true);

            this.ajax(this.config.actions.retry, {}, (response) => {
                if (response.success) {
                    this.log('Retry completed', 'success');
                    this.checkCurrentStatus();
                } else {
                    this.log('Retry failed', 'error');
                    this.$retryBtn.prop('disabled', false);
                }
            }, () => {
                this.log('Retry failed', 'error');
                this.$retryBtn.prop('disabled', false);
            });
        }

        /**
         * Update UI based on state
         */
        updateUI(state) {
            if (!state) return;

            // Update status text
            const $statusText = $('#status-text, [data-status-text]');
            if ($statusText.length && state.status) {
                $statusText.text(state.status.charAt(0).toUpperCase() + state.status.slice(1));
            }

            // Update counts
            $('#processed-count, [data-processed]').text(state.processed || 0);
            $('#total-count, [data-total]').text(state.total_files || state.total || 0);
            $('#failed-count, [data-failed]').text(state.failed || 0);

            // Update progress bar
            if (state.total_files > 0 || state.total > 0) {
                const total = state.total_files || state.total;
                const progress = Math.round((state.processed / total) * 100);
                this.$progressBar.css('width', progress + '%');
                this.$progressText.text(progress + '%');
            }

            // Show/hide failed badge
            const $failedBadge = $('#failed-badge, [data-failed-badge]');
            if (state.failed > 0) {
                $failedBadge.show();
            } else {
                $failedBadge.hide();
            }

            // Set buttons state
            this.setButtonsState(state.status || 'idle');

            // Call custom status update handler
            if (typeof this.config.onStatusUpdate === 'function') {
                this.config.onStatusUpdate(state);
            }
        }

        /**
         * Update stats (can be overridden)
         */
        updateStats(stats) {
            // Default implementation - update common stat elements
            Object.keys(stats).forEach(key => {
                $(`#stat-${key}, [data-stat="${key}"]`).text(
                    typeof stats[key] === 'number'
                        ? stats[key].toLocaleString()
                        : stats[key]
                );
            });
        }

        /**
         * Log message to the log container
         */
        log(message, type = 'info') {
            if (!this.$logContainer.length) return;

            const timestamp = new Date().toLocaleTimeString();

            // Remove placeholder (the entire line, not just the span)
            this.$logContainer.find('.mt-terminal-line').first().each(function() {
                if ($(this).find('.mt-terminal-muted').text().includes('will appear here')) {
                    $(this).remove();
                }
            });

            const typeClass = {
                success: 'mt-terminal-success',
                error: 'mt-terminal-error',
                warning: 'mt-terminal-warning',
                info: 'mt-terminal-muted'
            }[type] || 'mt-terminal-muted';

            const $entry = $(`
                <div class="mt-terminal-line">
                    <span class="mt-terminal-prompt">$</span>
                    <span class="mt-terminal-text ${typeClass}">[${timestamp}] ${this.escapeHtml(message)}</span>
                </div>
            `);

            this.$logContainer.append($entry);
            this.$logContainer.scrollTop(this.$logContainer[0].scrollHeight);
        }

        /**
         * Clear log
         */
        clearLog() {
            if (!this.$logContainer.length) return;

            this.$logContainer.html(`
                <div class="mt-terminal-line">
                    <span class="mt-terminal-prompt">$</span>
                    <span class="mt-terminal-text mt-terminal-muted">Log will appear here...</span>
                </div>
            `);
        }

        /**
         * Show confirmation modal
         */
        showConfirmModal(title, message, callback, confirmLabel = null) {
            this.pendingCallback = callback;
            $('#confirm-title').text(title);
            $('#confirm-message').text(message);
            if (confirmLabel) {
                $('#btn-confirm-yes').text(confirmLabel);
            }
            this.$modal.show();
        }

        /**
         * Confirm modal action
         */
        confirmAction() {
            this.closeModal();
            if (this.pendingCallback) {
                this.pendingCallback();
                this.pendingCallback = null;
            }
        }

        /**
         * Close modal
         */
        closeModal() {
            $('.mt-modal-overlay').hide();
        }

        /**
         * AJAX helper with automatic retry for temporary errors
         */
        ajax(action, data, success, error, retryCount = 0) {
            const self = this;
            const maxRetries = 2;
            const retryableStatuses = [0, 502, 503, 504, 520, 521, 522, 523, 524]; // 0 = timeout, 5xx = server errors
            
            $.ajax({
                url: mediaToolkit.ajaxUrl,
                method: 'POST',
                timeout: 60000, // 60 second timeout
                data: Object.assign({
                    action: action,
                    nonce: mediaToolkit.nonce
                }, data),
                success: success,
                error: function(xhr, status, errorThrown) {
                    // Check if this is a retryable error and we haven't exceeded max retries
                    if (retryableStatuses.includes(xhr.status) && retryCount < maxRetries && self.isRunning) {
                        const retryDelay = (retryCount + 1) * 2000; // 2s, 4s
                        self.log(`HTTP ${xhr.status || 'timeout'} - retrying in ${retryDelay/1000}s... (attempt ${retryCount + 2}/${maxRetries + 1})`, 'warning');
                        
                        setTimeout(() => {
                            // Check again if still running before retry
                            if (self.isRunning) {
                                self.ajax(action, data, success, error, retryCount + 1);
                            }
                        }, retryDelay);
                        return;
                    }
                    
                    // Log detailed error info
                    self.log(`HTTP Error: ${xhr.status} ${errorThrown}`, 'error');
                    if (xhr.responseText) {
                        // Try to parse JSON error response
                        try {
                            const jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse.data?.message) {
                                self.log(`Server message: ${jsonResponse.data.message}`, 'error');
                            }
                            if (jsonResponse.data?.trace) {
                                console.error('Stack trace:', jsonResponse.data.trace);
                            }
                        } catch(e) {
                            // Not JSON, log raw response (first 500 chars)
                            const snippet = xhr.responseText.substring(0, 500);
                            console.error('Server response:', snippet);
                            self.log(`Server response: ${snippet.substring(0, 100)}...`, 'error');
                        }
                    }
                    if (error) error(xhr, status, errorThrown);
                }
            });
        }

        /**
         * Stop interval
         */
        stopInterval() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        }

        /**
         * Escape HTML
         */
        escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /**
         * Destroy the processor
         */
        destroy() {
            this.stopInterval();
            this.$startBtn.off('click');
            this.$pauseBtn.off('click');
            this.$resumeBtn.off('click');
            this.$stopBtn.off('click');
            this.$retryBtn.off('click');
        }
    }

    // Export to window
    window.BatchProcessor = BatchProcessor;

})(jQuery, window);
