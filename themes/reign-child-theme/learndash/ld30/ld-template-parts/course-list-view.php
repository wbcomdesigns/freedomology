<?php

/**
 * Template part for displaying course list item
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package reign-theme
 */

global $wbtm_reign_settings;
$user_id     = get_current_user_id();
$course_id   = learndash_get_course_id(get_the_ID());
$author_id   = get_post_field('post_author', $course_id);
$first_name  = get_the_author_meta('user_firstname', $author_id);
$last_name   = get_the_author_meta('user_lastname', $author_id);
$author_name = get_the_author_meta('display_name', $author_id);
if (! empty($first_name)) {
	$author_name = $first_name . ' ' . $last_name;
}
remove_filter('author_link', 'wpforo_change_author_default_page');
$author_url        = apply_filters('lm_filter_course_author_url', get_author_posts_url($author_id));
$author_avatar_url = get_avatar_url($author_id);
$course_price      = get_reign_ld_course_price($course_id);
$review_tab        = (isset($wbtm_reign_settings['learndash']['hide_review_tab'])) ? $wbtm_reign_settings['learndash']['hide_review_tab'] : '';
$class             = 'ld_course_grid_price';
$ribbon_text       = get_post_meta(get_the_ID(), '_learndash_course_grid_custom_ribbon_text', true);
$custom_btn_text   = get_post_meta(get_the_ID(), '_learndash_course_grid_custom_button_text', true);
$ribbon_text       = isset($ribbon_text) && ! empty($ribbon_text) ? $ribbon_text : '';
$course_options    = get_post_meta($course_id, '_sfwd-courses', true);

$course_pricing = learndash_get_course_price($course_id);
$price          = $course_pricing && isset($course_pricing['price']) && $course_pricing['price'] != '' ? wp_kses_post($course_pricing['price']) : esc_html__('Free', 'reign-learndash-addon');

$short_description = (isset($course_options['sfwd-courses_course_short_description'])) ? $course_options['sfwd-courses_course_short_description'] : '';
$price_type        = $course_options && isset($course_options['sfwd-courses_course_price_type']) ? $course_options['sfwd-courses_course_price_type'] : '';
$options           = get_option('sfwd_cpt_options');
$currency_setting  = class_exists('LearnDash_Settings_Section') ? LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Section_PayPal', 'paypal_currency') : null;
$currency          = '';

$currency = null;
if (! is_null($options)) {
	if (isset($options['modules']) && isset($options['modules']['sfwd-courses_options']) && isset($options['modules']['sfwd-courses_options']['sfwd-courses_paypal_currency'])) {
		$currency = $options['modules']['sfwd-courses_options']['sfwd-courses_paypal_currency'];
	}
}

if (is_null($currency)) {
	$currency = (version_compare(LEARNDASH_VERSION, '4.1.0', '<')) ? learndash_30_get_currency_symbol() : learndash_get_currency_symbol();
}

$currency = apply_filters('learndash_course_grid_currency', $currency, $course_id);

$price_text = '';

if (is_numeric($price) && ! empty($price)) {
	$price_format = apply_filters('learndash_course_grid_price_text_format', '{currency}{price}');

	$price_text = str_replace(array('{currency}', '{price}'), array($currency, $price), $price_format);
} elseif (is_string($price) && ! empty($price)) {
	$price_text = $price;
} elseif (empty($price)) {
	$price_text = __('Free', 'learndash-course-grid');
}


$has_access   = sfwd_lms_has_access($course_id, $user_id);
$is_completed = learndash_course_completed($user_id, $course_id);

if ($has_access && ! $is_completed && $price_type != 'open' && empty($ribbon_text)) {
	$class      .= ' ribbon-enrolled';
	$ribbon_text = __('Enrolled', 'learndash-course-grid');
} elseif ($has_access && $is_completed && $price_type != 'open' && empty($ribbon_text)) {
	$class      .= '';
	$ribbon_text = __('Completed', 'learndash-course-grid');
} elseif ($price_type == 'open' && empty($ribbon_text)) {
	if (is_user_logged_in() && ! $is_completed) {
		$class      .= ' ribbon-enrolled';
		$ribbon_text = __('Enrolled', 'learndash-course-grid');
	} elseif (is_user_logged_in() && $is_completed) {
		$class      .= '';
		$ribbon_text = __('Completed', 'learndash-course-grid');
	} else {
		$class      .= ' ribbon-enrolled';
		$ribbon_text = $price_text;
	}
} elseif ($price_type == 'closed' && empty($price)) {
	$class .= ' ribbon-enrolled';

	if (is_numeric($price)) {
		$ribbon_text = $price_text;
	} else {
		$ribbon_text = '';
	}
} elseif (empty($ribbon_text)) {
	$class      .= ! empty($course_options['sfwd-courses_course_price']) ? ' price_' . $currency : ' free';
	$ribbon_text = $price_text;
} else {
	$class .= ' custom';
}
?>

<div class="lm-course-item-wrapper lm-course-item-<?php echo esc_attr($course_id); ?>">
	<div class="lm-course-item">
		<div class="lm-course-thumbnail">
			<?php if (get_post_type() == 'sfwd-courses' && ! isset($wbtm_reign_settings['learndash']['hide_course_ribbon'])) : ?>
				<div class="<?php echo esc_attr($class); ?>">
					<?php echo esc_attr($ribbon_text); ?>
				</div>
			<?php endif; ?>

			<a class="lm-course-image-link" href="<?php echo esc_url(learndash_get_step_permalink(get_the_ID(), $course_id)); ?>" title="<?php echo the_title_attribute('echo=0'); ?>" rel="bookmark">
			<?php
			$render_course_thumbnail = true;
			if (defined('LEARNDASH_COURSE_GRID_FILE')) {
				$enable_video = get_post_meta($course_id, '_learndash_course_grid_enable_video_preview', true);
				$embed_code   = get_post_meta($course_id, '_learndash_course_grid_video_embed_code', true);
				// Retrive oembed HTML if URL provided.
				if (preg_match('/^http/', $embed_code)) {
					$embed_code = wp_oembed_get(
						$embed_code,
						array(
							'height' => 600,
							'width'  => 400,
						)
					);
				}
				if (1 == $enable_video && ! empty($embed_code)) {
					echo $embed_code; //phpcs:ignore
					$render_course_thumbnail = false;
				}
			}
			if ($render_course_thumbnail) {
				if (has_post_thumbnail()) {
					the_post_thumbnail();
				} else {
					echo wp_kses_post(get_reign_ld_default_course_img_html());
				}
			}
			?></a>
			<?php
			if (! isset($wbtm_reign_settings['learndash']['hide_read_more_button'])) :
			?>
				<a class="button lm-course-readmore-button lm-course-grid-view-data" href="<?php echo esc_url(learndash_get_step_permalink(get_the_ID(), $course_id)); ?>" title="<?php echo the_title_attribute('echo=0'); ?>" rel="bookmark"><?php echo ($custom_btn_text != '') ? esc_html( $custom_btn_text ) : esc_html_e('Read More', 'reign-learndash-addon'); ?></a>
			<?php endif; ?>
		</div>
		<div class="lm-course-content">
			<div class="lm-course-author lm-course-grid-view-data" itemscope="" itemtype="http://schema.org/Person">
				<a href="<?php echo esc_url($author_url); ?>">
					<img alt="Admin bar avatar" src="<?php echo esc_url($author_avatar_url); ?>" class="lm-author-avatar" width="40" height="40">
				</a>
				<div class="author-contain">
					<label itemprop="jobTitle"><?php esc_html_e('Instructor', 'reign-learndash-addon'); ?></label>
					<div class="lm-value" itemprop="name">
						<a href="<?php echo esc_url($author_url); ?>">
							<?php echo esc_html($author_name); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			the_title('<h2 class="lm-course-title"><a href="' . learndash_get_step_permalink(get_the_ID(), $course_id) . '" title="' . the_title_attribute('echo=0') . '" rel="bookmark">', '</a></h2>');
			?>

			<?php if (! isset($wbtm_reign_settings['learndash']['hide_course_description'])) : ?>
				<div class="lm-course-description lm-course-grid-view-data">
					<?php
					if (! empty($short_description)) {
						echo esc_html(do_shortcode(htmlspecialchars_decode($short_description)));
					} elseif (! is_singular('sfwd-courses')) {
						the_excerpt();
					}
					?>
				</div>
			<?php endif; ?>

			<?php if (isset($wbtm_reign_settings['learndash']['show_course_progress_bar']) && $wbtm_reign_settings['learndash']['show_course_progress_bar'] == 'on') : ?>
				<div class="lm-course-progressbar lm-course-grid-view-data">
					<?php echo do_shortcode('[learndash_course_progress course_id="' . get_the_ID() . '" user_id="' . get_current_user_id() . '"]'); ?>
				</div>
			<?php endif; ?>

			<div class="lm-course-meta">
				<div class="lm-course-author lm-course-list-view-data" itemscope="" itemtype="http://schema.org/Person">
					<a href="<?php echo esc_url($author_url); ?>">
						<img alt="Admin bar avatar" src="<?php echo esc_url($author_avatar_url); ?>" class="lm-author-avatar" width="40" height="40">
					</a>
					<div class="author-contain">
						<label itemprop="jobTitle"><?php esc_html_e('Instructor', 'reign-learndash-addon'); ?></label>
						<div class="lm-value" itemprop="name">
							<a href="<?php echo esc_url($author_url); ?>">
								<?php echo esc_html($author_name); ?>
							</a>
						</div>
					</div>
				</div>

				<?php
				$course_reviews_analytics = wb_ld_get_course_reviews_analytics($course_id);
				if (class_exists('LD_Course_Review_Manager') && ! $review_tab && comments_open($course_id)) :
					$reviews_percentage = 0;
					if (isset($course_reviews_analytics['reviews_percentage'])) {
						$reviews_percentage = $course_reviews_analytics['reviews_percentage'];
					}
				?>
					<div class="lm-course-review lm-course-list-view-data">
						<label><?php esc_html_e('Review', 'reign-learndash-addon'); ?></label>
						<div class="lm-value">
							<div class="lm-review-stars-rated">
								<ul class="lm-review-stars">
									<li><span class="far fa-star"></span></li>
									<li><span class="far fa-star"></span></li>
									<li><span class="far fa-star"></span></li>
									<li><span class="far fa-star"></span></li>
									<li><span class="far fa-star"></span></li>
								</ul>
								<ul class="lm-review-stars lm-filled" style="width: <?php echo esc_attr($reviews_percentage); ?>%">
									<li><span class="fa fa-star"></span></li>
									<li><span class="fa fa-star"></span></li>
									<li><span class="fa fa-star"></span></li>
									<li><span class="fa fa-star"></span></li>
									<li><span class="fa fa-star"></span></li>
								</ul>
							</div>
							<span>(<?php echo esc_html($course_reviews_analytics['total_reviews']); ?> <?php esc_html_e('reviews', 'reign-learndash-addon'); ?>)</span>
						</div>
					</div>
				<?php endif; ?>

				<?php if (! isset($wbtm_reign_settings['learndash']['hide_course_student_count'])) { ?>
					<div class="lm-course-students">
						<label><?php esc_html_e('Students', 'reign-learndash-addon'); ?></label>
						<div class="lm-value">
							<i class="far fa-users"></i>
							<?php
							// Fetch all course user IDs using the reign_get_course_user_ids function
							$course_user_ids = reign_get_course_user_ids($course_id);

							// Ensure $course_user_ids is an array (though it should be by default)
							if (!is_array($course_user_ids)) {
								$course_user_ids = array();
							}

							// Output the count of enrolled users
							echo count($course_user_ids);
							?>

						</div>
					</div>
				<?php } ?>
				<?php if (class_exists('LD_Course_Review_Manager') && ! $review_tab && comments_open($course_id)) : ?>
					<div class="lm-course-comments-count lm-course-grid-view-data">
						<label><?php esc_html_e('Comment', 'reign-learndash-addon'); ?></label>
						<div class="lm-value">
							<i class="far fa-comment"></i><?php echo esc_html($course_reviews_analytics['total_reviews']); ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="lm-course-price lm-course-grid-view-data" itemprop="offers" itemscope="" itemtype="http://schema.org/Offer">
					<div class="lm-value">
						<?php echo $course_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
						?>
					</div>
					<meta itemprop="priceCurrency" content="$">
				</div>
			</div>
			<?php if (! isset($wbtm_reign_settings['learndash']['hide_course_description'])) : ?>
				<div class="lm-course-description lm-course-list-view-data">
					<?php
					if (! empty($short_description)) {
						echo esc_html(do_shortcode(htmlspecialchars_decode($short_description)));
					} elseif (! is_singular('sfwd-courses')) {
						the_excerpt();
					}
					?>
				</div>
			<?php endif; ?>

			<?php if (isset($wbtm_reign_settings['learndash']['show_course_progress_bar']) && $wbtm_reign_settings['learndash']['show_course_progress_bar'] == 'on') : ?>
				<div class="lm-course-progressbar lm-course-list-view-data">
					<?php echo do_shortcode('[learndash_course_progress course_id="' . $course_id . '" user_id="' . get_current_user_id() . '"]'); ?>
				</div>
			<?php endif; ?>

			<div class="lm-course-price lm-course-list-view-data" itemprop="offers" itemscope="" itemtype="http://schema.org/Offer">
				<div class="lm-value">
					<?php echo $course_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
					?>
				</div>
				<meta itemprop="priceCurrency" content="$">
			</div>

			<?php if (! isset($wbtm_reign_settings['learndash']['hide_read_more_button'])) : ?>
				<div class="lm-course-readmore lm-course-list-view-data">
					<a class="button lm-course-readmore-button" href="<?php echo esc_url(learndash_get_step_permalink(get_the_ID(), $course_id)); ?>" title="<?php echo the_title_attribute('echo=0'); ?>" rel="bookmark"><?php esc_html_e('Read More', 'reign-learndash-addon'); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>