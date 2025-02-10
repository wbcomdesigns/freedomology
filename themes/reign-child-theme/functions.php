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
add_action('wp_enqueue_scripts', 'child_enqueue_styles', 99);

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