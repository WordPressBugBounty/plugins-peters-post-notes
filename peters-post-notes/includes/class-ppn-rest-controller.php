<?php
/**
 * REST routes (namespace ppn/v1) used by the admin UI.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_REST_Controller
 */
class PPN_REST_Controller {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'ppn/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$content_arg = array(
			'type'     => 'string',
			'required' => true,
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/posts/(?P<post_id>\d+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_post_notes' ),
					'permission_callback' => array( __CLASS__, 'can_use_post_notes' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_post_note' ),
					'permission_callback' => array( __CLASS__, 'can_use_post_notes' ),
					'args'                => array(
						'content'  => $content_arg,
						'personal' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notes/(?P<type>post|general)/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_note' ),
					'permission_callback' => array( __CLASS__, 'can_modify_note' ),
					'args'                => array(
						'content'  => $content_arg,
						'personal' => array( 'type' => 'boolean' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_note' ),
					'permission_callback' => array( __CLASS__, 'can_modify_note' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/general-notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_general_notes' ),
					'permission_callback' => array( 'PPN_Permissions', 'can_use_general_notes' ),
					'args'                => array(
						'personal' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_general_note' ),
					'permission_callback' => array( 'PPN_Permissions', 'can_use_general_notes' ),
					'args'                => array(
						'content'  => $content_arg,
						'personal' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission: notes enabled on the post and the user can edit it.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_use_post_notes( $request ) {
		return PPN_Permissions::can_use_post_notes( (int) $request['post_id'] );
	}

	/**
	 * Permission: the user can edit/delete the requested note. Missing notes are treated as forbidden.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_modify_note( $request ) {
		$note = PPN_Notes_Repository::get( (string) $request['type'], (int) $request['id'] );
		return $note && PPN_Permissions::can_modify_note( $note, (string) $request['type'] );
	}

	/**
	 * GET /posts/{post_id}/notes: rendered notes list for the meta box.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_post_notes( $request ) {
		$notes = array();
		foreach ( PPN_Notes_Repository::get_post_notes( (int) $request['post_id'], get_current_user_id() ) as $note ) {
			$notes[] = self::note_response( $note, PPN_Notes_Repository::TYPE_POST );
		}
		return rest_ensure_response( array( 'notes' => $notes ) );
	}

	/**
	 * POST /posts/{post_id}/notes: add a note.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_post_note( $request ) {
		$post_id = (int) $request['post_id'];
		$note_id = PPN_Notes_Repository::add_post_note( $post_id, (string) $request['content'], get_current_user_id(), (bool) $request['personal'] );

		if ( $note_id === null ) {
			return new WP_Error( 'ppn_duplicate_note', __( 'This note is the same as the latest note.', 'peters-post-notes' ), array( 'status' => 409 ) );
		}

		return self::created_response( PPN_Notes_Repository::TYPE_POST, $note_id );
	}

	/**
	 * GET /general-notes: rendered list page.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_general_notes( $request ) {
		return rest_ensure_response(
			array(
				'html' => PPN_Dashboard::general_list_html( (bool) $request['personal'], (int) $request['page'] ),
			)
		);
	}

	/**
	 * POST /general-notes: add a general note. Users without the required capability can only add private notes.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_general_note( $request ) {
		$personal = (bool) $request['personal'] || ! PPN_Permissions::can_add_public_general_note();
		$content  = PPN_Content::to_storage( (string) $request['content'], PPN_Notes_Repository::TYPE_GENERAL );

		$note_id = $content === '' ? false : PPN_Notes_Repository::insert( PPN_Notes_Repository::TYPE_GENERAL, $content, get_current_user_id(), $personal );

		$response = self::created_response( PPN_Notes_Repository::TYPE_GENERAL, $note_id );
		if ( $response instanceof WP_REST_Response ) {
			// The list the note went into, re-rendered from its first page.
			$data              = $response->get_data();
			$data['list_html'] = PPN_Dashboard::general_list_html( $personal, 0 );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * POST|PUT|PATCH /notes/{type}/{id}: update content and, for post notes, the private flag.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_note( $request ) {
		$type    = (string) $request['type'];
		$note_id = (int) $request['id'];
		$content = PPN_Content::to_storage( (string) $request['content'], $type );

		if ( $content === '' ) {
			return new WP_Error( 'ppn_empty_note', __( 'Please write a note first.', 'peters-post-notes' ), array( 'status' => 400 ) );
		}

		// Only post notes can switch between private and public (same as 1.x).
		$personal = $type === PPN_Notes_Repository::TYPE_POST && $request->has_param( 'personal' ) ? (bool) $request['personal'] : null;

		if ( ! PPN_Notes_Repository::update( $type, $note_id, $content, $personal ) ) {
			return self::db_error();
		}

		return rest_ensure_response( self::note_response( PPN_Notes_Repository::get( $type, $note_id ), $type ) );
	}

	/**
	 * DELETE /notes/{type}/{id}.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_note( $request ) {
		if ( ! PPN_Notes_Repository::delete( (string) $request['type'], (int) $request['id'] ) ) {
			return self::db_error();
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * 201 response for a newly created note, or an error.
	 *
	 * @param  string    $type    Note type.
	 * @param  int|false $note_id New note ID.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function created_response( $type, $note_id ) {
		$note = $note_id ? PPN_Notes_Repository::get( $type, $note_id ) : null;
		if ( ! $note ) {
			return self::db_error();
		}

		$response = rest_ensure_response( self::note_response( $note, $type ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Note data plus its rendered list item.
	 *
	 * @param  object $note Note row.
	 * @param  string $type Note type.
	 * @return array<string, mixed>
	 */
	private static function note_response( $note, $type ) {
		$view = PPN_Plugin::note_view( $note, $type );

		$data         = $view;
		$data['html'] = PPN_Plugin::template(
			'note-item',
			array(
				'note'        => $view,
				'show_author' => ! ( $type === PPN_Notes_Repository::TYPE_GENERAL && $view['personal'] ),
			),
			true
		);

		/**
		 * Filter the REST representation of a note.
		 *
		 * @param array<string, mixed> $data Response data.
		 * @param object               $note Note row.
		 * @param string               $type Note type.
		 */
		return apply_filters( 'ppn_rest_note_response', $data, $note, $type );
	}

	/**
	 * Generic database error.
	 *
	 * @return WP_Error
	 */
	private static function db_error() {
		return new WP_Error( 'ppn_db_error', __( 'Error: Unknown or database problem when updating note.', 'peters-post-notes' ), array( 'status' => 500 ) );
	}
}
