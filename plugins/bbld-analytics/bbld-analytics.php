<?php
/**
 * Plugin Name: BuddyBoss LearnDash Analytics
 * Plugin URI: https://wbcomdesigns.com
 * Description: Advanced analytics for LearnDash Groups focusing on course engagement, group performance, and enrollment tracking
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: bbld-analytics
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BBLD_ANALYTICS_VERSION', '1.0.0');
define('BBLD_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBLD_ANALYTICS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BBLD_ANALYTICS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main BuddyBoss LearnDash Analytics Plugin Class
 */
class BBLD_Analytics {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $database;
    public $data_collector;
    public $widget_manager;
    public $admin;
    public $ajax;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'check_dependencies'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('BBLD_Analytics', 'uninstall'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load textdomain
        load_plugin_textdomain('bbld-analytics', false, dirname(BBLD_ANALYTICS_PLUGIN_BASENAME) . '/languages');
        
        // Initialize components after plugins are loaded
        do_action('bbld_analytics_init');
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        // Check if LearnDash is active
        if (!class_exists('SFWD_LMS')) {
            add_action('admin_notices', array($this, 'learndash_missing_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Display LearnDash missing notice
     */
    public function learndash_missing_notice() {
        $class = 'notice notice-error';
        $message = __('BuddyBoss LearnDash Analytics requires LearnDash LMS plugin to be installed and activated.', 'bbld-analytics');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load abstract classes
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/abstracts/abstract-widget.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/abstracts/abstract-data-source.php';
        
        // Load core classes
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-database.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-utils.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-data-collector.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-widget-manager.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-admin.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/class-ajax.php';
        
        // Load data sources
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/data-sources/class-buddyboss-data.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/data-sources/class-learndash-data.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/data-sources/class-learndash-groups-data.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/data-sources/class-platform-data.php';
        
        // Load widgets
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/widgets/class-buddyboss-widget.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/widgets/class-learndash-widget.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/widgets/class-course-engagement-widget.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/widgets/class-group-leaders-widget.php';
        require_once BBLD_ANALYTICS_PLUGIN_PATH . 'includes/widgets/class-platform-widget.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new BBLD_Analytics_Database();
        $this->data_collector = new BBLD_Analytics_Data_Collector();
        $this->widget_manager = new BBLD_Analytics_Widget_Manager();
        
        if (is_admin()) {
            $this->admin = new BBLD_Analytics_Admin();
            $this->ajax = new BBLD_Analytics_Ajax();
        }
        
        // Hook for components loaded
        do_action('bbld_analytics_components_loaded');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database = new BBLD_Analytics_Database();
        $this->database->create_tables();
        
        // Set default options
        $default_options = array(
            'version' => BBLD_ANALYTICS_VERSION,
            'enabled_widgets' => array('buddyboss', 'learndash', 'platform', 'course_engagement', 'group_leaders'),
            'update_frequency' => 'daily',
            'shared_courses' => array(),
            'admin_only_access' => true,
            'dashboard_refresh_interval' => 300
        );
        
        add_option('bbld_analytics_options', $default_options);
        
        // Schedule cron events
        if (!wp_next_scheduled('bbld_analytics_daily_update')) {
            wp_schedule_event(strtotime('tomorrow 2:00 AM'), 'daily', 'bbld_analytics_daily_update');
        }
        
        if (!wp_next_scheduled('bbld_analytics_hourly_update')) {
            wp_schedule_event(time(), 'hourly', 'bbld_analytics_hourly_update');
        }
        
        // Schedule initial collection
        wp_schedule_single_event(time() + 60, 'bbld_analytics_initial_collection');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('bbld_analytics_daily_update');
        wp_clear_scheduled_hook('bbld_analytics_hourly_update');
        wp_clear_scheduled_hook('bbld_analytics_initial_collection');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove options
        delete_option('bbld_analytics_options');
        
        // Drop database tables
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'bbld_analytics_metrics',
            $wpdb->prefix . 'bbld_analytics_activity',
            $wpdb->prefix . 'bbld_analytics_widgets'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Clear any remaining scheduled events
        wp_clear_scheduled_hook('bbld_analytics_daily_update');
        wp_clear_scheduled_hook('bbld_analytics_hourly_update');
        wp_clear_scheduled_hook('bbld_analytics_initial_collection');
    }
    
    /**
     * Get plugin options
     */
    public function get_options() {
        return get_option('bbld_analytics_options', array());
    }
    
    /**
     * Update plugin options
     */
    public function update_options($options) {
        return update_option('bbld_analytics_options', $options);
    }
    
    /**
     * Get plugin option
     */
    public function get_option($key, $default = null) {
        $options = $this->get_options();
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Update plugin option
     */
    public function update_option($key, $value) {
        $options = $this->get_options();
        $options[$key] = $value;
        return $this->update_options($options);
    }
}

/**
 * Initialize the plugin
 */
function bbld_analytics() {
    return BBLD_Analytics::get_instance();
}

// Initialize plugin
bbld_analytics();

/**
 * Add cron hooks
 */
add_action('bbld_analytics_daily_update', array(bbld_analytics()->data_collector, 'collect_all_metrics'));
add_action('bbld_analytics_hourly_update', array(bbld_analytics()->data_collector, 'collect_hourly_metrics'));
add_action('bbld_analytics_initial_collection', array(bbld_analytics()->data_collector, 'initial_collection'));