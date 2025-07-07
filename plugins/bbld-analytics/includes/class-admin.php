<?php
/**
 * Admin Interface Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('BBLD Analytics', 'bbld-analytics'),
            __('BBLD Analytics', 'bbld-analytics'),
            'manage_options',
            'bbld-analytics',
            array($this, 'dashboard_page'),
            'dashicons-chart-area',
            30
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'bbld-analytics',
            __('Dashboard', 'bbld-analytics'),
            __('Dashboard', 'bbld-analytics'),
            'manage_options',
            'bbld-analytics',
            array($this, 'dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'bbld-analytics',
            __('Settings', 'bbld-analytics'),
            __('Settings', 'bbld-analytics'),
            'manage_options',
            'bbld-analytics-settings',
            array($this, 'settings_page')
        );
        
        // Widgets submenu
        add_submenu_page(
            'bbld-analytics',
            __('Widgets', 'bbld-analytics'),
            __('Widgets', 'bbld-analytics'),
            'manage_options',
            'bbld-analytics-widgets',
            array($this, 'widgets_page')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('bbld_analytics_settings', 'bbld_analytics_options', array($this, 'validate_options'));
        
        // Add settings sections
        add_settings_section(
            'bbld_analytics_general',
            __('General Settings', 'bbld-analytics'),
            array($this, 'general_settings_section'),
            'bbld_analytics_settings'
        );
        
        add_settings_section(
            'bbld_analytics_courses',
            __('Shared Courses', 'bbld-analytics'),
            array($this, 'courses_settings_section'),
            'bbld_analytics_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'update_frequency',
            __('Update Frequency', 'bbld-analytics'),
            array($this, 'update_frequency_field'),
            'bbld_analytics_settings',
            'bbld_analytics_general'
        );
        
        add_settings_field(
            'dashboard_refresh_interval',
            __('Dashboard Refresh Interval', 'bbld-analytics'),
            array($this, 'refresh_interval_field'),
            'bbld_analytics_settings',
            'bbld_analytics_general'
        );
        
        add_settings_field(
            'shared_courses',
            __('Base Courses', 'bbld-analytics'),
            array($this, 'shared_courses_field'),
            'bbld_analytics_settings',
            'bbld_analytics_courses'
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'bbld-analytics') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'bbld-analytics-admin',
            BBLD_ANALYTICS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BBLD_ANALYTICS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'bbld-analytics-admin',
            BBLD_ANALYTICS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            BBLD_ANALYTICS_VERSION,
            true
        );
        
        // Enqueue Chart.js for visualizations
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Localize script
        wp_localize_script('bbld-analytics-admin', 'bbldAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bbld_analytics_nonce'),
            'refreshInterval' => bbld_analytics()->get_option('dashboard_refresh_interval', 300) * 1000,
            'strings' => array(
                'loading' => __('Loading...', 'bbld-analytics'),
                'error' => __('Error loading data', 'bbld-analytics'),
                'refreshSuccess' => __('Data refreshed successfully', 'bbld-analytics'),
                'refreshError' => __('Error refreshing data', 'bbld-analytics'),
                'confirmRefresh' => __('This will refresh all analytics data. Continue?', 'bbld-analytics')
            )
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $data_collector = bbld_analytics()->data_collector;
        $widget_manager = bbld_analytics()->widget_manager;
        
        // Get summary metrics
        $summary_metrics = $data_collector->get_summary_metrics();
        $collection_status = $data_collector->get_collection_status();
        $data_freshness = $data_collector->get_data_freshness();
        
        ?>
        <div class="wrap bbld-analytics-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Data Status Bar -->
            <div class="data-status-bar status-<?php echo esc_attr($data_freshness['status']); ?>">
                <div class="status-info">
                    <span class="status-icon dashicons dashicons-<?php echo $data_freshness['status'] === 'fresh' ? 'yes-alt' : ($data_freshness['status'] === 'good' ? 'clock' : 'warning'); ?>"></span>
                    <span class="status-text"><?php echo esc_html($data_freshness['message']); ?></span>
                </div>
                <div class="status-actions">
                    <button type="button" class="button button-secondary" id="refresh-data">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Data', 'bbld-analytics'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html($this->format_number($summary_metrics['total_groups'])); ?></h3>
                        <p><?php _e('Total Groups', 'bbld-analytics'); ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html($this->format_number($summary_metrics['total_learners'])); ?></h3>
                        <p><?php _e('Total Learners', 'bbld-analytics'); ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html($this->format_number($summary_metrics['active_learners'])); ?></h3>
                        <p><?php _e('Active Learners', 'bbld-analytics'); ?></p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html($this->format_percentage($summary_metrics['completion_rate'])); ?></h3>
                        <p><?php _e('Completion Rate', 'bbld-analytics'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Widgets Grid -->
            <div class="widgets-grid">
                <?php $widget_manager->render_dashboard_widgets(); ?>
            </div>
            
            <!-- Loading Overlay -->
            <div id="loading-overlay" class="loading-overlay" style="display: none;">
                <div class="loading-content">
                    <div class="spinner is-active"></div>
                    <p><?php _e('Refreshing data...', 'bbld-analytics'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('bbld_analytics_settings');
                do_settings_sections('bbld_analytics_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Widgets page
     */
    public function widgets_page() {
        $widget_manager = bbld_analytics()->widget_manager;
        $widgets = $widget_manager->get_registered_widgets();
        
        if (isset($_POST['save_widgets']) && wp_verify_nonce($_POST['_wpnonce'], 'bbld_analytics_widgets')) {
            $this->save_widget_settings();
            echo '<div class="notice notice-success"><p>' . __('Widget settings saved.', 'bbld-analytics') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('bbld_analytics_widgets'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Widget', 'bbld-analytics'); ?></th>
                            <th scope="col"><?php _e('Description', 'bbld-analytics'); ?></th>
                            <th scope="col"><?php _e('Status', 'bbld-analytics'); ?></th>
                            <th scope="col"><?php _e('Actions', 'bbld-analytics'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($widgets as $widget_id => $widget): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($widget->get_title()); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html($widget->get_description()); ?>
                                </td>
                                <td>
                                    <label class="widget-toggle">
                                        <input type="checkbox" name="enabled_widgets[]" value="<?php echo esc_attr($widget_id); ?>" <?php checked($widget->is_enabled()); ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="button button-small widget-configure" data-widget="<?php echo esc_attr($widget_id); ?>">
                                        <?php _e('Configure', 'bbld-analytics'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_widgets" class="button-primary" value="<?php _e('Save Changes', 'bbld-analytics'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * General settings section
     */
    public function general_settings_section() {
        echo '<p>' . __('Configure general plugin settings.', 'bbld-analytics') . '</p>';
    }
    
    /**
     * Courses settings section
     */
    public function courses_settings_section() {
        echo '<p>' . __('Select the base courses that are shared across all groups.', 'bbld-analytics') . '</p>';
    }
    
    /**
     * Update frequency field
     */
    public function update_frequency_field() {
        $options = bbld_analytics()->get_options();
        $current = isset($options['update_frequency']) ? $options['update_frequency'] : 'daily';
        
        $frequencies = array(
            'hourly' => __('Hourly', 'bbld-analytics'),
            'daily' => __('Daily', 'bbld-analytics'),
            'weekly' => __('Weekly', 'bbld-analytics')
        );
        
        echo '<select name="bbld_analytics_options[update_frequency]">';
        foreach ($frequencies as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('How often should analytics data be updated automatically.', 'bbld-analytics') . '</p>';
    }
    
    /**
     * Refresh interval field
     */
    public function refresh_interval_field() {
        $options = bbld_analytics()->get_options();
        $current = isset($options['dashboard_refresh_interval']) ? $options['dashboard_refresh_interval'] : 300;
        
        echo '<input type="number" name="bbld_analytics_options[dashboard_refresh_interval]" value="' . esc_attr($current) . '" min="60" max="3600" step="60">';
        echo '<p class="description">' . __('Dashboard auto-refresh interval in seconds (60-3600).', 'bbld-analytics') . '</p>';
    }
    
    /**
     * Shared courses field
     */
    public function shared_courses_field() {
        $options = bbld_analytics()->get_options();
        $selected_courses = isset($options['shared_courses']) ? $options['shared_courses'] : array();
        
        // Get all LearnDash courses
        $courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        if (empty($courses)) {
            echo '<p>' . __('No LearnDash courses found.', 'bbld-analytics') . '</p>';
            return;
        }
        
        echo '<div class="shared-courses-list">';
        foreach ($courses as $course) {
            $checked = in_array($course->ID, $selected_courses);
            echo '<label class="course-checkbox">';
            echo '<input type="checkbox" name="bbld_analytics_options[shared_courses][]" value="' . esc_attr($course->ID) . '"' . checked($checked, true, false) . '>';
            echo esc_html($course->post_title);
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Select up to 3 courses that are shared across all groups for analytics tracking.', 'bbld-analytics') . '</p>';
    }
    
    /**
     * Validate options
     */
    public function validate_options($input) {
        $validated = array();
        
        // Validate update frequency
        $valid_frequencies = array('hourly', 'daily', 'weekly');
        if (isset($input['update_frequency']) && in_array($input['update_frequency'], $valid_frequencies)) {
            $validated['update_frequency'] = $input['update_frequency'];
        } else {
            $validated['update_frequency'] = 'daily';
        }
        
        // Validate refresh interval
        if (isset($input['dashboard_refresh_interval'])) {
            $interval = intval($input['dashboard_refresh_interval']);
            $validated['dashboard_refresh_interval'] = max(60, min(3600, $interval));
        } else {
            $validated['dashboard_refresh_interval'] = 300;
        }
        
        // Validate shared courses (limit to 3)
        if (isset($input['shared_courses']) && is_array($input['shared_courses'])) {
            $courses = array_map('intval', $input['shared_courses']);
            $validated['shared_courses'] = array_slice($courses, 0, 3);
        } else {
            $validated['shared_courses'] = array();
        }
        
        // Preserve other options
        $current_options = bbld_analytics()->get_options();
        $validated = array_merge($current_options, $validated);
        
        return $validated;
    }
    
    /**
     * Save widget settings
     */
    private function save_widget_settings() {
        $enabled_widgets = isset($_POST['enabled_widgets']) ? $_POST['enabled_widgets'] : array();
        $widget_manager = bbld_analytics()->widget_manager;
        $widgets = $widget_manager->get_registered_widgets();
        
        foreach ($widgets as $widget_id => $widget) {
            $is_enabled = in_array($widget_id, $enabled_widgets);
            $widget->set_enabled($is_enabled);
        }
    }
    
    /**
     * Save widget settings
     */
    private function save_widget_settings() {
        if (!isset($_POST['enabled_widgets']) || !is_array($_POST['enabled_widgets'])) {
            return false;
        }
        
        $enabled_widgets = array_map('sanitize_text_field', $_POST['enabled_widgets']);
        $current_options = bbld_analytics()->get_options();
        $current_options['enabled_widgets'] = $enabled_widgets;
        
        return bbld_analytics()->update_options($current_options);
    }
    
    /**
     * Check missing dependencies
     */
    public function check_missing_dependencies() {
        $missing = array();
        
        if (!class_exists('SFWD_LMS')) {
            $missing[] = 'LearnDash LMS';
        }
        
        if (!empty($missing)) {
            $message = sprintf(
                __('BBLD Analytics requires: %s', 'bbld-analytics'),
                implode(', ', $missing)
            );
            BBLD_Analytics_Utils::render_admin_notice($message, 'error');
        }
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check missing dependencies first
        $this->check_missing_dependencies();
        
        // Check if initial collection is done
        $initial_done = bbld_analytics()->get_option('initial_collection_done', false);
        
        if (!$initial_done && isset($_GET['page']) && strpos($_GET['page'], 'bbld-analytics') !== false) {
            echo '<div class="notice notice-info">';
            echo '<p>' . __('Initial data collection is in progress. Full analytics will be available shortly.', 'bbld-analytics') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Format number for display
     */
    private function format_number($number) {
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
    private function format_percentage($value, $decimals = 1) {
        return number_format($value, $decimals) . '%';
    }
}