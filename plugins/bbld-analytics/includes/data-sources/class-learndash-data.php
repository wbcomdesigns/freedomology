<?php
/**
 * LearnDash Data Source
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BBLD_Analytics_LearnDash_Data extends BBLD_Analytics_Abstract_Data_Source {
    
    /**
     * Initialize data source
     */
    protected function init() {
        $this->source_id = 'learndash';
    }
    
    /**
     * Check if data source is available
     */
    public function is_available() {
        return class_exists('SFWD_LMS');
    }
    
    /**
     * Collect metrics
     */
    public function collect_metrics() {
        if (!$this->is_available()) {
            return array();
        }
        
        $metrics = array();
        
        // Total courses
        $total_courses = $this->get_total_courses();
        $this->store_metric('total_courses', $total_courses);
        $metrics['total_courses'] = $total_courses;
        
        // Total lessons
        $total_lessons = $this->get_total_lessons();
        $this->store_metric('total_lessons', $total_lessons);
        $metrics['total_lessons'] = $total_lessons;
        
        // Total students
        $total_students = $this->get_total_students();
        $this->store_metric('total_students', $total_students);
        $metrics['total_students'] = $total_students;
        
        // Course completions
        $course_completions = $this->get_course_completions();
        $this->store_metric('course_completions', $course_completions);
        $metrics['course_completions'] = $course_completions;
        
        // Lesson completions
        $lesson_completions = $this->get_lesson_completions();
        $this->store_metric('lesson_completions', $lesson_completions);
        $metrics['lesson_completions'] = $lesson_completions;
        
        // Quiz attempts
        $quiz_attempts = $this->get_quiz_attempts();
        $this->store_metric('quiz_attempts', $quiz_attempts);
        $metrics['quiz_attempts'] = $quiz_attempts;
        
        // Average completion time
        $avg_completion_time = $this->get_average_completion_time();
        $this->store_metric('avg_completion_time', $avg_completion_time);
        $metrics['avg_completion_time'] = $avg_completion_time;
        
        return $metrics;
    }
    
    /**
     * Get total courses
     */
    private function get_total_courses() {
        return wp_count_posts('sfwd-courses')->publish;
    }
    
    /**
     * Get total lessons
     */
    private function get_total_lessons() {
        return wp_count_posts('sfwd-lessons')->publish;
    }
    
    /**
     * Get total students enrolled in courses
     */
    private function get_total_students() {
        global $wpdb;
        
        // Get users enrolled in any course
        $enrolled_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'course_%_access_from'"
        );
        
        return (int)$enrolled_users;
    }
    
    /**
     * Get course completions
     */
    private function get_course_completions() {
        global $wpdb;
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'course_completed_%'"
        );
    }
    
    /**
     * Get lesson completions
     */
    private function get_lesson_completions() {
        global $wpdb;
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'learndash_course_%_lesson_%'"
        );
    }
    
    /**
     * Get quiz attempts
     */
    private function get_quiz_attempts() {
        global $wpdb;
        
        $quiz_table = esc_sql(LDLMS_DB::get_table_name('user_activity'));
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$quiz_table'") !== $quiz_table) {
            return 0;
        }
        
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$quiz_table} WHERE activity_type = 'quiz'"
        );
    }
    
    /**
     * Get average course completion time
     */
    private function get_average_completion_time() {
        global $wpdb;
        
        // Get course completion times from user activity
        $completion_times = $wpdb->get_results(
            "SELECT 
                um1.meta_value as started,
                um2.meta_value as completed
             FROM {$wpdb->usermeta} um1
             JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
             WHERE um1.meta_key LIKE 'course_%_access_from'
             AND um2.meta_key LIKE 'course_completed_%'
             AND SUBSTRING(um1.meta_key, 8, LENGTH(um1.meta_key) - 19) = SUBSTRING(um2.meta_key, 18)
             LIMIT 1000"
        );
        
        if (empty($completion_times)) {
            return 0;
        }
        
        $total_time = 0;
        $valid_completions = 0;
        
        foreach ($completion_times as $completion) {
            $started = (int)$completion->started;
            $completed = (int)$completion->completed;
            
            if ($completed > $started) {
                $total_time += ($completed - $started);
                $valid_completions++;
            }
        }
        
        return $valid_completions > 0 ? round($total_time / $valid_completions / 86400, 1) : 0; // Days
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
        $data['total_courses'] = $this->get_current_metric_value('total_courses');
        $data['total_lessons'] = $this->get_current_metric_value('total_lessons');
        $data['total_students'] = $this->get_current_metric_value('total_students');
        $data['course_completions'] = $this->get_current_metric_value('course_completions');
        $data['lesson_completions'] = $this->get_current_metric_value('lesson_completions');
        $data['quiz_attempts'] = $this->get_current_metric_value('quiz_attempts');
        $data['avg_completion_time'] = $this->get_current_metric_value('avg_completion_time');
        
        // Course performance
        $data['course_performance'] = $this->get_course_performance();
        
        // Popular courses
        $data['popular_courses'] = $this->get_popular_courses();
        
        // Trends
        $data['trends'] = $this->get_trend_data($period);
        
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
     * Get course performance data
     */
    private function get_course_performance() {
        $shared_courses = bbld_analytics()->get_option('shared_courses', array());
        $performance = array();
        
        foreach ($shared_courses as $course_id) {
            $course = get_post($course_id);
            
            if (!$course) {
                continue;
            }
            
            $enrolled = $this->get_course_enrolled_users($course_id);
            $completed = $this->get_course_completed_users($course_id);
            $completion_rate = count($enrolled) > 0 ? (count($completed) / count($enrolled)) * 100 : 0;
            
            $performance[] = array(
                'course_id' => $course_id,
                'title' => $course->post_title,
                'enrolled' => count($enrolled),
                'completed' => count($completed),
                'completion_rate' => round($completion_rate, 2)
            );
        }
        
        return $performance;
    }
    
    /**
     * Get course enrolled users
     */
    private function get_course_enrolled_users($course_id) {
        return learndash_get_users_for_course($course_id, array(), false);
    }
    
    /**
     * Get course completed users
     */
    private function get_course_completed_users($course_id) {
        global $wpdb;
        
        $completed_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            "course_completed_{$course_id}"
        ));
        
        return array_map('intval', $completed_users);
    }
    
    /**
     * Get popular courses
     */
    private function get_popular_courses($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                SUBSTRING(meta_key, 8, LENGTH(meta_key) - 19) as course_id,
                COUNT(*) as enrollment_count
             FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'course_%%_access_from'
             GROUP BY course_id
             ORDER BY enrollment_count DESC
             LIMIT %d",
            $limit
        ));
        
        $popular_courses = array();
        
        foreach ($results as $result) {
            $course = get_post($result->course_id);
            
            if ($course) {
                $popular_courses[] = array(
                    'course_id' => $result->course_id,
                    'title' => $course->post_title,
                    'enrollment_count' => (int)$result->enrollment_count
                );
            }
        }
        
        return $popular_courses;
    }
    
    /**
     * Get trend data
     */
    private function get_trend_data($period) {
        $dates = $this->get_period_dates($period);
        $trends = array();
        
        $trend_metrics = array('course_completions', 'lesson_completions', 'quiz_attempts');
        
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
     * Collect hourly metrics
     */
    public function collect_hourly_metrics() {
        if (!$this->is_available()) {
            return array();
        }
        
        $metrics = array();
        
        // Update quiz attempts (changes frequently)
        $quiz_attempts = $this->get_quiz_attempts();
        $this->store_metric('quiz_attempts', $quiz_attempts);
        $metrics['quiz_attempts'] = $quiz_attempts;
        
        return $metrics;
    }
}