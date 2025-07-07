<?php
/**
 * Platform Data Source
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_Platform_Data extends BBLD_Analytics_Abstract_Data_Source {
    
    /**
     * Initialize data source
     */
    protected function init() {
        $this->source_id = 'platform';
    }
    
    /**
     * Check if data source is available
     */
    public function is_available() {
        return true; // Platform data is always available
    }
    
    /**
     * Collect metrics
     */
    public function collect_metrics() {
        $metrics = array();
        
        // Total users
        $total_users = $this->count_records($this->get_wp_users_table());
        $this->store_metric('total_users', $total_users);
        $metrics['total_users'] = $total_users;
        
        // New registrations today
        $new_registrations = $this->get_new_registrations_today();
        $this->store_metric('new_registrations', $new_registrations);
        $metrics['new_registrations'] = $new_registrations;
        
        // Active users metrics
        $daily_active = $this->get_active_users(1);
        $weekly_active = $this->get_active_users(7);
        $monthly_active = $this->get_active_users(30);
        
        $this->store_metric('daily_active_users', count($daily_active));
        $this->store_metric('weekly_active_users', count($weekly_active));
        $this->store_metric('monthly_active_users', count($monthly_active));
        
        $metrics['daily_active_users'] = count($daily_active);
        $metrics['weekly_active_users'] = count($weekly_active);
        $metrics['monthly_active_users'] = count($monthly_active);
        
        // Overall engagement score
        $engagement_score = $this->calculate_engagement_score($total_users, $monthly_active);
        $this->store_metric('engagement_score', $engagement_score);
        $metrics['engagement_score'] = $engagement_score;
        
        // User role distribution
        $role_distribution = $this->get_user_role_distribution();
        $this->store_metric('user_role_distribution', $role_distribution);
        $metrics['user_role_distribution'] = $role_distribution;
        
        // Registration trends
        $registration_trends = $this->get_registration_trends();
        $this->store_metric('registration_trends', $registration_trends);
        $metrics['registration_trends'] = $registration_trends;
        
        return $metrics;
    }
    
    /**
     * Get WordPress users table name
     */
    private function get_wp_users_table() {
        global $wpdb;
        return $wpdb->users;
    }
    
    /**
     * Get new registrations today
     */
    private function get_new_registrations_today() {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE DATE(user_registered) = %s",
            $today
        ));
    }
    
    /**
     * Get active users for specified days
     */
    private function get_active_users($days) {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Check if activity table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            // Fallback to user meta for last activity
            return $this->get_active_users_fallback($days);
        }
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $activity_table WHERE recorded_at >= %s",
            $since_date
        ));
        
        return array_map('intval', $user_ids);
    }
    
    /**
     * Fallback method to get active users using user meta
     */
    private function get_active_users_fallback($days) {
        global $wpdb;
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Check for users with recent last activity
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'bbld_last_activity' 
             AND meta_value >= %s",
            $since_date
        ));
        
        // If no activity meta, use last login times
        if (empty($user_ids)) {
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE user_login >= %s",
                $since_date
            ));
        }
        
        return array_map('intval', $user_ids);
    }
    
    /**
     * Calculate overall engagement score
     */
    private function calculate_engagement_score($total_users, $active_users) {
        if ($total_users === 0) {
            return 0;
        }
        
        $active_count = is_array($active_users) ? count($active_users) : $active_users;
        return ($active_count / $total_users) * 100;
    }
    
    /**
     * Get user role distribution
     */
    private function get_user_role_distribution() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT meta_value as role, COUNT(*) as count 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = '{$wpdb->prefix}capabilities' 
             GROUP BY meta_value"
        );
        
        $distribution = array();
        
        foreach ($results as $result) {
            // Parse the capabilities meta value
            $capabilities = maybe_unserialize($result->role);
            
            if (is_array($capabilities)) {
                $roles = array_keys($capabilities);
                $primary_role = reset($roles);
                
                if (!isset($distribution[$primary_role])) {
                    $distribution[$primary_role] = 0;
                }
                
                $distribution[$primary_role] += (int)$result->count;
            }
        }
        
        return $distribution;
    }
    
    /**
     * Get registration trends for last 30 days
     */
    private function get_registration_trends() {
        global $wpdb;
        
        $trends = array();
        
        // Get daily registrations for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->users} WHERE DATE(user_registered) = %s",
                $date
            ));
            
            $trends[] = array(
                'date' => $date,
                'registrations' => (int)$count
            );
        }
        
        return $trends;
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data($period = '30d') {
        $cache_key = $this->get_cache_key($period);
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        $data = array();
        
        // Current metrics
        $data['total_users'] = $this->get_current_metric_value('total_users');
        $data['new_registrations'] = $this->get_current_metric_value('new_registrations');
        $data['daily_active_users'] = $this->get_current_metric_value('daily_active_users');
        $data['weekly_active_users'] = $this->get_current_metric_value('weekly_active_users');
        $data['monthly_active_users'] = $this->get_current_metric_value('monthly_active_users');
        $data['engagement_score'] = $this->get_current_metric_value('engagement_score');
        
        // Complex data
        $data['user_role_distribution'] = $this->get_current_metric_value('user_role_distribution', array());
        $data['registration_trends'] = $this->get_current_metric_value('registration_trends', array());
        
        // Period-specific trends
        $data['trends'] = $this->get_trend_data($period);
        
        // Growth calculations
        $data['growth'] = $this->calculate_growth_metrics($period);
        
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
        
        $trend_metrics = array(
            'total_users',
            'new_registrations', 
            'daily_active_users',
            'weekly_active_users',
            'monthly_active_users'
        );
        
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
     * Calculate growth metrics
     */
    private function calculate_growth_metrics($period) {
        $current_date = current_time('Y-m-d');
        
        // Calculate comparison date based on period
        switch ($period) {
            case '7d':
                $comparison_date = date('Y-m-d', strtotime('-14 days'));
                break;
            case '30d':
                $comparison_date = date('Y-m-d', strtotime('-60 days'));
                break;
            case '90d':
                $comparison_date = date('Y-m-d', strtotime('-180 days'));
                break;
            case '1y':
                $comparison_date = date('Y-m-d', strtotime('-2 years'));
                break;
            default:
                $comparison_date = date('Y-m-d', strtotime('-60 days'));
        }
        
        $growth = array();
        
        // Calculate growth for key metrics
        $metrics_to_compare = array('total_users', 'weekly_active_users', 'monthly_active_users');
        
        foreach ($metrics_to_compare as $metric_key) {
            $current = $this->get_metric($metric_key, $current_date);
            $previous = $this->get_metric($metric_key, $comparison_date);
            
            $current_value = $current ? (float)$current->metric_value : 0;
            $previous_value = $previous ? (float)$previous->metric_value : 0;
            
            $growth_rate = $this->calculate_growth_rate($current_value, $previous_value);
            
            $growth[$metric_key] = array(
                'current' => $current_value,
                'previous' => $previous_value,
                'growth_rate' => $growth_rate,
                'growth_absolute' => $current_value - $previous_value
            );
        }
        
        return $growth;
    }
    
    /**
     * Get user activity summary
     */
    public function get_user_activity_summary() {
        $summary = array();
        
        // Recent user registrations
        $summary['recent_registrations'] = $this->get_recent_registrations(10);
        
        // Most active users
        $summary['most_active_users'] = $this->get_most_active_users(10);
        
        // User activity by day of week
        $summary['activity_by_day'] = $this->get_activity_by_day_of_week();
        
        // User activity by hour
        $summary['activity_by_hour'] = $this->get_activity_by_hour();
        
        return $summary;
    }
    
    /**
     * Get recent user registrations
     */
    private function get_recent_registrations($limit = 10) {
        global $wpdb;
        
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, user_login, user_email, user_registered, display_name 
             FROM {$wpdb->users} 
             ORDER BY user_registered DESC 
             LIMIT %d",
            $limit
        ));
        
        return array_map(function($user) {
            return array(
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'registered' => $user->user_registered
            );
        }, $users);
    }
    
    /**
     * Get most active users
     */
    private function get_most_active_users($limit = 10) {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        // Check if activity table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                a.user_id,
                COUNT(*) as activity_count,
                u.display_name,
                u.user_email
             FROM $activity_table a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY a.user_id
             ORDER BY activity_count DESC
             LIMIT %d",
            $limit
        ));
        
        return array_map(function($result) {
            return array(
                'user_id' => $result->user_id,
                'display_name' => $result->display_name,
                'email' => $result->user_email,
                'activity_count' => (int)$result->activity_count
            );
        }, $results);
    }
    
    /**
     * Get activity by day of week
     */
    private function get_activity_by_day_of_week() {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            return array();
        }
        
        $results = $wpdb->get_results(
            "SELECT 
                DAYNAME(recorded_at) as day_name,
                DAYOFWEEK(recorded_at) as day_number,
                COUNT(*) as activity_count
             FROM $activity_table
             WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DAYOFWEEK(recorded_at), DAYNAME(recorded_at)
             ORDER BY day_number"
        );
        
        return array_map(function($result) {
            return array(
                'day' => $result->day_name,
                'count' => (int)$result->activity_count
            );
        }, $results);
    }
    
    /**
     * Get activity by hour
     */
    private function get_activity_by_hour() {
        global $wpdb;
        
        $activity_table = $wpdb->prefix . 'bbld_analytics_activity';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") !== $activity_table) {
            return array();
        }
        
        $results = $wpdb->get_results(
            "SELECT 
                HOUR(recorded_at) as hour,
                COUNT(*) as activity_count
             FROM $activity_table
             WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY HOUR(recorded_at)
             ORDER BY hour"
        );
        
        return array_map(function($result) {
            return array(
                'hour' => (int)$result->hour,
                'count' => (int)$result->activity_count
            );
        }, $results);
    }
    
    /**
     * Collect hourly metrics
     */
    public function collect_hourly_metrics() {
        $metrics = array();
        
        // Update active users counts
        $daily_active = $this->get_active_users(1);
        $weekly_active = $this->get_active_users(7);
        $monthly_active = $this->get_active_users(30);
        
        $this->store_metric('daily_active_users', count($daily_active));
        $this->store_metric('weekly_active_users', count($weekly_active));
        $this->store_metric('monthly_active_users', count($monthly_active));
        
        $metrics['daily_active_users'] = count($daily_active);
        $metrics['weekly_active_users'] = count($weekly_active);
        $metrics['monthly_active_users'] = count($monthly_active);
        
        // Update new registrations
        $new_registrations = $this->get_new_registrations_today();
        $this->store_metric('new_registrations', $new_registrations);
        $metrics['new_registrations'] = $new_registrations;
        
        return $metrics;
    }
}