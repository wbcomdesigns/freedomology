<?php

/**
 * LearnDash LD30 focus mode sidebar.
 *
 * @since 3.0.0
 *
 * @package LearnDash\Templates\LD30
 */

if (! defined('ABSPATH')) {
	exit;
}
$has_access = sfwd_lms_has_access($course_id);
global $course_pager_results;

$parent_course       = get_post($course_id);
$parent_course_link  = $parent_course->guid;
$parent_course_title = $parent_course->post_title;

// Get all enrolled users and their count using reign_get_course_user_ids

// Initialize variables to avoid warnings
$members_arr = array();
$members_count = 0;

$members_arr = reign_get_course_user_ids($parent_course->ID, true);
$members_count = count($members_arr);

// Limit to the first 5 members
$members = array_slice($members_arr, 0, 5);

/** This action is documented in themes/ld30/templates/focus/index.php */
do_action('learndash-focus-sidebar-before', $course_id, $user_id); ?>

<div class="ld-focus-sidebar">
	<div class="ld-focus-sidebar-wrapper">

		<div class="ld-course-navigation-heading">
			<div class="lms-topic-sidebar-course-navigation">
				<div class="ld-course-navigation">
					<a title="<?php echo esc_attr($parent_course_title); ?>" href="<?php echo esc_url( home_url( 'courses' ) ); ?>" class="course-entry-link">
						<span>
							<i class="ld-icon ld-icon-arrow-left"></i>
							<?php echo sprintf(esc_html_x('Back to %s', 'link: Back to Course sdfdfs', 'reign-learndash-addon'), LearnDash_Custom_Label::get_label('course')); //phpcs:ignore ?>
						</span>
					</a>
				</div>
			</div>
			<?php
			/**
			 * Fires before the sidebar trigger wrapper in the focus template.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action('learndash-focus-sidebar-trigger-wrapper-before', $course_id, $user_id);
			?>

			<span class="ld-focus-sidebar-trigger">
				<?php
				/**
				 * Fires before the sidebar trigger in the focus template.
				 *
				 * @since 3.0.0
				 *
				 * @param int $course_id Course ID.
				 * @param int $user_id   User ID.
				 */
				do_action('learndash-focus-sidebar-trigger-before', $course_id, $user_id);
				?>
				<span class="ld-icon <?php echo esc_attr(learndash_30_get_focus_mode_sidebar_arrow_class()); ?>"></span>
				<?php
				/**
				 * Fires after the sidebar trigger in the focus template.
				 *
				 * @since 3.0.0
				 *
				 * @param int $course_id Course ID.
				 * @param int $user_id   User ID.
				 */
				do_action('learndash-focus-sidebar-trigger-after', $course_id, $user_id);
				?>
			</span>

			<?php
			/**
			 * Fires after the sidebar trigger wrapper in the focus template.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action('learndash-focus-sidebar-trigger-wrapper-after', $course_id, $user_id);
			?>

			<?php
			/**
			 * Fires before the sidebar heading in the focus template.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action('learndash-focus-sidebar-heading-before', $course_id, $user_id);
			?>

			<h3>
				<a href="<?php echo esc_url(get_the_permalink($course_id)); ?>" id="ld-focus-mode-course-heading">
					<span class="ld-icon ld-icon-content"></span>
					<?php echo esc_html(get_the_title($course_id)); ?>
				</a>
			</h3>
			<?php
			/**
			 * Fires after the sidebar heading in the focus template.
			 *
			 * @since 3.0.0
			 *
			 * @param int $course_id Course ID.
			 * @param int $user_id   User ID.
			 */
			do_action('learndash-focus-sidebar-heading-after', $course_id, $user_id);
			?>

			<div class="lms-topic-sidebar-progress">
				<div class="course-progress-wrap">
					<?php
					learndash_get_template_part(
						'modules/progress.php',
						array(
							'context'   => 'focus',
							'user_id'   => get_current_user_id(),
							'course_id' => $course_id,
						),
						true
					);
					?>
				</div>
			</div>

		</div>

		<?php
		/**
		 * Fires inside the sidebar heading navigation in the focus template.
		 *
		 * @since 3.0.0
		 *
		 * @param int $course_id Course ID.
		 * @param int $user_id   User ID.
		 */
		do_action('learndash-focus-sidebar-between-heading-navigation', $course_id, $user_id);
		?>
		<div class="ld-course-navigation">
			<div class="ld-course-navigation-list">
				<div class="ld-lesson-navigation">
					<div class="ld-lesson-items" id="<?php echo esc_attr('ld-lesson-list-' . $course_id); ?>">
						<?php
						/**
						 * Fires before the sidebar nav in the focus template.
						 *
						 * @since 3.0.0
						 *
						 * @param int $course_id Course ID.
						 * @param int $user_id   User ID.
						 */
						do_action('learndash-focus-sidebar-nav-before', $course_id, $user_id);

						$lessons = learndash_get_course_lessons_list($course_id, $user_id, learndash_focus_mode_lesson_query_args($course_id));

						/**
						 * Filters focus mode navigation setting arguments.
						 *
						 * @since 3.0.0
						 *
						 * @param array $navigation_setting_args An array of focus mode navigation settings.
						 */
						$widget_instance = apply_filters(
							'ld-focus-mode-navigation-settings',
							array(
								'show_lesson_quizzes' => true,
								'show_topic_quizzes'  => true,
								'show_course_quizzes' => true,
							)
						);

						learndash_get_template_part(
							'widgets/navigation/rows.php',
							array(
								'course_id'            => $course_id,
								'widget_instance'      => $widget_instance,
								'lessons'              => $lessons,
								'has_access'           => $has_access,
								'user_id'              => $user_id,
								'course_pager_results' => $course_pager_results,
							),
							true
						);

						/**
						 * Fires after the sidebar nav in the focus template.
						 *
						 * @since 3.0.0
						 *
						 * @param int $course_id Course ID.
						 * @param int $user_id   User ID.
						 */
						do_action('learndash-focus-sidebar-nav-after', $course_id, $user_id);
						?>
					</div> <!--/.ld-lesson-items-->
				</div> <!--/.ld-lesson-navigation-->
			</div> <!--/.ld-course-navigation-list-->
		</div> <!--/.ld-course-navigation-->
		<?php
		/**
		 * Fires after the sidebar nav wrapper in the focus template.
		 *
		 * @since 3.0.0
		 *
		 * @param int $course_id Course ID.
		 * @param int $user_id   User ID.
		 */
		do_action('learndash-focus-sidebar-after-nav-wrapper', $course_id, $user_id);
		?>

		<div class="lms-course-members-list">
			<h4 class="lms-course-sidebar-heading">
				<?php esc_html_e('Participants', 'reign-learndash-addon'); ?>
				<span class="lms-count"><?php echo esc_html($members_count); ?></span>
			</h4>
			<input type="hidden" name="regin_learndash_course_participants_course_id" id="regin_learndash_course_participants_course_id" value="<?php echo esc_attr($course_id); ?>">
			<div class="reign-learndash-course-member-wrap">

				<ul class="course-members-list">
					<?php
					$count = 0;
					foreach ($members as $course_member) :
						if ($count > 4) {
							break;
						}
					?>
						<li>

							<?php if (class_exists('BuddyPress')) { ?>
								<?php if (function_exists('buddypress') && version_compare(buddypress()->version, '12.0', '>=')) { ?>
									<a href="<?php echo esc_url(bp_members_get_user_url((int) $course_member)); ?>">
									<?php } else { ?>
										<a href="<?php echo esc_url(bp_core_get_user_domain((int) $course_member)); ?>">
										<?php } ?>
									<?php } ?>
									<img class="round" src="<?php echo esc_url(get_avatar_url((int) $course_member, array('size' => 96))); ?>" alt="" />
									<?php
									if (class_exists('BuddyPress')) {
									?>
										<span><?php echo esc_html( bp_core_get_user_displayname((int) $course_member) ); ?></span>
									<?php
									} else {
										$course_member = get_userdata((int) $course_member);
									?>
										<span><?php echo esc_html( $course_member->display_name ); ?></span>
									<?php
									}
									if (class_exists('BuddyPress')) {
									?>
										</a>
									<?php
									}
									?>
						</li>
					<?php
						$count++;
					endforeach;
					?>
				</ul>

				<ul class="course-members-list course-members-list-extra">
				</ul>
				<?php
				if ($members_count > 5) {
				?>
					<a href="javascript:void(0);" class="list-members-extra lme-more"><span class="members-count-g"></span> <?php esc_html_e('Show more', 'reign-learndash-addon'); ?><i class="ld-icon ld-icon-arrow-down"></i></a>
				<?php
				}
				?>
			</div>
		</div>
	</div> <!--/.ld-focus-sidebar-wrapper-->
</div> <!--/.ld-focus-sidebar-->

<?php
/**
 * Fires after the sidebar in the focus template.
 *
 * @since 3.0.0
 *
 * @param int $course_id Course ID.
 * @param int $user_id   User ID.
 */
do_action('learndash-focus-sidebar-after', $course_id, $user_id);
?>