<?php
/**
 * Plugin Name: Freedomology
 * Plugin URI: https://wbcomdesigns
 * Description: A base skeleton plugin for custom code development.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: freedomology
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use uncanny_learndash_groups\SharedFunctions;

class Freedomology {

    /**
     * Constructor to initialize the plugin
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        define( 'FREEDOMOLOGY_VERSION', '1.0.0' );
        define( 'FREEDOMOLOGY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'FREEDOMOLOGY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Include files here when needed
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', [ $this, 'initialize_plugin_features' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'wbcom_enqueue_assets' ] );
        if ( class_exists('GFForms') ) {
            add_action('gform_after_submission_1', [ $this, 'ghl_learning_network_create_group_form_1' ], 10, 2);            
        }
		
		add_action( 'gform_post_add_entry', [ $this, 'wbcom_zapier_gform_entry_created'], 10, 2 );
		add_action( 'ld_added_group_access', [ $this, 'ghl_learning_network_ld_added_group_access' ], 10, 2 );
		add_action( 'ld_removed_group_access', [ $this, 'ghl_learning_network_ld_removed_group_access' ], 10, 2 );
		
		add_action( 'uo_new_group_created', [ $this, 'ghl_learning_network_uo_new_group_created' ], 10, 2 );
		add_action( 'ld_added_leader_group_access', [ $this, 'ghl_learning_network_added_leader_group_access' ], 10, 2 );
		add_action( 'ld_removed_leader_group_access', [ $this, 'ghl_learning_network_removed_leader_group_access' ], 10, 2 );
		add_action( 'ulgm_after_add_invite_form_fields', array( $this, 'wbcom_add_invite_form_fields' ), 10, 2 );
		add_filter( 'gform_field_validation_2_8', array( $this, 'wbcom_validate_invitation_code' ), 10, 4 );
		add_action( 'gform_user_registered', array( $this, 'wbcom_cleanup_user_signup' ), 10, 4 );
        // Added new hook for invite link registration
        add_action( 'gform_user_registered', array( $this, 'wbcom_handle_invite_registration' ), 9, 3 );
		add_action( 'bp_init', array( $this, 'wbcom_disable_activation_email' ) );
		add_action( 'wp_head', array( $this, 'wbcom_style_invite_with_link' ) );
		add_action( 'wp_footer', array( $this, 'wbcom_scripts_invite_with_link' ) );
		add_filter( 'learndash_focus_mode_comments', array( $this, 'wbcom_learndash_enable_comments_focus_mode' ), 10, 2 );
		add_filter( 'comments_array', array( $this, 'wbcom_filter_comments_for_same_group_users' ), -10, 2 );
		add_shortcode( 'signup_course', array( $this, 'wbcom_render_signup_course' ) );
		add_shortcode( 'sprint_name', array( $this, 'wbcom_render_sprint_name' ) );
		add_filter( 'body_class', array( $this, 'wbcom_manage_body_classes' ) );
        // Added new filter for seat validation
        add_filter( 'ulgm_validate_seat_availability', array( $this, 'skip_seat_validation_for_invite_links' ), 10, 2 );
		

		add_filter(
			'gform_is_feed_asynchronous',
			function ( $is_asynchronous, $feed, $entry, $form ) {
				if ( ! $is_asynchronous || rgar( $feed, 'addon_slug' ) !== 'gravityformsuserregistration' ) {
					return $is_asynchronous;
				}

				return gf_user_registration()->is_update_feed( $feed ) ? $is_asynchronous : false;
			},
			10,
			4
		);
    }

    /**
     * Initialize plugin features
     *
     * This function is responsible for setting up custom functionalities
     * and loading any additional resources required by the plugin.
     */
    public function initialize_plugin_features() {
        // Add custom post types, taxonomies, or other initialization code here.
    }


    public function wbcom_enqueue_assets() {
    	wp_enqueue_style( 'freedomology-core', FREEDOMOLOGY_PLUGIN_URL . 'assets/css/freedomology-core-style.css', array(), time(), 'all' );
    }

    /**
     * Create a Group Using Gravity Form via Uncanny LearnDash Groups Plugin
     */
    public function ghl_learning_network_create_group_form_1($entry, $form) {

        $first_name   = rgar($entry, '1');
        $last_name    = rgar($entry, '3');
        $email        = rgar($entry, '4');
        $phone_number = rgar($entry, '5');
        $course_id    = rgar($entry, '7');
        $start_date   = rgar($entry, '8');
        $total_seats  = rgar($entry, '11');
        $group_name   = rgar($entry, '9');
        $group_image  = '';

        $customer_id = '';
        if ( is_user_logged_in() ) {
            $customer    = wp_get_current_user();
            $customer_id = $customer->ID;
        }
		
		/* 
		 * Get the Course id from the Course Title.
		 */
		if( is_string( $course_id ) ) {
			global $wpdb;
			$sql 	= $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s", $course_id, 'sfwd-courses');
			$course_id 	= $wpdb->get_var( $sql );
		}
		
        $args = array(
            'ulgm_group_leader_first_name' => $first_name,
            'ulgm_group_leader_last_name'  => $last_name,
            'ulgm_group_leader_email'      => $email,
            'ulgm_group_name'              => $group_name,
            'ulgm_group_total_seats'       => $total_seats,
            'ulgm_group_courses'           => [$course_id],
            'ulgm_group_image'             => $group_image,
            'ulgm_group_customer_id'       => $customer_id,
        );
		if ( class_exists('uncanny_learndash_groups\ProcessManualGroup') ) {
			add_filter('ulgm_filter_var_is_front_end', function(){ return 'yes'; });
			add_filter( 'pre_user_login', [$this, 'ghl_learning_network_pre_user_login'] );
			$group_id = \uncanny_learndash_groups\ProcessManualGroup::process( $args, $_POST );
		}
    }
	
	public function ghl_learning_network_pre_user_login( $sanitized_user_login ) {
		return strstr($sanitized_user_login, '@', true);
	}
	
	/**
     * Assign tag to the user when the user add group
     */
	public function ghl_learning_network_ld_added_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', false );
			}		
		}
	}
	
	/**
     * Remove tag to the user when the user remove group
     */
	public function ghl_learning_network_ld_removed_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				do_action( 'learndash_update_course_access', $user_id, $course_id, '', true );
			}		
		}
	}
	
	function ghl_learning_network_uo_new_group_created( $group_id, $group_leader_id ) {
		$this->ghl_learning_network_added_leader_group_access( $group_leader_id, $group_id );		
	}
	public function ghl_learning_network_added_leader_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				$tag_name = trim( sanitize_text_field( wp_unslash( get_the_title($course_id) . ' Leader' ) ) );
				$tag_id 	= wpf_get_tag_id( $tag_name );
				if( $tag_id == false ) {
					$tag_id   	 = wp_fusion()->crm->add_tag( $tag_name );
				}				
				wp_fusion()->user->apply_tags( [$tag_id] , $user_id );				
			}		
		}
	}
	
	public function ghl_learning_network_removed_leader_group_access( $user_id, $group_id ) {
		$group_course_ids = learndash_group_enrolled_courses( $group_id );		
		if( ! empty( $group_course_ids ) ) {
			foreach( $group_course_ids as $course_id ) {
				$tag_name 	= trim( sanitize_text_field( wp_unslash( get_the_title($course_id) . ' Leader' ) ) );				
				$tag_id 	= wpf_get_tag_id( $tag_name );				
				if ( $tag_id !=  false ) {					
					wp_fusion()->user->remove_tags( [$tag_id], $user_id );
				}
			}		
		}		
	}

    /**
     * Generate a permanent invite link for a LearnDash group
     * 
     * @param int $group_id The LearnDash group ID
     * @return string The permanent invite URL
     */
    public function generate_permanent_group_invite_link($group_id) {
        // Create a unique but permanent hash for this group
        $hash = wp_hash($group_id . get_option('site_secret_key', ''));
        $hash = substr($hash, 0, 12); // Shortened for URL friendliness
        
        // Get the first course in the group
        $group_course_ids = learndash_group_enrolled_courses($group_id);
        $course_id = !empty($group_course_ids) ? $group_course_ids[0] : 0;
        
        $invite_url = add_query_arg(
            array(
                'group_id' => $group_id,
                'course_id' => $course_id,
                'invite_key' => $hash,
            ),
            home_url('/sign-up/')
        );
        
        return $invite_url;
    }

    /**
     * Validate a group invitation key
     * 
     * @param int $group_id The LearnDash group ID
     * @param string $invite_key The invitation key to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_group_invite_key($group_id, $invite_key) {
        $expected_key = substr(wp_hash($group_id . get_option('site_secret_key', '')), 0, 12);
        return $invite_key === $expected_key;
    }

    /**
     * Process a user signup via invite link
     * 
     * @param int $user_id The WordPress user ID
     * @param int $group_id The LearnDash group ID
     * @param string $invite_key The invitation key
     * @return bool True if successful, false otherwise
     */
    public function wbcom_process_invite_signup($user_id, $group_id, $invite_key) {
        // Validate the invite key
        if (!$this->validate_group_invite_key($group_id, $invite_key)) {
            return false;
        }
        
        // Check if the group has available seats
        $remaining_seats = ulgm()->group_management->seat->remaining_seats($group_id);
        if ($remaining_seats <= 0) {
            return false;
        }
        
        // Add user to the group directly
        $result = SharedFunctions::set_user_to_group($user_id, $group_id);
        
        // Store record that this user was added via invite link
        if ($result) {
            update_user_meta($user_id, '_joined_via_group_invite', $group_id);
        }
        
        return $result;
    }

    /**
     * Handle registration from invite links
     * 
     * @param int $user_id The new user ID
     * @param array $feed The form feed
     * @param array $entry The form entry
     */
    public function wbcom_handle_invite_registration($user_id, $feed, $entry) {
        // Check if this is an invite registration
        if (isset($_GET['group_id']) && isset($_GET['invite_key'])) {
            $group_id = intval($_GET['group_id']);
            $invite_key = sanitize_text_field($_GET['invite_key']);
            
            // Process the invite signup
            $this->wbcom_process_invite_signup($user_id, $group_id, $invite_key);
        }
    }

    /**
     * Skip seat validation for invite links
     * 
     * @param bool $validate Whether to validate seat availability
     * @param int $group_id The LearnDash group ID
     * @return bool Whether to validate seat availability
     */
    public function skip_seat_validation_for_invite_links($validate, $group_id) {
        if (isset($_GET['invite_key']) && $this->validate_group_invite_key($group_id, $_GET['invite_key'])) {
            return false; // Skip validation
        }
        return $validate;
    }

	public function wbcom_add_invite_form_fields( $group_id, $object ) {
        // Generate permanent invite link
        $invite_url = $this->generate_permanent_group_invite_link($group_id);
        
        ?>
        <div class="uo-row" id="uo_add_user_invite_url" style="display: none;">
            <label for="wbcom_invite_url">
                <div class="uo-row__title">
                    <?php _e('Invite With Link', 'wbcom'); ?>
                </div>
            </label>
            <div class="uo_add_user_invite_url_block">
                <input class="uo-input" type="url" name="wbcom_invite_url" id="wbcom_invite_url" value="<?php echo $invite_url; ?>" readonly />
                <button class="uo-btn" type="button" onclick="copyInviteUrl()">Copy</button>
            </div>
            <span id="copyTooltip" style="visibility: hidden;">URL Copied!</span>
        </div>
        <?php
	}

	public function wbcom_validate_invitation_code( $result, $value, $form, $field ) {
		global $bp;
		
        // Check if this is an invite link registration
        if (isset($_GET['invite_key']) && isset($_GET['group_id'])) {
            $group_id = intval($_GET['group_id']);
            $invite_key = sanitize_text_field($_GET['invite_key']);
            
            // Validate the invite key
            if ($this->validate_group_invite_key($group_id, $invite_key)) {
                // Check seat availability
                $remaining_seats = ulgm()->group_management->seat->remaining_seats($group_id);
                if ($remaining_seats <= 0) {
                    $result['is_valid'] = false;
                    $result['message'] = esc_html__('No seats available in this group', 'uncanny-learndash-groups');
                } else {
                    $result['is_valid'] = true;
                }
                return $result;
            }
        }
        
        // Standard code validation for non-invite link registrations
		$code = $value;
		if ( '' === $code && 'no' === $code ) {
			$result['is_valid'] = false;
			$result['message'] = esc_html__( 'Registration code is empty', 'uncanny-learndash-groups' );
		}

		$code_details = SharedFunctions::is_key_available( $code );

		if ( 'failed' === $code_details['result'] ) {
			if ( 'invalid' === $code_details['error'] ) {
				$result['is_valid'] = false;
				$result['message'] = esc_html__( 'Invalid registration code', 'uncanny-learndash-groups' );
			} elseif ( 'existing' === $code_details['error'] ) {
				$result['is_valid'] = false;
				$result['message'] = esc_html__( 'Code already redeemed', 'uncanny-learndash-groups' );
			} elseif ( 'seat_not_available' === $code_details['error'] ) {
				$result['is_valid'] = false;
				$result['message'] = esc_html__( 'Seat not available', 'uncanny-learndash-groups' );
			}
		}

		return $result;
	}

	public function wbcom_cleanup_user_signup( $user_id, $feed, $entry, $user_pass ) {
		$form = GFFormsModel::get_form_meta( $entry['form_id'] );
		$meta = $feed['meta'];

		if ( ! $user_pass ) {
			$user_pass = gf_user_registration()->get_meta_value( 'password', $meta, $form, $entry );
		}

        // Check if this is an invite link registration
        if (isset($_GET['group_id']) && isset($_GET['invite_key'])) {
            $group_id = intval($_GET['group_id']);
            $invite_key = sanitize_text_field($_GET['invite_key']);
            
            // Process the invite signup
            $this->wbcom_process_invite_signup($user_id, $group_id, $invite_key);
        } else {
            // Standard code registration
            $code = isset($entry[8]) ? $entry[8] : '';
            $group_id = isset($entry[6]) ? $entry[6] : 0;

            if (empty($code)) {
                return;
            }

            // Update user meta with the used code
            update_user_meta($user_id, '_ulgm_code_used', $code);

            // Assign user to group
            $result = ulgm()->group_management->set_user_to_code($user_id, $code, SharedFunctions::$not_started_status, $group_id);

            if ($result) {
                SharedFunctions::set_user_to_group($user_id, $group_id);
            }
        }

		// Get user data
		$user = get_userdata( $user_id );

		if ( $user ) {
			$creds = array(
				'user_login'    => $user->user_login,
				'user_password' => $user_pass,
				'remember'      => true,
			);

			add_filter( 'check_password', '__return_true' );

			$login_user = wp_signon( $creds, false );

			remove_filter( 'check_password', '__return_true' );
		}
	}

	function wbcom_disable_activation_email() {
		remove_action( 'bp_core_signup_send_validation_email', 'bp_core_signup_send_validation_email' );
	}


	// Automatically activate user signups
	public function wbcom_auto_activate_user( $user_login ) {
		global $wpdb;

		// Get the signup entry using BP_Signup class
		$signup_data = BP_Signup::get(
			array(
				'user_login' => $user_login,
				'active'     => 0, // Only look for inactive users
			)
		);

		// Check if the signup exists
		if ( ! empty( $signup_data['signups'] ) ) {
			$signup = $signup_data['signups'][0]; // Assuming the first result is correct

			// Activate the signup using BuddyPress' activation method
			$activation_result = BP_Signup::activate( array( $signup->signup_id ) );

			if ( isset( $activation_result['activated'] ) && ! empty( $activation_result['activated'] ) ) {
				$activated_user = $activation_result['activated'][0];

				// Log the successful activation
				error_log( 'User successfully activated: ' . $activated_user->ID );
			} else {
				error_log( 'Error activating user: ' . $signup->user_login );
			}
		} else {
			error_log( 'User signup not found or already activated: ' . $user_login );
		}
	}


	public function wbcom_style_invite_with_link() {
		$group_management_page_id      = ulgm()->group_management->pages->get_group_management_page_id();
		if( $group_management_page_id == get_the_ID() ) {
			?>
			<style type="text/css">
				.uo_add_user_invite_url_block{
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 10px;
				}
			</style>
			<?php
		}
    	
    }	


    public function wbcom_scripts_invite_with_link() {
    	$group_management_page_id      = ulgm()->group_management->pages->get_group_management_page_id();
		if( $group_management_page_id == get_the_ID() ) {
			?>
			<script type="text/javascript">

				document.getElementById("send_enrollment").addEventListener("click", function() {
					document.getElementById("uo_add_user_invite_url").style.display = "block";
					document.getElementById("uo_add_user_first_name").style.display = "none";
					document.getElementById("uo_add_user_last_name").style.display = "none";
					document.getElementById("uo_add_user_email").style.display = "none";
				});
				document.getElementById("add_invite").addEventListener("click", function() {
					document.getElementById("uo_add_user_invite_url").style.display = "none";
					document.getElementById("uo_add_user_first_name").style.display = "block";
					document.getElementById("uo_add_user_last_name").style.display = "block";
					document.getElementById("uo_add_user_email").style.display = "block";
				});
				function copyInviteUrl() {
					var copyText = document.getElementById("wbcom_invite_url");
					copyText.select();
					copyText.setSelectionRange(0, 99999); // For mobile devices
					document.execCommand("copy");

					var tooltip = document.getElementById("copyTooltip");
					tooltip.style.visibility = "visible";
					setTimeout(function() {
						tooltip.style.visibility = "hidden";
					}, 2000);
				}
			</script>
			<?php
		}
    	
    }

    public function wbcom_learndash_enable_comments_focus_mode( $status, $object ) {
		if ( 'sfwd-lessons' === $object->post_type || 'sfwd-topic' === $object->post_type ) {
			$status = 'open';

		}
		return $status;
	}


	public function wbcom_filter_comments_for_same_group_users( $comments, $post_id ) {
	    // Check if the user is logged in
	    if ( ! is_user_logged_in() ) {
	        return $comments; // Return all comments if the user is not logged in
	    }

	    // Get the current user object
	    $current_user = wp_get_current_user();

	    // Check if the user is an administrator
	    if ( in_array( 'administrator', (array) $current_user->roles ) ) {
	        return $comments; // Return all comments for admin users
	    }

	    // Check if the post type is 'sfwd-lessons' or 'sfwd-topic'
	    if ( 'sfwd-lessons' === get_post_type( $post_id ) || 'sfwd-topic' === get_post_type( $post_id ) ) {
	        // Get the groups of the logged-in user
	        $login_user_groups = learndash_get_users_group_ids( get_current_user_id(), false );

	        // Initialize an array to hold filtered comments
	        $filtered_comments = array();

	        // Loop through each comment
	        foreach ( $comments as $comment ) {
	            // Get the comment author's user ID
	            $comment_author = $comment->user_id;

	            // Get the groups of the comment author
	            $comment_author_groups = learndash_get_users_group_ids( $comment_author, false );

	            // Check if there is an intersection between the logged-in user's groups and the comment author's groups
	            if ( ! empty( array_intersect( $login_user_groups, $comment_author_groups ) ) ) {
	                // If they share a group, add the comment to the filtered array
	                $filtered_comments[] = $comment;
	            }
	        }

	        // Return only the comments from the same group
	        return $filtered_comments;
	    }

	    // Return all comments for other post types
	    return $comments;
	}



	public function wbcom_render_signup_course() {
	    // Get the course ID from the query parameter and sanitize it
	    $course_id = isset($_GET['course_id']) ? sanitize_text_field($_GET['course_id']) : '';

	    // Start output buffering
	    ob_start();

	    // Check if the course ID is valid
	    if ( ! empty( $course_id ) && get_post_type( $course_id ) === 'sfwd-courses' ) { // Assuming 'sfwd-courses' is the post type for courses
	        ?>
	        <div class="course-list-freedomology">
	            <div class="course-list-img">
	                <img src="<?php echo get_the_post_thumbnail_url( $course_id ); ?>" alt="<?php echo esc_attr( get_the_title( $course_id ) ); ?>"/>
	            </div>
				<div class="course-list-content">
					<h3 class="course-list-title"><?php echo esc_html( get_the_title( $course_id ) ); ?></h3>
					<div class='course-short-content'><?php echo wp_trim_words( get_post_field( 'post_content', $course_id ), 20  ); ?></div>
					<div class="learn-more-btn">
						<a href="<?php echo esc_url( get_the_permalink( $course_id ) ); ?>">Learn More</a>
					</div>
				</div>
	        </div>
	        <?php
	    } else {
	        // Optionally, you can display a message if the course ID is invalid
	        echo '<p>No course found.</p>';
	    }

	    // Get the contents of the output buffer and clean it
	    $output = ob_get_clean(); // Use ob_get_clean() to get the buffer contents and clean it

	    return $output;
	}

	public function wbcom_render_sprint_name() {
		$group_id = isset($_GET['group_id']) ? sanitize_text_field($_GET['group_id']) : '';
		$return = '';

		if( ! empty( $group_id ) ) {
			return get_the_title( $group_id );
		}
	}


	public function wbcom_manage_body_classes( $classes ) {
		if( is_page( 'sign-up' ) ) {
			if( isset( $_GET['group_id'] ) && isset( $_GET['course_id'] ) ) {
				$classes[] = sanitize_title( get_the_title( $_GET['course_id'] ) );
			}
		}

		return $classes;
	}

	public function wbcom_zapier_gform_entry_created( $entry, $form ) {
		$form_id = 1; // Replace with your actual form ID		
	    if ( (int) $entry['form_id'] === $form_id ) {
			error_log( print_r( $entry, true ) . "\r\n", 3, plugin_dir_path( __FILE__ ) . '/error_log.txt' );
			
	        do_action( 'gform_after_submission_' . $entry['form_id'], $entry, $form );
	    }
	}
}

// Initialize the plugin
new Freedomology();