<?php
/**
 * Enhanced Dashboard Reports Class with Full Indexing Support
 * 
 * @package Wbcom_Reports
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wbcom_Reports_Dashboard {
    
    private $index;
    private $cache_group = 'wbcom_reports_dashboard';
    
    public function __construct() {
        // Initialize indexing system
        $this->index = new Wbcom_Reports_Index();
        
        add_action('wp_ajax_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_rebuild_dashboard_index', array($this, 'ajax_rebuild_index'));
    }
    
    /**
     * Render dashboard page with index status
     */
    public static function render_page() {
        $index = new Wbcom_Reports_Index();
        $index_stats = $index->get_index_stats();
        $needs_rebuilding = $index->needs_rebuilding();
        $cache_stats = $index->get_cache_stats();
        ?>
        <div class="wrap wbcom-reports-wrap">
            <h1><?php _e('Wbcom Reports Dashboard', 'wbcom-reports'); ?></h1>
            
            <!-- Index Status -->
            <div class="wbcom-index-status">
                <div class="index-info">
                    <div class="index-details">
                        <p>
                            <strong><?php _e('Dashboard Index Status:', 'wbcom-reports'); ?></strong>
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
                            | <strong><?php _e('Performance:', 'wbcom-reports'); ?></strong>
                            <span class="performance-optimized"><?php _e('Optimized with Indexing', 'wbcom-reports'); ?></span>
                        </p>
                    </div>
                    <?php if ($needs_rebuilding): ?>
                        <div class="notice notice-warning inline">
                            <p><?php _e('Index needs rebuilding for optimal dashboard performance.', 'wbcom-reports'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="wbcom-stats-container">
                <div class="wbcom-stats-summary">
                    <div class="wbcom-stat-box">
                        <h3><?php _e('Total Users', 'wbcom-reports'); ?></h3>
                        <span id="total-users" class="stat-number">Loading...</span>
                    </div>
                    <div class="wbcom-stat-box">
                        <h3><?php _e('Total Activities', 'wbcom-reports'); ?></h3>
                        <span id="total-activities" class="stat-number">Loading...</span>
                    </div>
                    <div class="wbcom-stat-box">
                        <h3><?php _e('Total Courses', 'wbcom-reports'); ?></h3>
                        <span id="total-courses" class="stat-number">Loading...</span>
                    </div>
                    <div class="wbcom-stat-box">
                        <h3><?php _e('New Users (30 days)', 'wbcom-reports'); ?></h3>
                        <span id="total-groups" class="stat-number">Loading...</span>
                    </div>
                </div>
                
                <div class="wbcom-stats-actions">
                    <button id="refresh-dashboard-stats" class="button button-primary">
                        <?php _e('Refresh Stats', 'wbcom-reports'); ?>
                    </button>
                    <button id="rebuild-dashboard-index" class="button <?php echo $needs_rebuilding ? 'needs-rebuild' : ''; ?>" 
                            title="<?php _e('Rebuild index for optimal performance', 'wbcom-reports'); ?>">
                        <?php _e('Rebuild Index', 'wbcom-reports'); ?>
                        <?php if ($needs_rebuilding): ?>
                            <span class="dashicons dashicons-warning" style="color: #ff6b6b; margin-left: 5px;"></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="wbcom-dashboard-tables">
                    <div class="wbcom-table-section">
                        <div class="wbcom-user-stats">
                            <h2><?php _e('Top Active Users (BuddyPress)', 'wbcom-reports'); ?></h2>
                            <table id="top-users-table" class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Rank', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Display Name', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Username', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Activity Count', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Comments', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Last Activity', 'wbcom-reports'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wbcom-table-section">
                        <div class="wbcom-user-stats">
                            <h2><?php _e('Top Learners (LearnDash)', 'wbcom-reports'); ?></h2>
                            <table id="top-groups-table" class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Rank', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Display Name', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Enrolled Courses', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Completed Courses', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Avg. Progress', 'wbcom-reports'); ?></th>
                                        <th><?php _e('Last Activity', 'wbcom-reports'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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
        </style>
        <?php
    }
    
    /**
     * AJAX handler for dashboard stats using indexed data
     */
    public function ajax_get_dashboard_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        $stats = array(
            'total_users' => $this->get_cached_total_users(),
            'total_activities' => $this->get_cached_total_activities(),
            'total_courses' => $this->get_cached_total_courses(),
            'total_groups' => $this->get_cached_new_users_30_days(),
            'top_users' => $this->get_top_active_users_from_index(),
            'top_groups' => $this->get_top_learners_from_index(),
            'using_index' => true
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get cached total users
     */
    private function get_cached_total_users() {
        $cache_key = 'total_users';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            $user_count = count_users();
            $total = $user_count['total_users'];
            wp_cache_set($cache_key, $total, $this->cache_group, 3600);
        }
        
        return $total;
    }
    
    /**
     * Get cached total activities
     */
    private function get_cached_total_activities() {
        $cache_key = 'total_activities';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
                $total = 0;
            } else {
                global $wpdb;
                $table_name = $wpdb->prefix . 'bp_activity';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
                    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE component = 'activity' AND type = 'activity_update'");
                } else {
                    $total = 0;
                }
            }
            wp_cache_set($cache_key, intval($total), $this->cache_group, 1800); // 30 minutes
        }
        
        return $total;
    }
    
    /**
     * Get cached total courses
     */
    private function get_cached_total_courses() {
        $cache_key = 'total_courses';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            if (!Wbcom_Reports_Helpers::is_learndash_active()) {
                $total = 0;
            } else {
                $course_post_type = 'sfwd-courses';
                if (function_exists('learndash_get_post_type_slug')) {
                    $course_post_type = learndash_get_post_type_slug('course');
                }
                
                $courses = get_posts(array(
                    'post_type' => $course_post_type,
                    'numberposts' => -1,
                    'post_status' => 'publish'
                ));
                $total = count($courses);
            }
            wp_cache_set($cache_key, $total, $this->cache_group, 3600);
        }
        
        return $total;
    }
    
    /**
     * Get cached new users in last 30 days
     */
    private function get_cached_new_users_30_days() {
        $cache_key = 'new_users_30_days';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            $user_query = new WP_User_Query(array(
                'date_query' => array(
                    array(
                        'after' => $thirty_days_ago,
                        'inclusive' => true
                    )
                ),
                'count_total' => true
            ));
            
            $total = $user_query->get_total();
            wp_cache_set($cache_key, intval($total), $this->cache_group, 3600); // 1 hour
        }
        
        return $total;
    }
    
    /**
     * Get top active users from index
     */
    private function get_top_active_users_from_index($limit = 10) {
        $cache_key = 'top_active_users_' . $limit;
        $top_users = wp_cache_get($cache_key, $this->cache_group);
        
        if ($top_users === false) {
            if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
                return array();
            }
            
            // Use indexed data for top users
            $indexed_users = $this->index->get_indexed_users(array(
                'page' => 1,
                'per_page' => $limit,
                'sort_by' => 'activity_count',
                'sort_order' => 'desc',
                'filter' => 'all'
            ));
            
            $top_users = array();
            $rank = 1;
            
            foreach ($indexed_users['users'] as $user) {
                // Only include users with actual activity
                if ($user['activity_count'] > 0) {
                    $top_users[] = array(
                        'rank' => $rank++,
                        'user_id' => $user['user_id'],
                        'display_name' => $user['display_name'],
                        'user_login' => $user['user_login'],
                        'activity_count' => intval($user['activity_count']),
                        'comment_count' => intval($user['comment_count']),
                        'last_activity' => $user['last_activity'] ? date('Y-m-d H:i:s', strtotime($user['last_activity'])) : 'Never'
                    );
                }
            }
            
            wp_cache_set($cache_key, $top_users, $this->cache_group, 1800); // 30 minutes
        }
        
        return $top_users;
    }
    
    /**
     * Get top learners from index (replaces top groups)
     */
    private function get_top_learners_from_index($limit = 10) {
        $cache_key = 'top_learners_' . $limit;
        $top_learners = wp_cache_get($cache_key, $this->cache_group);
        
        if ($top_learners === false) {
            if (!Wbcom_Reports_Helpers::is_learndash_active()) {
                return array();
            }
            
            // Use indexed data for top learners
            $indexed_users = $this->index->get_indexed_users(array(
                'page' => 1,
                'per_page' => $limit,
                'sort_by' => 'enrolled_courses',
                'sort_order' => 'desc',
                'filter' => 'all'
            ));
            
            $top_learners = array();
            $rank = 1;
            
            foreach ($indexed_users['users'] as $user) {
                // Only include users with learning activity
                if ($user['enrolled_courses'] > 0) {
                    $top_learners[] = array(
                        'rank' => $rank++,
                        'user_id' => $user['user_id'],
                        'display_name' => $user['display_name'],
                        'user_login' => $user['user_login'],
                        'enrolled_courses' => intval($user['enrolled_courses']),
                        'completed_courses' => intval($user['completed_courses']),
                        'avg_progress' => $user['avg_progress'] . '%',
                        'last_activity' => $user['last_activity'] ? date('Y-m-d H:i:s', strtotime($user['last_activity'])) : 'Never'
                    );
                }
            }
            
            wp_cache_set($cache_key, $top_learners, $this->cache_group, 1800); // 30 minutes
        }
        
        return $top_learners;
    }
    
    /**
     * AJAX handler for rebuilding index
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
        
        // Clear dashboard specific caches
        wp_cache_flush_group($this->cache_group);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully rebuilt dashboard index for %d users.', 'wbcom-reports'), $indexed_count),
            'indexed_count' => $indexed_count
        ));
    }
}

// Initialize dashboard
new Wbcom_Reports_Dashboard();