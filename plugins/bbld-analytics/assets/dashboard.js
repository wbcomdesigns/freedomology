/**
 * Enhanced Dashboard JavaScript with Indexing Support
 * 
 * @package Wbcom_Reports
 */

jQuery(document).ready(function($) {
    'use strict';
    
    let isIndexing = false;
    
    // Initialize dashboard
    initDashboard();
    
    function initDashboard() {
        loadDashboardStats();
        bindEvents();
        checkIndexStatus();
    }
    
    function bindEvents() {
        // Refresh stats button
        $('#refresh-dashboard-stats').on('click', function() {
            $(this).prop('disabled', true).text('Refreshing...');
            loadDashboardStats();
        });
        
        // Rebuild index button
        $('#rebuild-dashboard-index').on('click', function() {
            rebuildDashboardIndex();
        });
        
        // Auto-refresh every 5 minutes
        setInterval(function() {
            loadDashboardStats();
        }, 300000); // 5 minutes
    }
    
    function checkIndexStatus() {
        // Check if index needs rebuilding based on UI indicators
        const $rebuildButton = $('#rebuild-dashboard-index');
        if ($rebuildButton.hasClass('needs-rebuild')) {
            showIndexWarning();
        }
    }
    
    function showIndexWarning() {
        const warningHtml = `
            <div class="notice notice-warning is-dismissible index-warning">
                <p>
                    <strong>Dashboard Performance Notice:</strong> 
                    The dashboard index needs rebuilding for optimal performance. 
                    <a href="#" id="rebuild-dashboard-index-link">Rebuild now</a> to improve loading times.
                </p>
            </div>
        `;
        
        $('.wbcom-stats-container').prepend(warningHtml);
        
        $('#rebuild-dashboard-index-link').on('click', function(e) {
            e.preventDefault();
            rebuildDashboardIndex();
        });
    }
    
    function rebuildDashboardIndex() {
        if (isIndexing) {
            return;
        }
        
        const $button = $('#rebuild-dashboard-index');
        const originalText = $button.text();
        
        isIndexing = true;
        $button.prop('disabled', true).text('Rebuilding Dashboard Index...');
        
        // Show progress indicator
        showIndexProgress();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'rebuild_dashboard_index',
                nonce: wbcomReports.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);
                    
                    // Update index status display
                    updateIndexStatus(response.data.indexed_count);
                    
                    // Remove warning if present
                    $('.index-warning').fadeOut();
                    
                    // Change button styling back to normal
                    $button.removeClass('needs-rebuild');
                    
                    // Show performance indicator
                    showPerformanceIndicator();
                    
                    // Refresh the data to show improved performance
                    setTimeout(() => {
                        loadDashboardStats();
                    }, 1000);
                    
                } else {
                    showErrorMessage('Failed to rebuild dashboard index: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error rebuilding dashboard index: ' + error);
            },
            complete: function() {
                isIndexing = false;
                $button.prop('disabled', false).text(originalText);
                hideIndexProgress();
            }
        });
    }
    
    function showIndexProgress() {
        const progressHtml = `
            <div id="dashboard-index-progress" class="wbcom-index-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p>Rebuilding dashboard index... This improves performance across all reports.</p>
            </div>
        `;
        
        $('.wbcom-stats-actions').after(progressHtml);
        
        // Animate progress bar
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            
            $('#dashboard-index-progress .progress-fill').css('width', progress + '%');
        }, 500);
        
        // Store interval for cleanup
        $('#dashboard-index-progress').data('interval', interval);
    }
    
    function hideIndexProgress() {
        const $progress = $('#dashboard-index-progress');
        const interval = $progress.data('interval');
        
        if (interval) {
            clearInterval(interval);
        }
        
        // Complete the progress bar
        $progress.find('.progress-fill').css('width', '100%');
        
        setTimeout(() => {
            $progress.fadeOut(() => {
                $progress.remove();
            });
        }, 500);
    }
    
    function updateIndexStatus(indexedCount) {
        $('.index-details p').first().html(function(i, html) {
            return html.replace(/\d+ of \d+ users indexed/, 
                indexedCount + ' of ' + indexedCount + ' users indexed (100% coverage)');
        });
    }
    
    function showPerformanceIndicator() {
        if (!$('.performance-indicator').length) {
            const indicatorHtml = `
                <div class="performance-indicator">
                    <span class="dashicons dashicons-performance"></span>
                    <small>Using indexed data for fast performance</small>
                </div>
            `;
            $('.wbcom-stats-actions').append(indicatorHtml);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                $('.performance-indicator').fadeOut();
            }, 3000);
        }
    }
    
    function loadDashboardStats() {
        showLoadingState();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_dashboard_stats',
                nonce: wbcomReports.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardDisplay(response.data);
                    
                    // Show performance indicator if using indexed data
                    if (response.data.using_index) {
                        showPerformanceIndicator();
                    }
                } else {
                    showErrorMessage('Failed to load dashboard stats: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error loading dashboard stats: ' + error);
            },
            complete: function() {
                hideLoadingState();
            }
        });
    }
    
    function updateDashboardDisplay(data) {
        // Update stat boxes with animation
        updateStatBox('#total-users', data.total_users);
        updateStatBox('#total-activities', data.total_activities);
        updateStatBox('#total-courses', data.total_courses);
        updateStatBox('#total-groups', data.total_groups);
        
        // Update top users table
        updateTopUsersTable(data.top_users);
        
        // Update top learners table (replaces top groups)
        updateTopLearnersTable(data.top_groups);
    }
    
    function updateStatBox(selector, value) {
        const $element = $(selector);
        $element.fadeOut(200, function() {
            $element.text(formatNumber(value)).fadeIn(200);
        });
    }
    
    function updateTopUsersTable(users) {
        const $tableBody = $('#top-users-table tbody');
        $tableBody.empty();
        
        if (users && users.length > 0) {
            users.forEach(function(user) {
                const editUrl = wbcomReports.adminUrl + 'user-edit.php?user_id=' + user.user_id;
                
                const row = `
                    <tr class="fade-in">
                        <td><strong class="text-primary">#${user.rank}</strong></td>
                        <td>
                            <strong>
                                <a href="${editUrl}" class="user-edit-link" target="_blank">
                                    ${escapeHtml(user.display_name)}
                                    <span class="dashicons dashicons-external" style="font-size: 12px; margin-left: 4px;"></span>
                                </a>
                            </strong>
                            <br><small class="text-muted">${escapeHtml(user.user_login)}</small>
                        </td>
                        <td><code>${escapeHtml(user.user_login)}</code></td>
                        <td><span class="font-bold text-primary">${formatNumber(user.activity_count)}</span></td>
                        <td>${formatNumber(user.comment_count)}</td>
                        <td><small>${formatDate(user.last_activity)}</small></td>
                    </tr>
                `;
                $tableBody.append(row);
            });
        } else {
            $tableBody.append(`
                <tr>
                    <td colspan="6" class="text-center">
                        <em>No activity data found. Users need to post activities first.</em>
                    </td>
                </tr>
            `);
        }
    }
    
    function updateTopLearnersTable(learners) {
        const $tableBody = $('#top-groups-table tbody');
        $tableBody.empty();
        
        if (learners && learners.length > 0) {
            learners.forEach(function(learner) {
                const editUrl = wbcomReports.adminUrl + 'user-edit.php?user_id=' + learner.user_id;
                const progressClass = getProgressClass(learner.avg_progress);
                
                const row = `
                    <tr class="fade-in">
                        <td><strong class="text-primary">#${learner.rank}</strong></td>
                        <td>
                            <strong>
                                <a href="${editUrl}" class="user-edit-link" target="_blank">
                                    ${escapeHtml(learner.display_name)}
                                    <span class="dashicons dashicons-external" style="font-size: 12px; margin-left: 4px;"></span>
                                </a>
                            </strong>
                            <br><small class="text-muted">${escapeHtml(learner.user_login)}</small>
                        </td>
                        <td>
                            <span class="font-bold text-success">${formatNumber(learner.enrolled_courses)}</span>
                        </td>
                        <td>
                            <span class="text-success font-bold">${formatNumber(learner.completed_courses)}</span>
                        </td>
                        <td>
                            <span class="progress-badge ${progressClass}">${learner.avg_progress}</span>
                        </td>
                        <td>
                            <small>${formatDate(learner.last_activity)}</small>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
        } else {
            $tableBody.append(`
                <tr>
                    <td colspan="6" class="text-center">
                        <em>No learning data found. Create courses and enroll users to see top learners.</em>
                    </td>
                </tr>
            `);
        }
    }
    
    function getProgressClass(progressRate) {
        const rate = parseFloat(progressRate);
        if (rate >= 80) return 'progress-excellent';
        if (rate >= 60) return 'progress-good';
        if (rate >= 40) return 'progress-average';
        return 'progress-poor';
    }
    
    function showLoadingState() {
        // Show loading in stat boxes
        $('.stat-number').text('Loading...');
        
        // Show loading in tables
        $('#top-users-table tbody').html('<tr><td colspan="6" class="text-center wbcom-loading">Loading users...</td></tr>');
        $('#top-groups-table tbody').html('<tr><td colspan="6" class="text-center wbcom-loading">Loading learners...</td></tr>');
    }
    
    function hideLoadingState() {
        $('#refresh-dashboard-stats').prop('disabled', false).text('Refresh Stats');
    }
    
    function showErrorMessage(message) {
        showMessage(message, 'error');
    }
    
    function showSuccessMessage(message) {
        showMessage(message, 'success');
    }
    
    function showMessage(message, type) {
        const $container = $('.wbcom-stats-container');
        const messageHtml = `
            <div class="notice notice-${type} is-dismissible fade-in">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;
        
        $container.prepend(messageHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $(`.notice-${type}`).fadeOut();
        }, 5000);
    }
    
    // Utility functions
    function formatNumber(num) {
        if (num === null || num === undefined) return '0';
        return parseInt(num).toLocaleString();
    }
    
    function formatDate(dateString) {
        if (!dateString || dateString === 'Never') return 'Never';
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } catch (e) {
            return dateString;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Handle notice dismissal
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    // Add status badge styles dynamically
    if (!$('#dashboard-dynamic-styles').length) {
        $('head').append(`
            <style id="dashboard-dynamic-styles">
                .text-muted { color: #666; }
                
                /* Index Status Styles */
                .wbcom-index-status {
                    background: #f8f9fa;
                    border: 1px solid #e1e1e1;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .index-details p {
                    margin: 5px 0;
                }
                .cache-enabled {
                    color: #46b450;
                    font-weight: bold;
                }
                .cache-disabled {
                    color: #ffb900;
                    font-weight: bold;
                }
                .performance-optimized {
                    color: #0073aa;
                    font-weight: bold;
                }
                .needs-rebuild {
                    background-color: #ff6b6b !important;
                    color: white !important;
                }
                .needs-rebuild:hover {
                    background-color: #ff5252 !important;
                }
                
                /* Progress Bar Styles */
                .wbcom-index-progress {
                    background: #fff;
                    border: 1px solid #0073aa;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 15px 0;
                    text-align: center;
                }
                .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin-bottom: 10px;
                }
                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #0073aa, #00a0d2);
                    width: 0%;
                    transition: width 0.3s ease;
                    border-radius: 10px;
                }
                
                /* Performance Indicator */
                .performance-indicator {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    background: #d4edda;
                    color: #155724;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-size: 12px;
                    margin-left: 10px;
                }
                .performance-indicator .dashicons {
                    font-size: 14px;
                    width: 14px;
                    height: 14px;
                }
                
                /* Progress badges for learners */
                .progress-badge {
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    color: white;
                }
                .progress-excellent { background: #46b450; }
                .progress-good { background: #00a32a; }
                .progress-average { background: #ffb900; }
                .progress-poor { background: #dc3232; }
                
                /* User edit link styles */
                .user-edit-link {
                    text-decoration: none;
                    color: #0073aa;
                    transition: color 0.2s ease;
                }
                .user-edit-link:hover {
                    color: #005a87;
                    text-decoration: underline;
                }
                .user-edit-link .dashicons {
                    opacity: 0.7;
                    transition: opacity 0.2s ease;
                }
                .user-edit-link:hover .dashicons {
                    opacity: 1;
                }
                
                /* Fade in animation */
                .fade-in {
                    animation: fadeIn 0.5s ease-in forwards;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            </style>
        `);
    }
});