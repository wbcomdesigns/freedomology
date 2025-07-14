/**
 * Enhanced LearnDash Reports JavaScript with Indexing Support and Advanced Group Analytics
 * 
 * @package Wbcom_Reports
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // State management
    let currentTab = 'user-progress';
    let currentFilter = 'all';
    let currentCourseFilter = 'all';
    let currentSearch = '';
    let currentSortBy = 'enrolled_courses';
    let currentSortOrder = 'desc';
    let completionChart = null;
    let isIndexing = false;
    
    // Group analytics state
    let currentGroupPage = 1;
    let currentGroupPerPage = 25;
    let currentActivityLevelFilter = 'all';
    let currentSizeFilter = 'all';
    let currentPerformanceFilter = 'all';
    
    // Initialize LearnDash reports
    initLearnDashReports();
    
    function initLearnDashReports() {
        loadLearnDashStats();
        bindEvents();
        initTabs();
        checkIndexStatus();
    }
    
    function bindEvents() {
        // Refresh stats button
        $('#refresh-learndash-stats').on('click', function() {
            $(this).prop('disabled', true).text('Refreshing...');
            loadLearnDashStats();
        });
        
        // Export CSV button
        $('#export-learndash-stats').on('click', function() {
            exportLearnDashData();
        });
        
        // Rebuild index button
        $('#rebuild-ld-index').on('click', function() {
            rebuildLearnDashIndex();
        });
        
        // Tab switching
        $('.tab-button').on('click', function() {
            const tabId = $(this).data('tab');
            switchTab(tabId);
        });
        
        // Apply learning filters
        $('#apply-learning-filters').on('click', function() {
            applyLearningFilters();
        });
        
        // Search functionality for user progress
        $('#user-search').on('input', debounce(function() {
            currentSearch = $(this).val().trim();
            if (currentTab === 'user-progress') {
                loadLearnDashStats();
            }
        }, 500));
        
        // Clear search button
        $('#clear-search').on('click', function() {
            $('#user-search').val('');
            currentSearch = '';
            if (currentTab === 'user-progress') {
                loadLearnDashStats();
            }
        });
        
        // Sort functionality for user progress
        $('.sortable-header').on('click', function() {
            if (currentTab !== 'user-progress') return;
            
            const sortBy = $(this).data('sort');
            
            if (currentSortBy === sortBy) {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortBy = sortBy;
                currentSortOrder = 'desc';
            }
            
            updateSortIndicators();
            loadLearnDashStats();
        });
        
        // Filter change events
        $('#progress-filter').on('change', function() {
            currentFilter = $(this).val();
        });
        
        $('#course-filter').on('change', function() {
            currentCourseFilter = $(this).val();
        });
        
        $('#group-filter').on('change', function() {
            currentFilter = $(this).val();
        });
        
        // Apply group filters
        $('#apply-group-filters').on('click', function() {
            applyGroupFilters();
        });
        
        // Enhanced group analytics filters
        $('#activity-level-filter, #size-filter, #performance-filter').on('change', function() {
            currentActivityLevelFilter = $('#activity-level-filter').val();
            currentSizeFilter = $('#size-filter').val();
            currentPerformanceFilter = $('#performance-filter').val();
        });
        
        $('#apply-group-filters').on('click', function() {
            loadFilteredGroups();
        });
        
        $('#export-group-insights').on('click', function() {
            exportGroupInsights();
        });
        
        // Group table pagination
        $('#prev-groups-page').on('click', function() {
            if (currentGroupPage > 1) {
                currentGroupPage--;
                loadFilteredGroups();
            }
        });
        
        $('#next-groups-page').on('click', function() {
            currentGroupPage++;
            loadFilteredGroups();
        });
        
        $('#groups-per-page').on('change', function() {
            currentGroupPerPage = parseInt($(this).val());
            currentGroupPage = 1;
            loadFilteredGroups();
        });
        
        // Bind group details events
        bindGroupDetailsEvents();
    }
    
    function bindGroupDetailsEvents() {
        // Group details pagination
        $('#prev-groups-page').on('click', function() {
            if (currentGroupPage > 1) {
                currentGroupPage--;
                loadGroupDetailsTable();
            }
        });
        
        $('#next-groups-page').on('click', function() {
            currentGroupPage++;
            loadGroupDetailsTable();
        });
        
        $('#groups-per-page').on('change', function() {
            currentGroupPerPage = parseInt($(this).val());
            currentGroupPage = 1;
            loadGroupDetailsTable();
        });
        
        // Filter change events for group details
        $('#activity-level-filter, #size-filter, #performance-filter').on('change', function() {
            currentActivityLevelFilter = $('#activity-level-filter').val();
            currentSizeFilter = $('#size-filter').val();
            currentPerformanceFilter = $('#performance-filter').val();
            currentGroupPage = 1; // Reset to first page when filters change
        });
        
        // Apply filters button
        $('#apply-group-filters').on('click', function() {
            loadGroupDetailsTable();
        });
        
        // Sortable headers for group details table
        $('#group-details-table .sortable').on('click', function() {
            const sortBy = $(this).data('sort');
            
            if (currentSortBy === sortBy) {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortBy = sortBy;
                currentSortOrder = 'desc';
            }
            
            updateGroupDetailsSortIndicators();
            currentGroupPage = 1;
            loadGroupDetailsTable();
        });
    }
    
    function loadGroupDetailsTable() {
        showGroupDetailsLoading();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_filtered_groups',
                nonce: wbcomReports.nonce,
                page: currentGroupPage,
                per_page: currentGroupPerPage,
                activity_level: currentActivityLevelFilter,
                size_filter: currentSizeFilter,
                performance_filter: currentPerformanceFilter,
                sort_by: currentSortBy,
                sort_order: currentSortOrder
            },
            success: function(response) {
                if (response.success) {
                    updateGroupDetailsTable(response.data.groups);
                    updateGroupDetailsPagination(response.data.pagination);
                } else {
                    showGroupDetailsError('Failed to load group details: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showGroupDetailsError('Error loading group details: ' + error);
            },
            complete: function() {
                hideGroupDetailsLoading();
            }
        });
    }
    
    function updateGroupDetailsTable(groups) {
        const $tableBody = $('#group-details-table tbody');
        $tableBody.empty();
        
        if (groups && groups.length > 0) {
            groups.forEach(function(group, index) {
                const statusClass = getGroupStatusClass(group.activity_level || 'inactive');
                const activityLevelLabel = getActivityLevelLabel(group.activity_level || 'inactive');
                
                const row = `
                    <tr class="fade-in" style="animation-delay: ${index * 0.05}s">
                        <td>
                            <strong>${escapeHtml(group.group_name)}</strong>
                            <br><small class="text-muted">ID: ${group.group_id}</small>
                        </td>
                        <td>
                            <span class="group-users-count font-bold">
                                ${formatNumber(group.total_users)}
                            </span>
                        </td>
                        <td>
                            <span class="active-users-count text-success">
                                ${formatNumber(group.active_users_30d || 0)}
                            </span>
                        </td>
                        <td>
                            <span class="activity-rate-badge ${getActivityRateClass(group.activity_rate)}">
                                ${group.activity_rate}%
                            </span>
                        </td>
                        <td>
                            <span class="completion-rate-badge ${getCompletionRateClass(group.completion_rate)}">
                                ${group.completion_rate}%
                            </span>
                        </td>
                        <td>
                            <span class="courses-count">
                                ${formatNumber(group.associated_courses || 0)}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                ${activityLevelLabel}
                            </span>
                        </td>
                        <td>
                            <button class="button button-small view-group-details" 
                                    data-group-id="${group.group_id}" 
                                    data-group-name="${escapeHtml(group.group_name)}">
                                View Details
                            </button>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
            
            // Bind click events for view details buttons
            $('.view-group-details').on('click', function() {
                const groupId = $(this).data('group-id');
                const groupName = $(this).data('group-name');
                showGroupDrillDown(groupId, groupName);
            });
            
        } else {
            const noDataMessage = getNoGroupsMessage();
            $tableBody.append(`
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="no-data-message">
                            ${noDataMessage}
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    function updateGroupDetailsPagination(pagination) {
        if (!pagination) return;
        
        const { current_page, total_pages, total_groups, per_page } = pagination;
        
        // Update page info
        $('#groups-page-info').text(`Page ${current_page} of ${total_pages} (${formatNumber(total_groups)} groups)`);
        
        // Update button states
        $('#prev-groups-page').prop('disabled', current_page <= 1);
        $('#next-groups-page').prop('disabled', current_page >= total_pages);
        
        // Update current page variable
        currentGroupPage = current_page;
        
        // Show/hide pagination if needed
        if (total_pages <= 1) {
            $('.table-pagination').hide();
        } else {
            $('.table-pagination').show();
        }
    }
    
    function getActivityLevelLabel(activityLevel) {
        const labels = {
            'very_active': 'Very Active',
            'active': 'Active',
            'moderate': 'Moderate',
            'inactive': 'Needs Attention'
        };
        return labels[activityLevel] || 'Unknown';
    }
    
    function getActivityRateClass(rate) {
        const numRate = parseFloat(rate);
        if (numRate >= 70) return 'rate-excellent';
        if (numRate >= 40) return 'rate-good';
        if (numRate >= 20) return 'rate-average';
        return 'rate-poor';
    }
    
    function getCompletionRateClass(rate) {
        const numRate = parseFloat(rate);
        if (numRate >= 80) return 'completion-excellent';
        if (numRate >= 60) return 'completion-good';
        if (numRate >= 40) return 'completion-average';
        return 'completion-poor';
    }
    
    function getNoGroupsMessage() {
        const activeFilters = [];
        if (currentActivityLevelFilter !== 'all') activeFilters.push('activity level');
        if (currentSizeFilter !== 'all') activeFilters.push('size');
        if (currentPerformanceFilter !== 'all') activeFilters.push('performance');
        
        if (activeFilters.length > 0) {
            return `
                <p><em>No groups found matching the selected ${activeFilters.join(', ')} filter(s).</em></p>
                <p><small>Try adjusting your filters to see more groups.</small></p>
            `;
        } else {
            return `
                <p><em>No LearnDash groups found.</em></p>
                <p><small>Create groups in LearnDash and enroll users to see analytics here.</small></p>
                <p><small>Use LearnDash Testing Toolkit to generate test groups for demo purposes.</small></p>
            `;
        }
    }
    
    function updateGroupDetailsSortIndicators() {
        // Remove all sort indicators
        $('#group-details-table .sortable .sort-indicator').remove();
        
        // Add indicator to current sort column
        const $currentHeader = $(`#group-details-table .sortable[data-sort="${currentSortBy}"]`);
        const indicator = currentSortOrder === 'asc' ? '↑' : '↓';
        $currentHeader.append(`<span class="sort-indicator">${indicator}</span>`);
        
        // Update header classes
        $('#group-details-table .sortable').removeClass('sorted-asc sorted-desc');
        $currentHeader.addClass(`sorted-${currentSortOrder}`);
    }
    
    function showGroupDetailsLoading() {
        $('#group-details-table tbody').html(`
            <tr>
                <td colspan="8" class="text-center wbcom-loading">
                    Loading group details...
                </td>
            </tr>
        `);
        $('.table-pagination').hide();
    }
    
    function hideGroupDetailsLoading() {
        // Loading state will be replaced by updateGroupDetailsTable
    }
    
    function showGroupDetailsError(message) {
        $('#group-details-table tbody').html(`
            <tr>
                <td colspan="8" class="text-center">
                    <div class="notice notice-error inline">
                        <p>${escapeHtml(message)}</p>
                    </div>
                </td>
            </tr>
        `);
    }
    
    function checkIndexStatus() {
        const $rebuildButton = $('#rebuild-ld-index');
        if ($rebuildButton.hasClass('needs-rebuild')) {
            showIndexWarning();
        }
    }
    
    function showIndexWarning() {
        const warningHtml = `
            <div class="notice notice-warning is-dismissible index-warning">
                <p>
                    <strong>Learning Performance Notice:</strong> 
                    The learning index needs rebuilding for optimal performance. 
                    <a href="#" id="rebuild-ld-index-link">Rebuild now</a> to improve loading times.
                </p>
            </div>
        `;
        
        $('.wbcom-stats-container').prepend(warningHtml);
        
        $('#rebuild-ld-index-link').on('click', function(e) {
            e.preventDefault();
            rebuildLearnDashIndex();
        });
    }
    
    function rebuildLearnDashIndex() {
        if (isIndexing) {
            return;
        }
        
        const $button = $('#rebuild-ld-index');
        const originalText = $button.text();
        
        isIndexing = true;
        $button.prop('disabled', true).text('Rebuilding Learning Index...');
        
        showIndexProgress();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'rebuild_ld_index',
                nonce: wbcomReports.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);
                    
                    updateIndexStatus(response.data.indexed_count);
                    $('.index-warning').fadeOut();
                    $button.removeClass('needs-rebuild');
                    
                    setTimeout(() => {
                        loadLearnDashStats();
                    }, 1000);
                    
                } else {
                    showErrorMessage('Failed to rebuild learning index: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error rebuilding learning index: ' + error);
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
            <div id="ld-index-progress" class="wbcom-index-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p>Rebuilding learning index... This may take a few moments for large sites with many learners.</p>
            </div>
        `;
        
        $('.wbcom-stats-actions').after(progressHtml);
        
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            
            $('#ld-index-progress .progress-fill').css('width', progress + '%');
        }, 500);
        
        $('#ld-index-progress').data('interval', interval);
    }
    
    function hideIndexProgress() {
        const $progress = $('#ld-index-progress');
        const interval = $progress.data('interval');
        
        if (interval) {
            clearInterval(interval);
        }
        
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
    
    function initTabs() {
        switchTab('user-progress');
        updateSortIndicators();
    }
    
    function switchTab(tabId) {
        $('.tab-button').removeClass('active');
        $(`.tab-button[data-tab="${tabId}"]`).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${tabId}`).addClass('active');
        
        currentTab = tabId;
        
        if (tabId === 'user-progress') {
            $('.search-controls').show();
            updateSortIndicators();
        } else {
            $('.search-controls').hide();
        }
        
        loadLearnDashStats();
        
        // Load group analytics if switching to group reports
        if (tabId === 'group-reports') {
            loadGroupAnalytics();
            // Load the group details table after other data loads
            setTimeout(() => {
                loadGroupDetailsTable();
            }, 1000);
        }
    }
    
    function updateSortIndicators() {
        if (currentTab !== 'user-progress') return;
        
        $('.sortable-header .sort-indicator').remove();
        
        const $currentHeader = $(`.sortable-header[data-sort="${currentSortBy}"]`);
        const indicator = currentSortOrder === 'asc' ? '↑' : '↓';
        $currentHeader.append(`<span class="sort-indicator">${indicator}</span>`);
        
        $('.sortable-header').removeClass('sorted-asc sorted-desc');
        $currentHeader.addClass(`sorted-${currentSortOrder}`);
    }
    
    function loadLearnDashStats() {
        showLoadingState();
        
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_learndash_stats',
                nonce: wbcomReports.nonce,
                tab: currentTab,
                filter: currentFilter,
                course_id: currentCourseFilter,
                search: currentSearch,
                sort_by: currentSortBy,
                sort_order: currentSortOrder
            },
            success: function(response) {
                if (response.success) {
                    updateLearnDashDisplay(response.data);
                    
                    if (response.data.using_index) {
                        showPerformanceIndicator();
                    }
                } else {
                    if (response.data && response.data.includes('LearnDash not active')) {
                        showLearnDashNotActiveMessage();
                    } else {
                        showErrorMessage('Failed to load LearnDash stats: ' + (response.data || 'Unknown error'));
                    }
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error loading LearnDash stats: ' + error);
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
            
            setTimeout(() => {
                $('.performance-indicator').fadeOut();
            }, 3000);
        }
    }
    
    function updateLearnDashDisplay(data) {
        updateStatBox('#total-courses', data.total_courses || 0);
        updateStatBox('#total-lessons', data.total_lessons || 0);
        updateStatBox('#active-learners', data.active_learners || 0);
        updateStatBox('#completed-courses', data.completed_courses || 0);
        
        updateCourseFilter(data.courses_list || []);
        
        switch (currentTab) {
            case 'user-progress':
                updateUserProgressTable(data.user_stats || []);
                updateSearchInfo(data.search_info);
                break;
            case 'course-analytics':
                updateCourseAnalytics(data.course_analytics || []);
                break;
            case 'group-reports':
                updateGroupAnalytics(data.group_analytics || []);
                break;
        }
    }
    
    function updateCourseFilter(coursesList) {
        const $courseFilter = $('#course-filter');
        const currentValue = $courseFilter.val();
        
        $courseFilter.empty().append('<option value="all">All Courses</option>');
        
        if (coursesList && coursesList.length > 0) {
            coursesList.forEach(function(course) {
                $courseFilter.append(`<option value="${course.id}">${escapeHtml(course.title)}</option>`);
            });
        }
        
        if (currentValue && $courseFilter.find(`option[value="${currentValue}"]`).length) {
            $courseFilter.val(currentValue);
        }
    }
    
    function updateUserProgressTable(userStats) {
        const $tableBody = $('#user-learning-stats-table tbody');
        $tableBody.empty();
        
        if (userStats && userStats.length > 0) {
            userStats.forEach(function(user, index) {
                const progressClass = getProgressClass(user.avg_progress);
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
                        <td>
                            <span class="course-count font-bold">
                                ${formatNumber(user.enrolled_courses)}
                            </span>
                        </td>
                        <td>
                            <span class="completion-count text-success font-bold">
                                ${formatNumber(user.completed_courses)}
                            </span>
                        </td>
                        <td>
                            <span class="progress-count text-warning">
                                ${formatNumber(user.in_progress)}
                            </span>
                        </td>
                        <td>
                            <div class="avg-progress">
                                <span class="progress-percentage ${progressClass}">
                                    ${user.avg_progress}
                                </span>
                                <div class="progress-bar">
                                    <div class="progress-fill ${progressClass}" 
                                         style="width: ${user.avg_progress}">
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="${getActivityClass(user.last_activity)}">
                                ${formatDate(user.last_activity)}
                            </small>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
            
        } else {
            const noDataMessage = currentSearch ? 
                `No learners found matching "${currentSearch}". Try adjusting your search or filters.` :
                'No learning data found with the current filters. Users need to be enrolled in courses and have progress data.';
                
            $tableBody.append(`
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="no-data-message">
                            <p><em>${noDataMessage}</em></p>
                            ${currentSearch ? '<p><small>Clear the search to see all learners.</small></p>' : 
                              '<p><small>Try switching to "All Learners" filter or create test data using LearnDash Testing Toolkit.</small></p>'}
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    function updateSearchInfo(searchInfo) {
        if (!searchInfo || currentTab !== 'user-progress') return;
        
        const $searchInfo = $('#search-info');
        if (searchInfo.active_search) {
            $searchInfo.html(`
                <div class="search-results-info">
                    <strong>Search Results:</strong> Found ${formatNumber(searchInfo.filtered_count)} 
                    of ${formatNumber(searchInfo.total_count)} learners
                    ${searchInfo.search_term ? ` matching "${escapeHtml(searchInfo.search_term)}"` : ''}
                </div>
            `).show();
        } else {
            $searchInfo.hide();
        }
    }
    
    function updateCourseAnalytics(courseAnalytics) {
        const $tableBody = $('#course-analytics-table tbody');
        $tableBody.empty();
        
        if (courseAnalytics && courseAnalytics.length > 0) {
            courseAnalytics.forEach(function(course, index) {
                const row = `
                    <tr class="fade-in" style="animation-delay: ${index * 0.1}s">
                        <td>
                            <strong>${escapeHtml(course.course_name)}</strong>
                        </td>
                        <td>${formatNumber(course.enrolled_users)}</td>
                        <td>
                            <span class="text-success font-bold">
                                ${formatNumber(course.completed)}
                            </span>
                        </td>
                        <td>
                            <span class="text-warning">
                                ${formatNumber(course.in_progress)}
                            </span>
                        </td>
                        <td>
                            <span class="${getProgressClass(course.completion_rate)}">
                                ${course.completion_rate}
                            </span>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
            
            createCourseCompletionChart(courseAnalytics);
            
        } else {
            $tableBody.append(`
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="no-data-message">
                            <p><em>No course analytics data available.</em></p>
                            <p><small>Make sure LearnDash courses exist and users are enrolled.</small></p>
                            <p><small>Create test data using LearnDash Testing Toolkit for demo purposes.</small></p>
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    function updateGroupAnalytics(groupAnalytics) {
        const $tableBody = $('#group-analytics-table tbody');
        $tableBody.empty();
        
        if (groupAnalytics && groupAnalytics.length > 0) {
            groupAnalytics.forEach(function(group, index) {
                const statusClass = getGroupStatusClass(group.status);
                
                const row = `
                    <tr class="fade-in" style="animation-delay: ${index * 0.1}s">
                        <td>
                            <strong>${escapeHtml(group.group_name)}</strong>
                        </td>
                        <td>
                            <small>${escapeHtml(group.group_leaders)}</small>
                        </td>
                        <td>
                            <span class="user-count font-bold text-primary">
                                ${formatNumber(group.total_users)}
                            </span>
                        </td>
                        <td>
                            <span class="course-count text-success">
                                ${formatNumber(group.associated_courses)}
                            </span>
                        </td>
                        <td>
                            <span class="${getProgressClass(group.avg_progress)}">
                                ${group.avg_progress}
                            </span>
                        </td>
                        <td>
                            <span class="completed-count text-success font-bold">
                                ${formatNumber(group.completed_users)}
                            </span>
                        </td>
                        <td>
                            <small>${formatDate(group.created_date)}</small>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                ${escapeHtml(group.status)}
                            </span>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
            
            createGroupEnrollmentChart(groupAnalytics);
            
        } else {
            $tableBody.append(`
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="no-data-message">
                            <p><em>No group analytics data available.</em></p>
                            <p><small>Make sure LearnDash groups exist and users are enrolled.</small></p>
                            <p><small>Create test groups using LearnDash Testing Toolkit for demo purposes.</small></p>
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    // Enhanced Group Enrollment Chart - Top 25 Implementation
    function createGroupEnrollmentChart(data) {
        const ctx = document.getElementById('group-enrollment-chart');
        if (!ctx) return;
        
        if (window.groupChart) {
            window.groupChart.destroy();
        }
        
        if (!data || data.length === 0) {
            $(ctx).closest('.wbcom-chart-container').html(`
                <div class="text-center no-data-message" style="padding: 100px;">
                    <h3>No Group Data Available</h3>
                    <p>Create LearnDash groups and enroll users to see analytics here.</p>
                    <p><small>Use LearnDash Testing Toolkit to generate test groups and enrollments.</small></p>
                </div>
            `);
            return;
        }
        
        // SOLUTION: Cap at top 25 groups by members
        const top25Groups = data
            .sort((a, b) => (b.total_users || 0) - (a.total_users || 0))
            .slice(0, 25);
        
        // Show indicator if more groups exist
        const hasMoreGroups = data.length > 25;
        const remainingGroups = data.length - 25;
        
        const chartData = {
            labels: top25Groups.map(group => {
                const name = group.group_name.substring(0, 15);
                return name + (group.group_name.length > 15 ? '...' : '');
            }),
            datasets: [{
                label: 'Total Users',
                data: top25Groups.map(group => group.total_users || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2
            }, {
                label: 'Active Users (30d)',
                data: top25Groups.map(group => group.active_users_30d || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            }, {
                label: 'Completed Users',
                data: top25Groups.map(group => group.completed_users || 0),
                backgroundColor: 'rgba(46, 204, 113, 0.6)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 2
            }]
        };
        
        window.groupChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: `Top 25 LearnDash Groups by Members${hasMoreGroups ? ` (${remainingGroups} more groups available)` : ''}`,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return top25Groups[index].group_name; // Full name in tooltip
                            },
                            afterBody: function(context) {
                                const index = context[0].dataIndex;
                                const group = top25Groups[index];
                                return [
                                    `Completion Rate: ${group.completion_rate || '0%'}`,
                                    `Activity Rate: ${group.activity_rate || '0%'}`,
                                    `Associated Courses: ${group.associated_courses || 0}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Groups (Top 25 by Member Count)'
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Users'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
        
        // Add click handler for drill-down
        ctx.onclick = function(event) {
            const points = window.groupChart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
            if (points.length) {
                const firstPoint = points[0];
                const group = top25Groups[firstPoint.index];
                showGroupDrillDown(group.group_id, group.group_name);
            }
        };
    }
    
    // Load enhanced group analytics
    function loadGroupAnalytics() {
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_group_analytics',
                nonce: wbcomReports.nonce,
                include_trends: true,
                include_distribution: true
            },
            success: function(response) {
                if (response.success) {
                    updateGroupAnalyticsDisplay(response.data);
                } else {
                    showErrorMessage('Failed to load group analytics');
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error loading group analytics: ' + error);
            }
        });
    }
    
    function updateGroupAnalyticsDisplay(data) {
        // Update quick stats
        updateStatBox('.active-groups-count', data.active_groups_count);
        updateStatBox('.very-active-groups-count', data.very_active_groups_count);
        updateStatBox('.inactive-groups-count', data.inactive_groups_count);
        updateStatBox('.avg-completion-rate', data.avg_completion_rate + '%');
        
        // Create activity distribution chart
        createActivityDistributionChart(data.activity_distribution);
        
        // Create trends chart
        createGroupTrendsChart(data.trends_data);
        
        // Update insights lists
        updateTopPerformersList(data.top_performers);
        updateNeedAttentionList(data.need_attention);
        
        // Update main enrollment chart with top 25
        createGroupEnrollmentChart(data.top_25_groups);
    }
    
    function createActivityDistributionChart(distributionData) {
        const ctx = document.getElementById('group-activity-distribution-chart');
        if (!ctx) return;
        
        if (window.activityDistributionChart) {
            window.activityDistributionChart.destroy();
        }
        
        const data = {
            labels: ['Very Active (70%+)', 'Active (40-70%)', 'Moderate (20-40%)', 'Inactive (<20%)'],
            datasets: [{
                data: [
                    distributionData.very_active || 0,
                    distributionData.active || 0,
                    distributionData.moderate || 0,
                    distributionData.inactive || 0
                ],
                backgroundColor: [
                    '#27ae60', // Green for very active
                    '#2ecc71', // Light green for active  
                    '#f39c12', // Orange for moderate
                    '#e74c3c'  // Red for inactive
                ],
                borderWidth: 2
            }]
        };
        
        window.activityDistributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Groups by Activity Level'
                    },
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                                return `${context.label}: ${context.parsed} groups (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    function createGroupTrendsChart(trendsData) {
        const ctx = document.getElementById('group-trends-chart');
        if (!ctx) return;
        
        if (window.groupTrendsChart) {
            window.groupTrendsChart.destroy();
        }
        
        window.groupTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendsData.months,
                datasets: [{
                    label: 'Active Groups',
                    data: trendsData.active_groups,
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Avg Completion Rate',
                    data: trendsData.completion_rates,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Group Activity & Performance Trends'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Active Groups'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Completion Rate (%)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }
    
    function updateTopPerformersList(performers) {
        const $list = $('#top-performing-groups');
        $list.empty();
        
        if (performers && performers.length > 0) {
            performers.forEach(function(group, index) {
                const item = `
                    <div class="insight-item">
                        <div class="insight-rank">#${index + 1}</div>
                        <div class="insight-details">
                            <strong>${escapeHtml(group.group_name)}</strong>
                            <small>${group.total_users} users | ${group.completion_rate}% completion</small>
                        </div>
                        <div class="insight-score">${group.engagement_score}</div>
                    </div>
                `;
                $list.append(item);
            });
        } else {
            $list.append('<p><em>No top performers data available.</em></p>');
        }
    }
    
    function updateNeedAttentionList(groups) {
        const $list = $('#groups-need-attention');
        $list.empty();
        
        if (groups && groups.length > 0) {
            groups.forEach(function(group, index) {
                const item = `
                    <div class="insight-item attention-item">
                        <div class="insight-rank">⚠️</div>
                        <div class="insight-details">
                            <strong>${escapeHtml(group.group_name)}</strong>
                            <small>${group.total_users} users | ${group.completion_rate}% completion</small>
                        </div>
                        <div class="insight-score">${group.engagement_score}</div>
                    </div>
                `;
                $list.append(item);
            });
        } else {
            $list.append('<p><em>No groups need immediate attention.</em></p>');
        }
    }
    
    // Group drill-down modal
    function showGroupDrillDown(groupId, groupName) {
        $.ajax({
            url: wbcomReports.ajaxurl,
            type: 'POST', 
            data: {
                action: 'get_group_drilldown',
                nonce: wbcomReports.nonce,
                group_id: groupId
            },
            success: function(response) {
                if (response.success) {
                    displayGroupDrillDownModal(response.data, groupName);
                } else {
                    showErrorMessage('Failed to load group details');
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Error loading group details: ' + error);
            }
        });
    }
    
    function displayGroupDrillDownModal(data, groupName) {
        const modalHtml = `
            <div id="group-drilldown-modal" class="group-modal-overlay">
                <div class="group-modal-content">
                    <div class="group-modal-header">
                        <h2>Group Details: ${escapeHtml(groupName)}</h2>
                        <button class="group-modal-close">&times;</button>
                    </div>
                    <div class="group-modal-body">
                        <div class="group-stats-grid">
                            <div class="group-stat">
                                <label>Total Users:</label>
                                <span>${data.total_users || 0}</span>
                            </div>
                            <div class="group-stat">
                                <label>Active Users (30d):</label>
                                <span>${data.active_users || 0}</span>
                            </div>
                            <div class="group-stat">
                                <label>Completion Rate:</label>
                                <span>${data.completion_rate || '0%'}</span>
                            </div>
                            <div class="group-stat">
                                <label>Activity Level:</label>
                                <span class="activity-level-${data.activity_level}">${data.activity_level || 'Unknown'}</span>
                            </div>
                        </div>
                        <div class="group-details-tabs">
                            <!-- Additional group details would go here -->
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        $('#group-drilldown-modal .group-modal-close, #group-drilldown-modal').on('click', function(e) {
            if (e.target === this) {
                $('#group-drilldown-modal').remove();
            }
        });
    }
    
    function loadFilteredGroups() {
        // Implementation for loading filtered groups with pagination
        // This would call a new AJAX endpoint to get filtered group data
        loadGroupDetailsTable();
    }
    
    function exportGroupInsights() {
        const $button = $('#export-group-insights');
        $button.prop('disabled', true).text('Exporting...');
        
        const form = $('<form>', {
            method: 'POST',
            action: wbcomReports.ajaxurl
        });
        
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'export_group_insights' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: wbcomReports.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'activity_level', value: currentActivityLevelFilter }));
        form.append($('<input>', { type: 'hidden', name: 'size_filter', value: currentSizeFilter }));
        form.append($('<input>', { type: 'hidden', name: 'performance_filter', value: currentPerformanceFilter }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        setTimeout(function() {
            $button.prop('disabled', false).text('Export Insights');
        }, 2000);
        
        showSuccessMessage('Export started! Your download should begin shortly.');
    }
    
    function createCourseCompletionChart(data) {
        const ctx = document.getElementById('course-completion-chart');
        if (!ctx) return;
        
        if (completionChart) {
            completionChart.destroy();
        }
        
        if (!data || data.length === 0) {
            $(ctx).closest('.wbcom-chart-container').html(`
                <div class="text-center no-data-message" style="padding: 100px;">
                    <h3>No Course Data Available</h3>
                    <p>Create courses and enroll users to see analytics here.</p>
                    <p><small>Use LearnDash Testing Toolkit to generate test data.</small></p>
                </div>
            `);
            return;
        }
        
        const chartData = {
            labels: data.map(course => course.course_name.substring(0, 20) + (course.course_name.length > 20 ? '...' : '')),
            datasets: [{
                label: 'Enrolled',
                data: data.map(course => course.enrolled_users || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2
            }, {
                label: 'Completed',
                data: data.map(course => course.completed || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            }, {
                label: 'In Progress',
                data: data.map(course => course.in_progress || 0),
                backgroundColor: 'rgba(255, 206, 86, 0.5)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 2
            }]
        };
        
        completionChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Course Enrollment vs Completion'
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    function applyLearningFilters() {
        currentFilter = $('#progress-filter').val();
        currentCourseFilter = $('#course-filter').val();
        
        loadLearnDashStats();
        showSuccessMessage('Learning filters applied successfully!');
    }
    
    function applyGroupFilters() {
        currentFilter = $('#group-filter').val();
        loadLearnDashStats();
        showSuccessMessage('Group filters applied successfully!');
    }
    
    function exportLearnDashData() {
        const $button = $('#export-learndash-stats');
        $button.prop('disabled', true).text('Exporting...');
        
        const form = $('<form>', {
            method: 'POST',
            action: wbcomReports.ajaxurl
        });
        
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'export_learndash_stats' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: wbcomReports.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'tab', value: currentTab }));
        form.append($('<input>', { type: 'hidden', name: 'filter', value: currentFilter }));
        form.append($('<input>', { type: 'hidden', name: 'course_id', value: currentCourseFilter }));
        form.append($('<input>', { type: 'hidden', name: 'search', value: currentSearch }));
        form.append($('<input>', { type: 'hidden', name: 'sort_by', value: currentSortBy }));
        form.append($('<input>', { type: 'hidden', name: 'sort_order', value: currentSortOrder }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        setTimeout(function() {
            $button.prop('disabled', false).text('Export CSV');
        }, 2000);
        
        showSuccessMessage('Export started! Your download should begin shortly.');
    }
    
    function showLearnDashNotActiveMessage() {
        const $container = $('.wbcom-stats-container');
        const messageHtml = `
            <div class="notice notice-warning">
                <h3>LearnDash Not Active</h3>
                <p>LearnDash plugin is not active or not detected. To view learning reports:</p>
                <ul>
                    <li>Make sure LearnDash is installed and activated</li>
                    <li>Check if the LearnDash Testing Toolkit has created test data</li>
                    <li>Verify that courses and users exist in your system</li>
                </ul>
                <p><strong>For testing:</strong> Use the LearnDash Testing Toolkit to create sample courses, users, and progress data.</p>
            </div>
        `;
        
        $container.html(messageHtml);
    }
    
    function getProgressClass(progressRate) {
        const rate = parseFloat(progressRate);
        if (rate >= 80) return 'progress-excellent';
        if (rate >= 60) return 'progress-good';
        if (rate >= 40) return 'progress-average';
        return 'progress-poor';
    }
    
    function getGroupStatusClass(status) {
        switch (status.toLowerCase()) {
            case 'active': return 'status-active';
            case 'empty': return 'status-empty';
            case 'no courses': return 'status-no-courses';
            case 'needs attention': return 'status-attention';
            default: return 'status-default';
        }
    }
    
    function getActivityClass(lastActivity) {
        if (lastActivity === 'Never') return 'text-danger';
        
        const activityDate = new Date(lastActivity);
        const now = new Date();
        const daysDiff = (now - activityDate) / (1000 * 60 * 60 * 24);
        
        if (daysDiff <= 7) return 'text-success';
        if (daysDiff <= 30) return 'text-warning';
        return 'text-danger';
    }
    
    function showLoadingState() {
        $('.stat-number').text('Loading...');
        
        if (currentTab === 'user-progress') {
            $('#user-learning-stats-table tbody').html(`
                <tr><td colspan="7" class="text-center wbcom-loading">Loading learning progress...</td></tr>
            `);
            $('#search-info').hide();
        } else if (currentTab === 'course-analytics') {
            $('#course-analytics-table tbody').html(`
                <tr><td colspan="5" class="text-center wbcom-loading">Loading course analytics...</td></tr>
            `);
        } else if (currentTab === 'group-reports') {
            $('#group-analytics-table tbody').html(`
                <tr><td colspan="8" class="text-center wbcom-loading">Loading group analytics...</td></tr>
            `);
        }
    }
    
    function hideLoadingState() {
        $('#refresh-learndash-stats').prop('disabled', false).text('Refresh Stats');
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
            return date.toLocaleDateString();
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
    
    // Add dynamic styles for LearnDash
    if (!$('#learndash-dynamic-styles').length) {
        $('head').append(`
            <style id="learndash-dynamic-styles">
                .avg-progress {
                    max-width: 150px;
                }
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #f0f0f0;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-top: 4px;
                }
                .progress-fill {
                    height: 100%;
                    transition: width 0.3s ease;
                }
                .progress-excellent { color: #46b450; }
                .progress-excellent.progress-fill { background: #46b450; }
                .progress-good { color: #00a32a; }
                .progress-good.progress-fill { background: #00a32a; }
                .progress-average { color: #ffb900; }
                .progress-average.progress-fill { background: #ffb900; }
                .progress-poor { color: #dc3232; }
                .progress-poor.progress-fill { background: #dc3232; }
                
                .course-count, .completion-count { font-size: 16px; }
                .no-data-message {
                    padding: 40px 20px;
                    color: #666;
                    line-height: 1.6;
                }
                .text-muted { color: #666; }
                
                /* Group status badges */
                .status-active { background: #46b450; color: white; }
                .status-empty { background: #dc3232; color: white; }
                .status-no-courses { background: #ffb900; color: white; }
                .status-attention { background: #e67e22; color: white; }
                .status-default { background: #666; color: white; }
                .status-badge {
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                /* Enhanced Group Analytics Styles */
                .insight-item {
                    display: flex;
                    align-items: center;
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                    transition: background-color 0.2s ease;
                }
                
                .insight-item:hover {
                    background-color: #f8f9fa;
                }
                
                .insight-item:last-child {
                    border-bottom: none;
                }
                
                .insight-rank {
                    font-weight: bold;
                    color: #0073aa;
                    margin-right: 10px;
                    min-width: 30px;
                }
                
                .insight-details {
                    flex: 1;
                }
                
                .insight-details strong {
                    display: block;
                    margin-bottom: 2px;
                }
                
                .insight-details small {
                    color: #666;
                    font-size: 12px;
                }
                
                .insight-score {
                    font-weight: bold;
                    color: #2ecc71;
                }
                
                .attention-item .insight-score {
                    color: #e74c3c;
                }
                
                /* Group Modal Styles */
                .group-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .group-modal-content {
                    background: white;
                    border-radius: 8px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                
                .group-modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .group-modal-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                }
                
                .group-modal-close:hover {
                    color: #dc3232;
                }
                
                .group-modal-body {
                    padding: 20px;
                }
                
                .group-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .group-stat {
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }
                
                .group-stat label {
                    display: block;
                    font-size: 12px;
                    color: #666;
                    margin-bottom: 5px;
                }
                
                .group-stat span {
                    font-weight: bold;
                    font-size: 16px;
                    color: #1d2327;
                }
                
                /* Activity Level Indicators */
                .activity-level-very_active {
                    color: #27ae60;
                    font-weight: bold;
                }
                
                .activity-level-active {
                    color: #2ecc71;
                    font-weight: bold;
                }
                
                .activity-level-moderate {
                    color: #f39c12;
                    font-weight: bold;
                }
                
                .activity-level-inactive {
                    color: #e74c3c;
                    font-weight: bold;
                }
                
                /* Index Status Styles */
                .wbcom-index-progress {
                    background: #fff;
                    border: 1px solid #0073aa;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 15px 0;
                    text-align: center;
                }
                .wbcom-index-progress .progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin-bottom: 10px;
                }
                .wbcom-index-progress .progress-fill {
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
                
                /* Fixed height for pie chart container */
                .chart-container-pie {
                    height: 400px;
                    max-height: 400px;
                    position: relative;
                    margin: 0 auto;
                    width: 100%;
                }
                
                /* Group Details Table Styles */
                .rate-excellent, .completion-excellent { 
                    background: #46b450; 
                    color: white; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 11px;
                    font-weight: 600;
                }
                .rate-good, .completion-good { 
                    background: #00a32a; 
                    color: white; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 11px;
                    font-weight: 600;
                }
                .rate-average, .completion-average { 
                    background: #ffb900; 
                    color: white; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 11px;
                    font-weight: 600;
                }
                .rate-poor, .completion-poor { 
                    background: #dc3232; 
                    color: white; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 11px;
                    font-weight: 600;
                }
                
                .group-users-count { 
                    font-size: 16px; 
                    color: #0073aa; 
                }
                
                .button-small {
                    padding: 4px 8px;
                    font-size: 11px;
                    height: auto;
                    line-height: 1.2;
                }
                
                .activity-rate-badge,
                .completion-rate-badge {
                    display: inline-block;
                    min-width: 40px;
                    text-align: center;
                }
                
                /* Table sortable headers for group details */
                #group-details-table .sortable {
                    cursor: pointer;
                    user-select: none;
                    position: relative;
                    transition: background-color 0.2s ease;
                }
                
                #group-details-table .sortable:hover {
                    background-color: #f0f0f0;
                }
                
                #group-details-table .sort-indicator {
                    margin-left: 5px;
                    font-weight: bold;
                    color: #0073aa;
                }
                
                #group-details-table .sortable.sorted-asc,
                #group-details-table .sortable.sorted-desc {
                    background-color: #f8f9fa;
                }
                
                /* Fade in animation for table rows */
                .fade-in {
                    animation: fadeInUp 0.5s ease-out forwards;
                }
                
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                /* Mobile Responsiveness */
                @media (max-width: 768px) {
                    .search-controls {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .search-input-wrapper {
                        min-width: 100%;
                    }
                    .group-modal-content {
                        width: 95%;
                    }
                    .group-stats-grid {
                        grid-template-columns: 1fr;
                    }
                    .chart-container-pie {
                        height: 300px;
                        max-height: 300px;
                    }
                    .chart-container {
                        height: 250px;
                        max-height: 250px;
                    }
                    
                    /* Make group details table responsive */
                    #group-details-table {
                        font-size: 12px;
                    }
                    
                    #group-details-table th,
                    #group-details-table td {
                        padding: 6px 4px;
                    }
                    
                    .button-small {
                        padding: 2px 4px;
                        font-size: 10px;
                    }
                    
                    .rate-excellent, .completion-excellent,
                    .rate-good, .completion-good,
                    .rate-average, .completion-average,
                    .rate-poor, .completion-poor {
                        padding: 1px 3px;
                        font-size: 9px;
                    }
                }
                
                @media (max-width: 480px) {
                    .group-quick-stats {
                        grid-template-columns: 1fr;
                    }
                    
                    .filter-controls {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    
                    .filter-controls select {
                        min-width: 100%;
                        margin-bottom: 5px;
                    }
                    
                    .table-pagination {
                        flex-direction: column;
                        text-align: center;
                        gap: 10px;
                    }
                    
                    .table-pagination button {
                        width: 100%;
                        max-width: 120px;
                    }
                    
                    #groups-per-page {
                        width: 100%;
                        max-width: 200px;
                    }
                }
            </style>
        `);
    }
});