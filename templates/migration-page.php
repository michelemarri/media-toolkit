<?php
/**
 * Migration page template - Modern UI
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

$stats = new \Metodo\MediaToolkit\Stats\Stats(
    $plugin->get_logger(),
    $plugin->get_history()
);
$migration_stats = $stats->get_migration_stats();
?>

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-migration">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </div>
            <div>
                <h1>Media Migration</h1>
                <p class="s3-subtitle">Migrate existing media files to Amazon S3</p>
            </div>
        </div>
    </div>

    <?php if (!$is_configured): ?>
        <div class="s3-alert s3-alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div>
                <strong>S3 Offload is not configured.</strong>
                <p>Please <a href="<?php echo admin_url('admin.php?page=media-toolkit&tab=settings'); ?>">configure your S3 settings</a> before migrating.</p>
            </div>
        </div>
    <?php else: ?>

    <!-- Stats Row -->
    <div class="s3-stats-row">
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-uploaded">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-total"><?php echo esc_html($migration_stats['total_attachments']); ?></span>
                <span class="s3-stat-label">Total Files</span>
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
                <span class="s3-stat-value" id="stat-migrated"><?php echo esc_html($migration_stats['migrated_attachments']); ?></span>
                <span class="s3-stat-label">Migrated</span>
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
                <span class="s3-stat-value" id="stat-pending"><?php echo esc_html($migration_stats['pending_attachments']); ?></span>
                <span class="s3-stat-label">Pending</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-deleted">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-size"><?php echo esc_html($migration_stats['pending_size_formatted']); ?></span>
                <span class="s3-stat-label">Pending Size</span>
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
                Migration Progress
            </h3>
            <span class="s3-progress-badge" id="progress-percentage"><?php echo esc_html($migration_stats['progress_percentage']); ?>%</span>
        </div>
        <div class="s3-card-body">
            <div class="s3-progress-large">
                <div class="s3-progress-track">
                    <div class="s3-progress-fill-animated" id="migration-progress" 
                         style="width: <?php echo esc_attr($migration_stats['progress_percentage']); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="s3-grid-2">
        <!-- Options -->
        <div class="s3-card-panel">
            <div class="s3-card-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4"></path>
                    </svg>
                    Migration Options
                </h3>
            </div>
            <div class="s3-card-body">
                <div class="s3-form-group">
                    <label for="batch-size" class="s3-label">Batch Size</label>
                    <div class="s3-select-wrapper s3-select-full">
                        <select id="batch-size" class="s3-select">
                            <option value="10">10 files per batch</option>
                            <option value="25" selected>25 files per batch</option>
                            <option value="50">50 files per batch</option>
                            <option value="100">100 files per batch</option>
                        </select>
                    </div>
                    <span class="s3-help">Smaller batches are safer but slower</span>
                </div>
                
                <div class="s3-form-group">
                    <label for="migration-mode" class="s3-label">Mode</label>
                    <div class="s3-select-wrapper s3-select-full">
                        <select id="migration-mode" class="s3-select">
                            <option value="sync">Synchronous (browser must stay open)</option>
                            <option value="async">Asynchronous (runs in background)</option>
                        </select>
                    </div>
                    <span class="s3-help">Async mode uses WordPress cron</span>
                </div>
                
                <div class="s3-checkbox-group" style="margin-top: 20px;">
                    <label class="s3-checkbox-label s3-checkbox-warning">
                        <input type="checkbox" id="remove-local">
                        <span class="s3-checkbox-box"></span>
                        <span class="s3-checkbox-text">
                            <strong>Delete local files after migration</strong>
                            <span>⚠️ This will permanently delete local copies. Make sure you have a backup!</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="s3-card-panel">
            <div class="s3-card-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    Controls
                </h3>
            </div>
            <div class="s3-card-body">
                <div class="s3-migration-controls">
                    <button type="button" class="s3-btn s3-btn-primary s3-btn-lg" id="btn-start-migration">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="10 8 16 12 10 16 10 8"></polygon>
                        </svg>
                        Start Migration
                    </button>
                    
                    <div class="s3-btn-group">
                        <button type="button" class="s3-btn s3-btn-secondary" id="btn-pause-migration" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="6" y="4" width="4" height="16"></rect>
                                <rect x="14" y="4" width="4" height="16"></rect>
                            </svg>
                            Pause
                        </button>
                        
                        <button type="button" class="s3-btn s3-btn-secondary" id="btn-resume-migration" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            Resume
                        </button>
                        
                        <button type="button" class="s3-btn s3-btn-danger" id="btn-stop-migration" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>
                
                <div class="s3-status-panel" id="migration-status" style="display: none;">
                    <div class="s3-status-item">
                        <span class="s3-status-label">Status</span>
                        <span class="s3-status-value" id="status-text">Idle</span>
                    </div>
                    <div class="s3-status-item">
                        <span class="s3-status-label">Current file</span>
                        <span class="s3-status-value s3-file-mono" id="current-file">-</span>
                    </div>
                    <div class="s3-status-item">
                        <span class="s3-status-label">Progress</span>
                        <span class="s3-status-value">
                            <span id="processed-count">0</span> / <span id="total-count">0</span>
                            <span class="s3-badge s3-badge-error" style="margin-left: 8px; display: none;" id="failed-badge">
                                <span id="failed-count">0</span> failed
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Migration Log -->
    <div class="s3-card-panel">
        <div class="s3-card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                Migration Log
            </h3>
            <button type="button" class="s3-btn s3-btn-secondary s3-btn-sm" id="btn-retry-failed" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Retry Failed
            </button>
        </div>
        <div class="s3-card-body s3-card-body-dark">
            <div class="s3-terminal" id="migration-log">
                <div class="s3-terminal-line s3-terminal-muted">
                    <span class="s3-terminal-prompt">$</span> Migration log will appear here...
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
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
