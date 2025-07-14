<?php
/**
 * Additional Micro Functions for Freedomology Plugin
 * 
 * Resume Course, Admin Columns, and BuddyPress Integration Functions
 */

/**
 * ========================================
 * LEARNDASH RESUME COURSE FUNCTIONS
 * ========================================
 */

/**
 * Get user's most recent course progress for resume functionality
 * 
 * @param int $user_id User ID (optional, defaults to current user)
 * @return array|false Resume data array or false if no progress found
 */
function freedomology_get_user_resume_course_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Get all enrolled courses for user
    $enrolled_courses = learndash_user_get_enrolled_courses($user_id);
    
    if (empty($enrolled_courses)) {
        return false;
    }
    
    $most_recent_activity = null;
    $resume_data = null;
    
    foreach ($enrolled_courses as $course_id) {
        // Skip completed courses
        if (learndash_course_completed($user_id, $course_id)) {
            continue;
        }
        
        // Get course progress
        $course_progress = get_user_meta($user_id, '_sfwd-course_progress', true);
        $course_activity = $course_progress[$course_id] ?? null;
        
        if (!$course_activity) {
            // No progress yet, this could be a candidate
            $course_lessons = learndash_get_course_lessons_list($course_id);
            if (!empty($course_lessons)) {
                $first_lesson = reset($course_lessons);
                $candidate_data = array(
                    'course_id' => $course_id,
                    'course_title' => get_the_title($course_id),
                    'next_lesson_id' => $first_lesson['post']->ID,
                    'next_lesson_title' => $first_lesson['post']->post_title,
                    'progress_percentage' => 0,
                    'completed_lessons' => 0,
                    'total_lessons' => count($course_lessons),
                    'last_activity' => 0
                );
                
                if (!$resume_data || $candidate_data['last_activity'] > $most_recent_activity) {
                    $resume_data = $candidate_data;
                    $most_recent_activity = 0;
                }
            }
            continue;
        }
        
        // Get last activity timestamp
        $last_activity = $course_activity['last_id'] ?? 0;
        $activity_timestamp = 0;
        
        if ($last_activity) {
            $activity_meta = get_user_meta($user_id, '_sfwd-course_activity', true);
            if (isset($activity_meta[$last_activity])) {
                $activity_timestamp = $activity_meta[$last_activity]['activity_completed'] ?? 0;
            }
        }
        
        // Find next incomplete lesson
        $course_lessons = learndash_get_course_lessons_list($course_id);
        $next_lesson = null;
        
        foreach ($course_lessons as $lesson) {
            $lesson_id = $lesson['post']->ID;
            if (!learndash_is_lesson_complete($user_id, $lesson_id, $course_id)) {
                $next_lesson = $lesson;
                break;
            }
        }
        
        if ($next_lesson) {
            // Calculate progress percentage
            $total_lessons = count($course_lessons);
            $completed_lessons = 0;
            
            foreach ($course_lessons as $lesson) {
                if (learndash_is_lesson_complete($user_id, $lesson['post']->ID, $course_id)) {
                    $completed_lessons++;
                }
            }
            
            $progress_percentage = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
            
            $candidate_data = array(
                'course_id' => $course_id,
                'course_title' => get_the_title($course_id),
                'next_lesson_id' => $next_lesson['post']->ID,
                'next_lesson_title' => $next_lesson['post']->post_title,
                'progress_percentage' => $progress_percentage,
                'completed_lessons' => $completed_lessons,
                'total_lessons' => $total_lessons,
                'last_activity' => $activity_timestamp
            );
            
            // Use most recently accessed course
            if (!$resume_data || $activity_timestamp > $most_recent_activity) {
                $resume_data = $candidate_data;
                $most_recent_activity = $activity_timestamp;
            }
        }
    }
    
    return $resume_data;
}

/**
 * Display resume course button (for template use)
 * 
 * @param array $args Button configuration arguments
 */
function freedomology_display_resume_course_button($args = array()) {
    $defaults = array(
        'button_text' => 'Resume Sprint',
        'button_class' => 'custom-ld-resume-course-btn elementor-button',
        'no_course_text' => 'No courses in progress'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    if (!is_user_logged_in()) {
        echo '<p>Please log in to resume your course.</p>';
        return;
    }
    
    $resume_data = freedomology_get_user_resume_course_data();
    
    if (!$resume_data) {
        echo '<p>' . esc_html($args['no_course_text']) . '</p>';
        return;
    }
    
    $lesson_url = get_permalink($resume_data['next_lesson_id']);
    
    // Create tooltip content
    $tooltip_content = esc_html($resume_data['course_title']) . '&#10;';
    $tooltip_content .= 'Progress: ' . $resume_data['progress_percentage'] . '% (' . $resume_data['completed_lessons'] . '/' . $resume_data['total_lessons'] . ' lessons)&#10;';
    $tooltip_content .= 'Next: ' . esc_html($resume_data['next_lesson_title']);
    
    echo '<a href="' . esc_url($lesson_url) . '" class="' . esc_attr($args['button_class']) . '" title="' . $tooltip_content . '">';
    echo esc_html($args['button_text']);
    echo '</a>';
}

/**
 * Resume course shortcode handler
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function freedomology_resume_course_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'button_text' => 'Resume Sprint',
        'button_class' => 'custom-ld-resume-course-btn elementor-button',
        'no_course_text' => 'No courses in progress'
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '<p>Please log in to resume your course.</p>';
    }
    
    $resume_data = freedomology_get_user_resume_course_data();
    
    if (!$resume_data) {
        return '<p>' . esc_html($atts['no_course_text']) . '</p>';
    }
    
    $lesson_url = get_permalink($resume_data['next_lesson_id']);
    
    // Create tooltip content
    $tooltip_content = esc_html($resume_data['course_title']) . '&#10;';
    $tooltip_content .= 'Progress: ' . $resume_data['progress_percentage'] . '% (' . $resume_data['completed_lessons'] . '/' . $resume_data['total_lessons'] . ' lessons)&#10;';
    $tooltip_content .= 'Next: ' . esc_html($resume_data['next_lesson_title']);
    
    $output = '<a href="' . esc_url($lesson_url) . '" class="' . esc_attr($atts['button_class']) . '" title="' . $tooltip_content . '">';
    $output .= esc_html($atts['button_text']);
    $output .= '</a>';
    
    return $output;
}

/**
 * AJAX handler for getting resume course data
 */
function freedomology_handle_resume_course_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }
    
    $resume_data = freedomology_get_user_resume_course_data();
    
    if (!$resume_data) {
        wp_send_json_error('No course progress found');
    }
    
    $resume_data['lesson_url'] = get_permalink($resume_data['next_lesson_id']);
    
    wp_send_json_success($resume_data);
}

/**
 * Add resume course button styles
 */
function freedomology_add_resume_course_button_styles() {
    ?>
    <style>
    /* Resume Course Button - Matches existing Elementor button styles */
    .custom-ld-resume-course-btn.elementor-button {
        display: inline-block;
        padding: 14px 30px;
        background: #30BFBA;
        border: none;
        border-radius: 9px;
        color: #FFF;
        font-weight: 400;
        margin: 0;
        transition: all 0.3s ease;
        position: relative;
        font-size: 15px;
        text-decoration: none;
    }
    
    .custom-ld-resume-course-btn.elementor-button:hover {
        background: linear-gradient(90deg, rgba(30, 200, 198, 1) 0%, rgba(30, 213, 164, 1) 100%);
        color: #FFF;
        text-decoration: none;
    }
    
    .custom-ld-resume-course-btn.elementor-button:active {
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(34, 223, 220, 0.2);
    }
    
    /* Enhanced tooltip styling */
    .custom-ld-resume-course-btn[title]:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #333 0%, #444 100%);
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 12px;
        line-height: 18px;
        white-space: pre-line;
        z-index: 1000;
        min-width: 220px;
        text-align: center;
        font-weight: normal;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(34, 223, 220, 0.2);
    }
    
    .custom-ld-resume-course-btn[title]:hover::before {
        content: "";
        position: absolute;
        bottom: 110%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: #333;
        z-index: 1000;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .custom-ld-resume-course-btn.elementor-button {
            margin: auto;
            display: block;
            max-width: 200px;
        }
        
        .custom-ld-resume-course-btn[title]:hover::after {
            min-width: 180px;
            font-size: 11px;
            padding: 8px 12px;
        }
    }
    </style>
    <?php
}

/**
 * ========================================
 * ADMIN GROUPS COLUMN FUNCTIONS
 * ========================================
 */

/**
 * Add custom columns to Groups admin list
 * 
 * @param array $columns Existing columns
 * @return array Modified columns
 */
function freedomology_add_groups_admin_columns($columns) {
    // Insert new columns after title
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key == 'title') {
            $new_columns['group_leader'] = 'Group Leader';
            $new_columns['sprint_start_date'] = 'Sprint Start Date';
            $new_columns['course'] = 'Course';
        }
    }
    return $new_columns;
}

/**
 * Populate custom admin columns for Groups
 * 
 * @param string $column Column name
 * @param int $post_id Group post ID
 */
function freedomology_populate_groups_admin_columns($column, $post_id) {
    switch ($column) {
        case 'group_leader':
            // Get group leaders
            $leaders = learndash_get_groups_administrator_ids($post_id);
            if (!empty($leaders)) {
                $leader_names = array();
                foreach ($leaders as $leader_id) {
                    $user = get_userdata($leader_id);
                    if ($user) {
                        $leader_names[] = $user->display_name . ' (' . $user->user_email . ')';
                    }
                }
                echo implode('<br>', $leader_names);
            } else {
                // Try to get from stored meta
                $leader_email = get_post_meta($post_id, '_group_leader_email', true);
                if (!empty($leader_email)) {
                    echo '<em>' . esc_html($leader_email) . '</em>';
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
            }
            break;
            
        case 'sprint_start_date':
            $start_date = get_post_meta($post_id, '_sprint_start_date', true);
            if (!empty($start_date)) {
                // Format date nicely
                $date = DateTime::createFromFormat('Y-m-d', $start_date);
                if ($date) {
                    echo $date->format('M j, Y');
                } else {
                    echo esc_html($start_date);
                }
            } else {
                // Try to get from group leader's user meta
                $leaders = learndash_get_groups_administrator_ids($post_id);
                if (!empty($leaders)) {
                    $leader_id = $leaders[0];
                    $course_ids = learndash_group_enrolled_courses($post_id);
                    if (!empty($course_ids)) {
                        $course_id = $course_ids[0];
                        $meta_key = '';
                        switch ($course_id) {
                            case 6298:
                                $meta_key = 'sprintr40_start';
                                break;
                            case 6163:
                                $meta_key = 'sprintf40_start';
                                break;
                            case 6160:
                                $meta_key = 'sprinth40_start';
                                break;
                        }
                        if (!empty($meta_key)) {
                            $user_date = get_user_meta($leader_id, $meta_key, true);
                            if (!empty($user_date)) {
                                $date = DateTime::createFromFormat('Y-m-d', $user_date);
                                if ($date) {
                                    echo '<em>' . $date->format('M j, Y') . '</em>';
                                } else {
                                    echo '<em>' . esc_html($user_date) . '</em>';
                                }
                            } else {
                                echo '<span style="color:red;">Not Set</span>';
                            }
                        }
                    }
                } else {
                    echo '<span style="color:red;">Not Set</span>';
                }
            }
            break;
            
        case 'course':
            $course_ids = learndash_group_enrolled_courses($post_id);
            if (!empty($course_ids)) {
                $course_names = array();
                foreach ($course_ids as $course_id) {
                    $course_names[] = get_the_title($course_id);
                }
                echo implode(', ', $course_names);
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;
    }
}

/**
 * Make admin columns sortable
 * 
 * @param array $columns Sortable columns
 * @return array Modified sortable columns
 */
function freedomology_make_groups_columns_sortable($columns) {
    $columns['sprint_start_date'] = 'sprint_start_date';
    return $columns;
}

/**
 * Handle admin column sorting
 * 
 * @param WP_Query $query Query object
 */
function freedomology_handle_groups_column_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== 'groups') {
        return;
    }
    
    if ('sprint_start_date' == $query->get('orderby')) {
        $query->set('meta_key', '_sprint_start_date');
        $query->set('orderby', 'meta_value');
    }
}

/**
 * Add admin CSS for Groups columns
 */
function freedomology_add_groups_admin_css() {
    $screen = get_current_screen();
    if ($screen && $screen->id == 'edit-groups') {
        ?>
        <style>
            .column-group_leader { width: 25%; }
            .column-sprint_start_date { width: 15%; }
            .column-course { width: 20%; }
        </style>
        <?php
    }
}

/**
 * ========================================
 * BUDDYPRESS INTEGRATION FUNCTIONS
 * ========================================
 */

/**
 * Enable BuddyPress shortcodes on LearnDash lessons
 */
function freedomology_enable_buddypress_on_lessons() {
    if (is_singular('sfwd-lessons')) {
        // Force shortcode detection to return true
        add_filter('bp_shortcode_pro_is_shortcode_page', '__return_true');
    }
}

/**
 * Ensure BuddyPress assets are loaded on LearnDash lessons
 */
function freedomology_load_buddypress_assets_on_lessons() {
    if (is_singular('sfwd-lessons')) {
        // Force load BuddyPress shortcode assets
        add_filter('bp_shortcode_pro_is_shortcode_page', '__return_true');
        
        // Additional safety - manually trigger asset loading if needed
        if (class_exists('Shortcodes_For_BuddyPress_Public')) {
            // This ensures the CSS/JS detection passes
            global $post;
            if ($post && !has_shortcode($post->post_content, 'activity-listing')) {
                // Temporarily add shortcode to content for detection
                add_filter('the_content', function($content) {
                    return $content . '<!-- bp-shortcode-detected -->';
                });
            }
        }
    }
}

/**
 * ========================================
 * INITIALIZATION FUNCTIONS
 * ========================================
 */

/**
 * Initialize resume course functionality
 */
function freedomology_initialize_resume_course() {
    // Register shortcode
    add_shortcode('custom_ld_resume_course_button', 'freedomology_resume_course_button_shortcode');
    
    // AJAX handlers
    add_action('wp_ajax_custom_ld_get_resume_course_data', 'freedomology_handle_resume_course_ajax');
    
    // Add styles
    add_action('wp_head', 'freedomology_add_resume_course_button_styles');
}

/**
 * Initialize admin Groups columns
 */
function freedomology_initialize_groups_admin_columns() {
    // Add columns
    add_filter('manage_groups_posts_columns', 'freedomology_add_groups_admin_columns');
    add_action('manage_groups_posts_custom_column', 'freedomology_populate_groups_admin_columns', 10, 2);
    
    // Make sortable
    add_filter('manage_edit-groups_sortable_columns', 'freedomology_make_groups_columns_sortable');
    add_action('pre_get_posts', 'freedomology_handle_groups_column_sorting');
    
    // Add CSS
    add_action('admin_head', 'freedomology_add_groups_admin_css');
}

/**
 * Initialize BuddyPress integration
 */
function freedomology_initialize_buddypress_integration() {
    // Early hook for shortcode detection
    add_action('wp', 'freedomology_enable_buddypress_on_lessons', 1);
    
    // Asset loading
    add_action('wp_enqueue_scripts', 'freedomology_load_buddypress_assets_on_lessons', 5);
}

// Initialize all additional functions
add_action('init', 'freedomology_initialize_resume_course');
add_action('init', 'freedomology_initialize_groups_admin_columns');
add_action('init', 'freedomology_initialize_buddypress_integration');