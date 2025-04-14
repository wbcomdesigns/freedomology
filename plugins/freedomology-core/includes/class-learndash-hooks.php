<?php
class Freedomology_LearnDash_Hooks {

	public function __construct() {
		add_action( 'ld_added_group_access', array( $this, 'user_added_to_group' ), 10, 2 );
		add_action( 'ld_removed_group_access', array( $this, 'user_removed_from_group' ), 10, 2 );
		add_action( 'uo_new_group_created', array( $this, 'new_group_created' ), 10, 2 );
		add_action( 'ld_added_leader_group_access', array( $this, 'leader_added_to_group' ), 10, 2 );
		add_action( 'ld_removed_leader_group_access', array( $this, 'leader_removed_from_group' ), 10, 2 );
	}

	public function user_added_to_group( $user_id, $group_id ) {
		foreach ( learndash_group_enrolled_courses( $group_id ) as $course_id ) {
			do_action( 'learndash_update_course_access', $user_id, $course_id, '', false );
		}
	}

	public function user_removed_from_group( $user_id, $group_id ) {
		foreach ( learndash_group_enrolled_courses( $group_id ) as $course_id ) {
			do_action( 'learndash_update_course_access', $user_id, $course_id, '', true );
		}
	}

	public function new_group_created( $group_id, $group_leader_id ) {
		$this->leader_added_to_group( $group_leader_id, $group_id );
	}

	public function leader_added_to_group( $user_id, $group_id ) {
		foreach ( learndash_group_enrolled_courses( $group_id ) as $course_id ) {
			$tag = trim( sanitize_text_field( get_the_title( $course_id ) . ' Leader' ) );
			$tag_id = wpf_get_tag_id( $tag ) ?: wp_fusion()->crm->add_tag( $tag );
			wp_fusion()->user->apply_tags( array( $tag_id ), $user_id );
		}
	}

	public function leader_removed_from_group( $user_id, $group_id ) {
		foreach ( learndash_group_enrolled_courses( $group_id ) as $course_id ) {
			$tag = trim( sanitize_text_field( get_the_title( $course_id ) . ' Leader' ) );
			$tag_id = wpf_get_tag_id( $tag );
			if ( $tag_id ) {
				wp_fusion()->user->remove_tags( array( $tag_id ), $user_id );
			}
		}
	}
}
