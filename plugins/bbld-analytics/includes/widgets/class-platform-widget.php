<?php
/**
 * Platform Overview Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Platform_Widget extends BBLD_Analytics_Abstract_Widget {
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->widget_id = 'platform_overview';
        $this->title = __('Platform Overview', 'bbld-analytics');
        $this->description = __('Overall platform health, user activity metrics, and registration trends.', 'bbld-analytics');
    }
    
    /**
     * Setup data source
     */
    protected function setup_data_source() {
        $this->data_source = bbld_analytics()->data_collector->get_data_source('platform');
    }
    
    /**
     * Get default configuration
     */
    protected function get_default_config() {
        return array(
            'show_registrations' => true,
            'show_engagement' => true,
            'show_trends' => true,
            'period' => '7d',
            'chart_type' => 'line'
        );
    }
    
    /**
     * Get widget data
     */
    public function get_data($period = '7d') {
        if (!$this->data_source || !$this->data_source->is_available()) {
            throw new Exception(__('Platform data source not available', 'bbld-analytics'));
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
            'insights' => $this->get_insights_data($data)
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
            'total_users' => isset($data['total_users']) ? (int)$data['total_users'] : 0,
            'new_registrations' => isset($data['new_registrations']) ? (int)$data['new_registrations'] : 0,
            'daily_active_users' => isset($data['daily_active_users']) ? (int)$data['daily_active_users'] : 0,
            'weekly_active_users' => isset($data['weekly_active_users']) ? (int)$data['weekly_active_users'] : 0,
            'monthly_active_users' => isset($data['monthly_active_users']) ? (int)$data['monthly_active_users'] : 0,
            'engagement_score' => isset($data['engagement_score']) ? (float)$data['engagement_score'] : 0
        );
    }
    
    /**
     * Get chart data
     */
    private function get_chart_data($data) {
        $chart_data = array();
        
        // Registration trends chart
        if (isset($data['registration_trends']) && !empty($data['registration_trends'])) {
            $chart_data['registration_trends'] = array(
                'type' => 'line',
                'labels' => array_column($data['registration_trends'], 'date'),
                'datasets' => array(
                    array(
                        'label' => __('Registrations', 'bbld-analytics'),
                        'data' => array_column($data['registration_trends'], 'registrations'),
                        'borderColor' => '#2271b1',
                        'backgroundColor' => '#2271b120',
                        'fill' => true,
                        'tension' => 0.4
                    )
                )
            );
        }
        
        // User role distribution chart
        if (isset($data['user_role_distribution']) && !empty($data['user_role_distribution'])) {
            $chart_data['role_distribution'] = array(
                'type' => 'doughnut',
                'labels' => array_keys($data['user_role_distribution']),
                'datasets' => array(
                    array(
                        'data' => array_values($data['user_role_distribution']),
                        'backgroundColor' => BBLD_Analytics_Utils::generate_chart_colors(count($data['user_role_distribution']))
                    )
                )
            );
        }
        
        // Active users trends
        if (isset($data['trends']) && !empty($data['trends'])) {
            $chart_data['active_users_trends'] = $this->prepare_active_users_chart($data['trends']);
        }
        
        return $chart_data;
    }
    
    /**
     * Prepare active users trends chart
     */
    private function prepare_active_users_chart($trends) {
        $labels = array();
        $datasets = array();
        
        // Prepare labels from the first trend data
        if (!empty($trends)) {
            $first_trend = reset($trends);
            $labels = array_column($first_trend, 'date');
        }
        
        // Prepare datasets
        $trend_configs = array(
            'daily_active_users' => array(
                'label' => __('Daily Active', 'bbld-analytics'),
                'color' => '#2271b1'
            ),
            'weekly_active_users' => array(
                'label' => __('Weekly Active', 'bbld-analytics'),
                'color' => '#72aee6'
            ),
            'monthly_active_users' => array(
                'label' => __('Monthly Active', 'bbld-analytics'),
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
     * Get insights data
     */
    private function get_insights_data($data) {
        $insights = array();
        
        // Calculate user engagement insights
        if (isset($data['growth'])) {
            foreach ($data['growth'] as $metric => $growth_data) {
                if ($growth_data['growth_rate'] > 0) {
                    $insights[] = array(
                        'type' => 'positive',
                        'message' => sprintf(
                            __('%s increased by %s%% compared to previous period', 'bbld-analytics'),
                            ucfirst(str_replace('_', ' ', $metric)),
                            number_format($growth_data['growth_rate'], 1)
                        )
                    );
                } elseif ($growth_data['growth_rate'] < -5) {
                    $insights[] = array(
                        'type' => 'warning',
                        'message' => sprintf(
                            __('%s decreased by %s%% - consider engagement initiatives', 'bbld-analytics'),
                            ucfirst(str_replace('_', ' ', $metric)),
                            number_format(abs($growth_data['growth_rate']), 1)
                        )
                    );
                }
            }
        }
        
        // Engagement score insights
        $engagement = isset($data['engagement_score']) ? $data['engagement_score'] : 0;
        if ($engagement > 70) {
            $insights[] = array(
                'type' => 'positive',
                'message' => __('Excellent engagement score! Your platform is highly active.', 'bbld-analytics')
            );
        } elseif ($engagement > 40) {
            $insights[] = array(
                'type' => 'info',
                'message' => __('Good engagement score with room for improvement.', 'bbld-analytics')
            );
        } else {
            $insights[] = array(
                'type' => 'warning',
                'message' => __('Low engagement score - consider user retention strategies.', 'bbld-analytics')
            );
        }
        
        return $insights;
    }
    
    /**
     * Render widget content
     */
    public function render() {
        try {
            $period = $this->get_config_value('period', '7d');
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
            <?php $this->render_metric_card(__('Total Users', 'bbld-analytics'), $summary['total_users']); ?>
            
            <?php if ($this->get_config_value('show_registrations', true)): ?>
                <?php $this->render_metric_card(__('New Registrations', 'bbld-analytics'), $summary['new_registrations']); ?>
            <?php endif; ?>
            
            <?php $this->render_metric_card(__('Monthly Active', 'bbld-analytics'), $summary['monthly_active_users']); ?>
            
            <?php if ($this->get_config_value('show_engagement', true)): ?>
                <?php $this->render_metric_card(__('Engagement Score', 'bbld-analytics'), $summary['engagement_score'], null, 'percentage'); ?>
            <?php endif; ?>
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
                <button class="tab-button active" data-tab="activity">
                    <?php _e('User Activity', 'bbld-analytics'); ?>
                </button>
                <?php if ($this->get_config_value('show_trends', true)): ?>
                <button class="tab-button" data-tab="trends">
                    <?php _e('Registration Trends', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                <button class="tab-button" data-tab="distribution">
                    <?php _e('User Roles', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="insights">
                    <?php _e('Insights', 'bbld-analytics'); ?>
                </button>
            </div>
            
            <div class="tab-content">
                <div class="tab-panel active" id="tab-activity">
                    <?php $this->render_activity_tab($data); ?>
                </div>
                
                <?php if ($this->get_config_value('show_trends', true)): ?>
                <div class="tab-panel" id="tab-trends">
                    <?php $this->render_trends_tab($data); ?>
                </div>
                <?php endif; ?>
                
                <div class="tab-panel" id="tab-distribution">
                    <?php $this->render_distribution_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-insights">
                    <?php $this->render_insights_tab($data['insights']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render activity tab
     */
    private function render_activity_tab($data) {
        if (isset($data['charts']['active_users_trends'])) {
            ?>
            <div class="chart-container">
                <canvas id="active-users-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <div class="activity-summary">
                <div class="activity-grid">
                    <div class="activity-item">
                        <span class="activity-number"><?php echo esc_html(BBLD_Analytics_Utils::format_number($data['summary']['daily_active_users'])); ?></span>
                        <span class="activity-label"><?php _e('Daily Active', 'bbld-analytics'); ?></span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-number"><?php echo esc_html(BBLD_Analytics_Utils::format_number($data['summary']['weekly_active_users'])); ?></span>
                        <span class="activity-label"><?php _e('Weekly Active', 'bbld-analytics'); ?></span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-number"><?php echo esc_html(BBLD_Analytics_Utils::format_number($data['summary']['monthly_active_users'])); ?></span>
                        <span class="activity-label"><?php _e('Monthly Active', 'bbld-analytics'); ?></span>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('active-users-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['active_users_trends']); ?>);
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
        if (isset($data['charts']['registration_trends'])) {
            ?>
            <div class="chart-container">
                <canvas id="registration-trends-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('registration-trends-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['registration_trends']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No registration trends data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render distribution tab
     */
    private function render_distribution_tab($data) {
        if (isset($data['charts']['role_distribution'])) {
            ?>
            <div class="chart-container">
                <canvas id="role-distribution-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('role-distribution-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['role_distribution']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No user role distribution data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render insights tab
     */
    private function render_insights_tab($insights) {
        if (!empty($insights)) {
            ?>
            <div class="insights-content">
                <?php foreach ($insights as $insight): ?>
                <div class="insight-item insight-<?php echo esc_attr($insight['type']); ?>">
                    <span class="insight-icon dashicons dashicons-<?php echo $this->get_insight_icon($insight['type']); ?>"></span>
                    <span class="insight-message"><?php echo esc_html($insight['message']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
        } else {
            $this->render_empty(__('No insights available for this period.', 'bbld-analytics'));
        }
    }
    
    /**
     * Get insight icon based on type
     */
    private function get_insight_icon($type) {
        switch ($type) {
            case 'positive':
                return 'yes-alt';
            case 'warning':
                return 'warning';
            case 'info':
            default:
                return 'info';
        }
    }
    
    /**
     * Enqueue widget-specific styles
     */
    public function enqueue_styles() {
        wp_add_inline_style('bbld-analytics-admin', '
            .activity-summary {
                margin-top: 20px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .activity-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
            }
            
            .activity-item {
                text-align: center;
                padding: 10px;
                background: white;
                border-radius: 4px;
                border-left: 3px solid #2271b1;
            }
            
            .activity-number {
                display: block;
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 5px;
            }
            
            .activity-label {
                font-size: 12px;
                color: #646970;
                text-transform: uppercase;
            }
            
            .insights-content {
                padding: 10px 0;
            }
            
            .insight-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 4px;
                border-left: 4px solid;
            }
            
            .insight-positive {
                background: #d4edda;
                border-left-color: #28a745;
                color: #155724;
            }
            
            .insight-warning {
                background: #fff3cd;
                border-left-color: #ffc107;
                color: #856404;
            }
            
            .insight-info {
                background: #d1ecf1;
                border-left-color: #17a2b8;
                color: #0c5460;
            }
            
            .insight-icon {
                font-size: 18px;
            }
            
            .insight-message {
                flex: 1;
                font-size: 14px;
            }
        ');
    }
}