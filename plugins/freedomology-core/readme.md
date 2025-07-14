# Freedomology Plugin

A comprehensive WordPress plugin designed specifically for LearnDash group management, invitation tracking, and sprint-based learning systems. Built for the Freedomology platform with advanced analytics and user management features.

## 🚀 Features Overview

### 📊 **Invitation Link Tracking System**
- **Real-time Analytics**: Track clicks, conversions, and conversion rates for group invitation links
- **UTM Parameter Support**: Automatic UTM tracking for campaign attribution
- **Copy Action Logging**: Monitor when group leaders share invitation links
- **Session-based Tracking**: Prevent duplicate click counting
- **IP & User Agent Logging**: Detailed visitor information for analytics

### 🎯 **Group Management & Invitations**
- **Permanent Invitation Links**: Generate secure, reusable invitation URLs for each group
- **Security Code Validation**: Hash-based authentication prevents unauthorized access
- **Multi-Sprint Support**: Support for R40 (Relational), F40 (Financial), and H40 (Health) sprints
- **Automated Group Assignment**: Seamless user enrollment into groups and courses
- **Leader Email Tracking**: Automatic group leader association for new members

### 🔐 **Lesson Unlock System**
- **Time-based Lesson Release**: Control lesson access based on sprint start dates and delays
- **Group Leader Sprint Dates**: Individual sprint start dates per group leader
- **First Lesson Exception**: Always unlock the first two lessons immediately
- **Visual Lock Indicators**: Clear UI showing when lessons will unlock
- **Navigation Integration**: Locked lesson indicators in course navigation

### 📈 **Advanced Analytics & Reporting**
- **Admin Dashboard**: Comprehensive analytics interface under Groups → Invitation Stats
- **Real-time Statistics**: Live click and conversion data in invitation widgets
- **Daily Breakdown**: Day-by-day performance metrics
- **Group-specific Metrics**: Individual statistics for each group
- **Conversion Attribution**: Link signups back to specific invitation campaigns

### 🎨 **Enhanced User Interface**
- **Elementor Integration**: Custom Elementor widget for group invitation management
- **WordPress Widget**: Traditional WordPress widget support
- **Responsive Design**: Mobile-friendly interfaces
- **Real-time Feedback**: Instant visual feedback for user actions
- **Copy History**: Recent link sharing activity display

### 🔄 **WP Fusion Integration**
- **Automatic Tag Management**: Apply course-specific tags based on group enrollment
- **Leader Tag Assignment**: Special tags for sprint leaders (sprint-r40-leader-active, etc.)
- **Meta Data Sync**: Automatic synchronization of sprint start dates
- **Real-time Updates**: Immediate tag and meta data updates

### 📱 **Social & Sharing Features**
- **Video Sharing Buttons**: YouTube video sharing for lessons and topics
- **Social Login Integration**: BuddyBoss social login support
- **Custom Login URLs**: Clean `/login` URLs instead of `/wp-login.php`
- **Profile Redirects**: Automatic redirection to user profiles for logged-in users

### 🔄 **Micro Functions & Utilities**
- **Auto Login After Registration**: Seamless user experience post-signup
- **Home to Profile Redirect**: Redirect logged-in users from homepage to news feed
- **Course Auto-Redirect**: Automatic redirection to first lesson when accessing courses
- **Login URL Rewriting**: Clean login URLs (`/login` instead of `/wp-login.php`)
- **Custom Body Classes**: Dynamic CSS classes based on user enrollment and page context
- **Email Validation**: Enhanced email validation with existing user detection
- **User Login Generation**: Clean username generation from email addresses
- **Activation Email Disabling**: Streamlined signup without email verification
- **Comment Filtering**: Group-based comment visibility in lessons
- **Focus Mode Comments**: Enable comments in LearnDash focus mode

## 🛠 Installation

### Prerequisites
- WordPress 5.0 or higher
- LearnDash LMS plugin
- Uncanny LearnDash Groups plugin
- Elementor (optional, for enhanced widgets)
- BuddyBoss (optional, for social features)
- WP Fusion (optional, for CRM integration)

### Installation Steps

1. **Upload Plugin Files**
   ```bash
   wp-content/plugins/freedomology-core/
   ```

2. **Activate Required Plugins**
   - LearnDash LMS
   - Uncanny LearnDash Groups
   - Elementor (if using widgets)

3. **Activate Freedomology Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Freedomology"

4. **Database Setup**
   - Database tables are created automatically
   - No manual database configuration required

## 📋 Configuration

### Group Sprint Setup

1. **Create Sprint Courses**
   - R40 Relational Sprint (Course ID: 6298)
   - F40 Financial Sprint (Course ID: 6163)
   - H40 Health Sprint (Course ID: 6160)

2. **Configure Group Leaders**
   - Create groups using Gravity Form ID 1
   - Group leaders receive course-specific start date meta:
     - `sprintr40_start` - R40 sprint start date
     - `sprintf40_start` - F40 sprint start date
     - `sprinth40_start` - H40 sprint start date

3. **Set Up Lesson Delays**
   - Edit individual lessons in WordPress admin
   - Set "Lesson Unlock Delay" in the meta box
   - First two lessons are always unlocked immediately

### Invitation Link Configuration

1. **Group Invitation URLs**
   ```
   https://yoursite.com/sign-up/?group_id=123&code=abc123&course_id=6298&utm_source=group_invitation&utm_medium=link_share&utm_campaign=group_123
   ```

2. **Security Codes**
   - Automatically generated using `wp_hash()`
   - 12-character unique codes per group
   - Permanent and reusable

3. **Tracking Parameters**
   - UTM parameters added automatically
   - Custom campaign tracking
   - Attribution-ready URLs

## 🎯 Usage

### For Group Leaders

1. **Access Invitation Widget**
   - Available in Elementor or WordPress widgets
   - Real-time statistics display
   - Copy invitation links with tracking

2. **Monitor Performance**
   - View clicks, conversions, and rates
   - See recent copy activity
   - Track link sharing effectiveness

3. **Group Creation via Form**
   - Use Gravity Form ID 1 to create groups
   - Automatic group leader assignment
   - Sprint start date configuration

### For Administrators

1. **Analytics Dashboard**
   - Navigate to Groups → Invitation Stats
   - Filter by group and date range
   - View detailed charts and metrics

2. **Group Management**
   - Monitor all group invitation performance
   - Export data for reporting
   - Track conversion funnels

3. **Course Management**
   - Add video links to courses via meta boxes
   - Configure lesson unlock delays
   - Monitor course enrollment statistics

### For Members

1. **Sprint Enrollment**
   - Use invitation links to join sprints
   - Automatic group and course enrollment
   - Leader email association

2. **Lesson Access**
   - Time-based lesson unlocking
   - Clear unlock date indicators
   - Progress tracking

3. **Seamless User Experience**
   - Auto-login after registration
   - Automatic redirects to appropriate content
   - Group-filtered comments and interactions

## 🔄 Micro Functions & System Behaviors

### **Automatic Redirects**

#### Homepage Redirect
```php
// Logged-in users (non-admins) automatically redirect from homepage to news feed
if (is_user_logged_in() && is_front_page() && !current_user_can('manage_options')) {
    wp_redirect(site_url('/news-feed/'));
}
```

#### Course Auto-Redirect
```php
// When accessing a course page, automatically redirect to first lesson
// (Currently commented out but available)
$first_lesson_url = get_permalink($first_lesson_id);
// JavaScript-based redirect to first lesson
```

#### Login Redirect
```php
// Custom login redirect behavior
if (!empty($request)) {
    $redirect_to = $request; // Redirect to requested page
} else {
    $redirect_to = home_url('/news-feed/'); // Default to news feed
}
```

### **URL Management**

#### Clean Login URLs
```php
// Converts /wp-login.php to /login
add_rewrite_rule('^login/?

## 📊 Tracking & Analytics

### Impression Tracking
- **What**: Records when invitation links are clicked
- **Data Captured**: IP, user agent, referrer, UTM parameters, timestamp
- **Prevention**: Duplicate session tracking prevention

### Conversion Tracking
- **What**: Records successful signups from invitation links
- **Attribution**: Links conversions back to original clicks
- **Types**: New user registrations and existing user group joins

### Statistics Available
- Total clicks per group
- Total conversions per group
- Conversion rates
- Daily breakdown
- Recent activity logs

## 🔧 Developer Information

### Hooks & Filters

```php
// Enable/disable lesson unlock system
add_filter('freedomology_lesson_unlock_enabled', '__return_false');

// Course-specific unlock control
add_filter('freedomology_lesson_unlock_enabled_for_course', function($enabled, $course_id) {
    return $course_id === 6298; // Only for R40
}, 10, 2);

// Skip admin unlock checks
add_filter('freedomology_lesson_unlock_skip_admins', '__return_true');

// Invitation URL modifications
add_filter('freedomology_invitation_url', function($url, $group_id) {
    // Modify invitation URLs
    return $url;
}, 10, 2);
```

### Custom Actions

```php
// Fired when invitation link is converted
do_action('freedomology_invitation_converted', $user_id, $tracking_id);

// Fired when invitation link is copied
do_action('freedomology_invitation_copied', $group_id, $user_id, $url);

// LearnDash course access updates
do_action('learndash_update_course_access', $user_id, $course_id, '', false);

// User login trigger
do_action('wp_login', $user->user_login, $user);
```

### Form Hooks

```php
// Gravity Forms submission hooks
add_action('gform_after_submission_1', 'group_creation_handler'); // Group creation
add_action('gform_after_submission_4', 'existing_user_join_handler'); // Existing user join
add_action('gform_user_registered', 'new_user_cleanup_handler'); // New user registration

// Form validation hooks
add_filter('gform_field_validation_2', 'validate_invitation_code'); // Code validation
add_filter('gform_field_validation_2_2', 'validate_email_with_message'); // Email validation
```

### LearnDash Integration Hooks

```php
// Group access management
add_action('ld_added_group_access', 'handle_group_access_added');
add_action('ld_removed_group_access', 'handle_group_access_removed');
add_action('ld_added_leader_group_access', 'handle_leader_access_added');
add_action('ld_removed_leader_group_access', 'handle_leader_access_removed');

// Uncanny Groups integration
add_action('uo_new_group_created', 'handle_new_group_created');
add_action('ulgm_after_add_invite_form_fields', 'add_custom_invite_fields');
```

### Content & Navigation Hooks

```php
// Comment system
add_filter('learndash_focus_mode_comments', 'enable_focus_mode_comments');
add_filter('comments_array', 'filter_group_comments');

// Course navigation
add_filter('learndash_course_nav_items', 'modify_lesson_nav_items');

// Content filtering
add_filter('learndash_content', 'check_lesson_access_before_content');

// Body classes
add_filter('body_class', 'add_dynamic_body_classes');
```

### URL & Redirect Hooks

```php
// Login system
add_filter('login_redirect', 'custom_login_redirect');
add_filter('site_url', 'filter_login_urls'); 

// Template redirects
add_action('template_redirect', 'redirect_home_to_profile');
add_action('template_redirect', 'auto_redirect_to_first_lesson'); // Available

// URL rewriting
add_action('init', 'add_login_rewrite_rule');
add_filter('request', 'filter_login_request');
```

### Admin & Meta Hooks

```php
// Meta boxes
add_action('add_meta_boxes', 'add_lesson_delay_meta_box');
add_action('add_meta_boxes', 'add_course_meta_boxes');

// Meta saving
add_action('save_post_sfwd-lessons', 'save_lesson_delay_meta');
add_action('save_post_sfwd-courses', 'save_course_meta');

// Admin interface
add_action('admin_menu', 'add_tracking_admin_menu');
add_action('admin_enqueue_scripts', 'enqueue_admin_tracking_scripts');
```

### Micro Function Hooks & Filters

```php
// Email and activation
add_action('bp_init', 'disable_activation_email');
add_filter('pre_user_login', 'clean_username_from_email');

// Asset loading
add_action('wp_enqueue_scripts', 'enqueue_plugin_assets');

// Social integration
add_action('bp_init', 'add_social_login_to_popup');

// Video sharing
add_action('learndash-content-tabs-content-after', 'add_video_share_buttons');

// Widget registration
add_action('widgets_init', 'register_invitation_widget');
add_action('elementor/widgets/register', 'register_elementor_widget');
```

### Database Tables

#### `wp_freedomology_invitation_tracking`
- Stores click and conversion data
- Includes UTM parameters and user information
- Automatic cleanup of old records

## 🎨 Customization

### CSS Styling
```css
/* Invitation widget styling */
.ldgiu-invitation-container {
    /* Custom styles */
}

/* Lesson lock notifications */
.wbcom-lesson-locked-notice {
    /* Custom lock message styles */
}

/* Course list styling */
.course-list-freedomology {
    /* Course display styles */
}
```

### JavaScript Customization
```javascript
// Track custom events
document.addEventListener('freedomology_link_copied', function(event) {
    // Custom tracking logic
});
```

## 🔒 Security Features

### Invitation Security
- Hash-based group codes
- Session validation
- IP tracking for fraud prevention
- Nonce verification for AJAX requests

### Access Control
- User capability checks
- Group membership validation
- Lesson unlock date verification
- Admin bypass controls

## 🚦 Troubleshooting

### Common Issues

1. **Database Tables Not Created**
   - Deactivate and reactivate plugin
   - Check WordPress error logs
   - Verify database permissions

2. **Tracking Not Working**
   - Ensure sessions are enabled
   - Check AJAX URL configuration
   - Verify nonce generation

3. **Lesson Unlock Issues**
   - Check group leader start dates
   - Verify course ID mappings (6298, 6163, 6160)
   - Review lesson delay settings

4. **Invitation Links Invalid**
   - Check group ID and course ID
   - Verify security code generation
   - Test URL parameters

5. **Redirect Issues**
   - Check rewrite rules are flushed
   - Verify login redirect configuration
   - Test with different user roles

6. **Form Submission Problems**
   - Verify Gravity Forms field IDs
   - Check form validation rules
   - Review AJAX submission handling

7. **WP Fusion Integration Issues**
   - Verify WP Fusion is active and configured
   - Check tag creation permissions
   - Review meta field mappings

8. **Social Login Problems**
   - Ensure BuddyBoss SSO is configured
   - Check social app credentials
   - Verify login popup integration

### Debug Mode
```php
// Enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs in wp-content/debug.log
// Look for "Freedomology:" prefixed messages
```

### Micro Function Debugging

#### Redirect Debugging
```php
// Add to functions.php temporarily to debug redirects
add_action('template_redirect', function() {
    if (is_user_logged_in() && is_front_page()) {
        error_log('Homepage redirect triggered for user: ' . get_current_user_id());
    }
}, 1);
```

#### Form Field Debugging
```php
// Debug form submissions
add_action('gform_pre_submission', function($form) {
    error_log('Form submitted: ' . print_r($_POST, true));
});
```

#### Session Debugging
```php
// Check session data
add_action('wp_footer', function() {
    if (is_admin()) return;
    error_log('Session data: ' . print_r($_SESSION, true));
});
```

#### URL Rewrite Debugging
```php
// Check rewrite rules
add_action('admin_init', function() {
    global $wp_rewrite;
    error_log('Rewrite rules: ' . print_r($wp_rewrite->rules, true));
});
```

## 📈 Performance Considerations

### Database Optimization
- Indexed tracking table for fast queries
- Automatic cleanup of old tracking data
- Efficient query patterns

### Caching Compatibility
- Compatible with most caching plugins
- Session-based tracking considerations
- AJAX request optimization

## 🔄 Updates & Maintenance

### Version History
- v1.0.0 - Initial release with core features
- Database version tracking
- Automatic migration support

### Backup Recommendations
- Regular database backups
- Plugin file backups before updates
- Test updates on staging first

## 🤝 Support

### Documentation
- Inline code documentation
- WordPress coding standards
- PHPDoc comments

### Error Logging
- Comprehensive error logging
- Debug information available
- Admin notices for issues

## 📄 License

GPL2 or later - Compatible with WordPress licensing requirements.

---

**Built with ❤️ for the Freedomology learning platform**

For technical support or feature requests, please contact the development team.

## 📚 Quick Reference

### Key File Locations
```
plugins/freedomology-core/
├── freedomology.php                    # Main plugin file
├── elements/
│   ├── invitation-tracking-system.php # Tracking system
│   ├── learndash-group-invitation-url.php # Enhanced invitation widget
│   ├── lesson-unlock-system.php       # Lesson timing control
│   ├── wordpress-widget.php           # WordPress widget
│   └── includes/
│       └── elementor-widget.php        # Elementor integration
└── assets/
    └── css/
        └── freedomology-core-style.css # Plugin styles
```

### Important Course IDs
- **6298**: R40 Relational Sprint
- **6163**: F40 Financial Sprint  
- **6160**: H40 Health Sprint

### Key Form IDs
- **Form 1**: Group creation by leaders
- **Form 2**: New user registration via invitation
- **Form 4**: Existing user joining sprints

### Meta Field Reference
```php
// User meta fields
'sprintr40_start'    // R40 sprint start date
'sprintf40_start'    // F40 sprint start date  
'sprinth40_start'    // H40 sprint start date
'sprintleader_email' // Group leader email
'_ulgm_code_used'    // Invitation code used

// Group meta fields
'_group_leader_email'      // Leader email
'_group_leader_first_name' // Leader first name
'_group_leader_last_name'  // Leader last name
'_sprint_start_date'       // Group sprint start
'_sprint_course_id'        // Associated course

// Lesson meta fields
'_lesson_delay_days'       // Days to wait before unlock

// Course meta fields
'_video_link'              // Course intro video URL
```

### Database Tables
```sql
-- Invitation tracking
wp_freedomology_invitation_tracking
  - id, group_id, course_id, invitation_code
  - click_timestamp, converted, conversion_timestamp
  - user_id, ip_address, user_agent, referrer
  - utm_source, utm_medium, utm_campaign
```

---

**Built with ❤️ for the Freedomology learning platform**

For technical support or feature requests, please contact the development team., 'wp-login.php', 'top');

// Example URLs:
// Old: https://site.com/wp-login.php
// New: https://site.com/login
```

#### URL Parameter Handling
```php
// Preserves query strings in login redirects
if ($query_string) {
    $url .= '?' . $query_string;
}
```

### **User Management Functions**

#### Username Generation
```php
// Clean username from email address
public function ghl_learning_network_pre_user_login($sanitized_user_login) {
    return strstr($sanitized_user_login, '@', true); // Gets part before @
}
```

#### Auto-Login After Registration
```php
// Automatically log in users after successful registration
if ($user) {
    wp_set_auth_cookie($user_id, true);
    wp_set_current_user($user_id);
    do_action('wp_login', $user->user_login, $user);
}
```

#### Email Validation Enhancement
```php
// Custom email validation with existing user detection
if (email_exists($value)) {
    $redirect_url = add_query_arg(array(
        'group_id'  => $_GET['group_id'],
        'code'      => $_GET['code'],
        'course_id' => $_GET['course_id'],
    ), home_url('sign-up'));
    
    $result['message'] = 'Email already registered. Please login to join sprint.';
}
```

### **Content Management**

#### Dynamic Body Classes
```php
// Add CSS classes based on user state and page context
if (is_page('sign-up') && isset($_GET['course_id'])) {
    $classes[] = sanitize_title(get_the_title($_GET['course_id'])); // Course-specific class
}

if (is_page('courses') && empty($course_ids)) {
    $classes[] = 'not-enrolled'; // Not enrolled class
}
```

#### Comment Filtering
```php
// Show only comments from users in same groups
$login_user_groups = learndash_get_users_group_ids(get_current_user_id());
$comment_author_groups = learndash_get_users_group_ids($comment_author);

// Display comment only if users share a group
if (!empty(array_intersect($login_user_groups, $comment_author_groups))) {
    $filtered_comments[] = $comment;
}
```

#### Focus Mode Comment Enable
```php
// Enable comments in LearnDash focus mode for lessons and topics
if ('sfwd-lessons' === $object->post_type || 'sfwd-topic' === $object->post_type) {
    $status = 'open';
}
```

### **Shortcode Functions**

#### Course Display Shortcode
```php
// [signup_course] - Displays course info based on URL parameter
if (!empty($course_id) && get_post_type($course_id) === 'sfwd-courses') {
    // Display course card with image, title, description, learn more button
}
```

#### Sprint Name Shortcode
```php
// [sprint_name] - Displays group name from URL parameter
$group_id = isset($_GET['group_id']) ? sanitize_text_field($_GET['group_id']) : '';
return get_the_title($group_id);
```

#### Dashboard Course Shortcode
```php
// [dashboard_enrolled_course] - Shows user's enrolled course with video popup
// Includes course image, description, video popup functionality
```

### **Form Integration**

#### Gravity Forms Integration
```php
// Form 1: Group creation with leader assignment
// Form 2: User registration with invitation validation  
// Form 4: Existing user sprint joining

// Automatic field population from URL parameters
$group_id = rgar($entry, '6'); // Group ID field
$code = rgar($entry, '8');     // Invitation code field
```

#### Asynchronous Feed Processing
```php
// Ensure user registration feeds are processed synchronously
add_filter('gform_is_feed_asynchronous', function($is_asynchronous, $feed, $entry, $form) {
    if (rgar($feed, 'addon_slug') === 'gravityformsuserregistration') {
        return gf_user_registration()->is_update_feed($feed) ? $is_asynchronous : false;
    }
    return $is_asynchronous;
}, 10, 4);
```

### **Video & Media Functions**

#### YouTube Video Sharing
```php
// Add share buttons after lesson videos
$video_url = $sfwd_data['sfwd-lessons_lesson_video_url'];
if (strpos($video_url, 'youtube.com') !== false) {
    // Generate sharing buttons for Facebook, Twitter, LinkedIn, WhatsApp, Email
}
```

#### Video Popup System
```php
// Course dashboard video popup functionality
if (strpos($course_video_url, 'youtube.com/watch') !== false) {
    // Convert watch URL to embed URL
    $course_video_url = 'https://www.youtube.com/embed/' . $video_id;
}
```

### **Meta Data Management**

#### Sprint Start Date Storage
```php
// Course-specific meta keys for different sprints
switch ($course_id) {
    case 6298: $meta_key = 'sprintr40_start'; break;  // R40 Relational
    case 6163: $meta_key = 'sprintf40_start'; break;  // F40 Financial
    case 6160: $meta_key = 'sprinth40_start'; break;  // H40 Health
}
```

#### Group Leader Email Association
```php
// Store group leader email in group meta for easy retrieval
update_post_meta($group_id, '_group_leader_email', sanitize_email($email));
update_post_meta($group_id, '_group_leader_first_name', sanitize_text_field($first_name));
update_post_meta($group_id, '_group_leader_last_name', sanitize_text_field($last_name));
```

### **Social Login Integration**

#### BuddyBoss Social Login
```php
// Add social login buttons to login popup
add_action('reign_login_form_top', function() {
    if (class_exists('BB_SSO')) {
        echo BB_SSO::render_buttons_with_container([
            'label_type' => 'login',
            'style' => 'default'
        ]);
    }
}, 5);
```

### **Performance Optimizations**

#### Session Management
```php
// Start session only when needed for tracking
if (!session_id()) {
    session_start();
}

// Prevent duplicate tracking in same session
$tracking_key = 'invitation_tracked_' . $group_id . '_' . $code;
if (isset($_SESSION[$tracking_key])) {
    return; // Skip duplicate tracking
}
```

#### Database Query Optimization
```php
// Efficient group course retrieval with multiple fallback methods
if (function_exists('learndash_group_enrolled_courses')) {
    $direct_courses = learndash_group_enrolled_courses($group_id);
    if (!empty($direct_courses)) {
        return $direct_courses; // Use direct method first
    }
}
```

## 📊 Tracking & Analytics

### Impression Tracking
- **What**: Records when invitation links are clicked
- **Data Captured**: IP, user agent, referrer, UTM parameters, timestamp
- **Prevention**: Duplicate session tracking prevention

### Conversion Tracking
- **What**: Records successful signups from invitation links
- **Attribution**: Links conversions back to original clicks
- **Types**: New user registrations and existing user group joins

### Statistics Available
- Total clicks per group
- Total conversions per group
- Conversion rates
- Daily breakdown
- Recent activity logs

## 🔧 Developer Information

### Hooks & Filters

```php
// Enable/disable lesson unlock system
add_filter('freedomology_lesson_unlock_enabled', '__return_false');

// Course-specific unlock control
add_filter('freedomology_lesson_unlock_enabled_for_course', function($enabled, $course_id) {
    return $course_id === 6298; // Only for R40
}, 10, 2);

// Skip admin unlock checks
add_filter('freedomology_lesson_unlock_skip_admins', '__return_true');

// Invitation URL modifications
add_filter('freedomology_invitation_url', function($url, $group_id) {
    // Modify invitation URLs
    return $url;
}, 10, 2);
```

### Custom Actions

```php
// Fired when invitation link is converted
do_action('freedomology_invitation_converted', $user_id, $tracking_id);

// Fired when invitation link is copied
do_action('freedomology_invitation_copied', $group_id, $user_id, $url);
```

### Database Tables

#### `wp_freedomology_invitation_tracking`
- Stores click and conversion data
- Includes UTM parameters and user information
- Automatic cleanup of old records

## 🎨 Customization

### CSS Styling
```css
/* Invitation widget styling */
.ldgiu-invitation-container {
    /* Custom styles */
}

/* Lesson lock notifications */
.wbcom-lesson-locked-notice {
    /* Custom lock message styles */
}

/* Course list styling */
.course-list-freedomology {
    /* Course display styles */
}
```

### JavaScript Customization
```javascript
// Track custom events
document.addEventListener('freedomology_link_copied', function(event) {
    // Custom tracking logic
});
```

## 🔒 Security Features

### Invitation Security
- Hash-based group codes
- Session validation
- IP tracking for fraud prevention
- Nonce verification for AJAX requests

### Access Control
- User capability checks
- Group membership validation
- Lesson unlock date verification
- Admin bypass controls

## 🚦 Troubleshooting

### Common Issues

1. **Database Tables Not Created**
   - Deactivate and reactivate plugin
   - Check WordPress error logs
   - Verify database permissions

2. **Tracking Not Working**
   - Ensure sessions are enabled
   - Check AJAX URL configuration
   - Verify nonce generation

3. **Lesson Unlock Issues**
   - Check group leader start dates
   - Verify course ID mappings
   - Review lesson delay settings

4. **Invitation Links Invalid**
   - Check group ID and course ID
   - Verify security code generation
   - Test URL parameters

### Debug Mode
```php
// Enable debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs in wp-content/debug.log
```

## 📈 Performance Considerations

### Database Optimization
- Indexed tracking table for fast queries
- Automatic cleanup of old tracking data
- Efficient query patterns

### Caching Compatibility
- Compatible with most caching plugins
- Session-based tracking considerations
- AJAX request optimization

## 🔄 Updates & Maintenance

### Version History
- v1.0.0 - Initial release with core features
- Database version tracking
- Automatic migration support

### Backup Recommendations
- Regular database backups
- Plugin file backups before updates
- Test updates on staging first

## 🤝 Support

### Documentation
- Inline code documentation
- WordPress coding standards
- PHPDoc comments

### Error Logging
- Comprehensive error logging
- Debug information available
- Admin notices for issues

## 📄 License

GPL2 or later - Compatible with WordPress licensing requirements.

## 🎯 Roadmap

### Planned Features
- [ ] Advanced reporting dashboard
- [ ] Email notification system
- [ ] Webhook integrations
- [ ] Multi-language support
- [ ] Mobile app integration
- [ ] Advanced A/B testing

### Performance Improvements
- [ ] Query optimization
- [ ] Caching enhancements
- [ ] Background processing
- [ ] API rate limiting

---

**Built with ❤️ for the Freedomology learning platform**

For technical support or feature requests, please contact the development team.