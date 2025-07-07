<?php
/**
 * Abstract Data Source Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class BBLD_Analytics_Abstract_Data_Source {
    
    /**
     * Data source ID
     */
    protected $source_id;
    
    /**
     * Database instance
     */
    protected $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = bbld_analytics()->database;
        $this->init();
    }
    
    /**
     * Initialize data source
     */
    abstract protected function init();
    
    /**
     * Collect metrics
     */
    abstract public function collect_metrics();
    
    /**
     * Get analytics data
     */
    abstract public function get_analytics_data($period = '30d');
    
    /**
     * Check if data source is available
     */
    abstract public function is_available();
    
    /**
     * Get source ID
     */
    public function get_source_id() {
        return $this->source_id;
    }
    
    /**
     * Store metric
     */
    protected function store_metric($metric_key, $metric_value, $metric_meta = null, $date = null) {
        return $this->database->store_metric($this->source_id, $metric_key, $metric_value, $metric_meta, $date);
    }
    
    /**
     * Get metric
     */
    protected function get_metric($metric_key, $date = null) {
        return $this->database->get_metric($this->source_id, $metric_key, $date);
    }
    
    /**
     * Get metrics for period
     */
    protected function get_metrics_for_period($metric_key, $start_date, $end_date) {
        return $this->database->get_metrics_for_period($this->source_id, $metric_key, $start_date, $end_date);
    }
    
    /**
     * Get all metrics by type
     */
    protected function get_all_metrics($limit = 30, $offset = 0) {
        return $this->database->get_metrics_by_type($this->source_id, $limit, $offset);
    }
    
    /**
     * Log activity
     */
    protected function log_activity($user_id, $activity_type, $activity_subtype = null, $object_id = null, $activity_data = null) {
        return $this->database->log_activity($user_id, $activity_type, $activity_subtype, $object_id, $activity_data);
    }
    
    /**
     * Get period dates
     */
    protected function get_period_dates($period) {
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
     * Format date for database
     */
    protected function format_date($date = null) {
        if (!$date) {
            $date = current_time('mysql');
        }
        
        if (is_string($date)) {
            return date('Y-m-d', strtotime($date));
        }
        
        return $date;
    }
    
    /**
     * Get WordPress users
     */
    protected function get_wp_users($args = array()) {
        $defaults = array(
            'number' => -1,
            'fields' => 'all'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_users($args);
    }
    
    /**
     * Get active users for period
     */
    protected function get_active_users($days = 7) {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $activity_table WHERE recorded_at >= %s",
            $since_date
        ));
        
        return $user_ids;
    }
    
    /**
     * Count records
     */
    protected function count_records($table, $where_clause = '', $where_params = array()) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM $table";
        
        if ($where_clause) {
            $query .= " WHERE $where_clause";
        }
        
        if (!empty($where_params)) {
            return $wpdb->get_var($wpdb->prepare($query, $where_params));
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Get records
     */
    protected function get_records($table, $fields = '*', $where_clause = '', $where_params = array(), $order_by = '', $limit = null) {
        global $wpdb;
        
        $query = "SELECT $fields FROM $table";
        
        if ($where_clause) {
            $query .= " WHERE $where_clause";
        }
        
        if ($order_by) {
            $query .= " ORDER BY $order_by";
        }
        
        if ($limit) {
            $query .= " LIMIT $limit";
        }
        
        if (!empty($where_params)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Calculate percentage
     */
    protected function calculate_percentage($part, $total) {
        if ($total == 0) {
            return 0;
        }
        
        return ($part / $total) * 100;
    }
    
    /**
     * Calculate growth rate
     */
    protected function calculate_growth_rate($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }
    
    /**
     * Get cached data
     */
    protected function get_cached_data($key, $default = null) {
        return get_transient($key) ?: $default;
    }
    
    /**
     * Set cached data
     */
    protected function set_cached_data($key, $data, $expiration = 3600) {
        return set_transient($key, $data, $expiration);
    }
    
    /**
     * Get cache key
     */
    protected function get_cache_key($suffix = '') {
        $key = 'bbld_data_' . $this->source_id;
        if ($suffix) {
            $key .= '_' . $suffix;
        }
        return $key;
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        $cache_keys = array(
            $this->get_cache_key(),
            $this->get_cache_key('7d'),
            $this->get_cache_key('30d'),
            $this->get_cache_key('90d'),
            $this->get_cache_key('1y'),
            $this->get_cache_key('metrics')
        );
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
    
    /**
     * Validate period
     */
    protected function validate_period($period) {
        $valid_periods = array('7d', '30d', '90d', '1y');
        return in_array($period, $valid_periods) ? $period : '30d';
    }
    
    /**
     * Sanitize metric value
     */
    protected function sanitize_metric_value($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Log error
     */
    protected function log_error($message, $data = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'BBLD Analytics [%s]: %s - %s',
                $this->source_id,
                $message,
                print_r($data, true)
            ));
        }
    }
    
    /**
     * Check plugin dependency
     */
    protected function check_plugin_active($plugin_file) {
        return is_plugin_active($plugin_file);
    }
    
    /**
     * Check if class exists
     */
    protected function check_class_exists($class_name) {
        return class_exists($class_name);
    }
    
    /**
     * Check if function exists
     */
    protected function check_function_exists($function_name) {
        return function_exists($function_name);
    }
    
    /**
     * Get WordPress option
     */
    protected function get_wp_option($option_name, $default = false) {
        return get_option($option_name, $default);
    }
    
    /**
     * Update WordPress option
     */
    protected function update_wp_option($option_name, $value) {
        return update_option($option_name, $value);
    }
    
    /**
     * Get user meta
     */
    protected function get_user_meta($user_id, $meta_key, $single = true) {
        return get_user_meta($user_id, $meta_key, $single);
    }
    
    /**
     * Get post meta
     */
    protected function get_post_meta($post_id, $meta_key, $single = true) {
        return get_post_meta($post_id, $meta_key, $single);
    }
    
    /**
     * Get posts
     */
    protected function get_posts($args = array()) {
        $defaults = array(
            'post_status' => 'publish',
            'numberposts' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_posts($args);
    }
    
    /**
     * Batch process data
     */
    protected function batch_process($data, $callback, $batch_size = 100) {
        $total = count($data);
        $processed = 0;
        
        for ($i = 0; $i < $total; $i += $batch_size) {
            $batch = array_slice($data, $i, $batch_size);
            
            foreach ($batch as $item) {
                if (is_callable($callback)) {
                    call_user_func($callback, $item);
                }
                $processed++;
            }
            
            // Prevent memory issues
            if ($i > 0 && $i % 1000 === 0) {
                // Clear object cache every 1000 items
                wp_cache_flush();
            }
        }
        
        return $processed;
    }
}