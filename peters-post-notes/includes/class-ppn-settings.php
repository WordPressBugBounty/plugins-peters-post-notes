<?php
/**
 * Plugin settings: defaults, access, sanitization and the settings page.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Settings
 */
class PPN_Settings {

	/**
	 * Option name (unchanged since 1.5.0).
	 */
	const OPTION = 'ppn_settings';

	/**
	 * Settings API group.
	 */
	const GROUP = 'ppn_settings_group';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'peters-post-notes';

	/**
	 * Allowed values for "show_which_notes".
	 */
	const SHOW_WHICH_NOTES = array( 'related', 'others', 'all' );

	/**
	 * Request cache of the merged settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			// 'related': notes on posts the user wrote or noted on. 'others': same, excluding the user's own notes. 'all': every note.
			'show_which_notes'                  => 'related',
			// Notes per dashboard list. 0 = unlimited.
			'num_notes_limit'                   => 5,
			// Classic editor only: move the "Add note" box into the Publish panel.
			'add_post_note_in_publish_panel'    => true,
			// Roles/capabilities that can view all notes when "show_which_notes" is not "all".
			'super_roles'                       => array(),
			'super_caps'                        => array(),
			// Roles/capabilities that can edit and delete other users' public notes.
			'superedit_roles'                   => array(),
			'superedit_caps'                    => array(),
			// Post types with notes enabled.
			'post_types'                        => array( 'post', 'page' ),
			// Allow the same HTML tags as WordPress comments.
			'allow_html'                        => false,
			// Show the "Latest note" column in post lists.
			'show_latest_notes_column'          => true,
			// Capability needed to add a public general note on the dashboard.
			'general_notes_required_capability' => 'edit_posts',
		);
	}

	/**
	 * Get all settings or a single setting.
	 *
	 * @param  string|null $key Setting key, or null for all settings.
	 * @return mixed All settings array, the setting value, or null for an unknown key.
	 */
	public static function get( $key = null ) {
		if ( self::$cache === null ) {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();

			// Only keep known keys so stale options from old versions are ignored.
			self::$cache = array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
		}

		if ( $key === null ) {
			return self::$cache;
		}

		return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Clear the request cache (hooked to option updates).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Post types with notes enabled.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return array_values( array_filter( (array) self::get( 'post_types' ), 'post_type_exists' ) );
	}

	/**
	 * Register hooks for the settings page.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PPN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Register the setting with the Settings API.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Peter\'s Post Notes', 'peters-post-notes' ),
			__( 'Peter\'s Post Notes', 'peters-post-notes' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param  string[] $links Existing action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'peters-post-notes' ) . '</a>' );
		return $links;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		PPN_Plugin::template(
			'settings-page',
			array(
				'settings'   => self::get(),
				'roles'      => self::role_names(),
				'caps'       => self::capability_names(),
				'post_types' => self::available_post_types(),
			)
		);
	}

	/**
	 * Sanitize submitted settings. Every value is validated against its allowed set.
	 *
	 * @param  mixed $input Raw submitted value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$roles    = self::role_names();
		$caps     = self::capability_names();
		$types    = array_keys( self::available_post_types() );

		$clean = array(
			'show_which_notes'                  => in_array( $input['show_which_notes'] ?? '', self::SHOW_WHICH_NOTES, true ) ? $input['show_which_notes'] : $defaults['show_which_notes'],
			'num_notes_limit'                   => min( absint( $input['num_notes_limit'] ?? $defaults['num_notes_limit'] ), 500 ),
			'add_post_note_in_publish_panel'    => ! empty( $input['add_post_note_in_publish_panel'] ),
			'super_roles'                       => self::sanitize_list( $input['super_roles'] ?? array(), $roles ),
			'super_caps'                        => self::sanitize_list( $input['super_caps'] ?? array(), $caps ),
			'superedit_roles'                   => self::sanitize_list( $input['superedit_roles'] ?? array(), $roles ),
			'superedit_caps'                    => self::sanitize_list( $input['superedit_caps'] ?? array(), $caps ),
			'post_types'                        => self::sanitize_list( $input['post_types'] ?? array(), $types ),
			'allow_html'                        => ! empty( $input['allow_html'] ),
			'show_latest_notes_column'          => ! empty( $input['show_latest_notes_column'] ),
			'general_notes_required_capability' => in_array( $input['general_notes_required_capability'] ?? '', $caps, true ) ? $input['general_notes_required_capability'] : $defaults['general_notes_required_capability'],
		);

		/**
		 * Filter sanitized settings, e.g. to add settings from an add-on.
		 *
		 * @param array<string, mixed> $clean Sanitized settings.
		 * @param array<string, mixed> $input Raw input.
		 */
		return apply_filters( 'ppn_sanitize_settings', $clean, $input );
	}

	/**
	 * Keep only allowed string values from a submitted list.
	 *
	 * @param  mixed    $values  Submitted values.
	 * @param  string[] $allowed Allowed values.
	 * @return string[]
	 */
	private static function sanitize_list( $values, $allowed ) {
		if ( ! is_array( $values ) ) {
			return array();
		}
		// Not sanitize_key(): third-party capabilities may contain uppercase letters or spaces. The allow-list below is the real validation.
		$values = array_map( 'sanitize_text_field', array_filter( $values, 'is_string' ) );
		return array_values( array_intersect( $values, $allowed ) );
	}

	/**
	 * All role slugs, sorted.
	 *
	 * @return string[]
	 */
	public static function role_names() {
		$roles = array_keys( wp_roles()->roles );
		sort( $roles );
		return $roles;
	}

	/**
	 * All capabilities assigned to any role, sorted.
	 *
	 * @return string[]
	 */
	public static function capability_names() {
		$caps = array();
		foreach ( wp_roles()->roles as $role ) {
			$caps = array_merge( $caps, array_keys( array_filter( (array) $role['capabilities'] ) ) );
		}
		$caps = array_values( array_unique( $caps ) );
		sort( $caps );
		return $caps;
	}

	/**
	 * Post types that can have notes (those with an admin UI), slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function available_post_types() {
		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ), true ) ) {
				continue;
			}
			$types[ $type->name ] = $type->labels->singular_name;
		}
		return $types;
	}
}
