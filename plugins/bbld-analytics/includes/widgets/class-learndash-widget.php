<?php
/**
 * LearnDash Groups Analytics Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_LearnDash_Widget extends BBLD_Analytics_Abstract_Widget {
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->widget_id = 'learndash_groups';
        $this->title = __('LearnDash Groups Analytics', 'bbld-analytics');
        $this->description = __('Overview of LearnDash groups performance and engagement metrics.', 'bbld-analytics');
    }
    
    /**
     * Setup data source
     */
    protected function setup_data_source() {
        $this->data_source = bbld_analytics()->data_collector->get_data_source('learndash_groups');
    }
    
    /**
     * Get default configuration
     */
    protected function get_default_config() {
        return array(
            'show_enrollment' => true,
            'show_engagement' => true,
            'show_completions' => true,
            'top_groups_count' => 5,
            'period' => '30d',
            'chart_type' => 'bar'
        );
    }
    
    /**
     * Get widget data
     */
    public function get_data($period = '30d') {
        if (!$this->data_source || !$this->data_source->is_available()) {
            throw new Exception(__('LearnDash data source not available', 'bbld-analytics'));
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
            'tables' => $this->get_table_data($data),
            'trends' => $this->get_trend_data($data)
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
            'total_groups' => isset($data['total_groups']) ? (int)$data['total_groups'] : 0,
            'total_students' => isset($data['total_students']) ? (int)$data['total_students'] : 0,
            'active_learners' => isset($data['active_learners']) ? (int)$data['active_learners'] : 0,
            'completion_rate' => isset($data['completion_rate']) ? (float)$data['completion_rate'] : 0,
            'course_completions' => isset($data['course_completions']) ? (int)$data['course_completions'] : 0
        );
    }
    
    /**
     * Get chart data
     */
    private function get_chart_data($data) {
        $chart_data = array();
        
        // Top groups by enrollment chart
        if (isset($data['top_groups_by_enrollment']) && !empty($data['top_groups_by_enrollment'])) {
            $chart_data['enrollment_chart'] = array(
                'type' => 'bar',
                'labels' => array_column($data['top_groups_by_enrollment'], 'title'),
                'datasets' => array(
                    array(
                        'label' => __('Enrollment', 'bbld-analytics'),
                        'data' => array_column($data['top_groups_by_enrollment'], 'enrollment'),
                        'backgroundColor' => BBLD_Analytics_Utils::generate_chart_colors(count($data['top_groups_by_enrollment']))
                    )
                )
            );
        }
        
        // Engagement distribution pie chart
        if (isset($data['top_groups_by_engagement']) && !empty($data['top_groups_by_engagement'])) {
            $chart_data['engagement_chart'] = array(
                'type' => 'doughnut',
                'labels' => array_column($data['top_groups_by_engagement'], 'title'),
                'datasets' => array(
                    array(
                        'label' => __('Engagement Score', 'bbld-analytics'),
                        'data' => array_column($data['top_groups_by_engagement'], 'engagement'),
                        'backgroundColor' => BBLD_Analytics_Utils::generate_chart_colors(count($data['top_groups_by_engagement']))
                    )
                )
            );
        }
        
        // Trends line chart
        if (isset($data['trends']) && !empty($data['trends'])) {
            $chart_data['trends_chart'] = $this->prepare_trends_chart($data['trends']);
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
            'total_students' => array(
                'label' => __('Total Students', 'bbld-analytics'),
                'color' => '#2271b1'
            ),
            'active_learners' => array(
                'label' => __('Active Learners', 'bbld-analytics'),
                'color' => '#72aee6'
            ),
            'group_course_completions' => array(
                'label' => __('Course Completions', 'bbld-analytics'),
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
     * Get table data
     */
    private function get_table_data($data) {
        $table_data = array();
        
        // Top groups performance table
        $top_groups_count = $this->get_config_value('top_groups_count', 5);
        
        if (isset($data['top_groups_by_enrollment'])) {
            $table_data['top_groups'] = array_slice($data['top_groups_by_enrollment'], 0, $top_groups_count);
        }
        
        return $table_data;
    }
    
    /**
     * Get trend data for summary cards
     */
    private function get_trend_data($data) {
        // This would typically compare with previous period data
        // For now, return placeholder trend data
        return array(
            'total_groups' => array('direction' => 'neutral', 'percentage' => 0),
            'total_students' => array('direction' => 'up', 'percentage' => 5.2),
            'active_learners' => array('direction' => 'up', 'percentage' => 12.8),
            'completion_rate' => array('direction' => 'down', 'percentage' => 2.1)
        );
    }
    
    /**
     * Render widget content
     */
    public function render() {
        try {
            $period = $this->get_config_value('period', '30d');
            $data = $this->get_data($period);
            
            $this->render_widget_header();
            $this->render_summary_cards($data['summary'], $data['trends']);
            $this->render_tabs($data);
            
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
    private function render_summary_cards($summary, $trends) {
        ?>
        <div class="summary-cards-grid">
            <?php
            $this->render_metric_card(
                __('Total Groups', 'bbld-analytics'),
                $summary['total_groups'],
                isset($trends['total_groups']) ? $trends['total_groups'] : null
            );
            
            $this->render_metric_card(
                __('Total Students', 'bbld-analytics'),
                $summary['total_students'],
                isset($trends['total_students']) ? $trends['total_students'] : null
            );
            
            $this->render_metric_card(
                __('Active Learners', 'bbld-analytics'),
                $summary['active_learners'],
                isset($trends['active_learners']) ? $trends['active_learners'] : null
            );
            
            $this->render_metric_card(
                __('Completion Rate', 'bbld-analytics'),
                $summary['completion_rate'],
                isset($trends['completion_rate']) ? $trends['completion_rate'] : null,
                'percentage'
            );
            ?>
        </div>
        <?php
    }
    
    /**
     * Render tabs section
     */
    private function render_tabs($data) {
        ?>
        <div class="widget-tabs">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="enrollment">
                    <?php _e('By Enrollment', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="engagement">
                    <?php _e('By Engagement', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="completions">
                    <?php _e('By Completions', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="trends">
                    <?php _e('Trends', 'bbld-analytics'); ?>
                </button>
            </div>
            
            <div class="tab-content">
                <div class="tab-panel active" id="tab-enrollment">
                    <?php $this->render_enrollment_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-engagement">
                    <?php $this->render_engagement_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-completions">
                    <?php $this->render_completions_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-trends">
                    <?php $this->render_trends_tab($data); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render enrollment tab
     */
    private function render_enrollment_tab($data) {
        if (isset($data['charts']['enrollment_chart'])) {
            ?>
            <div class="chart-container">
                <canvas id="enrollment-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('enrollment-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['enrollment_chart']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No enrollment data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render engagement tab
     */
    private function render_engagement_tab($data) {
        if (isset($data['charts']['engagement_chart'])) {
            ?>
            <div class="chart-container">
                <canvas id="engagement-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('engagement-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['engagement_chart']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No engagement data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render completions tab
     */
    private function render_completions_tab($data) {
        if (isset($data['tables']['top_groups']) && !empty($data['tables']['top_groups'])) {
            ?>
            <div class="groups-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Group', 'bbld-analytics'); ?></th>
                            <th><?php _e('Enrollment', 'bbld-analytics'); ?></th>
                            <th><?php _e('Completions', 'bbld-analytics'); ?></th>
                            <th><?php _e('Engagement', 'bbld-analytics'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['tables']['top_groups'] as $group): ?>
                            <tr>
                                <td><strong><?php echo esc_html($group['title']); ?></strong></td>
                                <td><?php echo esc_html(BBLD_Analytics_Utils::format_number($group['enrollment'])); ?></td>
                                <td><?php echo esc_html(BBLD_Analytics_Utils::format_number($group['completions'])); ?></td>
                                <td><?php echo esc_html(BBLD_Analytics_Utils::format_percentage($group['engagement'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            $this->render_empty(__('No groups data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render trends tab
     */
    private function render_trends_tab($data) {
        if (isset($data['charts']['trends_chart'])) {
            ?>
            <div class="chart-container">
                <canvas id="trends-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('trends-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['trends_chart']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No trends data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Enqueue widget-specific scripts
     */
    public function enqueue_scripts() {
        wp_add_inline_script('bbld-analytics-admin', '
            // LearnDash widget specific JavaScript
            jQuery(document).ready(function($) {
                $(".tab-button").on("click", function() {
                    var tab = $(this).data("tab");
                    var widget = $(this).closest(".bbld-analytics-widget");
                    
                    // Update active states
                    widget.find(".tab-button").removeClass("active");
                    widget.find(".tab-panel").removeClass("active");
                    
                    $(this).addClass("active");
                    widget.find("#tab-" + tab).addClass("active");
                });
                
                $(".period-selector").on("change", function() {
                    var widget_id = $(this).data("widget");
                    var period = $(this).val();
                    
                    // Trigger widget refresh with new period
                    bbldAnalytics.refreshWidget(widget_id, period);
                });
            });
        ');
    }
    
    /**
     * Enqueue widget-specific styles
     */
    public function enqueue_styles() {
        wp_add_inline_style('bbld-analytics-admin', '
            .summary-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .widget-tabs .tab-navigation {
                border-bottom: 1px solid #ddd;
                margin-bottom: 20px;
            }
            
            .widget-tabs .tab-button {
                background: none;
                border: none;
                padding: 10px 20px;
                cursor: pointer;
                border-bottom: 2px solid transparent;
            }
            
            .widget-tabs .tab-button.active {
                border-bottom-color: #2271b1;
                color: #2271b1;
            }
            
            .widget-tabs .tab-panel {
                display: none;
            }
            
            .widget-tabs .tab-panel.active {
                display: block;
            }
            
            .chart-container {
                position: relative;
                height: 300px;
                margin: 20px 0;
            }
            
            .groups-table {
                overflow-x: auto;
            }
        ');
    }
}