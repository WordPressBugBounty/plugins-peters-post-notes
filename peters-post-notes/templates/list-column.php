<?php
/**
 * "Latest note" list table cell.
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type array<string, mixed> $note Note view model.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_note = $args['note'];
?>
<em>[<?php echo esc_html( $ppn_note['date'] ); ?>, <?php echo esc_html( $ppn_note['author_name'] ); ?>]</em><br />
<?php echo wp_kses( $ppn_note['content_html'], PPN_Content::allowed_html() ); ?>
