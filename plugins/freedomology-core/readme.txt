=== Freedomology ===
Contributors: wbcomdesigns
Tags: learndash, gravity forms, buddypress, buddypress activation, buddypress signup, signup invite link, social login
Requires at least: 5.9
Tested up to: 6.7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Freedomology is a custom plugin skeleton built for LearnDash, Gravity Forms, and BuddyBoss sites to manage user invites, group creation, social login, and user registration flows.

== Description ==

Freedomology provides a solid development foundation for:
- Gravity Forms based LearnDash Group creation
- Invite URL generation for LearnDash groups
- BuddyBoss Social Login integration
- Automatic BuddyPress/BuddyBoss user activation (no email validation)
- Shortcodes for displaying courses and sprints
- Custom login URL rewrites (/login)
- Frontend CSS and JavaScript for better UX

This plugin is a **base skeleton** designed for developers needing advanced control over user onboarding, registration, and learning flows.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Make sure you have installed and activated:
   - Gravity Forms
   - LearnDash LMS
   - BuddyBoss Platform (or BuddyPress)
4. Visit Settings > Permalinks and click **Save Changes** (to flush rewrite rules).
5. Create a WordPress page at `/sign-up/` if it does not exist for invite links.

== Frequently Asked Questions ==

= Does this plugin create LearnDash Groups automatically? =
Yes, when a user submits the configured Gravity Form.

= Can users sign up via invite links? =
Yes, permanent invite URLs are generated per LearnDash group.

= Does it replace the wp-login.php? =
It rewrites `/login/` URL to work exactly like `wp-login.php`.

= Are email validations disabled? =
Yes, for BuddyBoss/BuddyPress registration — users are auto-activated.

== Screenshots ==

1. Group Invite URL generator on the Group Management page.
2. Auto Redirect to User Profile after Login.
3. Social Login buttons on Reign login popup.

== Changelog ==

= 1.0.0 =
* Initial release.
* LearnDash integration.
* Gravity Forms integration.
* BuddyBoss/BuddyPress Social Login and Activation handling.
* Permanent Invite URL system for group signups.

== Upgrade Notice ==

= 1.0.0 =
First stable release of the Freedomology plugin.
