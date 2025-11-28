/**
 * Frontend functionality for HM User Notes.
 */

import { __ } from '@wordpress/i18n';

/**
 * Debounce function to limit how often a function is called.
 *
 * @param {Function} func The function to debounce.
 * @param {number}   wait The delay in milliseconds.
 * @return {Function} The debounced function.
 */
function debounce( func, wait ) {
	let timeout;
	return function executedFunction( ...args ) {
		const later = () => {
			clearTimeout( timeout );
			func( ...args );
		};
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
	};
}

/**
 * Save a note via the REST API.
 *
 * @param {string}  content   The note content.
 * @param {number}  commentId The comment ID (if updating).
 * @param {number}  postId    The post ID (if creating).
 * @param {Element} form      The form element.
 */
function saveNote( content, commentId, postId, form ) {
	const isUpdate = !! commentId;
	const url = isUpdate
		? `/wp-json/wp/v2/comments/${ commentId }`
		: '/wp-json/wp/v2/comments';

	const body = isUpdate
		? { content }
		: {
				content,
				post: postId,
				author: window.hmUserNotes?.userId,
		  };

	fetch( url, {
		method: 'POST',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.hmUserNotes?.nonce || '',
			'X-HM-User-Note': 1,
		},
		body: JSON.stringify( body ),
	} )
		.then( ( response ) => {
			if ( response.ok ) {
				return response.json();
			}
			throw new Error( 'Save failed' );
		} )
		.then( ( data ) => {
			// Show success indicator
			showSaveIndicator( form, 'saved' );

			// If this was a new note, update the stored ID
			if ( ! isUpdate && data.id ) {
				if ( window.hmUserNotes ) {
					window.hmUserNotes.existingNote = {
						id: data.id,
						content,
					};
				}

				// Update the hidden comment_ID field
				const commentIdField = form.querySelector( '#comment_ID' );
				if ( commentIdField ) {
					commentIdField.value = data.id;
				}
			} else if ( window.hmUserNotes?.existingNote ) {
				window.hmUserNotes.existingNote.content = content;
			}
		} )
		.catch( ( error ) => {
			console.error( 'Error saving note:', error ); // eslint-disable-line no-console
			showSaveIndicator( form, 'error' );
		} );
}

/**
 * Show a save status indicator.
 *
 * @param {Element} form   The form element.
 * @param {string}  status The status ('saving', 'saved', 'error').
 */
function showSaveIndicator( form, status ) {
	let indicator = form.querySelector( '.hm-user-note-indicator' );

	if ( ! indicator ) {
		indicator = document.createElement( 'div' );
		indicator.className = 'hm-user-note-indicator';
		const commentField = form.querySelector( '#comment' );
		if ( commentField ) {
			commentField.parentNode.appendChild( indicator );
		}
	}

	const messages = {
		saving: __( 'Saving…', 'hm-user-notes' ),
		saved: __( 'Saved', 'hm-user-notes' ),
		error: __( 'Error saving', 'hm-user-notes' ),
	};

	indicator.textContent = messages[ status ] || '';
	indicator.className = `hm-user-note-indicator hm-user-note-indicator--${ status }`;

	// Auto-hide success/error messages
	if ( status === 'saved' || status === 'error' ) {
		setTimeout( () => {
			indicator.textContent = '';
			indicator.className = 'hm-user-note-indicator';
		}, 2000 );
	}
}

/**
 * Initialize frontend functionality.
 */
function initFrontend() {
	const userNoteForms = document.querySelectorAll(
		'.hm-user-note-form form.comment-form'
	);

	userNoteForms.forEach( ( form ) => {
		const commentField = form.querySelector( '#comment' );
		const submitButton = form.querySelector( '[type="submit"]' );

		// Hide the submit button
		if ( submitButton ) {
			submitButton.style.display = 'none';
		}

		// Add hidden field to mark as user note
		const hiddenField = document.createElement( 'input' );
		hiddenField.type = 'hidden';
		hiddenField.name = 'hm_user_note';
		hiddenField.value = '1';
		form.appendChild( hiddenField );

		// Create debounced save function
		const debouncedSave = debounce( () => {
			if ( ! commentField ) {
				return;
			}

			const content = commentField.value.trim();

			// Don't save empty notes
			if ( ! content ) {
				return;
			}

			showSaveIndicator( form, 'saving' );

			const commentId = window.hmUserNotes?.existingNote?.id;
			const postId = window.hmUserNotes?.postId;

			saveNote( content, commentId, postId, form );
		}, 1000 );

		// Add keyup listener for auto-save
		if ( commentField ) {
			commentField.addEventListener( 'keyup', debouncedSave );
		}

		// Prevent default form submission
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			return false;
		} );
	} );
}

// Initialize on DOM ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initFrontend );
} else {
	initFrontend();
}
