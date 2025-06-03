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

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use uncanny_learndash_groups\SharedFunctions;

class Freedomology
{

	/**
	 * Constructor to initialize the plugin
	 */
	public function __construct()
	{
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants()
	{
		define('FREEDOMOLOGY_VERSION', '1.0.0');
		define('FREEDOMOLOGY_PLUGIN_DIR', plugin_dir_path(__FILE__));
		define('FREEDOMOLOGY_PLUGIN_URL', plugin_dir_url(__FILE__));
	}

	/**
	 * Include required files
	 */
	private function includes()
	{
		include plugin_dir_path(__FILE__) . '/elements/learndash-group-invitation-url.php';
		// Include files here when needed
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks()
	{

		add_action('init', [$this, 'initialize_plugin_features']);
		add_action('wp_enqueue_scripts', [$this, 'wbcom_enqueue_assets']);
		if (class_exists('GFForms')) {
			add_action('gform_after_submission_1', [$this, 'ghl_learning_network_create_group_form_1'], 10, 2);
		}

		//add_action( 'gform_entry_created', [ $this, 'wbcom_zapier_gform_entry_created'], 10, 2 );
		add_action('gform_post_add_entry', [$this, 'wbcom_zapier_gform_entry_created'], 10, 2);
		add_action('ld_added_group_access', [$this, 'ghl_learning_network_ld_added_group_access'], 10, 2);
		add_action('ld_removed_group_access', [$this, 'ghl_learning_network_ld_removed_group_access'], 10, 2);

		add_action('uo_new_group_created', [$this, 'ghl_learning_network_uo_new_group_created'], 10, 2);
		add_action('ld_added_leader_group_access', [$this, 'ghl_learning_network_added_leader_group_access'], 10, 2);
		add_action('ld_removed_leader_group_access', [$this, 'ghl_learning_network_removed_leader_group_access'], 10, 2);
		add_action('ulgm_after_add_invite_form_fields', array($this, 'wbcom_add_invite_form_fields'), 10, 2);
		add_filter('gform_field_validation_2', array($this, 'wbcom_validate_invitation_code'), 10, 4);
		add_filter('gform_field_validation_2_2', array($this, 'wbcom_validate_email_field_add_custom_message'), 10, 4);
		add_action('gform_user_registered', array($this, 'wbcom_cleanup_user_signup'), 10, 4);
		add_action('bp_init', array($this, 'wbcom_disable_activation_email'));
		add_action('wp_head', array($this, 'wbcom_style_invite_with_link'));
		add_action('wp_footer', array($this, 'wbcom_scripts_invite_with_link'));
		add_filter('learndash_focus_mode_comments', array($this, 'wbcom_learndash_enable_comments_focus_mode'), 10, 2);
		add_filter('comments_array', array($this, 'wbcom_filter_comments_for_same_group_users'), -10, 2);
		add_shortcode('signup_course', array($this, 'wbcom_render_signup_course'));
		add_shortcode('sprint_name', array($this, 'wbcom_render_sprint_name'));
		add_filter('body_class', array($this, 'wbcom_manage_body_classes'));
		add_action('add_meta_boxes', array($this, 'wbcom_add_sfwd_courses_meta_boxes'));
		add_action('save_post_sfwd-courses', array($this, 'wbcom_save_sfwd_courses_meta'));

		add_action('learndash-content-tabs-content-after', array($this, 'wbcom_youtube_share_buttons_after_video'), 10, 4);
		add_shortcode('dashboard_enrolled_course', array($this, 'wbcom_render_dashboard_enrolled_course'));
		// add_action( 'elementor/query/user_enrolled_courses', array( $this, 'wbcom_dashboar_course_query_callback' ) );
		add_action('bp_init', array($this, 'wbcom_render_buddyboss_social_login_on_login_popup'));
		add_action('init', array($this, 'wbcom_login_url_add_add_rewrite_rule'));
		add_filter('request', array($this, 'wbcom_login_filter_login_request'));
		add_filter('site_url', array($this, 'wbcom_login_filter_site_url'), 10, 4);
		add_action('template_redirect', array($this, 'wbcom_redirect_home_to_profile'), 10);
		// add_action('template_redirect', array( $this, 'wbcom_ld_auto_redirect_to_first_lesson' ), 10 );
		add_filter('login_redirect',  array($this, 'wbcom_buddyboss_custom_login_redirect'), 99999, 3);
		add_action('gform_after_submission_4', array($this, 'wbcom_after_submission_signup_join_sprint'), 10, 2);
		// add_action('wp_footer', array( $this, 'wbcom_ld_auto_redirect_js' ), 100 );

		add_filter(
			'gform_is_feed_asynchronous',
			function ($is_asynchronous, $feed, $entry, $form) {
				if (! $is_asynchronous || rgar($feed, 'addon_slug') !== 'gravityformsuserregistration') {
					return $is_asynchronous;
				}

				return gf_user_registration()->is_update_feed($feed) ? $is_asynchronous : false;
			},
			10,
			4
		);
	}

	/**
	 * Initialize plugin features
	 *
	 * This function is responsible for setting up custom functionalities
	 * and loading any additional resources required by the plugin.
	 */
	public function initialize_plugin_features()
	{
		// Add custom post types, taxonomies, or other initialization code here.
	}


	public function wbcom_enqueue_assets()
	{
		wp_enqueue_style('freedomology-core', FREEDOMOLOGY_PLUGIN_URL . 'assets/css/freedomology-core-style.css', array(), time(), 'all');
	}

	public function wbcom_buddyboss_custom_login_redirect($redirect_to, $request, $user)
	{

		if (! empty($request) && isset($request)) {
			$redirect_to = $request;
		} else {
			$redirect_to = home_url('/profile-dashboard/');
		}

		return $redirect_to;
	}

	/**
	 * Create a Group Using Gravity Form via Uncanny LearnDash Groups Plugin
	 */

	public function ghl_learning_network_create_group_form_1($entry, $form)
	{

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
		if (is_user_logged_in()) {
			$customer    = wp_get_current_user();
			$customer_id = $customer->ID;
		}

		/* 
		* Get the Course id from the Course Title.
		*/
		if (is_string($course_id)) {
			global $wpdb;
			$sql 	= $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s", $course_id, 'sfwd-courses');
			$course_id 	= $wpdb->get_var($sql);
		}

		$args = array(
			'ulgm_group_leader_first_name' => $first_name,
			'ulgm_group_leader_last_name'  => $last_name,
			'ulgm_group_leader_email'      => $email,
			'ulgm_group_name'              => $group_name,
			'ulgm_group_total_seats'       => ! empty($total_seats) ? $total_seats : 15000,
			'ulgm_group_courses'           => [$course_id],
			'ulgm_group_image'             => $group_image,
			'ulgm_group_customer_id'       => $customer_id,
		);

		$group_id = null;
		if (class_exists('uncanny_learndash_groups\ProcessManualGroup')) {
			add_filter('ulgm_filter_var_is_front_end', function () {
				return 'yes';
			});
			add_filter('pre_user_login', [$this, 'ghl_learning_network_pre_user_login']);
			$group_id = \uncanny_learndash_groups\ProcessManualGroup::process($args, $_POST);
		}

		// MOVED OUTSIDE: Save Sprint Start Date for GROUP LEADER (no override)
		if (! empty($start_date) && ! empty($course_id) && ! empty($customer_id)) {
			$course_specific_meta_key = '';
			switch ($course_id) {
				case 6298: // R40 Relational Sprint
					$course_specific_meta_key = 'sprintr40_start';
					break;
				case 6163: // F40 Financial Sprint
					$course_specific_meta_key = 'sprintf40_start';
					break;
				case 6160: // H40 Health Sprint
					$course_specific_meta_key = 'sprinth40_start';
					break;
			}

			// Save course-specific start date (only if not already set)
			if (! empty($course_specific_meta_key)) {
				$existing_date = get_user_meta($customer_id, $course_specific_meta_key, true);
				if (empty($existing_date)) {
					update_user_meta($customer_id, $course_specific_meta_key, sanitize_text_field($start_date));

					// Also save as global first start date for this course
					$global_option = $course_specific_meta_key . '_global';
					$global_start = get_option($global_option, '');
					if (empty($global_start)) {
						update_option($global_option, sanitize_text_field($start_date));
					}

					// Debug log
					error_log("Freedomology: Set {$course_specific_meta_key} = {$start_date} for user {$customer_id}");
				} else {
					error_log("Freedomology: User {$customer_id} already has {$course_specific_meta_key} = {$existing_date}");
				}
			}
		}

		// Store start date and course as group meta for members who join later
		if (! empty($start_date) && ! empty($group_id)) {
			update_post_meta($group_id, '_sprint_start_date', sanitize_text_field($start_date));
			update_post_meta($group_id, '_sprint_course_id', $course_id);
			error_log("Freedomology: Set group {$group_id} meta - start_date: {$start_date}, course_id: {$course_id}");
		} elseif (empty($group_id)) {
			error_log("Freedomology: Group creation failed - no group_id returned");
		}
	}

	public function ghl_learning_network_pre_user_login($sanitized_user_login)
	{
		return strstr($sanitized_user_login, '@', true);
	}

	/**
	 * Assign tag to the user when the user add group
	 */
	public function ghl_learning_network_ld_added_group_access($user_id, $group_id)
	{
		$group_course_ids = learndash_group_enrolled_courses($group_id);
		if (! empty($group_course_ids)) {
			foreach ($group_course_ids as $course_id) {
				do_action('learndash_update_course_access', $user_id, $course_id, '', false);
			}
		}
	}

	/**
	 * Remove tag to the user when the user remove group
	 */
	public function ghl_learning_network_ld_removed_group_access($user_id, $group_id)
	{
		$group_course_ids = learndash_group_enrolled_courses($group_id);
		if (! empty($group_course_ids)) {
			foreach ($group_course_ids as $course_id) {
				do_action('learndash_update_course_access', $user_id, $course_id, '', true);
			}
		}
	}

	function ghl_learning_network_uo_new_group_created($group_id, $group_leader_id)
	{
		$this->ghl_learning_network_added_leader_group_access($group_leader_id, $group_id);
	}

	// Replace your existing ghl_learning_network_added_leader_group_access function with this:
	public function ghl_learning_network_added_leader_group_access($user_id, $group_id)
	{
		$group_course_ids = learndash_group_enrolled_courses($group_id);
		if (! empty($group_course_ids)) {
			foreach ($group_course_ids as $course_id) {
				// Map course IDs to specific tags
				$tag_name = '';
				switch ($course_id) {
					case 6298: // R40 Relational Sprint
						$tag_name = 'sprint-r40-leader-active';
						break;
					case 6163: // F40 Financial Sprint
						$tag_name = 'sprint-f40-leader-active';
						break;
					case 6160: // H40 Health Sprint
						$tag_name = 'sprint-h40-leader-active';
						break;
					default:
						// Fallback to old format for other courses
						$tag_name = trim(sanitize_text_field(wp_unslash(get_the_title($course_id) . ' Leader')));
						break;
				}

				$tag_id = wpf_get_tag_id($tag_name);
				if ($tag_id == false) {
					$tag_id = wp_fusion()->crm->add_tag($tag_name);
				}
				wp_fusion()->user->apply_tags([$tag_id], $user_id);
			}
		}
	}

	// Replace your existing ghl_learning_network_removed_leader_group_access function with this:
	public function ghl_learning_network_removed_leader_group_access($user_id, $group_id)
	{
		$group_course_ids = learndash_group_enrolled_courses($group_id);
		if (! empty($group_course_ids)) {
			foreach ($group_course_ids as $course_id) {
				// Map course IDs to specific tags
				$tag_name = '';
				switch ($course_id) {
					case 6298: // R40 Relational Sprint
						$tag_name = 'sprint-r40-leader-active';
						break;
					case 6163: // F40 Financial Sprint
						$tag_name = 'sprint-f40-leader-active';
						break;
					case 6160: // H40 Health Sprint
						$tag_name = 'sprint-h40-leader-active';
						break;
					default:
						// Fallback to old format for other courses
						$tag_name = trim(sanitize_text_field(wp_unslash(get_the_title($course_id) . ' Leader')));
						break;
				}

				$tag_id = wpf_get_tag_id($tag_name);
				if ($tag_id != false) {
					wp_fusion()->user->remove_tags([$tag_id], $user_id);
				}
			}
		}
	}
	public function wbcom_add_invite_form_fields($group_id, $object)
	{
		// Generate permanent invite link
		$invite_url = $this->generate_permanent_group_invite_link($group_id);

?>
		<div class="uo-row" id="uo_add_user_invite_url" style="display: none;">
			<label for="wbcom_invite_url">
				<div class="uo-row__title">
					<?php _e('Invite With Link', 'wbcom'); ?>
				</div>
			</label>
			<div class="uo_add_user_invite_url_block">
				<input class="uo-input" type="url" name="wbcom_invite_url" id="wbcom_invite_url" value="<?php echo $invite_url; ?>" readonly />
				<button class="uo-btn" type="button" onclick="copyInviteUrl()">Copy</button>
			</div>
			<span id="copyTooltip" style="visibility: hidden;">URL Copied!</span>
		</div>
		<?php
	}


	/**
	 * Generate a permanent invite link for a LearnDash group
	 * 
	 * @param int $group_id The LearnDash group ID
	 * @return string The permanent invite URL
	 */
	public function generate_permanent_group_invite_link($group_id)
	{
		// Create a unique but permanent hash for this group
		$hash = wp_hash($group_id . get_option('site_secret_key', ''));
		$hash = substr($hash, 0, 12); // Shortened for URL friendliness

		// Get the first course in the group
		$group_course_ids = learndash_group_enrolled_courses($group_id);
		$course_id = !empty($group_course_ids) ? $group_course_ids[0] : 0;

		$invite_url = add_query_arg(
			array(
				'group_id' => $group_id,
				'course_id' => $course_id,
				'code' => $hash,
			),
			home_url('/sign-up/')
		);

		return $invite_url;
	}

	/**
	 * Validate a group invitation key
	 * 
	 * @param int $group_id The LearnDash group ID
	 * @param string $invite_key The invitation key to validate
	 * @return bool True if valid, false otherwise
	 */
	public function wbcom_validate_group_invite_key($group_id, $invite_key)
	{
		$expected_key = substr(wp_hash($group_id . get_option('site_secret_key', '')), 0, 12);
		return $invite_key === $expected_key;
	}

	public function wbcom_validate_email_field_add_custom_message($result, $value, $form, $field)
	{

		if ($result['is_valid'] && email_exists($value)) {
			$result['is_valid'] = false;
			$redirect_url = add_query_arg(
				array(
					'group_id'  => $_GET['group_id'],
					'code'      => $_GET['code'],
					'course_id' => $_GET['course_id'],
				),
				home_url('sign-up')
			);
			$result['message'] = sprintf('The email is already registered. Please login and Join the sprint. To login %s.', '<a href="' . wp_login_url($redirect_url) . '">Click Here</a>');
		}

		return $result;
	}

	public function wbcom_validate_invitation_code($result, $value, $form, $field)
	{

		global $bp;

		static $group_id = null;
		static $code     = null;

		$field_label = $field->label;

		// Store values from hidden fields
		if ($field->get_input_type() === 'hidden') {
			if ($field_label === 'Group ID') {
				$group_id = $value;
				return $result; // Let validation continue
			}

			if ($field_label === 'Code') {
				$code = $value;
			}
		}

		// Only proceed if we have both values
		if (is_null($group_id) || is_null($code)) {
			return $result; // Wait until both fields are processed
		}

		// Check if code is empty or invalid
		if (empty($code) || strtolower($code) === 'no') {
			$result['is_valid'] = false;
			$result['message']  = esc_html__('Registration code is empty or invalid.', 'uncanny-learndash-groups');
			return $result;
		}

		// Validate the invite key
		if ($this->wbcom_validate_group_invite_key($group_id, $code)) {
			$remaining_seats = ulgm()->group_management->seat->remaining_seats($group_id);

			if ($remaining_seats <= 0) {
				$result['is_valid'] = false;
				$result['message']  = esc_html__('No seats available in this group.', 'uncanny-learndash-groups');
			} else {
				$result['is_valid'] = true;
			}
		} else {
			$result['is_valid'] = false;
			$result['message']  = esc_html__('Invalid registration code.', 'uncanny-learndash-groups');
		}

		return $result;
	}



	public function wbcom_cleanup_user_signup($user_id, $feed, $entry, $user_pass)
	{

		$form = GFFormsModel::get_form_meta($entry['form_id']);
		$meta = $feed['meta'];

		if (! $user_pass) {
			$user_pass = gf_user_registration()->get_meta_value('password', $meta, $form, $entry);
		}

		$code     = isset($entry[8]) ? $entry[8] : '';
		$group_id = isset($entry[6]) ? $entry[6] : 0;

		if (empty($code)) {
			return;
		}

		$code = ulgm()->group_management->get_sign_up_code_from_group_id($group_id);

		// Update user meta with the used code
		update_user_meta($user_id, '_ulgm_code_used', $code);

		// Assign user to group
		$result = ulgm()->group_management->set_user_to_code($user_id, $code, SharedFunctions::$not_started_status, $group_id);

		if ($result) {
			SharedFunctions::set_user_to_group($user_id, $group_id);
		}

		// ADD this code after the SharedFunctions::set_user_to_group line:

		// Save Sprint Start Date for GROUP MEMBER (new signup via invite)
		$course_id = get_post_meta($group_id, '_sprint_course_id', true);

		if (empty($course_id)) {
			// Fallback: get course from group enrollment
			$group_course_ids = learndash_group_enrolled_courses($group_id);
			if (! empty($group_course_ids)) {
				$course_id = $group_course_ids[0];
			}
		}

		if (! empty($course_id)) {
			$course_specific_meta_key = '';
			switch ($course_id) {
				case 6298: // R40 Relational Sprint
					$course_specific_meta_key = 'sprintr40_start';
					break;
				case 6163: // F40 Financial Sprint
					$course_specific_meta_key = 'sprintf40_start';
					break;
				case 6160: // H40 Health Sprint
					$course_specific_meta_key = 'sprinth40_start';
					break;
			}

			if (! empty($course_specific_meta_key)) {
				// Get the FIRST start date for this course type
				$global_start_date = get_option($course_specific_meta_key . '_global', '');

				// Set user's start date to the global first start date (no override)
				if (! empty($global_start_date)) {
					$existing_date = get_user_meta($user_id, $course_specific_meta_key, true);
					if (empty($existing_date)) {
						update_user_meta($user_id, $course_specific_meta_key, sanitize_text_field($global_start_date));
					}
				}
			}
		}

		// Get user data
		$user = get_userdata($user_id);

		if ($user) {
			wp_set_auth_cookie($user_id, true);
			wp_set_current_user($user_id);
			do_action('wp_login', $user->user_login, $user);
		}
	}

	function wbcom_disable_activation_email()
	{
		remove_action('bp_core_signup_send_validation_email', 'bp_core_signup_send_validation_email');
	}


	// Automatically activate user signups
	public function wbcom_auto_activate_user($user_login)
	{
		global $wpdb;

		// Get the signup entry using BP_Signup class
		$signup_data = BP_Signup::get(
			array(
				'user_login' => $user_login,
				'active'     => 0, // Only look for inactive users
			)
		);

		// Check if the signup exists
		if (! empty($signup_data['signups'])) {
			$signup = $signup_data['signups'][0]; // Assuming the first result is correct

			// Activate the signup using BuddyPress' activation method
			$activation_result = BP_Signup::activate(array($signup->signup_id));

			if (isset($activation_result['activated']) && ! empty($activation_result['activated'])) {
				$activated_user = $activation_result['activated'][0];

				// Log the successful activation
				error_log('User successfully activated: ' . $activated_user->ID);
			} else {
				error_log('Error activating user: ' . $signup->user_login);
			}
		} else {
			error_log('User signup not found or already activated: ' . $user_login);
		}
	}


	public function wbcom_style_invite_with_link()
	{
		$group_management_page_id      = ulgm()->group_management->pages->get_group_management_page_id();
		if ($group_management_page_id == get_the_ID()) {
		?>
			<style type="text/css">
				.uo_add_user_invite_url_block {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 10px;
				}
			</style>
		<?php
		}
	}


	public function wbcom_scripts_invite_with_link()
	{
		$group_management_page_id      = ulgm()->group_management->pages->get_group_management_page_id();
		if ($group_management_page_id == get_the_ID()) {
		?>
			<script type="text/javascript">
				document.getElementById("send_enrollment").addEventListener("click", function() {
					document.getElementById("uo_add_user_invite_url").style.display = "block";
					document.getElementById("uo_add_user_first_name").style.display = "none";
					document.getElementById("uo_add_user_last_name").style.display = "none";
					document.getElementById("uo_add_user_email").style.display = "none";
				});
				document.getElementById("add_invite").addEventListener("click", function() {
					document.getElementById("uo_add_user_invite_url").style.display = "none";
					document.getElementById("uo_add_user_first_name").style.display = "block";
					document.getElementById("uo_add_user_last_name").style.display = "block";
					document.getElementById("uo_add_user_email").style.display = "block";
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
		<?php
		}
	}

	public function wbcom_learndash_enable_comments_focus_mode($status, $object)
	{
		if ('sfwd-lessons' === $object->post_type || 'sfwd-topic' === $object->post_type) {
			$status = 'open';
		}
		return $status;
	}


	public function wbcom_filter_comments_for_same_group_users($comments, $post_id)
	{
		// Check if the user is logged in
		if (! is_user_logged_in()) {
			return $comments; // Return all comments if the user is not logged in
		}

		// Get the current user object
		$current_user = wp_get_current_user();

		// Check if the user is an administrator
		if (in_array('administrator', (array) $current_user->roles)) {
			return $comments; // Return all comments for admin users
		}

		// Check if the post type is 'sfwd-lessons' or 'sfwd-topic'
		if ('sfwd-lessons' === get_post_type($post_id) || 'sfwd-topic' === get_post_type($post_id)) {
			// Get the groups of the logged-in user
			$login_user_groups = learndash_get_users_group_ids(get_current_user_id(), false);

			// Initialize an array to hold filtered comments
			$filtered_comments = array();

			// Loop through each comment
			foreach ($comments as $comment) {
				// Get the comment author's user ID
				$comment_author = $comment->user_id;

				// Get the groups of the comment author
				$comment_author_groups = learndash_get_users_group_ids($comment_author, false);

				// Check if there is an intersection between the logged-in user's groups and the comment author's groups
				if (! empty(array_intersect($login_user_groups, $comment_author_groups))) {
					// If they share a group, add the comment to the filtered array
					$filtered_comments[] = $comment;
				}
			}

			// Return only the comments from the same group
			return $filtered_comments;
		}

		// Return all comments for other post types
		return $comments;
	}



	public function wbcom_render_signup_course()
	{
		// Get the course ID from the query parameter and sanitize it
		$course_id = isset($_GET['course_id']) ? sanitize_text_field($_GET['course_id']) : '';

		// Start output buffering
		ob_start();

		// Check if the course ID is valid
		if (! empty($course_id) && get_post_type($course_id) === 'sfwd-courses') { // Assuming 'sfwd-courses' is the post type for courses
		?>
			<div class="course-list-freedomology">
				<div class="course-list-img">
					<img src="<?php echo get_the_post_thumbnail_url($course_id); ?>" alt="<?php echo esc_attr(get_the_title($course_id)); ?>" />
				</div>
				<div class="course-list-content">
					<h3 class="course-list-title"><?php echo esc_html(get_the_title($course_id)); ?></h3>
					<div class='course-short-content'><?php echo wp_trim_words(get_post_field('post_content', $course_id), 20); ?></div>
					<div class="learn-more-btn">
						<a href="<?php echo esc_url(get_the_permalink($course_id)); ?>">Learn More</a>
					</div>
				</div>
			</div>
		<?php
		} else {
			// Optionally, you can display a message if the course ID is invalid
			echo '<p>No course found.</p>';
		}

		// Get the contents of the output buffer and clean it
		$output = ob_get_clean(); // Use ob_get_clean() to get the buffer contents and clean it

		return $output;
	}

	public function wbcom_render_sprint_name()
	{
		$group_id = isset($_GET['group_id']) ? sanitize_text_field($_GET['group_id']) : '';
		$return = '';

		if (! empty($group_id)) {
			return get_the_title($group_id);
		}
	}


	public function wbcom_manage_body_classes($classes)
	{
		if (is_page('sign-up')) {
			if (isset($_GET['group_id']) && isset($_GET['course_id'])) {
				$classes[] = sanitize_title(get_the_title($_GET['course_id']));
			}
		} elseif (is_page('profile-dashboard') && is_user_logged_in()) {
			$user_id = get_current_user_id();
			$course_ids = learndash_user_get_enrolled_courses($user_id);

			if ($course_ids) {
				$classes[] = sanitize_title(get_the_title($course_ids[0]));
			}
		} elseif (is_page('courses') && is_user_logged_in()) {
			$user_id = get_current_user_id();
			$course_ids = learndash_user_get_enrolled_courses($user_id);

			if (empty($course_ids)) {
				$classes[] = 'not-enrolled';
			}
		}

		return $classes;
	}

	public function wbcom_zapier_gform_entry_created($entry, $form)
	{
		$form_id = 1; // Replace with your actual form ID		
		if ((int) $entry['form_id'] === $form_id) {
			do_action('gform_after_submission_' . $entry['form_id'], $entry, $form);
		}
	}

	public	function wbcom_youtube_share_buttons_after_video($post_id, $context, $course_id, $user_id)
	{
		if (!in_array($context, ['lesson', 'topic'])) return;

		$sfwd_data = get_post_meta($post_id, '_sfwd-lessons', true);
		if (!is_array($sfwd_data) || empty($sfwd_data['sfwd-lessons_lesson_video_url'])) return;

		$video_url = $sfwd_data['sfwd-lessons_lesson_video_url'];
		if (strpos($video_url, 'youtube.com') === false && strpos($video_url, 'youtu.be') === false) return;

		$encoded_url   = urlencode($video_url);
		$lesson_title  = get_the_title($post_id);
		$encoded_title = urlencode("Check out this lesson: " . $lesson_title);

		?>
		<div class="ld-share-video-modern">
			<h4 class="ld-share-heading">Share this video 🎥</h4>
			<div class="ld-share-input-wrap">
				<input type="text" class="ld-share-url" value="<?php echo esc_url($video_url); ?>" readonly onclick="this.select();" aria-label="Copy video URL" />
				<button onclick="navigator.clipboard.writeText('<?php echo esc_js($video_url); ?>'); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy Link', 2000);" class="ld-share-copy-btn">Copy Link</button>
			</div>
			<div class="ld-share-icons">
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encoded_url; ?>" target="_blank" rel="noopener noreferrer" title="Share on Facebook">Facebook</a>
				<a href="https://twitter.com/intent/tweet?url=<?php echo $encoded_url; ?>&text=<?php echo $encoded_title; ?>" target="_blank" rel="noopener noreferrer" title="Share on Twitter">Twitter</a>
				<a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $encoded_url; ?>&title=<?php echo $encoded_title; ?>" target="_blank" rel="noopener noreferrer" title="Share on LinkedIn">LinkedIn</a>
				<a href="https://api.whatsapp.com/send?text=<?php echo $encoded_title . '%20' . $encoded_url; ?>" target="_blank" rel="noopener noreferrer" title="Share on WhatsApp">WhatsApp</a>
				<a href="mailto:?subject=<?php echo $encoded_title; ?>&body=<?php echo $encoded_url; ?>" title="Share via Email">Email</a>
			</div>
		</div>
	<?php
	}

	/* Course meta boxes */

	public function wbcom_add_sfwd_courses_meta_boxes()
	{
		add_meta_box(
			'sfwd_courses_meta_box',
			'Course Details',
			[$this, 'render_sfwd_courses_meta_box'],
			'sfwd-courses', // Ensure it's the correct post type
			'normal',
			'high'
		);
	}

	public function render_sfwd_courses_meta_box($post)
	{
		$video_link = get_post_meta($post->ID, '_video_link', true);
		$short_description = get_post_meta($post->ID, '_short_description', true);

		wp_nonce_field(basename(__FILE__), 'sfwd_courses_meta_nonce');
	?>
		<p>
			<label for="video_link">Video Link:</label>
			<input type="url" name="video_link" id="video_link" value="<?php echo esc_url($video_link); ?>" class="widefat">
		</p>

	<?php
	}

	public function wbcom_save_sfwd_courses_meta($post_id)
	{
		if (!isset($_POST['sfwd_courses_meta_nonce']) || !wp_verify_nonce($_POST['sfwd_courses_meta_nonce'], basename(__FILE__))) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		if (isset($_POST['video_link'])) {
			update_post_meta($post_id, '_video_link', esc_url_raw($_POST['video_link']));
		}
	}

	public function wbcom_render_dashboard_enrolled_course()
	{
		ob_start(); // Start output buffering

		$user_id = get_current_user_id();
		$course_ids = learndash_user_get_enrolled_courses($user_id);

		if (empty($course_ids)) {
			return '<p>You are not enrolled in any courses.</p>';
		}

		// Get the first enrolled course
		$course_id = $course_ids[0];
		$course_title = get_the_title($course_id);
		$course_permalink = get_permalink($course_id);
		$course_image = get_the_post_thumbnail($course_id, 'full');
		$course_short_desc = get_post_meta($course_id, '_learndash_course_grid_short_description', true);
		$course_video_url = get_post_meta($course_id, '_video_link', true); // Custom field for intro video

		if (strpos($course_video_url, 'youtube.com/watch') !== false) {
			parse_str(parse_url($course_video_url, PHP_URL_QUERY), $query_params);
			if (! empty($query_params['v'])) {
				$course_video_url = 'https://www.youtube.com/embed/' . $query_params['v'];
			}
		}

	?>
		<div class="enrolled-course-wrapper">
			<div class="enrolled-course-accordion">
				<div class="accordion-title">
					<?php echo esc_html($course_title); ?>
				</div>
				<div class="accordion-content">
					<div class="course-card">

						<!-- Course Image as Video Trigger -->
						<div class="course-image">
							<a href="#" class="video-popup-trigger" data-video="<?php echo esc_url($course_video_url); ?>">
								<?php echo $course_image; ?>
								<span class="play-icon"><i class="fa-solid fa-play"></i></span>
							</a>
						</div>

						<!-- Course Info -->
						<div class="course-info">
							<p><?php echo esc_html($course_short_desc); ?></p>
							<a href="<?php echo esc_url($course_permalink); ?>" class="learn-more-btn">Learn More</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Video Popup -->
		<div id="video-popup" class="video-popup-overlay">
			<div class="video-popup-content">
				<span class="close-popup">&times;</span>
				<iframe id="popup-video-frame" src="" frameborder="0" allowfullscreen></iframe>
			</div>
		</div>

		<script type="text/javascript">
			// Video Popup
			const popupOverlay = document.getElementById("video-popup");
			const popupFrame = document.getElementById("popup-video-frame");

			document.querySelectorAll(".video-popup-trigger").forEach((trigger) => {
				trigger.addEventListener("click", function(e) {
					e.preventDefault();
					const videoUrl = this.getAttribute("data-video");
					if (videoUrl.includes("youtube.com/embed/")) {
						document.getElementById("popup-video-frame").src = videoUrl;
						document.getElementById("video-popup").style.display = "flex";
						document.getElementById("video-popup").classList.add("active");
					} else {
						alert("Invalid video URL. Please check the video settings.");
					}
				});
			});

			// Close Popup
			document.querySelector(".close-popup").addEventListener("click", function() {
				popupOverlay.style.display = "none";
				popupFrame.src = "";
				document.getElementById("video-popup").classList.removeClass("active");
			});

			// Close popup when clicking outside
			popupOverlay.addEventListener("click", function(e) {
				if (e.target === popupOverlay) {
					popupOverlay.style.display = "none";
					popupFrame.src = "";
					document.getElementById("video-popup").classList.removeClass("active");
				}
			});
		</script>
		<?php

		return ob_get_clean(); // Return buffered output
	}


	public function wbcom_render_buddyboss_social_login_on_login_popup()
	{
		add_action('reign_login_form_top', function () {
			if (class_exists('BB_SSO') && method_exists('BB_SSO', 'render_buttons_with_container')) {
				echo BB_SSO::render_buttons_with_container([
					'label_type' => 'login',
					'style'      => 'default', // or 'grid', 'icon', etc.
				]);
			}
		}, 5);
	}

	public function wbcom_login_url_add_add_rewrite_rule()
	{
		add_rewrite_rule('^login/?$', 'wp-login.php', 'top');
	}

	public function wbcom_login_filter_login_request($query_vars)
	{
		if (isset($query_vars['pagename']) && $query_vars['pagename'] === 'login') {
			$query_vars = array(); // Allow core login handlers to process it
			$_SERVER['REQUEST_URI'] = '/wp-login.php'; // Trick WordPress into handling it like normal
		}
		return $query_vars;
	}

	public function wbcom_login_filter_site_url($url, $path, $scheme, $blog_id)
	{
		if ($path === 'wp-login.php' || strpos($path, 'wp-login.php') !== false) {
			// Preserve query strings if any (like ?action=register)
			$query_string = parse_url($url, PHP_URL_QUERY);
			$url = site_url('login', $scheme);
			if ($query_string) {
				$url .= '?' . $query_string;
			}
		}
		return $url;
	}

	public function wbcom_redirect_home_to_profile()
	{
		// Check if user is logged in and on homepage
		if (is_user_logged_in() && is_front_page() && ! is_admin() && ! current_user_can('manage_options')) {
			// Redirect to profile page (adjust the URL if needed)
			wp_redirect(site_url('/profile-dashboard'));
			exit;
		}
	}

	public function wbcom_ld_auto_redirect_to_first_lesson()
	{
		if (! is_user_logged_in() || is_admin()) {
			return;
		}

		// Check if we are on a single LearnDash course page
		if (is_singular('sfwd-courses')) {
			global $post;

			$course_id = $post->ID;


			// Get the course steps (lessons, topics, etc.)
			$course_steps = learndash_get_course_steps($course_id);

			// If lessons exist, get the first lesson
			if (! empty($course_steps) && is_array($course_steps)) {
				$first_lesson_id = reset($course_steps);

				// Optional: Check if user has access to the course
				if (sfwd_lms_has_access($course_id, get_current_user_id())) {
					// Redirect to the first lesson URL
					wp_redirect(get_permalink($first_lesson_id));
					exit;
				}
			}
		}
	}


	public function wbcom_ld_auto_redirect_js()
	{
		if (!is_singular('sfwd-courses')) {
			return;
		}

		$course_id = get_the_ID();
		$user_id = get_current_user_id();
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;

		if (is_user_logged_in() && (sfwd_lms_has_access($course_id, $user_id) || in_array('administrator', $user_roles))) {
			$lesson_list = learndash_get_course_lessons_list($course_id);

			if (!empty($lesson_list)) {
				$first_lesson = reset($lesson_list);

				if (isset($first_lesson['post']->ID)) {
					$first_lesson_id = $first_lesson['post']->ID;
					$first_lesson_url = get_permalink($first_lesson_id);
		?>
					<script type="text/javascript">
						document.addEventListener('DOMContentLoaded', function() {
							window.location.href = '<?php echo esc_url($first_lesson_url); ?>';
						});
					</script>
<?php
				}
			}
		}
	}

	public function wbcom_after_submission_signup_join_sprint($entry, $form)
	{
		$user_id  = get_current_user_id();
		$group_id = rgar($entry, '6'); // Make sure field ID '2' is correct for Group ID

		// Bail early if no user or group ID
		if (empty($user_id) || empty($group_id)) {
			return;
		}

		// Get signup code for the group
		$code = ulgm()->group_management->get_sign_up_code_from_group_id($group_id);

		if (empty($code)) {
			return; // Bail if no code found for the group
		}

		// Save the code used during signup
		update_user_meta($user_id, '_ulgm_code_used', $code);

		// Try to set the user to the group using the code
		$result = ulgm()->group_management->set_user_to_code(
			$user_id,
			$code,
			SharedFunctions::$not_started_status,
			$group_id
		);

		// If successful, associate user with group
		if ($result) {
			SharedFunctions::set_user_to_group($user_id, $group_id);
		}

		// ADD this code after the SharedFunctions::set_user_to_group line:

		// Save Sprint Start Date for GROUP MEMBER (existing user joining)
		$course_id = get_post_meta($group_id, '_sprint_course_id', true);

		if (empty($course_id)) {
			// Fallback: get course from group enrollment
			$group_course_ids = learndash_group_enrolled_courses($group_id);
			if (! empty($group_course_ids)) {
				$course_id = $group_course_ids[0];
			}
		}

		if (! empty($course_id)) {
			$course_specific_meta_key = '';
			switch ($course_id) {
				case 6298: // R40 Relational Sprint
					$course_specific_meta_key = 'sprintr40_start';
					break;
				case 6163: // F40 Financial Sprint
					$course_specific_meta_key = 'sprintf40_start';
					break;
				case 6160: // H40 Health Sprint
					$course_specific_meta_key = 'sprinth40_start';
					break;
			}

			if (! empty($course_specific_meta_key)) {
				// Get the FIRST start date for this course type
				$global_start_date = get_option($course_specific_meta_key . '_global', '');

				// Set user's start date to the global first start date (no override)
				if (! empty($global_start_date)) {
					$existing_date = get_user_meta($user_id, $course_specific_meta_key, true);
					if (empty($existing_date)) {
						update_user_meta($user_id, $course_specific_meta_key, sanitize_text_field($global_start_date));
					}
				}
			}
		}
	}
}

// Initialize the plugin
new Freedomology();
