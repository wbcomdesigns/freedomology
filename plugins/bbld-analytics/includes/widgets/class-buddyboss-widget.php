<?php
/**
 * BuddyBoss Analytics Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_BuddyBoss_Widget extends BBLD_Analytics_Abstract_Widget {
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->widget_id = 'buddyboss_analytics';
        $this->title = __('BuddyBoss Analytics', 'bbld-analytics');
        $this->description = __('Community engagement and activity metrics from BuddyBoss platform.', 'bbld-analytics');
    }
    
    /**
     * Setup data source
     */
    protected function setup_data_source() {
        $this->data_source = bbld_analytics()->data_collector->get_data_source('buddyboss');
    }
    
    /**
     * Get default configuration
     */
    protected function get_default_config() {
        return array(
            'show_posts' => true,
            'show_likes' => true,
            'show_active_users' => true,
            'show_contributors' => true,
            'period' => '30d',
            'contributors_limit' => 5
        );
    }
    
    /**
     * Get widget data
     */
    public function get_data($period = '30d') {
        if (!$this->data_source || !$this->data_source->is_available()) {
            throw new Exception(__('BuddyBoss data source not available', 'bbld-analytics'));
        }
        
        $period = BBLD_Analytics_Utils::validate_period($period);
        
        // Get cached data first
        $cache_key = $this->get_cache_key($period);
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        // Get fresh data
        $data = $this->data_source->get_analytics_data($period);
        
        // Process data for widget display
        $widget_data = array(
            'summary' => $this->get_summary_data($data),
            'charts' => $this->get_chart_data($data),
            'contributors' => $this->get_contributors_data($data)
        );
        
        // Cache the processed data
        $this->set_cached_data($cache_key, $widget_data, 300); // 5 minutes
        
        return $widget_data;
    }
    
    /**
     * Get summary data
     */
    private function get_summary_data($data) {
        return array(
            'total_posts' => isset($data['total_posts']) ? (int)$data['total_posts'] : 0,
            'total_likes' => isset($data['total_likes']) ? (int)$data['total_likes'] : 0,
            'active_users' => isset($data['active_users']) ? (int)$data['active_users'] : 0,
            'daily_posts' => isset($data['daily_posts']) ? (int)$data['daily_posts'] : 0,
            'group_activity' => isset($data['group_activity']) ? (int)$data['group_activity'] : 0,
            'forum_activity' => isset($data['forum_activity']) ? (int)$data['forum_activity'] : 0
        );
    }
    
    /**
     * Get chart data
     */
    private function get_chart_data($data) {
        $chart_data = array();
        
        // Activity breakdown pie chart
        if (isset($data['group_activity']) && isset($data['forum_activity']) && isset($data['total_posts'])) {
            $chart_data['activity_breakdown'] = array(
                'type' => 'doughnut',
                'labels' => array(
                    __('Community Posts', 'bbld-analytics'),
                    __('Group Activity', 'bbld-analytics'),
                    __('Forum Activity', 'bbld-analytics')
                ),
                'datasets' => array(
                    array(
                        'data' => array(
                            $data['total_posts'],
                            $data['group_activity'],
                            $data['forum_activity']
                        ),
                        'backgroundColor' => array('#2271b1', '#72aee6', '#00a32a')
                    )
                )
            );
        }
        
        // Activity trends line chart
        if (isset($data['trends']) && !empty($data['trends'])) {
            $chart_data['activity_trends'] = $this->prepare_trends_chart($data['trends']);
        }
        
        return $chart_data;
    }
    
    /**
     * Prepare trends chart data
     */
    private function prepare_trends_chart($trends) {
        $labels = array();
        $datasets = array();
        
        // Prepare labels from the first trend data
        if (!empty($trends)) {
            $first_trend = reset($trends);
            $labels = array_column($first_trend, 'date');
        }
        
        // Prepare datasets
        $trend_configs = array(
            'total_posts' => array(
                'label' => __('Total Posts', 'bbld-analytics'),
                'color' => '#2271b1'
            ),
            'daily_posts' => array(
                'label' => __('Daily Posts', 'bbld-analytics'),
                'color' => '#72aee6'
            ),
            'active_users' => array(
                'label' => __('Active Users', 'bbld-analytics'),
                'color' => '#00a32a'
            )
        );
        
        foreach ($trend_configs as $key => $config) {
            if (isset($trends[$key])) {
                $datasets[] = array(
                    'label' => $config['label'],
                    'data' => array_column($trends[$key], 'value'),
                    'borderColor' => $config['color'],
                    'backgroundColor' => $config['color'] . '20',
                    'fill' => false,
                    'tension' => 0.4
                );
            }
        }
        
        return array(
            'type' => 'line',
            'labels' => $labels,
            'datasets' => $datasets
        );
    }
    
    /**
     * Get contributors data
     */
    private function get_contributors_data($data) {
        $limit = $this->get_config_value('contributors_limit', 5);
        
        if (isset($data['top_contributors']) && !empty($data['top_contributors'])) {
            return array_slice($data['top_contributors'], 0, $limit);
        }
        
        return array();
    }
    
    /**
     * Render widget content
     */
    public function render() {
        try {
            $period = $this->get_config_value('period', '30d');
            $data = $this->get_data($period);
            
            $this->render_widget_header();
            $this->render_summary_cards($data['summary']);
            $this->render_content_tabs($data);
            
        } catch (Exception $e) {
            $this->render_error($e->getMessage());
        }
    }
    
    /**
     * Render widget header
     */
    private function render_widget_header() {
        ?>
        <div class="widget-header-controls">
            <select class="period-selector" data-widget="<?php echo esc_attr($this->widget_id); ?>">
                <?php foreach (BBLD_Analytics_Utils::get_period_options() as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($this->get_config_value('period'), $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
    
    /**
     * Render summary cards
     */
    private function render_summary_cards($summary) {
        ?>
        <div class="summary-cards-grid">
            <?php if ($this->get_config_value('show_posts', true)): ?>
                <?php $this->render_metric_card(__('Total Posts', 'bbld-analytics'), $summary['total_posts']); ?>
            <?php endif; ?>
            
            <?php if ($this->get_config_value('show_likes', true)): ?>
                <?php $this->render_metric_card(__('Total Likes', 'bbld-analytics'), $summary['total_likes']); ?>
            <?php endif; ?>
            
            <?php if ($this->get_config_value('show_active_users', true)): ?>
                <?php $this->render_metric_card(__('Active Users', 'bbld-analytics'), $summary['active_users']); ?>
            <?php endif; ?>
            
            <?php $this->render_metric_card(__('Daily Posts', 'bbld-analytics'), $summary['daily_posts']); ?>
        </div>
        <?php
    }
    
    /**
     * Render content tabs
     */
    private function render_content_tabs($data) {
        ?>
        <div class="widget-tabs">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="overview">
                    <?php _e('Overview', 'bbld-analytics'); ?>
                </button>
                <?php if ($this->get_config_value('show_contributors', true) && !empty($data['contributors'])): ?>
                <button class="tab-button" data-tab="contributors">
                    <?php _e('Top Contributors', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                <button class="tab-button" data-tab="activity">
                    <?php _e('Activity Breakdown', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="trends">
                    <?php _e('Trends', 'bbld-analytics'); ?>
                </button>
            </div>
            
            <div class="tab-content">
                <div class="tab-panel active" id="tab-overview">
                    <?php $this->render_overview_tab($data); ?>
                </div>
                
                <?php if ($this->get_config_value('show_contributors', true) && !empty($data['contributors'])): ?>
                <div class="tab-panel" id="tab-contributors">
                    <?php $this->render_contributors_tab($data['contributors']); ?>
                </div>
                <?php endif; ?>
                
                <div class="tab-panel" id="tab-activity">
                    <?php $this->render_activity_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-trends">
                    <?php $this->render_trends_tab($data); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render overview tab
     */
    private function render_overview_tab($data) {
        ?>
        <div class="overview-content">
            <div class="activity-stats">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Group Activity', 'bbld-analytics'); ?></span>
                    <span class="stat-value"><?php echo esc_html(BBLD_Analytics_Utils::format_number($data['summary']['group_activity'])); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Forum Activity', 'bbld-analytics'); ?></span>
                    <span class="stat-value"><?php echo esc_html(BBLD_Analytics_Utils::format_number($data['summary']['forum_activity'])); ?></span>
                </div>
            </div>
            
            <p class="overview-description">
                <?php _e('Your community is showing strong engagement with regular posting activity and active user participation.', 'bbld-analytics'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render contributors tab
     */
    private function render_contributors_tab($contributors) {
        ?>
        <div class="contributors-content">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'bbld-analytics'); ?></th>
                        <th><?php _e('Activity Count', 'bbld-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contributors as $contributor): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($contributor['display_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo esc_html(BBLD_Analytics_Utils::format_number($contributor['activity_count'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render activity breakdown tab
     */
    private function render_activity_tab($data) {
        if (isset($data['charts']['activity_breakdown'])) {
            ?>
            <div class="chart-container">
                <canvas id="activity-breakdown-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('activity-breakdown-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['activity_breakdown']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No activity data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render trends tab
     */
    private function render_trends_tab($data) {
        if (isset($data['charts']['activity_trends'])) {
            ?>
            <div class="chart-container">
                <canvas id="activity-trends-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('activity-trends-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['activity_trends']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No trends data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Enqueue widget-specific styles
     */
    public function enqueue_styles() {
        wp_add_inline_style('bbld-analytics-admin', '
            .activity-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            
            .stat-item {
                text-align: center;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 3px solid #2271b1;
            }
            
            .stat-label {
                display: block;
                font-size: 12px;
                color: #646970;
                margin-bottom: 5px;
                text-transform: uppercase;
            }
            
            .stat-value {
                display: block;
                font-size: 20px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .overview-description {
                margin-top: 20px;
                padding: 15px;
                background: #e7f3ff;
                border-radius: 4px;
                color: #0073aa;
            }
            
            .contributors-content .wp-list-table {
                margin-top: 0;
            }
        ');
    }
}