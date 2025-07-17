<?php
/**
 * Enhanced LearnDash Group Invitation URL (No AJAX Tracking)
 * 
 * This file should replace: plugins/freedomology-core/elements/learndash-group-invitation-url.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class (No AJAX Tracking)
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
        
        // Manual copy tracking (simple logging)
        add_action('wp_ajax_track_invitation_copy', array($this, 'ajax_track_invitation_copy'));
        add_action('wp_ajax_nopriv_track_invitation_copy', array($this, 'ajax_track_invitation_copy'));
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
     * @return string HTML output with dropdown selection and copyable invitation link.
     */
    public function render_invitation_url($settings = array()) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view invitation URLs.', 'ldgiu') . '</p>';
        }

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
            
            // Get invitation stats for this group
            $stats = $this->get_group_invitation_stats($group_id);
            
            // Store group data
            $group_data[$group_id] = array(
                'name' => $group_name,
                'code' => $hash,
                'courses' => $course_ids,
                'stats' => $stats,
            );
        }
        
        // Generate unique ID for this instance
        $unique_id = 'ldgiu_invite_' . uniqid();
        
        // Build HTML output (no AJAX tracking)
        ob_start();
        ?>
        <div class="ldgiu-invitation-container" id="<?php echo esc_attr($unique_id); ?>">
            <div class="ldgiu-group-selection">
                <label for="<?php echo esc_attr($unique_id); ?>_group"><?php echo esc_html__('Select Group:', 'ldgiu'); ?></label>
                <select id="<?php echo esc_attr($unique_id); ?>_group" class="ldgiu-group-select">
                    <?php foreach ($group_data as $group_id => $data) : ?>
                        <option value="<?php echo esc_attr($group_id); ?>" 
                                data-clicks="<?php echo esc_attr($data['stats']['total_clicks']); ?>"
                                data-conversions="<?php echo esc_attr($data['stats']['total_conversions']); ?>"
                                data-rate="<?php echo esc_attr($data['stats']['conversion_rate']); ?>">
                            <?php echo esc_html($data['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Stats Display -->
            <div class="ldgiu-stats-display" id="<?php echo esc_attr($unique_id); ?>_stats" style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 13px;">
                <div style="display: flex; gap: 15px; justify-content: space-between;">
                    <span>📊 <strong>Clicks:</strong> <span id="stats-clicks">0</span></span>
                    <span>✅ <strong>Signups:</strong> <span id="stats-conversions">0</span></span>
                    <span>📈 <strong>Rate:</strong> <span id="stats-rate">0%</span></span>
                </div>
            </div>
            
            <div class="ldgiu-invitation-url-container">
                <input type="text" id="<?php echo esc_attr($unique_id); ?>_url" class="ldgiu-invitation-url" readonly>
                <button id="<?php echo esc_attr($unique_id); ?>_copy" class="ldgiu-copy-button elementor-button elementor-button-link elementor-size-sm">
                    <span class="elementor-button-content-wrapper">
                        <span class="elementor-button-icon"><i aria-hidden="true" class="fas fa-copy"></i></span>
                        <span class="elementor-button-text"><?php echo esc_html__('Share Sprint Link', 'ldgiu'); ?></span>
                    </span>
                </button>
            </div>
            
            <!-- Copy History (Recent Copies) -->
            <div class="ldgiu-copy-history" id="<?php echo esc_attr($unique_id); ?>_history" style="margin-top: 10px; font-size: 12px; color: #666;">
                <div id="copy-log" style="max-height: 100px; overflow-y: auto;"></div>
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
            .ldgiu-stats-display {
                border-left: 4px solid #4BD7D3;
            }
            .copy-success {
                color: #28a745;
                font-weight: bold;
            }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("<?php echo esc_attr($unique_id); ?>");
            const groupSelect = document.getElementById("<?php echo esc_attr($unique_id); ?>_group");
            const urlInput = document.getElementById("<?php echo esc_attr($unique_id); ?>_url");
            const copyButton = document.getElementById("<?php echo esc_attr($unique_id); ?>_copy");
            const copyLog = document.getElementById("copy-log");
            
            // Group data from PHP
            const groupData = <?php echo json_encode($group_data); ?>;
            
            // Function to update stats display
            function updateStatsDisplay() {
                const selectedOption = groupSelect.options[groupSelect.selectedIndex];
                const clicks = selectedOption.getAttribute('data-clicks') || '0';
                const conversions = selectedOption.getAttribute('data-conversions') || '0';
                const rate = selectedOption.getAttribute('data-rate') || '0';
                
                document.getElementById('stats-clicks').textContent = clicks;
                document.getElementById('stats-conversions').textContent = conversions;
                document.getElementById('stats-rate').textContent = rate + '%';
            }
            
            // Function to update the invitation URL
            function updateInvitationUrl() {
                const groupId = groupSelect.value;
                const groupInfo = groupData[groupId];
                
                if (groupInfo) {
                    // Use the first course in array
                    let courseId = 0;
                    if (groupInfo.courses && groupInfo.courses.length > 0) {
                        courseId = groupInfo.courses[0];
                    }
                    
                    // Build the URL with tracking parameters
                    let inviteUrl = new URL("<?php echo esc_url(home_url('/sign-up/')); ?>");
                    inviteUrl.searchParams.set("group_id", groupId);
                    inviteUrl.searchParams.set("code", groupInfo.code);
                    inviteUrl.searchParams.set("course_id", courseId);
                    
                    // Add UTM tracking parameters
                    inviteUrl.searchParams.set("utm_source", "group_invitation");
                    inviteUrl.searchParams.set("utm_medium", "link_share");
                    inviteUrl.searchParams.set("utm_campaign", "group_" + groupId);
                    inviteUrl.searchParams.set("utm_content", "manual_copy");
                    
                    urlInput.value = inviteUrl.toString();
                }
                
                // Update stats display
                updateStatsDisplay();
            }
            
            // Function to track copy action (simple logging)
            function trackCopyAction(groupId, url) {
                // Send simple tracking data to server (optional)
                fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({
                        action: "track_invitation_copy",
                        group_id: groupId,
                        invitation_url: url,
                        nonce: "<?php echo wp_create_nonce('track_invitation_copy'); ?>"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Copy action logged");
                })
                .catch(error => {
                    console.log("Copy logging failed:", error);
                });
                
                // Add to copy log
                const timestamp = new Date().toLocaleTimeString();
                const groupName = groupData[groupId].name;
                const logEntry = document.createElement('div');
                logEntry.className = 'copy-success';
                logEntry.innerHTML = `${timestamp}: Copied link for "${groupName}"`;
                copyLog.insertBefore(logEntry, copyLog.firstChild);
                
                // Keep only last 3 entries
                while (copyLog.children.length > 3) {
                    copyLog.removeChild(copyLog.lastChild);
                }
            }
            
            // Copy URL to clipboard
            copyButton.addEventListener("click", function() {
                const groupId = groupSelect.value;
                const url = urlInput.value;
                
                urlInput.select();
                document.execCommand("copy");
                
                // Track the copy action
                trackCopyAction(groupId, url);
                
                // Visual feedback
                const originalText = copyButton.querySelector(".elementor-button-text").textContent;
                copyButton.querySelector(".elementor-button-text").textContent = "<?php echo esc_js(__('Copied!', 'ldgiu')); ?>";
                
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
     * Simple AJAX handler for tracking invitation copy actions (logging only)
     */
    public function ajax_track_invitation_copy() {
        if (!wp_verify_nonce($_POST['nonce'], 'track_invitation_copy')) {
            wp_die('Security check failed');
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $invitation_url = isset($_POST['invitation_url']) ? esc_url_raw($_POST['invitation_url']) : '';
        $user_id = get_current_user_id();
        
        if (empty($group_id) || empty($invitation_url)) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        // Simple logging to WordPress options (no database table)
        $copy_data = array(
            'group_id' => $group_id,
            'user_id' => $user_id,
            'url' => $invitation_url,
            'timestamp' => current_time('mysql'),
            'action' => 'manual_copy',
            'ip_address' => $this->get_user_ip(),
        );
        
        // Save copy action to WordPress options
        $copy_log = get_option('freedomology_copy_log', array());
        $copy_log[] = $copy_data;
        
        // Keep only last 100 copy actions
        if (count($copy_log) > 100) {
            $copy_log = array_slice($copy_log, -100);
        }
        
        update_option('freedomology_copy_log', $copy_log);
        
        // Trigger action for other plugins to hook into
        do_action('freedomology_invitation_copied', $group_id, $user_id, $invitation_url);
        
        wp_send_json_success(array('message' => 'Copy action logged'));
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Get invitation statistics for a group
     */
    private function get_group_invitation_stats($group_id, $days = 30) {
        // Check if tracking system is available
        if (class_exists('FreedomologyInvitationTrackingSystem')) {
            $tracking_system = new FreedomologyInvitationTrackingSystem();
            return $tracking_system->get_group_invitation_stats($group_id, $days);
        }
        
        // Fallback to basic stats if tracking system not available
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(
                'total_clicks' => 0,
                'total_conversions' => 0,
                'conversion_rate' => 0,
            );
        }
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get total clicks (with duplicate filtering)
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(ip_address, '-', DATE(click_timestamp))) 
            FROM $table_name 
            WHERE group_id = %d AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Get total conversions (unique conversions only)
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
            FROM $table_name 
            WHERE group_id = %d AND converted = 1 AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Calculate conversion rate
        $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
        
        return array(
            'total_clicks' => intval($total_clicks),
            'total_conversions' => intval($total_conversions),
            'conversion_rate' => $conversion_rate,
        );
    }

    /**
     * Get courses for a group
     */
    private function get_group_courses($group_id) {
        $course_ids = array();
        
        // Direct method to get course IDs
        if (function_exists('learndash_group_enrolled_courses')) {
            $direct_courses = learndash_group_enrolled_courses($group_id);
            if (!empty($direct_courses) && is_array($direct_courses)) {
                return $direct_courses;
            }
        }
        
        // Fallback: manual query
        global $wpdb;
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
