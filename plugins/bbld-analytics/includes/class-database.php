<?php
/**
 * Database management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize database
     */
    public function init() {
        // Check if we need to update database schema
        $current_version = get_option('bbld_analytics_db_version', '0');
        
        if (version_compare($current_version, BBLD_ANALYTICS_VERSION, '<')) {
            $this->create_tables();
            update_option('bbld_analytics_db_version', BBLD_ANALYTICS_VERSION);
        }
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics metrics table
        $metrics_table = $wpdb->prefix . 'bbld_analytics_metrics';
        $metrics_sql = "CREATE TABLE $metrics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value longtext,
            metric_meta longtext,
            date_recorded date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_metric_type (metric_type),
            KEY idx_metric_key (metric_key),
            KEY idx_date_recorded (date_recorded),
            KEY idx_composite (metric_type, metric_key, date_recorded)
        ) $charset_collate;";
        
        // User activity table
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        $activity_sql = "CREATE TABLE $activity_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_subtype varchar(50),
            object_id bigint(20) unsigned,
            activity_data longtext,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_activity_type (activity_type),
            KEY idx_recorded_at (recorded_at),
            KEY idx_object_id (object_id)
        ) $charset_collate;";
        
        // Widget settings table
        $widgets_table = $wpdb->prefix . 'bbld_analytics_widgets';
        $widgets_sql = "CREATE TABLE $widgets_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            widget_id varchar(50) NOT NULL,
            widget_type varchar(50) NOT NULL,
            widget_title varchar(200),
            widget_config longtext,
            widget_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_widget_id (widget_id),
            KEY idx_widget_type (widget_type),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
        
        // Execute table creation
        dbDelta($metrics_sql);
        dbDelta($activity_sql);
        dbDelta($widgets_sql);
        
        // Insert default widget configurations
        $this->insert_default_widgets();
    }
    
    /**
     * Insert default widget configurations
     */
    private function insert_default_widgets() {
        global $wpdb;
        
        $widgets_table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        $default_widgets = array(
            array(
                'widget_id' => 'buddyboss_analytics',
                'widget_type' => 'buddyboss',
                'widget_title' => 'BuddyBoss Analytics',
                'widget_config' => json_encode(array(
                    'show_posts' => true,
                    'show_likes' => true,
                    'show_active_users' => true,
                    'period' => '30d'
                )),
                'widget_order' => 1,
                'is_active' => 1
            ),
            array(
                'widget_id' => 'learndash_groups',
                'widget_type' => 'learndash',
                'widget_title' => 'LearnDash Groups Analytics',
                'widget_config' => json_encode(array(
                    'show_enrollment' => true,
                    'show_engagement' => true,
                    'show_completions' => true,
                    'top_groups_count' => 5
                )),
                'widget_order' => 2,
                'is_active' => 1
            ),
            array(
                'widget_id' => 'course_engagement',
                'widget_type' => 'course_engagement',
                'widget_title' => 'Course Engagement Analytics',
                'widget_config' => json_encode(array(
                    'chart_type' => 'line',
                    'period' => '30d',
                    'show_trends' => true
                )),
                'widget_order' => 3,
                'is_active' => 1
            ),
            array(
                'widget_id' => 'group_leaders',
                'widget_type' => 'group_leaders',
                'widget_title' => 'Group Leaders Dashboard',
                'widget_config' => json_encode(array(
                    'show_performance' => true,
                    'show_unassigned' => true,
                    'ranking_count' => 10
                )),
                'widget_order' => 4,
                'is_active' => 1
            ),
            array(
                'widget_id' => 'platform_overview',
                'widget_type' => 'platform',
                'widget_title' => 'Platform Overview',
                'widget_config' => json_encode(array(
                    'show_registrations' => true,
                    'show_engagement' => true,
                    'period' => '7d'
                )),
                'widget_order' => 5,
                'is_active' => 1
            )
        );
        
        foreach ($default_widgets as $widget) {
            // Check if widget already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $widgets_table WHERE widget_id = %s",
                $widget['widget_id']
            ));
            
            if (!$existing) {
                $wpdb->insert($widgets_table, $widget);
            }
        }
    }
    
    /**
     * Store metric
     */
    public function store_metric($metric_type, $metric_key, $metric_value, $metric_meta = null, $date = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_metrics';
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        // Check if metric already exists for today
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE metric_type = %s AND metric_key = %s AND date_recorded = %s",
            $metric_type,
            $metric_key,
            $date
        ));
        
        $data = array(
            'metric_type' => $metric_type,
            'metric_key' => $metric_key,
            'metric_value' => is_array($metric_value) || is_object($metric_value) ? json_encode($metric_value) : $metric_value,
            'metric_meta' => $metric_meta ? json_encode($metric_meta) : null,
            'date_recorded' => $date
        );
        
        if ($existing) {
            // Update existing metric
            $data['updated_at'] = current_time('mysql');
            return $wpdb->update($table, $data, array('id' => $existing));
        } else {
            // Insert new metric
            return $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Get metric
     */
    public function get_metric($metric_type, $metric_key, $date = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_metrics';
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE metric_type = %s AND metric_key = %s AND date_recorded = %s",
            $metric_type,
            $metric_key,
            $date
        ));
        
        if ($result) {
            // Decode JSON values
            $result->metric_value = $this->maybe_decode_json($result->metric_value);
            $result->metric_meta = $this->maybe_decode_json($result->metric_meta);
        }
        
        return $result;
    }
    
    /**
     * Get metrics by type
     */
    public function get_metrics_by_type($metric_type, $limit = 30, $offset = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_metrics';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE metric_type = %s ORDER BY date_recorded DESC LIMIT %d OFFSET %d",
            $metric_type,
            $limit,
            $offset
        ));
        
        foreach ($results as $result) {
            $result->metric_value = $this->maybe_decode_json($result->metric_value);
            $result->metric_meta = $this->maybe_decode_json($result->metric_meta);
        }
        
        return $results;
    }
    
    /**
     * Get metrics for date range
     */
    public function get_metrics_for_period($metric_type, $metric_key, $start_date, $end_date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_metrics';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE metric_type = %s 
             AND metric_key = %s 
             AND date_recorded BETWEEN %s AND %s 
             ORDER BY date_recorded ASC",
            $metric_type,
            $metric_key,
            $start_date,
            $end_date
        ));
        
        foreach ($results as $result) {
            $result->metric_value = $this->maybe_decode_json($result->metric_value);
            $result->metric_meta = $this->maybe_decode_json($result->metric_meta);
        }
        
        return $results;
    }
    
    /**
     * Log user activity
     */
    public function log_activity($user_id, $activity_type, $activity_subtype = null, $object_id = null, $activity_data = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_activity';
        
        $data = array(
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'activity_subtype' => $activity_subtype,
            'object_id' => $object_id,
            'activity_data' => $activity_data ? json_encode($activity_data) : null,
            'recorded_at' => current_time('mysql')
        );
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get user activities
     */
    public function get_user_activities($user_id, $activity_type = null, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_activity';
        
        $where = "WHERE user_id = %d";
        $params = array($user_id);
        
        if ($activity_type) {
            $where .= " AND activity_type = %s";
            $params[] = $activity_type;
        }
        
        $query = "SELECT * FROM $table $where ORDER BY recorded_at DESC LIMIT %d";
        $params[] = $limit;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        foreach ($results as $result) {
            $result->activity_data = $this->maybe_decode_json($result->activity_data);
        }
        
        return $results;
    }
    
    /**
     * Get widget configuration
     */
    public function get_widget_config($widget_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE widget_id = %s",
            $widget_id
        ));
        
        if ($result && $result->widget_config) {
            $result->widget_config = json_decode($result->widget_config, true);
        }
        
        return $result;
    }
    
    /**
     * Update widget configuration
     */
    public function update_widget_config($widget_id, $config) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        return $wpdb->update(
            $table,
            array(
                'widget_config' => json_encode($config),
                'updated_at' => current_time('mysql')
            ),
            array('widget_id' => $widget_id)
        );
    }
    
    /**
     * Get all active widgets
     */
    public function get_active_widgets() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY widget_order ASC"
        );
        
        foreach ($results as $result) {
            if ($result->widget_config) {
                $result->widget_config = json_decode($result->widget_config, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Toggle widget status
     */
    public function toggle_widget($widget_id, $is_active) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        return $wpdb->update(
            $table,
            array(
                'is_active' => $is_active ? 1 : 0,
                'updated_at' => current_time('mysql')
            ),
            array('widget_id' => $widget_id)
        );
    }
    
    /**
     * Clean old metrics
     */
    public function clean_old_metrics($days = 90) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_metrics';
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE date_recorded < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Clean old activities
     */
    public function clean_old_activities($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bbld_analytics_activity';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE recorded_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Maybe decode JSON string
     */
    private function maybe_decode_json($value) {
        if (is_string($value) && $this->is_json($value)) {
            return json_decode($value, true);
        }
        return $value;
    }
    
    /**
     * Check if string is valid JSON
     */
    private function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    
    /**
     * Get database statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'bbld_analytics_metrics';
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        $widgets_table = $wpdb->prefix . 'bbld_analytics_widgets';
        
        $stats = array();
        
        // Metrics count
        $stats['metrics_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $metrics_table");
        
        // Activities count
        $stats['activities_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $activity_table");
        
        // Active widgets count
        $stats['active_widgets'] = $wpdb->get_var("SELECT COUNT(*) FROM $widgets_table WHERE is_active = 1");
        
        // Latest metric date
        $stats['latest_metric_date'] = $wpdb->get_var("SELECT MAX(date_recorded) FROM $metrics_table");
        
        // Database size
        $stats['database_size'] = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
             FROM information_schema.tables 
             WHERE table_schema = %s 
             AND table_name IN (%s, %s, %s)",
            DB_NAME,
            $metrics_table,
            $activity_table,
            $widgets_table
        ));
        
        return $stats;
    }
}