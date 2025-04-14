<?php
// Freedomology Elementor Widgets Loader
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	// Load custom widget file
	require_once FREEDOMOLOGY_PLUGIN_DIR . 'includes/elementor/elementor-widget.php';

	// Register the widget
	$widgets_manager->register( new \Freedomology_Elementor_Widget() );
});
