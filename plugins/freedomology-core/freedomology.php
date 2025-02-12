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
        add_action( 'init', [ $this, 'initialize_plugin_features' ] );

        if ( class_exists('GFForms') && class_exists('uncanny_learndash_groups\ProcessManualGroup') ) {
            add_action('gform_after_submission_1', [ $this, 'ghl_learning_network_create_group_form_1' ], 10, 2);
        }
		
		add_action( 'ld_added_group_access', [ $this, 'ghl_learning_network_ld_added_group_access' ], 10, 2 );
		add_action( 'ld_removed_group_access', [ $this, 'ghl_learning_network_ld_removed_group_access' ], 10, 2 );
    }

    /**
     * Initialize plugin features
     *
     * This function is responsible for setting up custom functionalities
     * and loading any additional resources required by the plugin.
     */
    public function initialize_plugin_features() {
        // Add custom post types, taxonomies, or other initialization code here.
    }

    /**
     * Create a Group Using Gravity Form via Uncanny LearnDash Groups Plugin
     */
    public function ghl_learning_network_create_group_form_1($entry, $form) {
        $first_name   = rgar($entry, '1');
        $last_name    = rgar($entry, '3');
        $email        = rgar($entry, '4');
        $phone_number = rgar($entry, '5');
        $course_id    = rgar($entry, '7');
        $start_date   = rgar($entry, '8');
        $total_seats  = rgar($entry, '11');
        $group_name   = rgar($entry, '9');
        $group_image  = '';

        $customer_id = '';
        if ( is_user_logged_in() ) {
            $customer    = wp_get_current_user();
            $customer_id = $customer->ID;
        }

        $args = array(
            'ulgm_group_leader_first_name' => $first_name,
            'ulgm_group_leader_last_name'  => $last_name,
            'ulgm_group_leader_email'      => $email,
            'ulgm_group_name'              => $group_name,
            'ulgm_group_total_seats'       => $total_seats,
            'ulgm_group_courses'           => [$course_id],
            'ulgm_group_image'             => $group_image,
            'ulgm_group_customer_id'       => $customer_id,
        );

        add_filter('ulgm_filter_var_is_front_end', function(){ return 'yes'; });
        $group_id = \uncanny_learndash_groups\ProcessManualGroup::process( $args, $_POST );
    }
	
	/**
     * Assign tag to the user when the user add group
     */
	public function ghl_learning_network_ld_added_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', false );
			}		
		}
	}
	
	/**
     * Remove tag to the user when the user remove group
     */
	public function ghl_learning_network_ld_removed_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', true );
			}		
		}
	}
}

// Initialize the plugin
new Freedomology();
