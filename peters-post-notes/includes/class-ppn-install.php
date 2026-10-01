<?php
/**
 * Install and versioned upgrades.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come only from PPN_Notes_Repository::table() (fixed names); all values are prepared.

/**
 * Class PPN_Install
 */
class PPN_Install {

	/**
	 * Option holding the installed version.
	 */
	const VERSION_OPTION = 'ppn_version';

	/**
	 * Option used as an upgrade lock.
	 */
	const LOCK_OPTION = 'ppn_upgrade_lock';

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_upgrade();
	}

	/**
	 * Create or upgrade the schema when the stored version differs from the code version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::VERSION_OPTION, '' );
		if ( $installed === PPN_VERSION ) {
			return;
		}

		// add_option() is atomic (unique option_name), so only one request runs the upgrade.
		// The unslash migration is not idempotent, so a concurrent second run would corrupt data.
		if ( ! add_option( self::LOCK_OPTION, time(), '', false ) ) {
			if ( (int) get_option( self::LOCK_OPTION ) > time() - 10 * MINUTE_IN_SECONDS ) {
				return;
			}
			// Stale lock from a crashed run: take it over.
			update_option( self::LOCK_OPTION, time(), false );
		}

		// Re-read after locking: another request may have finished the upgrade meanwhile.
		wp_cache_delete( self::VERSION_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$installed = (string) get_option( self::VERSION_OPTION, '' );

		if ( $installed !== PPN_VERSION ) {
			self::create_tables();

			// 1.x rows were saved from slashed $_POST data and unslashed on output. Store them clean from 2.0.0 on.
			if ( $installed !== '' && version_compare( $installed, '2.0.0', '<' ) ) {
				self::drop_legacy_unique_keys();
				self::unslash_legacy_rows();
			}

			update_option( self::VERSION_OPTION, PPN_VERSION, true );
		}

		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Create tables, or add missing columns and indexes to existing ones (dbDelta).
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();
		$notes   = PPN_Notes_Repository::table( PPN_Notes_Repository::TYPE_POST );
		$general = PPN_Notes_Repository::table( PPN_Notes_Repository::TYPE_GENERAL );

		// dbDelta formatting rules: two spaces after PRIMARY KEY, one field per line.
		dbDelta(
			"CREATE TABLE {$notes} (
  noteid bigint(20) NOT NULL AUTO_INCREMENT,
  postid bigint(20) NOT NULL,
  notecontent text NOT NULL,
  author bigint(20) NOT NULL,
  notetime datetime NOT NULL,
  personal tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (noteid),
  KEY postid (postid,notetime),
  KEY author (author)
) {$collate};
CREATE TABLE {$general} (
  noteid bigint(20) NOT NULL AUTO_INCREMENT,
  notecontent text NOT NULL,
  author bigint(20) NOT NULL,
  notetime datetime NOT NULL,
  personal tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (noteid),
  KEY personal (personal,notetime),
  KEY author (author)
) {$collate};"
		);
	}

	/**
	 * 1.x created "UNIQUE KEY noteid"; it is redundant once the primary key exists.
	 *
	 * @return void
	 */
	private static function drop_legacy_unique_keys() {
		global $wpdb;

		foreach ( array( PPN_Notes_Repository::TYPE_POST, PPN_Notes_Repository::TYPE_GENERAL ) as $type ) {
			$table = PPN_Notes_Repository::table( $type );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_primary = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_legacy = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'noteid'" );

			if ( $has_primary && $has_legacy ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table} DROP INDEX noteid" );
			}
		}
	}

	/**
	 * Unslash 1.x rows. Only rows containing a backslash are touched.
	 *
	 * @return void
	 */
	private static function unslash_legacy_rows() {
		global $wpdb;

		foreach ( array( PPN_Notes_Repository::TYPE_POST, PPN_Notes_Repository::TYPE_GENERAL ) as $type ) {
			$table = PPN_Notes_Repository::table( $type );
			$last  = 0;

			// Batched by ID so large tables don't exhaust memory.
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT noteid, notecontent FROM {$table} WHERE noteid > %d AND notecontent LIKE %s ORDER BY noteid LIMIT 500", $last, '%\\\\%' ) );

				foreach ( $rows as $row ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update( $table, array( 'notecontent' => stripslashes( $row->notecontent ) ), array( 'noteid' => (int) $row->noteid ) );
					$last = (int) $row->noteid;
				}
				$batch = count( $rows );
			} while ( $batch === 500 );
		}
	}
}
