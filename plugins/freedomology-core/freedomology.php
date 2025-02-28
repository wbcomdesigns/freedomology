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

use uncanny_learndash_groups\SharedFunctions;

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
		add_action( 'init', array( $this, 'initialize_plugin_features' ) );
		if ( class_exists( 'GFForms' ) ) {
			add_action( 'gform_after_submission_1', array( $this, 'ghl_learning_network_create_group_form_1' ), 10, 2 );
		}

		add_action( 'ld_added_group_access', array( $this, 'ghl_learning_network_ld_added_group_access' ), 10, 2 );
		add_action( 'ld_removed_group_access', array( $this, 'ghl_learning_network_ld_removed_group_access' ), 10, 2 );

		add_action( 'uo_new_group_created', array( $this, 'ghl_learning_network_uo_new_group_created' ), 10, 2 );
		add_action( 'ld_added_leader_group_access', array( $this, 'ghl_learning_network_added_leader_group_access' ), 10, 2 );
		add_action( 'ld_removed_leader_group_access', array( $this, 'ghl_learning_network_removed_leader_group_access' ), 10, 2 );
		add_filter( 'pre_user_login', array( $this, 'ghl_learning_network_pre_user_login' ) );
		add_action( 'ulgm_after_add_invite_form_fields', array( $this, 'wbcom_add_invite_form_fields' ), 10, 2 );
		add_action( 'bp_signup_validate', array( $this, 'wbcom_validate_invitation_code' ) );
		add_action( 'bp_core_signup_user', array( $this, 'wpcom_cleanup_user_signup' ), 999, 5 );
		add_action( 'bp_init', array( $this, 'wbcom_disable_activation_email' ) );
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
	public function ghl_learning_network_create_group_form_1( $entry, $form ) {

		$first_name   = rgar( $entry, '1' );
		$last_name    = rgar( $entry, '3' );
		$email        = rgar( $entry, '4' );
		$phone_number = rgar( $entry, '5' );
		$course_id    = rgar( $entry, '7' );
		$start_date   = rgar( $entry, '8' );
		$total_seats  = rgar( $entry, '11' );
		$group_name   = rgar( $entry, '9' );
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
			'ulgm_group_courses'           => array( $course_id ),
			'ulgm_group_image'             => $group_image,
			'ulgm_group_customer_id'       => $customer_id,
		);
		if ( class_exists( 'uncanny_learndash_groups\ProcessManualGroup' ) ) {
			add_filter(
				'ulgm_filter_var_is_front_end',
				function() {
					return 'yes';
				}
			);

			$group_id = \uncanny_learndash_groups\ProcessManualGroup::process( $args, $_POST );
		}
	}

	public function ghl_learning_network_pre_user_login( $sanitized_user_login ) {
		return strstr( $sanitized_user_login, '@', true );
	}

	/**
	 * Assign tag to the user when the user add group
	 */
	public function ghl_learning_network_ld_added_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if ( ! empty( $group_course_ids ) ) {
			foreach ( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', false );
			}
		}
	}

	/**
	 * Remove tag to the user when the user remove group
	 */
	public function ghl_learning_network_ld_removed_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if ( ! empty( $group_course_ids ) ) {
			foreach ( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', true );
			}
		}
	}

	function ghl_learning_network_uo_new_group_created( $group_id, $group_leader_id ) {
		$this->ghl_learning_network_added_leader_group_access( $group_leader_id, $group_id );
	}
	public function ghl_learning_network_added_leader_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if ( ! empty( $group_course_ids ) ) {
			foreach ( $group_course_ids as $course_id ) {
				$tag_name = trim( sanitize_text_field( wp_unslash( get_the_title( $course_id ) . ' Leader' ) ) );
				$tag_id   = wpf_get_tag_id( $tag_name );
				if ( $tag_id == false ) {
					$tag_id = wp_fusion()->crm->add_tag( $tag_name );
				}
				wp_fusion()->user->apply_tags( array( $tag_id ), $user_id );
			}
		}
	}

	public function ghl_learning_network_removed_leader_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if ( ! empty( $group_course_ids ) ) {
			foreach ( $group_course_ids as $course_id ) {
				$tag_name = trim( sanitize_text_field( wp_unslash( get_the_title( $course_id ) . ' Leader' ) ) );
				$tag_id   = wpf_get_tag_id( $tag_name );
				if ( $tag_id != false ) {
					wp_fusion()->user->remove_tags( array( $tag_id ), $user_id );
				}
			}
		}
	}

	public function wbcom_add_invite_form_fields( $group_id, $object ) {
		$code       = ulgm()->group_management->get_sign_up_code_from_group_id( $group_id );
		$invite_url = add_query_arg(
			array(
				'group_id' => $group_id,
				'code'     => $code,
			),
			wp_registration_url()
		);

		?>
	<div class="uo-row" id="uo_add_user_invite_url" style="display: none;">
		<label for="wbcom_invite_url">
			<div class="uo-row__title">
				<?php _e( 'Invite With Link', 'wbcom' ); ?>
			</div>
		</label>
		<input class="uo-input" type="url" name="wbcom_invite_url" id="wbcom_invite_url" value="<?php echo $invite_url; ?>" readonly />
		<button class="uo-btn" type="button" onclick="copyInviteUrl()">Copy</button>
		<span id="copyTooltip" style="visibility: hidden;">URL Copied!</span>
		<script>
			document.getElementById("send_enrollment").addEventListener("click", function() {
				document.getElementById("uo_add_user_invite_url").style.display = "block";
			});
			document.getElementById("add_invite").addEventListener("click", function() {
				document.getElementById("uo_add_user_invite_url").style.display = "none";
			});
			function copyInviteUrl() {
				var copyText = document.getElementById("wbcom_invite_url");
				copyText.select();
				copyText.setSelectionRange(0, 99999); // For mobile devices
				document.execCommand("copy");

				var tooltip = document.getElementById("copyTooltip");
				tooltip.style.visibility = "visible";
				setTimeout(function() {
					tooltip.style.visibility = "hidden";
				}, 2000);
			}
		</script>
	</div>
		<?php
	}

	public function wbcom_validate_invitation_code() {
		global $bp;
		$code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';

		if ( '' === $code && 'no' === $code ) {
			$bp->signup->errors['signup_username'] = esc_html__( 'Registration code is empty', 'uncanny-learndash-groups' );
		}

		$code_details = SharedFunctions::is_key_available( $code );

		if ( 'failed' === $code_details['result'] ) {
			if ( 'invalid' === $code_details['error'] ) {
				$bp->signup->errors['signup_username'] = esc_html__( 'Invalid registration code', 'uncanny-learndash-groups' );
			} elseif ( 'existing' === $code_details['error'] ) {
				$bp->signup->errors['signup_username'] = esc_html__( 'Code already redeemed', 'uncanny-learndash-groups' );
			} elseif ( 'seat_not_available' === $code_details['error'] ) {
				$bp->signup->errors['signup_username'] = esc_html__( 'Seat not available', 'uncanny-learndash-groups' );
			}
		}
	}


	public function wpcom_cleanup_user_signup( $user_id, $user_login, $user_password, $user_email, $usermeta ) {
		$code     = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		$group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;

		if ( '' === $code ) {
			return;
		}

		$this->wbcom_auto_activate_user( $user_login );

		update_user_meta( $user_id, '_ulgm_code_used', $code );

		$result = ulgm()->group_management->set_user_to_code( $user_id, $code, SharedFunctions::$not_started_status, $group_id );
		if ( $result ) {
			SharedFunctions::set_user_to_group( $user_id, $group_id );
		}

		wp_set_auth_cookie( $user_id );
		wp_set_current_user( $user_id, $user_login );

		$redirect_url = bp_core_get_user_domain( $user_id );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	function wbcom_disable_activation_email() {
		remove_action( 'bp_core_signup_send_validation_email', 'bp_core_signup_send_validation_email' );
	}


	// Automatically activate user signups
	public function wbcom_auto_activate_user( $user_login ) {
		global $wpdb;

		// Get the signup entry using BP_Signup class
		$signup_data = BP_Signup::get(
			array(
				'user_login' => $user_login,
				'active'     => 0, // Only look for inactive users
			)
		);

		// Check if the signup exists
		if ( ! empty( $signup_data['signups'] ) ) {
			$signup = $signup_data['signups'][0]; // Assuming the first result is correct

			// Activate the signup using BuddyPress' activation method
			$activation_result = BP_Signup::activate( array( $signup->signup_id ) );

			if ( isset( $activation_result['activated'] ) && ! empty( $activation_result['activated'] ) ) {
				$activated_user = $activation_result['activated'][0];

				// Log the successful activation
				error_log( 'User successfully activated: ' . $activated_user->ID );
			} else {
				error_log( 'Error activating user: ' . $signup->user_login );
			}
		} else {
			error_log( 'User signup not found or already activated: ' . $user_login );
		}
	}
}

// Initialize the plugin
new Freedomology();
