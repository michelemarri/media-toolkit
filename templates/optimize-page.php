<?php
/**
 * Optimize page template - Image Compression
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$settings = $plugin->get_settings();
$is_configured = $settings && $settings->is_configured();

// Get Admin_Optimize instance from plugin or create new one
$admin_optimize = new \Metodo\MediaToolkit\Admin\Admin_Optimize(
    $plugin->get_image_optimizer(),
    $settings
);

$stats = $admin_optimize->get_stats();
$opt_settings = $admin_optimize->get_optimization_settings();
$capabilities = $admin_optimize->get_server_capabilities();
$state = $admin_optimize->get_state();
?>

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-optimize">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div>
                <h1>Image Optimization</h1>
                <p class="s3-subtitle">Compress and optimize your media library images</p>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="s3-stats-row">
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-total">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-total_images"><?php echo esc_html($stats['total_images']); ?></span>
                <span class="s3-stat-label">Total Images</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-migrated">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-optimized_images"><?php echo esc_html($stats['optimized_images']); ?></span>
                <span class="s3-stat-label">Optimized</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-edited">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-pending_images"><?php echo esc_html($stats['pending_images']); ?></span>
                <span class="s3-stat-label">Pending</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-sync">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-total_saved_formatted"><?php echo esc_html($stats['total_saved_formatted']); ?></span>
                <span class="s3-stat-label">Space Saved</span>
            </div>
        </div>
    </div>

    <!-- Progress -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
                Optimization Progress
            </h3>
            <span class="s3-progress-badge" id="progress-percentage"><?php echo esc_html($stats['progress_percentage']); ?>%</span>
        </div>
        <div class="s3-card-body">
            <div class="s3-progress-large">
                <div class="s3-progress-track">
                    <div class="s3-progress-fill-animated" id="optimization-progress" 
                         style="width: <?php echo esc_attr($stats['progress_percentage']); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="s3-grid-2">
        <!-- Compression Settings -->
        <div class="s3-card-panel">
            <div class="s3-card-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Compression Settings
                </h3>
            </div>
            <div class="s3-card-body">
                <div class="s3-form-group">
                    <label for="jpeg-quality" class="s3-label">JPEG Quality</label>
                    <div class="s3-slider-group">
                        <input type="range" id="jpeg-quality" class="s3-slider" 
                               min="60" max="100" value="<?php echo esc_attr($opt_settings['jpeg_quality']); ?>">
                        <span class="s3-slider-value" id="jpeg-quality-value"><?php echo esc_html($opt_settings['jpeg_quality']); ?></span>
                    </div>
                    <span class="s3-help">Higher = better quality, larger file. Recommended: 75-85</span>
                </div>
                
                <div class="s3-form-group" style="margin-top: 20px;">
                    <label for="png-compression" class="s3-label">PNG Compression Level</label>
                    <div class="s3-slider-group">
                        <input type="range" id="png-compression" class="s3-slider" 
                               min="0" max="9" value="<?php echo esc_attr($opt_settings['png_compression']); ?>">
                        <span class="s3-slider-value" id="png-compression-value"><?php echo esc_html($opt_settings['png_compression']); ?></span>
                    </div>
                    <span class="s3-help">0 = no compression, 9 = max compression (lossless)</span>
                </div>

                <div class="s3-form-group" style="margin-top: 20px;">
                    <label for="max-file-size" class="s3-label">Max File Size (MB)</label>
                    <div class="s3-select-wrapper s3-select-full">
                        <select id="max-file-size" class="s3-select">
                            <option value="5" <?php selected($opt_settings['max_file_size_mb'], 5); ?>>5 MB</option>
                            <option value="10" <?php selected($opt_settings['max_file_size_mb'], 10); ?>>10 MB</option>
                            <option value="20" <?php selected($opt_settings['max_file_size_mb'], 20); ?>>20 MB</option>
                            <option value="50" <?php selected($opt_settings['max_file_size_mb'], 50); ?>>50 MB</option>
                        </select>
                    </div>
                    <span class="s3-help">Files larger than this will be skipped</span>
                </div>

                <div class="s3-checkbox-group" style="margin-top: 20px;">
                    <label class="s3-checkbox-label">
                        <input type="checkbox" id="strip-metadata" <?php checked($opt_settings['strip_metadata']); ?>>
                        <span class="s3-checkbox-box"></span>
                        <span class="s3-checkbox-text">
                            <strong>Strip EXIF/Metadata</strong>
                            <span>Remove camera info, GPS data, etc. from JPEG files</span>
                        </span>
                    </label>
                </div>

                <div class="s3-form-actions">
                    <button type="button" class="s3-btn s3-btn-secondary" id="btn-save-settings">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Save Settings
                    </button>
                    <span class="s3-action-status" id="settings-status"></span>
                </div>
            </div>
        </div>

        <!-- Batch Processing Controls -->
        <div class="s3-card-panel">
            <div class="s3-card-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    Batch Optimization
                </h3>
            </div>
            <div class="s3-card-body">
                <div class="s3-form-group">
                    <label for="batch-size" class="s3-label">Batch Size</label>
                    <div class="s3-select-wrapper s3-select-full">
                        <select id="batch-size" class="s3-select">
                            <option value="10">10 images per batch</option>
                            <option value="25" selected>25 images per batch</option>
                            <option value="50">50 images per batch</option>
                        </select>
                    </div>
                    <span class="s3-help">Smaller batches are safer for shared hosting</span>
                </div>

                <div class="s3-migration-controls" style="margin-top: 20px;">
                    <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-start-optimization">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        Start Optimization
                    </button>
                    
                    <div class="s3-btn-group">
                        <button type="button" class="s3-btn s3-btn-secondary" id="btn-pause-optimization" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="6" y="4" width="4" height="16"></rect>
                                <rect x="14" y="4" width="4" height="16"></rect>
                            </svg>
                            Pause
                        </button>
                        
                        <button type="button" class="s3-btn s3-btn-secondary" id="btn-resume-optimization" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Resume
                        </button>
                        
                        <button type="button" class="s3-btn s3-btn-danger" id="btn-stop-optimization" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>
                
                <div class="s3-status-panel" id="optimization-status" style="display: <?php echo $state['status'] !== 'idle' ? 'block' : 'none'; ?>;">
                    <div class="s3-status-item">
                        <span class="s3-status-label">Status</span>
                        <span class="s3-status-value" id="status-text"><?php echo esc_html(ucfirst($state['status'])); ?></span>
                    </div>
                    <div class="s3-status-item">
                        <span class="s3-status-label">Progress</span>
                        <span class="s3-status-value">
                            <span id="processed-count"><?php echo esc_html($state['processed']); ?></span> / <span id="total-count"><?php echo esc_html($state['total_files']); ?></span>
                            <span class="s3-badge s3-badge-error" style="margin-left: 8px; display: <?php echo $state['failed'] > 0 ? 'inline-flex' : 'none'; ?>;" id="failed-badge">
                                <span id="failed-count"><?php echo esc_html($state['failed']); ?></span> failed
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Capabilities -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                Server Capabilities
            </h3>
        </div>
        <div class="s3-card-body">
            <div class="s3-capabilities-grid">
                <div class="s3-capability-item <?php echo $capabilities['gd'] ? 's3-capability-ok' : 's3-capability-error'; ?>">
                    <span class="s3-capability-icon">
                        <?php if ($capabilities['gd']): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        <?php endif; ?>
                    </span>
                    <span class="s3-capability-name">GD Library</span>
                    <span class="s3-capability-status"><?php echo $capabilities['gd'] ? 'Available' : 'Not available'; ?></span>
                </div>

                <div class="s3-capability-item <?php echo $capabilities['imagick'] ? 's3-capability-ok' : 's3-capability-warn'; ?>">
                    <span class="s3-capability-icon">
                        <?php if ($capabilities['imagick']): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        <?php endif; ?>
                    </span>
                    <span class="s3-capability-name">ImageMagick</span>
                    <span class="s3-capability-status"><?php echo $capabilities['imagick'] ? 'Available' : 'Not available (optional)'; ?></span>
                </div>

                <div class="s3-capability-item <?php echo $capabilities['webp_support'] ? 's3-capability-ok' : 's3-capability-warn'; ?>">
                    <span class="s3-capability-icon">
                        <?php if ($capabilities['webp_support']): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        <?php endif; ?>
                    </span>
                    <span class="s3-capability-name">WebP Support</span>
                    <span class="s3-capability-status"><?php echo $capabilities['webp_support'] ? 'Available' : 'Not available'; ?></span>
                </div>

                <div class="s3-capability-item s3-capability-info">
                    <span class="s3-capability-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                    </span>
                    <span class="s3-capability-name">Memory Limit</span>
                    <span class="s3-capability-status"><?php echo esc_html($capabilities['max_memory']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Log -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                Optimization Log
            </h3>
        </div>
        <div class="s3-card-body s3-card-body-dark">
            <div class="s3-terminal" id="optimization-log">
                <div class="s3-terminal-line s3-terminal-muted">
                    <span class="s3-terminal-prompt">$</span> Optimization log will appear here...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="s3-modal" style="display:none;">
    <div class="s3-modal-content">
        <button type="button" class="s3-modal-close">&times;</button>
        <h2 id="confirm-title">Confirm Action</h2>
        <p id="confirm-message"></p>
        <div class="s3-modal-buttons">
            <button type="button" class="s3-btn s3-btn-primary" id="btn-confirm-yes">Yes, Continue</button>
            <button type="button" class="s3-btn s3-btn-ghost" id="btn-confirm-no">Cancel</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize batch processor for optimization
    const optimizer = new BatchProcessor({
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
        confirmStopMessage: 'Are you sure you want to stop? Progress will be saved and you can resume later.',
        getStartOptions: function() {
            return {
                batch_size: parseInt($('#batch-size').val())
            };
        },
        onStatusUpdate: function(state) {
            // Update counts
            $('#processed-count').text(state.processed || 0);
            $('#total-count').text(state.total_files || 0);
            $('#failed-count').text(state.failed || 0);
            
            if (state.failed > 0) {
                $('#failed-badge').show();
            } else {
                $('#failed-badge').hide();
            }
            
            if (state.status) {
                $('#status-text').text(state.status.charAt(0).toUpperCase() + state.status.slice(1));
            }
        },
        onBatchComplete: function(result) {
            // Update stats from response
            if (result.stats) {
                $('#stat-total_images').text(result.stats.total_images || 0);
                $('#stat-optimized_images').text(result.stats.optimized_images || 0);
                $('#stat-pending_images').text(result.stats.pending_images || 0);
                $('#stat-total_saved_formatted').text(result.stats.total_saved_formatted || '0 B');
                
                const progress = result.stats.progress_percentage || 0;
                $('#optimization-progress').css('width', progress + '%');
                $('#progress-percentage').text(progress + '%');
            }
        },
        onComplete: function(state) {
            // Final stats refresh
            optimizer.checkCurrentStatus();
        }
    });

    // Slider value updates
    $('#jpeg-quality').on('input', function() {
        $('#jpeg-quality-value').text($(this).val());
    });

    $('#png-compression').on('input', function() {
        $('#png-compression-value').text($(this).val());
    });

    // Save settings
    $('#btn-save-settings').on('click', function() {
        const $btn = $(this);
        const $status = $('#settings-status');
        
        $btn.prop('disabled', true);
        $status.text('Saving...').removeClass('s3-terminal-success s3-terminal-error');

        $.ajax({
            url: mediaToolkit.ajaxUrl,
            method: 'POST',
            data: {
                action: 'media_toolkit_save_optimize_settings',
                nonce: mediaToolkit.nonce,
                jpeg_quality: $('#jpeg-quality').val(),
                png_compression: $('#png-compression').val(),
                strip_metadata: $('#strip-metadata').is(':checked') ? 'true' : 'false',
                max_file_size_mb: $('#max-file-size').val()
            },
            success: function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.text('✓ Saved').addClass('s3-terminal-success');
                    optimizer.log('Settings saved', 'success');
                } else {
                    $status.text('✗ Failed').addClass('s3-terminal-error');
                    optimizer.log('Failed to save settings', 'error');
                }
                setTimeout(() => $status.text(''), 3000);
            },
            error: function() {
                $btn.prop('disabled', false);
                $status.text('✗ Error').addClass('s3-terminal-error');
                setTimeout(() => $status.text(''), 3000);
            }
        });
    });
});
</script>

