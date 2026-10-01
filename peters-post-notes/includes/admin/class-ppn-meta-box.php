<?php
/**
 * Notes meta box on the post edit screen.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Meta_Box
 */
class PPN_Meta_Box {

	/**
	 * Nonce action/field for the classic "note on save" flow.
	 */
	const NONCE_ACTION = 'ppn_save_post_note';
	const NONCE_FIELD  = 'ppn_post_note_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_note_on_post_save' ) );
	}

	/**
	 * Add the meta box to enabled post types.
	 *
	 * @param  string             $post_type Post type.
	 * @param  WP_Post|WP_Comment $post      Post (a comment on the comment edit screen).
	 * @return void
	 */
	public static function add( $post_type, $post ) {
		if ( ! in_array( $post_type, PPN_Settings::post_types(), true ) || ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		add_meta_box( 'collaboration', __( 'Notes', 'peters-post-notes' ), array( __CLASS__, 'render' ), $post_type, 'side', 'high' );
	}

	/**
	 * Render the meta box.
	 *
	 * @param  WP_Post $post Post.
	 * @return void
	 */
	public static function render( $post ) {
		$notes = array();
		foreach ( PPN_Notes_Repository::get_post_notes( $post->ID, get_current_user_id() ) as $note ) {
			$notes[] = PPN_Plugin::note_view( $note, PPN_Notes_Repository::TYPE_POST );
		}

		PPN_Plugin::template(
			'meta-box',
			array(
				'post'  => $post,
				'notes' => $notes,
			)
		);
	}

	/**
	 * Classic editor: add the note typed in the meta box when the post is saved.
	 * Field names ppn_post_note / ppn_private are a public contract (Peter's Collaboration E-mails reads them).
	 *
	 * @param  int $post_id Post ID.
	 * @return void
	 */
	public static function save_note_on_post_save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST['ppn_post_note'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! PPN_Permissions::can_use_post_notes( $post_id ) ) {
			return;
		}

		// Raw text; PPN_Content::to_storage() escapes or kses-filters it.
		$raw = wp_unslash( $_POST['ppn_post_note'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return;
		}

		PPN_Notes_Repository::add_post_note( $post_id, $raw, get_current_user_id(), ! empty( $_POST['ppn_private'] ) );
	}
}
