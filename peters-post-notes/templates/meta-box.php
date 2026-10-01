<?php
/**
 * Notes meta box.
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type WP_Post                          $post  Post being edited.
 *     @type array<int, array<string, mixed>> $notes Note view models.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_post  = $args['post'];
$ppn_notes = $args['notes'];
?>
<div class="ppn-meta-box" data-post-id="<?php echo esc_attr( $ppn_post->ID ); ?>">
	<p class="ppn-empty"<?php echo $ppn_notes ? ' hidden' : ''; ?>><?php esc_html_e( 'No notes for this post.', 'peters-post-notes' ); ?></p>

	<ul class="ppn-notes-list">
		<?php
		foreach ( $ppn_notes as $ppn_note ) {
			PPN_Plugin::template(
				'note-item',
				array(
					'note'        => $ppn_note,
					'show_author' => true,
				)
			);
		}
		?>
	</ul>

	<?php
	/**
	 * Fires after the notes list in the meta box.
	 *
	 * @param WP_Post $ppn_post Post being edited.
	 */
	do_action( 'ppn_meta_box_after_notes', $ppn_post );
	?>

	<div id="ppn-add-post-note" class="ppn-add-note">
		<label for="ppn_post_note"><?php esc_html_e( 'Add note:', 'peters-post-notes' ); ?></label>
		<?php // Field names ppn_post_note / ppn_private are read by Peter's Collaboration E-mails. Keep them. ?>
		<textarea rows="3" name="ppn_post_note" id="ppn_post_note" class="widefat"></textarea>
		<label class="ppn-private-toggle">
			<input type="checkbox" name="ppn_private" value="1" class="ppn-add-private" />
			<?php esc_html_e( 'Private note', 'peters-post-notes' ); ?>
		</label>
		<p class="ppn-add-actions">
			<button type="button" class="button ppn-add"><?php esc_html_e( 'Add note', 'peters-post-notes' ); ?></button>
		</p>
		<p class="ppn-error" role="alert" hidden></p>
		<?php wp_nonce_field( PPN_Meta_Box::NONCE_ACTION, PPN_Meta_Box::NONCE_FIELD, false ); ?>
	</div>
</div>
