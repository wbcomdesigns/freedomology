<?php
/**
 * LearnDash Group Invitation URL WordPress Widget for Freedomology Plugin
 * 
 * File: plugins/freedomology-core/elements/wordpress-widget.php
 */

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit;
}

/**
 * WordPress widget for LearnDash Group Invitation URL
 */
class LDGIU_WordPress_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'ldgiu_invitation_url_widget',
            __('Group Invitation URL', 'freedomology'),
            array(
                'description' => __('Display LearnDash Group Invitation URL with dropdown selection.', 'freedomology'),
                'classname' => 'ldgiu-invitation-url-widget'
            )
        );
    }

    /**
     * Widget output on frontend
     */
    public function widget($args, $instance) {
        // Check if plugin class exists
        if (!class_exists("LearnDash_Group_Invitation_URL")) {
            echo $args['before_widget'];
            echo "<p>" . esc_html__("Group Invitation URL plugin is not properly configured.", "freedomology") . "</p>";
            echo $args['after_widget'];
            return;
        }

        $plugin_instance = LearnDash_Group_Invitation_URL::get_instance();
        
        if (!method_exists($plugin_instance, "render_invitation_url")) {
            echo $args['before_widget'];
            echo "<p>" . esc_html__("Group Invitation URL plugin is not properly configured.", "freedomology") . "</p>";
            echo $args['after_widget'];
            return;
        }

        // Prepare settings array exactly like Elementor widget
        $settings = array(
            'dropdown_label' => !empty($instance['dropdown_label']) ? $instance['dropdown_label'] : __('Select Group:', 'freedomology'),
            'copy_button_text' => !empty($instance['copy_button_text']) ? $instance['copy_button_text'] : __('Share Sprint Link', 'freedomology'),
            'copied_text' => !empty($instance['copied_text']) ? $instance['copied_text'] : __('Copied!', 'freedomology')
        );

        // Output widget
        echo $args['before_widget'];
        
        // Display widget title if set
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        // Add wrapper div to match your existing CSS exactly like Elementor
        echo '<div class="elementor-widget-ldgiu_invitation_url">';
        
        // Render the invitation URL interface with settings
        echo $plugin_instance->render_invitation_url($settings);
        
        echo '</div>';
        
        echo $args['after_widget'];
    }

    /**
     * Widget settings form in admin
     */
    public function form($instance) {
        // Set default values to match Elementor widget defaults
        $title = !empty($instance['title']) ? $instance['title'] : __('Group Invitation', 'freedomology');
        $dropdown_label = !empty($instance['dropdown_label']) ? $instance['dropdown_label'] : __('Select Group:', 'freedomology');
        $copy_button_text = !empty($instance['copy_button_text']) ? $instance['copy_button_text'] : __('Share Sprint Link', 'freedomology');
        $copied_text = !empty($instance['copied_text']) ? $instance['copied_text'] : __('Copied!', 'freedomology');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_attr_e('Widget Title:', 'freedomology'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Enter widget title (optional)', 'freedomology'); ?>">
        </p>
        
        <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('dropdown_label')); ?>"><?php esc_attr_e('Dropdown Label:', 'freedomology'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('dropdown_label')); ?>" name="<?php echo esc_attr($this->get_field_name('dropdown_label')); ?>" type="text" value="<?php echo esc_attr($dropdown_label); ?>" placeholder="<?php esc_attr_e('Select Group:', 'freedomology'); ?>">
            <small class="description"><?php esc_html_e('Label text shown above the group selection dropdown.', 'freedomology'); ?></small>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('copy_button_text')); ?>"><?php esc_attr_e('Copy Button Text:', 'freedomology'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('copy_button_text')); ?>" name="<?php echo esc_attr($this->get_field_name('copy_button_text')); ?>" type="text" value="<?php echo esc_attr($copy_button_text); ?>" placeholder="<?php esc_attr_e('Share Sprint Link', 'freedomology'); ?>">
            <small class="description"><?php esc_html_e('Text displayed on the copy button.', 'freedomology'); ?></small>
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('copied_text')); ?>"><?php esc_attr_e('Copied Feedback Text:', 'freedomology'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('copied_text')); ?>" name="<?php echo esc_attr($this->get_field_name('copied_text')); ?>" type="text" value="<?php echo esc_attr($copied_text); ?>" placeholder="<?php esc_attr_e('Copied!', 'freedomology'); ?>">
            <small class="description"><?php esc_html_e('Text shown briefly when the URL is copied to clipboard.', 'freedomology'); ?></small>
        </p>
        
        <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
        
        <p class="description" style="font-style: italic; color: #666;">
            <strong><?php esc_html_e('Note:', 'freedomology'); ?></strong> 
            <?php esc_html_e('This widget displays a dropdown to select groups and generates invitation URLs. Only logged-in users who are group leaders or members will see their available groups. The widget uses your existing CSS styling for consistent appearance.', 'freedomology'); ?>
        </p>
        
        <p class="description" style="font-style: italic; color: #666;">
            <strong><?php esc_html_e('Functionality:', 'freedomology'); ?></strong>
            <br>• <?php esc_html_e('Automatically detects user groups', 'freedomology'); ?>
            <br>• <?php esc_html_e('Generates unique invitation URLs', 'freedomology'); ?>
            <br>• <?php esc_html_e('One-click copy to clipboard', 'freedomology'); ?>
            <br>• <?php esc_html_e('Matches your Elementor widget styling', 'freedomology'); ?>
        </p>
        <?php
    }

    /**
     * Save widget settings
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['dropdown_label'] = (!empty($new_instance['dropdown_label'])) ? sanitize_text_field($new_instance['dropdown_label']) : '';
        $instance['copy_button_text'] = (!empty($new_instance['copy_button_text'])) ? sanitize_text_field($new_instance['copy_button_text']) : '';
        $instance['copied_text'] = (!empty($new_instance['copied_text'])) ? sanitize_text_field($new_instance['copied_text']) : '';

        return $instance;
    }
}

/**
 * Register the widget
 */
function freedomology_register_ldgiu_widget() {
    register_widget('LDGIU_WordPress_Widget');
}
add_action('widgets_init', 'freedomology_register_ldgiu_widget');
?>