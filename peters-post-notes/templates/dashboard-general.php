<?php
/**
 * General Notes dashboard widget.
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type string $public_html    Rendered public notes list.
 *     @type string $personal_html  Rendered personal notes list.
 *     @type bool   $has_personal   Whether the user has personal notes.
 *     @type bool   $can_add_public Whether the user can add public notes.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ppn-general">
	<div class="ppn-general-list" data-personal="0">
		<?php echo $args['public_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in general-notes-list template. ?>
	</div>

	<div class="ppn-general-personal"<?php echo $args['has_personal'] ? '' : ' hidden'; ?>>
		<h4><?php esc_html_e( 'Personal notes:', 'peters-post-notes' ); ?></h4>
		<div class="ppn-general-list" data-personal="1">
			<?php echo $args['personal_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in general-notes-list template. ?>
		</div>
	</div>

	<div class="ppn-add-note ppn-add-general">
		<label for="ppn_post_generalnote"><?php esc_html_e( 'Add note:', 'peters-post-notes' ); ?></label>
		<textarea rows="3" id="ppn_post_generalnote" class="widefat ppn-general-text"></textarea>
		<label class="ppn-private-toggle">
			<?php if ( $args['can_add_public'] ) : ?>
				<input type="checkbox" class="ppn-add-private" />
			<?php else : ?>
				<?php // Users without the configured capability can only add private notes. ?>
				<input type="checkbox" class="ppn-add-private" checked disabled />
			<?php endif; ?>
			<?php esc_html_e( 'Private note', 'peters-post-notes' ); ?>
		</label>
		<p class="ppn-add-actions">
			<button type="button" class="button ppn-add-general-note"><?php esc_html_e( 'Add', 'peters-post-notes' ); ?></button>
		</p>
		<p class="ppn-error" role="alert" hidden></p>
	</div>
</div>
