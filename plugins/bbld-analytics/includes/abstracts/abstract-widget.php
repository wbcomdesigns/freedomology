<?php
/**
 * Abstract Widget Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

abstract class BBLD_Analytics_Abstract_Widget {
    
    /**
     * Widget ID
     */
    protected $widget_id;
    
    /**
     * Widget title
     */
    protected $title;
    
    /**
     * Widget description
     */
    protected $description;
    
    /**
     * Widget configuration
     */
    protected $config;
    
    /**
     * Data source
     */
    protected $data_source;
    
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
        $this->setup_data_source();
        $this->load_config();
    }
    
    /**
     * Initialize widget
     */
    abstract protected function init();
    
    /**
     * Setup data source
     */
    abstract protected function setup_data_source();
    
    /**
     * Get widget data
     */
    abstract public function get_data($period = '30d');
    
    /**
     * Render widget
     */
    abstract public function render();
    
    /**
     * Get widget ID
     */
    public function get_id() {
        return $this->widget_id;
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return $this->title;
    }
    
    /**
     * Get widget description
     */
    public function get_description() {
        return $this->description;
    }
    
    /**
     * Get widget configuration
     */
    public function get_config() {
        return $this->config;
    }
    
    /**
     * Load widget configuration from database
     */
    protected function load_config() {
        $widget_config = $this->database->get_widget_config($this->widget_id);
        
        if ($widget_config && $widget_config->widget_config) {
            $this->config = array_merge($this->get_default_config(), $widget_config->widget_config);
        } else {
            $this->config = $this->get_default_config();
        }
    }
    
    /**
     * Get default configuration
     */
    protected function get_default_config() {
        return array();
    }
    
    /**
     * Update widget configuration
     */
    public function update_config($config) {
        $this->config = array_merge($this->config, $config);
        return $this->database->update_widget_config($this->widget_id, $this->config);
    }
    
    /**
     * Get configuration value
     */
    protected function get_config_value($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Check if widget is enabled
     */
    public function is_enabled() {
        $widget_config = $this->database->get_widget_config($this->widget_id);
        return $widget_config ? (bool) $widget_config->is_active : true;
    }
    
    /**
     * Enable/disable widget
     */
    public function set_enabled($enabled) {
        return $this->database->toggle_widget($this->widget_id, $enabled);
    }
    
    /**
     * Render widget container
     */
    public function render_container() {
        if (!$this->is_enabled()) {
            return;
        }
        
        $widget_class = 'bbld-analytics-widget bbld-analytics-widget-' . $this->widget_id;
        
        echo '<div class="' . esc_attr($widget_class) . '" id="widget-' . esc_attr($this->widget_id) . '">';
        echo '<div class="widget-header">';
        echo '<h3 class="widget-title">' . esc_html($this->title) . '</h3>';
        
        if ($this->description) {
            echo '<p class="widget-description">' . esc_html($this->description) . '</p>';
        }
        
        $this->render_widget_controls();
        echo '</div>';
        
        echo '<div class="widget-content">';
        $this->render();
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render widget controls
     */
    protected function render_widget_controls() {
        echo '<div class="widget-controls">';
        echo '<button type="button" class="button widget-refresh" data-widget="' . esc_attr($this->widget_id) . '">';
        echo '<span class="dashicons dashicons-update"></span>';
        echo '</button>';
        echo '</div>';
    }
    
    /**
     * Format number for display
     */
    protected function format_number($number) {
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
    protected function format_percentage($value, $decimals = 1) {
        return number_format($value, $decimals) . '%';
    }
    
    /**
     * Get trend indicator
     */
    protected function get_trend_indicator($current, $previous) {
        if ($previous == 0) {
            return array('direction' => 'neutral', 'percentage' => 0);
        }
        
        $change = (($current - $previous) / $previous) * 100;
        
        if ($change > 0) {
            $direction = 'up';
        } elseif ($change < 0) {
            $direction = 'down';
            $change = abs($change);
        } else {
            $direction = 'neutral';
        }
        
        return array(
            'direction' => $direction,
            'percentage' => round($change, 1)
        );
    }
    
    /**
     * Render trend indicator
     */
    protected function render_trend_indicator($current, $previous, $label = '') {
        $trend = $this->get_trend_indicator($current, $previous);
        
        $class = 'trend-indicator trend-' . $trend['direction'];
        $icon = '';
        
        switch ($trend['direction']) {
            case 'up':
                $icon = 'arrow-up-alt';
                break;
            case 'down':
                $icon = 'arrow-down-alt';
                break;
            default:
                $icon = 'minus';
        }
        
        echo '<span class="' . esc_attr($class) . '">';
        echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
        
        if ($trend['percentage'] > 0) {
            echo esc_html($trend['percentage']) . '%';
        }
        
        if ($label) {
            echo ' ' . esc_html($label);
        }
        
        echo '</span>';
    }
    
    /**
     * Render metric card
     */
    protected function render_metric_card($title, $value, $previous_value = null, $format = 'number') {
        echo '<div class="metric-card">';
        echo '<div class="metric-title">' . esc_html($title) . '</div>';
        echo '<div class="metric-value">';
        
        if ($format === 'percentage') {
            echo esc_html($this->format_percentage($value));
        } else {
            echo esc_html($this->format_number($value));
        }
        
        echo '</div>';
        
        if ($previous_value !== null) {
            echo '<div class="metric-trend">';
            $this->render_trend_indicator($value, $previous_value);
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render loading state
     */
    protected function render_loading() {
        echo '<div class="widget-loading">';
        echo '<div class="spinner is-active"></div>';
        echo '<p>' . __('Loading data...', 'bbld-analytics') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render error state
     */
    protected function render_error($message = '') {
        if (!$message) {
            $message = __('Unable to load widget data.', 'bbld-analytics');
        }
        
        echo '<div class="widget-error">';
        echo '<div class="error-icon">';
        echo '<span class="dashicons dashicons-warning"></span>';
        echo '</div>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }
    
    /**
     * Render empty state
     */
    protected function render_empty($message = '') {
        if (!$message) {
            $message = __('No data available for this widget.', 'bbld-analytics');
        }
        
        echo '<div class="widget-empty">';
        echo '<div class="empty-icon">';
        echo '<span class="dashicons dashicons-chart-area"></span>';
        echo '</div>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
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
     * Enqueue widget scripts
     */
    public function enqueue_scripts() {
        // Override in child classes if needed
    }
    
    /**
     * Enqueue widget styles
     */
    public function enqueue_styles() {
        // Override in child classes if needed
    }
    
    /**
     * Get widget cache key
     */
    protected function get_cache_key($suffix = '') {
        $key = 'bbld_widget_' . $this->widget_id;
        if ($suffix) {
            $key .= '_' . $suffix;
        }
        return $key;
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
    protected function set_cached_data($key, $data, $expiration = 300) {
        return set_transient($key, $data, $expiration);
    }
    
    /**
     * Clear widget cache
     */
    public function clear_cache() {
        $cache_keys = array(
            $this->get_cache_key(),
            $this->get_cache_key('7d'),
            $this->get_cache_key('30d'),
            $this->get_cache_key('90d'),
            $this->get_cache_key('1y')
        );
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
}