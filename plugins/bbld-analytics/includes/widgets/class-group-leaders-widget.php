<?php
/**
 * Group Leaders Dashboard Widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Group_Leaders_Widget extends BBLD_Analytics_Abstract_Widget {
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->widget_id = 'group_leaders';
        $this->title = __('Group Leaders Dashboard', 'bbld-analytics');
        $this->description = __('Track group leader performance, assignments, and groups requiring attention.', 'bbld-analytics');
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
            'show_performance' => true,
            'show_unassigned' => true,
            'ranking_count' => 10,
            'show_alerts' => true,
            'performance_threshold' => 70
        );
    }
    
    /**
     * Get widget data
     */
    public function get_data($period = '30d') {
        if (!$this->data_source || !$this->data_source->is_available()) {
            throw new Exception(__('LearnDash Groups data source not available', 'bbld-analytics'));
        }
        
        // Get cached data first
        $cache_key = $this->get_cache_key($period);
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        // Get fresh group leaders data
        $leaders_data = $this->get_group_leaders_data();
        
        // Process data for widget display
        $widget_data = array(
            'summary' => $this->get_summary_data($leaders_data),
            'leaders' => $leaders_data['leaders_performance'],
            'unassigned_groups' => $leaders_data['groups_without_leaders'],
            'alerts' => $this->get_leader_alerts($leaders_data),
            'insights' => $this->get_leader_insights($leaders_data)
        );
        
        // Cache the processed data
        $this->set_cached_data($cache_key, $widget_data, 600); // 10 minutes
        
        return $widget_data;
    }
    
    /**
     * Get group leaders data
     */
    private function get_group_leaders_data() {
        if (!function_exists('learndash_get_groups')) {
            return array(
                'total_leaders' => 0,
                'total_groups' => 0,
                'groups_without_leaders' => array(),
                'leaders_performance' => array()
            );
        }
        
        $groups = learndash_get_groups(true);
        $leaders_data = array();
        $groups_without_leaders = array();
        
        foreach ($groups as $group) {
            $group_id = $group->ID;
            $leader_id = learndash_get_group_leader_id($group_id);
            $group_users = learndash_get_groups_users($group_id);
            
            // Get group performance metrics
            $engagement_metric = bbld_analytics()->database->get_metric('learndash_groups', "group_{$group_id}_engagement_score");
            $completions_metric = bbld_analytics()->database->get_metric('learndash_groups', "group_{$group_id}_course_completions");
            $active_users_metric = bbld_analytics()->database->get_metric('learndash_groups', "group_{$group_id}_active_users");
            
            $engagement_score = $engagement_metric ? (float)$engagement_metric->metric_value : 0;
            $completions = $completions_metric ? (int)$completions_metric->metric_value : 0;
            $active_users = $active_users_metric ? (int)$active_users_metric->metric_value : 0;
            
            if ($leader_id) {
                $leader = get_user_by('ID', $leader_id);
                $leader_key = $leader_id;
                
                if (!isset($leaders_data[$leader_key])) {
                    $leaders_data[$leader_key] = array(
                        'leader_id' => $leader_id,
                        'leader_name' => $leader ? $leader->display_name : __('Unknown', 'bbld-analytics'),
                        'leader_email' => $leader ? $leader->user_email : '',
                        'groups_count' => 0,
                        'total_students' => 0,
                        'total_completions' => 0,
                        'avg_engagement' => 0,
                        'total_active_users' => 0,
                        'groups' => array(),
                        'performance_score' => 0
                    );
                }
                
                $leaders_data[$leader_key]['groups_count']++;
                $leaders_data[$leader_key]['total_students'] += count($group_users);
                $leaders_data[$leader_key]['total_completions'] += $completions;
                $leaders_data[$leader_key]['total_active_users'] += $active_users;
                
                $leaders_data[$leader_key]['groups'][] = array(
                    'id' => $group_id,
                    'title' => $group->post_title,
                    'students' => count($group_users),
                    'engagement_score' => $engagement_score,
                    'completions' => $completions,
                    'active_users' => $active_users
                );
            } else {
                $groups_without_leaders[] = array(
                    'id' => $group_id,
                    'title' => $group->post_title,
                    'students' => count($group_users),
                    'engagement_score' => $engagement_score,
                    'completions' => $completions,
                    'created_date' => $group->post_date
                );
            }
        }
        
        // Calculate performance scores and averages
        foreach ($leaders_data as &$leader) {
            if ($leader['groups_count'] > 0) {
                $leader['avg_engagement'] = $this->calculate_leader_avg_engagement($leader['groups']);
                $leader['performance_score'] = $this->calculate_leader_performance_score($leader);
            }
        }
        
        return array(
            'total_leaders' => count($leaders_data),
            'total_groups' => count($groups),
            'groups_without_leaders' => $groups_without_leaders,
            'leaders_performance' => array_values($leaders_data)
        );
    }
    
    /**
     * Calculate leader average engagement
     */
    private function calculate_leader_avg_engagement($groups) {
        if (empty($groups)) {
            return 0;
        }
        
        $total_engagement = array_sum(array_column($groups, 'engagement_score'));
        return $total_engagement / count($groups);
    }
    
    /**
     * Calculate leader performance score
     */
    private function calculate_leader_performance_score($leader) {
        // Performance score based on:
        // - Average engagement (40%)
        // - Completions per student (30%)
        // - Active users ratio (30%)
        
        $engagement_score = $leader['avg_engagement'] * 0.4;
        
        $completions_per_student = $leader['total_students'] > 0 ? 
            ($leader['total_completions'] / $leader['total_students']) * 20 : 0; // Scale to 100
        $completions_score = min($completions_per_student, 30) * 0.3; // Max 30 points
        
        $active_ratio = $leader['total_students'] > 0 ? 
            ($leader['total_active_users'] / $leader['total_students']) * 100 : 0;
        $active_score = $active_ratio * 0.3;
        
        return round($engagement_score + $completions_score + $active_score, 1);
    }
    
    /**
     * Get summary data
     */
    private function get_summary_data($leaders_data) {
        $active_leaders = array_filter($leaders_data['leaders_performance'], function($leader) {
            return $leader['groups_count'] > 0;
        });
        
        return array(
            'total_leaders' => $leaders_data['total_leaders'],
            'active_leaders' => count($active_leaders),
            'total_groups' => $leaders_data['total_groups'],
            'unassigned_groups' => count($leaders_data['groups_without_leaders']),
            'avg_performance' => $this->calculate_avg_performance($active_leaders),
            'high_performers' => $this->count_high_performers($active_leaders)
        );
    }
    
    /**
     * Calculate average performance
     */
    private function calculate_avg_performance($leaders) {
        if (empty($leaders)) {
            return 0;
        }
        
        $total_performance = array_sum(array_column($leaders, 'performance_score'));
        return round($total_performance / count($leaders), 1);
    }
    
    /**
     * Count high performers
     */
    private function count_high_performers($leaders) {
        $threshold = $this->get_config_value('performance_threshold', 70);
        
        return count(array_filter($leaders, function($leader) use ($threshold) {
            return $leader['performance_score'] >= $threshold;
        }));
    }
    
    /**
     * Get leader alerts
     */
    private function get_leader_alerts($leaders_data) {
        $alerts = array();
        
        // Alert for unassigned groups
        $unassigned_count = count($leaders_data['groups_without_leaders']);
        if ($unassigned_count > 0) {
            $alerts[] = array(
                'type' => 'warning',
                'title' => __('Unassigned Groups', 'bbld-analytics'),
                'message' => sprintf(
                    _n('%d group needs a leader assignment', '%d groups need leader assignments', $unassigned_count, 'bbld-analytics'),
                    $unassigned_count
                ),
                'action' => 'assign_leaders',
                'count' => $unassigned_count
            );
        }
        
        // Alert for low-performing leaders
        $threshold = $this->get_config_value('performance_threshold', 70);
        $low_performers = array_filter($leaders_data['leaders_performance'], function($leader) use ($threshold) {
            return $leader['performance_score'] < ($threshold - 20); // 20 points below threshold
        });
        
        if (!empty($low_performers)) {
            $alerts[] = array(
                'type' => 'info',
                'title' => __('Performance Support Needed', 'bbld-analytics'),
                'message' => sprintf(
                    _n('%d leader may need performance support', '%d leaders may need performance support', count($low_performers), 'bbld-analytics'),
                    count($low_performers)
                ),
                'action' => 'support_leaders',
                'count' => count($low_performers)
            );
        }
        
        // Alert for inactive groups
        $inactive_groups = array();
        foreach ($leaders_data['leaders_performance'] as $leader) {
            foreach ($leader['groups'] as $group) {
                if ($group['engagement_score'] < 20) {
                    $inactive_groups[] = $group;
                }
            }
        }
        
        if (!empty($inactive_groups)) {
            $alerts[] = array(
                'type' => 'warning',
                'title' => __('Inactive Groups', 'bbld-analytics'),
                'message' => sprintf(
                    _n('%d group has very low engagement', '%d groups have very low engagement', count($inactive_groups), 'bbld-analytics'),
                    count($inactive_groups)
                ),
                'action' => 'boost_engagement',
                'count' => count($inactive_groups)
            );
        }
        
        return $alerts;
    }
    
    /**
     * Get leader insights
     */
    private function get_leader_insights($leaders_data) {
        $insights = array();
        
        if (empty($leaders_data['leaders_performance'])) {
            $insights[] = array(
                'type' => 'info',
                'message' => __('No group leaders found. Consider assigning leaders to improve group management.', 'bbld-analytics')
            );
            return $insights;
        }
        
        // Best performing leader insight
        $best_leader = $this->get_best_performing_leader($leaders_data['leaders_performance']);
        if ($best_leader && $best_leader['performance_score'] > 80) {
            $insights[] = array(
                'type' => 'positive',
                'message' => sprintf(
                    __('%s is your top performing leader with a score of %s points', 'bbld-analytics'),
                    $best_leader['leader_name'],
                    $best_leader['performance_score']
                )
            );
        }
        
        // Coverage insight
        $coverage_rate = $leaders_data['total_groups'] > 0 ? 
            (($leaders_data['total_groups'] - count($leaders_data['groups_without_leaders'])) / $leaders_data['total_groups']) * 100 : 0;
        
        if ($coverage_rate >= 90) {
            $insights[] = array(
                'type' => 'positive',
                'message' => sprintf(
                    __('Excellent leader coverage at %s%% of all groups', 'bbld-analytics'),
                    number_format($coverage_rate, 1)
                )
            );
        } elseif ($coverage_rate < 70) {
            $insights[] = array(
                'type' => 'warning',
                'message' => sprintf(
                    __('Leader coverage is only %s%% - consider recruiting more leaders', 'bbld-analytics'),
                    number_format($coverage_rate, 1)
                )
            );
        }
        
        return $insights;
    }
    
    /**
     * Get best performing leader
     */
    private function get_best_performing_leader($leaders) {
        if (empty($leaders)) {
            return null;
        }
        
        usort($leaders, function($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });
        
        return $leaders[0];
    }
    
    /**
     * Render widget content
     */
    public function render() {
        try {
            $data = $this->get_data();
            
            $this->render_summary_cards($data['summary']);
            $this->render_alerts($data['alerts']);
            $this->render_content_tabs($data);
            
        } catch (Exception $e) {
            $this->render_error($e->getMessage());
        }
    }
    
    /**
     * Render summary cards
     */
    private function render_summary_cards($summary) {
        ?>
        <div class="summary-cards-grid">
            <?php $this->render_metric_card(__('Total Leaders', 'bbld-analytics'), $summary['total_leaders']); ?>
            <?php $this->render_metric_card(__('Active Leaders', 'bbld-analytics'), $summary['active_leaders']); ?>
            <?php $this->render_metric_card(__('Unassigned Groups', 'bbld-analytics'), $summary['unassigned_groups']); ?>
            <?php $this->render_metric_card(__('High Performers', 'bbld-analytics'), $summary['high_performers']); ?>
        </div>
        <?php
    }
    
    /**
     * Render alerts section
     */
    private function render_alerts($alerts) {
        if (empty($alerts)) {
            return;
        }
        ?>
        <div class="leader-alerts">
            <h4><?php _e('Attention Required', 'bbld-analytics'); ?></h4>
            <div class="alerts-grid">
                <?php foreach ($alerts as $alert): ?>
                <div class="alert-item alert-<?php echo esc_attr($alert['type']); ?>">
                    <div class="alert-header">
                        <span class="alert-title"><?php echo esc_html($alert['title']); ?></span>
                        <span class="alert-count"><?php echo esc_html($alert['count']); ?></span>
                    </div>
                    <div class="alert-message"><?php echo esc_html($alert['message']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
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
                <?php if ($this->get_config_value('show_unassigned', true)): ?>
                <button class="tab-button active" data-tab="unassigned">
                    <?php _e('Unassigned Groups', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                
                <?php if ($this->get_config_value('show_performance', true)): ?>
                <button class="tab-button" data-tab="performance">
                    <?php _e('Leader Performance', 'bbld-analytics'); ?>
                </button>
                <?php endif; ?>
                
                <button class="tab-button" data-tab="insights">
                    <?php _e('Insights', 'bbld-analytics'); ?>
                </button>
            </div>
            
            <div class="tab-content">
                <?php if ($this->get_config_value('show_unassigned', true)): ?>
                <div class="tab-panel active" id="tab-unassigned">
                    <?php $this->render_unassigned_tab($data['unassigned_groups']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($this->get_config_value('show_performance', true)): ?>
                <div class="tab-panel" id="tab-performance">
                    <?php $this->render_performance_tab($data['leaders']); ?>
                </div>
                <?php endif; ?>
                
                <div class="tab-panel" id="tab-insights">
                    <?php $this->render_insights_tab($data['insights']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render unassigned groups tab
     */
    private function render_unassigned_tab($unassigned_groups) {
        if (empty($unassigned_groups)) {
            ?>
            <div class="no-unassigned-groups">
                <div class="success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <p><?php _e('Great! All groups have assigned leaders.', 'bbld-analytics'); ?></p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="unassigned-groups-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Group', 'bbld-analytics'); ?></th>
                        <th><?php _e('Students', 'bbld-analytics'); ?></th>
                        <th><?php _e('Engagement Score', 'bbld-analytics'); ?></th>
                        <th><?php _e('Created', 'bbld-analytics'); ?></th>
                        <th><?php _e('Action', 'bbld-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unassigned_groups as $group): ?>
                    <tr>
                        <td><strong><?php echo esc_html($group['title']); ?></strong></td>
                        <td><?php echo esc_html($group['students']); ?></td>
                        <td>
                            <span class="engagement-score engagement-<?php echo $this->get_engagement_class($group['engagement_score']); ?>">
                                <?php echo esc_html(BBLD_Analytics_Utils::format_percentage($group['engagement_score'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('M j, Y', strtotime($group['created_date']))); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $group['id'] . '&action=edit'); ?>" 
                               class="button button-small button-primary">
                                <?php _e('Assign Leader', 'bbld-analytics'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render performance tab
     */
    private function render_performance_tab($leaders) {
        if (empty($leaders)) {
            $this->render_empty(__('No group leaders found.', 'bbld-analytics'));
            return;
        }
        
        // Sort by performance score
        usort($leaders, function($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });
        
        $limit = $this->get_config_value('ranking_count', 10);
        $top_leaders = array_slice($leaders, 0, $limit);
        ?>
        <div class="leaders-performance-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Rank', 'bbld-analytics'); ?></th>
                        <th><?php _e('Leader', 'bbld-analytics'); ?></th>
                        <th><?php _e('Groups', 'bbld-analytics'); ?></th>
                        <th><?php _e('Students', 'bbld-analytics'); ?></th>
                        <th><?php _e('Avg. Engagement', 'bbld-analytics'); ?></th>
                        <th><?php _e('Performance Score', 'bbld-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_leaders as $index => $leader): ?>
                    <tr>
                        <td>
                            <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                #<?php echo $index + 1; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($leader['leader_name']); ?></strong>
                            <div class="leader-email"><?php echo esc_html($leader['leader_email']); ?></div>
                        </td>
                        <td><?php echo esc_html($leader['groups_count']); ?></td>
                        <td><?php echo esc_html($leader['total_students']); ?></td>
                        <td>
                            <span class="engagement-score engagement-<?php echo $this->get_engagement_class($leader['avg_engagement']); ?>">
                                <?php echo esc_html(BBLD_Analytics_Utils::format_percentage($leader['avg_engagement'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="performance-score performance-<?php echo $this->get_performance_class($leader['performance_score']); ?>">
                                <?php echo esc_html($leader['performance_score']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
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
     * Get engagement class
     */
    private function get_engagement_class($score) {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }
    
    /**
     * Get performance class
     */
    private function get_performance_class($score) {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'average';
        return 'poor';
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
            .leader-alerts {
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .leader-alerts h4 {
                margin: 0 0 15px 0;
                color: #1d2327;
            }
            
            .alerts-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .alert-item {
                padding: 15px;
                border-radius: 4px;
                border-left: 4px solid;
            }
            
            .alert-warning {
                background: #fff3cd;
                border-left-color: #ffc107;
                color: #856404;
            }
            
            .alert-info {
                background: #d1ecf1;
                border-left-color: #17a2b8;
                color: #0c5460;
            }
            
            .alert-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 5px;
            }
            
            .alert-title {
                font-weight: 600;
            }
            
            .alert-count {
                background: rgba(0,0,0,0.1);
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .alert-message {
                font-size: 13px;
            }
            
            .no-unassigned-groups {
                text-align: center;
                padding: 40px;
                color: #00a32a;
            }
            
            .success-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }
            
            .engagement-score,
            .performance-score {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            
            .engagement-high,
            .performance-excellent {
                background: #d4edda;
                color: #155724;
            }
            
            .engagement-medium,
            .performance-good {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .engagement-low,
            .performance-average {
                background: #fff3cd;
                color: #856404;
            }
            
            .performance-poor {
                background: #f8d7da;
                color: #721c24;
            }
            
            .rank-badge {
                display: inline-block;
                width: 30px;
                height: 30px;
                line-height: 30px;
                text-align: center;
                border-radius: 50%;
                font-weight: 600;
                font-size: 12px;
            }
            
            .rank-1 {
                background: #ffd700;
                color: #856404;
            }
            
            .rank-2 {
                background: #c0c0c0;
                color: #495057;
            }
            
            .rank-3 {
                background: #cd7f32;
                color: #fff;
            }
            
            .rank-badge:not(.rank-1):not(.rank-2):not(.rank-3) {
                background: #e9ecef;
                color: #495057;
            }
            
            .leader-email {
                font-size: 12px;
                color: #646970;
                font-weight: normal;
            }
            
            .leaders-performance-table,
            .unassigned-groups-table {
                overflow-x: auto;
            }
            
            .leaders-performance-table table,
            .unassigned-groups-table table {
                margin-top: 0;
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