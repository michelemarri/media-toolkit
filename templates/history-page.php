<?php
/**
 * History page template - Modern UI
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$stats = new \Metodo\MediaToolkit\Stats\Stats(
    $plugin->get_logger(),
    $plugin->get_history()
);
$history_stats = $stats->get_history_stats();
?>

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-history">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div>
                <h1>Operation History</h1>
                <p class="s3-subtitle">Permanent record of all S3 operations</p>
            </div>
        </div>
        <div class="s3-header-actions">
            <button type="button" class="s3-btn s3-btn-ghost" id="btn-clear-history">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Clear All
            </button>
            <button type="button" class="s3-btn s3-btn-primary" id="btn-export-history">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    <div class="s3-stats-row">
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-uploaded">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-uploaded"><?php echo esc_html($history_stats['uploaded'] ?? 0); ?></span>
                <span class="s3-stat-label">Uploaded</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-migrated">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 16 12 12 8 16"></polyline>
                    <line x1="12" y1="12" x2="12" y2="21"></line>
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-migrated"><?php echo esc_html($history_stats['migrated'] ?? 0); ?></span>
                <span class="s3-stat-label">Migrated</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-edited">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-edited"><?php echo esc_html($history_stats['edited'] ?? 0); ?></span>
                <span class="s3-stat-label">Edited</span>
            </div>
        </div>
        
        <div class="s3-stat-card">
            <div class="s3-stat-icon s3-stat-deleted">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </div>
            <div class="s3-stat-content">
                <span class="s3-stat-value" id="stat-deleted"><?php echo esc_html($history_stats['deleted'] ?? 0); ?></span>
                <span class="s3-stat-label">Deleted</span>
            </div>
        </div>
    </div>

    <div class="s3-filters-bar">
        <div class="s3-filter-group">
            <div class="s3-select-wrapper">
                <select id="filter-history-action" class="s3-select">
                    <option value="">All Actions</option>
                    <option value="uploaded">Uploaded</option>
                    <option value="migrated">Migrated</option>
                    <option value="deleted">Deleted</option>
                    <option value="edited">Edited</option>
                    <option value="invalidation">Invalidation</option>
                </select>
            </div>
            
            <div class="s3-date-range">
                <div class="s3-input-wrapper">
                    <input type="date" id="filter-date-from" class="s3-input" placeholder="From">
                </div>
                <span class="s3-date-separator">to</span>
                <div class="s3-input-wrapper">
                    <input type="date" id="filter-date-to" class="s3-input" placeholder="To">
                </div>
            </div>
            
            <button type="button" class="s3-btn s3-btn-secondary" id="btn-filter-history">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                Apply
            </button>
        </div>
    </div>

    <div class="s3-table-card">
        <div class="s3-table-wrapper">
            <table class="s3-table" id="history-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Date</th>
                        <th style="width: 110px;">Action</th>
                        <th>File Path</th>
                        <th style="width: 100px;">Size</th>
                        <th style="width: 150px;">User</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <tr>
                        <td colspan="5">
                            <div class="s3-empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <p>No history found</p>
                                <span>Operations will be recorded here</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="s3-table-footer">
            <div class="s3-table-count">
                <span class="s3-count-badge" id="history-count">0</span> total entries
            </div>
            
            <div class="s3-pagination" id="history-pagination">
                <button type="button" class="s3-btn s3-btn-icon" id="btn-prev-page" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <span id="page-info" class="s3-page-info">Page 1 of 1</span>
                <button type="button" class="s3-btn s3-btn-icon" id="btn-next-page" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
