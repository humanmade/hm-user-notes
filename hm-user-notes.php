<?php
/**
 * Plugin Name: HM User Notes
 * Description: Allows logged-in users to add private comments to posts that only they can see
 * Version: 1.0.0
 * Author: Human Made
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

namespace HM\UserNotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HM_USER_NOTES_DIR', __DIR__ );
define( 'HM_USER_NOTES_URL', plugins_url( '', __FILE__ ) );

require_once __DIR__ . '/inc/comment-type.php';
require_once __DIR__ . '/inc/block-variations.php';
require_once __DIR__ . '/inc/post-meta.php';

/**
 * Initialize the plugin.
 */
function init() {
	CommentType\init();
	BlockVariations\init();
	PostMeta\init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
