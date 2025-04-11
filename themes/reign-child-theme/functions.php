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

function child_enqueue_styles() {
    $parent_style = 'parent-style';
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', array($parent_style));
}

if (get_stylesheet() !== get_template()) {
    add_filter('pre_update_option_theme_mods_' . get_stylesheet(), function ( $value, $old_value ) {
        global $pagenow;
        if ($pagenow != 'themes.php') {
            update_option('theme_mods_' . get_template(), $value);
        }
        return $value; // prevent update to child theme mods
    }, 10, 2);
}

// update fonts on kirki customizer  

add_filter( 'kirki_fonts_standard_fonts', function( $standard_fonts ) {
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
} );



function wbcom_get_filtered_comment_count( $post_id ) {
    // Check if the user is logged in
    if ( ! is_user_logged_in() ) {
        return 0; // Return 0 if the user is not logged in
    }

    // Get the current user object
    $current_user = wp_get_current_user();

    // Check if the user is an admin
    if ( in_array( 'administrator', (array) $current_user->roles ) ) {
        // Return the total comment count for admins
        return wp_count_comments( $post_id )->approved; // Count only approved comments
    }

    // Check if the post type is 'sfwd-lessons' or 'sfwd-topic'
    if ( 'sfwd-lessons' === get_post_type( $post_id ) || 'sfwd-topic' === get_post_type( $post_id ) ) {
        // Get the groups of the logged-in user
        $login_user_groups = learndash_get_users_group_ids( get_current_user_id(), false );

        // Get all comments for the post
        $comments = get_comments( array( 'post_id' => $post_id ) );

        // Initialize a counter for filtered comments
        $filtered_comment_count = 0;

        // Loop through each comment
        foreach ( $comments as $comment ) {
            // Get the comment author's groups
            $comment_author_groups = learndash_get_users_group_ids( $comment->user_id, false );

            // Check if there is an intersection between the logged-in user's groups and the comment author's groups
            if ( ! empty( array_intersect( $login_user_groups, $comment_author_groups ) ) ) {
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


function add_enrolled_course_class($classes) {
    // Check if LearnDash plugin is active
    if (!function_exists('learndash_user_get_enrolled_courses')) {
        return $classes; // Agar LearnDash active nahi hai to kuch mat karo
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
