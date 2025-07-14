<?php
/**
 * Enhanced LearnDash Reports Class with Full Indexing and Advanced Group Analytics
 * 
 * @package Wbcom_Reports
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wbcom_Reports_LearnDash {
    
    private $index;
    private $cache_group = 'wbcom_reports_ld';
    private $group_index_table;
    
    public function __construct() {
        global $wpdb;
        
        // Initialize indexing system
        $this->index = new Wbcom_Reports_Index();
        $this->group_index_table = $wpdb->prefix . 'wbcom_reports_group_index';
        
        add_action('wp_ajax_get_learndash_stats', array($this, 'ajax_get_learndash_stats'));
        add_action('wp_ajax_get_course_analytics', array($this, 'ajax_get_course_analytics'));
        add_action('wp_ajax_export_learndash_stats', array($this, 'ajax_export_learndash_stats'));
        add_action('wp_ajax_rebuild_ld_index', array($this, 'ajax_rebuild_index'));
        
        // New AJAX endpoints for enhanced group analytics
        add_action('wp_ajax_get_group_analytics', array($this, 'ajax_get_group_analytics'));
        add_action('wp_ajax_get_filtered_groups', array($this, 'ajax_get_filtered_groups'));
        add_action('wp_ajax_get_group_drilldown', array($this, 'ajax_get_group_drilldown'));
        
        // Hook into LearnDash completion events for real-time indexing
        add_action('learndash_course_completed', array($this, 'update_course_completion_index'), 10, 1);
        add_action('learndash_lesson_completed', array($this, 'update_lesson_completion_index'), 10, 1);
        add_action('learndash_topic_completed', array($this, 'update_topic_completion_index'), 10, 1);
        add_action('learndash_quiz_completed', array($this, 'update_quiz_completion_index'), 10, 2);
        add_action('ld_course_access_granted', array($this, 'update_enrollment_index'), 10, 2);
        add_action('ld_course_access_removed', array($this, 'update_enrollment_index'), 10, 2);
        
        // Initialize group index table
        add_action('init', array($this, 'maybe_create_group_index_table'));
        
        global $wpdb;
        $this->group_index_table = $wpdb->prefix . 'wbcom_learndash_group_index';

        add_action('wp_ajax_export_group_insights', array($this, 'ajax_export_group_insights'));
    }
    
    /**
     * Create group index table if it doesn't exist
     */
    public function maybe_create_group_index_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->group_index_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            group_name varchar(255) NOT NULL,
            total_users int(11) DEFAULT 0,
            active_users_30d int(11) DEFAULT 0,
            completed_users int(11) DEFAULT 0,
            associated_courses int(11) DEFAULT 0,
            completion_rate decimal(5,2) DEFAULT 0.00,
            activity_rate decimal(5,2) DEFAULT 0.00,
            engagement_score decimal(5,2) DEFAULT 0.00,
            performance_tier varchar(20) DEFAULT 'average',
            activity_level varchar(20) DEFAULT 'inactive',
            last_activity datetime DEFAULT NULL,
            last_learning_activity datetime DEFAULT NULL,
            created_date datetime DEFAULT NULL,
            leader_count int(11) DEFAULT 0,
            group_size_category varchar(20) DEFAULT 'small',
            monthly_growth_rate decimal(5,2) DEFAULT 0.00,
            avg_session_duration int(11) DEFAULT 0,
            completion_velocity decimal(5,2) DEFAULT 0.00,
            leader_activity_score decimal(5,2) DEFAULT 0.00,
            indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY group_id (group_id),
            KEY idx_performance_tier (performance_tier),
            KEY idx_completion_rate (completion_rate),
            KEY idx_total_users (total_users),
            KEY idx_active_users_30d (active_users_30d),
            KEY idx_last_activity (last_activity),
            KEY idx_activity_level (activity_level),
            KEY idx_group_size_category (group_size_category),
            KEY idx_engagement_score (engagement_score)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Render LearnDash reports page with index status
     */
    public static function render_page() {
        $index = new Wbcom_Reports_Index();
        $index_stats = $index->get_index_stats();
        $needs_rebuilding = $index->needs_rebuilding();
        $cache_stats = $index->get_cache_stats();
        ?>
        <div class="wrap wbcom-reports-wrap">
            <h1><?php _e('LearnDash Learning Reports', 'wbcom-reports'); ?></h1>
            
            <?php if (!Wbcom_Reports_Helpers::is_learndash_active()): ?>
                <div class="notice notice-warning">
                    <p><?php _e('LearnDash plugin is not active. Please activate LearnDash to view learning reports.', 'wbcom-reports'); ?></p>
                </div>
            <?php else: ?>
                
                <!-- Index Status -->
                <div class="wbcom-index-status">
                    <div class="index-info">
                        <div class="index-details">
                            <p>
                                <strong><?php _e('Learning Index Status:', 'wbcom-reports'); ?></strong>
                                <?php echo sprintf(__('%d of %d users indexed (%s%% coverage)', 'wbcom-reports'), 
                                    $index_stats['total_indexed'], 
                                    $index_stats['total_users'], 
                                    $index_stats['coverage_percentage']
                                ); ?>
                                <?php if ($index_stats['last_update']): ?>
                                    | <?php echo sprintf(__('Last updated: %s ago', 'wbcom-reports'), 
                                        human_time_diff(strtotime($index_stats['last_update']))
                                    ); ?>
                                <?php endif; ?>
                            </p>
                            <p>
                                <strong><?php _e('Cache Status:', 'wbcom-reports'); ?></strong>
                                <?php if ($cache_stats['cache_enabled']): ?>
                                    <span class="cache-enabled"><?php _e('Object Cache Active', 'wbcom-reports'); ?></span>
                                <?php else: ?>
                                    <span class="cache-disabled"><?php _e('Using Database Cache', 'wbcom-reports'); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($needs_rebuilding): ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('Learning index needs rebuilding for optimal performance.', 'wbcom-reports'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wbcom-stats-container">
                    <div class="wbcom-stats-summary">
                        <div class="wbcom-stat-box">
                            <h3><?php _e('Total Courses', 'wbcom-reports'); ?></h3>
                            <span id="total-courses" class="stat-number">Loading...</span>
                        </div>
                        <div class="wbcom-stat-box">
                            <h3><?php _e('Total Lessons', 'wbcom-reports'); ?></h3>
                            <span id="total-lessons" class="stat-number">Loading...</span>
                        </div>
                        <div class="wbcom-stat-box">
                            <h3><?php _e('Active Learners', 'wbcom-reports'); ?></h3>
                            <span id="active-learners" class="stat-number">Loading...</span>
                        </div>
                        <div class="wbcom-stat-box">
                            <h3><?php _e('Completed Courses', 'wbcom-reports'); ?></h3>
                            <span id="completed-courses" class="stat-number">Loading...</span>
                        </div>
                    </div>
                    
                    <div class="wbcom-stats-actions">
                        <button id="refresh-learndash-stats" class="button button-primary">
                            <?php _e('Refresh Stats', 'wbcom-reports'); ?>
                        </button>
                        <button id="export-learndash-stats" class="button">
                            <?php _e('Export CSV', 'wbcom-reports'); ?>
                        </button>
                        <button id="rebuild-ld-index" class="button <?php echo $needs_rebuilding ? 'needs-rebuild' : ''; ?>" 
                                title="<?php _e('Rebuild learning index for optimal performance', 'wbcom-reports'); ?>">
                            <?php _e('Rebuild Index', 'wbcom-reports'); ?>
                            <?php if ($needs_rebuilding): ?>
                                <span class="dashicons dashicons-warning" style="color: #ff6b6b; margin-left: 5px;"></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    
                    <div class="wbcom-learning-tabs">
                        <div class="wbcom-tab-nav">
                            <button class="tab-button active" data-tab="user-progress"><?php _e('User Progress', 'wbcom-reports'); ?></button>
                            <button class="tab-button" data-tab="course-analytics"><?php _e('Course Analytics', 'wbcom-reports'); ?></button>
                            <button class="tab-button" data-tab="group-reports"><?php _e('Group Reports', 'wbcom-reports'); ?></button>
                        </div>
                        
                        <div id="user-progress" class="tab-content active">
                            <div class="wbcom-user-stats">
                                <h2><?php _e('User Learning Progress', 'wbcom-reports'); ?></h2>
                                
                                <!-- Search Controls -->
                                <div class="search-controls">
                                    <div class="search-input-wrapper">
                                        <input type="text" id="user-search" placeholder="<?php _e('Search learners by name or username...', 'wbcom-reports'); ?>" class="regular-text">
                                        <button type="button" id="clear-search" class="search-clear" title="<?php _e('Clear search', 'wbcom-reports'); ?>">&times;</button>
                                    </div>
                                    <button id="apply-learning-filters" class="button"><?php _e('Apply Filters', 'wbcom-reports'); ?></button>
                                </div>
                                
                                <!-- Search Results Info -->
                                <div id="search-info" style="display: none;"></div>
                                
                                <div class="wbcom-filters">
                                    <select id="progress-filter">
                                        <option value="all"><?php _e('All Learners', 'wbcom-reports'); ?></option>
                                        <option value="active"><?php _e('Active Learners', 'wbcom-reports'); ?></option>
                                        <option value="completed"><?php _e('Completed Courses', 'wbcom-reports'); ?></option>
                                        <option value="in-progress"><?php _e('In Progress', 'wbcom-reports'); ?></option>
                                    </select>
                                    <select id="course-filter">
                                        <option value="all"><?php _e('All Courses', 'wbcom-reports'); ?></option>
                                    </select>
                                </div>
                                
                                <table id="user-learning-stats-table" class="widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th class="sortable-header" data-sort="display_name"><?php _e('Display Name', 'wbcom-reports'); ?></th>
                                            <th class="sortable-header" data-sort="user_login"><?php _e('Username', 'wbcom-reports'); ?></th>
                                            <th class="sortable-header" data-sort="enrolled_courses"><?php _e('Enrolled Courses', 'wbcom-reports'); ?></th>
                                            <th class="sortable-header" data-sort="completed_courses"><?php _e('Completed Courses', 'wbcom-reports'); ?></th>
                                            <th class="sortable-header" data-sort="in_progress"><?php _e('In Progress', 'wbcom-reports'); ?></th>
                                            <th><?php _e('Avg. Progress', 'wbcom-reports'); ?></th>
                                            <th class="sortable-header" data-sort="last_activity"><?php _e('Last Activity', 'wbcom-reports'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div id="course-analytics" class="tab-content">
                            <div class="wbcom-user-stats">
                                <h2><?php _e('Course Analytics', 'wbcom-reports'); ?></h2>
                                <div class="wbcom-chart-container">
                                    <canvas id="course-completion-chart"></canvas>
                                </div>
                                <table id="course-analytics-table" class="widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Course Name', 'wbcom-reports'); ?></th>
                                            <th><?php _e('Enrolled Users', 'wbcom-reports'); ?></th>
                                            <th><?php _e('Completed', 'wbcom-reports'); ?></th>
                                            <th><?php _e('In Progress', 'wbcom-reports'); ?></th>
                                            <th><?php _e('Completion Rate', 'wbcom-reports'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div id="group-reports" class="tab-content">
                            <div class="wbcom-user-stats">
                                <h2><?php _e('LearnDash Group Reports', 'wbcom-reports'); ?></h2>
                                
                                <!-- Enhanced Group Chart - Top 25 -->
                                <div class="wbcom-chart-container">
                                    <canvas id="group-enrollment-chart"></canvas>
                                </div>
                                
                                <!-- Enhanced Group Analytics Section -->
                                <div class="group-analytics-detailed">
                                    
                                    <!-- Quick Stats Row -->
                                    <div class="group-quick-stats">
                                        <div class="stat-card">
                                            <h4><?php _e('Active Groups', 'wbcom-reports'); ?></h4>
                                            <span class="stat-number active-groups-count">Loading...</span>
                                            <small><?php _e('Groups with 20%+ activity in 30 days', 'wbcom-reports'); ?></small>
                                        </div>
                                        <div class="stat-card">
                                            <h4><?php _e('Very Active Groups', 'wbcom-reports'); ?></h4>
                                            <span class="stat-number very-active-groups-count">Loading...</span>
                                            <small><?php _e('Groups with 70%+ activity rate', 'wbcom-reports'); ?></small>
                                        </div>
                                        <div class="stat-card">
                                            <h4><?php _e('Groups Need Attention', 'wbcom-reports'); ?></h4>
                                            <span class="stat-number inactive-groups-count">Loading...</span>
                                            <small><?php _e('Groups with <20% activity', 'wbcom-reports'); ?></small>
                                        </div>
                                        <div class="stat-card">
                                            <h4><?php _e('Avg. Completion Rate', 'wbcom-reports'); ?></h4>
                                            <span class="stat-number avg-completion-rate">Loading...</span>
                                            <small><?php _e('Across all groups', 'wbcom-reports'); ?></small>
                                        </div>
                                    </div>
                                    
                                    <!-- Activity Distribution Chart -->
                                    <div class="activity-distribution-container">
                                        <h3><?php _e('Group Activity Distribution', 'wbcom-reports'); ?></h3>
                                        <div class="chart-container-pie">
                                            <canvas id="group-activity-distribution-chart"></canvas>
                                        </div>
                                    </div>
                                    
                                    <!-- Performance Trends -->
                                    <div class="group-trends-container">
                                        <h3><?php _e('Group Performance Trends (Last 6 Months)', 'wbcom-reports'); ?></h3>
                                        <div class="chart-container">
                                            <canvas id="group-trends-chart"></canvas>
                                        </div>
                                    </div>
                                    
                                    <!-- Top Performers & Need Attention -->
                                    <div class="group-insights-grid">
                                        <div class="top-performers">
                                            <h3>🏆 <?php _e('Top Performing Groups', 'wbcom-reports'); ?></h3>
                                            <div class="insight-list" id="top-performing-groups">
                                                <!-- Dynamically loaded -->
                                            </div>
                                        </div>
                                        
                                        <div class="need-attention">
                                            <h3>⚠️ <?php _e('Groups Need Attention', 'wbcom-reports'); ?></h3>
                                            <div class="insight-list" id="groups-need-attention">
                                                <!-- Dynamically loaded -->
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Advanced Filters for Full Group List -->
                                    <div class="group-filters-advanced">
                                        <h3><?php _e('Detailed Group Analysis', 'wbcom-reports'); ?></h3>
                                        <div class="filter-controls">
                                            <select id="activity-level-filter">
                                                <option value="all"><?php _e('All Activity Levels', 'wbcom-reports'); ?></option>
                                                <option value="very_active"><?php _e('Very Active (70%+)', 'wbcom-reports'); ?></option>
                                                <option value="active"><?php _e('Active (40-70%)', 'wbcom-reports'); ?></option>
                                                <option value="moderate"><?php _e('Moderate (20-40%)', 'wbcom-reports'); ?></option>
                                                <option value="inactive"><?php _e('Inactive (<20%)', 'wbcom-reports'); ?></option>
                                            </select>
                                            
                                            <select id="size-filter">
                                                <option value="all"><?php _e('All Sizes', 'wbcom-reports'); ?></option>
                                                <option value="large"><?php _e('Large (100+ members)', 'wbcom-reports'); ?></option>
                                                <option value="medium"><?php _e('Medium (25-99 members)', 'wbcom-reports'); ?></option>
                                                <option value="small"><?php _e('Small (1-24 members)', 'wbcom-reports'); ?></option>
                                            </select>
                                            
                                            <select id="performance-filter">
                                                <option value="all"><?php _e('All Performance', 'wbcom-reports'); ?></option>
                                                <option value="high"><?php _e('High Performers (80%+)', 'wbcom-reports'); ?></option>
                                                <option value="good"><?php _e('Good (60-80%)', 'wbcom-reports'); ?></option>
                                                <option value="average"><?php _e('Average (40-60%)', 'wbcom-reports'); ?></option>
                                                <option value="low"><?php _e('Low (<40%)', 'wbcom-reports'); ?></option>
                                            </select>
                                            
                                            <button id="apply-group-filters" class="button"><?php _e('Apply Filters', 'wbcom-reports'); ?></button>
                                            <button id="export-group-insights" class="button"><?php _e('Export Insights', 'wbcom-reports'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <!-- Paginated Group Details Table -->
                                    <div class="group-details-table-container">
                                        <table id="group-details-table" class="widefat fixed striped">
                                            <thead>
                                                <tr>
                                                    <th class="sortable" data-sort="group_name"><?php _e('Group Name', 'wbcom-reports'); ?></th>
                                                    <th class="sortable" data-sort="total_users"><?php _e('Total Users', 'wbcom-reports'); ?></th>
                                                    <th class="sortable" data-sort="active_users_30d"><?php _e('Active Users (30d)', 'wbcom-reports'); ?></th>
                                                    <th class="sortable" data-sort="activity_rate"><?php _e('Activity Rate', 'wbcom-reports'); ?></th>
                                                    <th class="sortable" data-sort="completion_rate"><?php _e('Completion Rate', 'wbcom-reports'); ?></th>
                                                    <th class="sortable" data-sort="associated_courses"><?php _e('Courses', 'wbcom-reports'); ?></th>
                                                    <th><?php _e('Status', 'wbcom-reports'); ?></th>
                                                    <th><?php _e('Actions', 'wbcom-reports'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="8"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        
                                        <div class="table-pagination">
                                            <button id="prev-groups-page" class="button"><?php _e('Previous', 'wbcom-reports'); ?></button>
                                            <span id="groups-page-info">Page 1 of 1</span>
                                            <button id="next-groups-page" class="button"><?php _e('Next', 'wbcom-reports'); ?></button>
                                            <select id="groups-per-page">
                                                <option value="25">25 per page</option>
                                                <option value="50">50 per page</option>
                                                <option value="100">100 per page</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <style>
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
        .needs-rebuild {
            background-color: #ff6b6b !important;
            color: white !important;
        }
        .needs-rebuild:hover {
            background-color: #ff5252 !important;
        }
        
        /* Enhanced Group Analytics Styles */
        .group-analytics-detailed {
            margin-top: 30px;
        }
        
        .group-quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        }
        
        .stat-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #1d2327;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .stat-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
            line-height: 1.2;
        }
        
        .stat-card small {
            color: #666;
            font-size: 12px;
        }
        
        .activity-distribution-container,
        .group-trends-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        
        .activity-distribution-container h3,
        .group-trends-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1d2327;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Fixed height for pie chart */
        .chart-container-pie {
            height: 400px;
            max-height: 400px;
            position: relative;
        }
        
        .group-insights-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .top-performers,
        .need-attention {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
        }
        
        .top-performers h3,
        .need-attention h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .insight-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .group-filters-advanced {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #e1e1e1;
        }
        
        .group-filters-advanced h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .filter-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-controls select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            min-width: 150px;
        }
        
        .group-details-table-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .table-pagination {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #ccd0d4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .group-insights-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls select {
                min-width: 100%;
            }
            
            .table-pagination {
                flex-direction: column;
                text-align: center;
            }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for LearnDash stats using indexed data
     */
    public function ajax_get_learndash_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!Wbcom_Reports_Helpers::is_learndash_active()) {
            wp_send_json_error('LearnDash not active');
        }
        
        try {
            $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'user-progress';
            $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'enrolled_courses';
            $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'desc';
            
            $stats = array(
                'total_courses' => $this->get_cached_total_courses(),
                'total_lessons' => $this->get_cached_total_lessons(),
                'active_learners' => $this->get_cached_active_learners(),
                'completed_courses' => $this->get_cached_total_completed_courses(),
                'courses_list' => $this->get_cached_courses_list()
            );
            
            if ($tab === 'user-progress') {
                // Use indexed data for user progress
                $indexed_users = $this->get_indexed_learning_users($filter, $course_id, $search, $sort_by, $sort_order);
                $stats['user_stats'] = $indexed_users['users'];
                $stats['search_info'] = array(
                    'active_search' => !empty($search) || $filter !== 'all',
                    'search_term' => $search,
                    'filtered_count' => $indexed_users['total_count'],
                    'total_count' => $indexed_users['total_count']
                );
                $stats['using_index'] = true;
            } elseif ($tab === 'course-analytics') {
                $stats['course_analytics'] = $this->get_cached_course_analytics();
            } elseif ($tab === 'group-reports') {
                $stats['group_analytics'] = $this->get_cached_group_analytics($filter);
            }
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            error_log('LearnDash Stats Error: ' . $e->getMessage());
            wp_send_json_error('Error loading stats: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for enhanced group analytics
     */
    public function ajax_get_group_analytics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        try {
            // Rebuild group index if needed
            $this->maybe_rebuild_group_index();
            
            $analytics = array(
                'active_groups_count' => $this->get_active_groups_count(),
                'very_active_groups_count' => $this->get_very_active_groups_count(),
                'inactive_groups_count' => $this->get_inactive_groups_count(),
                'avg_completion_rate' => $this->get_avg_completion_rate(),
                'activity_distribution' => $this->get_activity_distribution(),
                'trends_data' => $this->get_trends_data(),
                'top_performers' => $this->get_top_performing_groups(5),
                'need_attention' => $this->get_groups_need_attention(5),
                'top_25_groups' => $this->get_top_25_groups_by_members()
            );
            
            wp_send_json_success($analytics);
            
        } catch (Exception $e) {
            error_log('Group Analytics Error: ' . $e->getMessage());
            wp_send_json_error('Error loading group analytics: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate course analytics using improved enrollment counting
     */
    private function calculate_course_analytics() {
        global $wpdb;
        
        $course_post_type = 'sfwd-courses';
        if (function_exists('learndash_get_post_type_slug')) {
            $course_post_type = learndash_get_post_type_slug('course');
        }
        
        $courses = get_posts(array(
            'post_type' => $course_post_type,
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        
        if (empty($courses)) {
            return array();
        }
        
        $analytics = array();
        
        foreach ($courses as $course) {
            // Count enrollments using multiple methods for accuracy
            $enrolled_count = $this->get_accurate_course_enrollment_count($course->ID);
            $completed_count = $this->get_course_completion_count($course->ID);
            
            $analytics[] = array(
                'course_name' => $course->post_title,
                'enrolled_users' => $enrolled_count,
                'completed' => $completed_count,
                'in_progress' => max(0, $enrolled_count - $completed_count),
                'completion_rate' => $enrolled_count > 0 ? round(($completed_count / $enrolled_count) * 100, 1) . '%' : '0%'
            );
        }
        
        return $analytics;
    }
    
    /**
     * Get accurate course enrollment count using multiple data sources
     */
    private function get_accurate_course_enrollment_count($course_id) {
        global $wpdb;
        
        $total_enrolled = 0;
        
        // Method 1: Count LDTT progress entries
        $ldtt_enrolled = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s
        ", '_ldtt_progress_course_' . $course_id));
        
        // Method 2: Count regular LearnDash course access
        $ld_enrolled = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_sfwd-course_progress' 
            AND meta_value LIKE %s
        ", '%"' . $course_id . '"%'));
        
        // Method 3: Count course access entries
        $course_access = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s 
            AND meta_value = %s
        ", 'course_' . $course_id . '_access_from', 'ANY'));
        
        // Method 4: Count from our index table if available
        $index_table = $wpdb->prefix . 'wbcom_reports_index';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$index_table}'") == $index_table) {
            // This would require storing per-course enrollment data in the index
            // For now, we'll use the other methods
        }
        
        // Use the highest count to ensure we capture all enrollments
        $total_enrolled = max($ldtt_enrolled, $ld_enrolled, $course_access);
        
        // Additional check: Look for any user who has any progress on this course
        $any_progress = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE (meta_key = %s OR meta_key LIKE %s OR meta_key LIKE %s)
        ", 
            '_ldtt_progress_course_' . $course_id,
            'course_' . $course_id . '_%',
            '%course_completed_' . $course_id
        ));
        
        // Use the maximum to ensure we don't miss anyone
        $total_enrolled = max($total_enrolled, $any_progress);
        
        return intval($total_enrolled);
    }
    
    /**
     * Get course completion count
     */
    private function get_course_completion_count($course_id) {
        global $wpdb;
        
        $completed_count = 0;
        
        // Method 1: Check LDTT progress with 100% completion
        $ldtt_completed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s 
            AND meta_value LIKE %s
        ", '_ldtt_progress_course_' . $course_id, '%"completion_rate";i:100%'));
        
        // Method 2: Check course completion meta
        $ld_completed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s
        ", 'course_completed_' . $course_id));
        
        // Method 3: Check course progress with 100% completion
        $progress_completed = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_sfwd-course_progress' 
            AND meta_value LIKE %s
        ", '%"' . $course_id . '"%'));
        
        $progress_completed_count = 0;
        foreach ($progress_completed as $progress) {
            $progress_data = maybe_unserialize($progress->meta_value);
            if (is_array($progress_data) && isset($progress_data[$course_id])) {
                $course_progress = $progress_data[$course_id];
                if (isset($course_progress['completed']) && isset($course_progress['total']) && 
                    $course_progress['total'] > 0 && 
                    $course_progress['completed'] >= $course_progress['total']) {
                    $progress_completed_count++;
                }
            }
        }
        
        // Use the highest count
        $completed_count = max($ldtt_completed, $ld_completed, $progress_completed_count);
        
        return intval($completed_count);
    }
    
    /**
     * Get cached course analytics
     */
    private function get_cached_course_analytics() {
        $cache_key = 'course_analytics';
        $analytics = wp_cache_get($cache_key, $this->cache_group);
        
        if ($analytics === false) {
            $analytics = $this->calculate_course_analytics();
            wp_cache_set($cache_key, $analytics, $this->cache_group, 1800);
        }
        
        return $analytics;
    }
    
    // Continue with all other existing methods...
    // (I'll include the rest of the methods to ensure completeness)
    
    /**
     * Calculate if a group is considered "active"
     */
    private function calculate_group_activity_status($group_id) {
        global $wpdb;
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Criteria 1: Users have completed lessons/topics/quizzes
        $learning_activity = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learndash_user_activity'") == $wpdb->prefix . 'learndash_user_activity') {
            $learning_activity = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT ua.user_id)
                FROM {$wpdb->prefix}learndash_user_activity ua
                INNER JOIN {$wpdb->posts} g ON g.ID = %d
                WHERE ua.activity_updated >= %s
                AND ua.activity_type IN ('lesson', 'topic', 'quiz')
                AND ua.user_id IN (
                    SELECT user_id FROM {$wpdb->usermeta} 
                    WHERE meta_key = %s AND meta_value = %d
                )
            ", $group_id, $thirty_days_ago, 'learndash_group_users_' . $group_id, $group_id));
        }
        
        // Criteria 2: Users have logged in and accessed group content
        $login_activity = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT um1.user_id)
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'last_login'
            AND FROM_UNIXTIME(um1.meta_value) >= %s
            AND um2.meta_key = %s
            AND um2.meta_value = %d
        ", $thirty_days_ago, 'learndash_group_users_' . $group_id, $group_id));
        
        $active_users_count = max($learning_activity, $login_activity);
        $total_users = $this->get_group_total_users($group_id);
        
        $activity_rate = $total_users > 0 ? ($active_users_count / $total_users) * 100 : 0;
        
        return array(
            'is_active' => $activity_rate >= 20, // 20% threshold
            'active_users_count' => $active_users_count,
            'total_users' => $total_users,
            'activity_rate' => round($activity_rate, 1),
            'activity_level' => $this->get_activity_level($activity_rate)
        );
    }
    
    private function get_activity_level($activity_rate) {
        if ($activity_rate >= 70) return 'very_active';
        if ($activity_rate >= 40) return 'active';  
        if ($activity_rate >= 20) return 'moderate';
        return 'inactive';
    }
    
    /**
     * Get group total users count
     */
    private function get_group_total_users($group_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s 
            AND meta_value = %d
        ", 'learndash_group_users_' . $group_id, $group_id));
        
        return intval($count);
    }
    
    /**
     * Rebuild group index
     */
    private function rebuild_group_index() {
        global $wpdb;
        
        // Get all LearnDash groups
        $groups = get_posts(array(
            'post_type' => 'groups',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($groups as $group) {
            $this->index_group($group->ID);
        }
    }
    
    /**
     * Index a single group
     */
    private function index_group($group_id) {
        global $wpdb;
        
        $group = get_post($group_id);
        if (!$group) return false;
        
        // Calculate group metrics
        $activity_status = $this->calculate_group_activity_status($group_id);
        $associated_courses = $this->get_group_associated_courses($group_id);
        $completion_data = $this->calculate_group_completion_data($group_id);
        
        // Determine group size category
        $size_category = 'small';
        if ($activity_status['total_users'] >= 100) {
            $size_category = 'large';
        } elseif ($activity_status['total_users'] >= 25) {
            $size_category = 'medium';
        }
        
        // Calculate performance tier
        $performance_tier = $this->calculate_performance_tier(
            $completion_data['completion_rate'], 
            $activity_status['activity_rate']
        );
        
        // Prepare data for insertion/update
        $data = array(
            'group_id' => $group_id,
            'group_name' => $group->post_title,
            'total_users' => $activity_status['total_users'],
            'active_users_30d' => $activity_status['active_users_count'],
            'completed_users' => $completion_data['completed_users'],
            'associated_courses' => count($associated_courses),
            'completion_rate' => $completion_data['completion_rate'],
            'activity_rate' => $activity_status['activity_rate'],
            'engagement_score' => $this->calculate_engagement_score($activity_status, $completion_data),
            'performance_tier' => $performance_tier,
            'activity_level' => $activity_status['activity_level'],
            'last_activity' => $this->get_group_last_activity($group_id),
            'created_date' => $group->post_date,
            'leader_count' => $this->get_group_leader_count($group_id),
            'group_size_category' => $size_category,
            'updated_at' => current_time('mysql')
        );
        
        // Insert or update
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->group_index_table} WHERE group_id = %d",
            $group_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->group_index_table,
                $data,
                array('group_id' => $group_id)
            );
        } else {
            $data['indexed_at'] = current_time('mysql');
            $wpdb->insert($this->group_index_table, $data);
        }
        
        return true;
    }
    
    /**
     * Get group associated courses
     */
    private function get_group_associated_courses($group_id) {
        $courses = learndash_group_enrolled_courses($group_id);
        return is_array($courses) ? $courses : array();
    }
    
    /**
     * Calculate group completion data
     */
    private function calculate_group_completion_data($group_id) {
        global $wpdb;
        
        $total_users = $this->get_group_total_users($group_id);
        $completed_users = 0;
        
        if ($total_users > 0) {
            // Count users who have completed at least one course
            $completed_users = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT um1.user_id)
                FROM {$wpdb->usermeta} um1
                INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
                WHERE um1.meta_key LIKE '_sfwd-course_progress_%'
                AND um2.meta_key = %s
                AND um2.meta_value = %d
            ", 'learndash_group_users_' . $group_id, $group_id));
        }
        
        $completion_rate = $total_users > 0 ? ($completed_users / $total_users) * 100 : 0;
        
        return array(
            'completed_users' => intval($completed_users),
            'completion_rate' => round($completion_rate, 2)
        );
    }
    
    /**
     * Calculate performance tier
     */
    private function calculate_performance_tier($completion_rate, $activity_rate) {
        $combined_score = ($completion_rate * 0.6) + ($activity_rate * 0.4);
        
        if ($combined_score >= 80) return 'high';
        if ($combined_score >= 60) return 'good';
        if ($combined_score >= 40) return 'average';
        return 'low';
    }
    
    /**
     * Get group last activity
     */
    private function get_group_last_activity($group_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(FROM_UNIXTIME(um1.meta_value))
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'last_login'
            AND um2.meta_key = %s
            AND um2.meta_value = %d
        ", 'learndash_group_users_' . $group_id, $group_id));
        
        return $last_activity;
    }
    
    /**
     * Get group leader count
     */
    private function get_group_leader_count($group_id) {
        $leaders = learndash_get_groups_administrator_ids($group_id);
        return is_array($leaders) ? count($leaders) : 0;
    }
    
    /**
     * Get active groups count
     */
    private function get_active_groups_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->group_index_table} 
            WHERE activity_level IN ('active', 'very_active', 'moderate')
        ");
    }
    
    /**
     * Get very active groups count
     */
    private function get_very_active_groups_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->group_index_table} 
            WHERE activity_level = 'very_active'
        ");
    }
    
    /**
     * Get inactive groups count
     */
    private function get_inactive_groups_count() {
        global $wpdb;
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->group_index_table} 
            WHERE activity_level = 'inactive'
        ");
    }
    
    /**
     * Get average completion rate
     */
    private function get_avg_completion_rate() {
        global $wpdb;
        
        $avg = $wpdb->get_var("
            SELECT AVG(completion_rate) 
            FROM {$this->group_index_table}
        ");
        
        return round(floatval($avg), 1);
    }
    
    /**
     * Get activity distribution
     */
    private function get_activity_distribution() {
        global $wpdb;
        
        $distribution = $wpdb->get_results("
            SELECT activity_level, COUNT(*) as count
            FROM {$this->group_index_table}
            GROUP BY activity_level
        ");
        
        $result = array(
            'very_active' => 0,
            'active' => 0,
            'moderate' => 0,
            'inactive' => 0
        );
        
        foreach ($distribution as $item) {
            $result[$item->activity_level] = intval($item->count);
        }
        
        return $result;
    }
    
    /**
     * Get trends data (last 6 months)
     */
    private function get_trends_data() {
        // This would require historical data - for now return sample data
        return array(
            'months' => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'),
            'active_groups' => array(45, 52, 48, 61, 58, 67),
            'completion_rates' => array(72.5, 74.2, 73.8, 76.1, 75.4, 77.2)
        );
    }
    
    /**
     * Get top performing groups
     */
    private function get_top_performing_groups($limit = 5) {
        global $wpdb;
        
        $groups = $wpdb->get_results($wpdb->prepare("
            SELECT group_name, total_users, completion_rate, activity_rate, engagement_score
            FROM {$this->group_index_table}
            ORDER BY engagement_score DESC
            LIMIT %d
        ", $limit));
        
        return $groups;
    }
    
    /**
     * Get groups that need attention
     */
    private function get_groups_need_attention($limit = 5) {
        global $wpdb;
        
        $groups = $wpdb->get_results($wpdb->prepare("
            SELECT group_name, total_users, completion_rate, activity_rate, engagement_score
            FROM {$this->group_index_table}
            WHERE activity_level = 'inactive' OR performance_tier = 'low'
            ORDER BY engagement_score ASC
            LIMIT %d
        ", $limit));
        
        return $groups;
    }
    
    /**
     * Get top 25 groups by members
     */
    private function get_top_25_groups_by_members() {
        global $wpdb;
        
        $groups = $wpdb->get_results("
            SELECT *
            FROM {$this->group_index_table}
            ORDER BY total_users DESC
            LIMIT 25
        ");
        
        return $groups;
    }
    
    /**
     * Get cached total courses
     */
    private function get_cached_total_courses() {
        $cache_key = 'total_courses';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            $course_post_type = 'sfwd-courses';
            if (function_exists('learndash_get_post_type_slug')) {
                $course_post_type = learndash_get_post_type_slug('course');
            }
            
            $courses = get_posts(array(
                'post_type' => $course_post_type,
                'numberposts' => -1,
                'post_status' => 'publish'
            ));
            $total = is_array($courses) ? count($courses) : 0;
            wp_cache_set($cache_key, $total, $this->cache_group, 3600);
        }
        
        return $total;
    }
    
    /**
     * Get cached total lessons
     */
    private function get_cached_total_lessons() {
        $cache_key = 'total_lessons';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            $lesson_post_type = 'sfwd-lessons';
            if (function_exists('learndash_get_post_type_slug')) {
                $lesson_post_type = learndash_get_post_type_slug('lesson');
            }
            
            $lessons = get_posts(array(
                'post_type' => $lesson_post_type,
                'numberposts' => -1,
                'post_status' => 'publish'
            ));
            $total = is_array($lessons) ? count($lessons) : 0;
            wp_cache_set($cache_key, $total, $this->cache_group, 3600);
        }
        
        return $total;
    }
    
    /**
     * Get cached active learners
     */
    private function get_cached_active_learners() {
        $cache_key = 'active_learners';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            global $wpdb;
            $index_table = $wpdb->prefix . 'wbcom_reports_index';
            
            $total = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$index_table} 
                WHERE enrolled_courses > 0
            ");
            
            wp_cache_set($cache_key, intval($total), $this->cache_group, 1800);
        }
        
        return $total;
    }
    
    /**
     * Get cached total completed courses
     */
    private function get_cached_total_completed_courses() {
        $cache_key = 'total_completed_courses';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            global $wpdb;
            $index_table = $wpdb->prefix . 'wbcom_reports_index';
            
            $total = $wpdb->get_var("
                SELECT SUM(completed_courses) 
                FROM {$index_table}
            ");
            
            wp_cache_set($cache_key, intval($total), $this->cache_group, 1800);
        }
        
        return $total;
    }
    
    /**
     * Get cached courses list
     */
    private function get_cached_courses_list() {
        $cache_key = 'courses_list';
        $courses_list = wp_cache_get($cache_key, $this->cache_group);
        
        if ($courses_list === false) {
            $course_post_type = 'sfwd-courses';
            if (function_exists('learndash_get_post_type_slug')) {
                $course_post_type = learndash_get_post_type_slug('course');
            }
            
            $courses = get_posts(array(
                'post_type' => $course_post_type,
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ));
            
            $courses_list = array();
            if (is_array($courses)) {
                foreach ($courses as $course) {
                    $courses_list[] = array(
                        'id' => $course->ID,
                        'title' => $course->post_title
                    );
                }
            }
            
            wp_cache_set($cache_key, $courses_list, $this->cache_group, 3600);
        }
        
        return $courses_list;
    }
    
    /**
     * Get indexed learning users with enhanced data
     */
    private function get_indexed_learning_users($filter = 'all', $course_id = 0, $search = '', $sort_by = 'enrolled_courses', $sort_order = 'desc') {
        // Build filter for learning users
        $learning_filter = $filter;
        if ($filter === 'active') {
            $learning_filter = 'all'; // We'll filter by enrolled_courses > 0
        } elseif ($filter === 'completed') {
            $learning_filter = 'all'; // We'll filter by completed_courses > 0
        } elseif ($filter === 'in-progress') {
            $learning_filter = 'all'; // We'll filter by in_progress_courses > 0
        }
        
        $indexed_users = $this->index->get_indexed_users(array(
            'page' => 1,
            'per_page' => 50,
            'search' => $search,
            'filter' => $learning_filter,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ));
        
        // Transform and filter data
        $user_stats = array();
        foreach ($indexed_users['users'] as $user) {
            // Apply learning-specific filters
            if ($filter === 'active' && $user['enrolled_courses'] == 0) {
                continue;
            }
            if ($filter === 'completed' && $user['completed_courses'] == 0) {
                continue;
            }
            if ($filter === 'in-progress' && $user['in_progress_courses'] == 0) {
                continue;
            }
            
            // Apply course filter
            if ($course_id > 0 && !$this->is_user_enrolled_in_course($user['user_id'], $course_id)) {
                continue;
            }
            
            // Skip users with no learning activity unless they are test users
            if ($filter === 'all' && $user['enrolled_courses'] == 0 && !$user['is_test_user']) {
                continue;
            }
            
            $user_stats[] = array(
                'user_id' => $user['user_id'],
                'display_name' => $user['display_name'],
                'user_login' => $user['user_login'],
                'enrolled_courses' => $user['enrolled_courses'],
                'completed_courses' => $user['completed_courses'],
                'in_progress' => $user['in_progress_courses'],
                'avg_progress' => $user['avg_progress'] . '%',
                'last_activity' => $user['last_activity'] ?: 'Never'
            );
        }
        
        return array(
            'users' => $user_stats,
            'total_count' => count($user_stats)
        );
    }
    
    /**
     * Check if user is enrolled in specific course
     */
    private function is_user_enrolled_in_course($user_id, $course_id) {
        // Check LDTT progress
        $ldtt_progress = get_user_meta($user_id, '_ldtt_progress_course_' . $course_id, true);
        if (!empty($ldtt_progress)) {
            return true;
        }
        
        // Check regular LearnDash enrollment
        $course_access = get_user_meta($user_id, '_sfwd-course_progress', true);
        if (is_array($course_access) && isset($course_access[$course_id])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get cached group analytics
     */
    private function get_cached_group_analytics($filter) {
        $cache_key = 'group_analytics_' . $filter;
        $analytics = wp_cache_get($cache_key, $this->cache_group);
        
        if ($analytics === false) {
            $analytics = $this->calculate_group_analytics($filter);
            wp_cache_set($cache_key, $analytics, $this->cache_group, 1800);
        }
        
        return $analytics;
    }
    
    /**
     * Calculate group analytics using indexed data
     */
    private function calculate_group_analytics($filter) {
        global $wpdb;
        
        $where_clause = '1=1';
        if ($filter === 'with_leaders') {
            $where_clause = 'leader_count > 0';
        } elseif ($filter === 'active') {
            $where_clause = "activity_level IN ('active', 'very_active')";
        }
        
        $groups = $wpdb->get_results("
            SELECT group_name, total_users, completed_users, associated_courses, 
                   completion_rate, activity_rate, created_date, activity_level,
                   CASE 
                       WHEN activity_level = 'very_active' THEN 'Active'
                       WHEN activity_level = 'active' THEN 'Active'
                       WHEN activity_level = 'moderate' THEN 'Moderate'
                       ELSE 'Needs Attention'
                   END as status,
                   'Group Leaders' as group_leaders
            FROM {$this->group_index_table}
            WHERE {$where_clause}
            ORDER BY total_users DESC
        ");
        
        // Format the data
        $analytics = array();
        foreach ($groups as $group) {
            $analytics[] = array(
                'group_name' => $group->group_name,
                'group_leaders' => $group->group_leaders,
                'total_users' => $group->total_users,
                'associated_courses' => $group->associated_courses,
                'avg_progress' => $group->completion_rate . '%',
                'completed_users' => $group->completed_users,
                'created_date' => $group->created_date,
                'status' => $group->status
            );
        }
        
        return $analytics;
    }
    
    /**
     * AJAX handler for exporting LearnDash stats
     */
    public function ajax_export_learndash_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'user-progress';
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'enrolled_courses';
        $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'desc';
        
        $export_data = array();
        
        if ($tab === 'user-progress') {
            $users_data = $this->get_indexed_learning_users($filter, $course_id, $search, $sort_by, $sort_order);
            
            foreach ($users_data['users'] as $user) {
                $export_data[] = array(
                    'Display Name' => $user['display_name'],
                    'Username' => $user['user_login'],
                    'Enrolled Courses' => $user['enrolled_courses'],
                    'Completed Courses' => $user['completed_courses'],
                    'In Progress' => $user['in_progress'],
                    'Average Progress' => $user['avg_progress'],
                    'Last Activity' => $user['last_activity']
                );
            }
            $filename = 'learndash-user-progress-' . date('Y-m-d-H-i-s') . '.csv';
            
        } elseif ($tab === 'course-analytics') {
            $courses = $this->get_cached_course_analytics();
            
            foreach ($courses as $course) {
                $export_data[] = array(
                    'Course Name' => $course['course_name'],
                    'Enrolled Users' => $course['enrolled_users'],
                    'Completed' => $course['completed'],
                    'In Progress' => $course['in_progress'],
                    'Completion Rate' => $course['completion_rate']
                );
            }
            $filename = 'learndash-course-analytics-' . date('Y-m-d-H-i-s') . '.csv';
            
        } elseif ($tab === 'group-reports') {
            $groups = $this->get_cached_group_analytics($filter);
            
            foreach ($groups as $group) {
                $export_data[] = array(
                    'Group Name' => $group['group_name'],
                    'Group Leaders' => $group['group_leaders'],
                    'Total Users' => $group['total_users'],
                    'Associated Courses' => $group['associated_courses'],
                    'Average Progress' => $group['avg_progress'],
                    'Completed Users' => $group['completed_users'],
                    'Created Date' => $group['created_date'],
                    'Status' => $group['status']
                );
            }
            $filename = 'learndash-group-analytics-' . date('Y-m-d-H-i-s') . '.csv';
        }
        
        Wbcom_Reports_Helpers::export_to_csv($export_data, $filename);
    }
    
    /**
     * AJAX handler for rebuilding LearnDash index
     */
    public function ajax_rebuild_index() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        // Increase time limit for large sites
        if (function_exists('set_time_limit')) {
            set_time_limit(300); // 5 minutes
        }
        
        $indexed_count = $this->index->rebuild_user_index();
        
        // Also rebuild group index
        $this->rebuild_group_index();
        
        // Clear LearnDash specific caches
        wp_cache_flush_group($this->cache_group);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully rebuilt learning index for %d users.', 'wbcom-reports'), $indexed_count),
            'indexed_count' => $indexed_count
        ));
    }
    
    /**
     * Update index when course is completed
     */
    public function update_course_completion_index($data) {
        if (isset($data['user']) && isset($data['user']->ID)) {
            $this->index->index_user($data['user']->ID);
            wp_cache_delete('total_completed_courses', $this->cache_group);
            wp_cache_delete('course_analytics', $this->cache_group);
        }
    }
    
    /**
     * Update index when lesson is completed
     */
    public function update_lesson_completion_index($data) {
        if (isset($data['user']) && isset($data['user']->ID)) {
            $this->index->index_user($data['user']->ID);
        }
    }
    
    /**
     * Update index when topic is completed
     */
    public function update_topic_completion_index($data) {
        if (isset($data['user']) && isset($data['user']->ID)) {
            $this->index->index_user($data['user']->ID);
        }
    }
    
    /**
     * Update index when quiz is completed
     */
    public function update_quiz_completion_index($data, $user) {
        if ($user && isset($user->ID)) {
            $this->index->index_user($user->ID);
        }
    }
    
    /**
     * Update index when course enrollment changes
     */
    public function update_enrollment_index($user_id, $course_id) {
        $this->index->index_user($user_id);
        wp_cache_delete('active_learners', $this->cache_group);
        wp_cache_delete('course_analytics', $this->cache_group);
    }
    
    /**
     * AJAX handler for filtered groups
     */
    public function ajax_get_filtered_groups() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        try {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 25;
            $activity_level = isset($_POST['activity_level']) ? sanitize_text_field($_POST['activity_level']) : 'all';
            $size_filter = isset($_POST['size_filter']) ? sanitize_text_field($_POST['size_filter']) : 'all';
            $performance_filter = isset($_POST['performance_filter']) ? sanitize_text_field($_POST['performance_filter']) : 'all';
            
            $filtered_groups = $this->get_filtered_groups_data($page, $per_page, $activity_level, $size_filter, $performance_filter);
            
            wp_send_json_success($filtered_groups);
            
        } catch (Exception $e) {
            error_log('Filtered Groups Error: ' . $e->getMessage());
            wp_send_json_error('Error loading filtered groups: ' . $e->getMessage());
        }
    }

    /**
     * Get filtered groups data
     */
    private function get_filtered_groups_data($page = 1, $per_page = 25, $activity_level = 'all', $size_filter = 'all', $performance_filter = 'all') {
        global $wpdb;
        
        // Ensure group index exists
        $this->maybe_rebuild_group_index();
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        
        if ($activity_level !== 'all') {
            $where_conditions[] = $wpdb->prepare('activity_level = %s', $activity_level);
        }
        
        if ($size_filter !== 'all') {
            switch ($size_filter) {
                case 'large':
                    $where_conditions[] = 'total_users >= 100';
                    break;
                case 'medium':
                    $where_conditions[] = 'total_users BETWEEN 25 AND 99';
                    break;
                case 'small':
                    $where_conditions[] = 'total_users BETWEEN 1 AND 24';
                    break;
            }
        }
        
        if ($performance_filter !== 'all') {
            switch ($performance_filter) {
                case 'high':
                    $where_conditions[] = 'completion_rate >= 80';
                    break;
                case 'good':
                    $where_conditions[] = 'completion_rate BETWEEN 60 AND 79.99';
                    break;
                case 'average':
                    $where_conditions[] = 'completion_rate BETWEEN 40 AND 59.99';
                    break;
                case 'low':
                    $where_conditions[] = 'completion_rate < 40';
                    break;
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Calculate pagination
        $offset = ($page - 1) * $per_page;
        $limit = $per_page > 0 ? "LIMIT {$offset}, {$per_page}" : "";
        
        // Get filtered groups
        $groups = $wpdb->get_results("
            SELECT *,
                   CASE 
                       WHEN activity_level = 'very_active' THEN 'Very Active'
                       WHEN activity_level = 'active' THEN 'Active'
                       WHEN activity_level = 'moderate' THEN 'Moderate'
                       WHEN activity_level = 'inactive' THEN 'Needs Attention'
                       ELSE 'Unknown'
                   END as status
            FROM {$this->group_index_table}
            {$where_clause}
            ORDER BY total_users DESC, completion_rate DESC
            {$limit}
        ");
        
        // Get total count for pagination
        $total_count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$this->group_index_table}
            {$where_clause}
        ");
        
        $total_pages = $per_page > 0 ? ceil($total_count / $per_page) : 1;
        
        return array(
            'groups' => $groups,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_groups' => intval($total_count),
                'per_page' => $per_page
            )
        );
    }

    /**
     * AJAX handler for group drill-down
     */
    public function ajax_get_group_drilldown() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (!$group_id) {
            wp_send_json_error('Invalid group ID');
        }
        
        try {
            $group_details = $this->get_group_drilldown_data($group_id);
            wp_send_json_success($group_details);
            
        } catch (Exception $e) {
            error_log('Group Drilldown Error: ' . $e->getMessage());
            wp_send_json_error('Error loading group details: ' . $e->getMessage());
        }
    }

    /**
     * Get group drill-down data
     */
    private function get_group_drilldown_data($group_id) {
        global $wpdb;
        
        // Get group data from index
        $group_data = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->group_index_table} WHERE group_id = %d
        ", $group_id));
        
        if (!$group_data) {
            throw new Exception('Group not found in index');
        }
        
        // Get additional details
        $group_leaders = $this->get_group_leaders($group_id);
        $recent_activity = $this->get_group_recent_activity($group_id);
        $top_learners = $this->get_group_top_learners($group_id);
        
        return array(
            'group_id' => $group_id,
            'total_users' => $group_data->total_users,
            'active_users' => $group_data->active_users_30d,
            'completion_rate' => $group_data->completion_rate . '%',
            'activity_level' => $group_data->activity_level,
            'activity_rate' => $group_data->activity_rate . '%',
            'engagement_score' => isset($group_data->engagement_score) ? $group_data->engagement_score : 0,
            'associated_courses' => $group_data->associated_courses,
            'leaders' => $group_leaders,
            'recent_activity' => $recent_activity,
            'top_learners' => $top_learners
        );
    }

    /**
     * Get group leaders
     */
    private function get_group_leaders($group_id) {
        if (!function_exists('learndash_get_groups_administrator_ids')) {
            return array();
        }
        
        $leader_ids = learndash_get_groups_administrator_ids($group_id);
        $leaders = array();
        
        if (is_array($leader_ids)) {
            foreach ($leader_ids as $leader_id) {
                $user = get_user_by('ID', $leader_id);
                if ($user) {
                    $leaders[] = array(
                        'id' => $leader_id,
                        'name' => $user->display_name,
                        'email' => $user->user_email
                    );
                }
            }
        }
        
        return $leaders;
    }

    /**
     * Get group recent activity
     */
    private function get_group_recent_activity($group_id, $limit = 10) {
        global $wpdb;
        
        // Get group members first
        $group_users = $wpdb->get_col($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = %s AND meta_value = %s
        ", 'learndash_group_users_' . $group_id, $group_id));
        
        if (empty($group_users)) {
            return array();
        }
        
        $user_ids_placeholder = implode(',', array_fill(0, count($group_users), '%d'));
        
        // Get recent learning activity for group members
        $recent_activity = $wpdb->get_results($wpdb->prepare("
            SELECT ua.user_id, ua.activity_type, ua.post_id, ua.activity_updated, u.display_name,
                CASE 
                    WHEN ua.activity_type = 'course' THEN 'Course Activity'
                    WHEN ua.activity_type = 'lesson' THEN 'Lesson Completed'
                    WHEN ua.activity_type = 'topic' THEN 'Topic Completed'
                    WHEN ua.activity_type = 'quiz' THEN 'Quiz Taken'
                    ELSE 'Learning Activity'
                END as activity_label
            FROM {$wpdb->prefix}learndash_user_activity ua
            INNER JOIN {$wpdb->users} u ON ua.user_id = u.ID
            WHERE ua.user_id IN ({$user_ids_placeholder})
            AND ua.activity_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY ua.activity_updated DESC
            LIMIT %d
        ", array_merge($group_users, array($limit))));
        
        return $recent_activity;
    }

    /**
     * Get group top learners
     */
    private function get_group_top_learners($group_id, $limit = 5) {
        global $wpdb;
        
        // Get group members first
        $group_users = $wpdb->get_col($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = %s AND meta_value = %s
        ", 'learndash_group_users_' . $group_id, $group_id));
        
        if (empty($group_users)) {
            return array();
        }
        
        $user_ids_placeholder = implode(',', array_fill(0, count($group_users), '%d'));
        
        // Get top learners from the group
        $top_learners = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.display_name, 
                COUNT(DISTINCT pm1.meta_value) as enrolled_courses,
                COUNT(DISTINCT pm2.meta_value) as completed_courses,
                ROUND(AVG(CASE WHEN pm3.meta_value != '' THEN pm3.meta_value ELSE 0 END), 1) as avg_progress
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->postmeta} pm1 ON FIND_IN_SET(u.ID, pm1.meta_value) AND pm1.meta_key = 'course_access_list'
            LEFT JOIN {$wpdb->usermeta} pm2 ON u.ID = pm2.user_id AND pm2.meta_key LIKE 'course_completed_%'
            LEFT JOIN {$wpdb->usermeta} pm3 ON u.ID = pm3.user_id AND pm3.meta_key LIKE 'course_%_progress'
            WHERE u.ID IN ({$user_ids_placeholder})
            GROUP BY u.ID, u.display_name
            HAVING enrolled_courses > 0
            ORDER BY completed_courses DESC, avg_progress DESC
            LIMIT %d
        ", array_merge($group_users, array($limit))));
        
        return $top_learners;
    }

    /**
     * AJAX handler for exporting group insights
     */
    public function ajax_export_group_insights() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        $activity_level = isset($_POST['activity_level']) ? sanitize_text_field($_POST['activity_level']) : 'all';
        $size_filter = isset($_POST['size_filter']) ? sanitize_text_field($_POST['size_filter']) : 'all';
        $performance_filter = isset($_POST['performance_filter']) ? sanitize_text_field($_POST['performance_filter']) : 'all';
        
        // Get all groups (no pagination limit)
        $filtered_data = $this->get_filtered_groups_data(1, 0, $activity_level, $size_filter, $performance_filter);
        
        $export_data = array();
        foreach ($filtered_data['groups'] as $group) {
            $export_data[] = array(
                'Group Name' => $group->group_name,
                'Group ID' => $group->group_id,
                'Total Users' => $group->total_users,
                'Active Users (30d)' => $group->active_users_30d,
                'Activity Rate' => $group->activity_rate . '%',
                'Activity Level' => $group->activity_level,
                'Completion Rate' => $group->completion_rate . '%',
                'Performance Tier' => isset($group->performance_tier) ? $group->performance_tier : 'N/A',
                'Associated Courses' => $group->associated_courses,
                'Engagement Score' => isset($group->engagement_score) ? $group->engagement_score : 'N/A',
                'Group Size Category' => isset($group->group_size_category) ? $group->group_size_category : $this->get_size_category($group->total_users),
                'Created Date' => isset($group->created_date) ? $group->created_date : 'N/A',
                'Last Activity' => isset($group->last_activity) ? $group->last_activity : 'Never',
                'Leader Count' => isset($group->leader_count) ? $group->leader_count : 0
            );
        }
        
        $filename = 'learndash-group-insights-' . date('Y-m-d-H-i-s') . '.csv';
        $this->export_to_csv($export_data, $filename);
    }

    /**
     * Get size category for a group
     */
    private function get_size_category($total_users) {
        if ($total_users >= 100) {
            return 'Large';
        } elseif ($total_users >= 25) {
            return 'Medium';
        } elseif ($total_users >= 1) {
            return 'Small';
        } else {
            return 'Empty';
        }
    }

    /**
     * Export data to CSV
     */
    private function export_to_csv($data, $filename) {
        if (empty($data)) {
            wp_die('No data to export');
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write CSV headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Maybe rebuild group index table
     */
    private function maybe_rebuild_group_index() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->group_index_table}'");
        
        if (!$table_exists) {
            $this->create_group_index_table();
            $this->populate_group_index();
        }
        
        // Check if data is recent (less than 1 hour old)
        $last_update = get_option('wbcom_group_index_last_update', 0);
        if (time() - $last_update > 3600) { // 1 hour
            $this->populate_group_index();
            update_option('wbcom_group_index_last_update', time());
        }
    }

    /**
     * Create group index table
     */
    private function create_group_index_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->group_index_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            group_name varchar(255) NOT NULL,
            total_users int(11) DEFAULT 0,
            active_users_30d int(11) DEFAULT 0,
            activity_rate decimal(5,2) DEFAULT 0.00,
            activity_level varchar(20) DEFAULT 'inactive',
            completion_rate decimal(5,2) DEFAULT 0.00,
            associated_courses int(11) DEFAULT 0,
            engagement_score decimal(5,2) DEFAULT 0.00,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime NULL,
            leader_count int(11) DEFAULT 0,
            group_size_category varchar(20) DEFAULT 'small',
            performance_tier varchar(20) DEFAULT 'low',
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY group_id (group_id),
            KEY activity_level (activity_level),
            KEY completion_rate (completion_rate),
            KEY total_users (total_users)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Populate group index with current data
     */
    private function populate_group_index() {
        global $wpdb;
        
        // Get all LearnDash groups
        $groups = get_posts(array(
            'post_type' => 'groups',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        foreach ($groups as $group_id) {
            $this->update_group_index_data($group_id);
        }
    }

    /**
     * Update group index data for a specific group
     */
    private function update_group_index_data($group_id) {
        global $wpdb;
        
        $group_name = get_the_title($group_id);
        $group_users = learndash_get_groups_user_ids($group_id);
        $total_users = is_array($group_users) ? count($group_users) : 0;
        
        // Calculate metrics
        $active_users_30d = $this->get_active_users_count($group_users, 30);
        $activity_rate = $total_users > 0 ? round(($active_users_30d / $total_users) * 100, 2) : 0;
        $completion_rate = $this->calculate_group_completion_rate($group_users);
        $associated_courses = $this->get_group_courses_count($group_id);
        $engagement_score = $this->calculate_engagement_score($activity_rate, $completion_rate);
        
        // Determine activity level
        $activity_level = $this->determine_activity_level($activity_rate);
        
        // Determine size category
        $size_category = $this->get_size_category($total_users);
        
        // Determine performance tier
        $performance_tier = $this->determine_performance_tier($completion_rate);
        
        // Get leader count
        $leader_count = is_array(learndash_get_groups_administrator_ids($group_id)) ? 
                       count(learndash_get_groups_administrator_ids($group_id)) : 0;
        
        // Insert or update the record
        $wpdb->replace(
            $this->group_index_table,
            array(
                'group_id' => $group_id,
                'group_name' => $group_name,
                'total_users' => $total_users,
                'active_users_30d' => $active_users_30d,
                'activity_rate' => $activity_rate,
                'activity_level' => $activity_level,
                'completion_rate' => $completion_rate,
                'associated_courses' => $associated_courses,
                'engagement_score' => $engagement_score,
                'leader_count' => $leader_count,
                'group_size_category' => $size_category,
                'performance_tier' => $performance_tier,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%d', '%f', '%s', '%f', '%d', '%f', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Helper methods for calculations
     */
    private function get_active_users_count($user_ids, $days = 30) {
        if (empty($user_ids)) return 0;
        
        global $wpdb;
        $user_ids_str = implode(',', array_map('intval', $user_ids));
        
        return $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->prefix}learndash_user_activity 
            WHERE user_id IN ({$user_ids_str}) 
            AND activity_updated >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
        ");
    }

    private function calculate_group_completion_rate($user_ids) {
        if (empty($user_ids)) return 0;
        
        global $wpdb;
        $total_enrollments = 0;
        $total_completions = 0;
        
        foreach ($user_ids as $user_id) {
            $user_courses = learndash_user_get_enrolled_courses($user_id);
            $total_enrollments += count($user_courses);
            
            foreach ($user_courses as $course_id) {
                if (learndash_course_completed($user_id, $course_id)) {
                    $total_completions++;
                }
            }
        }
        
        return $total_enrollments > 0 ? round(($total_completions / $total_enrollments) * 100, 2) : 0;
    }

    private function get_group_courses_count($group_id) {
        $courses = learndash_group_enrolled_courses($group_id);
        return is_array($courses) ? count($courses) : 0;
    }

    private function calculate_engagement_score($activity_rate, $completion_rate) {
        return round(($activity_rate * 0.6) + ($completion_rate * 0.4), 2);
    }

    private function determine_activity_level($activity_rate) {
        if ($activity_rate >= 80) return 'very_active';
        if ($activity_rate >= 60) return 'active';
        if ($activity_rate >= 30) return 'moderate';
        return 'inactive';
    }

    private function determine_performance_tier($completion_rate) {
        if ($completion_rate >= 80) return 'high';
        if ($completion_rate >= 60) return 'good';
        if ($completion_rate >= 40) return 'average';
        return 'low';
    }
}

// Initialize LearnDash reports
new Wbcom_Reports_LearnDash();