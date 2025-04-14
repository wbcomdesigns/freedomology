<?php
class Freedomology_Shortcodes {

	public function __construct() {
		add_shortcode( 'signup_course', array( $this, 'render_signup_course' ) );
		add_shortcode( 'sprint_name', array( $this, 'render_sprint_name' ) );
	}

	public function render_signup_course() {
		$course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;

		if ( $course_id && get_post_type( $course_id ) === 'sfwd-courses' ) {
			ob_start();
			?>
			<div class="course-list-freedomology">
				<div class="course-list-img">
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $course_id ) ); ?>" alt="<?php echo esc_attr( get_the_title( $course_id ) ); ?>" />
				</div>
				<div class="course-list-content">
					<h3><?php echo esc_html( get_the_title( $course_id ) ); ?></h3>
					<p><?php echo esc_html( wp_trim_words( get_post_field( 'post_content', $course_id ), 20 ) ); ?></p>
					<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="learn-more-btn"><?php esc_html_e( 'Learn More', 'freedomology' ); ?></a>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
		return '<p>' . esc_html__( 'No course found.', 'freedomology' ) . '</p>';
	}

	public function render_sprint_name() {
		$group_id = isset( $_GET['group_id'] ) ? intval( $_GET['group_id'] ) : 0;
		return $group_id ? esc_html( get_the_title( $group_id ) ) : '';
	}
}
