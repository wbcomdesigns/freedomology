<?php
/**
 * BuddyBoss Data Source
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_BuddyBoss_Data extends BBLD_Analytics_Abstract_Data_Source {
    
    /**
     * Initialize data source
     */
    protected function init() {
        $this->source_id = 'buddyboss';
    }
    
    /**
     * Check if data source is available
     */
    public function is_available() {
        return class_exists('BuddyPress') || function_exists('bp_is_active');
    }
    
    /**
     * Collect metrics
     */
    public function collect_metrics() {
        if (!$this->is_available()) {
            return array();
        }
        
        $metrics = array();
        
        // Total posts
        $total_posts = $this->get_total_activity_posts();
        $this->store_metric('total_posts', $total_posts);
        $metrics['total_posts'] = $total_posts;
        
        // Total likes/favorites
        $total_likes = $this->get_total_activity_favorites();
        $this->store_metric('total_likes', $total_likes);
        $metrics['total_likes'] = $total_likes;
        
        // Active users (30 days)
        $active_users = $this->get_bp_active_users(30);
        $this->store_metric('active_users', count($active_users));
        $metrics['active_users'] = count($active_users);
        
        // Daily posts
        $daily_posts = $this->get_daily_activity_posts();
        $this->store_metric('daily_posts', $daily_posts);
        $metrics['daily_posts'] = $daily_posts;
        
        // Group activity
        $group_activity = $this->get_group_activity_count();
        $this->store_metric('group_activity', $group_activity);
        $metrics['group_activity'] = $group_activity;
        
        // Forum activity
        $forum_activity = $this->get_forum_activity_count();
        $this->store_metric('forum_activity', $forum_activity);
        $metrics['forum_activity'] = $forum_activity;
        
        return $metrics;
    }
    
    /**
     * Get total activity posts
     */
    private function get_total_activity_posts() {
        global $wpdb;
        
        if (!$this->is_available()) {
            return 0;
        }
        
        $bp_prefix = bp_core_get_table_prefix();
        $activity_table = $bp_prefix . 'bp_activity';
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$activity_table} WHERE type = 'activity_update' AND hide_sitewide = 0"
        );
    }
    
    /**
     * Get total activity favorites
     */
    private function get_total_activity_favorites() {
        global $wpdb;
        
        if (!$this->is_available() || !function_exists('bp_activity_get_meta_table_name')) {
            return 0;
        }
        
        $meta_table = bp_activity_get_meta_table_name();
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$meta_table} WHERE meta_key = 'favorite_count'"
        );
    }
    
    /**
     * Get BuddyPress active users
     */
    private function get_bp_active_users($days = 30) {
        global $wpdb;
        
        if (!$this->is_available()) {
            return array();
        }
        
        $bp_prefix = bp_core_get_table_prefix();
        $activity_table = $bp_prefix . 'bp_activity';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$activity_table} WHERE date_recorded >= %s",
            $since_date
        ));
        
        return array_map('intval', $user_ids);
    }
    
    /**
     * Get daily activity posts
     */
    private function get_daily_activity_posts() {
        global $wpdb;
        
        if (!$this->is_available()) {
            return 0;
        }
        
        $bp_prefix = bp_core_get_table_prefix();
        $activity_table = $bp_prefix . 'bp_activity';
        $today = current_time('Y-m-d');
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$activity_table} 
             WHERE type = 'activity_update' 
             AND DATE(date_recorded) = %s 
             AND hide_sitewide = 0",
            $today
        ));
    }
    
    /**
     * Get group activity count
     */
    private function get_group_activity_count() {
        global $wpdb;
        
        if (!$this->is_available()) {
            return 0;
        }
        
        $bp_prefix = bp_core_get_table_prefix();
        $activity_table = $bp_prefix . 'bp_activity';
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$activity_table} 
             WHERE component = 'groups' 
             AND hide_sitewide = 0"
        );
    }
    
    /**
     * Get forum activity count
     */
    private function get_forum_activity_count() {
        global $wpdb;
        
        if (!$this->is_available() || !function_exists('bbp_get_forum_post_type')) {
            return 0;
        }
        
        // Count forum topics and replies
        $topics = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'topic' AND post_status = 'publish'");
        $replies = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reply' AND post_status = 'publish'");
        
        return (int)$topics + (int)$replies;
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
        
        $data = array();
        
        // Current metrics
        $data['total_posts'] = $this->get_current_metric_value('total_posts');
        $data['total_likes'] = $this->get_current_metric_value('total_likes');
        $data['active_users'] = $this->get_current_metric_value('active_users');
        $data['daily_posts'] = $this->get_current_metric_value('daily_posts');
        $data['group_activity'] = $this->get_current_metric_value('group_activity');
        $data['forum_activity'] = $this->get_current_metric_value('forum_activity');
        
        // Trends
        $data['trends'] = $this->get_trend_data($period);
        
        // Top contributors
        $data['top_contributors'] = $this->get_top_contributors();
        
        // Cache the data
        $this->set_cached_data($cache_key, $data, 1800);
        
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
     * Get trend data
     */
    private function get_trend_data($period) {
        $dates = $this->get_period_dates($period);
        $trends = array();
        
        $trend_metrics = array('total_posts', 'daily_posts', 'active_users');
        
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
     * Get top contributors
     */
    private function get_top_contributors($limit = 10) {
        global $wpdb;
        
        if (!$this->is_available()) {
            return array();
        }
        
        $bp_prefix = bp_core_get_table_prefix();
        $activity_table = $bp_prefix . 'bp_activity';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                a.user_id,
                COUNT(*) as activity_count,
                u.display_name
             FROM {$activity_table} a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.type = 'activity_update' 
             AND a.hide_sitewide = 0
             AND a.date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY a.user_id
             ORDER BY activity_count DESC
             LIMIT %d",
            $limit
        ));
        
        return array_map(function($result) {
            return array(
                'user_id' => $result->user_id,
                'display_name' => $result->display_name,
                'activity_count' => (int)$result->activity_count
            );
        }, $results);
    }
}