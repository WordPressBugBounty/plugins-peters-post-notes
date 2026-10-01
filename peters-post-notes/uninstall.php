<?php
/**
 * Uninstall: remove tables and options (on every site in a network).
 *
 * @package PetersPostNotes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Drop plugin data for the current site.
 *
 * @return void
 */
function ppn_uninstall_site() {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}collabnotes" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}generalnotes" );
	// phpcs:enable

	delete_option( 'ppn_settings' );
	delete_option( 'ppn_version' );
	delete_option( 'ppn_upgrade_lock' );
}

if ( is_multisite() ) {
	$ppn_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $ppn_site_ids as $ppn_site_id ) {
		switch_to_blog( (int) $ppn_site_id );
		ppn_uninstall_site();
		restore_current_blog();
	}
} else {
	ppn_uninstall_site();
}
