<?php
/**
 * AJAX Handler Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Admin AJAX actions
        add_action('wp_ajax_bbld_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_bbld_get_widget_data', array($this, 'get_widget_data'));
        add_action('wp_ajax_bbld_get_course_engagement_data', array($this, 'get_course_engagement_data'));
        add_action('wp_ajax_bbld_get_group_performance_data', array($this, 'get_group_performance_data'));
        add_action('wp_ajax_bbld_refresh_metrics', array($this, 'refresh_metrics'));
        add_action('wp_ajax_bbld_save_widget_settings', array($this, 'save_widget_settings'));
        add_action('wp_ajax_bbld_toggle_widget', array($this, 'toggle_widget'));
        add_action('wp_ajax_bbld_get_widget_config', array($this, 'get_widget_config'));
        add_action('wp_ajax_bbld_update_widget_config', array($this, 'update_widget_config'));
        add_action('wp_ajax_bbld_get_dashboard_summary', array($this, 'get_dashboard_summary'));
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $source_type = sanitize_text_field($_POST['source_type']);
        $period = sanitize_text_field($_POST['period']) ?: '30d';
        
        $data_collector = bbld_analytics()->data_collector;
        $data_source = $data_collector->get_data_source($source_type);
        
        if (!$data_source) {
            wp_send_json_error(__('Data source not found', 'bbld-analytics'));
        }
        
        if (!$data_source->is_available()) {
            wp_send_json_error(__('Data source not available', 'bbld-analytics'));
        }
        
        try {
            $data = $data_source->get_analytics_data($period);
            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get widget data
     */
    public function get_widget_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $period = sanitize_text_field($_POST['period']) ?: '30d';
        
        $widget_manager = bbld_analytics()->widget_manager;
        $result = $widget_manager->get_widget_data($widget_id, $period);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get course engagement data
     */
    public function get_course_engagement_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $period = sanitize_text_field($_POST['period']) ?: '30d';
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
        
        $data_collector = bbld_analytics()->data_collector;
        $learndash_data = $data_collector->get_data_source('learndash');
        
        if (!$learndash_data || !$learndash_data->is_available()) {
            wp_send_json_error(__('LearnDash data source not available', 'bbld-analytics'));
        }
        
        try {
            // Get engagement data
            $engagement_data = $this->get_course_engagement_timeline($period, $course_id);
            wp_send_json_success($engagement_data);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get group performance data
     */
    public function get_group_performance_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $group_id = intval($_POST['group_id']);
        $period = sanitize_text_field($_POST['period']) ?: '30d';
        
        if (!$group_id) {
            wp_send_json_error(__('Group ID is required', 'bbld-analytics'));
        }
        
        try {
            $performance_data = $this->get_group_detailed_performance($group_id, $period);
            wp_send_json_success($performance_data);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Refresh metrics manually
     */
    public function refresh_metrics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $data_collector = bbld_analytics()->data_collector;
        $result = $data_collector->manual_refresh();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Clear widget caches
        $widget_manager = bbld_analytics()->widget_manager;
        $widget_manager->clear_all_caches();
        
        wp_send_json_success(array(
            'message' => __('Data refreshed successfully', 'bbld-analytics'),
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Save widget settings
     */
    public function save_widget_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $widget_settings = isset($_POST['widget_settings']) ? $_POST['widget_settings'] : array();
        
        $widget_manager = bbld_analytics()->widget_manager;
        $saved_count = 0;
        
        foreach ($widget_settings as $widget_id => $settings) {
            $widget = $widget_manager->get_widget($widget_id);
            
            if (!$widget) {
                continue;
            }
            
            // Update enabled status
            if (isset($settings['enabled'])) {
                $widget->set_enabled((bool)$settings['enabled']);
            }
            
            // Update configuration
            if (isset($settings['config'])) {
                $widget->update_config($settings['config']);
            }
            
            $saved_count++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d widget settings saved', 'bbld-analytics'), $saved_count),
            'saved_count' => $saved_count
        ));
    }
    
    /**
     * Toggle widget status
     */
    public function toggle_widget() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $enabled = (bool)$_POST['enabled'];
        
        $widget_manager = bbld_analytics()->widget_manager;
        $result = $widget_manager->toggle_widget($widget_id, $enabled);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => $enabled ? __('Widget enabled', 'bbld-analytics') : __('Widget disabled', 'bbld-analytics'),
            'widget_id' => $widget_id,
            'enabled' => $enabled
        ));
    }
    
    /**
     * Get widget configuration
     */
    public function get_widget_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        
        $widget_manager = bbld_analytics()->widget_manager;
        $widget = $widget_manager->get_widget($widget_id);
        
        if (!$widget) {
            wp_send_json_error(__('Widget not found', 'bbld-analytics'));
        }
        
        $config_fields = $widget_manager->get_widget_config_fields($widget_id);
        
        wp_send_json_success(array(
            'widget_id' => $widget_id,
            'title' => $widget->get_title(),
            'config' => $widget->get_config(),
            'fields' => $config_fields
        ));
    }
    
    /**
     * Update widget configuration
     */
    public function update_widget_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $config = isset($_POST['config']) ? $_POST['config'] : array();
        
        $widget_manager = bbld_analytics()->widget_manager;
        
        // Validate configuration
        $validated_config = $widget_manager->validate_widget_config($widget_id, $config);
        
        if (is_wp_error($validated_config)) {
            wp_send_json_error($validated_config->get_error_message());
        }
        
        // Update configuration
        $result = $widget_manager->update_widget_config($widget_id, $validated_config);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Widget configuration updated', 'bbld-analytics'),
            'widget_id' => $widget_id,
            'config' => $validated_config
        ));
    }
    
    /**
     * Get dashboard summary
     */
    public function get_dashboard_summary() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bbld_analytics_nonce')) {
            wp_die(__('Security check failed', 'bbld-analytics'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bbld-analytics'));
        }
        
        $data_collector = bbld_analytics()->data_collector;
        
        try {
            $summary = array(
                'metrics' => $data_collector->get_summary_metrics(),
                'status' => $data_collector->get_collection_status(),
                'freshness' => $data_collector->get_data_freshness()
            );
            
            wp_send_json_success($summary);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get course engagement timeline
     */
    private function get_course_engagement_timeline($period, $course_id = null) {
        global $wpdb;
        
        $dates = $this->get_period_dates($period);
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        $where_clause = "activity_type = 'learndash' AND recorded_at BETWEEN %s AND %s";
        $params = array($dates['start_date'] . ' 00:00:00', $dates['end_date'] . ' 23:59:59');
        
        if ($course_id) {
            $where_clause .= " AND object_id = %d";
            $params[] = $course_id;
        }
        
        $query = "
            SELECT 
                DATE(recorded_at) as date,
                COUNT(*) as activities,
                COUNT(DISTINCT user_id) as unique_users
            FROM $activity_table 
            WHERE $where_clause
            GROUP BY DATE(recorded_at)
            ORDER BY date ASC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Fill in missing dates with zero values
        $timeline = array();
        $current_date = $dates['start_date'];
        
        while ($current_date <= $dates['end_date']) {
            $found = false;
            foreach ($results as $result) {
                if ($result->date === $current_date) {
                    $timeline[] = array(
                        'date' => $current_date,
                        'activities' => (int)$result->activities,
                        'unique_users' => (int)$result->unique_users
                    );
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $timeline[] = array(
                    'date' => $current_date,
                    'activities' => 0,
                    'unique_users' => 0
                );
            }
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $timeline;
    }
    
    /**
     * Get detailed group performance
     */
    private function get_group_detailed_performance($group_id, $period) {
        // Get group information
        $group_info = $this->get_group_info($group_id);
        
        if (!$group_info) {
            throw new Exception(__('Group not found', 'bbld-analytics'));
        }
        
        $database = bbld_analytics()->database;
        $dates = $this->get_period_dates($period);
        
        // Get group metrics
        $enrollment_count = $database->get_metric('learndash_groups', "group_{$group_id}_enrollment_count");
        $active_users = $database->get_metric('learndash_groups', "group_{$group_id}_active_users");
        $course_completions = $database->get_metric('learndash_groups', "group_{$group_id}_course_completions");
        $engagement_score = $database->get_metric('learndash_groups', "group_{$group_id}_engagement_score");
        
        // Get course performance for this group
        $shared_courses = bbld_analytics()->get_option('shared_courses', array());
        $courses_performance = array();
        
        foreach ($shared_courses as $course_id) {
            $completion_rate = $database->get_metric('learndash_groups', "group_{$group_id}_course_{$course_id}_completion_rate");
            
            $courses_performance[] = array(
                'course_id' => $course_id,
                'course_title' => get_the_title($course_id),
                'completion_rate' => $completion_rate ? (float)$completion_rate->metric_value : 0
            );
        }
        
        return array(
            'group_info' => $group_info,
            'enrollment_count' => $enrollment_count ? (int)$enrollment_count->metric_value : 0,
            'active_users' => $active_users ? (int)$active_users->metric_value : 0,
            'course_completions' => $course_completions ? (int)$course_completions->metric_value : 0,
            'engagement_score' => $engagement_score ? (float)$engagement_score->metric_value : 0,
            'courses_performance' => $courses_performance
        );
    }
    
    /**
     * Get group information
     */
    private function get_group_info($group_id) {
        if (!function_exists('learndash_get_group')) {
            return null;
        }
        
        $group = learndash_get_group($group_id);
        
        if (!$group) {
            return null;
        }
        
        $group_leader_id = learndash_get_group_leader_id($group_id);
        $group_users = learndash_get_groups_users($group_id);
        
        return array(
            'id' => $group_id,
            'title' => $group->post_title,
            'description' => $group->post_excerpt,
            'leader_id' => $group_leader_id,
            'leader_name' => $group_leader_id ? get_user_by('ID', $group_leader_id)->display_name : __('No leader assigned', 'bbld-analytics'),
            'user_count' => count($group_users),
            'created_date' => $group->post_date
        );
    }
    
    /**
     * Get period dates
     */
    private function get_period_dates($period) {
        $end_date = current_time('Y-m-d');
        
        switch ($period) {
            case '7d':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90d':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case '1y':
                $start_date = date('Y-m-d', strtotime('-1 year'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Sanitize array recursively
     */
    private function sanitize_array($array) {
        $sanitized = array();
        
        foreach ($array as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_array($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate period parameter
     */
    private function validate_period($period) {
        $valid_periods = array('7d', '30d', '90d', '1y');
        return in_array($period, $valid_periods) ? $period : '30d';
    }
    
    /**
     * Log AJAX error
     */
    private function log_ajax_error($action, $error, $data = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'BBLD Analytics AJAX Error [%s]: %s - %s',
                $action,
                $error,
                print_r($data, true)
            ));
        }
    }
}