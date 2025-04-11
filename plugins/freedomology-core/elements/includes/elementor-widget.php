<?php
/**
 * LearnDash Group Invitation URL Elementor Widget
 */

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit;
}

/**
 * Elementor widget for LearnDash Group Invitation URL
 */
class LDGIU_Elementor_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name.
     *
     * @return string Widget name.
     */
    public function get_name() {
        return "ldgiu_invitation_url";
    }

    /**
     * Get widget title.
     *
     * @return string Widget title.
     */
    public function get_title() {
        return __("Group Invitation URL", "ldgiu");
    }

    /**
     * Get widget icon.
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return "eicon-link";
    }

    /**
     * Get widget categories.
     *
     * @return array Widget categories.
     */
    public function get_categories() {
        return ["general"];
    }

    /**
     * Register widget controls.
     */
    protected function register_controls() {
        $this->start_controls_section(
            "section_content",
            [
                "label" => __("Content", "ldgiu"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            "dropdown_label",
            [
                "label" => __("Dropdown Label", "ldgiu"),
                "type" => \Elementor\Controls_Manager::TEXT,
                "default" => __("Select Group:", "ldgiu"),
            ]
        );

        $this->add_control(
            "copy_button_text",
            [
                "label" => __("Copy Button Text", "ldgiu"),
                "type" => \Elementor\Controls_Manager::TEXT,
                "default" => __("Copy", "ldgiu"),
            ]
        );

        $this->add_control(
            "copied_text",
            [
                "label" => __("Copied Feedback Text", "ldgiu"),
                "type" => \Elementor\Controls_Manager::TEXT,
                "default" => __("Copied!", "ldgiu"),
            ]
        );

        $this->end_controls_section();

        // Style Section for Dropdown
        $this->start_controls_section(
            "section_dropdown_style",
            [
                "label" => __("Dropdown Style", "ldgiu"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                "name" => "dropdown_typography",
                "selector" => "{{WRAPPER}} .ldgiu-group-select",
            ]
        );

        $this->add_control(
            "dropdown_padding",
            [
                "label" => __("Padding", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "em", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-group-select" => "padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->add_control(
            "dropdown_border_radius",
            [
                "label" => __("Border Radius", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-group-select" => "border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section for URL Input
        $this->start_controls_section(
            "section_url_style",
            [
                "label" => __("URL Input Style", "ldgiu"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                "name" => "url_typography",
                "selector" => "{{WRAPPER}} .ldgiu-invitation-url",
            ]
        );

        $this->add_control(
            "url_padding",
            [
                "label" => __("Padding", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "em", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-invitation-url" => "padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->add_control(
            "url_border_radius",
            [
                "label" => __("Border Radius", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-invitation-url" => "border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section for Copy Button
        $this->start_controls_section(
            "section_button_style",
            [
                "label" => __("Copy Button Style", "ldgiu"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                "name" => "button_typography",
                "selector" => "{{WRAPPER}} .ldgiu-copy-button",
            ]
        );

        $this->add_control(
            "button_text_color",
            [
                "label" => __("Text Color", "ldgiu"),
                "type" => \Elementor\Controls_Manager::COLOR,
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-copy-button" => "color: {{VALUE}};",
                ],
            ]
        );

        $this->add_control(
            "button_background_color",
            [
                "label" => __("Background Color", "ldgiu"),
                "type" => \Elementor\Controls_Manager::COLOR,
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-copy-button" => "background-color: {{VALUE}};",
                ],
            ]
        );

        $this->add_control(
            "button_padding",
            [
                "label" => __("Padding", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "em", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-copy-button" => "padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->add_control(
            "button_border_radius",
            [
                "label" => __("Border Radius", "ldgiu"),
                "type" => \Elementor\Controls_Manager::DIMENSIONS,
                "size_units" => ["px", "%"],
                "selectors" => [
                    "{{WRAPPER}} .ldgiu-copy-button" => "border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        if (!class_exists("LearnDash_Group_Invitation_URL")) {
            echo "<p>" . esc_html__("Group Invitation URL plugin is not properly configured.", "ldgiu") . "</p>";
            return;
        }
        
        $plugin_instance = LearnDash_Group_Invitation_URL::get_instance();
        
        if (!method_exists($plugin_instance, "render_invitation_url")) {
            echo "<p>" . esc_html__("Group Invitation URL plugin is not properly configured.", "ldgiu") . "</p>";
            return;
        }
        
        echo $plugin_instance->render_invitation_url( $settings );
    }
}
