<?php
/**
 * Single note list item (meta box and General Notes widget).
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type array<string, mixed> $note        View model from PPN_Plugin::note_view().
 *     @type bool                 $show_author Show the author name.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_note        = $args['note'];
$ppn_show_author = ! empty( $args['show_author'] );
$ppn_is_post     = $ppn_note['type'] === PPN_Notes_Repository::TYPE_POST;
?>
<li class="ppn-note<?php echo $ppn_note['personal'] ? ' ppn-note--private' : ''; ?><?php echo $ppn_note['is_own'] ? ' ppn-note--own' : ''; ?>" data-note-id="<?php echo esc_attr( $ppn_note['id'] ); ?>" data-note-type="<?php echo esc_attr( $ppn_note['type'] ); ?>">
	<div class="ppn-note-view">
		<p class="ppn-note-meta">
			<?php if ( $ppn_note['personal'] && $ppn_is_post ) : ?>
				<span class="ppn-private-badge"><?php esc_html_e( '[private]', 'peters-post-notes' ); ?></span>
			<?php endif; ?>
			<?php if ( $ppn_show_author ) : ?>
				<strong class="ppn-note-author"><?php echo esc_html( $ppn_note['author_name'] ); ?></strong>
			<?php endif; ?>
			<time class="ppn-note-date" datetime="<?php echo esc_attr( $ppn_note['date_iso'] ); ?>"><?php echo esc_html( $ppn_note['date'] ); ?></time>
		</p>
		<div class="ppn-note-content"><?php echo wp_kses( $ppn_note['content_html'], PPN_Content::allowed_html() ); ?></div>
		<?php if ( $ppn_note['can_modify'] ) : ?>
			<p class="ppn-note-actions">
				<button type="button" class="button-link ppn-edit"><?php esc_html_e( 'Edit', 'peters-post-notes' ); ?></button>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $ppn_note['can_modify'] ) : ?>
		<div class="ppn-note-form" hidden>
			<textarea class="ppn-note-text widefat" rows="3" aria-label="<?php esc_attr_e( 'Edit note', 'peters-post-notes' ); ?>"><?php echo esc_textarea( $ppn_note['edit_value'] ); ?></textarea>
			<?php if ( $ppn_is_post ) : ?>
				<label class="ppn-private-toggle">
					<input type="checkbox" class="ppn-note-private" <?php checked( $ppn_note['personal'] ); ?> />
					<?php esc_html_e( 'Private note', 'peters-post-notes' ); ?>
				</label>
			<?php endif; ?>
			<p class="ppn-note-form-actions">
				<button type="button" class="button button-primary button-small ppn-save"><?php esc_html_e( 'Save', 'peters-post-notes' ); ?></button>
				<button type="button" class="button-link ppn-cancel"><?php esc_html_e( 'Cancel', 'peters-post-notes' ); ?></button>
				<button type="button" class="button-link button-link-delete ppn-delete"><?php esc_html_e( 'Delete', 'peters-post-notes' ); ?></button>
			</p>
		</div>
	<?php endif; ?>

	<p class="ppn-error" role="alert" hidden></p>
</li>
