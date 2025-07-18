<?php
/**
 * ULGM Group Codes Automated Cleanup
 * 
 * Cron-only cleanup for wp_ulgm_group_codes table
 * No admin interface - pure automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class FreedomologyULGMAutomatedCleanup
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->schedule_cleanup();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Schedule cleanup
        add_action('wp', array($this, 'schedule_cleanup'));
        
        // Cleanup event handler
        add_action('freedomology_ulgm_cleanup', array($this, 'run_cleanup'));
        
        // Emergency check on admin login
        add_action('wp_login', array($this, 'check_emergency_cleanup'), 10, 2);
    }

    /**
     * Schedule cleanup events
     */
    public function schedule_cleanup()
    {
        if (!wp_next_scheduled('freedomology_ulgm_cleanup')) {
            // Schedule for every Sunday at 3 AM
            wp_schedule_event(strtotime('next sunday 3:00'), 'weekly', 'freedomology_ulgm_cleanup');
        }
    }

    /**
     * Check if emergency cleanup is needed (only for admin users)
     */
    public function check_emergency_cleanup($user_login, $user)
    {
        // Only check for admin users and once per day
        if (!user_can($user, 'manage_options')) {
            return;
        }
        
        $last_check = get_option('ulgm_last_emergency_check', 0);
        if ((time() - $last_check) < DAY_IN_SECONDS) {
            return;
        }
        
        update_option('ulgm_last_emergency_check', time());
        
        $count = $this->get_ulgm_record_count();
        
        // If more than 1 million records, run emergency cleanup
        if ($count > 1000000) {
            error_log("ULGM Emergency: {$count} records detected, running cleanup");
            $this->run_cleanup(true); // Emergency mode
        }
    }

    /**
     * Main cleanup function
     */
    public function run_cleanup($emergency = false)
    {
        $cleanup_type = $emergency ? 'emergency' : 'weekly';
        $days_old = $emergency ? 3 : 7; // More aggressive in emergency
        
        error_log("ULGM Cleanup: Starting {$cleanup_type} cleanup (removing {$days_old}+ day old records)");
        
        $start_time = microtime(true);
        $total_cleaned = 0;
        
        try {
            // Clean ULGM records
            $total_cleaned = $this->cleanup_ulgm_records($days_old);
            
            // Optimize table if significant cleanup
            if ($total_cleaned > 1000) {
                $this->optimize_ulgm_table();
            }
            
            $duration = round(microtime(true) - $start_time, 2);
            
            error_log("ULGM Cleanup: {$cleanup_type} cleanup completed - Cleaned {$total_cleaned} records in {$duration}s");
            
            // Store cleanup stats
            update_option('ulgm_last_cleanup_stats', array(
                'timestamp' => time(),
                'records_cleaned' => $total_cleaned,
                'duration' => $duration,
                'type' => $cleanup_type,
                'table_size_mb' => $this->get_table_size()
            ));
            
        } catch (Exception $e) {
            error_log("ULGM Cleanup: {$cleanup_type} cleanup failed - " . $e->getMessage());
        }
    }

    /**
     * Cleanup ULGM records
     */
    private function cleanup_ulgm_records($days_old = 7)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ulgm_group_codes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        $total_deleted = 0;
        $batch_size = 2000;
        $max_deletions = 100000; // Safety limit
        
        // Records to delete
        $where_conditions = array(
            // Old records
            $wpdb->prepare("used_date < DATE_SUB(NOW(), INTERVAL %d DAY)", $days_old),
            
            // Old available codes (never used)
            $wpdb->prepare(
                "code_status = 'available' AND used_date < DATE_SUB(NOW(), INTERVAL %d DAY)", 
                max(2, $days_old - 3)
            ),
            
            // Orphaned records
            "group_id IS NULL OR group_id = 0",
            
            // Records for non-existent groups
            "group_id NOT IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'groups' AND post_status = 'publish'
            )"
        );
        
        $where_clause = '(' . implode(') OR (', $where_conditions) . ')';
        
        // Delete in batches
        do {
            $deleted = $wpdb->query("
                DELETE FROM $table_name 
                WHERE $where_clause 
                LIMIT $batch_size
            ");
            
            if ($deleted === false) {
                error_log("ULGM Cleanup: Delete failed - " . $wpdb->last_error);
                break;
            }
            
            $total_deleted += $deleted;
            
            // Safety checks
            if ($deleted == 0 || $total_deleted >= $max_deletions) {
                break;
            }
            
            // Brief pause to avoid overwhelming database
            usleep(25000); // 0.025 seconds
            
        } while ($deleted > 0);
        
        return $total_deleted;
    }

    /**
     * Optimize table after cleanup
     */
    private function optimize_ulgm_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ulgm_group_codes';
        $wpdb->query("OPTIMIZE TABLE $table_name");
    }

    /**
     * Get record count
     */
    private function get_ulgm_record_count()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ulgm_group_codes';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));
    }

    /**
     * Get table size in MB
     */
    private function get_table_size()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ulgm_group_codes';
        
        $size = $wpdb->get_var("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = '$table_name'
        ");
        
        return $size ? floatval($size) : 0;
    }

    /**
     * Deactivation cleanup
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('freedomology_ulgm_cleanup');
        delete_option('ulgm_last_emergency_check');
    }
}

// Initialize the automated cleanup
new FreedomologyULGMAutomatedCleanup();

// Register deactivation hook
register_deactivation_hook(__FILE__, array('FreedomologyULGMAutomatedCleanup', 'deactivate'));