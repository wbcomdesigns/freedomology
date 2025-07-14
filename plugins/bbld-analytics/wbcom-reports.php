<?php
/**
 * Plugin Name: Wbcom Activity & Learning Reports
 * Description: Comprehensive reporting widget for BuddyPress activity and LearnDash learning metrics with advanced group analytics and search capabilities
 * Version: 1.2
 * Author: vapvarun
 * Author URI: https://wbcomdesigns.com
 * Company: Wbcom Designs
 * Text Domain: wbcom-reports
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WBCOM_REPORTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WBCOM_REPORTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WBCOM_REPORTS_VERSION', '1.2');

class Wbcom_Reports_Main {
    
    public function __construct() {
        // Include files early so they're available for activation hook
        $this->include_files();
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Create database indexes on activation
        register_activation_hook(__FILE__, array($this, 'create_indexes'));
        
        // Track user login
        add_action('wp_login', array($this, 'track_login_time'), 10, 2);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wbcom-reports', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        // Include helpers first since other classes depend on it
        require_once WBCOM_REPORTS_PLUGIN_DIR . 'includes/class-helpers.php';
        require_once WBCOM_REPORTS_PLUGIN_DIR . 'includes/class-reports-index.php';
        require_once WBCOM_REPORTS_PLUGIN_DIR . 'includes/class-dashboard.php';
        require_once WBCOM_REPORTS_PLUGIN_DIR . 'includes/class-buddypress-reports.php';
        require_once WBCOM_REPORTS_PLUGIN_DIR . 'includes/class-learndash-reports.php';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Wbcom Reports', 'wbcom-reports'),
            __('Wbcom Reports', 'wbcom-reports'),
            'manage_options',
            'wbcom-reports',
            array('Wbcom_Reports_Dashboard', 'render_page'),
            'dashicons-chart-bar',
            30
        );
        
        // Add submenu pages
        add_submenu_page(
            'wbcom-reports',
            __('Dashboard', 'wbcom-reports'),
            __('Dashboard', 'wbcom-reports'),
            'manage_options',
            'wbcom-reports',
            array('Wbcom_Reports_Dashboard', 'render_page')
        );
        
        add_submenu_page(
            'wbcom-reports',
            __('BuddyPress Reports', 'wbcom-reports'),
            __('BuddyPress Reports', 'wbcom-reports'),
            'manage_options',
            'wbcom-buddypress-reports',
            array('Wbcom_Reports_BuddyPress', 'render_page')
        );
        
        add_submenu_page(
            'wbcom-reports',
            __('LearnDash Reports', 'wbcom-reports'),
            __('LearnDash Reports', 'wbcom-reports'),
            'manage_options',
            'wbcom-learndash-reports',
            array('Wbcom_Reports_LearnDash', 'render_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wbcom-reports') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        
        // Common styles for all report pages
        wp_enqueue_style('wbcom-reports-admin', WBCOM_REPORTS_PLUGIN_URL . 'assets/admin-style.css', array(), WBCOM_REPORTS_VERSION);
        
        // Page-specific scripts
        if ($hook === 'toplevel_page_wbcom-reports') {
            wp_enqueue_script('wbcom-dashboard', WBCOM_REPORTS_PLUGIN_URL . 'assets/dashboard.js', array('jquery', 'chart-js'), WBCOM_REPORTS_VERSION, true);
        } elseif ($hook === 'wbcom-reports_page_wbcom-buddypress-reports') {
            // Use the indexed version for BuddyPress reports
            wp_enqueue_script('wbcom-buddypress-indexed', WBCOM_REPORTS_PLUGIN_URL . 'assets/buddypress-indexed.js', array('jquery'), WBCOM_REPORTS_VERSION, true);
        } elseif ($hook === 'wbcom-reports_page_wbcom-learndash-reports') {
            // Use the enhanced indexed version for LearnDash reports with advanced group analytics
            wp_enqueue_script('wbcom-learndash-indexed', WBCOM_REPORTS_PLUGIN_URL . 'assets/learndash-indexed.js', array('jquery', 'chart-js'), WBCOM_REPORTS_VERSION, true);
        }
        
        // Localize script with admin URL support
        $localize_handle = 'jquery';
        if ($hook === 'toplevel_page_wbcom-reports') {
            $localize_handle = 'wbcom-dashboard';
        } elseif ($hook === 'wbcom-reports_page_wbcom-buddypress-reports') {
            $localize_handle = 'wbcom-buddypress-indexed';
        } elseif ($hook === 'wbcom-reports_page_wbcom-learndash-reports') {
            $localize_handle = 'wbcom-learndash-indexed';
        }
        
        wp_localize_script($localize_handle, 'wbcomReports', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('wbcom_reports_nonce'),
            'pluginUrl' => WBCOM_REPORTS_PLUGIN_URL
        ));
    }
    
    /**
     * Track user login time
     */
    public function track_login_time($user_login, $user) {
        update_user_meta($user->ID, 'last_login', current_time('timestamp'));
    }
    
    /**
     * Create database indexes
     */
    public function create_indexes() {
        global $wpdb;
        
        // Suppress any output during index creation
        ob_start();
        
        try {
            // Check if BuddyPress is active using direct class check instead of helper
            $is_buddypress_active = class_exists('BuddyPress');
            
            // BuddyPress indexes
            if ($is_buddypress_active) {
                $activity_table = $wpdb->prefix . 'bp_activity';
                
                // Check if table exists before creating indexes
                if ($wpdb->get_var("SHOW TABLES LIKE '{$activity_table}'") == $activity_table) {
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_bp_activity_user_id ON {$activity_table} (user_id)");
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_bp_activity_component_type ON {$activity_table} (component, type)");
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_bp_activity_date_recorded ON {$activity_table} (date_recorded)");
                }
                
                $activity_meta_table = $wpdb->prefix . 'bp_activity_meta';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$activity_meta_table}'") == $activity_meta_table) {
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_bp_activity_meta_key_value ON {$activity_meta_table} (meta_key, meta_value)");
                }
                
                $groups_table = $wpdb->prefix . 'bp_groups';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$groups_table}'") == $groups_table) {
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_bp_groups_date_created ON {$groups_table} (date_created)");
                }
            }
            
            // Check if LearnDash is active using direct class check instead of helper
            $is_learndash_active = class_exists('SFWD_LMS');
            
            // LearnDash indexes
            if ($is_learndash_active) {
                $ld_activity_table = $wpdb->prefix . 'learndash_user_activity';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$ld_activity_table}'") == $ld_activity_table) {
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_ld_user_activity_user_id ON {$ld_activity_table} (user_id)");
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_ld_user_activity_type ON {$ld_activity_table} (activity_type)");
                    $wpdb->query("CREATE INDEX IF NOT EXISTS idx_ld_user_activity_updated ON {$ld_activity_table} (activity_updated)");
                }
                
                // Index for LearnDash groups
                $ld_groups_table = $wpdb->prefix . 'posts';
                $wpdb->query("CREATE INDEX IF NOT EXISTS idx_posts_type_status ON {$ld_groups_table} (post_type, post_status)");
            }
            
            // Index for user meta to improve query performance
            $wpdb->query("CREATE INDEX IF NOT EXISTS idx_usermeta_key_value ON {$wpdb->usermeta} (meta_key, meta_value(100))");
            
        } catch (Exception $e) {
            // Log error if WP_DEBUG is enabled, but don't break activation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Wbcom Reports index creation error: ' . $e->getMessage());
            }
        }
        
        // Clean any output
        ob_end_clean();
    }
}

// Initialize the plugin
new Wbcom_Reports_Main();
?>