<?php
/**
 * Note content handling: input to storage, storage to output, storage to edit value.
 *
 * Storage format (kept from 1.x for compatibility): sanitized HTML with newlines as "<br />".
 * Plain-text mode stores HTML-escaped text; HTML mode stores kses-filtered HTML.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PPN_Content
 */
class PPN_Content {

	/**
	 * Convert raw (unslashed) user input to the stored format.
	 *
	 * @param  string $raw  Unslashed user input.
	 * @param  string $type Note type (selects the table whose charset must hold the text).
	 * @return string
	 */
	public static function to_storage( $raw, $type = PPN_Notes_Repository::TYPE_POST ) {
		$text = wp_check_invalid_utf8( str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $raw ) ), true );

		if ( PPN_Settings::get( 'allow_html' ) ) {
			$text = wp_kses( $text, self::allowed_html() );
		} else {
			$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}

		$text = self::fit_table_charset( $text, PPN_Notes_Repository::table( $type ) );

		return str_replace( "\n", '<br />', $text );
	}

	/**
	 * Tables created by 1.x have no explicit charset and are often latin1. Store non-ASCII
	 * characters as numeric entities there so nothing is lost; utf8/utf8mb4 tables store raw text.
	 *
	 * @param  string $text  Escaped or kses-filtered text.
	 * @param  string $table Table name.
	 * @return string
	 */
	private static function fit_table_charset( $text, $table ) {
		global $wpdb;

		$charset = $wpdb->get_col_charset( $table, 'notecontent' );
		if ( ! is_string( $charset ) || strpos( $charset, 'utf8' ) === 0 ) {
			return $text;
		}

		return (string) preg_replace_callback(
			'/[^\x00-\x7F]/u',
			static function ( $chars ) {
				return '&#' . self::codepoint( $chars[0] ) . ';';
			},
			$text
		);
	}

	/**
	 * Unicode code point of a single UTF-8 character (no mbstring dependency).
	 *
	 * @param  string $char One UTF-8 encoded character.
	 * @return int
	 */
	private static function codepoint( $char ) {
		$bytes = array_values( (array) unpack( 'C*', $char ) );

		switch ( count( $bytes ) ) {
			case 2:
				return ( ( $bytes[0] & 0x1F ) << 6 ) | ( $bytes[1] & 0x3F );
			case 3:
				return ( ( $bytes[0] & 0x0F ) << 12 ) | ( ( $bytes[1] & 0x3F ) << 6 ) | ( $bytes[2] & 0x3F );
			case 4:
				return ( ( $bytes[0] & 0x07 ) << 18 ) | ( ( $bytes[1] & 0x3F ) << 12 ) | ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
			default:
				return (int) $bytes[0];
		}
	}

	/**
	 * Render stored content for output. Always filtered, so legacy or tampered rows are safe.
	 *
	 * @param  string $stored Stored note content.
	 * @return string
	 */
	public static function render( $stored ) {
		return wp_kses( (string) $stored, self::allowed_html() );
	}

	/**
	 * Convert stored content back to editable text for a textarea (escape with esc_textarea()).
	 *
	 * @param  string $stored Stored note content.
	 * @return string
	 */
	public static function to_edit_value( $stored ) {
		$text = preg_replace( '#<br\s*/?>#i', "\n", (string) $stored );

		// Plain-text notes are stored escaped; decode so the user edits the original text.
		if ( ! PPN_Settings::get( 'allow_html' ) ) {
			$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		}

		return $text;
	}

	/**
	 * Allowed HTML in notes: comment tags plus line breaks.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html() {
		global $allowedtags;

		$allowed       = is_array( $allowedtags ) ? $allowedtags : array();
		$allowed['br'] = array();

		/**
		 * Filter HTML tags allowed in rendered notes.
		 *
		 * @param array<string, array<string, bool>> $allowed Allowed tags.
		 */
		return apply_filters( 'ppn_note_allowed_html', $allowed );
	}
}
