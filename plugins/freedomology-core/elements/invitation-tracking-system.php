<?php
/**
 * Complete Final Group Invitation Link Tracking System
 * 
 * This file should replace: plugins/freedomology-core/elements/invitation-tracking-system.php
 * 
 * Tracks impressions (clicks) and conversions (successful signups) for group invitation links
 * Uses server-side tracking only - NO AJAX from frontend widgets
 * Updated with improved count logic and fixed backend chart display (BAR CHART)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FreedomologyInvitationTrackingSystem
{
    /**
     * Constructor to initialize the tracking system
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->create_tracking_tables();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Server-side tracking only (no frontend AJAX)
        add_action('template_redirect', array($this, 'track_invitation_click'), 1);
        
        // Track conversions when user successfully signs up
        add_action('gform_user_registered', array($this, 'track_invitation_conversion'), 20, 4);
        
        // Track conversions for existing users joining sprints (Form 4)
        add_action('gform_after_submission_4', array($this, 'track_existing_user_conversion'), 20, 2);
        
        // Add tracking parameters to invitation URLs
        add_filter('freedomology_invitation_url', array($this, 'add_tracking_parameters'), 10, 2);
        
        // Admin interface for viewing statistics - Settings Menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers for admin statistics ONLY (not frontend)
        add_action('wp_ajax_get_invitation_stats', array($this, 'ajax_get_invitation_stats'));
        
        // Enqueue admin scripts ONLY
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add meta box to group edit page
        add_action('add_meta_boxes', array($this, 'add_group_stats_meta_box'));
        
        // Show stats in group management interface
        add_action('ulgm_after_group_header', array($this, 'show_group_invitation_stats'), 10, 1);
    }

    /**
     * Create database tables for tracking
     */
    private function create_tracking_tables()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            group_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            invitation_code varchar(50) NOT NULL,
            click_timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            referrer text,
            converted tinyint(1) DEFAULT 0,
            conversion_timestamp datetime NULL,
            user_id bigint(20) NULL,
            utm_source varchar(100),
            utm_medium varchar(100),
            utm_campaign varchar(100),
            tracking_method varchar(50) DEFAULT 'standard',
            unique_session varchar(100),
            page_load_id varchar(50),
            PRIMARY KEY (id),
            KEY group_id (group_id),
            KEY course_id (course_id),
            KEY invitation_code (invitation_code),
            KEY converted (converted),
            KEY click_timestamp (click_timestamp),
            KEY ip_date_lookup (ip_address, click_timestamp),
            KEY tracking_method (tracking_method),
            KEY page_load_id (page_load_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update table version option
        update_option('freedomology_tracking_db_version', '3.0');
    }

    /**
     * Track invitation link clicks (improved logic)
     */
    public function track_invitation_click()
    {
        // Only track if we have invitation parameters
        if (empty($_GET['group_id']) || empty($_GET['code'])) {
            return;
        }
        
        $group_id = intval($_GET['group_id']);
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        $code = sanitize_text_field($_GET['code']);
        
        if (empty($group_id) || empty($code)) {
            return;
        }
        
        // Check if group still exists
        if (!get_post($group_id) || get_post_status($group_id) !== 'publish') {
            error_log("Freedomology: Group {$group_id} no longer exists, skipping tracking");
            return;
        }
        
        // Validate invitation code format
        $expected_code = substr(wp_hash($group_id . get_option('site_secret_key', '')), 0, 12);
        if ($code !== $expected_code) {
            error_log("Freedomology: Invalid invitation code for group {$group_id}. Expected: {$expected_code}, Got: {$code}");
            return;
        }
        
        // Skip AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Get user information
        $user_ip = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $this->create_tracking_tables();
        }
        
        // Minimal duplicate prevention: only within 2 minutes from same IP + same code
        $recent_duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE group_id = %d 
            AND ip_address = %s 
            AND invitation_code = %s
            AND click_timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            LIMIT 1",
            $group_id, $user_ip, $code
        ));
        
        if ($recent_duplicate) {
            error_log("Freedomology: Duplicate click from {$user_ip} for group {$group_id} within 2 minutes, skipping");
            return;
        }
        
        // Get UTM parameters from URL or set defaults
        $utm_source = isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : 'group_invitation';
        $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : 'link_share';
        $utm_campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : 'group_' . $group_id;
        
        // Generate unique session identifier for this page load
        $unique_session = wp_generate_uuid4();
        $page_load_id = substr(md5($user_ip . time() . $user_agent), 0, 12);
        
        // Insert new tracking record
        $result = $wpdb->insert(
            $table_name,
            array(
                'group_id' => $group_id,
                'course_id' => $course_id,
                'invitation_code' => $code,
                'ip_address' => $user_ip,
                'user_agent' => $user_agent,
                'referrer' => $referrer,
                'utm_source' => $utm_source,
                'utm_medium' => $utm_medium,
                'utm_campaign' => $utm_campaign,
                'tracking_method' => 'standard',
                'unique_session' => $unique_session,
                'page_load_id' => $page_load_id,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $tracking_id = $wpdb->insert_id;
            
            // Store tracking ID for potential conversion tracking
            if (!session_id()) {
                @session_start();
            }
            if (session_id()) {
                $_SESSION['invitation_tracking_id'] = $tracking_id;
                $_SESSION['invitation_group_id'] = $group_id;
            }
            
            // Also store in cookies as backup
            setcookie('invitation_tracking_id', $tracking_id, time() + 3600, '/');
            setcookie('invitation_group_id', $group_id, time() + 3600, '/');
            
            // Log successful tracking
            error_log("Freedomology: Tracked click - ID: {$tracking_id}, Group: {$group_id}, IP: {$user_ip}");
            
            // Trigger cleanup occasionally (1% chance)
            if (rand(1, 100) === 1) {
                $this->cleanup_old_tracking_data();
            }
        } else {
            error_log("Freedomology: Failed to track click - " . $wpdb->last_error);
        }
    }

    /**
     * Track invitation conversions for new user registrations
     */
    public function track_invitation_conversion($user_id, $feed, $entry, $user_pass)
    {
        // Try to get tracking ID from session first
        $tracking_id = 0;
        
        if (!session_id()) {
            @session_start();
        }
        
        if (isset($_SESSION['invitation_tracking_id'])) {
            $tracking_id = intval($_SESSION['invitation_tracking_id']);
        } elseif (isset($_COOKIE['invitation_tracking_id'])) {
            // Fallback to cookie (for incognito mode)
            $tracking_id = intval($_COOKIE['invitation_tracking_id']);
        }
        
        if (empty($tracking_id)) {
            error_log("Freedomology: No tracking ID found for conversion - User: {$user_id}");
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Update tracking record to mark as converted
        $result = $wpdb->update(
            $table_name,
            array(
                'converted' => 1,
                'conversion_timestamp' => current_time('mysql'),
                'user_id' => $user_id,
            ),
            array('id' => $tracking_id),
            array('%d', '%s', '%d'),
            array('%d')
        );
        
        if ($result) {
            // Clear session and cookie tracking
            if (isset($_SESSION['invitation_tracking_id'])) {
                unset($_SESSION['invitation_tracking_id']);
            }
            setcookie('invitation_tracking_id', '', time() - 3600, '/'); // Clear cookie
            
            error_log("Freedomology: Conversion tracked - Tracking ID: {$tracking_id}, User: {$user_id}");
            
            // Hook for additional actions on conversion
            do_action('freedomology_invitation_converted', $user_id, $tracking_id);
        } else {
            error_log("Freedomology: Failed to update conversion - Tracking ID: {$tracking_id}");
        }
    }

    /**
     * Track conversions for existing users joining sprints
     */
    public function track_existing_user_conversion($entry, $form)
    {
        // Try to get tracking ID from session first
        $tracking_id = 0;
        $user_id = get_current_user_id();
        
        if (!session_id()) {
            @session_start();
        }
        
        if (isset($_SESSION['invitation_tracking_id'])) {
            $tracking_id = intval($_SESSION['invitation_tracking_id']);
        } elseif (isset($_COOKIE['invitation_tracking_id'])) {
            // Fallback to cookie (for incognito mode)
            $tracking_id = intval($_COOKIE['invitation_tracking_id']);
        }
        
        if (empty($tracking_id) || empty($user_id)) {
            error_log("Freedomology: No tracking data for existing user conversion - Tracking ID: {$tracking_id}, User: {$user_id}");
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Update tracking record to mark as converted
        $result = $wpdb->update(
            $table_name,
            array(
                'converted' => 1,
                'conversion_timestamp' => current_time('mysql'),
                'user_id' => $user_id,
            ),
            array('id' => $tracking_id),
            array('%d', '%s', '%d'),
            array('%d')
        );
        
        if ($result) {
            // Clear session and cookie tracking
            if (isset($_SESSION['invitation_tracking_id'])) {
                unset($_SESSION['invitation_tracking_id']);
            }
            setcookie('invitation_tracking_id', '', time() - 3600, '/'); // Clear cookie
            
            error_log("Freedomology: Existing user conversion tracked - Tracking ID: {$tracking_id}, User: {$user_id}");
            
            // Hook for additional actions on conversion
            do_action('freedomology_invitation_converted', $user_id, $tracking_id);
        }
    }

    /**
     * Add tracking parameters to invitation URLs
     */
    public function add_tracking_parameters($url, $group_id)
    {
        // Add UTM parameters for better tracking
        $url = add_query_arg(array(
            'utm_source' => 'group_invitation',
            'utm_medium' => 'link_share',
            'utm_campaign' => 'group_' . $group_id,
            'utm_content' => 'manual_copy'
        ), $url);
        
        return $url;
    }

    /**
     * Get user IP address
     */
    private function get_user_ip()
    {
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
     * Get invitation statistics for a group (Updated count logic)
     */
    public function get_group_invitation_stats($group_id, $days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Raw count - shows all legitimate clicks (minimal duplicate filtering)
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE group_id = %d AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Alternative count methods (uncomment if needed):
        
        // Method 1: Unique sessions (recommended for engagement tracking)
        /*
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT page_load_id) 
            FROM $table_name 
            WHERE group_id = %d AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        */
        
        // Method 2: 5-minute window deduplication
        /*
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT DISTINCT ip_address, 
                       FLOOR(UNIX_TIMESTAMP(click_timestamp) / 300) as time_bucket
                FROM $table_name 
                WHERE group_id = %d AND click_timestamp >= %s
            ) as deduplicated",
            $group_id, $since_date
        ));
        */
        
        // Method 3: Daily unique IPs (original method)
        /*
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(ip_address, '-', DATE(click_timestamp))) 
            FROM $table_name 
            WHERE group_id = %d AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        */
        
        // Conversions - always count unique users
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
            FROM $table_name 
            WHERE group_id = %d AND converted = 1 AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Daily stats matching the click counting method
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(click_timestamp) as date,
                COUNT(*) as clicks,
                COUNT(DISTINCT CASE WHEN converted = 1 THEN user_id END) as conversions
            FROM $table_name 
            WHERE group_id = %d AND click_timestamp >= %s
            GROUP BY DATE(click_timestamp)
            ORDER BY date DESC",
            $group_id, $since_date
        ));
        
        // Calculate conversion rate
        $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
        
        return array(
            'total_clicks' => intval($total_clicks),
            'total_conversions' => intval($total_conversions),
            'conversion_rate' => $conversion_rate,
            'daily_stats' => $daily_stats,
        );
    }

    /**
     * Cleanup old tracking data automatically
     */
    private function cleanup_old_tracking_data()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Delete tracking records older than 6 months
        $deleted = $wpdb->query("
            DELETE FROM $table_name 
            WHERE click_timestamp < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        
        if ($deleted > 0) {
            error_log("Freedomology: Cleaned up {$deleted} old tracking records");
        }
        
        // Also cleanup orphaned records (groups that no longer exist)
        $orphaned = $wpdb->query("
            DELETE t FROM $table_name t
            LEFT JOIN {$wpdb->posts} p ON t.group_id = p.ID
            WHERE p.ID IS NULL OR p.post_status != 'publish'
        ");
        
        if ($orphaned > 0) {
            error_log("Freedomology: Cleaned up {$orphaned} orphaned tracking records");
        }
    }

    /**
     * Add admin menu under Settings
     */
    public function add_admin_menu()
    {
        add_options_page(
            'Group Invitation Tracking Statistics',  // Page title
            'Group Tracking',                        // Menu title
            'manage_options',                        // Capability
            'group-invitation-tracking',             // Menu slug
            array($this, 'admin_page')              // Callback
        );
    }

    /**
     * Admin page for viewing statistics (Fixed chart display with BAR CHART)
     */
    public function admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Group Invitation Tracking Statistics</h1>
            
            <div class="invitation-stats-container">
                <div class="stats-filters">
                    <label for="group-select">Select Group:</label>
                    <select id="group-select" class="regular-text">
                        <option value="">All Groups</option>
                        <?php
                        // Get groups based on available post types
                        $post_types = get_post_types();
                        $group_post_type = 'groups'; // Default
                        
                        if (in_array('learndash-groups', $post_types)) {
                            $group_post_type = 'learndash-groups';
                        } elseif (in_array('groups', $post_types)) {
                            $group_post_type = 'groups';
                        }
                        
                        $groups = get_posts(array(
                            'post_type' => $group_post_type,
                            'posts_per_page' => -1,
                            'post_status' => 'publish'
                        ));
                        
                        foreach ($groups as $group) {
                            echo '<option value="' . $group->ID . '">' . esc_html($group->post_title) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <label for="date-range">Date Range:</label>
                    <select id="date-range" class="regular-text">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                    </select>
                    
                    <button id="load-stats" class="button button-primary">Load Statistics</button>
                    
                    <div style="margin-top: 10px;">
                        <small style="color: #666;">
                            <strong>Debug:</strong> 
                            Chart.js: <span id="chart-status">Checking...</span> | 
                            jQuery: <span id="jquery-status">Checking...</span>
                        </small>
                    </div>
                </div>
                
                <div id="stats-display">
                    <div class="stats-summary">
                        <div class="stat-box">
                            <h3>Total Clicks</h3>
                            <span id="total-clicks">Loading...</span>
                        </div>
                        <div class="stat-box">
                            <h3>Total Conversions</h3>
                            <span id="total-conversions">Loading...</span>
                        </div>
                        <div class="stat-box">
                            <h3>Conversion Rate</h3>
                            <span id="conversion-rate">Loading...</span>
                        </div>
                    </div>
                    
                    <div class="stats-chart">
                        <h3 style="margin-top: 0;">Performance Chart</h3>
                        <div style="position: relative; height: 350px; width: 100%;">
                            <canvas id="invitation-chart" style="display: block; width: 100%; height: 100%;"></canvas>
                        </div>
                        <div id="chart-error" style="display: none; color: red; text-align: center; padding: 20px;">
                            Chart failed to load. Check browser console for details.
                        </div>
                    </div>
                    
                    <div class="stats-table">
                        <h3>Daily Breakdown</h3>
                        <div style="overflow-x: auto;">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clicks</th>
                                        <th>Conversions</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="daily-stats-body">
                                    <tr><td colspan="4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Database Maintenance Section -->
                <div class="cleanup-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                    <h3>Database Maintenance</h3>
                    <p>Clean up old tracking data and orphaned records from deleted groups.</p>
                    
                    <?php
                    if (isset($_POST['cleanup_tracking_data']) && wp_verify_nonce($_POST['cleanup_nonce'], 'cleanup_tracking')) {
                        $cleanup_result = $this->manual_cleanup_tracking_data();
                        if ($cleanup_result) {
                            echo '<div class="notice notice-success"><p>';
                            echo sprintf('Cleanup completed: %d old records and %d orphaned records deleted.', 
                                $cleanup_result['old_records_deleted'], 
                                $cleanup_result['orphaned_records_deleted']
                            );
                            echo '</p></div>';
                        }
                    }
                    ?>
                    
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('cleanup_tracking', 'cleanup_nonce'); ?>
                        <input type="submit" name="cleanup_tracking_data" class="button button-secondary" 
                               value="Clean Up Old Data" 
                               onclick="return confirm('This will delete tracking records older than 6 months and orphaned records. Continue?')">
                    </form>
                    <p><small><strong>Note:</strong> This will delete tracking records older than 6 months and records for groups that no longer exist.</small></p>
                </div>
            </div>
        </div>
        
        <style>
        .invitation-stats-container {
            margin-top: 20px;
        }
        
        .stats-filters {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
        }
        
        .stats-filters label {
            display: inline-block;
            width: 100px;
            margin-right: 10px;
            font-weight: 600;
        }
        
        .stats-filters select {
            margin-right: 20px;
            vertical-align: middle;
        }
        
        .stats-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            text-align: center;
            flex: 1;
            min-width: 200px;
            border-radius: 4px;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-box span {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
            display: block;
        }
        
        .stats-chart {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .stats-table {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .stats-table h3 {
            margin-top: 0;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .stats-summary {
                flex-direction: column;
            }
            
            .stats-filters label {
                display: block;
                width: auto;
                margin-bottom: 5px;
            }
            
            .stats-filters select {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        </style>

        <script>
        // Debug status check
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.getElementById('chart-status').textContent = typeof Chart !== 'undefined' ? 'Loaded ✓' : 'Failed ✗';
                document.getElementById('jquery-status').textContent = typeof jQuery !== 'undefined' ? 'Loaded ✓' : 'Failed ✗';
                
                if (typeof Chart === 'undefined') {
                    document.getElementById('chart-error').style.display = 'block';
                    console.error('Chart.js failed to load from CDN');
                }
            }, 1000);
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for getting invitation stats (ADMIN ONLY)
     */
    public function ajax_get_invitation_stats()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'invitation_stats_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        try {
            if (empty($group_id)) {
                // Get stats for all groups
                $stats = $this->get_all_groups_stats($days);
            } else {
                // Get stats for specific group
                $stats = $this->get_group_invitation_stats($group_id, $days);
            }
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            wp_send_json_error('Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics for all groups combined
     */
    private function get_all_groups_stats($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Raw count for all groups
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $table_name 
            WHERE click_timestamp >= %s",
            $since_date
        ));
        
        // Total conversions (unique conversions only)
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
            FROM $table_name 
            WHERE converted = 1 AND click_timestamp >= %s",
            $since_date
        ));
        
        // Daily stats
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(click_timestamp) as date,
                COUNT(*) as clicks,
                COUNT(DISTINCT CASE WHEN converted = 1 THEN user_id END) as conversions
            FROM $table_name 
            WHERE click_timestamp >= %s
            GROUP BY DATE(click_timestamp)
            ORDER BY date DESC",
            $since_date
        ));
        
        // Calculate conversion rate
        $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
        
        return array(
            'total_clicks' => intval($total_clicks),
            'total_conversions' => intval($total_conversions),
            'conversion_rate' => $conversion_rate,
            'daily_stats' => $daily_stats,
        );
    }

    /**
     * Manual cleanup function for admin use
     */
    public function manual_cleanup_tracking_data()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Clean up records older than specified days
        $days_to_keep = apply_filters('freedomology_tracking_retention_days', 180); // 6 months default
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM $table_name 
            WHERE click_timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days_to_keep));
        
        // Clean up orphaned records
        $orphaned = $wpdb->query("
            DELETE t FROM $table_name t
            LEFT JOIN {$wpdb->posts} p ON t.group_id = p.ID
            WHERE p.ID IS NULL OR p.post_status != 'publish'
        ");
        
        return array(
            'old_records_deleted' => $deleted,
            'orphaned_records_deleted' => $orphaned
        );
    }

    /**
     * Enqueue admin scripts with fixed BAR chart display
     */
    public function enqueue_admin_scripts($hook)
    {
        // Check if we're on the invitation tracking settings page
        if ($hook !== 'settings_page_group-invitation-tracking') {
            return;
        }
        
        // Enqueue Chart.js with jQuery dependency
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        
        // Enqueue jQuery (ensure it's loaded)
        wp_enqueue_script('jquery');
        
        // Create inline script for stats functionality with BAR CHART
        $inline_script = "
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Chart.js loaded:', typeof Chart !== 'undefined');
            console.log('jQuery loaded:', typeof jQuery !== 'undefined');
            
            if (typeof jQuery === 'undefined') {
                console.error('jQuery not loaded');
                return;
            }
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }
            
            jQuery(document).ready(function($) {
                let invitationChart;
                
                // AJAX configuration
                const ajaxConfig = {
                    url: '" . admin_url('admin-ajax.php') . "',
                    nonce: '" . wp_create_nonce('invitation_stats_nonce') . "',
                    action: 'get_invitation_stats'
                };
                
                function loadStats() {
                    const groupId = $('#group-select').val();
                    const days = $('#date-range').val();
                    
                    console.log('Loading stats for group:', groupId, 'days:', days);
                    
                    // Show loading state
                    $('#total-clicks').text('Loading...');
                    $('#total-conversions').text('Loading...');
                    $('#conversion-rate').text('Loading...');
                    $('#daily-stats-body').html('<tr><td colspan=\"4\">Loading...</td></tr>');
                    
                    $.ajax({
                        url: ajaxConfig.url,
                        type: 'POST',
                        data: {
                            action: ajaxConfig.action,
                            group_id: groupId,
                            days: days,
                            nonce: ajaxConfig.nonce
                        },
                        success: function(response) {
                            console.log('AJAX Response:', response);
                            
                            if (response.success) {
                                updateStatsDisplay(response.data);
                            } else {
                                showError('Error loading statistics: ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', xhr, status, error);
                            showError('Failed to load statistics. Please try again.');
                        }
                    });
                }
                
                function showError(message) {
                    $('#total-clicks').text('Error');
                    $('#total-conversions').text('Error');
                    $('#conversion-rate').text('Error');
                    $('#daily-stats-body').html('<tr><td colspan=\"4\" style=\"color:red;\">' + message + '</td></tr>');
                }
                
                function updateStatsDisplay(data) {
                    console.log('Updating stats display with:', data);
                    
                    // Update summary stats
                    $('#total-clicks').text(data.total_clicks || 0);
                    $('#total-conversions').text(data.total_conversions || 0);
                    $('#conversion-rate').text((data.conversion_rate || 0) + '%');
                    
                    // Update daily stats table
                    let tableHtml = '';
                    if (data.daily_stats && data.daily_stats.length > 0) {
                        data.daily_stats.forEach(function(day) {
                            const rate = day.clicks > 0 ? Math.round((day.conversions / day.clicks) * 100) : 0;
                            tableHtml += '<tr>' +
                                '<td>' + day.date + '</td>' +
                                '<td>' + day.clicks + '</td>' +
                                '<td>' + day.conversions + '</td>' +
                                '<td>' + rate + '%</td>' +
                            '</tr>';
                        });
                    } else {
                        tableHtml = '<tr><td colspan=\"4\">No data available for selected period</td></tr>';
                    }
                    $('#daily-stats-body').html(tableHtml);
                    
                    // Update chart
                    updateChart(data.daily_stats || []);
                }
                
                function updateChart(dailyStats) {
                    const chartCanvas = document.getElementById('invitation-chart');
                    if (!chartCanvas) {
                        console.error('Chart canvas not found');
                        return;
                    }
                    
                    console.log('Updating chart with data:', dailyStats);
                    
                    const ctx = chartCanvas.getContext('2d');
                    
                    // Prepare chart data
                    const labels = dailyStats.map(day => day.date).reverse();
                    const clicksData = dailyStats.map(day => parseInt(day.clicks) || 0).reverse();
                    const conversionsData = dailyStats.map(day => parseInt(day.conversions) || 0).reverse();
                    
                    console.log('Chart data:', { labels, clicksData, conversionsData });
                    
                    // Destroy existing chart
                    if (invitationChart) {
                        invitationChart.destroy();
                    }
                    
                    // Create new BAR chart
                    try {
                        invitationChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Clicks',
                                    data: clicksData,
                                    backgroundColor: 'rgba(0, 115, 170, 0.8)',
                                    borderColor: '#0073aa',
                                    borderWidth: 1
                                }, {
                                    label: 'Conversions',
                                    data: conversionsData,
                                    backgroundColor: 'rgba(0, 163, 42, 0.8)',
                                    borderColor: '#00a32a',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Invitation Link Performance'
                                    },
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Count'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Date'
                                        }
                                    }
                                },
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                }
                            }
                        });
                        
                        console.log('Bar chart created successfully');
                    } catch (error) {
                        console.error('Error creating chart:', error);
                    }
                }
                
                // Event handlers
                $('#load-stats').click(function(e) {
                    e.preventDefault();
                    loadStats();
                });
                
                // Auto-load stats on page load
                setTimeout(loadStats, 500); // Small delay to ensure everything is loaded
            });
        });
        ";
        
        // Add the inline script
        wp_add_inline_script('chart-js', $inline_script);
        
        // Add some additional CSS for better chart display
        wp_add_inline_style('wp-admin', '
            .stats-chart {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                margin-bottom: 20px;
                height: 400px;
            }
            
            .stats-chart canvas {
                max-height: 350px !important;
            }
            
            .invitation-stats-container .stats-summary {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }
            
            .invitation-stats-container .stat-box {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                text-align: center;
                flex: 1;
                min-width: 200px;
            }
            
            @media (max-width: 768px) {
                .invitation-stats-container .stats-summary {
                    flex-direction: column;
                }
                
                .stats-chart {
                    height: 300px;
                }
            }
        ');
    }

    /**
     * Add meta box to group edit pages
     */
    public function add_group_stats_meta_box()
    {
        // Try to add to different possible group post types
        $post_types = get_post_types();
        
        if (in_array('learndash-groups', $post_types)) {
            add_meta_box(
                'group-invitation-stats',
                'Invitation Statistics',
                array($this, 'render_group_stats_meta_box'),
                'learndash-groups',
                'side',
                'high'
            );
        }
        
        if (in_array('groups', $post_types)) {
            add_meta_box(
                'group-invitation-stats',
                'Invitation Statistics',
                array($this, 'render_group_stats_meta_box'),
                'groups',
                'side',
                'high'
            );
        }
    }

    /**
     * Render group stats meta box
     */
    public function render_group_stats_meta_box($post)
    {
        $stats = $this->get_group_invitation_stats($post->ID, 30);
        ?>
        <div class="group-invitation-stats">
            <p><strong>Last 30 Days:</strong></p>
            <p>Clicks: <strong><?php echo $stats['total_clicks']; ?></strong></p>
            <p>Conversions: <strong><?php echo $stats['total_conversions']; ?></strong></p>
            <p>Rate: <strong><?php echo $stats['conversion_rate']; ?>%</strong></p>
            
            <p><a href="<?php echo admin_url('options-general.php?page=group-invitation-tracking&group=' . $post->ID); ?>" class="button">View Detailed Stats</a></p>
        </div>
        <?php
    }

    /**
     * Show invitation stats in group management interface
     */
    public function show_group_invitation_stats($group_id)
    {
        $stats = $this->get_group_invitation_stats($group_id, 30);
        ?>
        <div class="ulgm-invitation-stats" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <h4 style="margin: 0 0 10px 0;">📊 Invitation Link Performance (Last 30 Days)</h4>
            <div style="display: flex; gap: 20px;">
                <div>
                    <strong>Clicks:</strong> <?php echo $stats['total_clicks']; ?>
                </div>
                <div>
                    <strong>Signups:</strong> <?php echo $stats['total_conversions']; ?>
                </div>
                <div>
                    <strong>Rate:</strong> <?php echo $stats['conversion_rate']; ?>%
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the tracking system
new FreedomologyInvitationTrackingSystem();