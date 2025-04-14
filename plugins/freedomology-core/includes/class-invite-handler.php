<?php
class Freedomology_Invite_Handler {

	public function __construct() {
		add_action( 'ulgm_after_add_invite_form_fields', array( $this, 'add_invite_form_fields' ), 10, 2 );
		add_action( 'gform_user_registered', array( $this, 'handle_invite_registration' ), 9, 3 );
	}

	public function generate_invite_link( $group_id ) {
		$hash = substr( wp_hash( $group_id . get_option( 'site_secret_key', '' ) ), 0, 12 );
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		$course_id = ! empty( $group_course_ids ) ? $group_course_ids[0] : 0;
		return add_query_arg(
			array(
				'group_id'   => $group_id,
				'course_id'  => $course_id,
				'invite_key' => $hash,
			),
			home_url( '/sign-up/' )
		);
	}

	public function validate_invite_key( $group_id, $invite_key ) {
		$expected = substr( wp_hash( $group_id . get_option( 'site_secret_key', '' ) ), 0, 12 );
		return $invite_key === $expected;
	}

	public function process_invite_signup( $user_id, $group_id, $invite_key ) {
		if ( ! $this->validate_invite_key( $group_id, $invite_key ) ) {
			return false;
		}
		$remaining = ulgm()->group_management->seat->remaining_seats( $group_id );
		if ( $remaining <= 0 ) {
			return false;
		}
		ld_update_group_access( $user_id, $group_id, true );
		update_user_meta( $user_id, '_joined_via_group_invite', $group_id );
		return true;
	}

	public function handle_invite_registration( $user_id, $feed, $entry ) {
		if ( isset( $_GET['group_id'], $_GET['invite_key'] ) ) {
			$this->process_invite_signup( $user_id, intval( $_GET['group_id'] ), sanitize_text_field( $_GET['invite_key'] ) );
		}
	}

	public function add_invite_form_fields( $group_id, $object ) {
		$invite_url = $this->generate_invite_link( $group_id );
		echo '<div class="uo-row" id="uo_add_user_invite_url" style="display:none;">
			<label><div class="uo-row__title">' . esc_html__( 'Invite With Link', 'wbcom' ) . '</div></label>
			<div class="uo_add_user_invite_url_block">
				<input class="uo-input" type="url" id="wbcom_invite_url" value="' . esc_url( $invite_url ) . '" readonly />
				<button class="uo-btn" type="button" onclick="copyInviteUrl()">Copy</button>
			</div>
			<span id="copyTooltip" style="visibility:hidden;">URL Copied!</span>
		</div>';
	}
}
