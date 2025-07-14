<?php
/**
 * Helpers Class
 * 
 * @package Wbcom_Reports
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wbcom_Reports_Helpers {
    
    /**
     * Check if BuddyPress is active
     */
    public static function is_buddypress_active() {
        return class_exists('BuddyPress');
    }
    
    /**
     * Check if LearnDash is active
     */
    public static function is_learndash_active() {
        return class_exists('SFWD_LMS');
    }
    
    /**
     * Get user last login
     */
    public static function get_user_last_login($user_id) {
        $last_login = get_user_meta($user_id, 'last_login', true);
        return empty($last_login) ? 'Never' : date('Y-m-d H:i:s', $last_login);
    }
    
    /**
     * Format time duration
     */
    public static function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' sec';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . ' min';
        } else {
            return round($seconds / 3600, 1) . ' hrs';
        }
    }
    
    /**
     * Get activity type label
     */
    public static function get_activity_type_label($type) {
        $labels = array(
            'activity_update' => __('Status Update', 'wbcom-reports'),
            'activity_comment' => __('Comment', 'wbcom-reports'),
            'friendship_created' => __('Friendship', 'wbcom-reports'),
            'joined_group' => __('Joined Group', 'wbcom-reports'),
            'created_group' => __('Created Group', 'wbcom-reports'),
            'new_blog_post' => __('Blog Post', 'wbcom-reports'),
            'new_blog_comment' => __('Blog Comment', 'wbcom-reports')
        );
        
        return isset($labels[$type]) ? $labels[$type] : ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * Get course progress percentage
     */
    public static function get_course_progress($user_id, $course_id) {
        if (!self::is_learndash_active()) {
            return 0;
        }
        
        return learndash_course_progress($user_id, $course_id);
    }
    
    /**
     * Get user avatar URL
     */
    public static function get_user_avatar_url($user_id, $size = 32) {
        if (self::is_buddypress_active()) {
            return bp_core_fetch_avatar(array(
                'item_id' => $user_id,
                'type' => 'thumb',
                'width' => $size,
                'height' => $size,
                'html' => false
            ));
        } else {
            return get_avatar_url($user_id, array('size' => $size));
        }
    }
    
    /**
     * Export data to CSV
     */
    public static function export_to_csv($data, $filename = 'export.csv') {
        if (empty($data)) {
            wp_die(__('No data to export', 'wbcom-reports'));
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write headers (first row keys)
        if (!empty($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Sanitize filter input
     */
    public static function sanitize_filter($filter, $allowed_filters = array()) {
        $filter = sanitize_text_field($filter);
        
        if (!empty($allowed_filters) && !in_array($filter, $allowed_filters)) {
            return $allowed_filters[0]; // Return first allowed filter as default
        }
        
        return $filter;
    }
    
    /**
     * Get date range for filters
     */
    public static function get_date_range($period = '30_days') {
        $end_date = current_time('Y-m-d');
        
        switch ($period) {
            case '7_days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30_days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90_days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case '1_year':
                $start_date = date('Y-m-d', strtotime('-1 year'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }
    
    /**
     * Calculate percentage
     */
    public static function calculate_percentage($part, $total, $decimals = 1) {
        if ($total == 0) {
            return '0%';
        }
        
        return round(($part / $total) * 100, $decimals) . '%';
    }
    
    /**
     * Format number with thousands separator
     */
    public static function format_number($number) {
        return number_format($number);
    }
    
    /**
     * Get WordPress user roles
     */
    public static function get_user_roles() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        return $wp_roles->get_names();
    }
    
    /**
     * Check if user has specific capability
     */
    public static function user_can_view_reports($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_options') || user_can($user_id, 'view_wbcom_reports');
    }
    
    /**
     * Get plugin settings
     */
    public static function get_setting($key, $default = '') {
        $settings = get_option('wbcom_reports_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update plugin setting
     */
    public static function update_setting($key, $value) {
        $settings = get_option('wbcom_reports_settings', array());
        $settings[$key] = $value;
        return update_option('wbcom_reports_settings', $settings);
    }
    
    /**
     * Log debug information
     */
    public static function log($message, $type = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Wbcom Reports] %s: %s', strtoupper($type), $message));
        }
    }
    
    /**
     * Get memory usage
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'formatted_current' => size_format(memory_get_usage(true)),
            'formatted_peak' => size_format(memory_get_peak_usage(true))
        );
    }
    
    /**
     * Validate date format
     */
    public static function validate_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Get time zones list
     */
    public static function get_timezones() {
        return timezone_identifiers_list();
    }
    
    /**
     * Convert UTC time to user timezone
     */
    public static function convert_to_user_timezone($utc_time, $user_timezone = null) {
        if (!$user_timezone) {
            $user_timezone = get_option('timezone_string') ?: 'UTC';
        }
        
        $utc = new DateTime($utc_time, new DateTimeZone('UTC'));
        $user_tz = new DateTimeZone($user_timezone);
        $utc->setTimezone($user_tz);
        
        return $utc->format('Y-m-d H:i:s');
    }
}
?>