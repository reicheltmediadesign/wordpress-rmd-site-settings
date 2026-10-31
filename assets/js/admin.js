/**
 * RMD Site Settings – settings page: shows the post type lists only for the
 * modes that use them and asks before deleting comments.
 */
( function () {
	'use strict';

	const config = window.rmdSsAdmin || {};

	function bindToggle( radioSelector, rowSelector, value ) {
		const radios = document.querySelectorAll( radioSelector );
		const row = document.querySelector( rowSelector );
		if ( ! radios.length || ! row ) {
			return;
		}
		const update = function () {
			const checked = document.querySelector( radioSelector + ':checked' );
			row.classList.toggle( 'rmd-ss-hidden', ! checked || checked.value !== value );
		};
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', update );
		} );
		update();
	}

	bindToggle( 'input[name="rmd_ss_settings[comments][mode]"]', '.rmd-ss-post-types', 'post_types' );
	bindToggle( 'input[name="delete_mode"]', '.rmd-ss-delete-post-types', 'post_types' );

	const cleanup = document.querySelector( '.rmd-ss-cleanup' );
	if ( cleanup && config.confirmDelete ) {
		cleanup.addEventListener( 'submit', function ( event ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( config.confirmDelete ) ) {
				event.preventDefault();
			}
		} );
	}
} )();
