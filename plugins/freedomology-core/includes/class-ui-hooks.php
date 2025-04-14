<?php
class Freedomology_UI_Hooks {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'add_invite_link_styles' ) );
		add_action( 'wp_footer', array( $this, 'add_invite_link_scripts' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'freedomology-core', FREEDOMOLOGY_PLUGIN_URL . 'assets/css/freedomology-core-style.css', array(), time(), 'all' );
	}

	public function add_invite_link_styles() {
		if ( function_exists( 'ulgm' ) && get_the_ID() === ulgm()->group_management->pages->get_group_management_page_id() ) {
			echo '<style>
				.uo_add_user_invite_url_block { display: flex; gap: 10px; align-items: center; }
			</style>';
		}
	}

	public function add_invite_link_scripts() {
		if ( function_exists( 'ulgm' ) && get_the_ID() === ulgm()->group_management->pages->get_group_management_page_id() ) {
			echo '<script>
				function copyInviteUrl() {
					var copyText = document.getElementById(\"wbcom_invite_url\");
					copyText.select();
					copyText.setSelectionRange(0, 99999);
					document.execCommand(\"copy\");
					document.getElementById(\"copyTooltip\").style.visibility = \"visible\";
					setTimeout(function() { document.getElementById(\"copyTooltip\").style.visibility = \"hidden\"; }, 2000);
				}
			</script>';
		}
	}
}
