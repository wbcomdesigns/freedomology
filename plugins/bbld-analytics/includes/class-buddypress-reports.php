<?php
/**
 * Enhanced BuddyPress Reports Class with Full Indexing and Caching Support
 * 
 * @package Wbcom_Reports
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wbcom_Reports_BuddyPress {
    
    private $index;
    private $cache_group = 'wbcom_reports_bp';
    
    public function __construct() {
        // Initialize indexing system
        $this->index = new Wbcom_Reports_Index();
        
        add_action('wp_ajax_get_buddypress_stats', array($this, 'ajax_get_buddypress_stats'));
        add_action('wp_ajax_export_buddypress_stats', array($this, 'ajax_export_buddypress_stats'));
        add_action('wp_ajax_rebuild_bp_index', array($this, 'ajax_rebuild_index'));
        
        // Hook into BuddyPress activity updates for real-time indexing
        add_action('bp_activity_after_save', array($this, 'update_activity_index'), 10, 1);
        add_action('bp_activity_delete', array($this, 'update_activity_index_on_delete'), 10, 1);
        add_action('bp_activity_comment_posted', array($this, 'update_comment_index'), 10, 2);
        add_action('bp_activity_comment_deleted', array($this, 'update_comment_index_on_delete'), 10, 1);
    }
    
    /**
     * Render BuddyPress reports page with index status
     */
    public static function render_page() {
        $index = new Wbcom_Reports_Index();
        $index_stats = $index->get_index_stats();
        $needs_rebuilding = $index->needs_rebuilding();
        $cache_stats = $index->get_cache_stats();
        ?>
        <div class="wrap wbcom-reports-wrap">
            <h1><?php _e('BuddyPress Activity Reports', 'wbcom-reports'); ?></h1>
            
            <?php if (!Wbcom_Reports_Helpers::is_buddypress_active()): ?>
                <div class="notice notice-warning">
                    <p><?php _e('BuddyPress plugin is not active. Please activate BuddyPress to view activity reports.', 'wbcom-reports'); ?></p>
                </div>
            <?php else: ?>
            
            <!-- Index Status -->
            <div class="wbcom-index-status">
                <div class="index-info">
                    <div class="index-details">
                        <p>
                            <strong><?php _e('Index Status:', 'wbcom-reports'); ?></strong>
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
                            <p><?php _e('Index needs rebuilding for optimal performance.', 'wbcom-reports'); ?></p>
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
                        <h3><?php _e('Total Comments', 'wbcom-reports'); ?></h3>
                        <span id="total-comments" class="stat-number">Loading...</span>
                    </div>
                    <div class="wbcom-stat-box">
                        <h3><?php _e('New Users (30 days)', 'wbcom-reports'); ?></h3>
                        <span id="total-likes" class="stat-number">Loading...</span>
                    </div>
                </div>
                
                <div class="wbcom-stats-actions">
                    <button id="refresh-buddypress-stats" class="button button-primary">
                        <?php _e('Refresh Stats', 'wbcom-reports'); ?>
                    </button>
                    <button id="export-buddypress-stats" class="button">
                        <?php _e('Export CSV', 'wbcom-reports'); ?>
                    </button>
                    <button id="rebuild-bp-index" class="button <?php echo $needs_rebuilding ? 'needs-rebuild' : ''; ?>" 
                            title="<?php _e('Rebuild user index for optimal performance', 'wbcom-reports'); ?>">
                        <?php _e('Rebuild Index', 'wbcom-reports'); ?>
                        <?php if ($needs_rebuilding): ?>
                            <span class="dashicons dashicons-warning" style="color: #ff6b6b; margin-left: 5px;"></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="wbcom-user-stats">
                    <h2><?php _e('User Activity Statistics', 'wbcom-reports'); ?></h2>
                    
                    <!-- Search Controls -->
                    <div class="search-controls">
                        <div class="search-input-wrapper">
                            <input type="text" id="user-search" placeholder="<?php _e('Search users by name or username...', 'wbcom-reports'); ?>" class="regular-text">
                            <button type="button" id="clear-search" class="search-clear" title="<?php _e('Clear search', 'wbcom-reports'); ?>">&times;</button>
                        </div>
                        <button id="apply-filters" class="button"><?php _e('Apply Filters', 'wbcom-reports'); ?></button>
                    </div>
                    
                    <!-- Search Results Info -->
                    <div id="search-info" style="display: none;"></div>
                    
                    <div class="wbcom-filters">
                        <select id="activity-filter">
                            <option value="all"><?php _e('All Users', 'wbcom-reports'); ?></option>
                            <option value="active"><?php _e('Active Users (Last 30 days)', 'wbcom-reports'); ?></option>
                            <option value="test_users"><?php _e('Test Users (LDTT)', 'wbcom-reports'); ?></option>
                            <option value="real_users"><?php _e('Real Users (Non-Test)', 'wbcom-reports'); ?></option>
                        </select>
                        <input type="date" id="date-from" placeholder="<?php _e('From Date', 'wbcom-reports'); ?>">
                        <input type="date" id="date-to" placeholder="<?php _e('To Date', 'wbcom-reports'); ?>">
                    </div>
                    
                    <table id="user-activity-stats-table" class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="sortable-header" data-sort="display_name"><?php _e('Display Name', 'wbcom-reports'); ?></th>
                                <th class="sortable-header" data-sort="user_login"><?php _e('Username', 'wbcom-reports'); ?></th>
                                <th><?php _e('User Type', 'wbcom-reports'); ?></th>
                                <th class="sortable-header" data-sort="registration_date"><?php _e('Registration Date', 'wbcom-reports'); ?></th>
                                <th class="sortable-header" data-sort="last_login"><?php _e('Last Login', 'wbcom-reports'); ?></th>
                                <th class="sortable-header" data-sort="activity_count"><?php _e('Activity Count', 'wbcom-reports'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6"><?php _e('Loading...', 'wbcom-reports'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="wbcom-pagination">
                        <button id="prev-page" class="button" disabled><?php _e('Previous', 'wbcom-reports'); ?></button>
                        <span id="page-info">Page 1 of 1</span>
                        <button id="next-page" class="button" disabled><?php _e('Next', 'wbcom-reports'); ?></button>
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
        </style>
        <?php
    }
    
    /**
     * AJAX handler for BuddyPress stats using indexed data
     */
    public function ajax_get_buddypress_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
            wp_send_json_error('BuddyPress not active');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'activity_count';
        $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'desc';
        
        // Use indexed data for user stats
        $indexed_users = $this->index->get_indexed_users(array(
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
            'filter' => $filter,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
        
        // Transform indexed data to match expected format
        $user_stats = array();
        foreach ($indexed_users['users'] as $user) {
            $user_stats[] = array(
                'user_id' => $user['user_id'],
                'display_name' => $user['display_name'],
                'user_login' => $user['user_login'],
                'user_type' => $this->format_user_type($user['user_type'], $user['is_test_user']),
                'registration_date' => $user['user_registered'],
                'last_login' => $user['last_login'] ?: 'Never',
                'activity_count' => $user['activity_count']
            );
        }
        
        $stats = array(
            'total_users' => $this->get_cached_total_users(),
            'total_activities' => $this->get_cached_total_activities(),
            'total_comments' => $this->get_cached_total_comments(),
            'total_likes' => $this->get_cached_new_users_30_days(),
            'user_stats' => $user_stats,
            'pagination' => array(
                'current_page' => $indexed_users['page'],
                'total_pages' => $indexed_users['total_pages'],
                'total_users' => $indexed_users['total_count'],
                'filtered_users' => $indexed_users['total_count'],
                'per_page' => $indexed_users['per_page']
            ),
            'search_info' => array(
                'active_search' => !empty($search) || $filter !== 'all',
                'search_term' => $search,
                'filtered_count' => $indexed_users['total_count'],
                'total_count' => $this->get_cached_total_users()
            ),
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
            global $wpdb;
            $bp_activity_table = $wpdb->prefix . 'bp_activity';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$bp_activity_table}'") == $bp_activity_table) {
                $total = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$bp_activity_table} 
                    WHERE component IN ('activity', 'groups', 'friends', 'blogs')
                ");
            } else {
                $total = 0;
            }
            
            wp_cache_set($cache_key, intval($total), $this->cache_group, 1800); // 30 minutes
        }
        
        return $total;
    }
    
    /**
     * Get cached total comments
     */
    private function get_cached_total_comments() {
        $cache_key = 'total_comments';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            global $wpdb;
            $bp_activity_table = $wpdb->prefix . 'bp_activity';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$bp_activity_table}'") == $bp_activity_table) {
                $total = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$bp_activity_table} 
                    WHERE type = 'activity_comment'
                ");
            } else {
                $total = 0;
            }
            
            wp_cache_set($cache_key, intval($total), $this->cache_group, 1800); // 30 minutes
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
     * Format user type for display
     */
    private function format_user_type($user_type, $is_test_user) {
        if ($is_test_user) {
            if (strpos($user_type, 'test_') === 0) {
                $type = str_replace('test_', '', $user_type);
                return 'Test (' . ucfirst(str_replace('_', ' ', $type)) . ')';
            }
            return 'Test User';
        }
        
        return 'Regular User';
    }
    
    /**
     * AJAX handler for exporting BuddyPress stats
     */
    public function ajax_export_buddypress_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_reports_nonce')) {
            wp_die('Security check failed');
        }
        
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'activity_count';
        $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'desc';
        
        // Get all users for export (no pagination limit)
        $indexed_users = $this->index->get_indexed_users(array(
            'page' => 1,
            'per_page' => 0, // No limit
            'search' => $search,
            'filter' => $filter,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
        
        // Prepare data for CSV export
        $export_data = array();
        foreach ($indexed_users['users'] as $user) {
            $export_data[] = array(
                'Display Name' => $user['display_name'],
                'Username' => $user['user_login'],
                'Email' => $user['user_email'],
                'User Type' => $this->format_user_type($user['user_type'], $user['is_test_user']),
                'Registration Date' => $user['user_registered'],
                'Last Login' => $user['last_login'] ?: 'Never',
                'Activity Count' => $user['activity_count'],
                'Comment Count' => $user['comment_count'],
                'Last Activity' => $user['last_activity'] ?: 'Never'
            );
        }
        
        $filename = 'buddypress-user-activity-' . date('Y-m-d-H-i-s') . '.csv';
        Wbcom_Reports_Helpers::export_to_csv($export_data, $filename);
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
        
        // Clear BuddyPress specific caches
        wp_cache_flush_group($this->cache_group);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully rebuilt index for %d users.', 'wbcom-reports'), $indexed_count),
            'indexed_count' => $indexed_count
        ));
    }
    
    /**
     * Update activity index when activity is saved
     */
    public function update_activity_index($activity) {
        if ($activity && isset($activity->user_id)) {
            $this->index->index_user($activity->user_id);
            // Clear relevant caches
            wp_cache_delete('total_activities', $this->cache_group);
        }
    }
    
    /**
     * Update activity index when activity is deleted
     */
    public function update_activity_index_on_delete($args) {
        if (isset($args['user_id'])) {
            $this->index->index_user($args['user_id']);
            // Clear relevant caches
            wp_cache_delete('total_activities', $this->cache_group);
        }
    }
    
    /**
     * Update comment index when comment is posted
     */
    public function update_comment_index($comment_id, $params) {
        if (isset($params['user_id'])) {
            $this->index->index_user($params['user_id']);
            // Clear relevant caches
            wp_cache_delete('total_comments', $this->cache_group);
        }
    }
    
    /**
     * Update comment index when comment is deleted
     */
    public function update_comment_index_on_delete($comment_id) {
        // Get comment data before deletion
        global $wpdb;
        $bp_activity_table = $wpdb->prefix . 'bp_activity';
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$bp_activity_table} WHERE id = %d",
            $comment_id
        ));
        
        if ($user_id) {
            $this->index->index_user($user_id);
            // Clear relevant caches
            wp_cache_delete('total_comments', $this->cache_group);
        }
    }
}

// Initialize BuddyPress reports
new Wbcom_Reports_BuddyPress();