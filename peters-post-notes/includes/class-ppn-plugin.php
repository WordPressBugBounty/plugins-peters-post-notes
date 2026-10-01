<?php
/**
 * Main plugin class: wires hooks and provides shared view helpers.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Plugin
 */
final class PPN_Plugin {

	/**
	 * Script/style handle.
	 */
	const HANDLE = 'peters-post-notes';

	/**
	 * Singleton instance.
	 *
	 * @var PPN_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PPN_Plugin
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks. Front-end requests only get the lightweight hooks (REST, cleanup).
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( 'PPN_REST_Controller', 'register_routes' ) );
		add_action( 'delete_post', array( 'PPN_Notes_Repository', 'delete_post_notes' ) );
		add_action( 'update_option_' . PPN_Settings::OPTION, array( 'PPN_Settings', 'flush_cache' ) );
		add_action( 'add_option_' . PPN_Settings::OPTION, array( 'PPN_Settings', 'flush_cache' ) );

		// Loaded everywhere: the REST controller reuses their render helpers. Hooks are admin-only.
		require_once PPN_PATH . 'includes/admin/class-ppn-meta-box.php';
		require_once PPN_PATH . 'includes/admin/class-ppn-dashboard.php';
		require_once PPN_PATH . 'includes/admin/class-ppn-list-columns.php';

		if ( is_admin() ) {
			add_action( 'admin_init', array( 'PPN_Install', 'maybe_upgrade' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			PPN_Settings::init();
			PPN_Meta_Box::init();
			PPN_Dashboard::init();
			PPN_List_Columns::init();
		}
	}

	/**
	 * Load bundled translations. wordpress.org language packs take precedence automatically.
	 * Remove once the bundled translations are imported into translate.wordpress.org (Plugin Check discourages this call).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'peters-post-notes', false, dirname( plugin_basename( PPN_FILE ) ) . '/languages' );
	}

	/**
	 * Enqueue admin assets only where notes are shown.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_editor    = $screen->base === 'post' && in_array( $screen->post_type, PPN_Settings::post_types(), true );
		$is_dashboard = $screen->base === 'dashboard';
		if ( ! $is_editor && ! $is_dashboard ) {
			return;
		}

		$is_block_editor = $is_editor && $screen->is_block_editor();
		$deps            = array( 'jquery', 'wp-api-fetch' );
		if ( $is_block_editor ) {
			$deps[] = 'wp-data';
		}

		wp_enqueue_style( self::HANDLE, PPN_URL . 'assets/css/admin.css', array(), PPN_VERSION );
		wp_enqueue_script( self::HANDLE, PPN_URL . 'assets/js/admin.js', $deps, PPN_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'ppnData',
			array(
				'isBlockEditor'    => $is_block_editor,
				'movePublishPanel' => $is_editor && ! $is_block_editor && (bool) PPN_Settings::get( 'add_post_note_in_publish_panel' ),
				'i18n'             => array(
					'genericError' => __( 'Error: Unknown or database problem when updating note.', 'peters-post-notes' ),
					'emptyNote'    => __( 'Please write a note first.', 'peters-post-notes' ),
				),
			)
		);
	}

	/**
	 * Load a template from /templates.
	 *
	 * @param  string               $name      Template name without extension.
	 * @param  array<string, mixed> $args      Variables available to the template as $args.
	 * @param  bool                 $to_string Return output instead of printing it.
	 * @return string
	 */
	public static function template( $name, $args = array(), $to_string = false ) {
		$file = PPN_PATH . 'templates/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}

		if ( $to_string ) {
			ob_start();
		}

		include $file;

		return $to_string ? (string) ob_get_clean() : '';
	}

	/**
	 * View model for a single note, shared by server-rendered lists and REST responses.
	 *
	 * @param  object $note Note row.
	 * @param  string $type Note type.
	 * @return array<string, mixed>
	 */
	public static function note_view( $note, $type ) {
		return array(
			'id'           => (int) $note->noteid,
			'type'         => $type,
			'post_id'      => isset( $note->postid ) ? (int) $note->postid : 0,
			'author_id'    => (int) $note->author,
			'author_name'  => self::author_name( (int) $note->author ),
			'is_own'       => (int) $note->author === get_current_user_id(),
			'date'         => mysql2date( __( 'M j, Y \a\t G:i', 'peters-post-notes' ), $note->notetime ),
			'date_iso'     => mysql2date( 'c', $note->notetime ),
			'personal'     => (bool) (int) $note->personal,
			'content_html' => PPN_Content::render( $note->notecontent ),
			'edit_value'   => PPN_Content::to_edit_value( $note->notecontent ),
			'can_modify'   => PPN_Permissions::can_modify_note( $note, $type ),
		);
	}

	/**
	 * Author display name, or a generic label for deleted users.
	 *
	 * @param  int $user_id User ID.
	 * @return string
	 */
	public static function author_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'User', 'peters-post-notes' );
	}
}
