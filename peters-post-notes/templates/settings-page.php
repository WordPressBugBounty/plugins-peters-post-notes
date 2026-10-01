<?php
/**
 * Settings page (Settings > Peter's Post Notes).
 *
 * @package PetersPostNotes
 *
 * @var array<string, mixed> $args {
 *     @type array<string, mixed>  $settings   Current settings.
 *     @type string[]              $roles      Role slugs.
 *     @type string[]              $caps       Capability names.
 *     @type array<string, string> $post_types Post type slug => label.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ppn_s    = $args['settings'];
$ppn_name = PPN_Settings::OPTION;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Peter\'s Post Notes settings', 'peters-post-notes' ); ?></h1>

	<form action="options.php" method="post">
		<?php settings_fields( PPN_Settings::GROUP ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ppn_show_which_notes"><?php esc_html_e( 'Which types of notes to show on the Dashboard', 'peters-post-notes' ); ?></label></th>
				<td>
					<select id="ppn_show_which_notes" name="<?php echo esc_attr( $ppn_name ); ?>[show_which_notes]">
						<option value="related" <?php selected( $ppn_s['show_which_notes'], 'related' ); ?>><?php esc_html_e( "Relevant notes including the current user's notes", 'peters-post-notes' ); ?></option>
						<option value="others" <?php selected( $ppn_s['show_which_notes'], 'others' ); ?>><?php esc_html_e( "Relevant notes excluding the current user's notes", 'peters-post-notes' ); ?></option>
						<option value="all" <?php selected( $ppn_s['show_which_notes'], 'all' ); ?>><?php esc_html_e( 'All notes', 'peters-post-notes' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ppn_num_notes_limit"><?php esc_html_e( 'How many recent notes to display on the Dashboard. 0 = unlimited', 'peters-post-notes' ); ?></label></th>
				<td>
					<input id="ppn_num_notes_limit" name="<?php echo esc_attr( $ppn_name ); ?>[num_notes_limit]" type="number" min="0" max="500" class="small-text" value="<?php echo esc_attr( (string) absint( $ppn_s['num_notes_limit'] ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show "Add note" text area for post within publish panel', 'peters-post-notes' ); ?></th>
				<td>
					<label>
						<input name="<?php echo esc_attr( $ppn_name ); ?>[add_post_note_in_publish_panel]" type="checkbox" value="1" <?php checked( (bool) $ppn_s['add_post_note_in_publish_panel'] ); ?> />
						<?php esc_html_e( 'Yes', 'peters-post-notes' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Classic editor only.', 'peters-post-notes' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles that are allowed to view all notes (used when the global setting is to only show relevant notes)', 'peters-post-notes' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $args['roles'] as $ppn_role ) : ?>
							<label><input name="<?php echo esc_attr( $ppn_name ); ?>[super_roles][]" type="checkbox" value="<?php echo esc_attr( $ppn_role ); ?>" <?php checked( in_array( $ppn_role, (array) $ppn_s['super_roles'], true ) ); ?> /> <?php echo esc_html( $ppn_role ); ?></label><br />
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ppn_super_caps"><?php esc_html_e( 'Capabilities that are allowed to view all notes (used when the global setting is to only show relevant notes)', 'peters-post-notes' ); ?></label></th>
				<td>
					<select id="ppn_super_caps" name="<?php echo esc_attr( $ppn_name ); ?>[super_caps][]" multiple="multiple" size="10">
						<?php foreach ( $args['caps'] as $ppn_cap ) : ?>
							<option value="<?php echo esc_attr( $ppn_cap ); ?>" <?php selected( in_array( $ppn_cap, (array) $ppn_s['super_caps'], true ) ); ?>><?php echo esc_html( $ppn_cap ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles that are allowed to edit all notes (they must also be allowed to view the notes)', 'peters-post-notes' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $args['roles'] as $ppn_role ) : ?>
							<label><input name="<?php echo esc_attr( $ppn_name ); ?>[superedit_roles][]" type="checkbox" value="<?php echo esc_attr( $ppn_role ); ?>" <?php checked( in_array( $ppn_role, (array) $ppn_s['superedit_roles'], true ) ); ?> /> <?php echo esc_html( $ppn_role ); ?></label><br />
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ppn_superedit_caps"><?php esc_html_e( 'Capabilities that are allowed to edit all notes (they must also be allowed to view the notes)', 'peters-post-notes' ); ?></label></th>
				<td>
					<select id="ppn_superedit_caps" name="<?php echo esc_attr( $ppn_name ); ?>[superedit_caps][]" multiple="multiple" size="10">
						<?php foreach ( $args['caps'] as $ppn_cap ) : ?>
							<option value="<?php echo esc_attr( $ppn_cap ); ?>" <?php selected( in_array( $ppn_cap, (array) $ppn_s['superedit_caps'], true ) ); ?>><?php echo esc_html( $ppn_cap ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'List of post types on which post notes should be enabled', 'peters-post-notes' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $args['post_types'] as $ppn_type => $ppn_label ) : ?>
							<label><input name="<?php echo esc_attr( $ppn_name ); ?>[post_types][]" type="checkbox" value="<?php echo esc_attr( $ppn_type ); ?>" <?php checked( in_array( $ppn_type, (array) $ppn_s['post_types'], true ) ); ?> /> <?php echo esc_html( $ppn_label ); ?> <code><?php echo esc_html( $ppn_type ); ?></code></label><br />
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow the same set of HTML tags in notes as are allowed in WordPress comments', 'peters-post-notes' ); ?></th>
				<td>
					<label>
						<input name="<?php echo esc_attr( $ppn_name ); ?>[allow_html]" type="checkbox" value="1" <?php checked( (bool) $ppn_s['allow_html'] ); ?> />
						<?php esc_html_e( 'Yes', 'peters-post-notes' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show "Latest note" column in the "Manage posts" page', 'peters-post-notes' ); ?></th>
				<td>
					<label>
						<input name="<?php echo esc_attr( $ppn_name ); ?>[show_latest_notes_column]" type="checkbox" value="1" <?php checked( (bool) $ppn_s['show_latest_notes_column'] ); ?> />
						<?php esc_html_e( 'Yes', 'peters-post-notes' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ppn_general_cap"><?php esc_html_e( 'Capability required to add a general note. (Otherwise users can only add a private note.)', 'peters-post-notes' ); ?></label></th>
				<td>
					<select id="ppn_general_cap" name="<?php echo esc_attr( $ppn_name ); ?>[general_notes_required_capability]">
						<?php foreach ( $args['caps'] as $ppn_cap ) : ?>
							<option value="<?php echo esc_attr( $ppn_cap ); ?>" <?php selected( $ppn_s['general_notes_required_capability'], $ppn_cap ); ?>><?php echo esc_html( $ppn_cap ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php
			/**
			 * Fires after the built-in settings rows, to add rows from an add-on.
			 *
			 * @param array<string, mixed> $ppn_s Current settings.
			 */
			do_action( 'ppn_settings_fields', $ppn_s );
			?>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
