<?php
class Freedomology_GravityForms_Handler {

	public function __construct() {
		add_action( 'gform_after_submission_1', array( $this, 'create_group_from_form' ), 10, 2 );
		add_action( 'gform_post_add_entry', array( $this, 'zapier_gform_entry_created' ), 10, 2 );
		add_filter( 'gform_field_validation', array( $this, 'validate_invitation_code' ), 10, 4 );
		add_action( 'gform_user_registered', array( $this, 'cleanup_user_signup' ), 10, 4 );
	}

	public function create_group_from_form( $entry, $form ) {
		$first_name  = rgar( $entry, '1' );
		$last_name   = rgar( $entry, '3' );
		$email       = rgar( $entry, '4' );
		$group_name  = rgar( $entry, '9' );
		$course_id   = rgar( $entry, '7' );
		$total_seats = rgar( $entry, '11' );
		$customer_id = is_user_logged_in() ? get_current_user_id() : '';

		if ( is_string( $course_id ) ) {
			global $wpdb;
			$sql       = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s", $course_id, 'sfwd-courses' );
			$course_id = $wpdb->get_var( $sql );
		}

		$args = array(
			'ulgm_group_leader_first_name' => $first_name,
			'ulgm_group_leader_last_name'  => $last_name,
			'ulgm_group_leader_email'      => $email,
			'ulgm_group_name'              => $group_name,
			'ulgm_group_total_seats'       => ! empty( $total_seats ) ? $total_seats : 15000,
			'ulgm_group_courses'           => array( $course_id ),
			'ulgm_group_customer_id'       => $customer_id,
		);

		if ( class_exists( 'uncanny_learndash_groups\\ProcessManualGroup' ) ) {
			add_filter( 'ulgm_filter_var_is_front_end', '__return_yes' );
			\\uncanny_learndash_groups\\ProcessManualGroup::process( $args, $_POST );
		}
	}

	public function validate_invitation_code( $result, $value, $form, $field ) {
		if ( isset( $_GET['invite_key'], $_GET['group_id'] ) ) {
			$invite_key = sanitize_text_field( $_GET['invite_key'] );
			$group_id   = intval( $_GET['group_id'] );

			$invite_handler = new Freedomology_Invite_Handler();
			if ( $invite_handler->validate_invite_key( $group_id, $invite_key ) ) {
				$remaining_seats = ulgm()->group_management->seat->remaining_seats( $group_id );
				if ( $remaining_seats <= 0 ) {
					$result['is_valid'] = false;
					$result['message']  = esc_html__( 'No seats available in this group.', 'uncanny-learndash-groups' );
				} else {
					$result['is_valid'] = true;
				}
			}
		}
		return $result;
	}

	public function cleanup_user_signup( $user_id, $feed, $entry, $user_pass ) {
		$user = get_userdata( $user_id );
		if ( $user && ! $user_pass ) {
			$user_pass = gf_user_registration()->get_meta_value( 'password', $feed['meta'], GFFormsModel::get_form_meta( $entry['form_id'] ), $entry );
		}
		wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $user_pass,
			'remember'      => true,
		) );
	}

	public function zapier_gform_entry_created( $entry, $form ) {
		if ( (int) $entry['form_id'] === 1 ) {
			do_action( 'gform_after_submission_1', $entry, $form );
		}
	}
}
