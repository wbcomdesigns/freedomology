<?php
/**
 * Utility Functions Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Utils {
    
    /**
     * Format number for display
     */
    public static function format_number($number) {
        if (!is_numeric($number)) {
            return '0';
        }
        
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }
        
        return number_format($number);
    }
    
    /**
     * Format percentage for display
     */
    public static function format_percentage($value, $decimals = 1) {
        if (!is_numeric($value)) {
            return '0%';
        }
        
        return number_format($value, $decimals) . '%';
    }
    
    /**
     * Format currency for display
     */
    public static function format_currency($amount, $currency = 'USD') {
        if (!is_numeric($amount)) {
            return '$0';
        }
        
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥'
        );
        
        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';
        
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get time ago string
     */
    public static function time_ago($datetime) {
        if (!$datetime) {
            return __('Never', 'bbld-analytics');
        }
        
        $time = time() - strtotime($datetime);
        
        if ($time < 1) {
            return __('Just now', 'bbld-analytics');
        }
        
        $condition = array(
            12 * 30 * 24 * 60 * 60 => __('year', 'bbld-analytics'),
            30 * 24 * 60 * 60      => __('month', 'bbld-analytics'),
            24 * 60 * 60           => __('day', 'bbld-analytics'),
            60 * 60                => __('hour', 'bbld-analytics'),
            60                     => __('minute', 'bbld-analytics'),
            1                      => __('second', 'bbld-analytics')
        );
        
        foreach ($condition as $secs => $str) {
            $d = $time / $secs;
            
            if ($d >= 1) {
                $t = round($d);
                return sprintf(
                    _n('1 %2$s ago', '%1$d %2$s ago', $t, 'bbld-analytics'),
                    $t,
                    $str
                );
            }
        }
        
        return __('Just now', 'bbld-analytics');
    }
    
    /**
     * Get trend indicator
     */
    public static function get_trend_indicator($current, $previous) {
        if ($previous == 0) {
            return array(
                'direction' => 'neutral',
                'percentage' => 0,
                'class' => 'trend-neutral'
            );
        }
        
        $change = (($current - $previous) / $previous) * 100;
        
        if ($change > 0) {
            $direction = 'up';
            $class = 'trend-up';
        } elseif ($change < 0) {
            $direction = 'down';
            $class = 'trend-down';
            $change = abs($change);
        } else {
            $direction = 'neutral';
            $class = 'trend-neutral';
        }
        
        return array(
            'direction' => $direction,
            'percentage' => round($change, 1),
            'class' => $class
        );
    }
    
    /**
     * Render trend indicator HTML
     */
    public static function render_trend_indicator($current, $previous, $label = '') {
        $trend = self::get_trend_indicator($current, $previous);
        
        $icons = array(
            'up' => 'arrow-up-alt',
            'down' => 'arrow-down-alt',
            'neutral' => 'minus'
        );
        
        $icon = $icons[$trend['direction']];
        
        $html = '<span class="trend-indicator ' . esc_attr($trend['class']) . '">';
        $html .= '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
        
        if ($trend['percentage'] > 0) {
            $html .= esc_html($trend['percentage']) . '%';
        }
        
        if ($label) {
            $html .= ' ' . esc_html($label);
        }
        
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Sanitize array recursively
     */
    public static function sanitize_array($array) {
        $sanitized = array();
        
        foreach ($array as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_array($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = $value; // Keep objects as-is
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Generate chart colors
     */
    public static function generate_chart_colors($count) {
        $base_colors = array(
            '#2271b1', '#135e96', '#0073aa', '#005177',
            '#72aee6', '#4f94d4', '#3582c4', '#135e96',
            '#f0f6fc', '#c5d9ed', '#9ec5e5', '#72aee6'
        );
        
        $colors = array();
        
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $base_colors[$i % count($base_colors)];
        }
        
        return $colors;
    }
    
    /**
     * Get period options
     */
    public static function get_period_options() {
        return array(
            '7d' => __('Last 7 days', 'bbld-analytics'),
            '30d' => __('Last 30 days', 'bbld-analytics'),
            '90d' => __('Last 90 days', 'bbld-analytics'),
            '1y' => __('Last year', 'bbld-analytics')
        );
    }
    
    /**
     * Validate period
     */
    public static function validate_period($period) {
        $valid_periods = array_keys(self::get_period_options());
        return in_array($period, $valid_periods) ? $period : '30d';
    }
    
    /**
     * Get date range for period
     */
    public static function get_date_range($period) {
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
            'end_date' => $end_date,
            'days' => (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60) + 1
        );
    }
    
    /**
     * Calculate percentage change
     */
    public static function calculate_percentage_change($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }
    
    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status, $label = '') {
        $badges = array(
            'excellent' => array('class' => 'badge-success', 'label' => __('Excellent', 'bbld-analytics')),
            'good' => array('class' => 'badge-info', 'label' => __('Good', 'bbld-analytics')),
            'needs_attention' => array('class' => 'badge-warning', 'label' => __('Needs Attention', 'bbld-analytics')),
            'poor' => array('class' => 'badge-danger', 'label' => __('Poor', 'bbld-analytics')),
            'active' => array('class' => 'badge-success', 'label' => __('Active', 'bbld-analytics')),
            'inactive' => array('class' => 'badge-secondary', 'label' => __('Inactive', 'bbld-analytics')),
            'empty' => array('class' => 'badge-light', 'label' => __('Empty', 'bbld-analytics'))
        );
        
        $badge = isset($badges[$status]) ? $badges[$status] : $badges['inactive'];
        $display_label = $label ?: $badge['label'];
        
        return '<span class="status-badge ' . esc_attr($badge['class']) . '">' . esc_html($display_label) . '</span>';
    }
    
    /**
     * Generate CSV data
     */
    public static function generate_csv($data, $headers = array()) {
        if (empty($data)) {
            return '';
        }
        
        $output = '';
        
        // Add headers if provided
        if (!empty($headers)) {
            $output .= implode(',', array_map(array(self, 'escape_csv_field'), $headers)) . "\n";
        }
        
        // Add data rows
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            
            $output .= implode(',', array_map(array(self, 'escape_csv_field'), $row)) . "\n";
        }
        
        return $output;
    }
    
    /**
     * Escape CSV field
     */
    private static function escape_csv_field($field) {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        
        return $field;
    }
    
    /**
     * Get plugin memory usage
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        );
    }
    
    /**
     * Log debug message
     */
    public static function debug_log($message, $data = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                'BBLD Analytics: %s',
                $message
            );
            
            if (!empty($data)) {
                $log_message .= ' - ' . print_r($data, true);
            }
            
            error_log($log_message);
        }
    }
    
    /**
     * Check if user can access analytics
     */
    public static function can_access_analytics($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check if admin only access is enabled
        $admin_only = bbld_analytics()->get_option('admin_only_access', true);
        
        if ($admin_only) {
            return user_can($user_id, 'manage_options');
        }
        
        // Allow access for users with specific capabilities
        $allowed_capabilities = apply_filters('bbld_analytics_allowed_capabilities', array(
            'manage_options',
            'edit_posts',
            'manage_categories'
        ));
        
        foreach ($allowed_capabilities as $capability) {
            if (user_can($user_id, $capability)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get plugin version
     */
    public static function get_plugin_version() {
        return BBLD_ANALYTICS_VERSION;
    }
    
    /**
     * Check plugin dependencies
     */
    public static function check_dependencies() {
        $dependencies = array(
            'wordpress' => array(
                'required' => '5.0',
                'current' => get_bloginfo('version'),
                'met' => version_compare(get_bloginfo('version'), '5.0', '>=')
            ),
            'php' => array(
                'required' => '7.4',
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, '7.4', '>=')
            ),
            'learndash' => array(
                'required' => 'Any',
                'current' => class_exists('SFWD_LMS') ? 'Active' : 'Not Active',
                'met' => class_exists('SFWD_LMS')
            ),
            'buddyboss' => array(
                'required' => 'Optional',
                'current' => (class_exists('BuddyPress') || function_exists('bp_is_active')) ? 'Active' : 'Not Active',
                'met' => true // Optional dependency
            )
        );
        
        return $dependencies;
    }
    
    /**
     * Format file size
     */
    public static function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Get timezone display
     */
    public static function get_timezone_display() {
        $timezone = wp_timezone_string();
        $datetime = new DateTime('now', new DateTimeZone($timezone));
        $offset = $datetime->format('P');
        
        return sprintf('%s (UTC%s)', $timezone, $offset);
    }
    
    /**
     * Validate email address
     */
    public static function validate_email($email) {
        return is_email($email);
    }
    
    /**
     * Generate nonce for AJAX requests
     */
    public static function generate_ajax_nonce($action = 'bbld_analytics_nonce') {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    public static function verify_ajax_nonce($nonce, $action = 'bbld_analytics_nonce') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Get user display name
     */
    public static function get_user_display_name($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return __('Unknown User', 'bbld-analytics');
        }
        
        return $user->display_name ?: $user->user_login;
    }
    
    /**
     * Get course title
     */
    public static function get_course_title($course_id) {
        $title = get_the_title($course_id);
        return $title ?: __('Unknown Course', 'bbld-analytics');
    }
    
    /**
     * Get group title
     */
    public static function get_group_title($group_id) {
        if (function_exists('learndash_get_group')) {
            $group = learndash_get_group($group_id);
            return $group ? $group->post_title : __('Unknown Group', 'bbld-analytics');
        }
        
        return get_the_title($group_id) ?: __('Unknown Group', 'bbld-analytics');
    }
    
    /**
     * Clean old data
     */
    public static function clean_old_data($days = 90) {
        $database = bbld_analytics()->database;
        
        $cleaned = array(
            'metrics' => $database->clean_old_metrics($days),
            'activities' => $database->clean_old_activities($days)
        );
        
        return $cleaned;
    }
    
    /**
     * Export data as JSON
     */
    public static function export_data($type = 'all', $period = '30d') {
        $data_collector = bbld_analytics()->data_collector;
        $export_data = array();
        
        switch ($type) {
            case 'metrics':
                $export_data = $data_collector->get_dashboard_data();
                break;
            case 'summary':
                $export_data = $data_collector->get_summary_metrics();
                break;
            case 'all':
            default:
                $export_data = array(
                    'summary' => $data_collector->get_summary_metrics(),
                    'dashboard' => $data_collector->get_dashboard_data(),
                    'status' => $data_collector->get_collection_status()
                );
                break;
        }
        
        return array(
            'plugin_version' => self::get_plugin_version(),
            'export_date' => current_time('Y-m-d H:i:s'),
            'export_type' => $type,
            'period' => $period,
            'data' => $export_data
        );
    }
    
    /**
     * Convert array to object recursively
     */
    public static function array_to_object($array) {
        if (is_array($array)) {
            $object = new stdClass();
            foreach ($array as $key => $value) {
                $object->$key = self::array_to_object($value);
            }
            return $object;
        }
        
        return $array;
    }
    
    /**
     * Convert object to array recursively
     */
    public static function object_to_array($object) {
        if (is_object($object)) {
            $array = array();
            foreach (get_object_vars($object) as $key => $value) {
                $array[$key] = self::object_to_array($value);
            }
            return $array;
        }
        
        return $object;
    }
    
    /**
     * Get admin page URL
     */
    public static function get_admin_page_url($page = 'bbld-analytics', $args = array()) {
        $url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
    
    /**
     * Render admin notice
     */
    public static function render_admin_notice($message, $type = 'info', $dismissible = true) {
        $classes = array('notice', 'notice-' . $type);
        
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }
        
        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr(implode(' ', $classes)),
            wp_kses_post($message)
        );
    }
    
    /**
     * Get system information
     */
    public static function get_system_info() {
        global $wpdb;
        
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'plugin_version' => self::get_plugin_version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => self::get_timezone_display(),
            'multisite' => is_multisite(),
            'active_plugins' => self::get_active_plugins_list(),
            'dependencies' => self::check_dependencies()
        );
    }
    
    /**
     * Get active plugins list
     */
    private static function get_active_plugins_list() {
        $active_plugins = get_option('active_plugins', array());
        $plugin_names = array();
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_names[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
        }
        
        return $plugin_names;
    }
    
    /**
     * Check if feature is enabled
     */
    public static function is_feature_enabled($feature) {
        $enabled_features = bbld_analytics()->get_option('enabled_features', array());
        return in_array($feature, $enabled_features);
    }
    
    /**
     * Get available chart types
     */
    public static function get_chart_types() {
        return array(
            'line' => __('Line Chart', 'bbld-analytics'),
            'bar' => __('Bar Chart', 'bbld-analytics'),
            'pie' => __('Pie Chart', 'bbld-analytics'),
            'doughnut' => __('Doughnut Chart', 'bbld-analytics'),
            'area' => __('Area Chart', 'bbld-analytics')
        );
    }
    
    /**
     * Generate random color
     */
    public static function generate_random_color() {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
    
    /**
     * Check if request is AJAX
     */
    public static function is_ajax_request() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
    
    /**
     * Check if request is from admin
     */
    public static function is_admin_request() {
        return is_admin() && !self::is_ajax_request();
    }
    
    /**
     * Safe LearnDash function wrapper
     */
    public static function safe_learndash_function($function_name, $args = array(), $default = null) {
        if (function_exists($function_name)) {
            return call_user_func_array($function_name, $args);
        }
        return $default;
    }
    
    /**
     * Get LearnDash group leader safely (CORRECTED)
     */
    public static function get_group_leader_id($group_id) {
        // Method 1: Check for multiple leaders
        $leaders = get_post_meta($group_id, 'learndash_group_leaders', true);
        if (is_array($leaders) && !empty($leaders)) {
            return (int)$leaders[0]; // Return first leader
        }
        
        // Method 2: Check for single leader
        $leader = get_post_meta($group_id, 'learndash_group_leader', true);
        if ($leader) {
            return (int)$leader;
        }
        
        // Method 3: Check alternative meta key
        $leader = get_post_meta($group_id, '_ld_group_leader', true);
        if ($leader) {
            return (int)$leader;
        }
        
        return false;
    }
    
    /**
     * Get LearnDash group users safely (CORRECTED)
     */
    public static function get_group_users($group_id) {
        // Method 1: Try official function variations
        if (function_exists('learndash_get_groups_user_ids')) {
            return learndash_get_groups_user_ids($group_id);
        }
        
        // Method 2: Check enrolled users meta
        $enrolled_users = get_post_meta($group_id, 'learndash_group_enrolled_users', true);
        if (is_array($enrolled_users)) {
            return array_map('intval', $enrolled_users);
        }
        
        // Method 3: Check alternative meta key
        $enrolled_users = get_post_meta($group_id, '_ld_group_enrolled_users', true);
        if (is_array($enrolled_users)) {
            return array_map('intval', $enrolled_users);
        }
        
        // Method 4: Direct database query
        global $wpdb;
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE %s",
            'learndash_group_users_' . $group_id
        ));
        
        return array_map('intval', $user_ids);
    }
    
    /**
     * Get LearnDash groups safely (CORRECTED)
     */
    public static function get_learndash_groups() {
        return get_posts(array(
            'post_type' => 'groups',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Check course completion safely (CORRECTED)
     */
    public static function is_course_completed($user_id, $course_id) {
        // Method 1: Use official function if exists
        if (function_exists('learndash_course_completed')) {
            return learndash_course_completed($user_id, $course_id);
        }
        
        // Method 2: Check completion meta
        $completed = get_user_meta($user_id, "course_completed_{$course_id}", true);
        return !empty($completed);
    }
    
    /**
     * Get course enrolled users safely (CORRECTED)
     */
    public static function get_course_enrolled_users($course_id) {
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = %s",
            "course_{$course_id}_access_from"
        ));
        
        return array_map('intval', $user_ids);
    }
}