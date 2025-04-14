<?php
/**
 * Plugin Name: Freedomology
 * Plugin URI: https://wbcomdesigns.com
 * Description: A base skeleton plugin for custom development (LearnDash, GravityForms, BuddyBoss).
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: freedomology
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Define Constants
 */
define( 'FREEDOMOLOGY_VERSION', '1.0.0' );
define( 'FREEDOMOLOGY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FREEDOMOLOGY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload Includes
 */
foreach ( glob( FREEDOMOLOGY_PLUGIN_DIR . 'includes/*.php' ) as $file ) {
	require_once $file;
}

/**
 * Initialize all functionality
 */
function freedomology_init_plugin() {
	new Freedomology_Invite_Handler();
	new Freedomology_GravityForms_Handler();
	new Freedomology_LearnDash_Hooks();
	new Freedomology_BuddyBoss_Hooks();
	new Freedomology_Shortcodes();
	new Freedomology_Login_Redirect();
	new Freedomology_UI_Hooks();
}
add_action( 'plugins_loaded', 'freedomology_init_plugin' );
