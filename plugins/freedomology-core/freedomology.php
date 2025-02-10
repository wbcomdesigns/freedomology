<?php
/**
 * Plugin Name: Freedomology
 * Plugin URI: https://wbcomdesigns
 * Description: A base skeleton plugin for custom code development.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: freedomology
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Freedomology {

    /**
     * Constructor to initialize the plugin
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        define( 'FREEDOMOLOGY_VERSION', '1.0.0' );
        define( 'FREEDOMOLOGY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'FREEDOMOLOGY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Include files here when needed
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', [ $this, 'custom_init' ] );
    }

    /**
     * Custom initialization function
     */
    public function custom_init() {
        // Custom initialization code goes here
    }
}

// Initialize the plugin
new Freedomology();
