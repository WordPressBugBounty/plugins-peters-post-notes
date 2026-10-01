<?php
/**
 * Data access for post notes and general notes. All queries are prepared.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come only from PPN_Notes_Repository::table() (fixed names); all values are prepared.

/**
 * Class PPN_Notes_Repository
 *
 * Note rows are returned as stdClass with: noteid, postid (post notes only), notecontent, author, notetime, personal.
 */
class PPN_Notes_Repository {

	/**
	 * Note type: note attached to a post.
	 */
	const TYPE_POST = 'post';

	/**
	 * Note type: general dashboard note.
	 */
	const TYPE_GENERAL = 'general';

	/**
	 * Table name for a note type.
	 *
	 * @param  string $type Note type.
	 * @return string
	 */
	public static function table( $type ) {
		global $wpdb;
		return $type === self::TYPE_GENERAL ? $wpdb->prefix . 'generalnotes' : $wpdb->prefix . 'collabnotes';
	}

	/**
	 * Whether a note type is valid.
	 *
	 * @param  mixed $type Note type.
	 * @return bool
	 */
	public static function is_valid_type( $type ) {
		return in_array( $type, array( self::TYPE_POST, self::TYPE_GENERAL ), true );
	}

	/**
	 * Get a single note.
	 *
	 * @param  string $type    Note type.
	 * @param  int    $note_id Note ID.
	 * @return object|null
	 */
	public static function get( $type, $note_id ) {
		global $wpdb;

		if ( ! self::is_valid_type( $type ) ) {
			return null;
		}

		$table = self::table( $type );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE noteid = %d", $note_id ) );
	}

	/**
	 * Notes on a post visible to a user: public notes plus the user's private notes.
	 *
	 * @param  int $post_id Post ID.
	 * @param  int $user_id Viewing user ID.
	 * @return object[]
	 */
	public static function get_post_notes( $post_id, $user_id ) {
		global $wpdb;

		$table = self::table( self::TYPE_POST );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE postid = %d AND ( personal = 0 OR author = %d ) ORDER BY notetime DESC, noteid DESC", $post_id, $user_id ) );
	}

	/**
	 * Latest public note per post, for a batch of posts.
	 *
	 * @param  int[] $post_ids Post IDs.
	 * @return array<int, object> Keyed by post ID.
	 */
	public static function get_latest_public_notes( $post_ids ) {
		global $wpdb;

		$post_ids = array_filter( array_map( 'absint', $post_ids ) );
		if ( ! $post_ids ) {
			return array();
		}

		$table        = self::table( self::TYPE_POST );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// One row per post: the highest (newest) public note ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT n.* FROM {$table} n INNER JOIN ( SELECT MAX(noteid) AS noteid FROM {$table} WHERE personal = 0 AND postid IN ( {$placeholders} ) GROUP BY postid ) m ON n.noteid = m.noteid", $post_ids ) );

		$latest = array();
		foreach ( $rows as $row ) {
			$latest[ (int) $row->postid ] = $row;
		}
		return $latest;
	}

	/**
	 * Content of the latest note on a post (any author), used to block double submissions.
	 *
	 * @param  int $post_id Post ID.
	 * @return string|null
	 */
	public static function get_latest_post_note_content( $post_id ) {
		global $wpdb;

		$table = self::table( self::TYPE_POST );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( $wpdb->prepare( "SELECT notecontent FROM {$table} WHERE postid = %d ORDER BY notetime DESC, noteid DESC LIMIT 1", $post_id ) );
	}

	/**
	 * IDs of posts the user has written notes on.
	 *
	 * @param  int $user_id User ID.
	 * @return int[]
	 */
	public static function get_post_ids_noted_by( $user_id ) {
		global $wpdb;

		$table = self::table( self::TYPE_POST );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT postid FROM {$table} WHERE author = %d", $user_id ) ) );
	}

	/**
	 * Recent post notes for the dashboard.
	 *
	 * @param  int        $user_id        Viewing user ID (sees own private notes).
	 * @param  int[]|null $post_ids       Restrict to these posts, or null for all posts.
	 * @param  bool       $exclude_author Exclude notes written by the viewing user.
	 * @param  int        $limit          Max rows, 0 = unlimited.
	 * @return object[]
	 */
	public static function get_recent_post_notes( $user_id, $post_ids, $exclude_author, $limit ) {
		global $wpdb;

		$table = self::table( self::TYPE_POST );
		$where = array( '( personal = 0 OR author = %d )' );
		$args  = array( $user_id );

		if ( is_array( $post_ids ) ) {
			$post_ids = array_filter( array_map( 'absint', $post_ids ) );
			if ( ! $post_ids ) {
				return array();
			}
			$where[] = 'postid IN ( ' . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ' )';
			$args    = array_merge( $args, $post_ids );
		}

		if ( $exclude_author ) {
			$where[] = 'author != %d';
			$args[]  = $user_id;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY notetime DESC, noteid DESC';
		if ( $limit > 0 ) {
			$sql   .= ' LIMIT %d';
			$args[] = $limit;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * General notes: public notes, or the user's personal notes.
	 *
	 * @param  bool $personal Personal notes of the user instead of public notes.
	 * @param  int  $user_id  User ID.
	 * @param  int  $page     Zero-based page.
	 * @param  int  $per_page Notes per page, 0 = unlimited.
	 * @return object[]
	 */
	public static function get_general_notes( $personal, $user_id, $page, $per_page ) {
		global $wpdb;

		list( $where, $args ) = self::general_where( $personal, $user_id );

		$table = self::table( self::TYPE_GENERAL );
		$sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY notetime DESC, noteid DESC";
		if ( $per_page > 0 ) {
			$sql   .= ' LIMIT %d, %d';
			$args[] = max( 0, $page ) * $per_page;
			$args[] = $per_page;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Count general notes.
	 *
	 * @param  bool $personal Personal notes of the user instead of public notes.
	 * @param  int  $user_id  User ID.
	 * @return int
	 */
	public static function count_general_notes( $personal, $user_id ) {
		global $wpdb;

		list( $where, $args ) = self::general_where( $personal, $user_id );

		$table = self::table( self::TYPE_GENERAL );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are in $where.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $args ) );
	}

	/**
	 * WHERE clause for general notes lists.
	 *
	 * @param  bool $personal Personal notes.
	 * @param  int  $user_id  User ID.
	 * @return array{0: string, 1: array<int, int>}
	 */
	private static function general_where( $personal, $user_id ) {
		if ( $personal ) {
			return array( 'personal = 1 AND author = %d', array( $user_id ) );
		}
		return array( 'personal = %d', array( 0 ) );
	}

	/**
	 * Insert a note.
	 *
	 * @param  string $type     Note type.
	 * @param  string $content  Content in storage format.
	 * @param  int    $author   Author user ID.
	 * @param  bool   $personal Private note.
	 * @param  int    $post_id  Post ID (post notes only).
	 * @return int|false New note ID or false on failure.
	 */
	public static function insert( $type, $content, $author, $personal, $post_id = 0 ) {
		global $wpdb;

		if ( ! self::is_valid_type( $type ) ) {
			return false;
		}

		$data = array(
			'notecontent' => $content,
			'author'      => (int) $author,
			'notetime'    => current_time( 'mysql' ),
			'personal'    => $personal ? 1 : 0,
		);
		if ( $type === self::TYPE_POST ) {
			$data = array( 'postid' => (int) $post_id ) + $data;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->insert( self::table( $type ), $data ) ) {
			return false;
		}

		$note_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a note is added.
		 *
		 * @param int                  $note_id Note ID.
		 * @param string               $type    Note type ('post' or 'general').
		 * @param array<string, mixed> $data    Inserted row data.
		 */
		do_action( 'ppn_note_added', $note_id, $type, $data );

		return $note_id;
	}

	/**
	 * Add a post note from raw input, unless it repeats the latest note on the post (double-submit guard).
	 *
	 * @param  int    $post_id  Post ID.
	 * @param  string $raw      Raw (unslashed) note text.
	 * @param  int    $author   Author user ID.
	 * @param  bool   $personal Private note.
	 * @return int|false|null Note ID, false on empty content or DB error, null when skipped as duplicate.
	 */
	public static function add_post_note( $post_id, $raw, $author, $personal ) {
		$content = PPN_Content::to_storage( $raw );
		if ( $content === '' ) {
			return false;
		}
		if ( $content === self::get_latest_post_note_content( $post_id ) ) {
			return null;
		}

		return self::insert( self::TYPE_POST, $content, $author, $personal, $post_id );
	}

	/**
	 * Update a note's content and optionally its private flag.
	 *
	 * @param  string    $type     Note type.
	 * @param  int       $note_id  Note ID.
	 * @param  string    $content  Content in storage format.
	 * @param  bool|null $personal New private flag, or null to keep.
	 * @return bool
	 */
	public static function update( $type, $note_id, $content, $personal = null ) {
		global $wpdb;

		if ( ! self::is_valid_type( $type ) ) {
			return false;
		}

		$data = array( 'notecontent' => $content );
		if ( $personal !== null ) {
			$data['personal'] = $personal ? 1 : 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( self::table( $type ), $data, array( 'noteid' => (int) $note_id ) );
		if ( $result === false ) {
			return false;
		}

		/**
		 * Fires after a note is updated.
		 *
		 * @param int                  $note_id Note ID.
		 * @param string               $type    Note type.
		 * @param array<string, mixed> $data    Updated fields.
		 */
		do_action( 'ppn_note_updated', (int) $note_id, $type, $data );

		return true;
	}

	/**
	 * Delete a note.
	 *
	 * @param  string $type    Note type.
	 * @param  int    $note_id Note ID.
	 * @return bool
	 */
	public static function delete( $type, $note_id ) {
		global $wpdb;

		if ( ! self::is_valid_type( $type ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->delete( self::table( $type ), array( 'noteid' => (int) $note_id ) ) ) {
			return false;
		}

		/**
		 * Fires after a note is deleted.
		 *
		 * @param int    $note_id Note ID.
		 * @param string $type    Note type.
		 */
		do_action( 'ppn_note_deleted', (int) $note_id, $type );

		return true;
	}

	/**
	 * Delete all notes of a post.
	 *
	 * @param  int $post_id Post ID.
	 * @return void
	 */
	public static function delete_post_notes( $post_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table( self::TYPE_POST ), array( 'postid' => (int) $post_id ) );
	}
}
