/**
     * Track invitation link clicks (backup method)
     */
    public function track_invitation_click()
    {
        // Backup method - AJAX is now primary
        // This runs as fallback if JavaScript is disabled
        
        if (!is_page('sign-up')) {
            return;
        }
        
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        
        if (empty($group_id) || empty($code)) {
            return;
        }
        
        // Use same logic as AJAX handler but simplified
        $user_ip = $this->get_user_ip();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Check for existing click today
        $existing_click = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE group_id = %d 
            AND invitation_code = %s 
            AND ip_address = %s 
            AND DATE(click_timestamp) = CURDATE()
            LIMIT 1",
            $group_id, $code, $user_ip
        ));
        
        if ($existing_click) {
            return; // Already tracked
        }
        
        // Insert tracking record (simplified version)
        $wpdb->insert(
            $table_name,
            array(
                'group_id' => $group_id,
                'course_id' => $course_id,
                'invitation_code' => $code,
                'ip_address' => $user_ip,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'utm_source' => 'group_invitation',
                'utm_medium' => 'link_share',
                'utm_campaign' => 'group_' . $group_id,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }<?php
/**
 * Group Invitation Link Tracking System
 * 
 * This file should be placed in: plugins/freedomology-core/elements/invitation-tracking-system.php
 * 
 * Tracks impressions (clicks) and conversions (successful signups) for group invitation links
 * Uses database-only tracking to avoid UTM parameter 404 errors
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
        // AJAX click tracking (new method - more reliable)
        add_action('wp_ajax_track_invitation_click', array($this, 'ajax_track_invitation_click'));
        add_action('wp_ajax_nopriv_track_invitation_click', array($this, 'ajax_track_invitation_click'));
        
        // Enqueue tracking script on sign-up page
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_script'));
        
        // Keep original method as backup
        add_action('template_redirect', array($this, 'track_invitation_click'), 1);
        
        // Track conversions when user successfully signs up
        add_action('gform_user_registered', array($this, 'track_invitation_conversion'), 20, 4);
        
        // Track conversions for existing users joining sprints (Form 4)
        add_action('gform_after_submission_4', array($this, 'track_existing_user_conversion'), 20, 2);
        
        // Add tracking parameters to invitation URLs (now disabled for clean URLs)
        add_filter('freedomology_invitation_url', array($this, 'add_tracking_parameters'), 10, 2);
        
        // Admin interface for viewing statistics - Settings Menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers for admin statistics
        add_action('wp_ajax_get_invitation_stats', array($this, 'ajax_get_invitation_stats'));
        
        // Enqueue admin scripts
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
            PRIMARY KEY (id),
            KEY group_id (group_id),
            KEY course_id (course_id),
            KEY invitation_code (invitation_code),
            KEY converted (converted),
            KEY click_timestamp (click_timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update table version option
        update_option('freedomology_tracking_db_version', '1.0');
    }

    /**
     * Enqueue tracking script on sign-up page
     */
    public function enqueue_tracking_script()
    {
        // Only load on sign-up page with invitation parameters
        if (!is_page('sign-up') || empty($_GET['group_id']) || empty($_GET['code'])) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Localize script with tracking data
        wp_localize_script('jquery', 'invitationTracking', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('track_invitation_click'),
            'group_id' => intval($_GET['group_id']),
            'course_id' => isset($_GET['course_id']) ? intval($_GET['course_id']) : 0,
            'code' => sanitize_text_field($_GET['code'])
        ));
        
        // Add inline script to track click immediately
        wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Track invitation click via AJAX
            $.post(invitationTracking.ajax_url, {
                action: "track_invitation_click",
                group_id: invitationTracking.group_id,
                course_id: invitationTracking.course_id,
                code: invitationTracking.code,
                nonce: invitationTracking.nonce
            }, function(response) {
                if (response.success) {
                    console.log("Invitation click tracked successfully:", response.data);
                } else {
                    console.log("Invitation tracking failed:", response.data);
                }
            }).fail(function() {
                console.log("Invitation tracking AJAX failed");
            });
        });
        ');
    }

    /**
     * AJAX handler for tracking invitation clicks
     */
    public function ajax_track_invitation_click()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'track_invitation_click')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        
        if (empty($group_id) || empty($code)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Get user information
        $user_ip = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Check for duplicate clicks from same IP today
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $this->create_tracking_tables();
        }
        
        $existing_click = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE group_id = %d 
            AND invitation_code = %s 
            AND ip_address = %s 
            AND DATE(click_timestamp) = CURDATE()
            LIMIT 1",
            $group_id, $code, $user_ip
        ));
        
        if ($existing_click) {
            // Already tracked today from this IP for this group
            wp_send_json_success(array(
                'message' => 'Already tracked today',
                'tracking_id' => $existing_click
            ));
            return;
        }
        
        // Create synthetic UTM data for analytics
        $utm_source = 'group_invitation';
        $utm_medium = 'link_share';
        $utm_campaign = 'group_' . $group_id;
        
        // Insert tracking record
        $result = $wpdb->insert(
            $table_name,
            array(
                'group_id' => $group_id,
                'course_id' => $course_id,
                'invitation_code' => $code,
                'ip_address' => $user_ip,
                'user_agent' => $user_agent,
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'utm_source' => $utm_source,
                'utm_medium' => $utm_medium,
                'utm_campaign' => $utm_campaign,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $tracking_id = $wpdb->insert_id;
            
            // Store tracking ID for potential conversion tracking
            if (!session_id()) {
                @session_start();
            }
            if (session_id()) {
                $_SESSION['invitation_tracking_id'] = $tracking_id;
            }
            
            // Also store in a cookie as backup
            setcookie('invitation_tracking_id', $tracking_id, time() + 3600, '/');
            
            wp_send_json_success(array(
                'message' => 'Click tracked successfully',
                'tracking_id' => $tracking_id,
                'group_id' => $group_id,
                'ip' => $user_ip
            ));
        } else {
            wp_send_json_error('Database insert failed: ' . $wpdb->last_error);
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
            
            error_log("Freedomology Tracking: Recorded conversion for user {$user_id}, tracking ID {$tracking_id}");
            
            // Hook for additional actions on conversion
            do_action('freedomology_invitation_converted', $user_id, $tracking_id);
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
            
            error_log("Freedomology Tracking: Recorded existing user conversion for user {$user_id}, tracking ID {$tracking_id}");
            
            // Hook for additional actions on conversion
            do_action('freedomology_invitation_converted', $user_id, $tracking_id);
        }
    }

    /**
     * Add tracking parameters to invitation URLs (disabled for clean URLs)
     */
    public function add_tracking_parameters($url, $group_id)
    {
        // No UTM parameters - return URL unchanged to avoid 404 errors
        // Analytics are captured via database-only tracking
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
     * Get invitation statistics for a group
     */
    public function get_group_invitation_stats($group_id, $days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get total clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE group_id = %d AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Get total conversions
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE group_id = %d AND converted = 1 AND click_timestamp >= %s",
            $group_id, $since_date
        ));
        
        // Get daily stats
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(click_timestamp) as date,
                COUNT(*) as clicks,
                SUM(converted) as conversions
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
     * Admin page for viewing statistics
     */
    public function admin_page()
    {
        // Ensure database table exists
        $this->debug_database_status();
        
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
                        <canvas id="invitation-chart"></canvas>
                    </div>
                    
                    <div class="stats-table">
                        <h3>Daily Breakdown</h3>
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
        }
        
        .stats-filters select {
            margin-right: 20px;
        }
        
        .stats-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            text-align: center;
            flex: 1;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .stat-box span {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stats-chart {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
        }
        
        .stats-table {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for getting invitation stats
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
        
        // Get total clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE click_timestamp >= %s",
            $since_date
        ));
        
        // Get total conversions
        $total_conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE converted = 1 AND click_timestamp >= %s",
            $since_date
        ));
        
        // Get daily stats
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(click_timestamp) as date,
                COUNT(*) as clicks,
                SUM(converted) as conversions
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
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Check if we're on the invitation tracking settings page
        if ($hook !== 'settings_page_group-invitation-tracking') {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array('jquery'), '3.9.1', true);
        
        // Localize script with proper AJAX data
        wp_localize_script('chart-js', 'invitationAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('invitation_stats_nonce'),
            'action' => 'get_invitation_stats'
        ));
        
        wp_add_inline_script('chart-js', '
        jQuery(document).ready(function($) {
            let invitationChart;
            
            function loadStats() {
                const groupId = $("#group-select").val();
                const days = $("#date-range").val();
                
                $.ajax({
                    url: invitationAjax.ajax_url,
                    type: "POST",
                    data: {
                        action: invitationAjax.action,
                        group_id: groupId,
                        days: days,
                        nonce: invitationAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateStatsDisplay(response.data);
                        } else {
                            showError("Error loading statistics: " + (response.data || "Unknown error"));
                        }
                    },
                    error: function(xhr, status, error) {
                        showError("Failed to load statistics. Please try again.");
                    }
                });
            }
            
            function showError(message) {
                $("#total-clicks").text("Error");
                $("#total-conversions").text("Error");
                $("#conversion-rate").text("Error");
                $("#daily-stats-body").html("<tr><td colspan=\"4\" style=\"color:red;\">" + message + "</td></tr>");
            }
            
            function updateStatsDisplay(data) {
                $("#total-clicks").text(data.total_clicks || 0);
                $("#total-conversions").text(data.total_conversions || 0);
                $("#conversion-rate").text((data.conversion_rate || 0) + "%");
                
                // Update daily stats table
                let tableHtml = "";
                if (data.daily_stats && data.daily_stats.length > 0) {
                    data.daily_stats.forEach(function(day) {
                        const rate = day.clicks > 0 ? Math.round((day.conversions / day.clicks) * 100) : 0;
                        tableHtml += `<tr>
                            <td>${day.date}</td>
                            <td>${day.clicks}</td>
                            <td>${day.conversions}</td>
                            <td>${rate}%</td>
                        </tr>`;
                    });
                } else {
                    tableHtml = "<tr><td colspan=\"4\">No data available for selected period</td></tr>";
                }
                $("#daily-stats-body").html(tableHtml);
                
                // Update chart
                updateChart(data.daily_stats || []);
            }
            
            function updateChart(dailyStats) {
                const ctx = document.getElementById("invitation-chart");
                if (!ctx) return;
                
                const chartCtx = ctx.getContext("2d");
                
                const labels = dailyStats.map(day => day.date).reverse();
                const clicksData = dailyStats.map(day => parseInt(day.clicks) || 0).reverse();
                const conversionsData = dailyStats.map(day => parseInt(day.conversions) || 0).reverse();
                
                if (invitationChart) {
                    invitationChart.destroy();
                }
                
                invitationChart = new Chart(chartCtx, {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: [{
                            label: "Clicks",
                            data: clicksData,
                            borderColor: "#0073aa",
                            backgroundColor: "rgba(0, 115, 170, 0.1)",
                            tension: 0.1
                        }, {
                            label: "Conversions",
                            data: conversionsData,
                            borderColor: "#00a32a",
                            backgroundColor: "rgba(0, 163, 42, 0.1)",
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            $("#load-stats").click(function(e) {
                e.preventDefault();
                loadStats();
            });
            
            // Auto-load stats on page load
            loadStats();
        });
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

    /**
     * Debug database status (needed for functionality)
     */
    private function debug_database_status()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'freedomology_invitation_tracking';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        return true;
    }
}

// Initialize the tracking system
new FreedomologyInvitationTrackingSystem();
