<?php
/**
 * Widget Manager Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Widget_Manager {
    
    /**
     * Registered widgets
     */
    private $widgets = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_default_widgets();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_widget_assets'));
    }
    
    /**
     * Register default widgets
     */
    private function register_default_widgets() {
        // Register BuddyBoss widget
        if (class_exists('BuddyPress') || function_exists('bp_is_active')) {
            $this->register_widget('buddyboss_analytics', new BBLD_Analytics_BuddyBoss_Widget());
        }
        
        // Register LearnDash widgets
        if (class_exists('SFWD_LMS')) {
            $this->register_widget('learndash_groups', new BBLD_Analytics_LearnDash_Widget());
            $this->register_widget('course_engagement', new BBLD_Analytics_Course_Engagement_Widget());
            $this->register_widget('group_leaders', new BBLD_Analytics_Group_Leaders_Widget());
        }
        
        // Register Platform widget
        $this->register_widget('platform_overview', new BBLD_Analytics_Platform_Widget());
        
        // Allow third-party widgets
        do_action('bbld_analytics_register_widgets', $this);
    }
    
    /**
     * Register a widget
     */
    public function register_widget($widget_id, $widget_instance) {
        if (!($widget_instance instanceof BBLD_Analytics_Abstract_Widget)) {
            return new WP_Error('invalid_widget', __('Widget must extend BBLD_Analytics_Abstract_Widget', 'bbld-analytics'));
        }
        
        $this->widgets[$widget_id] = $widget_instance;
        
        return true;
    }
    
    /**
     * Unregister a widget
     */
    public function unregister_widget($widget_id) {
        unset($this->widgets[$widget_id]);
    }
    
    /**
     * Get registered widgets
     */
    public function get_registered_widgets() {
        return $this->widgets;
    }
    
    /**
     * Get active widgets
     */
    public function get_active_widgets() {
        $active_widgets = array();
        
        foreach ($this->widgets as $widget_id => $widget) {
            if ($widget->is_enabled()) {
                $active_widgets[$widget_id] = $widget;
            }
        }
        
        return $active_widgets;
    }
    
    /**
     * Get widget by ID
     */
    public function get_widget($widget_id) {
        return isset($this->widgets[$widget_id]) ? $this->widgets[$widget_id] : null;
    }
    
    /**
     * Render dashboard widgets
     */
    public function render_dashboard_widgets() {
        $active_widgets = $this->get_active_widgets();
        
        if (empty($active_widgets)) {
            echo '<div class="no-widgets-message">';
            echo '<p>' . __('No active widgets found. Please configure widgets in the settings.', 'bbld-analytics') . '</p>';
            echo '</div>';
            return;
        }
        
        foreach ($active_widgets as $widget_id => $widget) {
            echo '<div class="widget-container" data-widget-id="' . esc_attr($widget_id) . '">';
            $widget->render_container();
            echo '</div>';
        }
    }
    
    /**
     * Add WordPress dashboard widgets
     */
    public function add_dashboard_widgets() {
        // Only add to main dashboard if user has permission
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add summary widget to WordPress dashboard
        wp_add_dashboard_widget(
            'bbld_analytics_summary',
            __('BBLD Analytics Summary', 'bbld-analytics'),
            array($this, 'render_dashboard_summary_widget')
        );
    }
    
    /**
     * Render WordPress dashboard summary widget
     */
    public function render_dashboard_summary_widget() {
        $data_collector = bbld_analytics()->data_collector;
        $summary_metrics = $data_collector->get_summary_metrics();
        
        ?>
        <div class="bbld-dashboard-summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html($this->format_number($summary_metrics['total_groups'])); ?></span>
                    <span class="summary-label"><?php _e('Groups', 'bbld-analytics'); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html($this->format_number($summary_metrics['total_learners'])); ?></span>
                    <span class="summary-label"><?php _e('Learners', 'bbld-analytics'); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html($this->format_number($summary_metrics['active_learners'])); ?></span>
                    <span class="summary-label"><?php _e('Active', 'bbld-analytics'); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html($this->format_percentage($summary_metrics['completion_rate'])); ?></span>
                    <span class="summary-label"><?php _e('Completion', 'bbld-analytics'); ?></span>
                </div>
            </div>
            <div class="summary-actions">
                <a href="<?php echo admin_url('admin.php?page=bbld-analytics'); ?>" class="button button-primary">
                    <?php _e('View Full Analytics', 'bbld-analytics'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .bbld-dashboard-summary .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        .bbld-dashboard-summary .summary-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .bbld-dashboard-summary .summary-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        .bbld-dashboard-summary .summary-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .bbld-dashboard-summary .summary-actions {
            text-align: center;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue widget assets
     */
    public function enqueue_widget_assets($hook) {
        // Only load on our admin pages or dashboard
        if (strpos($hook, 'bbld-analytics') === false && $hook !== 'index.php') {
            return;
        }
        
        // Enqueue widget-specific assets
        foreach ($this->widgets as $widget) {
            if (method_exists($widget, 'enqueue_scripts')) {
                $widget->enqueue_scripts();
            }
            if (method_exists($widget, 'enqueue_styles')) {
                $widget->enqueue_styles();
            }
        }
    }
    
    /**
     * Get widget data via AJAX
     */
    public function get_widget_data($widget_id, $period = '30d') {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return new WP_Error('widget_not_found', __('Widget not found', 'bbld-analytics'));
        }
        
        if (!$widget->is_enabled()) {
            return new WP_Error('widget_disabled', __('Widget is disabled', 'bbld-analytics'));
        }
        
        try {
            return $widget->get_data($period);
        } catch (Exception $e) {
            return new WP_Error('widget_error', $e->getMessage());
        }
    }
    
    /**
     * Update widget configuration
     */
    public function update_widget_config($widget_id, $config) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return new WP_Error('widget_not_found', __('Widget not found', 'bbld-analytics'));
        }
        
        return $widget->update_config($config);
    }
    
    /**
     * Toggle widget status
     */
    public function toggle_widget($widget_id, $enabled) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return new WP_Error('widget_not_found', __('Widget not found', 'bbld-analytics'));
        }
        
        return $widget->set_enabled($enabled);
    }
    
    /**
     * Clear all widget caches
     */
    public function clear_all_caches() {
        foreach ($this->widgets as $widget) {
            $widget->clear_cache();
        }
    }
    
    /**
     * Get widget layout configuration
     */
    public function get_layout_config() {
        $layout = get_option('bbld_analytics_widget_layout', array());
        
        // Default layout if none exists
        if (empty($layout)) {
            $layout = array(
                'columns' => 2,
                'widgets' => array_keys($this->get_active_widgets())
            );
        }
        
        return $layout;
    }
    
    /**
     * Update widget layout
     */
    public function update_layout_config($layout) {
        return update_option('bbld_analytics_widget_layout', $layout);
    }
    
    /**
     * Render widget configuration modal
     */
    public function render_config_modal() {
        ?>
        <div id="widget-config-modal" class="bbld-modal" style="display: none;">
            <div class="bbld-modal-content">
                <div class="bbld-modal-header">
                    <h3><?php _e('Widget Configuration', 'bbld-analytics'); ?></h3>
                    <button type="button" class="bbld-modal-close">&times;</button>
                </div>
                <div class="bbld-modal-body">
                    <form id="widget-config-form">
                        <div id="widget-config-fields"></div>
                        <div class="form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Save Changes', 'bbld-analytics'); ?></button>
                            <button type="button" class="button button-secondary bbld-modal-close"><?php _e('Cancel', 'bbld-analytics'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get widget configuration fields
     */
    public function get_widget_config_fields($widget_id) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return array();
        }
        
        $config = $widget->get_config();
        $fields = array();
        
        // Common configuration fields
        $fields['title'] = array(
            'type' => 'text',
            'label' => __('Widget Title', 'bbld-analytics'),
            'value' => $widget->get_title(),
            'description' => __('Custom title for this widget', 'bbld-analytics')
        );
        
        $fields['period'] = array(
            'type' => 'select',
            'label' => __('Default Period', 'bbld-analytics'),
            'value' => isset($config['period']) ? $config['period'] : '30d',
            'options' => array(
                '7d' => __('Last 7 days', 'bbld-analytics'),
                '30d' => __('Last 30 days', 'bbld-analytics'),
                '90d' => __('Last 90 days', 'bbld-analytics'),
                '1y' => __('Last year', 'bbld-analytics')
            ),
            'description' => __('Default time period for widget data', 'bbld-analytics')
        );
        
        // Widget-specific fields
        $widget_fields = apply_filters("bbld_analytics_widget_config_fields_{$widget_id}", array(), $config);
        
        return array_merge($fields, $widget_fields);
    }
    
    /**
     * Validate widget configuration
     */
    public function validate_widget_config($widget_id, $config) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return new WP_Error('widget_not_found', __('Widget not found', 'bbld-analytics'));
        }
        
        $validated = array();
        
        // Validate common fields
        if (isset($config['period'])) {
            $valid_periods = array('7d', '30d', '90d', '1y');
            $validated['period'] = in_array($config['period'], $valid_periods) ? $config['period'] : '30d';
        }
        
        // Allow widget-specific validation
        $validated = apply_filters("bbld_analytics_validate_widget_config_{$widget_id}", $validated, $config);
        
        return $validated;
    }
    
    /**
     * Export widget configuration
     */
    public function export_widget_config() {
        $config = array();
        
        foreach ($this->widgets as $widget_id => $widget) {
            $config[$widget_id] = array(
                'enabled' => $widget->is_enabled(),
                'config' => $widget->get_config()
            );
        }
        
        return $config;
    }
    
    /**
     * Import widget configuration
     */
    public function import_widget_config($config) {
        $imported = 0;
        
        foreach ($config as $widget_id => $widget_config) {
            $widget = $this->get_widget($widget_id);
            
            if (!$widget) {
                continue;
            }
            
            // Set enabled status
            if (isset($widget_config['enabled'])) {
                $widget->set_enabled($widget_config['enabled']);
            }
            
            // Update configuration
            if (isset($widget_config['config'])) {
                $widget->update_config($widget_config['config']);
            }
            
            $imported++;
        }
        
        return $imported;
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
    
    /**
     * Get widget performance metrics
     */
    public function get_widget_performance() {
        $performance = array();
        
        foreach ($this->widgets as $widget_id => $widget) {
            $start_time = microtime(true);
            
            try {
                $widget->get_data('7d');
                $load_time = microtime(true) - $start_time;
                $status = 'success';
                $error = null;
            } catch (Exception $e) {
                $load_time = microtime(true) - $start_time;
                $status = 'error';
                $error = $e->getMessage();
            }
            
            $performance[$widget_id] = array(
                'load_time' => round($load_time, 4),
                'status' => $status,
                'error' => $error,
                'enabled' => $widget->is_enabled()
            );
        }
        
        return $performance;
    }
}