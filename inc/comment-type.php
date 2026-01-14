<?php
/**
 * Comment type handling for private user notes.
 */

namespace HM\UserNotes\CommentType;

use WP_Comment_Query;

/**
 * Initialize comment type functionality.
 */
function init() {
	add_filter( 'preprocess_comment', __NAMESPACE__ . '\\set_comment_type' );
	add_filter( 'rest_pre_insert_comment', __NAMESPACE__ . '\\set_comment_type_rest', 10, 2 );
	add_filter( 'comment_text', __NAMESPACE__ . '\\filter_comment_text', 10, 3 );
	add_filter( 'comments_array', __NAMESPACE__ . '\\filter_comments', 10, 2 );
	add_filter( 'wp_update_comment_data', __NAMESPACE__ . '\\verify_comment_ownership', 10, 3 );
	add_action( 'pre_comment_on_post', __NAMESPACE__ . '\\check_existing_comment' );
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_fields' );
	add_filter( 'pre_render_block', __NAMESPACE__ . '\\setup_comment_form_filters', 10, 2 );
	add_filter( 'render_block', __NAMESPACE__ . '\\cleanup_comment_form_filters', 20, 2 );
	add_filter( 'user_has_cap', __NAMESPACE__ . '\\filter_user_has_cap', 10, 4 );
	add_action( 'pre_get_comments', __NAMESPACE__ . '\\action_reference_pre_get_comments', 10000 );
	add_filter( 'comment_feed_where', __NAMESPACE__ . '\\filter_reference_comment_feed_where', 10, 2 );
}

/**
 * Filters the WHERE clause of the comments feed query before sending.
 *
 * @param string    $cwhere The WHERE clause of the query.
 * @param \WP_Query $query  The WP_Query instance (passed by reference).
 * @return string The WHERE clause of the query.
 */
function filter_reference_comment_feed_where( $cwhere, \WP_Query $query ) {
	$cwhere .= " AND comment_type != 'hm_user_note' ";
	return $cwhere;
}

/**
 * Set the comment type for private comments based on meta flag.
 *
 * @param array $commentdata Comment data.
 * @return array Modified comment data.
 */
function set_comment_type( $commentdata ) {
	if ( isset( $_POST['hm_user_note'] ) && $_POST['hm_user_note'] === '1' ) {
		$commentdata['comment_type'] = 'hm_user_note';
	}
	return $commentdata;
}

/**
 * Filters a comment before it is inserted via the REST API.
 *
 * @param array|\WP_Error  $prepared_comment The prepared comment data for wp_insert_comment().
 * @param \WP_REST_Request $request          Request used to insert the comment.
 * @return array|\WP_Error The prepared comment data for wp_insert_comment().
 */
function set_comment_type_rest( $prepared_comment, \WP_REST_Request $request ) {
	if ( $request->get_header( 'x-hm-user-note' ) ) {
		$prepared_comment['comment_type'] = 'hm_user_note';
	}
	return $prepared_comment;
}

/**
 * Filter comment text to hide private comments from non-authors.
 *
 * @param string     $comment_text Text of the comment.
 * @param WP_Comment $comment      Comment object.
 * @param array      $args         Arguments.
 * @return string Filtered comment text.
 */
function filter_comment_text( $comment_text, $comment, $args ) {
	if ( $comment->comment_type !== 'hm_user_note' ) {
		return $comment_text;
	}

	// Only show to comment author
	if ( (int) \get_current_user_id() !== (int) $comment->user_id ) {
		return '';
	}

	return $comment_text;
}

/**
 * Filter comments to only show private comments to their authors.
 *
 * @param array $comments Array of comments.
 * @param int   $post_id  Post ID.
 * @return array Filtered comments.
 */
function filter_comments( $comments, $post_id ) {
	$current_user_id = get_current_user_id();

	return array_filter( $comments, function( $comment ) use ( $current_user_id ) {
		if ( $comment->comment_type !== 'hm_user_note' ) {
			return true;
		}

		// Only show private comments to their author
		return $current_user_id && $current_user_id == $comment->user_id;
	} );
}

/**
 * Verify comment ownership before allowing updates.
 *
 * @param array $data       The new, processed comment data.
 * @param array $comment    The old, unslashed comment data.
 * @param array $commentarr The new, raw comment data.
 * @return array Modified comment data or WP_Error.
 */
function verify_comment_ownership( $data, $comment, $commentarr ) {
	// Only check for private comments
	if ( $comment['comment_type'] !== 'hm_user_note' ) {
		return $data;
	}

	$current_user_id = get_current_user_id();

	// Prevent editing other users' private comments
	if ( $current_user_id != $comment['user_id'] && ! current_user_can( 'moderate_comments' ) ) {
		return new \WP_Error( 'comment_unauthorized', __( 'You cannot edit this comment.', 'hm-user-notes' ) );
	}

	return $data;
}

/**
 * Check if user already has a private comment on this post.
 *
 * @param int $post_id Post ID.
 */
function check_existing_comment( $post_id ) {
	// Only check if this is a private comment
	if ( ! isset( $_POST['hm_user_note'] ) || $_POST['hm_user_note'] !== '1' ) {
		return;
	}

	$current_user_id = get_current_user_id();

	if ( ! $current_user_id ) {
		wp_die( __( 'You must be logged in to add a note.', 'hm-user-notes' ) );
	}

	// Check for existing private comment by this user on this post
	$existing = get_comments( [
		'post_id' => $post_id,
		'user_id' => $current_user_id,
		'type'    => 'hm_user_note',
		'number'  => 1,
	] );

	// If updating an existing comment, allow it
	if ( ! empty( $_POST['comment_ID'] ) ) {
		return;
	}

	// If there's already a private comment, prevent adding another
	if ( ! empty( $existing ) ) {
		wp_die( __( 'You already have a note on this post. Please edit your existing note instead.', 'hm-user-notes' ) );
	}
}

/**
 * Register REST API fields for retrieving existing user notes.
 */
function register_rest_fields() {
	register_rest_field(
		'comment',
		'is_private',
		[
			'get_callback' => function( $comment ) {
				return $comment['type'] === 'hm_user_note';
			},
			'schema' => [
				'type' => 'boolean',
			],
		]
	);

	// Allow comment_type to be set via REST API
	add_filter( 'rest_preprocess_comment', __NAMESPACE__ . '\\set_comment_type_from_rest', 10, 2 );
}

/**
 * Set comment type when creating via REST API.
 *
 * @param array           $prepared_comment Prepared comment data.
 * @param WP_REST_Request $request          Request object.
 * @return array Modified comment data.
 */
function set_comment_type_from_rest( $prepared_comment, $request ) {
	// Check if comment_type is set in the request
	$comment_type = $request->get_param( 'comment_type' );

	if ( $comment_type === 'hm_user_note' ) {
		$prepared_comment['comment_type'] = 'hm_user_note';

		// Verify user is logged in and setting their own comment
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_comment_unauthorized',
				__( 'You must be logged in to create a private note.', 'hm-user-notes' ),
				[ 'status' => 401 ]
			);
		}

		// Ensure the comment author is the current user
		$prepared_comment['user_id'] = get_current_user_id();

		// Check for existing private comment
		$post_id = $prepared_comment['comment_post_ID'];
		$existing = get_comments( [
			'post_id' => $post_id,
			'user_id' => get_current_user_id(),
			'type'    => 'hm_user_note',
			'number'  => 1,
		] );

		if ( ! empty( $existing ) ) {
			return new \WP_Error(
				'rest_comment_exists',
				__( 'You already have a note on this post. Please update your existing note instead.', 'hm-user-notes' ),
				[ 'status' => 400 ]
			);
		}
	}

	return $prepared_comment;
}

/**
 * Get the current user's private comment for a post.
 *
 * @param int $post_id Post ID.
 * @return WP_Comment|null The comment object or null.
 */
function get_user_note( $post_id ) {
	$current_user_id = get_current_user_id();

	if ( ! $current_user_id ) {
		return null;
	}

	$comments = get_comments( [
		'post_id'      => $post_id,
		'user_id'      => $current_user_id,
		'type'         => 'hm_user_note',
		'type__not_in' => [],
		'number'       => 1,
	] );

	return ! empty( $comments ) ? $comments[0] : null;
}

/**
 * Setup comment form filters when rendering our block variation.
 *
 * @param string|null $pre_render   The pre-rendered content.
 * @param array       $parsed_block The block being rendered.
 * @return string|null
 */
function setup_comment_form_filters( $pre_render, $parsed_block ) {
	// Check if this is our user note form variation
	if (
		$parsed_block['blockName'] === 'core/post-comments-form' &&
		! empty( $parsed_block['attrs']['className'] ) &&
		strpos( $parsed_block['attrs']['className'], 'hm-user-note-form' ) !== false
	) {
		add_filter( 'comment_form_defaults', __NAMESPACE__ . '\\customize_comment_form_defaults' );
		add_filter( 'comment_form_field_comment', __NAMESPACE__ . '\\add_existing_comment_value' );
	}

	return $pre_render;
}

/**
 * Cleanup comment form filters after rendering.
 *
 * @param string $block_content The block content.
 * @param array  $parsed_block  The block being rendered.
 * @return string
 */
function cleanup_comment_form_filters( $block_content, $parsed_block ) {
	// Remove filters after our block is rendered
	if (
		$parsed_block['blockName'] === 'core/post-comments-form' &&
		! empty( $parsed_block['attrs']['className'] ) &&
		strpos( $parsed_block['attrs']['className'], 'hm-user-note-form' ) !== false
	) {
		remove_filter( 'comment_form_defaults', __NAMESPACE__ . '\\customize_comment_form_defaults' );
		remove_filter( 'comment_form_field_comment', __NAMESPACE__ . '\\add_existing_comment_value' );
	}

	return $block_content;
}

/**
 * Customize comment form defaults for user notes.
 *
 * @param array $defaults The default comment form arguments.
 * @return array Modified defaults.
 */
function customize_comment_form_defaults( $defaults ) {
	global $post;

	// Remove the title
	$defaults['title_reply'] = '';
	$defaults['title_reply_before'] = '';
	$defaults['title_reply_after'] = '';

	// Remove logged-in message and profile link
	$defaults['logged_in_as'] = '';

	// Add hidden comment_ID field if user has existing note
	if ( $post ) {
		$user_note = get_user_note( $post->ID );
		if ( $user_note ) {
			$defaults['fields']['comment_ID'] = sprintf(
				'<input type="hidden" name="comment_ID" id="comment_ID" value="%d" />',
				esc_attr( $user_note->comment_ID )
			);
		}
	}

	// Set type for preprocessing.
	$defaults['fields']['comment_type'] = '<input type="hidden" name="hm_user_note" value="1" />';

	return $defaults;
}

/**
 * Add existing comment value to the comment field.
 *
 * @param string $field The comment field HTML.
 * @return string Modified field HTML.
 */
function add_existing_comment_value( $field ) {
	global $post;

	if ( ! $post ) {
		return $field;
	}

	$user_note = get_user_note( $post->ID );

	if ( ! $user_note ) {
		return $field;
	}

	// Replace the textarea with one that has the existing content
	$field = preg_replace(
		'/(<textarea[^>]*>).*?(<\/textarea>)/s',
		'$1' . esc_textarea( $user_note->comment_content ) . '$2',
		$field
	);

	return $field;
}

/**
 * Dynamically filter a user's capabilities.
 *
 * @param bool[]   $allcaps Array of key/value pairs where keys represent a capability name and boolean values represent whether the user has that capability.
 * @param string[] $caps    Required primitive capabilities for the requested capability.
 * @param array    $args    { Arguments that accompany the requested capability check.
 * 		@type string    $0 Requested capability.
 * 		@type int       $1 Concerned user ID.
 * 		@type mixed  ...$2 Optional second and further parameters, typically object ID.
 * }
 * @param \WP_User $user    The user object.
 * @return bool[] Array of key/value pairs where keys represent a capability name and boolean values represent whether the user has that capability.
 */
function filter_user_has_cap( $allcaps, $caps, $args, \WP_User $user ) {
	if ( $args[0] !== 'edit_comment' ) {
		return $allcaps;
	}

	$user_id = $args[1];
	$comment_id = $args[2];

	if ( get_current_user_id() !== $user_id ) {
		return $allcaps;
	}

	$comment = \get_comment( $comment_id );

	if ( (int) $user_id !== (int) $comment->user_id ) {
		return $allcaps;
	}

	$allcaps['edit_comment'] = true;

	// Add required primitives.
	foreach ( $caps as $cap ) {
		$allcaps[ $cap ] = true;
	}

	return $allcaps;
}

/**
 * Fires before comments are retrieved to ensure we don't leak user notes.
 *
 * @param WP_Comment_Query $query Current instance of WP_Comment_Query (passed by reference).
 */
function action_reference_pre_get_comments( WP_Comment_Query $query ) : void {
	if ( $query->query_vars['type'] === 'hm_user_note' || in_array( 'hm_user_note', (array) $query->query_vars['type__in'], true ) ) {
		$query->query_vars['user_id'] = \get_current_user_id();
	} else {
		$type__not_in = (array) ( $query->query_vars['type__not_in'] ?? [] );
		$type__not_in[] = 'hm_user_note';
		$query->query_vars['type__not_in'] = $type__not_in;
	}
}
