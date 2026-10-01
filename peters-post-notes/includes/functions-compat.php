<?php
/**
 * Backward compatibility with 1.x for third-party code.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

// 1.x exposed table names and version as globals.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
global $ppn_db_notes, $ppn_db_generalnotes, $ppn_version;
$ppn_db_notes        = PPN_Notes_Repository::table( PPN_Notes_Repository::TYPE_POST );
$ppn_db_generalnotes = PPN_Notes_Repository::table( PPN_Notes_Repository::TYPE_GENERAL );
$ppn_version         = PPN_VERSION;
// phpcs:enable

if ( ! class_exists( 'ppnFunctionCollection' ) ) {

	// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital -- Legacy 1.x class name.
	/**
	 * Deprecated 1.x helper class, kept as a thin shim.
	 *
	 * @deprecated 2.0.0 Use PPN_Settings, PPN_Permissions, PPN_Content and PPN_Plugin.
	 */
	class ppnFunctionCollection {

		/**
		 * Get all settings or one setting.
		 *
		 * @param  string|false $setting Setting key.
		 * @return mixed
		 */
		public static function get_settings( $setting = false ) {
			if ( ! $setting ) {
				return PPN_Settings::get();
			}
			$value = PPN_Settings::get( $setting );
			return $value === null ? false : $value;
		}

		/**
		 * Whether a user has any of the given roles or capabilities.
		 *
		 * @param  WP_User  $user        User.
		 * @param  string[] $super_roles Roles.
		 * @param  string[] $super_caps  Capabilities.
		 * @return bool
		 */
		public static function is_super( $user, $super_roles, $super_caps ) {
			if ( array_intersect( (array) $super_roles, (array) $user->roles ) ) {
				return true;
			}
			foreach ( (array) $super_caps as $cap ) {
				if ( $user->has_cap( $cap ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Convert raw note text to the stored format.
		 *
		 * @param  string $text Raw text.
		 * @return string
		 */
		public static function note_scrub( $text ) {
			return PPN_Content::to_storage( $text );
		}

		/**
		 * Author display name.
		 *
		 * @param  int $author_id User ID.
		 * @return string
		 */
		public static function get_author_name( $author_id ) {
			return PPN_Plugin::author_name( (int) $author_id );
		}
	}
}
