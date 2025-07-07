<?php
/**
 * Data Collector Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Data_Collector {
    
    /**
     * Data sources
     */
    private $data_sources = array();
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = bbld_analytics()->database;
        $this->init();
        $this->register_data_sources();
        $this->init_hooks();
    }
    
    /**
     * Initialize
     */
    private function init() {
        // Initialize data sources
    }
    
    /**
     * Register data sources
     */
    private function register_data_sources() {
        // Register BuddyBoss data source
        if (class_exists('BuddyPress') || function_exists('bp_is_active')) {
            $this->data_sources['buddyboss'] = new BBLD_Analytics_BuddyBoss_Data();
        }
        
        // Register LearnDash data source
        if (class_exists('SFWD_LMS')) {
            $this->data_sources['learndash'] = new BBLD_Analytics_LearnDash_Data();
            $this->data_sources['learndash_groups'] = new BBLD_Analytics_LearnDash_Groups_Data();
        }
        
        // Register Platform data source
        $this->data_sources['platform'] = new BBLD_Analytics_Platform_Data();
        
        // Allow third-party data sources
        $this->data_sources = apply_filters('bbld_analytics_data_sources', $this->data_sources);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Real-time tracking hooks
        add_action('user_register', array($this, 'track_user_registration'));
        add_action('wp_login', array($this, 'track_user_login'), 10, 2);
        
        // LearnDash hooks
        add_action('learndash_course_completed', array($this, 'track_course_completion'), 10, 2);
        add_action('learndash_lesson_completed', array($this, 'track_lesson_completion'), 10, 2);
        add_action('learndash_quiz_completed', array($this, 'track_quiz_completion'), 10, 2);
        add_action('learndash_topic_completed', array($this, 'track_topic_completion'), 10, 2);
        
        // BuddyBoss/BuddyPress hooks
        if (function_exists('bp_is_active')) {
            add_action('bp_activity_add', array($this, 'track_bp_activity'));
            add_action('bp_activity_delete', array($this, 'track_bp_activity_delete'));
        }
    }
    
    /**
     * Collect all metrics
     */
    public function collect_all_metrics() {
        $start_time = microtime(true);
        
        // Clear caches before collection
        $this->clear_all_caches();
        
        $collected_metrics = array();
        
        foreach ($this->data_sources as $source_id => $data_source) {
            if (!$data_source->is_available()) {
                continue;
            }
            
            try {
                $metrics = $data_source->collect_metrics();
                $collected_metrics[$source_id] = $metrics;
                
                // Fire action after each source collection
                do_action("bbld_analytics_metrics_collected_{$source_id}", $metrics);
                
            } catch (Exception $e) {
                error_log("BBLD Analytics: Error collecting metrics from {$source_id}: " . $e->getMessage());
            }
        }
        
        // Store collection timestamp
        $this->database->store_metric('system', 'last_collection', current_time('mysql'));
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Store execution time metric
        $this->database->store_metric('system', 'collection_time', $execution_time);
        
        // Fire action after all metrics collected
        do_action('bbld_analytics_all_metrics_collected', $collected_metrics);
        
        return $collected_metrics;
    }
    
    /**
     * Collect hourly metrics
     */
    public function collect_hourly_metrics() {
        // Collect only frequently changing metrics
        $hourly_metrics = array();
        
        foreach ($this->data_sources as $source_id => $data_source) {
            if (!$data_source->is_available()) {
                continue;
            }
            
            try {
                // Call a specific method for hourly updates if it exists
                if (method_exists($data_source, 'collect_hourly_metrics')) {
                    $metrics = $data_source->collect_hourly_metrics();
                    $hourly_metrics[$source_id] = $metrics;
                }
                
            } catch (Exception $e) {
                error_log("BBLD Analytics: Error collecting hourly metrics from {$source_id}: " . $e->getMessage());
            }
        }
        
        return $hourly_metrics;
    }
    
    /**
     * Initial collection on plugin activation
     */
    public function initial_collection() {
        // Perform initial data collection
        $this->collect_all_metrics();
        
        // Set initial collection flag
        bbld_analytics()->update_option('initial_collection_done', true);
        
        do_action('bbld_analytics_initial_collection_completed');
    }
    
    /**
     * Get data source
     */
    public function get_data_source($source_id) {
        return isset($this->data_sources[$source_id]) ? $this->data_sources[$source_id] : null;
    }
    
    /**
     * Get all data sources
     */
    public function get_data_sources() {
        return $this->data_sources;
    }
    
    /**
     * Track user registration
     */
    public function track_user_registration($user_id) {
        $this->database->log_activity($user_id, 'user', 'registration');
        
        // Update daily registration count
        $today = current_time('Y-m-d');
        $current_count = $this->database->get_metric('platform', 'new_registrations', $today);
        $new_count = $current_count ? (int)$current_count->metric_value + 1 : 1;
        
        $this->database->store_metric('platform', 'new_registrations', $new_count, null, $today);
    }
    
    /**
     * Track user login
     */
    public function track_user_login($user_login, $user) {
        $this->database->log_activity($user->ID, 'user', 'login');
        
        // Update user's last activity
        update_user_meta($user->ID, 'bbld_last_activity', current_time('mysql'));
    }
    
    /**
     * Track course completion
     */
    public function track_course_completion($data, $user) {
        $course_id = $data['course']->ID;
        $user_id = $user->ID;
        
        $activity_data = array(
            'course_id' => $course_id,
            'course_title' => get_the_title($course_id)
        );
        
        $this->database->log_activity($user_id, 'learndash', 'course_completion', $course_id, $activity_data);
        
        // Update daily completion count
        $today = current_time('Y-m-d');
        $current_count = $this->database->get_metric('learndash', 'daily_course_completions', $today);
        $new_count = $current_count ? (int)$current_count->metric_value + 1 : 1;
        
        $this->database->store_metric('learndash', 'daily_course_completions', $new_count, null, $today);
        
        // Update group-specific metrics if user is in groups
        $user_groups = learndash_get_users_group_ids($user_id);
        foreach ($user_groups as $group_id) {
            $group_key = "group_{$group_id}_course_completions";
            $group_current = $this->database->get_metric('learndash_groups', $group_key, $today);
            $group_new = $group_current ? (int)$group_current->metric_value + 1 : 1;
            
            $this->database->store_metric('learndash_groups', $group_key, $group_new, null, $today);
        }
    }
    
    /**
     * Track lesson completion
     */
    public function track_lesson_completion($data, $user) {
        $lesson_id = $data['lesson']->ID;
        $course_id = $data['course']->ID;
        $user_id = $user->ID;
        
        $activity_data = array(
            'lesson_id' => $lesson_id,
            'lesson_title' => get_the_title($lesson_id),
            'course_id' => $course_id,
            'course_title' => get_the_title($course_id)
        );
        
        $this->database->log_activity($user_id, 'learndash', 'lesson_completion', $lesson_id, $activity_data);
        
        // Update daily lesson completion count
        $today = current_time('Y-m-d');
        $current_count = $this->database->get_metric('learndash', 'daily_lesson_completions', $today);
        $new_count = $current_count ? (int)$current_count->metric_value + 1 : 1;
        
        $this->database->store_metric('learndash', 'daily_lesson_completions', $new_count, null, $today);
    }
    
    /**
     * Track quiz completion
     */
    public function track_quiz_completion($data, $user) {
        $quiz_id = $data['quiz']->ID;
        $course_id = $data['course']->ID;
        $user_id = $user->ID;
        
        $activity_data = array(
            'quiz_id' => $quiz_id,
            'quiz_title' => get_the_title($quiz_id),
            'course_id' => $course_id,
            'score' => isset($data['percentage']) ? $data['percentage'] : 0,
            'pass' => isset($data['pass']) ? $data['pass'] : false
        );
        
        $this->database->log_activity($user_id, 'learndash', 'quiz_completion', $quiz_id, $activity_data);
        
        // Update daily quiz attempt count
        $today = current_time('Y-m-d');
        $current_count = $this->database->get_metric('learndash', 'daily_quiz_attempts', $today);
        $new_count = $current_count ? (int)$current_count->metric_value + 1 : 1;
        
        $this->database->store_metric('learndash', 'daily_quiz_attempts', $new_count, null, $today);
    }
    
    /**
     * Track topic completion
     */
    public function track_topic_completion($data, $user) {
        $topic_id = $data['topic']->ID;
        $lesson_id = $data['lesson']->ID;
        $course_id = $data['course']->ID;
        $user_id = $user->ID;
        
        $activity_data = array(
            'topic_id' => $topic_id,
            'topic_title' => get_the_title($topic_id),
            'lesson_id' => $lesson_id,
            'course_id' => $course_id
        );
        
        $this->database->log_activity($user_id, 'learndash', 'topic_completion', $topic_id, $activity_data);
    }
    
    /**
     * Track BuddyPress activity
     */
    public function track_bp_activity($activity) {
        if (!isset($activity['user_id']) || !$activity['user_id']) {
            return;
        }
        
        $activity_data = array(
            'component' => $activity['component'],
            'type' => $activity['type'],
            'content' => isset($activity['content']) ? wp_trim_words($activity['content'], 10) : ''
        );
        
        $this->database->log_activity(
            $activity['user_id'], 
            'buddypress', 
            $activity['type'], 
            isset($activity['item_id']) ? $activity['item_id'] : null, 
            $activity_data
        );
        
        // Update daily activity count
        $today = current_time('Y-m-d');
        $current_count = $this->database->get_metric('buddyboss', 'daily_posts', $today);
        $new_count = $current_count ? (int)$current_count->metric_value + 1 : 1;
        
        $this->database->store_metric('buddyboss', 'daily_posts', $new_count, null, $today);
    }
    
    /**
     * Track BuddyPress activity deletion
     */
    public function track_bp_activity_delete($activity_ids) {
        // Log activity deletion for audit purposes
        foreach ((array)$activity_ids as $activity_id) {
            $this->database->log_activity(get_current_user_id(), 'buddypress', 'activity_delete', $activity_id);
        }
    }
    
    /**
     * Manual refresh of metrics
     */
    public function manual_refresh() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to refresh metrics.', 'bbld-analytics'));
        }
        
        // Prevent multiple simultaneous refreshes
        $refresh_lock = get_transient('bbld_analytics_refresh_lock');
        if ($refresh_lock) {
            return new WP_Error('refresh_in_progress', __('Metrics refresh is already in progress.', 'bbld-analytics'));
        }
        
        // Set refresh lock
        set_transient('bbld_analytics_refresh_lock', true, 300); // 5 minute lock
        
        try {
            $result = $this->collect_all_metrics();
            
            // Clear refresh lock
            delete_transient('bbld_analytics_refresh_lock');
            
            return $result;
            
        } catch (Exception $e) {
            // Clear refresh lock on error
            delete_transient('bbld_analytics_refresh_lock');
            
            return new WP_Error('refresh_failed', $e->getMessage());
        }
    }
    
    /**
     * Get collection status
     */
    public function get_collection_status() {
        $last_collection = $this->database->get_metric('system', 'last_collection');
        $collection_time = $this->database->get_metric('system', 'collection_time');
        $initial_done = bbld_analytics()->get_option('initial_collection_done', false);
        
        return array(
            'last_collection' => $last_collection ? $last_collection->metric_value : null,
            'collection_time' => $collection_time ? (float)$collection_time->metric_value : null,
            'initial_collection_done' => $initial_done,
            'refresh_in_progress' => (bool)get_transient('bbld_analytics_refresh_lock'),
            'next_scheduled' => wp_next_scheduled('bbld_analytics_daily_update'),
            'data_sources' => array_keys($this->data_sources)
        );
    }
    
    /**
     * Clear all caches
     */
    private function clear_all_caches() {
        // Clear data source caches
        foreach ($this->data_sources as $data_source) {
            if (method_exists($data_source, 'clear_cache')) {
                $data_source->clear_cache();
            }
        }
        
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear plugin-specific transients
        $transients_to_clear = array(
            'bbld_analytics_dashboard_data',
            'bbld_analytics_widget_data',
            'bbld_analytics_course_data',
            'bbld_analytics_group_data'
        );
        
        foreach ($transients_to_clear as $transient) {
            delete_transient($transient);
        }
    }
    
    /**
     * Get aggregated dashboard data
     */
    public function get_dashboard_data($force_refresh = false) {
        $cache_key = 'bbld_analytics_dashboard_data';
        
        if (!$force_refresh) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $dashboard_data = array();
        
        foreach ($this->data_sources as $source_id => $data_source) {
            if (!$data_source->is_available()) {
                continue;
            }
            
            try {
                $dashboard_data[$source_id] = $data_source->get_analytics_data('30d');
            } catch (Exception $e) {
                error_log("BBLD Analytics: Error getting dashboard data from {$source_id}: " . $e->getMessage());
                $dashboard_data[$source_id] = array();
            }
        }
        
        // Cache for 5 minutes
        set_transient($cache_key, $dashboard_data, 300);
        
        return $dashboard_data;
    }
    
    /**
     * Get summary metrics
     */
    public function get_summary_metrics() {
        $summary = array(
            'total_groups' => 0,
            'total_learners' => 0,
            'active_learners' => 0,
            'completion_rate' => 0
        );
        
        // Get LearnDash Groups data
        if (isset($this->data_sources['learndash_groups'])) {
            $groups_data = $this->data_sources['learndash_groups']->get_analytics_data('30d');
            
            if (isset($groups_data['total_groups'])) {
                $summary['total_groups'] = $groups_data['total_groups'];
            }
            
            if (isset($groups_data['total_students'])) {
                $summary['total_learners'] = $groups_data['total_students'];
            }
            
            if (isset($groups_data['active_learners'])) {
                $summary['active_learners'] = $groups_data['active_learners'];
            }
            
            if (isset($groups_data['completion_rate'])) {
                $summary['completion_rate'] = $groups_data['completion_rate'];
            }
        }
        
        return $summary;
    }
    
    /**
     * Schedule one-time collection
     */
    public function schedule_collection($delay = 60) {
        wp_schedule_single_event(time() + $delay, 'bbld_analytics_manual_collection');
        
        // Add hook for manual collection
        add_action('bbld_analytics_manual_collection', array($this, 'collect_all_metrics'));
    }
    
    /**
     * Get data freshness
     */
    public function get_data_freshness() {
        $last_collection = $this->database->get_metric('system', 'last_collection');
        
        if (!$last_collection) {
            return array(
                'status' => 'never',
                'message' => __('Data has never been collected.', 'bbld-analytics'),
                'last_collection' => null
            );
        }
        
        $last_time = strtotime($last_collection->metric_value);
        $time_diff = time() - $last_time;
        
        if ($time_diff < 3600) { // Less than 1 hour
            $status = 'fresh';
            $message = __('Data is fresh.', 'bbld-analytics');
        } elseif ($time_diff < 86400) { // Less than 24 hours
            $status = 'good';
            $message = sprintf(__('Data is %s old.', 'bbld-analytics'), human_time_diff($last_time));
        } else { // More than 24 hours
            $status = 'stale';
            $message = sprintf(__('Data is %s old and may be outdated.', 'bbld-analytics'), human_time_diff($last_time));
        }
        
        return array(
            'status' => $status,
            'message' => $message,
            'last_collection' => $last_collection->metric_value,
            'time_diff' => $time_diff
        );
    }
    
    /**
     * Validate data sources
     */
    public function validate_data_sources() {
        $validation = array();
        
        foreach ($this->data_sources as $source_id => $data_source) {
            $validation[$source_id] = array(
                'available' => $data_source->is_available(),
                'class' => get_class($data_source),
                'methods' => get_class_methods($data_source)
            );
        }
        
        return $validation;
    }
    
    /**
     * Error recovery for failed collections
     */
    public function recover_from_failed_collection() {
        // Clear failed collection lock
        delete_transient('bbld_analytics_refresh_lock');
        
        // Reset collection status
        $this->database->store_metric('system', 'collection_status', 'recovered');
        
        // Try lightweight collection
        try {
            return $this->collect_essential_metrics_only();
        } catch (Exception $e) {
            error_log('BBLD Analytics: Recovery failed - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lightweight metrics collection for recovery
     */
    private function collect_essential_metrics_only() {
        $metrics = array();
        
        // Only collect basic counts
        if (class_exists('SFWD_LMS')) {
            $metrics['total_courses'] = wp_count_posts('sfwd-courses')->publish;
            $metrics['total_lessons'] = wp_count_posts('sfwd-lessons')->publish;
        }
        
        $metrics['total_users'] = count_users()['total_users'];
        $metrics['collection_time'] = current_time('mysql');
        
        foreach ($metrics as $key => $value) {
            $this->database->store_metric('system', $key, $value);
        }
        
        return $metrics;
    }
}