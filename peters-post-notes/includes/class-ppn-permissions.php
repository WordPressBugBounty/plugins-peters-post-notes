<?php
/**
 * Permission checks for viewing, adding, editing and deleting notes.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Permissions
 */
class PPN_Permissions {

	/**
	 * Whether the user has any of the given roles or capabilities.
	 *
	 * @param  WP_User  $user  User.
	 * @param  string[] $roles Roles.
	 * @param  string[] $caps  Capabilities.
	 * @return bool
	 */
	private static function has_any( $user, $roles, $caps ) {
		if ( ! $user->exists() ) {
			return false;
		}
		if ( array_intersect( (array) $roles, (array) $user->roles ) ) {
			return true;
		}
		foreach ( (array) $caps as $cap ) {
			if ( $user->has_cap( $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the user can see all notes on the dashboard regardless of "show_which_notes".
	 *
	 * @param  WP_User|null $user User, defaults to current user.
	 * @return bool
	 */
	public static function is_super( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return self::has_any( $user, PPN_Settings::get( 'super_roles' ), PPN_Settings::get( 'super_caps' ) );
	}

	/**
	 * Whether the user can edit and delete other users' public notes.
	 *
	 * @param  WP_User|null $user User, defaults to current user.
	 * @return bool
	 */
	public static function is_supereditor( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return self::has_any( $user, PPN_Settings::get( 'superedit_roles' ), PPN_Settings::get( 'superedit_caps' ) );
	}

	/**
	 * Whether notes are enabled for the post and the current user can read/add notes on it.
	 *
	 * @param  int $post_id Post ID.
	 * @return bool
	 */
	public static function can_use_post_notes( $post_id ) {
		$post = get_post( $post_id );
		return $post
			&& in_array( $post->post_type, PPN_Settings::post_types(), true )
			&& current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Whether the current user can view the general notes widget and add notes.
	 *
	 * @return bool
	 */
	public static function can_use_general_notes() {
		return current_user_can( 'read' );
	}

	/**
	 * Whether the current user can add public (non-private) general notes.
	 *
	 * @return bool
	 */
	public static function can_add_public_general_note() {
		return current_user_can( (string) PPN_Settings::get( 'general_notes_required_capability' ) );
	}

	/**
	 * Whether the current user can edit or delete a note.
	 *
	 * Authors can always manage their own notes. Supereditors can manage other users' public notes.
	 * Private notes are only ever visible to, and manageable by, their author.
	 *
	 * @param  object $note Note row.
	 * @param  string $type Note type.
	 * @return bool
	 */
	public static function can_modify_note( $note, $type ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Post notes also require edit access to the parent post.
		if ( $type === PPN_Notes_Repository::TYPE_POST && ! self::can_use_post_notes( (int) $note->postid ) ) {
			return false;
		}
		if ( $type === PPN_Notes_Repository::TYPE_GENERAL && ! self::can_use_general_notes() ) {
			return false;
		}

		if ( (int) $note->author === $user_id ) {
			return true;
		}

		return ! (int) $note->personal && self::is_supereditor();
	}
}
