<?php
/**
 * Main Plugin Class
 */
class LearnDash_Group_Invitation_URL {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin path
     */
    private $plugin_path;

    /**
     * Plugin URL
     */
    private $plugin_url;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Check if required plugins are active
        add_action('admin_init', array($this, 'check_dependencies'));
        
        // Register shortcode
        add_shortcode('ldgiu_invite', array($this, 'invitation_url_shortcode'));
        
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
    }

    /**
     * Check if required plugins are active
     */
    public function check_dependencies() {
        if (!class_exists('SFWD_LMS') || !did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            deactivate_plugins(plugin_basename(__FILE__));
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    /**
     * Display admin notice for missing dependencies
     */
    public function dependency_notice() {
        $message = __('LearnDash Group Invitation URL requires both LearnDash LMS and Elementor to be installed and activated.', 'ldgiu');
        echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Render invitation URLs with dropdown selection for groups.
     * 
     * @param array $settings Settings array for customization
     * @return string HTML output with dropdown selection and copyable invitation link.
     */
    public function render_invitation_url($settings = array()) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view invitation URLs.', 'ldgiu') . '</p>';
        }

        // Set default settings if not provided
        $default_settings = array(
            'dropdown_label' => __('Select Group:', 'ldgiu'),
            'copy_button_text' => __('Share Sprint Link', 'ldgiu'),
            'copied_text' => __('Copied!', 'ldgiu')
        );
        
        $settings = wp_parse_args($settings, $default_settings);

        $user_id = get_current_user_id();
        $user_groups = array();
        $group_data = array();

        // Check if user is a group leader or regular user
        if (function_exists('learndash_is_group_leader_user') && learndash_is_group_leader_user($user_id)) {
            $user_groups = learndash_get_administrators_group_ids($user_id);
        } else {
            $user_groups = learndash_get_users_group_ids($user_id);
        }

        // If no groups, return message
        if (empty($user_groups)) {
            return '<p>' . esc_html__('You are not associated with any groups.', 'ldgiu') . '</p>';
        }

        // Process each group
        foreach ($user_groups as $group_id) {
            if (empty($group_id)) {
                continue;
            }

            // Get group name
            $group_name = get_the_title($group_id);
            
            // Get courses for this group
            $course_ids = $this->get_group_courses($group_id);
            $hash = wp_hash($group_id . get_option('site_secret_key', ''));
            $hash = substr($hash, 0, 12); // Shortened for URL friendliness
            
            // Store group data with first course
            $first_course_id = !empty($course_ids) ? $course_ids[0] : 0;
            
            $group_data[$group_id] = array(
                'name' => $group_name,
                'code' => $hash,
                'courses' => $course_ids,
                'first_course' => $first_course_id
            );
        }
        
        // Generate unique ID for this instance
        $unique_id = 'ldgiu_invite_' . uniqid();
        
        // Build HTML output
        ob_start();
        ?>
        <div class="ldgiu-invitation-container" id="<?php echo esc_attr($unique_id); ?>">
            <div class="ldgiu-group-selection">
                <label for="<?php echo esc_attr($unique_id); ?>_group"><?php echo esc_html($settings['dropdown_label']); ?></label>
                <select id="<?php echo esc_attr($unique_id); ?>_group" class="ldgiu-group-select">
                    <?php foreach ($group_data as $group_id => $data) : ?>
                        <option value="<?php echo esc_attr($group_id); ?>"><?php echo esc_html($data['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ldgiu-invitation-url-container">
                <input type="text" id="<?php echo esc_attr($unique_id); ?>_url" class="ldgiu-invitation-url" readonly>
                <button id="<?php echo esc_attr($unique_id); ?>_copy" class="ldgiu-copy-button elementor-button elementor-button-link elementor-size-sm">
                    <span class="elementor-button-content-wrapper">
                        <span class="elementor-button-icon"><i aria-hidden="true" class="fas fa-copy"></i></span>
                        <span class="elementor-button-text"><?php echo esc_html($settings['copy_button_text']); ?></span>
                    </span>
                </button>
            </div>
        </div>

        <style>
            .ldgiu-invitation-container {
                margin: 20px 0;
            }
            .ldgiu-group-selection {
                margin-bottom: 15px;
            }
            .ldgiu-group-select {
                width: 100%;
                padding: 8px;
                margin-top: 5px;
            }
            .ldgiu-invitation-url-container {
                display: flex;
                align-items: center;
            }
            .ldgiu-invitation-url {
                flex: 1;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-right: 10px;
            }
            .ldgiu-copy-button {
                white-space: nowrap;
            }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("<?php echo esc_attr($unique_id); ?>");
            const groupSelect = document.getElementById("<?php echo esc_attr($unique_id); ?>_group");
            const urlInput = document.getElementById("<?php echo esc_attr($unique_id); ?>_url");
            const copyButton = document.getElementById("<?php echo esc_attr($unique_id); ?>_copy");
            
            // Group data from PHP
            const groupData = <?php echo json_encode($group_data); ?>;
            
            // Function to update the invitation URL
            function updateInvitationUrl() {
                const groupId = groupSelect.value;
                const groupInfo = groupData[groupId];
                
                if (groupInfo) {
                    // Use the pre-computed first course ID (more reliable)
                    let courseId = groupInfo.first_course || 0;
                    
                    // Fallback to first course in array if needed
                    if (courseId === 0 && groupInfo.courses && groupInfo.courses.length > 0) {
                        courseId = groupInfo.courses[0];
                    }
                    
                    // Build the URL
                    let inviteUrl = new URL("<?php echo esc_url(home_url('/sign-up/')); ?>");
                    inviteUrl.searchParams.set("group_id", groupId);
                    inviteUrl.searchParams.set("code", groupInfo.code);
                    
                    // Always include the course_id parameter
                    // If no course is found, it will pass 0, which your system should handle
                    inviteUrl.searchParams.set("course_id", courseId);
                    
                    // Log the URL construction for debugging (remove in production)
                    console.log("Group ID:", groupId);
                    console.log("Group Info:", groupInfo);
                    console.log("First Course ID:", courseId);
                    console.log("Generated URL:", inviteUrl.toString());
                    
                    urlInput.value = inviteUrl.toString();
                }
            }
            
            // Copy URL to clipboard
            copyButton.addEventListener("click", function() {
                urlInput.select();
                document.execCommand("copy");
                
                // Visual feedback
                const originalText = copyButton.querySelector(".elementor-button-text").textContent;
                copyButton.querySelector(".elementor-button-text").textContent = "<?php echo esc_js($settings['copied_text']); ?>";
                
                setTimeout(function() {
                    copyButton.querySelector(".elementor-button-text").textContent = originalText;
                }, 2000);
            });
            
            // Update URL when group selection changes
            groupSelect.addEventListener("change", updateInvitationUrl);
            
            // Initial URL update
            updateInvitationUrl();
        });
        </script>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Get courses for a group
     * 
     * @param int $group_id The group ID
     * @return array Array of course IDs
     */
    private function get_group_courses($group_id) {
        $course_ids = array();
        $is_hierarchy_enabled = false;
        
        // Direct method to get course IDs - try this first for efficiency
        if (function_exists('learndash_group_enrolled_courses')) {
            $direct_courses = learndash_group_enrolled_courses($group_id);
            if (!empty($direct_courses) && is_array($direct_courses)) {
                return $direct_courses; // Return immediately if we got courses
            }
        }
        
        // Check for hierarchy settings
        if (function_exists('learndash_is_groups_hierarchical_enabled') && 
            learndash_is_groups_hierarchical_enabled() && 
            'yes' === get_option('ld_hierarchy_settings_child_groups', 'no')) {
            
            if (function_exists('ulgm_filter_has_var') && ulgm_filter_has_var('show-children')) {
                $is_hierarchy_enabled = true;
                if (class_exists('LearndashFunctionOverrides')) {
                    $group_courses = LearndashFunctionOverrides::learndash_group_enrolled_courses($group_id, true);
                } else {
                    $group_courses = learndash_group_enrolled_courses($group_id, true);
                }
                
                // If we have courses from hierarchy, return them
                if (!empty($group_courses) && is_array($group_courses)) {
                    return $group_courses;
                }
            }
        }

        // Set up query arguments for database query
        if ($is_hierarchy_enabled && !empty($group_courses)) {
            $post_vars = array(
                'post_type' => 'sfwd-courses',
                'post__in' => $group_courses,
                'orderby' => 'post_title',
                'order' => 'ASC',
                'posts_per_page' => 99999,
                'nopaging' => true,
            );
        } else {
            $post_vars = array(
                'post_type' => 'sfwd-courses',
                'meta_key' => 'learndash_group_enrolled_' . $group_id,
                'orderby' => 'post_title',
                'order' => 'ASC',
                'posts_per_page' => 99999,
                'nopaging' => true,
            );
        }

        // Apply group course order settings
        if (function_exists('learndash_get_group_courses_order')) {
            $ld_group_courses_order = learndash_get_group_courses_order($group_id);
            if (is_array($ld_group_courses_order)) {
                $post_vars['orderby'] = !empty($ld_group_courses_order['orderby']) ? 
                    $ld_group_courses_order['orderby'] : $post_vars['orderby'];
                
                $post_vars['order'] = !empty($ld_group_courses_order['order']) ? 
                    $ld_group_courses_order['order'] : $post_vars['order'];
            }
        }

        // Apply filter for customizations
        if (function_exists('apply_filters')) {
            $post_vars = apply_filters('ulgm_group_courses_list_get_posts_vars', $post_vars, $group_id);
        }
        
        // Execute query and collect course IDs
        $the_query = new \WP_Query($post_vars);
        
        if ($the_query->have_posts()) {
            while ($the_query->have_posts()) {
                $the_query->the_post();
                $course_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        // If no courses found, try direct database query as last resort
        if (empty($course_ids)) {
            global $wpdb;
            
            // Look for any courses with the group meta
            $meta_key = 'learndash_group_enrolled_' . $group_id;
            $sql = $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'sfwd-courses' AND post_status = 'publish'
                )
                LIMIT 1",
                $meta_key
            );
            
            $course_id = $wpdb->get_var($sql);
            if (!empty($course_id)) {
                $course_ids[] = $course_id;
            }
            
            // Log the database lookup for debugging
            error_log("LearnDash Group Invitation URL: Direct DB lookup for group {$group_id} found course ID: " . ($course_id ?: 'none found'));
        }
        
        return $course_ids;
    }

    /**
     * Register invitation URL shortcode
     */
    public function invitation_url_shortcode() {
        return $this->render_invitation_url();
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once $this->plugin_path . 'includes/elementor-widget.php';
        $widgets_manager->register(new LDGIU_Elementor_Widget());
    }
}

// Initialize the plugin
LearnDash_Group_Invitation_URL::get_instance();

// Plugin is now ready to use
?>