<?php
/**
 * Post meta and block binding for user notes.
 */

namespace HM\UserNotes\PostMeta;

use HM\UserNotes\CommentType;

/**
 * Initialize post meta functionality.
 */
function init() {
	add_action( 'init', __NAMESPACE__ . '\\register_post_meta' );
	add_filter( 'get_post_metadata', __NAMESPACE__ . '\\filter_user_note_status_meta', 10, 4 );
}

/**
 * Register post meta field for user note status.
 */
function register_post_meta() {
	\register_post_meta(
		'',
		'hm_user_note_status',
		[
			'show_in_rest' => true,
			'single'       => true,
			'type'         => 'string',
			'default'      => '',
		]
	);

	// Register block binding
	register_block_bindings_source(
		'hm-user-notes/note-status',
		[
			'label'              => __( 'User Note Status', 'hm-user-notes' ),
			'get_value_callback' => __NAMESPACE__ . '\\get_note_status_value',
			'uses_context'       => [ 'postId' ],
		]
	);
}

/**
 * Get the user note status text for a post.
 *
 * @param int $post_id Post ID.
 * @return string The status text.
 */
function get_user_note_status_text( $post_id ) {
	// Only for logged-in users
	if ( ! is_user_logged_in() ) {
		return '';
	}

	// Get the user's note for this post
	$user_note = CommentType\get_user_note( $post_id );

	// Return filtered text based on whether a note exists
	return $user_note
		? apply_filters( 'hm_user_notes_edit_text', __( 'Edit note', 'hm-user-notes' ), $post_id, $user_note )
		: apply_filters( 'hm_user_notes_add_text', __( 'Add a note', 'hm-user-notes' ), $post_id );
}

/**
 * Filter post metadata to dynamically generate user note status.
 *
 * @param mixed  $value     The value to return.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @param bool   $single    Whether to return a single value.
 * @return mixed
 */
function filter_user_note_status_meta( $value, $object_id, $meta_key, $single ) {
	// Only filter our specific meta key
	if ( $meta_key !== 'hm_user_note_status' ) {
		return $value;
	}

	$text = get_user_note_status_text( $object_id );

	return $single ? $text : [ $text ];
}

/**
 * Get the value for the block binding.
 *
 * @param array    $source_args    Source arguments (unused).
 * @param WP_Block $block_instance Block instance.
 * @return string
 */
function get_note_status_value( $source_args, $block_instance ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	$post_id = $block_instance->context['postId'] ?? get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	return get_user_note_status_text( $post_id );
}
