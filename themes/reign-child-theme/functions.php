<?php

/**
 * Reign Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Reign-child
 *
 * @since 1.0.0
 */
/**
 * Define Constants
 */
define('REIGN_CHILD_THEME_VERSION', '1.0.0');

/**
 * Enqueue styles.
 */
add_action('wp_enqueue_scripts', 'child_enqueue_styles', 5002);

function child_enqueue_styles()
{
    $parent_style = 'parent-style';
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', array($parent_style));
}

if (get_stylesheet() !== get_template()) {
    add_filter('pre_update_option_theme_mods_' . get_stylesheet(), function ($value, $old_value) {
        global $pagenow;
        if ($pagenow != 'themes.php') {
            update_option('theme_mods_' . get_template(), $value);
        }
        return $value; // prevent update to child theme mods
    }, 10, 2);
}

// update fonts on kirki customizer  

add_filter('kirki_fonts_standard_fonts', function ($standard_fonts) {
    $standard_fonts['arial'] = [
        'label' => 'Arial',
        'stack' => 'Arial, Helvetica, sans-serif',
    ];

    $standard_fonts['times_new_roman'] = [
        'label' => 'Times New Roman',
        'stack' => '"Times New Roman", Times, serif',
    ];

    $standard_fonts['helvetica'] = [
        'label' => 'Helvetica',
        'stack' => 'Helvetica, sans-serif',
    ];

    return $standard_fonts;
});


/**
 * Add this to your child theme's functions.php
 * Safe LearnDash Group Activity Filter - uses only existing functions from your plugin
 */

/**
 * Get comma-separated string of group member IDs for current user
 * Optimized for single lesson pages - filters by current lesson's course
 */
function wbcom_get_group_member_ids()
{
    if (!is_user_logged_in()) {
        return ''; // Return empty for fallback to all activity
    }

        if (!is_user_logged_in()) {
        return '';
    }

    // SAFETY: Don't run in main BuddyBoss activity pages
    if (function_exists('bp_is_activity_component') && bp_is_activity_component()) {
        return ''; // Don't filter main activity feed
    }

    // SAFETY: Only run on LearnDash lesson pages
    if (!is_singular(['sfwd-lessons', 'sfwd-topic'])) {
        return '';
    }

    $current_user_id = get_current_user_id();

    // Use the same function already used in your plugin's wbcom_filter_comments_for_same_group_users
    $user_groups = learndash_get_users_group_ids($current_user_id, false);

    if (empty($user_groups)) {
        return ''; // Return empty for fallback to all activity
    }

    // Since this is used on lesson pages, get the current lesson's course
    $current_course_id = learndash_get_course_id(get_the_ID());

    // If user is in multiple groups, filter by current lesson's course
    if (count($user_groups) > 1 && $current_course_id) {
        $relevant_groups = array();

        foreach ($user_groups as $group_id) {
            // Use the same function already used in your plugin multiple times
            $group_courses = learndash_group_enrolled_courses($group_id);
            if (in_array($current_course_id, $group_courses)) {
                $relevant_groups[] = $group_id;
            }
        }

        // If we found groups for this course, use those; otherwise use all groups
        if (!empty($relevant_groups)) {
            $user_groups = $relevant_groups;
        }

        // If still multiple groups, just use the first one
        if (count($user_groups) > 1) {
            $user_groups = array($user_groups[0]);
        }
    }

    $all_member_ids = array();

    foreach ($user_groups as $group_id) {
        // Use the same function already used in your plugin multiple times
        $group_member_ids = learndash_get_groups_user_ids($group_id);
        if (!empty($group_member_ids)) {
            $all_member_ids = array_merge($all_member_ids, $group_member_ids);
        }
    }

    // Remove duplicates and ensure current user is included
    $all_member_ids = array_unique($all_member_ids);
    if (!in_array($current_user_id, $all_member_ids)) {
        $all_member_ids[] = $current_user_id;
    }

    return implode(',', $all_member_ids);
}

function wbcom_get_filtered_comment_count($post_id)
{
    // Check if the user is logged in
    if (! is_user_logged_in()) {
        return 0; // Return 0 if the user is not logged in
    }

    // Get the current user object
    $current_user = wp_get_current_user();

    // Check if the user is an admin
    if (in_array('administrator', (array) $current_user->roles)) {
        // Return the total comment count for admins
        return wp_count_comments($post_id)->approved; // Count only approved comments
    }

    // Check if the post type is 'sfwd-lessons' or 'sfwd-topic'
    if ('sfwd-lessons' === get_post_type($post_id) || 'sfwd-topic' === get_post_type($post_id)) {
        // Get the groups of the logged-in user
        $login_user_groups = learndash_get_users_group_ids(get_current_user_id(), false);

        // Get all comments for the post
        $comments = get_comments(array('post_id' => $post_id));

        // Initialize a counter for filtered comments
        $filtered_comment_count = 0;

        // Loop through each comment
        foreach ($comments as $comment) {
            // Get the comment author's groups
            $comment_author_groups = learndash_get_users_group_ids($comment->user_id, false);

            // Check if there is an intersection between the logged-in user's groups and the comment author's groups
            if (! empty(array_intersect($login_user_groups, $comment_author_groups))) {
                // Increment the counter if they share a group
                $filtered_comment_count++;
            }
        }

        // Return the count of filtered comments
        return $filtered_comment_count;
    }

    // Return 0 for other post types
    return 0;
}


function add_enrolled_course_class($classes)
{
    // Check if LearnDash plugin is active
    if (!function_exists('learndash_user_get_enrolled_courses')) {
        return $classes;
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $courses = learndash_user_get_enrolled_courses($user_id);

        if (!empty($courses)) {
            $classes[] = 'user-enrolled';
        }
    }
    return $classes;
}
add_filter('body_class', 'add_enrolled_course_class');


function add_custom_body_class_on_checkemail($classes)
{
    if (isset($_GET['checkemail']) && $_GET['checkemail'] === 'confirm') {
        $classes[] = 'login-split-page';
    }
    return $classes;
}
add_filter('login_body_class', 'add_custom_body_class_on_checkemail');


function redirect_logged_in_users_from_home()
{
    // Only do this for logged-in users
    if (is_user_logged_in() && is_front_page()) {
        // Change this to the actual URL or slug of your profile dashboard page
        $dashboard_url = site_url('/news-feed/');

        // Perform the redirection
        wp_redirect($dashboard_url);
        exit;
    }
}
add_action('template_redirect', 'redirect_logged_in_users_from_home');



/* Single lesson on commnet or lesson tab on mobile view */
add_action('learndash-focus-content-content-after', 'learndash_focus_content_lesson_content_after', 10, 2);
function learndash_focus_content_lesson_content_after($course_id, $user_id)
{

?>

    <div class="ld-lesson-tabs-wrapper mobile-view-comment-lesson">

        <!-- Tab Navigation -->
        <ul class="ld-lesson-tabs-nav">
            <li class="active" data-tab="newsfeed-tab">Newsfeed</li>
            <li data-tab="comments-tab">Comments</li>
            <li data-tab="lesson-tab">Lesson</li>
        </ul>

        <!-- Tab Content -->
        <div class="ld-lesson-tabs-content">

            <!-- Newsfeed Tab -->
            <div id="newsfeed-tab" class="ld-tab-content active">
                <?php
                $group_member_ids = wbcom_get_group_member_ids();

                if (!empty($group_member_ids)) {
                    // Show only group member activities
                    echo do_shortcode('[activity-listing title="Recent Activities" per_page="5" max="5" load_more="no" allow_posting="yes" user_id="' . $group_member_ids . '"]');
                } else {
                    // Fallback: Show all activities
                    echo do_shortcode('[activity-listing title="Recent Activities" per_page="5" max="5" load_more="no" allow_posting="yes"]');
                }
                ?>
                <div class="view-all-activity-btn">
                    <a class="button btn " href="<?php echo esc_url(site_url('/news-feed/')); ?>">
                        <?php echo esc_html('View All Activity'); ?>
                    </a>
                </div>
            </div>


            <!-- Comments Tab -->
            <div id="comments-tab" class="ld-tab-content ">

                <?php
                learndash_get_template_part(
                    'focus/comments.php',
                    array(
                        'course_id' => $course_id,
                        'user_id'   => $user_id,
                        'context'   => 'focus',
                    ),
                    true
                );
                ?>

            </div>

            <!-- Lesson Tab -->
            <div id="lesson-tab" class="ld-tab-content">
                <?php
                learndash_get_template_part(
                    'focus/sidebar.php',
                    array(
                        'course_id' => $course_id,
                        'user_id'   => $user_id,
                        'context'   => 'focus',
                    ),
                    true
                );
                ?>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.ld-lesson-tabs-nav li');
            const contents = document.querySelectorAll('.ld-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));

                    this.classList.add('active');
                    document.getElementById(this.dataset.tab).classList.add('active');
                });
            });
        });
    </script>

<?php
}
