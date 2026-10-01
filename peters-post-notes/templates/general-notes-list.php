<?php
/**
 * Paged list of general notes (public or personal).
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type array<int, array<string, mixed>> $notes       Note view models.
 *     @type bool                             $personal    Personal list.
 *     @type int                              $page        Zero-based current page.
 *     @type int                              $total_pages Total pages.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_notes = $args['notes'];
$ppn_page  = (int) $args['page'];
$ppn_pages = (int) $args['total_pages'];
?>
<?php if ( ! $ppn_notes && ! $args['personal'] ) : ?>
	<p class="ppn-empty"><?php esc_html_e( 'No general notes.', 'peters-post-notes' ); ?></p>
<?php endif; ?>

<ul class="ppn-notes-list">
	<?php
	foreach ( $ppn_notes as $ppn_note ) {
		PPN_Plugin::template(
			'note-item',
			array(
				'note'        => $ppn_note,
				'show_author' => ! $args['personal'],
			)
		);
	}
	?>
</ul>

<?php if ( $ppn_pages > 1 ) : ?>
	<p class="ppn-pager">
		<?php if ( $ppn_page + 1 < $ppn_pages ) : ?>
			<button type="button" class="button-link ppn-page" data-page="<?php echo esc_attr( (string) ( $ppn_page + 1 ) ); ?>">&laquo; <?php esc_html_e( 'Older', 'peters-post-notes' ); ?></button>
		<?php endif; ?>
		<?php if ( $ppn_page > 0 ) : ?>
			<button type="button" class="button-link ppn-page" data-page="<?php echo esc_attr( (string) ( $ppn_page - 1 ) ); ?>"><?php esc_html_e( 'Newer', 'peters-post-notes' ); ?> &raquo;</button>
		<?php endif; ?>
	</p>
<?php endif; ?>
