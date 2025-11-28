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
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_frontend_assets' );
	add_action( 'wp_footer', __NAMESPACE__ . '\\output_user_note_data' );
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
				'postId' => $post->ID,
				'existingNote' => $user_note ? [
					'id' => $user_note->comment_ID,
					'content' => $user_note->comment_content,
				] : null,
				'userId' => get_current_user_id(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
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
 * Output user note data for frontend JavaScript.
 */
function output_user_note_data() {
	if ( ! is_singular() || ! is_user_logged_in() ) {
		return;
	}

	$post_id = get_the_ID();
	$user_note = CommentType\get_user_note( $post_id );

	?>
	<script type="text/javascript">
		window.hmUserNotes = {
			postId: <?php echo json_encode( $post_id ); ?>,
			existingNote: <?php echo json_encode( $user_note ? [
				'id' => $user_note->comment_ID,
				'content' => $user_note->comment_content,
			] : null ); ?>,
			userId: <?php echo json_encode( get_current_user_id() ); ?>,
			nonce: <?php echo json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
		};
	</script>
	<?php
}
