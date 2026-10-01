<?php
/**
 * "Latest note" column in post list tables.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_List_Columns
 */
class PPN_List_Columns {

	/**
	 * Column key.
	 */
	const COLUMN = 'ppn_notes';

	/**
	 * Latest notes for the posts on the current screen, keyed by post ID.
	 *
	 * @var array<int, object>|null
	 */
	private static $latest = null;

	/**
	 * Whether $latest holds every listed post.
	 *
	 * @var bool
	 */
	private static $primed = false;

	/**
	 * Max post IDs primed in one query.
	 */
	const PRIME_LIMIT = 500;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'load-edit.php', array( __CLASS__, 'register_columns' ) );
	}

	/**
	 * Hook the column for the current post type when enabled.
	 *
	 * @return void
	 */
	public static function register_columns() {
		$screen = get_current_screen();
		if ( ! $screen || ! PPN_Settings::get( 'show_latest_notes_column' ) || ! in_array( $screen->post_type, PPN_Settings::post_types(), true ) ) {
			return;
		}

		add_filter( "manage_{$screen->post_type}_posts_columns", array( __CLASS__, 'add_column' ) );
		add_action( "manage_{$screen->post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
	}

	/**
	 * Add the column header.
	 *
	 * @param  array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function add_column( $columns ) {
		$columns[ self::COLUMN ] = __( 'Latest note', 'peters-post-notes' );
		return $columns;
	}

	/**
	 * Render the column cell.
	 *
	 * @param  string $column  Column key.
	 * @param  int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		if ( $column !== self::COLUMN ) {
			return;
		}

		if ( self::$latest === null ) {
			self::prime();
		}

		$post_id = (int) $post_id;
		if ( ! self::$primed && ! array_key_exists( $post_id, self::$latest ) ) {
			// Not primed (very large hierarchical list): fetch this row only.
			self::$latest += PPN_Notes_Repository::get_latest_public_notes( array( $post_id ) ) + array( $post_id => null );
		}

		$note = self::$latest[ $post_id ] ?? null;
		if ( ! $note || ! current_user_can( 'edit_post', $post_id ) ) {
			echo '&ndash;';
			return;
		}

		PPN_Plugin::template( 'list-column', array( 'note' => PPN_Plugin::note_view( $note, PPN_Notes_Repository::TYPE_POST ) ) );
	}

	/**
	 * Load the latest note of every listed post in one query.
	 *
	 * @return void
	 */
	private static function prime() {
		global $wp_query;

		$ids = array();
		if ( $wp_query instanceof WP_Query && is_array( $wp_query->posts ) ) {
			$ids = wp_list_pluck( $wp_query->posts, 'ID' );
		}

		// Hierarchical lists (pages) load every row ID; above the cap, fall back to per-row lookups.
		self::$primed = count( $ids ) <= self::PRIME_LIMIT;
		self::$latest = self::$primed ? PPN_Notes_Repository::get_latest_public_notes( $ids ) : array();
	}
}
