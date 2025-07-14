<?php

/**
 * Lesson Unlock System for Freedomology Plugin
 * Controls lesson access based on sprint start dates and lesson delays.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FreedomologyLessonUnlockSystem
{
    /**
     * Constructor to initialize the system
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Check if lesson unlock system is enabled
        if (!$this->is_lesson_unlock_enabled()) {
            return;
        }

        // Main content filter for lesson access control
        add_filter('learndash_content', array($this, 'wbcom_check_sprint_access_before_content'), 10, 2);

        // Admin meta box functionality
        add_action('add_meta_boxes', array($this, 'wbcom_add_lesson_delay_meta_box'));
        add_action('save_post_sfwd-lessons', array($this, 'wbcom_save_lesson_delay_meta'));

        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'wbcom_enqueue_admin_styles'));

        // Navigation modifications for locked lessons
        add_filter('learndash_course_nav_items', array($this, 'wbcom_modify_lesson_navigation_items'), 10, 3);
        add_action('wp_head', array($this, 'wbcom_add_navigation_styles'));
        add_filter('body_class', array($this, 'wbcom_add_body_classes_for_navigation'));

        // AJAX handler for checking lesson lock status
        add_action('wp_ajax_wbcom_check_lesson_lock_status', array($this, 'wbcom_ajax_check_lesson_lock_status'));
        add_action('wp_ajax_nopriv_wbcom_check_lesson_lock_status', array($this, 'wbcom_ajax_check_lesson_lock_status'));

        // Enqueue navigation scripts
        add_action('wp_enqueue_scripts', array($this, 'wbcom_enqueue_navigation_scripts'));
    }

    /**
     * Check if lesson unlock system is enabled
     * 
     * @return bool True if enabled, false if disabled
     */
    private function is_lesson_unlock_enabled()
    {
        /**
         * Filter to enable/disable the lesson unlock system
         * 
         * @param bool $enabled Whether the lesson unlock system is enabled. Default true.
         * 
         * Usage examples:
         * 
         * // Disable globally
         * add_filter('freedomology_lesson_unlock_enabled', '__return_false');
         * 
         * // Enable only for specific courses
         * add_filter('freedomology_lesson_unlock_enabled', function($enabled) {
         *     if (is_singular('sfwd-lessons')) {
         *         $course_id = learndash_get_course_id(get_the_ID());
         *         return in_array($course_id, [6298, 6163, 6160]); // Only for sprint courses
         *     }
         *     return $enabled;
         * });
         * 
         * // Disable for administrators
         * add_filter('freedomology_lesson_unlock_enabled', function($enabled) {
         *     return !current_user_can('manage_options');
         * });
         */
        return apply_filters('freedomology_lesson_unlock_enabled', true);
    }

    /**
     * Get course-specific meta key for sprint start dates
     */
    private function wbcom_get_course_meta_key($course_id)
    {
        switch ($course_id) {
            case 6298: // R40 Relational Sprint
                return 'sprintr40_start';
            case 6163: // F40 Financial Sprint  
                return 'sprintf40_start';
            case 6160: // H40 Health Sprint
                return 'sprinth40_start';
            default:
                return false;
        }
    }

    /**
     * Get group leader ID for a user
     */
    private function wbcom_get_group_leader_id($user_id)
    {
        // First, check if the current user is a group leader themselves
        $user_data = get_userdata($user_id);
        if (!$user_data) {
            return $user_id;
        }
        
        $user_email = $user_data->user_email;
        $sprintleader_email = get_user_meta($user_id, 'sprintleader_email', true);
        
        // If user has sprintleader_email meta and it matches their email, they are the leader
        if (!empty($sprintleader_email) && $sprintleader_email === $user_email) {
            return $user_id;
        }
        
        // Alternative check: if user has any sprint start date meta, they might be a leader
        $sprint_meta_keys = ['sprintr40_start', 'sprintf40_start', 'sprinth40_start'];
        foreach ($sprint_meta_keys as $meta_key) {
            $sprint_date = get_user_meta($user_id, $meta_key, true);
            if (!empty($sprint_date)) {
                return $user_id; // User has sprint dates, so they're likely a leader
            }
        }

        // Get user's groups (original logic for group members)
        $user_groups = learndash_get_users_group_ids($user_id);

        if (!empty($user_groups)) {
            foreach ($user_groups as $group_id) {
                // Get group leader email from group meta
                $group_leader_email = get_post_meta($group_id, '_group_leader_email', true);
                if (!empty($group_leader_email)) {
                    $group_leader = get_user_by('email', $group_leader_email);
                    if ($group_leader) {
                        return $group_leader->ID;
                    }
                }
            }
        }

        // If no group leader found, return the user's own ID (fallback)
        return $user_id;
    }

    /**
     * Check if lesson is unlocked based on sprint start date + lesson delay
     */
    private function wbcom_is_lesson_unlocked($user_id, $lesson_id)
    {
        // Get the course ID for this lesson
        $course_id = learndash_get_course_id($lesson_id);

        if (!$course_id) {
            return true; // If no course found, allow access
        }

        // Check if this is one of the first two lessons in the course (always unlocked)
        if ($this->wbcom_is_first_two_lessons($lesson_id, $course_id)) {
            return true;
        }

        $meta_key = $this->wbcom_get_course_meta_key($course_id);

        if (!$meta_key) {
            return true; // If no specific meta key, allow access
        }

        // Get the group leader's ID
        $group_leader_id = $this->wbcom_get_group_leader_id($user_id);

        // Check if current user is the group leader - if so, bypass lock
        if ($user_id == $group_leader_id) {
            return true; // Group leaders have access to all lessons
        }

        // Check the group leader's sprint start date
        $sprint_start_date = get_user_meta($group_leader_id, $meta_key, true);

        // If no sprint start date is set, allow access (unlock all lessons)
        if (empty($sprint_start_date)) {
            return true; // No start date set, allow access
        }

        // Get lesson delay (default to 0 if not set)
        $lesson_delay = get_post_meta($lesson_id, '_lesson_delay_days', true);
        $lesson_delay = !empty($lesson_delay) ? intval($lesson_delay) : 0;

        // Calculate the unlock date (sprint start + lesson delay)
        $unlock_timestamp = strtotime($sprint_start_date . ' + ' . $lesson_delay . ' days');
        $current_timestamp = current_time('timestamp');

        return $current_timestamp >= $unlock_timestamp;
    }

    /**
     * Check if lesson is one of the first two lessons in a course
     */
    private function wbcom_is_first_two_lessons($lesson_id, $course_id)
    {
        // Get all lessons in the course in order
        $course_lessons = learndash_course_get_lessons($course_id, $user_id = null, array('num' => 2));

        if (empty($course_lessons)) {
            return false;
        }

        // Check if this lesson is among the first two
        foreach (array_slice($course_lessons, 0, 2) as $lesson) {
            if ($lesson->ID == $lesson_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check sprint access before showing lesson content
     */
    public function wbcom_check_sprint_access_before_content($content, $post)
    {
        // Double-check if system is enabled (in case filter changes during runtime)
        if (!$this->is_lesson_unlock_enabled()) {
            return $content;
        }

        // Only apply to lessons
        if (get_post_type($post) !== 'sfwd-lessons') {
            return $content;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return $content;
        }

        // Skip for administrators (unless filter says otherwise)
        if (user_can($user_id, 'manage_options')) {
            /**
             * Filter to control if lesson unlock applies to administrators
             * 
             * @param bool $skip_admins Whether to skip unlock checks for administrators. Default true.
             */
            $skip_admins = apply_filters('freedomology_lesson_unlock_skip_admins', true);
            if ($skip_admins) {
                return $content;
            }
        }

        // Get the course ID for this lesson
        $course_id = learndash_get_course_id($post->ID);

        if (!$course_id) {
            return $content;
        }

        /**
         * Filter to control lesson unlock on a per-course basis
         * 
         * @param bool $enabled Whether lesson unlock is enabled for this course. Default true.
         * @param int $course_id The course ID.
         * @param int $lesson_id The lesson ID.
         */
        $course_unlock_enabled = apply_filters('freedomology_lesson_unlock_enabled_for_course', true, $course_id, $post->ID);
        if (!$course_unlock_enabled) {
            return $content;
        }

        // Check if lesson is unlocked
        if (!$this->wbcom_is_lesson_unlocked($user_id, $post->ID)) {
            $course_title = get_the_title($course_id);
            $lesson_title = get_the_title($post->ID);

            // Get the group leader's sprint start date and lesson delay for display
            $group_leader_id = $this->wbcom_get_group_leader_id($user_id);
            $meta_key = $this->wbcom_get_course_meta_key($course_id);
            $sprint_start_date = get_user_meta($group_leader_id, $meta_key, true);
            
            // This should rarely happen now since empty sprint_start_date unlocks lessons
            // But keeping the check for edge cases
            if (empty($sprint_start_date)) {
                return $content; // Should be unlocked, so show content
            }
            
            $lesson_delay = get_post_meta($post->ID, '_lesson_delay_days', true);
            $lesson_delay = !empty($lesson_delay) ? intval($lesson_delay) : 0;

            // Calculate unlock date
            $unlock_timestamp = strtotime($sprint_start_date . ' + ' . $lesson_delay . ' days');
            $formatted_unlock_date = date('F j, Y', $unlock_timestamp);

            // Calculate days remaining
            $current_timestamp = current_time('timestamp');
            $days_remaining = max(0, ceil(($unlock_timestamp - $current_timestamp) / DAY_IN_SECONDS));

            $message = '
                <div class="wbcom-lesson-locked-notice">
                <div class="lesson-lock-icon">🔒</div>
                <h3 class="lesson-lock-heading">Lesson Locked</h3>
                <p class="lesson-lock-text">
                    <strong>' . esc_html($lesson_title) . '</strong> from the <strong>' . esc_html($course_title) . '</strong> sprint
                </p>';

            if ($lesson_delay > 0) {
                $message .= '
                <div style="background: rgba(75, 215, 211, 0.2); border-radius: 6px; padding: 12px; margin: 15px 0;">
                    <p style="color: #856404; margin: 0; font-size: 14px;">
                        📅 This lesson unlocks <strong>' . $lesson_delay . ' day' . ($lesson_delay > 1 ? 's' : '') . '</strong> after your sprint starts
                    </p>
                </div>';
            }

            $message .= '
                <div class="lesson-lock-message">
                    <p style="color: #856404; margin: 0 0 8px 0; font-size: 18px; font-weight: bold;">
                        🗓️ Unlocks: ' . esc_html($formatted_unlock_date) . '
                    </p>';

            if ($days_remaining > 0) {
                $message .= '
                    <div class="lesson-lock-date">
                        <span class="lesson-lock-date-day">' . $days_remaining . '</span>
                        <span  class="lesson-lock-date-text">day' . ($days_remaining > 1 ? 's' : '') . ' remaining</span>
                    </div>';
            } else {
                $message .= '
                    <p style="color: #28a745; margin: 8px 0 0 0; font-size: 14px; font-weight: bold;">
                        ✅ Should be available now - please refresh the page!
                    </p>';
            }

            $message .= '
                </div>
                <p style="color: #856404; margin: 10px 0 0 0; font-size: 14px;">
                    Come back on <strong>' . esc_html($formatted_unlock_date) . '</strong> to access this lesson!
                </p>
            </div>';

            return $message;
        }

        return $content;
    }

    /**
     * Add lesson delay meta box
     */
    public function wbcom_add_lesson_delay_meta_box()
    {
        // Only show meta box if system is enabled
        if (!$this->is_lesson_unlock_enabled()) {
            return;
        }

        add_meta_box(
            'lesson-delay-meta-box',
            __('Lesson Unlock Delay', 'freedomology'),
            array($this, 'wbcom_render_lesson_delay_meta_box'),
            'sfwd-lessons',
            'side',
            'high'
        );
    }

    /**
     * Render lesson delay meta box
     */
    public function wbcom_render_lesson_delay_meta_box($post)
    {
        wp_nonce_field('lesson_delay_meta_nonce', 'lesson_delay_meta_nonce');

        $lesson_delay = get_post_meta($post->ID, '_lesson_delay_days', true);
        $lesson_delay = !empty($lesson_delay) ? intval($lesson_delay) : 0;

        // Check if this is one of the first two lessons
        $course_id = learndash_get_course_id($post->ID);
        $is_first_two = $this->wbcom_is_first_two_lessons($post->ID, $course_id);

?>
        <div class="wbcom-lesson-delay-container">
            <?php if ($is_first_two): ?>
                <div class="wbcom-first-lesson-notice">
                    <p><strong>✅ This is one of the first two lessons and will always be unlocked immediately.</strong></p>
                </div>
            <?php endif; ?>

            <div class="wbcom-delay-field">
                <label for="lesson_delay_days" class="wbcom-delay-label">
                    <?php _e('Days to wait after sprint starts:', 'freedomology'); ?>
                </label>

                <select name="lesson_delay_days" id="lesson_delay_days" class="wbcom-delay-select" <?php echo $is_first_two ? 'disabled' : ''; ?>>
                    <?php for ($i = 0; $i <= 40; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($lesson_delay, $i); ?>>
                            <?php
                            if ($i == 0) {
                                echo __('Available immediately (0 days)', 'freedomology');
                            } else {
                                echo sprintf(__('%d day%s after sprint starts', 'freedomology'), $i, $i > 1 ? 's' : '');
                            }
                            ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <?php if ($is_first_two): ?>
                    <input type="hidden" name="lesson_delay_days" value="0" />
                <?php endif; ?>

                <p class="wbcom-delay-description">
                    <?php if ($is_first_two): ?>
                        <?php _e('First two lessons cannot have delays and are always available immediately.', 'freedomology'); ?>
                    <?php else: ?>
                        <?php _e('This lesson will unlock the specified number of days after the user\'s sprint start date.', 'freedomology'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php
    }

    /**
     * Save lesson delay meta
     */
    public function wbcom_save_lesson_delay_meta($post_id)
    {
        if (!isset($_POST['lesson_delay_meta_nonce']) || !wp_verify_nonce($_POST['lesson_delay_meta_nonce'], 'lesson_delay_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if this is one of the first two lessons (force to 0 if it is)
        $course_id = learndash_get_course_id($post_id);
        $is_first_two = $this->wbcom_is_first_two_lessons($post_id, $course_id);

        if ($is_first_two) {
            $lesson_delay = 0;
        } else {
            $lesson_delay = isset($_POST['lesson_delay_days']) ? intval($_POST['lesson_delay_days']) : 0;
            $lesson_delay = max(0, min(40, $lesson_delay)); // Ensure it's between 0-40
        }

        update_post_meta($post_id, '_lesson_delay_days', $lesson_delay);
    }

    /**
     * Enqueue admin styles
     */
    public function wbcom_enqueue_admin_styles($hook)
    {
        global $post_type;

        if ($hook == 'post.php' && $post_type == 'sfwd-lessons') {
            wp_add_inline_style('wp-admin', '
                /* Lesson Unlock Meta Box Styles */
                .wbcom-lesson-delay-container {
                    padding: 0;
                }
                
                .wbcom-first-lesson-notice {
                    background: #d1ecf1;
                    border: 1px solid #bee5eb;
                    border-radius: 4px;
                    padding: 12px;
                    margin-bottom: 16px;
                }
                
                .wbcom-first-lesson-notice p {
                    margin: 0;
                    color: #0c5460;
                    font-size: 13px;
                    line-height: 1.4;
                }
                
                .wbcom-delay-field {
                    padding: 0;
                }
                
                .wbcom-delay-label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #23282d;
                    font-size: 13px;
                }
                
                .wbcom-delay-select {
                    width: 100%;
                    padding: 6px 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 13px;
                    line-height: 1.4;
                    background-color: #fff;
                    margin-bottom: 8px;
                }
                
                .wbcom-delay-select:disabled {
                    background-color: #f7f7f7;
                    color: #666;
                    cursor: not-allowed;
                }
                
                .wbcom-delay-select:focus {
                    border-color: #007cba;
                    box-shadow: 0 0 0 1px #007cba;
                    outline: none;
                }
                
                .wbcom-delay-description {
                    margin: 0;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #666;
                    font-style: italic;
                }
                
                /* Meta box specific adjustments */
                #lesson-delay-meta-box .inside {
                    margin: 0;
                    padding: 12px;
                }
                
                #lesson-delay-meta-box .handlediv {
                    background: none;
                }
                
                /* Responsive adjustments */
                @media (max-width: 782px) {
                    .wbcom-delay-select {
                        padding: 8px;
                        font-size: 16px;
                    }
                    
                    .wbcom-delay-label {
                        font-size: 14px;
                    }
                }
            ');
        }
    }

    /**
     * Modify lesson navigation items to add locked class and calendar icons
     */
    public function wbcom_modify_lesson_navigation_items($nav_items, $course_id, $user_id)
    {
        if (empty($nav_items) || !$user_id) {
            return $nav_items;
        }

        // Skip for administrators unless filter says otherwise
        if (user_can($user_id, 'manage_options')) {
            $skip_admins = apply_filters('freedomology_lesson_unlock_skip_admins', true);
            if ($skip_admins) {
                return $nav_items;
            }
        }

        foreach ($nav_items as $key => $nav_item) {
            // Only process lessons
            if (isset($nav_item['post']->post_type) && $nav_item['post']->post_type === 'sfwd-lessons') {
                $lesson_id = $nav_item['post']->ID;

                // Check if lesson is locked
                if (!$this->wbcom_is_lesson_unlocked($user_id, $lesson_id)) {
                    // Add locked class to the navigation item
                    if (!isset($nav_items[$key]['classes'])) {
                        $nav_items[$key]['classes'] = array();
                    }
                    $nav_items[$key]['classes'][] = 'wbcom-lesson-locked';
                    $nav_items[$key]['classes'][] = 'ld-status-locked';

                    // Modify the status to show locked
                    $nav_items[$key]['status'] = 'locked';

                    // Add unlock date info for tooltip
                    $group_leader_id = $this->wbcom_get_group_leader_id($user_id);
                    $meta_key = $this->wbcom_get_course_meta_key($course_id);
                    $sprint_start_date = get_user_meta($group_leader_id, $meta_key, true);
                    $lesson_delay = get_post_meta($lesson_id, '_lesson_delay_days', true);
                    $lesson_delay = !empty($lesson_delay) ? intval($lesson_delay) : 0;

                    if (!empty($sprint_start_date)) {
                        $unlock_timestamp = strtotime($sprint_start_date . ' + ' . $lesson_delay . ' days');
                        $formatted_unlock_date = date('F j, Y', $unlock_timestamp);
                        $nav_items[$key]['unlock_date'] = $formatted_unlock_date;

                        // Calculate days remaining
                        $current_timestamp = current_time('timestamp');
                        $days_remaining = max(0, ceil(($unlock_timestamp - $current_timestamp) / DAY_IN_SECONDS));
                        $nav_items[$key]['days_remaining'] = $days_remaining;
                    }
                }
            }
        }

        return $nav_items;
    }

    /**
     * Add navigation styles for locked lessons
     */
    public function wbcom_add_navigation_styles()
    {
        if (!$this->is_lesson_unlock_enabled()) {
            return;
        }

    ?>
        <style type="text/css">
            /* Locked lesson navigation styles - Visual indicator only */
            .ld-lesson-item.wbcom-lesson-locked {
                position: relative;
            }

            /* Replace status icon with calendar for locked lessons */
            .ld-lesson-item.wbcom-lesson-locked .ld-status-icon {
                background: none !important;
                border: none !important;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .ld-lesson-item.wbcom-lesson-locked .ld-status-icon:before {
                content: "📅";
                font-size: 18px;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #AEE0DE;
                border-radius: 50%;
                border: 2px solid #4BD7D3;
            }

            /* Alternative CSS-only calendar icon (if emoji not preferred) */
            .ld-lesson-item.wbcom-lesson-locked.use-css-icon .ld-status-icon:before {
                content: "";
                background-color: #4BD7D3;
                background-image:
                    linear-gradient(to bottom, transparent 0px, transparent 4px, #fff 4px, #fff 6px, transparent 6px),
                    linear-gradient(to right, transparent 0px, transparent 6px, #fff 6px, #fff 8px, transparent 8px, transparent 10px, #fff 10px, #fff 12px, transparent 12px, transparent 14px, #fff 14px, #fff 16px, transparent 16px, transparent 18px, #fff 18px, #fff 20px, transparent 20px),
                    linear-gradient(to bottom, transparent 8px, #fff 8px, #fff 10px, transparent 10px, transparent 12px, #fff 12px, #fff 14px, transparent 14px, transparent 16px, #fff 16px, #fff 18px, transparent 18px);
                border: 2px solid #AEE0DE;
                border-radius: 4px;
                width: 20px;
                height: 20px;
            }

            /* Focus mode specific styles */
            .learndash-wrapper .ld-focus .ld-lesson-item.wbcom-lesson-locked {
                border-left: 3px solid #4BD7D3;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .ld-lesson-item.wbcom-lesson-locked .ld-status-icon:before {
                    font-size: 16px;
                    width: 20px;
                    height: 20px;
                }
            }
        </style>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Function to mark locked lessons in navigation
                function markLockedLessons() {
                    // Get all lesson items in navigation
                    const lessonItems = document.querySelectorAll('.ld-lesson-item');

                    lessonItems.forEach(function(lessonItem) {
                        const lessonLink = lessonItem.querySelector('a[href*="/lessons/"]');
                        if (!lessonLink) return;

                        // Extract lesson URL to check if it's locked
                        const lessonUrl = lessonLink.getAttribute('href');

                        // Check if this lesson should be locked using AJAX
                        checkLessonLockStatus(lessonUrl, lessonItem);
                    });
                }

                // Function to check lesson lock status via AJAX
                function checkLessonLockStatus(lessonUrl, lessonItem) {
                    // Extract lesson slug from URL
                    const urlParts = lessonUrl.split('/lessons/');
                    if (urlParts.length < 2) return;

                    const lessonSlug = urlParts[1].replace('/', '');

                    // Make AJAX request to check lock status
                    fetch(wbcom_lesson_unlock.ajax_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'wbcom_check_lesson_lock_status',
                                lesson_slug: lessonSlug,
                                nonce: wbcom_lesson_unlock.nonce
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data.is_locked) {
                                // Mark lesson as locked - visual indicator only
                                lessonItem.classList.add('wbcom-lesson-locked');
                            }
                        })
                        .catch(error => {
                            console.log('Error checking lesson lock status:', error);
                        });
                }

                // Initialize on page load
                markLockedLessons();

                // Re-run when navigation is updated (for AJAX-loaded content)
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            setTimeout(markLockedLessons, 100);
                        }
                    });
                });

                const navigationContainer = document.querySelector('.ld-course-navigation');
                if (navigationContainer) {
                    observer.observe(navigationContainer, {
                        childList: true,
                        subtree: true
                    });
                }
            });
        </script>
<?php
    }

    /**
     * Add body classes for navigation styling context
     */
    public function wbcom_add_body_classes_for_navigation($classes)
    {
        if (!$this->is_lesson_unlock_enabled()) {
            return $classes;
        }

        if (is_singular(array('sfwd-courses', 'sfwd-lessons', 'sfwd-topic'))) {
            $classes[] = 'wbcom-lesson-unlock-active';
        }

        return $classes;
    }

    /**
     * Enqueue navigation scripts with localized data
     */
    public function wbcom_enqueue_navigation_scripts()
    {
        if (!$this->is_lesson_unlock_enabled()) {
            return;
        }

        if (is_singular(array('sfwd-courses', 'sfwd-lessons', 'sfwd-topic'))) {
            wp_localize_script('jquery', 'wbcom_lesson_unlock', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wbcom_lesson_unlock_nonce')
            ));
        }
    }

    /**
     * AJAX handler to check lesson lock status
     */
    public function wbcom_ajax_check_lesson_lock_status()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wbcom_lesson_unlock_nonce')) {
            wp_die('Security check failed');
        }

        $lesson_slug = sanitize_text_field($_POST['lesson_slug']);
        $user_id = get_current_user_id();

        if (!$user_id || empty($lesson_slug)) {
            wp_send_json_error('Invalid request');
            return;
        }

        // Get lesson by slug
        $lesson = get_page_by_path($lesson_slug, OBJECT, 'sfwd-lessons');

        if (!$lesson) {
            wp_send_json_error('Lesson not found');
            return;
        }

        $lesson_id = $lesson->ID;

        // Check if lesson is locked
        $is_locked = !$this->wbcom_is_lesson_unlocked($user_id, $lesson_id);

        $response_data = array(
            'is_locked' => $is_locked
        );

        wp_send_json_success($response_data);
    }
}
