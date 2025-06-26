<?php
/**
 * Plugin Name: Freedomology Sprint Date Migration
 * Plugin URI: https://wbcomdesigns.com
 * Description: One-time migration tool to import sprint start dates from Gravity Forms Form ID 1 entries.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * License: GPL2
 * Text Domain: freedomology-migration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if class already exists to prevent fatal errors
if (!class_exists('Freedomology_Sprint_Migration')) {

class Freedomology_Sprint_Migration {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'show_migration_notice'));
    }
    
    /**
     * Show admin notice
     */
    public function show_migration_notice() {
        $screen = get_current_screen();
        if ($screen->id !== 'tools_page_freedomology-sprint-migration') {
            ?>
            <div class="notice notice-info">
                <p><strong>Freedomology Migration:</strong> Sprint date migration from Gravity Forms is ready. 
                <a href="<?php echo admin_url('tools.php?page=freedomology-sprint-migration'); ?>">Run Migration</a></p>
            </div>
            <?php
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Sprint Date Migration',
            'Sprint Date Migration',
            'manage_options',
            'freedomology-sprint-migration',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Freedomology Sprint Date Migration</h1>
            
            <?php
            // Handle form submission
            if (isset($_POST['run_migration']) && check_admin_referer('freedomology_migration')) {
                $this->run_migration();
            }
            ?>
            
            <!-- Current Status -->
            <div class="card">
                <h2>Current Status</h2>
                <?php $this->show_current_status(); ?>
            </div>
            
            <!-- Migration Form -->
            <div class="card">
                <h2>Import Sprint Dates from Gravity Forms</h2>
                <p>This will import sprint start dates from Gravity Forms Form ID 1 (Field 8) for all group leaders.</p>
                
                <form method="post">
                    <?php wp_nonce_field('freedomology_migration'); ?>
                    <p class="submit">
                        <input type="submit" name="run_migration" class="button-primary" value="Run Migration" 
                               onclick="return confirm('This will import all sprint dates from Gravity Forms entries. Continue?');" />
                    </p>
                </form>
            </div>
            
            <!-- Groups Overview -->
            <div class="card">
                <h2>Groups Overview</h2>
                <?php $this->show_groups_table(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show current status
     */
    private function show_current_status() {
        if (!class_exists('GFAPI')) {
            echo '<p style="color:red;">Gravity Forms is not active!</p>';
            return;
        }
        
        // Get entries from form 1
        $entries = GFAPI::get_entries(1, array('status' => 'active'), null, array('offset' => 0, 'page_size' => 1000));
        $entries_with_dates = 0;
        
        foreach ($entries as $entry) {
            if (!empty(rgar($entry, '8'))) {
                $entries_with_dates++;
            }
        }
        
        // Count users needing dates
        $groups = get_posts(array(
            'post_type' => 'groups',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $users_need_dates = 0;
        $users_have_dates = 0;
        
        foreach ($groups as $group) {
            $course_ids = learndash_group_enrolled_courses($group->ID);
            if (!empty($course_ids)) {
                $course_id = $course_ids[0];
                $meta_key = $this->get_course_meta_key($course_id);
                
                if (!empty($meta_key)) {
                    $all_users = learndash_get_groups_user_ids($group->ID);
                    foreach ($all_users as $user_id) {
                        $user_date = get_user_meta($user_id, $meta_key, true);
                        if (empty($user_date)) {
                            $users_need_dates++;
                        } else {
                            $users_have_dates++;
                        }
                    }
                }
            }
        }
        
        echo '<ul>';
        echo '<li>Gravity Forms entries found: <strong>' . count($entries) . '</strong></li>';
        echo '<li>Entries with start dates: <strong>' . $entries_with_dates . '</strong></li>';
        echo '<li>Users with sprint dates: <strong style="color:green;">' . $users_have_dates . '</strong></li>';
        echo '<li>Users needing sprint dates: <strong style="color:red;">' . $users_need_dates . '</strong></li>';
        echo '</ul>';
    }
    
    /**
     * Run the migration
     */
    private function run_migration() {
        if (!class_exists('GFAPI')) {
            echo '<div class="notice notice-error"><p>Gravity Forms is not active!</p></div>';
            return;
        }
        
        set_time_limit(300); // Allow 5 minutes
        
        // Get all entries from form 1
        $entries = GFAPI::get_entries(1, array('status' => 'active'), null, array('offset' => 0, 'page_size' => 1000));
        
        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $log = array();
        $processed_groups = array();
        
        foreach ($entries as $entry) {
            $email = rgar($entry, '4'); // Email
            $start_date = rgar($entry, '8'); // Start date
            $group_name = rgar($entry, '9'); // Group name
            
            if (empty($email) || empty($start_date)) {
                $skipped++;
                continue;
            }
            
            // Convert date format (MM/DD/YYYY to YYYY-MM-DD)
            if (strpos($start_date, '/') !== false) {
                $date_parts = explode('/', $start_date);
                if (count($date_parts) == 3) {
                    $start_date = $date_parts[2] . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                }
            }
            
            // Find user by email
            $user = get_user_by('email', $email);
            if (!$user) {
                $log[] = "Skipped: User not found for email {$email} (may have been deleted)";
                $skipped++;
                continue;
            }
            
            // Find groups where this user is a leader
            $user_groups = learndash_get_administrators_group_ids($user->ID);
            
            // If no groups found, check if user has group_leader role
            if (empty($user_groups)) {
                // Check if user has group_leader role
                if (in_array('group_leader', (array) $user->roles)) {
                    // Get all groups and check if this user is listed as a leader
                    $all_groups = get_posts(array(
                        'post_type' => 'groups',
                        'posts_per_page' => -1,
                        'post_status' => 'publish'
                    ));
                    
                    foreach ($all_groups as $group) {
                        $group_leaders = learndash_get_groups_administrator_ids($group->ID);
                        if (in_array($user->ID, (array) $group_leaders)) {
                            $user_groups[] = $group->ID;
                        }
                    }
                }
            }
            
            if (empty($user_groups)) {
                $log[] = "Skipped: {$email} is not a group leader (checked role and groups)";
                $skipped++;
                continue;
            }
            
            // Try to match by group name
            $matched_group = null;
            if (!empty($group_name)) {
                foreach ($user_groups as $group_id) {
                    $group = get_post($group_id);
                    if ($group && stripos($group->post_title, $group_name) !== false) {
                        $matched_group = $group_id;
                        break;
                    }
                }
            }
            
            // Use first group if no name match
            if (!$matched_group && !empty($user_groups)) {
                $matched_group = $user_groups[0];
            }
            
            if ($matched_group && !in_array($matched_group, $processed_groups)) {
                $result = $this->set_group_date($matched_group, $start_date, $user->user_email);
                
                if ($result['success']) {
                    $processed++;
                    $processed_groups[] = $matched_group;
                    $group_title = get_the_title($matched_group);
                    $log[] = "✓ Group '{$group_title}' (ID: {$matched_group}): Set date {$start_date} - Updated {$result['total_updated']} users";
                } else {
                    $errors++;
                    $log[] = "✗ Failed to process group {$matched_group}";
                }
            }
        }
        
        // Display results
        echo '<div class="notice notice-success">';
        echo '<h3>Migration Complete!</h3>';
        echo '<ul>';
        echo '<li>Groups processed: <strong>' . $processed . '</strong></li>';
        echo '<li>Entries skipped (no date/email/deleted user): <strong>' . $skipped . '</strong></li>';
        echo '<li>Errors: <strong>' . $errors . '</strong></li>';
        echo '</ul>';
        echo '</div>';
        
        // Show log
        if (!empty($log)) {
            echo '<div class="notice notice-info">';
            echo '<h4>Processing Log:</h4>';
            echo '<ul style="max-height: 300px; overflow-y: auto;">';
            foreach ($log as $log_entry) {
                echo '<li>' . esc_html($log_entry) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Set date for a specific group and all its users
     */
    private function set_group_date($group_id, $start_date, $leader_email = '') {
        // Get course for this group
        $group_course_ids = learndash_group_enrolled_courses($group_id);
        if (empty($group_course_ids)) {
            return array('success' => false, 'message' => 'No course found');
        }
        
        $course_id = $group_course_ids[0];
        $meta_key = $this->get_course_meta_key($course_id);
        
        if (empty($meta_key)) {
            return array('success' => false, 'message' => 'Invalid course');
        }
        
        // Update group meta
        update_post_meta($group_id, '_sprint_start_date', $start_date);
        update_post_meta($group_id, '_sprint_course_id', $course_id);
        if (!empty($leader_email)) {
            update_post_meta($group_id, '_group_leader_email', $leader_email);
        }
        
        $updated_users = array();
        
        // Update all users in the group
        $all_users = learndash_get_groups_user_ids($group_id);
        
        foreach ($all_users as $user_id) {
            // Check if user already has a date
            $existing_date = get_user_meta($user_id, $meta_key, true);
            if (!empty($existing_date)) {
                continue; // Skip
            }
            
            // Set the date
            update_user_meta($user_id, $meta_key, $start_date);
            
            // Set leader email for non-leaders
            if (!empty($leader_email)) {
                $existing_leader_email = get_user_meta($user_id, 'sprintleader_email', true);
                if (empty($existing_leader_email)) {
                    update_user_meta($user_id, 'sprintleader_email', $leader_email);
                }
            }
            
            $updated_users[] = $user_id;
        }
        
        // Sync with WP Fusion only for updated users
        if (function_exists('wp_fusion') && !empty($updated_users)) {
            foreach ($updated_users as $user_id) {
                wp_fusion()->user->push_user_meta($user_id);
            }
        }
        
        return array(
            'success' => true,
            'total_updated' => count($updated_users)
        );
    }
    
    /**
     * Get course meta key
     */
    private function get_course_meta_key($course_id) {
        switch ($course_id) {
            case 6298: // R40 Relational Sprint
                return 'sprintr40_start';
            case 6163: // F40 Financial Sprint
                return 'sprintf40_start';
            case 6160: // H40 Health Sprint
                return 'sprinth40_start';
            default:
                return '';
        }
    }
    
    /**
     * Show groups table
     */
    private function show_groups_table() {
        $groups = get_posts(array(
            'post_type' => 'groups',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Group</th>';
        echo '<th>Course</th>';
        echo '<th>Users</th>';
        echo '<th>Start Date</th>';
        echo '<th>Users Need Dates</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($groups as $group) {
            $group_id = $group->ID;
            $course_ids = learndash_group_enrolled_courses($group_id);
            $course_name = !empty($course_ids) ? get_the_title($course_ids[0]) : 'N/A';
            $all_users = learndash_get_groups_user_ids($group_id);
            $start_date = get_post_meta($group_id, '_sprint_start_date', true);
            
            // Count users needing dates
            $need_dates = 0;
            if (!empty($course_ids)) {
                $meta_key = $this->get_course_meta_key($course_ids[0]);
                if (!empty($meta_key)) {
                    foreach ($all_users as $user_id) {
                        if (empty(get_user_meta($user_id, $meta_key, true))) {
                            $need_dates++;
                        }
                    }
                }
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($group->post_title) . ' (#' . $group_id . ')</td>';
            echo '<td>' . esc_html($course_name) . '</td>';
            echo '<td>' . count($all_users) . '</td>';
            echo '<td>' . ($start_date ? esc_html($start_date) : '<span style="color:red;">Not Set</span>') . '</td>';
            echo '<td>' . ($need_dates > 0 ? '<span style="color:red;">' . $need_dates . '</span>' : '<span style="color:green;">0</span>') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}

// Initialize the plugin only if class doesn't exist
if (class_exists('Freedomology_Sprint_Migration')) {
    new Freedomology_Sprint_Migration();
}

} // End of class_exists check
