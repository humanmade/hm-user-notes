/**
 * Block variations for HM User Notes - Editor only.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Register comment form block variation for user notes.
 */
registerBlockVariation( 'core/post-comments-form', {
	name: 'hm-user-notes/note-form',
	title: __( 'User Note Form', 'hm-user-notes' ),
	description: __( 'A form for adding private user notes', 'hm-user-notes' ),
	attributes: {
		className: 'hm-user-note-form',
	},
	isActive: ( blockAttributes ) =>
		blockAttributes.className?.includes( 'hm-user-note-form' ),
} );
