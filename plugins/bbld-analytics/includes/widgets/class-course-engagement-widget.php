<?php
/**
 * Course Engagement Analytics Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Course_Engagement_Widget extends BBLD_Analytics_Abstract_Widget {
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->widget_id = 'course_engagement';
        $this->title = __('Course Engagement Analytics', 'bbld-analytics');
        $this->description = __('Detailed engagement metrics and performance analysis for shared courses.', 'bbld-analytics');
    }
    
    /**
     * Setup data source
     */
    protected function setup_data_source() {
        $this->data_source = bbld_analytics()->data_collector->get_data_source('learndash');
    }
    
    /**
     * Get default configuration
     */
    protected function get_default_config() {
        return array(
            'chart_type' => 'line',
            'period' => '30d',
            'show_trends' => true,
            'show_course_details' => true,
            'show_completion_times' => true
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
        
        // Get shared courses data
        $shared_courses_data = $this->get_shared_courses_data();
        
        // Process data for widget display
        $widget_data = array(
            'summary' => $this->get_summary_data($data),
            'courses' => $shared_courses_data,
            'charts' => $this->get_chart_data($data, $shared_courses_data),
            'insights' => $this->get_course_insights($shared_courses_data)
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
            'total_courses' => isset($data['total_courses']) ? (int)$data['total_courses'] : 0,
            'course_completions' => isset($data['course_completions']) ? (int)$data['course_completions'] : 0,
            'lesson_completions' => isset($data['lesson_completions']) ? (int)$data['lesson_completions'] : 0,
            'quiz_attempts' => isset($data['quiz_attempts']) ? (int)$data['quiz_attempts'] : 0,
            'avg_completion_time' => isset($data['avg_completion_time']) ? (float)$data['avg_completion_time'] : 0
        );
    }
    
    /**
     * Get shared courses data
     */
    private function get_shared_courses_data() {
        $shared_courses = bbld_analytics()->get_option('shared_courses', array());
        $courses_data = array();
        
        if (empty($shared_courses)) {
            return $courses_data;
        }
        
        // Get LearnDash groups data source for course performance
        $groups_data_source = bbld_analytics()->data_collector->get_data_source('learndash_groups');
        
        foreach ($shared_courses as $course_id) {
            $course = get_post($course_id);
            
            if (!$course) {
                continue;
            }
            
            // Get course performance data
            $course_data = $this->get_course_performance_data($course_id);
            
            $courses_data[] = array(
                'course_id' => $course_id,
                'title' => $course->post_title,
                'enrolled' => $course_data['enrolled'],
                'completed' => $course_data['completed'],
                'completion_rate' => $course_data['completion_rate'],
                'avg_progress' => $course_data['avg_progress'],
                'recent_activity' => $course_data['recent_activity']
            );
        }
        
        return $courses_data;
    }
    
    /**
     * Get course performance data
     */
    private function get_course_performance_data($course_id) {
        global $wpdb;
        
        // Get enrolled users
        $enrolled_users = $this->get_course_enrolled_users($course_id);
        $enrolled_count = count($enrolled_users);
        
        // Get completed users
        $completed_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            "course_completed_{$course_id}"
        ));
        $completed_count = count($completed_users);
        
        // Calculate completion rate
        $completion_rate = $enrolled_count > 0 ? ($completed_count / $enrolled_count) * 100 : 0;
        
        // Get average progress
        $avg_progress = $this->get_course_average_progress($course_id, $enrolled_users);
        
        // Get recent activity (last 7 days)
        $recent_activity = $this->get_course_recent_activity($course_id);
        
        return array(
            'enrolled' => $enrolled_count,
            'completed' => $completed_count,
            'completion_rate' => round($completion_rate, 2),
            'avg_progress' => round($avg_progress, 2),
            'recent_activity' => $recent_activity
        );
    }
    
    /**
     * Get course enrolled users
     */
    private function get_course_enrolled_users($course_id) {
        if (function_exists('learndash_get_users_for_course')) {
            return learndash_get_users_for_course($course_id, array(), false);
        }
        
        global $wpdb;
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            "course_{$course_id}_access_from"
        ));
        
        return array_map('intval', $user_ids);
    }
    
    /**
     * Get course average progress
     */
    private function get_course_average_progress($course_id, $enrolled_users) {
        if (empty($enrolled_users)) {
            return 0;
        }
        
        $total_progress = 0;
        $users_with_progress = 0;
        
        foreach ($enrolled_users as $user_id) {
            if (function_exists('learndash_course_progress')) {
                $progress = learndash_course_progress($user_id, $course_id);
                if (isset($progress['percentage'])) {
                    $total_progress += $progress['percentage'];
                    $users_with_progress++;
                }
            }
        }
        
        return $users_with_progress > 0 ? $total_progress / $users_with_progress : 0;
    }
    
    /**
     * Get course recent activity
     */
    private function get_course_recent_activity($course_id) {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        // Check if activity table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            return 0;
        }
        
        $since_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $activity_table 
             WHERE activity_type = 'learndash' 
             AND object_id = %d 
             AND recorded_at >= %s",
            $course_id,
            $since_date
        ));
    }
    
    /**
     * Get chart data
     */
    private function get_chart_data($data, $courses_data) {
        $chart_data = array();
        
        // Course completion trends
        if (isset($data['trends']) && !empty($data['trends'])) {
            $chart_data['completion_trends'] = array(
                'type' => $this->get_config_value('chart_type', 'line'),
                'labels' => $this->get_trend_labels($data['trends']),
                'datasets' => array(
                    array(
                        'label' => __('Course Completions', 'bbld-analytics'),
                        'data' => $this->get_trend_values($data['trends'], 'course_completions'),
                        'borderColor' => '#2271b1',
                        'backgroundColor' => '#2271b120',
                        'fill' => true,
                        'tension' => 0.4
                    ),
                    array(
                        'label' => __('Lesson Completions', 'bbld-analytics'),
                        'data' => $this->get_trend_values($data['trends'], 'lesson_completions'),
                        'borderColor' => '#72aee6',
                        'backgroundColor' => '#72aee620',
                        'fill' => false,
                        'tension' => 0.4
                    )
                )
            );
        }
        
        // Course performance comparison
        if (!empty($courses_data)) {
            $chart_data['course_comparison'] = array(
                'type' => 'bar',
                'labels' => array_column($courses_data, 'title'),
                'datasets' => array(
                    array(
                        'label' => __('Completion Rate (%)', 'bbld-analytics'),
                        'data' => array_column($courses_data, 'completion_rate'),
                        'backgroundColor' => BBLD_Analytics_Utils::generate_chart_colors(count($courses_data))
                    )
                )
            );
        }
        
        return $chart_data;
    }
    
    /**
     * Get trend labels
     */
    private function get_trend_labels($trends) {
        if (empty($trends)) {
            return array();
        }
        
        $first_trend = reset($trends);
        return array_column($first_trend, 'date');
    }
    
    /**
     * Get trend values
     */
    private function get_trend_values($trends, $metric_key) {
        if (!isset($trends[$metric_key])) {
            return array();
        }
        
        return array_column($trends[$metric_key], 'value');
    }
    
    /**
     * Get course insights
     */
    private function get_course_insights($courses_data) {
        $insights = array();
        
        if (empty($courses_data)) {
            $insights[] = array(
                'type' => 'warning',
                'message' => __('No shared courses configured. Please set up shared courses in settings.', 'bbld-analytics')
            );
            return $insights;
        }
        
        // Find best and worst performing courses
        $completion_rates = array_column($courses_data, 'completion_rate');
        $max_rate = max($completion_rates);
        $min_rate = min($completion_rates);
        
        if ($max_rate > 80) {
            $best_course = $courses_data[array_search($max_rate, $completion_rates)];
            $insights[] = array(
                'type' => 'positive',
                'message' => sprintf(
                    __('"%s" has excellent completion rate of %s%%', 'bbld-analytics'),
                    $best_course['title'],
                    number_format($max_rate, 1)
                )
            );
        }
        
        if ($min_rate < 30 && count($courses_data) > 1) {
            $worst_course = $courses_data[array_search($min_rate, $completion_rates)];
            $insights[] = array(
                'type' => 'warning',
                'message' => sprintf(
                    __('"%s" has low completion rate of %s%% - consider course improvements', 'bbld-analytics'),
                    $worst_course['title'],
                    number_format($min_rate, 1)
                )
            );
        }
        
        // Overall engagement insight
        $avg_completion = array_sum($completion_rates) / count($completion_rates);
        if ($avg_completion > 60) {
            $insights[] = array(
                'type' => 'positive',
                'message' => sprintf(
                    __('Overall course completion rate is healthy at %s%%', 'bbld-analytics'),
                    number_format($avg_completion, 1)
                )
            );
        } elseif ($avg_completion < 40) {
            $insights[] = array(
                'type' => 'info',
                'message' => sprintf(
                    __('Average completion rate is %s%% - consider engagement strategies', 'bbld-analytics'),
                    number_format($avg_completion, 1)
                )
            );
        }
        
        return $insights;
    }
    
    /**
     * Render widget content
     */
    public function render() {
        try {
            $period = $this->get_config_value('period', '30d');
            $data = $this->get_data($period);
            
            $this->render_widget_header();
            
            if (empty($data['courses'])) {
                $this->render_empty(__('No shared courses configured. Please configure shared courses in settings to see engagement analytics.', 'bbld-analytics'));
                return;
            }
            
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
            <?php $this->render_metric_card(__('Course Completions', 'bbld-analytics'), $summary['course_completions']); ?>
            <?php $this->render_metric_card(__('Lesson Completions', 'bbld-analytics'), $summary['lesson_completions']); ?>
            <?php $this->render_metric_card(__('Quiz Attempts', 'bbld-analytics'), $summary['quiz_attempts']); ?>
            
            <?php if ($this->get_config_value('show_completion_times', true)): ?>
                <?php $this->render_metric_card(__('Avg. Completion Time', 'bbld-analytics'), $summary['avg_completion_time'] . ' ' . __('days', 'bbld-analytics')); ?>
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
                <?php if ($this->get_config_value('show_course_details', true)): ?>
                <button class="tab-button active" data-tab="courses">
                    <?php _e('Course Performance', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                
                <?php if ($this->get_config_value('show_trends', true)): ?>
                <button class="tab-button" data-tab="trends">
                    <?php _e('Completion Trends', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                
                <button class="tab-button" data-tab="comparison">
                    <?php _e('Course Comparison', 'bbld-analytics'); ?>
                </button>
                <button class="tab-button" data-tab="insights">
                    <?php _e('Insights', 'bbld-analytics'); ?>
                </button>
            </div>
            
            <div class="tab-content">
                <?php if ($this->get_config_value('show_course_details', true)): ?>
                <div class="tab-panel active" id="tab-courses">
                    <?php $this->render_courses_tab($data['courses']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($this->get_config_value('show_trends', true)): ?>
                <div class="tab-panel" id="tab-trends">
                    <?php $this->render_trends_tab($data); ?>
                </div>
                <?php endif; ?>
                
                <div class="tab-panel" id="tab-comparison">
                    <?php $this->render_comparison_tab($data); ?>
                </div>
                
                <div class="tab-panel" id="tab-insights">
                    <?php $this->render_insights_tab($data['insights']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render courses tab
     */
    private function render_courses_tab($courses) {
        ?>
        <div class="courses-performance-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Course', 'bbld-analytics'); ?></th>
                        <th><?php _e('Enrolled', 'bbld-analytics'); ?></th>
                        <th><?php _e('Completed', 'bbld-analytics'); ?></th>
                        <th><?php _e('Completion Rate', 'bbld-analytics'); ?></th>
                        <th><?php _e('Avg. Progress', 'bbld-analytics'); ?></th>
                        <th><?php _e('Recent Activity', 'bbld-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($course['title']); ?></strong>
                        </td>
                        <td><?php echo esc_html(BBLD_Analytics_Utils::format_number($course['enrolled'])); ?></td>
                        <td><?php echo esc_html(BBLD_Analytics_Utils::format_number($course['completed'])); ?></td>
                        <td>
                            <span class="completion-rate completion-rate-<?php echo $this->get_completion_rate_class($course['completion_rate']); ?>">
                                <?php echo esc_html(BBLD_Analytics_Utils::format_percentage($course['completion_rate'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(BBLD_Analytics_Utils::format_percentage($course['avg_progress'])); ?></td>
                        <td><?php echo esc_html(BBLD_Analytics_Utils::format_number($course['recent_activity'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get completion rate CSS class
     */
    private function get_completion_rate_class($rate) {
        if ($rate >= 80) {
            return 'excellent';
        } elseif ($rate >= 60) {
            return 'good';
        } elseif ($rate >= 40) {
            return 'average';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Render trends tab
     */
    private function render_trends_tab($data) {
        if (isset($data['charts']['completion_trends'])) {
            ?>
            <div class="chart-container">
                <canvas id="completion-trends-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('completion-trends-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['completion_trends']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No trends data available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Render comparison tab
     */
    private function render_comparison_tab($data) {
        if (isset($data['charts']['course_comparison'])) {
            ?>
            <div class="chart-container">
                <canvas id="course-comparison-chart-<?php echo esc_attr($this->widget_id); ?>"></canvas>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('course-comparison-chart-<?php echo esc_js($this->widget_id); ?>').getContext('2d');
                new Chart(ctx, <?php echo json_encode($data['charts']['course_comparison']); ?>);
            });
            </script>
            <?php
        } else {
            $this->render_empty(__('No comparison data available.', 'bbld-analytics'));
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
            $this->render_empty(__('No insights available.', 'bbld-analytics'));
        }
    }
    
    /**
     * Get insight icon
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
            .completion-rate {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            
            .completion-rate-excellent {
                background: #d4edda;
                color: #155724;
            }
            
            .completion-rate-good {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .completion-rate-average {
                background: #fff3cd;
                color: #856404;
            }
            
            .completion-rate-poor {
                background: #f8d7da;
                color: #721c24;
            }
            
            .courses-performance-table {
                overflow-x: auto;
            }
            
            .courses-performance-table table {
                margin-top: 0;
            }
            
            .courses-performance-table th,
            .courses-performance-table td {
                padding: 12px 8px;
                text-align: left;
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
        ');
    }
}