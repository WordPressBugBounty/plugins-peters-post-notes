<?php
/**
 * Dashboard widgets: Collaboration Notes (recent post notes) and General Notes.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Dashboard
 */
class PPN_Dashboard {

	/**
	 * Max authored posts considered "relevant" (same as 1.x).
	 */
	const RELEVANT_POSTS_LIMIT = 100;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_widgets' ) );
	}

	/**
	 * Register the widgets for users who can use them.
	 *
	 * @return void
	 */
	public static function add_widgets() {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'ppn_dashboard', __( 'Collaboration Notes', 'peters-post-notes' ), array( __CLASS__, 'render_collaboration_notes' ) );
		}
		if ( PPN_Permissions::can_use_general_notes() ) {
			wp_add_dashboard_widget( 'ppn_dashboard_general', __( 'General Notes', 'peters-post-notes' ), array( __CLASS__, 'render_general_notes' ) );
		}
	}

	/**
	 * Render the Collaboration Notes widget.
	 *
	 * @return void
	 */
	public static function render_collaboration_notes() {
		PPN_Plugin::template( 'dashboard-collaboration', array( 'items' => self::collaboration_items() ) );
	}

	/**
	 * Recent post notes relevant to the current user, per the "show_which_notes" setting.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function collaboration_items() {
		$user_id  = get_current_user_id();
		$mode     = PPN_Settings::get( 'show_which_notes' );
		$limit    = absint( PPN_Settings::get( 'num_notes_limit' ) );
		$post_ids = null;
		$exclude  = false;

		if ( $mode !== 'all' && ! PPN_Permissions::is_super() ) {
			// Relevant posts: recently modified posts by the user plus posts the user noted on.
			$authored = get_posts(
				array(
					'author'         => $user_id,
					'post_type'      => PPN_Settings::post_types(),
					'post_status'    => 'any',
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'posts_per_page' => self::RELEVANT_POSTS_LIMIT,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$post_ids = array_unique( array_merge( array_map( 'intval', $authored ), PPN_Notes_Repository::get_post_ids_noted_by( $user_id ) ) );
			$exclude  = $mode === 'others';
		}

		// Over-fetch because notes on posts the user can't edit are dropped below.
		$notes = PPN_Notes_Repository::get_recent_post_notes( $user_id, $post_ids, $exclude, $limit ? $limit * 3 : 0 );

		$items = array();
		foreach ( $notes as $note ) {
			$post = get_post( (int) $note->postid );
			if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$status  = get_post_status_object( $post->post_status );
			$items[] = array(
				'note'        => PPN_Plugin::note_view( $note, PPN_Notes_Repository::TYPE_POST ),
				'post_title'  => get_the_title( $post ),
				'post_link'   => get_edit_post_link( $post->ID ),
				'post_status' => $status ? $status->label : $post->post_status,
			);

			if ( $limit && count( $items ) >= $limit ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Render the General Notes widget.
	 *
	 * @return void
	 */
	public static function render_general_notes() {
		$user_id = get_current_user_id();

		PPN_Plugin::template(
			'dashboard-general',
			array(
				'public_html'    => self::general_list_html( false, 0 ),
				'personal_html'  => self::general_list_html( true, 0 ),
				'has_personal'   => PPN_Notes_Repository::count_general_notes( true, $user_id ) > 0,
				'can_add_public' => PPN_Permissions::can_add_public_general_note(),
			)
		);
	}

	/**
	 * Rendered list of general notes (public or the user's personal notes) with pagination.
	 *
	 * @param  bool $personal Personal notes.
	 * @param  int  $page     Zero-based page.
	 * @return string
	 */
	public static function general_list_html( $personal, $page ) {
		$user_id     = get_current_user_id();
		$per_page    = absint( PPN_Settings::get( 'num_notes_limit' ) );
		$total       = PPN_Notes_Repository::count_general_notes( $personal, $user_id );
		$total_pages = $per_page ? max( 1, (int) ceil( $total / $per_page ) ) : 1;
		$page        = min( max( 0, $page ), $total_pages - 1 );

		$notes = array();
		foreach ( PPN_Notes_Repository::get_general_notes( $personal, $user_id, $page, $per_page ) as $note ) {
			$notes[] = PPN_Plugin::note_view( $note, PPN_Notes_Repository::TYPE_GENERAL );
		}

		return PPN_Plugin::template(
			'general-notes-list',
			array(
				'notes'       => $notes,
				'personal'    => $personal,
				'page'        => $page,
				'total_pages' => $total_pages,
			),
			true
		);
	}
}
