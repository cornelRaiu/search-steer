// Ticks the "Hide from site search" checkbox in quick edit for the edited post.
( function ( config ) {
	'use strict';

	if ( typeof window.inlineEditPost === 'undefined' ) {
		return;
	}

	const edit = window.inlineEditPost.edit;

	window.inlineEditPost.edit = function ( id ) {
		const result = edit.apply( this, arguments );
		const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : id;
		const state = document.querySelector( '#post-' + postId + ' .search-steer-hidden-state' );
		const checkbox = document.querySelector(
			'#edit-' + postId + ' input[name="' + config.field + '"]'
		);

		if ( state && checkbox ) {
			checkbox.checked = state.dataset.hidden === '1';
		}

		return result;
	};
} )( window.searchSteerQuickEdit );
