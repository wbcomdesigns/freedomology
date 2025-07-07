<?php
/**
 * LearnDash Groups Data Source
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_LearnDash_Groups_Data extends BBLD_Analytics_Abstract_Data_Source {
    
    /**
     * Initialize data source
     */
    protected function init() {
        $this->source_id = 'learndash_groups';
    }
    
    /**
     * Check if data source is available
     */
    public function is_available() {
        return class_exists('SFWD_LMS') && function_exists('learndash_get_groups');
    }
    
    /**
     * Collect metrics
     */
    public function collect_metrics() {
        if (!$this->is_available()) {
            return array();
        }
        
        $metrics = array();
        $today = current_time('Y-m-d');
        
        // Get all groups (CORRECTED)
        $groups = BBLD_Analytics_Utils::get_learndash_groups();
        $total_groups = count($groups);
        
        // Store total groups count
        $this->store_metric('total_groups', $total_groups);
        $metrics['total_groups'] = $total_groups;
        
        // Initialize aggregated counters
        $total_students = 0;
        $total_completions = 0;
        $total_active_users = 0;
        $completion_rates = array();
        
        // Process each group
        foreach ($groups as $group) {
            $group_id = $group->ID;
            $group_metrics = $this->collect_group_metrics($group_id);
            
            // Store individual group metrics
            foreach ($group_metrics as $key => $value) {
                $this->store_metric($key, $value);
                $metrics[$key] = $value;
            }
            
            // Aggregate data
            $total_students += $group_metrics["group_{$group_id}_enrollment_count"];
            $total_completions += $group_metrics["group_{$group_id}_course_completions"];
            $total_active_users += $group_metrics["group_{$group_id}_active_users"];
            
            if ($group_metrics["group_{$group_id}_engagement_score"] > 0) {
                $completion_rates[] = $group_metrics["group_{$group_id}_engagement_score"];
            }
        }
        
        // Store aggregated metrics
        $this->store_metric('total_students', $total_students);
        $this->store_metric('group_course_completions', $total_completions);
        $this->store_metric('active_learners', $total_active_users);
        
        // Calculate average completion rate
        $avg_completion_rate = !empty($completion_rates) ? array_sum($completion_rates) / count($completion_rates) : 0;
        $this->store_metric('group_completion_rate', $avg_completion_rate);
        
        $metrics['total_students'] = $total_students;
        $metrics['group_course_completions'] = $total_completions;
        $metrics['active_learners'] = $total_active_users;
        $metrics['group_completion_rate'] = $avg_completion_rate;
        
        // Collect top groups data
        $top_groups_data = $this->collect_top_groups_data($groups);
        foreach ($top_groups_data as $key => $value) {
            $this->store_metric($key, $value);
            $metrics[$key] = $value;
        }
        
        // Collect daily quiz attempts by group users
        $daily_quiz_attempts = $this->collect_daily_quiz_attempts();
        $this->store_metric('daily_group_quiz_attempts', $daily_quiz_attempts);
        $metrics['daily_group_quiz_attempts'] = $daily_quiz_attempts;
        
        return $metrics;
    }
    
    /**
     * Collect individual group metrics (Updated with safe functions)
     */
    private function collect_group_metrics($group_id) {
        $metrics = array();
        
        // Use safe functions
        $group_users = BBLD_Analytics_Utils::get_group_users($group_id);
        $enrollment_count = count($group_users);
        
        $metrics["group_{$group_id}_enrollment_count"] = $enrollment_count;
        
        if ($enrollment_count === 0) {
            // Return zero metrics for empty groups
            $metrics["group_{$group_id}_active_users"] = 0;
            $metrics["group_{$group_id}_course_completions"] = 0;
            $metrics["group_{$group_id}_engagement_score"] = 0;
            
            // Zero out course-specific completion rates
            $shared_courses = bbld_analytics()->get_option('shared_courses', array());
            foreach ($shared_courses as $course_id) {
                $metrics["group_{$group_id}_course_{$course_id}_completion_rate"] = 0;
            }
            
            return $metrics;
        }
        
        // Get active users (users with activity in last 7 days)
        $active_users = $this->get_group_active_users($group_id, 7);
        $metrics["group_{$group_id}_active_users"] = count($active_users);
        
        // Get course completions for this group
        $course_completions = $this->get_group_course_completions($group_id);
        $metrics["group_{$group_id}_course_completions"] = $course_completions;
        
        // Calculate engagement score (active users / total users * 100)
        $engagement_score = (count($active_users) / $enrollment_count) * 100;
        $metrics["group_{$group_id}_engagement_score"] = round($engagement_score, 2);
        
        // Get completion rates for shared courses
        $shared_courses = bbld_analytics()->get_option('shared_courses', array());
        foreach ($shared_courses as $course_id) {
            $completion_rate = $this->get_group_course_completion_rate($group_id, $course_id);
            $metrics["group_{$group_id}_course_{$course_id}_completion_rate"] = $completion_rate;
        }
        
        return $metrics;
    }
    
    /**
     * Get active users for a group (Updated with safe functions)
     */
    private function get_group_active_users($group_id, $days = 7) {
        global $wpdb;
        
        $group_users = BBLD_Analytics_Utils::get_group_users($group_id);
        
        if (empty($group_users)) {
            return array();
        }
        
        $user_ids = array_map('intval', $group_users);
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        // Check if activity table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            return array(); // Return empty if no activity tracking
        }
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $query = "
            SELECT DISTINCT user_id 
            FROM $activity_table 
            WHERE user_id IN ($placeholders) 
            AND recorded_at >= %s
        ";
        
        $params = array_merge($user_ids, array($since_date));
        $active_users = $wpdb->get_col($wpdb->prepare($query, $params));
        
        return array_map('intval', $active_users);
    }
    
    /**
     * Get course completions for a group (Updated with safe functions)
     */
    private function get_group_course_completions($group_id) {
        global $wpdb;
        
        $group_users = BBLD_Analytics_Utils::get_group_users($group_id);
        
        if (empty($group_users)) {
            return 0;
        }
        
        $user_ids = array_map('intval', $group_users);
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        // Check if activity table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            // Fallback: Check user meta for completions
            return $this->get_group_completions_fallback($user_ids);
        }
        
        $query = "
            SELECT COUNT(*) 
            FROM $activity_table 
            WHERE user_id IN ($placeholders) 
            AND activity_type = 'learndash' 
            AND activity_subtype = 'course_completion'
        ";
        
        return (int)$wpdb->get_var($wpdb->prepare($query, $user_ids));
    }
    
    /**
     * Fallback method for course completions
     */
    private function get_group_completions_fallback($user_ids) {
        global $wpdb;
        
        if (empty($user_ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE user_id IN ($placeholders) 
             AND meta_key LIKE 'course_completed_%'",
            $user_ids
        ));
    }
    
    /**
     * Get course completion rate for a group (CORRECTED)
     */
    private function get_group_course_completion_rate($group_id, $course_id) {
        $group_users = BBLD_Analytics_Utils::get_group_users($group_id);
        
        if (empty($group_users)) {
            return 0;
        }
        
        $total_users = count($group_users);
        $completed_users = 0;
        
        foreach ($group_users as $user_id) {
            if (BBLD_Analytics_Utils::is_course_completed($user_id, $course_id)) {
                $completed_users++;
            }
        }
        
        return $total_users > 0 ? ($completed_users / $total_users) * 100 : 0;
    }
    
    /**
     * Collect top groups data
     */
    private function collect_top_groups_data($groups) {
        $groups_data = array();
        
        // Collect data for each group
        foreach ($groups as $group) {
            $group_id = $group->ID;
            $enrollment_metric = $this->get_metric("group_{$group_id}_enrollment_count");
            $engagement_metric = $this->get_metric("group_{$group_id}_engagement_score");
            $completions_metric = $this->get_metric("group_{$group_id}_course_completions");
            
            $groups_data[] = array(
                'id' => $group_id,
                'title' => $group->post_title,
                'enrollment' => $enrollment_metric ? (int)$enrollment_metric->metric_value : 0,
                'engagement' => $engagement_metric ? (float)$engagement_metric->metric_value : 0,
                'completions' => $completions_metric ? (int)$completions_metric->metric_value : 0
            );
        }
        
        // Sort and get top 10 by enrollment
        usort($groups_data, function($a, $b) {
            return $b['enrollment'] - $a['enrollment'];
        });
        $top_by_enrollment = array_slice($groups_data, 0, 10);
        
        // Sort and get top 10 by engagement
        usort($groups_data, function($a, $b) {
            return $b['engagement'] <=> $a['engagement'];
        });
        $top_by_engagement = array_slice($groups_data, 0, 10);
        
        // Sort and get top 10 by completions
        usort($groups_data, function($a, $b) {
            return $b['completions'] - $a['completions'];
        });
        $top_by_completions = array_slice($groups_data, 0, 10);
        
        return array(
            'top_groups_by_enrollment' => $top_by_enrollment,
            'top_groups_by_engagement' => $top_by_engagement,
            'top_groups_by_completions' => $top_by_completions
        );
    }
    
    /**
     * Collect daily quiz attempts by group users
     */
    private function collect_daily_quiz_attempts() {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        $today = current_time('Y-m-d');
        
        // Get all group users (CORRECTED)
        $all_group_users = array();
        $groups = BBLD_Analytics_Utils::get_learndash_groups();
        
        foreach ($groups as $group) {
            $group_users = BBLD_Analytics_Utils::get_group_users($group->ID);
            $all_group_users = array_merge($all_group_users, $group_users);
        }
        
        $all_group_users = array_unique($all_group_users);
        
        if (empty($all_group_users)) {
            return 0;
        }
        
        $user_ids = array_map('intval', $all_group_users);
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        
        $query = "
            SELECT COUNT(*) 
            FROM $activity_table 
            WHERE user_id IN ($placeholders) 
            AND activity_type = 'learndash' 
            AND activity_subtype = 'quiz_completion'
            AND DATE(recorded_at) = %s
        ";
        
        $params = array_merge($user_ids, array($today));
        
        return (int)$wpdb->get_var($wpdb->prepare($query, $params));
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data($period = '30d') {
        if (!$this->is_available()) {
            return array();
        }
        
        $cache_key = $this->get_cache_key($period);
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        $dates = $this->get_period_dates($period);
        $data = array();
        
        // Get current metrics
        $data['total_groups'] = $this->get_current_metric_value('total_groups');
        $data['total_students'] = $this->get_current_metric_value('total_students');
        $data['active_learners'] = $this->get_current_metric_value('active_learners');
        $data['completion_rate'] = $this->get_current_metric_value('group_completion_rate');
        $data['course_completions'] = $this->get_current_metric_value('group_course_completions');
        $data['daily_quiz_attempts'] = $this->get_current_metric_value('daily_group_quiz_attempts');
        
        // Get top groups
        $data['top_groups_by_enrollment'] = $this->get_current_metric_value('top_groups_by_enrollment', array());
        $data['top_groups_by_engagement'] = $this->get_current_metric_value('top_groups_by_engagement', array());
        $data['top_groups_by_completions'] = $this->get_current_metric_value('top_groups_by_completions', array());
        
        // Get trend data for period
        $data['trends'] = $this->get_trend_data($period);
        
        // Cache the data
        $this->set_cached_data($cache_key, $data, 1800); // 30 minutes
        
        return $data;
    }
    
    /**
     * Get current metric value
     */
    private function get_current_metric_value($metric_key, $default = 0) {
        $metric = $this->get_metric($metric_key);
        return $metric ? $metric->metric_value : $default;
    }
    
    /**
     * Get trend data for period
     */
    private function get_trend_data($period) {
        $dates = $this->get_period_dates($period);
        $trends = array();
        
        // Get daily trends for key metrics
        $trend_metrics = array('total_students', 'active_learners', 'group_course_completions');
        
        foreach ($trend_metrics as $metric_key) {
            $trend_data = $this->get_metrics_for_period($metric_key, $dates['start_date'], $dates['end_date']);
            
            $trends[$metric_key] = array_map(function($item) {
                return array(
                    'date' => $item->date_recorded,
                    'value' => is_numeric($item->metric_value) ? (float)$item->metric_value : 0
                );
            }, $trend_data);
        }
        
        return $trends;
    }
    
    /**
     * Collect hourly metrics (for frequent updates)
     */
    public function collect_hourly_metrics() {
        if (!$this->is_available()) {
            return array();
        }
        
        $metrics = array();
        
        // Update active learners count (changes frequently)
        $groups = BBLD_Analytics_Utils::get_learndash_groups();
        $total_active_users = 0;
        
        foreach ($groups as $group) {
            $group_id = $group->ID;
            $active_users = $this->get_group_active_users($group_id, 7);
            $count = count($active_users);
            
            $this->store_metric("group_{$group_id}_active_users", $count);
            $total_active_users += $count;
        }
        
        $this->store_metric('active_learners', $total_active_users);
        $metrics['active_learners'] = $total_active_users;
        
        // Update daily quiz attempts
        $daily_quiz_attempts = $this->collect_daily_quiz_attempts();
        $this->store_metric('daily_group_quiz_attempts', $daily_quiz_attempts);
        $metrics['daily_group_quiz_attempts'] = $daily_quiz_attempts;
        
        return $metrics;
    }
    
    /**
     * Get group performance summary
     */
    public function get_group_performance_summary() {
        $groups = learndash_get_groups(true);
        $performance = array();
        
        foreach ($groups as $group) {
            $group_id = $group->ID;
            
            $enrollment = $this->get_current_metric_value("group_{$group_id}_enrollment_count");
            $active_users = $this->get_current_metric_value("group_{$group_id}_active_users");
            $completions = $this->get_current_metric_value("group_{$group_id}_course_completions");
            $engagement = $this->get_current_metric_value("group_{$group_id}_engagement_score");
            
            $performance[] = array(
                'id' => $group_id,
                'title' => $group->post_title,
                'enrollment' => $enrollment,
                'active_users' => $active_users,
                'completions' => $completions,
                'engagement_score' => $engagement,
                'status' => $this->get_group_status($engagement, $active_users, $enrollment)
            );
        }
        
        return $performance;
    }
    
    /**
     * Get group status based on metrics
     */
    private function get_group_status($engagement_score, $active_users, $total_users) {
        if ($total_users === 0) {
            return 'empty';
        }
        
        $activity_rate = $total_users > 0 ? ($active_users / $total_users) * 100 : 0;
        
        if ($engagement_score >= 70 && $activity_rate >= 60) {
            return 'excellent';
        } elseif ($engagement_score >= 50 && $activity_rate >= 40) {
            return 'good';
        } elseif ($engagement_score >= 30 && $activity_rate >= 20) {
            return 'needs_attention';
        } else {
            return 'poor';
        }
    }
}