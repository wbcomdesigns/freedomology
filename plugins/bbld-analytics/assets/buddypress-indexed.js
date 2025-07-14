/**
 * Enhanced Reports JavaScript with Indexing Support - Complete Version
 * 
 * @package Wbcom_Reports
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // State management
    let currentPage = 1;
    let currentFilter = 'all';
    let currentDateFrom = '';
    let currentDateTo = '';
    let currentSearch = '';
    let currentSortBy = 'activity_count';
    let currentSortOrder = 'desc';
    let isIndexing = false;
    
    // Initialize BuddyPress reports
    initBuddyPressReports();
    
    function initBuddyPressReports() {
        loadBuddyPressStats();
        bindEvents();
        initFilters();
        checkIndexStatus();
    }
    
    function bindEvents() {
        // Refresh stats button
        $('#refresh-buddypress-stats').on('click', function() {
            $(this).prop('disabled', true).text('Refreshing...');
            loadBuddyPressStats();
        });
        
        // Export CSV button
        $('#export-buddypress-stats').on('click', function() {
            exportBuddyPressData();
        });
        
        // Apply filters button
        $('#apply-filters').on('click', function() {
            applyFilters();
        });
        
        // Rebuild index button
        $('#rebuild-bp-index').on('click', function() {
            rebuildIndex();
        });
        
        // Search functionality
        $('#user-search').on('input', debounce(function() {
            currentSearch = $(this).val().trim();
            currentPage = 1;
            loadBuddyPressStats();
        }, 500));
        
        // Clear search button
        $('#clear-search').on('click', function() {
            $('#user-search').val('');
            currentSearch = '';
            currentPage = 1;
            loadBuddyPressStats();
        });
        
        // Sort functionality
        $('.sortable-header').on('click', function() {
            const sortBy = $(this).data('sort');
            
            if (currentSortBy === sortBy) {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortBy = sortBy;
                currentSortOrder = 'desc';
            }
            
            updateSortIndicators();
            currentPage = 1;
            loadBuddyPressStats();
        });
        
        // Pagination buttons
        $('#prev-page').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadBuddyPressStats();
            }
        });
        
        $('#next-page').on('click', function() {
            currentPage++;
            loadBuddyPressStats();
        });
        
        // Filter change events
        $('#activity-filter').on('change', function() {
            currentFilter = $(this).val();
            currentPage = 1;
            loadBuddyPressStats();
        });
        
        // Date filter events
        $('#date-from, #date-to').on('change', function() {
            currentDateFrom = $('#date-from').val();
            currentDateTo = $('#date-to').val();
            currentPage = 1;
        });
    }
    
    function checkIndexStatus() {
        // Check if index needs rebuilding based on UI indicators
        const $rebuildButton = $('#rebuild-bp-index');
        if ($rebuildButton.css('background-color') === 'rgb(255, 107, 107)') {
            showIndexWarning();
        }
    }
    
    function showIndexWarning() {
        const warningHtml = `
            <div class="notice notice-warning is-dismissible index-warning">
                <p>
                    <strong>Performance Notice:</strong> 
                    The user index needs rebuilding for optimal performance. 
                    <a href="#" id="rebuild-index-link">Rebuild now</a> to improve loading times.
                </p>
            </div>
        `;
        
        $('.wbcom-stats-container').prepend(warningHtml);
        
        $('#rebuild-index-link').on('click', function(e) {
            e.preventDefault();
            rebuildIndex();
        });
    }
    
    function rebuildIndex() {
        if (isIndexing) {
            return;
        }
        
        const $button = $('#rebuild-bp-index');
        const originalText = $button.text();
        
        isIndexing = true;
        $button.prop('disabled', true).text('Rebuilding Index...');
        
        // Show progress indicator
        showIndexProgress();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'rebuild_bp_index',
                nonce: wbcomReports.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);
                    
                    // Update index status display
                    updateIndexStatus(response.data.indexed_count);
                    
                    // Remove warning if present
                    $('.index-warning').fadeOut();
                    
                    // Change button color back to normal
                    $button.css({
                        'background-color': '',
                        'color': ''
                    });
                    
                    // Refresh the data to show improved performance
                    setTimeout(() => {
                        loadBuddyPressStats();
                    }, 1000);
                    
                } else {
                    showErrorMessage('Failed to rebuild index: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error rebuilding index: ' + error);
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
            <div id="index-progress" class="wbcom-index-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p>Rebuilding user index... This may take a few moments for large sites.</p>
            </div>
        `;
        
        $('.wbcom-stats-actions').after(progressHtml);
        
        // Animate progress bar
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            
            $('#index-progress .progress-fill').css('width', progress + '%');
        }, 500);
        
        // Store interval for cleanup
        $('#index-progress').data('interval', interval);
    }
    
    function hideIndexProgress() {
        const $progress = $('#index-progress');
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
        $('.index-info p').html(function(i, html) {
            return html.replace(/\d+ of \d+ users indexed/, 
                indexedCount + ' of ' + indexedCount + ' users indexed (100% coverage)');
        });
    }
    
    function loadBuddyPressStats() {
        showLoadingState();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_buddypress_stats',
                nonce: wbcomReports.nonce,
                page: currentPage,
                filter: currentFilter,
                date_from: currentDateFrom,
                date_to: currentDateTo,
                search: currentSearch,
                sort_by: currentSortBy,
                sort_order: currentSortOrder
            },
            success: function(response) {
                if (response.success) {
                    updateBuddyPressDisplay(response.data);
                    
                    // Show performance indicator if using indexed data
                    if (response.data.using_index) {
                        showPerformanceIndicator();
                    }
                } else {
                    showErrorMessage('Failed to load BuddyPress stats: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error loading BuddyPress stats: ' + error);
            },
            complete: function() {
                hideLoadingState();
            }
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
    
    function initFilters() {
        // Set default date range (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        $('#date-to').val(formatDateInput(today));
        $('#date-from').val(formatDateInput(thirtyDaysAgo));
        
        // Initialize sort indicators
        updateSortIndicators();
    }
    
    function updateSortIndicators() {
        // Remove all sort indicators
        $('.sortable-header .sort-indicator').remove();
        
        // Add indicator to current sort column
        const $currentHeader = $(`.sortable-header[data-sort="${currentSortBy}"]`);
        const indicator = currentSortOrder === 'asc' ? '↑' : '↓';
        $currentHeader.append(`<span class="sort-indicator">${indicator}</span>`);
        
        // Update header classes
        $('.sortable-header').removeClass('sorted-asc sorted-desc');
        $currentHeader.addClass(`sorted-${currentSortOrder}`);
    }
    
    function updateBuddyPressDisplay(data) {
        // Update stat boxes with animation
        updateStatBox('#total-users', data.total_users);
        updateStatBox('#total-activities', data.total_activities);
        updateStatBox('#total-comments', data.total_comments);
        updateStatBox('#total-likes', data.total_likes);
        
        // Update user statistics table
        updateUserStatsTable(data.user_stats);
        
        // Update pagination
        updatePagination(data.pagination);
        
        // Update search results info
        updateSearchInfo(data.search_info);
    }
    
    function updateUserStatsTable(userStats) {
        const $tableBody = $('#user-activity-stats-table tbody');
        $tableBody.empty();
        
        if (userStats && userStats.length > 0) {
            userStats.forEach(function(user, index) {
                const editUrl = wbcomReports.adminUrl + 'user-edit.php?user_id=' + user.user_id;
                
                const row = `
                    <tr class="fade-in" style="animation-delay: ${index * 0.1}s">
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
                        <td>${escapeHtml(user.user_type)}</td>
                        <td><small>${formatDate(user.registration_date)}</small></td>
                        <td>
                            <small class="${getLastLoginClass(user.last_login)}">
                                ${formatDate(user.last_login)}
                            </small>
                        </td>
                        <td>
                            <span class="activity-count text-success font-bold">
                                ${formatNumber(user.activity_count)}
                            </span>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
            
            // Add hover effects
            addTableHoverEffects();
            
        } else {
            const noDataMessage = currentSearch ? 
                `No users found matching "${currentSearch}". Try adjusting your search or filters.` :
                'No user data found with the current filters. Try adjusting your filter criteria or date range.';
                
            $tableBody.append(`
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="no-data-message">
                            <p><em>${noDataMessage}</em></p>
                            ${currentSearch ? '<p><small>Clear the search to see all users.</small></p>' : ''}
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    function updatePagination(pagination) {
        if (!pagination) return;
        
        const { current_page, total_pages, total_users, filtered_users } = pagination;
        
        // Update page info
        let pageInfo = `Page ${current_page} of ${total_pages}`;
        if (currentSearch) {
            pageInfo += ` (${formatNumber(filtered_users)} of ${formatNumber(total_users)} users)`;
        } else {
            pageInfo += ` (${formatNumber(total_users)} total users)`;
        }
        $('#page-info').text(pageInfo);
        
        // Update button states
        $('#prev-page').prop('disabled', current_page <= 1);
        $('#next-page').prop('disabled', current_page >= total_pages);
        
        // Show/hide pagination if needed
        if (total_pages <= 1) {
            $('.wbcom-pagination').hide();
        } else {
            $('.wbcom-pagination').show();
        }
    }
    
    function updateSearchInfo(searchInfo) {
        if (!searchInfo) return;
        
        const $searchInfo = $('#search-info');
        if (searchInfo.active_search) {
            $searchInfo.html(`
                <div class="search-results-info">
                    <strong>Search Results:</strong> Found ${formatNumber(searchInfo.filtered_count)} 
                    of ${formatNumber(searchInfo.total_count)} users
                    ${searchInfo.search_term ? ` matching "${escapeHtml(searchInfo.search_term)}"` : ''}
                </div>
            `).show();
        } else {
            $searchInfo.hide();
        }
    }
    
    function applyFilters() {
        currentFilter = $('#activity-filter').val();
        currentDateFrom = $('#date-from').val();
        currentDateTo = $('#date-to').val();
        currentPage = 1;
        
        loadBuddyPressStats();
        showSuccessMessage('Filters applied successfully!');
    }
    
    function exportBuddyPressData() {
        const $button = $('#export-buddypress-stats');
        $button.prop('disabled', true).text('Exporting...');
        
        // Create a form and submit it to trigger download
        const form = $('<form>', {
            method: 'POST',
            action: wbcomReports.ajaxurl
        });
        
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'export_buddypress_stats' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: wbcomReports.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'filter', value: currentFilter }));
        form.append($('<input>', { type: 'hidden', name: 'date_from', value: currentDateFrom }));
        form.append($('<input>', { type: 'hidden', name: 'date_to', value: currentDateTo }));
        form.append($('<input>', { type: 'hidden', name: 'search', value: currentSearch }));
        form.append($('<input>', { type: 'hidden', name: 'sort_by', value: currentSortBy }));
        form.append($('<input>', { type: 'hidden', name: 'sort_order', value: currentSortOrder }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        // Reset button after delay
        setTimeout(function() {
            $button.prop('disabled', false).text('Export CSV');
        }, 2000);
        
        showSuccessMessage('Export started! Your download should begin shortly.');
    }
    
    function showLoadingState() {
        $('.stat-number').text('Loading...');
        $('#user-activity-stats-table tbody').html(`
            <tr>
                <td colspan="6" class="text-center wbcom-loading">
                    Loading user activity data...
                </td>
            </tr>
        `);
        $('.wbcom-pagination').hide();
        $('#search-info').hide();
    }
    
    function hideLoadingState() {
        $('#refresh-buddypress-stats').prop('disabled', false).text('Refresh Stats');
    }
    
    function updateStatBox(selector, value) {
        const $element = $(selector);
        $element.fadeOut(200, function() {
            $element.text(formatNumber(value)).fadeIn(200);
        });
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
    
    function addTableHoverEffects() {
        $('#user-activity-stats-table tbody tr').hover(
            function() {
                $(this).addClass('table-row-hover');
            },
            function() {
                $(this).removeClass('table-row-hover');
            }
        );
    }
    
    function getLastLoginClass(lastLogin) {
        if (lastLogin === 'Never') return 'text-danger';
        
        const loginDate = new Date(lastLogin);
        const now = new Date();
        const daysDiff = (now - loginDate) / (1000 * 60 * 60 * 24);
        
        if (daysDiff <= 7) return 'text-success';
        if (daysDiff <= 30) return 'text-warning';
        return 'text-danger';
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
            return date.toLocaleDateString();
        } catch (e) {
            return dateString;
        }
    }
    
    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Handle notice dismissal
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    // Add dynamic styles
    if (!$('#buddypress-dynamic-styles').length) {
        $('head').append(`
            <style id="buddypress-dynamic-styles">
                .table-row-hover {
                    background-color: #e8f4f8 !important;
                    transform: scale(1.01);
                    transition: all 0.2s ease;
                }
                .activity-count {
                    font-weight: bold;
                }
                .no-data-message {
                    padding: 40px 20px;
                    color: #666;
                }
                .fade-in {
                    animation: fadeIn 0.5s ease-in forwards;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                
                /* Index Status Styles */
                .wbcom-index-status {
                    background: #f8f9fa;
                    border: 1px solid #e1e1e1;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .index-info {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                }
                .index-info p {
                    margin: 0;
                    color: #555;
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
                
                /* Search and Sort Styles */
                .search-controls {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 15px;
                    flex-wrap: wrap;
                }
                .search-input-wrapper {
                    position: relative;
                    flex: 1;
                    min-width: 250px;
                }
                .search-input-wrapper input {
                    padding-right: 30px;
                }
                .search-clear {
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    color: #666;
                    cursor: pointer;
                    font-size: 14px;
                }
                .search-clear:hover {
                    color: #dc3232;
                }
                .search-results-info {
                    background: #e7f3ff;
                    border: 1px solid #0073aa;
                    padding: 8px 12px;
                    border-radius: 3px;
                    margin-bottom: 15px;
                    font-size: 13px;
                }
                
                /* Sortable headers */
                .sortable-header {
                    cursor: pointer;
                    user-select: none;
                    position: relative;
                    transition: background-color 0.2s ease;
                }
                .sortable-header:hover {
                    background-color: #f0f0f0;
                }
                .sort-indicator {
                    margin-left: 5px;
                    font-weight: bold;
                    color: #0073aa;
                }
                .sortable-header.sorted-asc,
                .sortable-header.sorted-desc {
                    background-color: #f8f9fa;
                }
                
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
                
                /* Mobile Responsiveness */
                @media (max-width: 768px) {
                    .index-info {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }
                    .search-controls {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .search-input-wrapper {
                        min-width: 100%;
                    }
                }
            </style>
        `);
    }
});