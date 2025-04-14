<?php
/**
 * LearnDash Group Invitation URL Elementor Widget
 *
 * @package Freedomology
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget for LearnDash Group Invitation URL
 */
class Freedomology_LDGIU_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 */
	public function get_name() {
		return 'freedomology_ldgiu_invitation_url';
	}

	/**
	 * Get widget title.
	 */
	public function get_title() {
		return esc_html__( 'Group Invitation URL', 'freedomology' );
	}

	/**
	 * Get widget icon.
	 */
	public function get_icon() {
		return 'eicon-link';
	}

	/**
	 * Get widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Content', 'freedomology' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'dropdown_label',
			[
				'label'   => esc_html__( 'Dropdown Label', 'freedomology' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Select Group:', 'freedomology' ),
			]
		);

		$this->add_control(
			'copy_button_text',
			[
				'label'   => esc_html__( 'Copy Button Text', 'freedomology' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Copy', 'freedomology' ),
			]
		);

		$this->add_control(
			'copied_text',
			[
				'label'   => esc_html__( 'Copied Feedback Text', 'freedomology' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Copied!', 'freedomology' ),
			]
		);

		$this->end_controls_section();

		// Optional: You can add Style sections here if needed.
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! class_exists( 'LearnDash_Group_Invitation_URL' ) ) {
			echo '<p>' . esc_html__( 'Group Invitation URL plugin is not properly configured.', 'freedomology' ) . '</p>';
			return;
		}

		$plugin_instance = LearnDash_Group_Invitation_URL::get_instance();

		if ( ! method_exists( $plugin_instance, 'render_invitation_url' ) ) {
			echo '<p>' . esc_html__( 'Group Invitation URL plugin is not properly configured.', 'freedomology' ) . '</p>';
			return;
		}

		echo wp_kses_post( $plugin_instance->render_invitation_url( $settings ) );
	}
}
