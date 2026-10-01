/**
 * Peter's Post Notes admin UI: meta box and dashboard widgets.
 * Talks to the ppn/v1 REST routes; the server returns pre-rendered, escaped list items.
 */
( function ( $, wp, config ) {
	'use strict';

	var apiFetch = wp.apiFetch;

	/**
	 * Show an error inside a context element (note item or add box).
	 *
	 * @param {jQuery} $ctx Context.
	 * @param {Object} err  apiFetch error or null.
	 * @param {string} msg  Message override.
	 */
	function showError( $ctx, err, msg ) {
		var text = msg || ( err && err.message ) || config.i18n.genericError;
		$ctx.children( '.ppn-error' ).text( text ).prop( 'hidden', false );
	}

	function clearError( $ctx ) {
		$ctx.children( '.ppn-error' ).text( '' ).prop( 'hidden', true );
	}

	/**
	 * Disable buttons in a context while a request runs.
	 *
	 * @param {jQuery}  $ctx Context.
	 * @param {boolean} busy Busy state.
	 */
	function setBusy( $ctx, busy ) {
		$ctx.find( 'button' ).prop( 'disabled', busy );
		$ctx.toggleClass( 'ppn-busy', busy );
	}

	/* ------------------------------------------------------------------
	 * Edit / delete an existing note (meta box and General Notes widget).
	 * ------------------------------------------------------------------ */

	function toggleForm( $note, open ) {
		$note.children( '.ppn-note-view' ).prop( 'hidden', open );
		$note.children( '.ppn-note-form' ).prop( 'hidden', ! open );
		if ( open ) {
			$note.find( '.ppn-note-text' ).trigger( 'focus' );
		}
	}

	$( document ).on( 'click', '.ppn-note .ppn-edit', function () {
		toggleForm( $( this ).closest( '.ppn-note' ), true );
	} );

	$( document ).on( 'click', '.ppn-note .ppn-cancel', function () {
		var $note = $( this ).closest( '.ppn-note' );
		clearError( $note );
		toggleForm( $note, false );
	} );

	$( document ).on( 'click', '.ppn-note .ppn-save', function () {
		var $note = $( this ).closest( '.ppn-note' );
		var $private = $note.find( '.ppn-note-private' );
		var payload = { content: $note.find( '.ppn-note-text' ).val() };

		if ( ! $.trim( payload.content ) ) {
			showError( $note, null, config.i18n.emptyNote );
			return;
		}
		if ( $private.length ) {
			payload.personal = $private.is( ':checked' );
		}

		clearError( $note );
		setBusy( $note, true );

		apiFetch( {
			path: 'ppn/v1/notes/' + $note.data( 'note-type' ) + '/' + $note.data( 'note-id' ),
			method: 'POST',
			data: payload,
		} )
			.then( function ( res ) {
				$note.replaceWith( res.html );
			} )
			.catch( function ( err ) {
				setBusy( $note, false );
				showError( $note, err );
			} );
	} );

	$( document ).on( 'click', '.ppn-note .ppn-delete', function () {
		var $note = $( this ).closest( '.ppn-note' );
		var $list = $note.closest( '.ppn-notes-list' );

		clearError( $note );
		setBusy( $note, true );

		apiFetch( {
			path: 'ppn/v1/notes/' + $note.data( 'note-type' ) + '/' + $note.data( 'note-id' ),
			method: 'DELETE',
		} )
			.then( function () {
				$note.fadeOut( 200, function () {
					$note.remove();
					if ( ! $list.children( '.ppn-note' ).length ) {
						$list.siblings( '.ppn-empty' ).prop( 'hidden', false );
					}
				} );
			} )
			.catch( function ( err ) {
				setBusy( $note, false );
				showError( $note, err );
			} );
	} );

	/* ------------------------------------------------------------------
	 * Meta box: add a post note.
	 * ------------------------------------------------------------------ */

	var addingPostNote = false;

	/**
	 * Add the note typed in the meta box. Returns a promise, or null when there is nothing to add.
	 */
	function addPostNote() {
		var $box = $( '.ppn-meta-box' );
		var $add = $( '#ppn-add-post-note' );
		var $text = $add.find( '#ppn_post_note' );
		var $private = $add.find( '.ppn-add-private' );
		var content = $text.val();

		if ( ! $box.length || addingPostNote ) {
			return null;
		}
		if ( ! $.trim( content ) ) {
			showError( $add, null, config.i18n.emptyNote );
			return null;
		}

		addingPostNote = true;
		clearError( $add );
		setBusy( $add, true );

		return apiFetch( {
			path: 'ppn/v1/posts/' + $box.data( 'post-id' ) + '/notes',
			method: 'POST',
			data: { content: content, personal: $private.is( ':checked' ) },
		} )
			.then( function ( res ) {
				$box.find( '.ppn-notes-list' ).prepend( res.html );
				$box.find( '.ppn-empty' ).prop( 'hidden', true );
				$text.val( '' );
				$private.prop( 'checked', false );
			} )
			.catch( function ( err ) {
				// Duplicate of the latest note (e.g. double click): nothing to add.
				if ( err && err.code === 'ppn_duplicate_note' ) {
					$text.val( '' );
					return;
				}
				showError( $add, err );
			} )
			.finally( function () {
				addingPostNote = false;
				setBusy( $add, false );
			} );
	}

	$( document ).on( 'click', '#ppn-add-post-note .ppn-add', function () {
		addPostNote();
	} );

	$( function () {
		// Classic editor option: move the "Add note" box into the Publish panel (still inside the post form).
		if ( config.movePublishPanel ) {
			$( '#major-publishing-actions' ).prepend( $( '#ppn-add-post-note' ) );
		}

		// Block editor: the meta box form is saved in a separate request after the post, so the classic
		// save_post fallback cannot refresh the list. Add the typed note via REST when the post is saved instead.
		if ( config.isBlockEditor && wp.data ) {
			$( '#ppn_post_note, #ppn-add-post-note .ppn-add-private' ).removeAttr( 'name' );

			var wasSaving = false;
			wp.data.subscribe( function () {
				var editor = wp.data.select( 'core/editor' );
				if ( ! editor ) {
					return;
				}
				var saving = editor.isSavingPost() && ! editor.isAutosavingPost();
				if ( saving && ! wasSaving && $.trim( $( '#ppn_post_note' ).val() ) ) {
					addPostNote();
				}
				wasSaving = saving;
			} );
		}
	} );

	/* ------------------------------------------------------------------
	 * General Notes dashboard widget.
	 * ------------------------------------------------------------------ */

	$( document ).on( 'click', '.ppn-add-general-note', function () {
		var $add = $( this ).closest( '.ppn-add-general' );
		var $widget = $add.closest( '.ppn-general' );
		var $text = $add.find( '.ppn-general-text' );
		var $private = $add.find( '.ppn-add-private' );
		var content = $text.val();

		if ( ! $.trim( content ) ) {
			showError( $add, null, config.i18n.emptyNote );
			return;
		}

		clearError( $add );
		setBusy( $add, true );

		apiFetch( {
			path: 'ppn/v1/general-notes',
			method: 'POST',
			data: { content: content, personal: $private.is( ':checked' ) },
		} )
			.then( function ( res ) {
				var personal = res.personal ? 1 : 0;
				$widget.find( '.ppn-general-list[data-personal="' + personal + '"]' ).html( res.list_html );
				if ( personal ) {
					$widget.find( '.ppn-general-personal' ).prop( 'hidden', false );
				}
				$text.val( '' );
				if ( ! $private.is( ':disabled' ) ) {
					$private.prop( 'checked', false );
				}
			} )
			.catch( function ( err ) {
				showError( $add, err );
			} )
			.finally( function () {
				setBusy( $add, false );
			} );
	} );

	$( document ).on( 'click', '.ppn-general .ppn-page', function () {
		var $list = $( this ).closest( '.ppn-general-list' );

		setBusy( $list, true );
		apiFetch( {
			path: 'ppn/v1/general-notes?personal=' + $list.data( 'personal' ) + '&page=' + $( this ).data( 'page' ),
		} )
			.then( function ( res ) {
				$list.html( res.html );
			} )
			.catch( function ( err ) {
				showError( $list.closest( '.ppn-general' ).children( '.ppn-add-general' ), err );
			} )
			.finally( function () {
				setBusy( $list, false );
			} );
	} );
} )( jQuery, window.wp, window.ppnData );
