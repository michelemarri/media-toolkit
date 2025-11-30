<?php
/**
 * Logs page template - Modern UI
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap s3-offload-wrap s3-modern">
    <div class="s3-page-header">
        <div class="s3-page-title">
            <div class="s3-icon-wrapper s3-icon-logs">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <div>
                <h1>Activity Logs</h1>
                <p class="s3-subtitle">Real-time operations • Auto-cleans after 24 hours</p>
            </div>
        </div>
        <div class="s3-header-actions">
            <button type="button" class="s3-btn s3-btn-ghost" id="btn-clear-logs">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Clear All
            </button>
        </div>
    </div>

    <div class="s3-filters-bar">
        <div class="s3-filter-group">
            <div class="s3-select-wrapper">
                <select id="filter-log-level" class="s3-select">
                    <option value="">All Levels</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="success">Success</option>
                </select>
            </div>
            
            <div class="s3-select-wrapper">
                <select id="filter-log-operation" class="s3-select">
                    <option value="">All Operations</option>
                </select>
            </div>
            
            <button type="button" class="s3-btn s3-btn-secondary" id="btn-refresh-logs">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                Refresh
            </button>
        </div>
        
        <label class="s3-toggle-label">
            <input type="checkbox" id="auto-refresh-logs" class="s3-toggle" checked>
            <span class="s3-toggle-switch"></span>
            <span class="s3-toggle-text">Live updates</span>
        </label>
    </div>

    <div class="s3-table-card">
        <div class="s3-table-wrapper">
            <table class="s3-table" id="logs-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Time</th>
                        <th style="width: 100px;">Level</th>
                        <th style="width: 130px;">Operation</th>
                        <th style="width: 220px;">File</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <tr>
                        <td colspan="5">
                            <div class="s3-empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <p>No logs found</p>
                                <span>Activity will appear here as operations occur</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="s3-table-footer">
            <div class="s3-table-count">
                <span class="s3-count-badge" id="logs-count">0</span> log entries
            </div>
            <div class="s3-live-indicator" id="live-indicator">
                <span class="s3-pulse"></span>
                Live
            </div>
        </div>
    </div>
</div>
