<?php
/**
 * Reports Indexing and Caching System
 * 
 * @package Wbcom_Reports
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wbcom_Reports_Index {
    
    private $cache_group = 'wbcom_reports';
    private $cache_duration = 3600; // 1 hour
    private $index_table;
    
    public function __construct() {
        global $wpdb;
        $this->index_table = $wpdb->prefix . 'wbcom_reports_index';
        
        // Initialize hooks
        add_action('init', array($this, 'maybe_create_index_table'));
        add_action('wp_ajax_rebuild_reports_index', array($this, 'ajax_rebuild_index'));
        add_action('wbcom_reports_rebuild_index', array($this, 'rebuild_user_index'));
        
        // Auto-update hooks
        add_action('bp_activity_add', array($this, 'update_user_activity_index'), 10, 1);
        add_action('bp_activity_delete', array($this, 'update_user_activity_index'), 10, 1);
        add_action('learndash_course_completed', array($this, 'update_user_learning_index'), 10, 1);
        add_action('learndash_lesson_completed', array($this, 'update_user_learning_index'), 10, 1);
        add_action('user_register', array($this, 'index_new_user'), 10, 1);
        add_action('wp_login', array($this, 'update_user_login_index'), 10, 2);
        
        // Schedule daily index rebuild
        if (!wp_next_scheduled('wbcom_reports_rebuild_index')) {
            wp_schedule_event(time(), 'daily', 'wbcom_reports_rebuild_index');
        }
    }
    
    /**
     * Create index table if it doesn't exist
     */
    public function maybe_create_index_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->index_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            activity_count int(11) DEFAULT 0,
            comment_count int(11) DEFAULT 0,
            enrolled_courses int(11) DEFAULT 0,
            completed_courses int(11) DEFAULT 0,
            in_progress_courses int(11) DEFAULT 0,
            avg_progress decimal(5,2) DEFAULT 0.00,
            last_activity datetime DEFAULT NULL,
            last_login datetime DEFAULT NULL,
            user_type varchar(50) DEFAULT 'regular',
            is_test_user tinyint(1) DEFAULT 0,
            indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY idx_activity_count (activity_count),
            KEY idx_enrolled_courses (enrolled_courses),
            KEY idx_completed_courses (completed_courses),
            KEY idx_last_activity (last_activity),
            KEY idx_last_login (last_login),
            KEY idx_user_type (user_type),
            KEY idx_is_test_user (is_test_user),
            KEY idx_updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get indexed user data with caching
     */
    public function get_indexed_users($args = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'filter' => 'all',
            'sort_by' => 'activity_count',
            'sort_order' => 'desc',
            'date_from' => '',
            'date_to' => '',
            'fields' => '*'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Create cache key based on arguments
        $cache_key = 'indexed_users_' . md5(serialize($args));
        
        // Try to get from cache first
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        global $wpdb;
        
        // Build SQL query
        $where_conditions = array('1=1');
        $join_clauses = array();
        
        // Join with users table for search and basic user data
        $join_clauses[] = "LEFT JOIN {$wpdb->users} u ON ri.user_id = u.ID";
        
        // Search conditions
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(u.user_login LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)",
                $search, $search, $search
            );
        }
        
        // Filter conditions
        switch ($args['filter']) {
            case 'test_users':
                $where_conditions[] = 'ri.is_test_user = 1';
                break;
            case 'real_users':
                $where_conditions[] = 'ri.is_test_user = 0';
                break;
            case 'active':
                $where_conditions[] = 'ri.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        // Date range filter
        if (!empty($args['date_from']) && !empty($args['date_to'])) {
            $where_conditions[] = $wpdb->prepare(
                "u.user_registered BETWEEN %s AND %s",
                $args['date_from'], $args['date_to']
            );
        }
        
        // Build ORDER BY clause
        $valid_sort_fields = array(
            'activity_count' => 'ri.activity_count',
            'enrolled_courses' => 'ri.enrolled_courses',
            'completed_courses' => 'ri.completed_courses',
            'last_activity' => 'ri.last_activity',
            'last_login' => 'ri.last_login',
            'display_name' => 'u.display_name',
            'user_login' => 'u.user_login',
            'registration_date' => 'u.user_registered'
        );
        
        $sort_field = isset($valid_sort_fields[$args['sort_by']]) ? 
                     $valid_sort_fields[$args['sort_by']] : 'ri.activity_count';
        
        $sort_order = strtoupper($args['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Handle NULL values in sorting
        if (in_array($args['sort_by'], array('last_activity', 'last_login'))) {
            $order_by = "ORDER BY {$sort_field} IS NULL, {$sort_field} {$sort_order}";
        } else {
            $order_by = "ORDER BY {$sort_field} {$sort_order}";
        }
        
        // Build LIMIT clause
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = $args['per_page'] > 0 ? "LIMIT {$offset}, {$args['per_page']}" : '';
        
        // Select fields
        $select_fields = $args['fields'] === '*' ? 
            'ri.*, u.user_login, u.display_name, u.user_email, u.user_registered' :
            $args['fields'];
        
        // Build final query
        $join_clause = implode(' ', $join_clauses);
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT {$select_fields}
            FROM {$this->index_table} ri
            {$join_clause}
            {$where_clause}
            {$order_by}
            {$limit}
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*)
            FROM {$this->index_table} ri
            {$join_clause}
            {$where_clause}
        ";
        
        $total_count = $wpdb->get_var($count_query);
        
        $result = array(
            'users' => $results,
            'total_count' => intval($total_count),
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'total_pages' => $args['per_page'] > 0 ? ceil($total_count / $args['per_page']) : 1
        );
        
        // Cache the result
        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_duration);
        
        return $result;
    }
    
    /**
     * Index a single user's data
     */
    public function index_user($user_id) {
        global $wpdb;
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        // Calculate BuddyPress stats
        $activity_count = $this->calculate_user_activity_count($user_id);
        $comment_count = $this->calculate_user_comment_count($user_id);
        $last_activity = $this->get_user_last_activity($user_id);
        
        // Calculate LearnDash stats
        $learning_stats = $this->calculate_user_learning_stats($user_id);
        
        // Get user type and test user status
        $is_test_user = get_user_meta($user_id, '_ldtt_test_user', true) ? 1 : 0;
        $user_type_meta = get_user_meta($user_id, '_ldtt_user_type', true);
        $user_type = $is_test_user && $user_type_meta ? 
                    'test_' . $user_type_meta : 'regular';
        
        // Get last login
        $last_login_timestamp = get_user_meta($user_id, 'last_login', true);
        $last_login = $last_login_timestamp ? 
                     date('Y-m-d H:i:s', $last_login_timestamp) : null;
        
        // Prepare data for insertion/update
        $data = array(
            'user_id' => $user_id,
            'activity_count' => $activity_count,
            'comment_count' => $comment_count,
            'enrolled_courses' => $learning_stats['enrolled_courses'],
            'completed_courses' => $learning_stats['completed_courses'],
            'in_progress_courses' => $learning_stats['in_progress_courses'],
            'avg_progress' => $learning_stats['avg_progress'],
            'last_activity' => $last_activity,
            'last_login' => $last_login,
            'user_type' => $user_type,
            'is_test_user' => $is_test_user,
            'updated_at' => current_time('mysql')
        );
        
        // Insert or update
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->index_table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $result = $wpdb->update(
                $this->index_table,
                $data,
                array('user_id' => $user_id),
                array('%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            $data['indexed_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $this->index_table,
                $data,
                array('%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        // Clear related caches
        $this->clear_user_caches($user_id);
        
        return $result !== false;
    }
    
    /**
     * Rebuild entire user index
     */
    public function rebuild_user_index() {
        global $wpdb;
        
        // Get all users in batches
        $batch_size = 100;
        $offset = 0;
        $indexed_count = 0;
        
        do {
            $users = get_users(array(
                'number' => $batch_size,
                'offset' => $offset,
                'fields' => 'ID'
            ));
            
            foreach ($users as $user_id) {
                if ($this->index_user($user_id)) {
                    $indexed_count++;
                }
            }
            
            $offset += $batch_size;
            
            // Prevent timeout
            if (function_exists('set_time_limit')) {
                set_time_limit(30);
            }
            
        } while (count($users) === $batch_size);
        
        // Clean up orphaned index entries
        $wpdb->query("
            DELETE ri FROM {$this->index_table} ri
            LEFT JOIN {$wpdb->users} u ON ri.user_id = u.ID
            WHERE u.ID IS NULL
        ");
        
        // Clear all caches
        wp_cache_flush_group($this->cache_group);
        
        return $indexed_count;
    }
    
    /**
     * Calculate user activity count
     */
    private function calculate_user_activity_count($user_id) {
        if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
            return 0;
        }
        
        global $wpdb;
        $bp_activity_table = $wpdb->prefix . 'bp_activity';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bp_activity_table}'") != $bp_activity_table) {
            return 0;
        }
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$bp_activity_table} 
            WHERE user_id = %d 
            AND component = 'activity' 
            AND type = 'activity_update'
        ", $user_id));
        
        return intval($count);
    }
    
    /**
     * Calculate user comment count
     */
    private function calculate_user_comment_count($user_id) {
        if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
            return 0;
        }
        
        global $wpdb;
        $bp_activity_table = $wpdb->prefix . 'bp_activity';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bp_activity_table}'") != $bp_activity_table) {
            return 0;
        }
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$bp_activity_table} 
            WHERE user_id = %d 
            AND type = 'activity_comment'
        ", $user_id));
        
        return intval($count);
    }
    
    /**
     * Get user last activity
     */
    private function get_user_last_activity($user_id) {
        if (!Wbcom_Reports_Helpers::is_buddypress_active()) {
            return null;
        }
        
        global $wpdb;
        $bp_activity_table = $wpdb->prefix . 'bp_activity';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bp_activity_table}'") != $bp_activity_table) {
            return null;
        }
        
        $last_activity = $wpdb->get_var($wpdb->prepare("
            SELECT date_recorded 
            FROM {$bp_activity_table} 
            WHERE user_id = %d 
            ORDER BY date_recorded DESC 
            LIMIT 1
        ", $user_id));
        
        return $last_activity;
    }
    
    /**
     * Calculate user learning statistics
     */
    private function calculate_user_learning_stats($user_id) {
        $stats = array(
            'enrolled_courses' => 0,
            'completed_courses' => 0,
            'in_progress_courses' => 0,
            'avg_progress' => 0.00
        );
        
        if (!Wbcom_Reports_Helpers::is_learndash_active()) {
            return $stats;
        }
        
        global $wpdb;
        
        // Get LDTT course progress data
        $ldtt_progress = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE user_id = %d 
            AND meta_key LIKE '_ldtt_progress_course_%%'
        ", $user_id));
        
        $total_progress = 0;
        $enrolled_courses = 0;
        $completed_courses = 0;
        
        // Process LDTT progress data
        foreach ($ldtt_progress as $progress) {
            if (preg_match('/_ldtt_progress_course_(\d+)/', $progress->meta_key, $matches)) {
                $progress_data = maybe_unserialize($progress->meta_value);
                
                if (is_array($progress_data) && isset($progress_data['completion_rate'])) {
                    $completion_rate = floatval($progress_data['completion_rate']);
                    $enrolled_courses++;
                    $total_progress += $completion_rate;
                    
                    if ($completion_rate >= 100) {
                        $completed_courses++;
                    }
                }
            }
        }
        
        // Get regular LearnDash enrollment data
        $course_access = get_user_meta($user_id, '_sfwd-course_progress', true);
        if (is_array($course_access)) {
            foreach ($course_access as $course_id => $progress_info) {
                // Skip if already counted in LDTT data
                $already_counted = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->usermeta} 
                    WHERE user_id = %d AND meta_key = %s
                ", $user_id, '_ldtt_progress_course_' . $course_id));
                
                if (!$already_counted) {
                    $completion_percentage = 0;
                    
                    if (is_array($progress_info) && isset($progress_info['total']) && $progress_info['total'] > 0) {
                        $completed = isset($progress_info['completed']) ? intval($progress_info['completed']) : 0;
                        $total = intval($progress_info['total']);
                        $completion_percentage = ($completed / $total) * 100;
                    }
                    
                    $enrolled_courses++;
                    $total_progress += $completion_percentage;
                    
                    if ($completion_percentage >= 100) {
                        $completed_courses++;
                    }
                }
            }
        }
        
        // Calculate final stats
        $stats['enrolled_courses'] = $enrolled_courses;
        $stats['completed_courses'] = $completed_courses;
        $stats['in_progress_courses'] = max(0, $enrolled_courses - $completed_courses);
        $stats['avg_progress'] = $enrolled_courses > 0 ? 
                                round($total_progress / $enrolled_courses, 2) : 0.00;
        
        return $stats;
    }
    
    /**
     * Update user activity index when activity is added/deleted
     */
    public function update_user_activity_index($activity) {
        if (is_object($activity) && isset($activity->user_id)) {
            $this->index_user($activity->user_id);
        } elseif (is_array($activity) && isset($activity['user_id'])) {
            $this->index_user($activity['user_id']);
        }
    }
    
    /**
     * Update user learning index when course/lesson is completed
     */
    public function update_user_learning_index($data) {
        $user_id = null;
        
        if (is_array($data) && isset($data['user'])) {
            $user_id = $data['user']->ID;
        } elseif (is_array($data) && isset($data['user_id'])) {
            $user_id = $data['user_id'];
        }
        
        if ($user_id) {
            $this->index_user($user_id);
        }
    }
    
    /**
     * Index new user
     */
    public function index_new_user($user_id) {
        $this->index_user($user_id);
    }
    
    /**
     * Update user login index
     */
    public function update_user_login_index($user_login, $user) {
        $this->index_user($user->ID);
    }
    
    /**
     * Clear user-related caches
     */
    private function clear_user_caches($user_id = null) {
        // Clear specific user caches
        if ($user_id) {
            wp_cache_delete('user_stats_' . $user_id, $this->cache_group);
        }
        
        // Clear general report caches
        wp_cache_flush_group($this->cache_group);
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
        
        $indexed_count = $this->rebuild_user_index();
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully indexed %d users.', 'wbcom-reports'), $indexed_count),
            'indexed_count' => $indexed_count
        ));
    }
    
    /**
     * Get index statistics
     */
    public function get_index_stats() {
        global $wpdb;
        
        $stats = wp_cache_get('index_stats', $this->cache_group);
        if ($stats !== false) {
            return $stats;
        }
        
        $total_indexed = $wpdb->get_var("SELECT COUNT(*) FROM {$this->index_table}");
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $last_update = $wpdb->get_var("SELECT MAX(updated_at) FROM {$this->index_table}");
        
        $stats = array(
            'total_indexed' => intval($total_indexed),
            'total_users' => intval($total_users),
            'coverage_percentage' => $total_users > 0 ? round(($total_indexed / $total_users) * 100, 1) : 0,
            'last_update' => $last_update
        );
        
        wp_cache_set('index_stats', $stats, $this->cache_group, 300); // 5 minutes
        
        return $stats;
    }
    
    /**
     * Check if index needs rebuilding
     */
    public function needs_rebuilding() {
        $stats = $this->get_index_stats();
        
        // Rebuild if less than 95% coverage or last update > 24 hours ago
        if ($stats['coverage_percentage'] < 95) {
            return true;
        }
        
        if ($stats['last_update']) {
            $last_update_time = strtotime($stats['last_update']);
            $twenty_four_hours_ago = time() - (24 * 60 * 60);
            
            if ($last_update_time < $twenty_four_hours_ago) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        // This would depend on your caching implementation
        // For object cache, you might need to implement cache-specific methods
        return array(
            'cache_group' => $this->cache_group,
            'cache_duration' => $this->cache_duration,
            'cache_enabled' => wp_using_ext_object_cache()
        );
    }
}

// Initialize the indexing system
new Wbcom_Reports_Index();