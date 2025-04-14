<?php
class Freedomology_Login_Redirect {

	public function __construct() {
		add_action( 'init', array( $this, 'add_login_rewrite_rule' ) );
		add_filter( 'request', array( $this, 'filter_login_request' ) );
		add_filter( 'site_url', array( $this, 'filter_login_url' ), 10, 4 );
		add_action( 'template_redirect', array( $this, 'redirect_home_to_profile' ) );
	}

	public function add_login_rewrite_rule() {
		add_rewrite_rule( '^login/?$', 'wp-login.php', 'top' );
	}

	public function filter_login_request( $query_vars ) {
		if ( isset( $query_vars['pagename'] ) && $query_vars['pagename'] === 'login' ) {
			$query_vars = array();
			$_SERVER['REQUEST_URI'] = '/wp-login.php';
		}
		return $query_vars;
	}

	public function filter_login_url( $url, $path, $scheme, $blog_id ) {
		if ( strpos( $path, 'wp-login.php' ) !== false ) {
			$url = site_url( 'login', $scheme );
		}
		return $url;
	}

	public function redirect_home_to_profile() {
		if ( is_user_logged_in() && is_front_page() && ! current_user_can( 'manage_options' ) ) {
			wp_redirect( site_url( '/profile-dashboard' ) );
			exit;
		}
	}
}
