<?php
class Freedomology_BuddyBoss_Hooks {

	public function __construct() {
		add_action( 'bp_init', array( $this, 'disable_activation_email' ) );
		add_action( 'bp_init', array( $this, 'render_social_login_in_popup' ) );
	}

	public function disable_activation_email() {
		remove_action( 'bp_core_signup_send_validation_email', 'bp_core_signup_send_validation_email' );
	}

	public function render_social_login_in_popup() {
		add_action( 'reign_login_form_top', function () {
			if ( class_exists( 'BB_SSO' ) && method_exists( 'BB_SSO', 'render_buttons_with_container' ) ) {
				echo BB_SSO::render_buttons_with_container(
					array(
						'label_type' => 'login',
						'style'      => 'default',
					)
				);
			}
		}, 5 );
	}
}
