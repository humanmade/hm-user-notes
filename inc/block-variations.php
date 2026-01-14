<?php
/**
 * Block variations for user notes.
 */

namespace HM\UserNotes\BlockVariations;

use HM\UserNotes\CommentType;

/**
 * Initialize block variations.
 */
function init() {
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
	add_filter( 'render_block_core/post-comments-form', __NAMESPACE__ . '\\render_block_post_comments_form', 10, 3 );
}

/**
 * Enqueue editor assets.
 */
function enqueue_editor_assets() {
	$asset_file = include HM_USER_NOTES_DIR . '/build/editor.asset.php';

	wp_enqueue_script(
		'hm-user-notes-editor',
		HM_USER_NOTES_URL . '/build/editor.js',
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	// Enqueue editor styles
	if ( file_exists( HM_USER_NOTES_DIR . '/build/editor.css' ) ) {
		wp_enqueue_style(
			'hm-user-notes-editor',
			HM_USER_NOTES_URL . '/build/editor.css',
			[],
			$asset_file['version']
		);
	}

	// Pass existing user note data to the editor
	global $post;
	if ( $post ) {
		$user_note = CommentType\get_user_note( $post->ID );
		wp_localize_script(
			'hm-user-notes-editor',
			'hmUserNotes',
			[
				"$post->ID" => [
					'postId' => $post->ID,
					'existingNote' => $user_note ? [
						'id' => $user_note->comment_ID,
						'content' => $user_note->comment_content,
					] : null,
					'userId' => get_current_user_id(),
					'nonce' => \wp_create_nonce( 'wp_rest' ),
				],
			]
		);
	}
}

/**
 * Enqueue frontend assets.
 */
function enqueue_frontend_assets() {
	$asset_file = include HM_USER_NOTES_DIR . '/build/frontend.asset.php';

	wp_enqueue_script(
		'hm-user-notes-frontend',
		HM_USER_NOTES_URL . '/build/frontend.js',
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	// Enqueue frontend styles
	if ( file_exists( HM_USER_NOTES_DIR . '/build/frontend.css' ) ) {
		wp_enqueue_style(
			'hm-user-notes-frontend',
			HM_USER_NOTES_URL . '/build/frontend.css',
			[],
			$asset_file['version']
		);
	}
}

/**
 * Filters the content of a single block.
 *
 * @param string    $block_content The block content.
 * @param array     $block         The full block, including name and attributes.
 * @param \WP_Block $instance      The block instance.
 * @return string The block content.
 */
function render_block_post_comments_form( $block_content, $block, \WP_Block $instance ) {
	if ( strpos( $block['attrs']['className'] ?? '', 'hm-user-note-form' ) === false ) {
		return $block_content;
	}

	if ( ! is_user_logged_in() ) {
		return '';
	}

	// We need the frontend JS and CSS now.
	enqueue_frontend_assets();

	$post_id = $instance->context['postId'] ?? get_the_ID();
	$user_note = CommentType\get_user_note( $post_id );

	$block_content .= sprintf(
		<<<'HTML'
			<script type="text/javascript">
				window.hmUserNotes = window.hmUserNotes || {};
				window.hmUserNotes["%1$d"] = {
					postId: %1$d,
					existingNote: %2$s,
					userId: %3$d,
					nonce: %4$s
				};
			</script>
			HTML,
		json_encode( $post_id ),
		json_encode( $user_note ? [
			'id' => $user_note->comment_ID,
			'content' => $user_note->comment_content,
		] : null ),
		json_encode( get_current_user_id() ),
		json_encode( wp_create_nonce( 'wp_rest' ) )
	);

	return $block_content;
}
