<?php
/**
 * Collaboration Notes dashboard widget.
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type array<int, array<string, mixed>> $items Items from PPN_Dashboard::collaboration_items().
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_items = $args['items'];
?>
<?php if ( ! $ppn_items ) : ?>
	<p><?php esc_html_e( 'No relevant notes.', 'peters-post-notes' ); ?></p>
<?php else : ?>
	<ul class="ppn-collab-list">
		<?php foreach ( $ppn_items as $ppn_item ) : ?>
			<li class="ppn-note<?php echo $ppn_item['note']['is_own'] ? ' ppn-note--own' : ''; ?>">
				<p class="ppn-note-meta">
					<?php if ( $ppn_item['note']['personal'] ) : ?>
						<span class="ppn-private-badge"><?php esc_html_e( '[private]', 'peters-post-notes' ); ?></span>
					<?php endif; ?>
					<strong><?php echo esc_html( $ppn_item['note']['author_name'] ); ?></strong>
				</p>
				<div class="ppn-note-content"><?php echo wp_kses( $ppn_item['note']['content_html'], PPN_Content::allowed_html() ); ?></div>
				<p class="ppn-note-meta">
					<time datetime="<?php echo esc_attr( $ppn_item['note']['date_iso'] ); ?>"><?php echo esc_html( $ppn_item['note']['date'] ); ?></time>
					<?php esc_html_e( 'on', 'peters-post-notes' ); ?>
					<a href="<?php echo esc_url( $ppn_item['post_link'] ); ?>"><?php echo esc_html( $ppn_item['post_title'] ); ?></a>
					(<?php echo esc_html( $ppn_item['post_status'] ); ?>)
				</p>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
