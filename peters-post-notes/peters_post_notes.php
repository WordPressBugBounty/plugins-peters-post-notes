<?php
/**
 * Plugin Name:       Peter's Post Notes
 * Plugin URI:        https://wordpress.org/plugins/peters-post-notes/
 * Description:       Private and shared admin notes on posts, pages and custom post types, plus team and personal notes on the dashboard.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Giannis Kipouros
 * Author URI:        https://profiles.wordpress.org/thewpgarden/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       peters-post-notes
 * Domain Path:       /languages
 *
 * Originally created by Peter Keung.
 *
 * @package PetersPostNotes
 */

defined( 'ABSPATH' ) || exit;

define( 'PPN_VERSION', '2.0.0' );
define( 'PPN_FILE', __FILE__ );
define( 'PPN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PPN_URL', plugin_dir_url( __FILE__ ) );

require_once PPN_PATH . 'includes/class-ppn-settings.php';
require_once PPN_PATH . 'includes/class-ppn-content.php';
require_once PPN_PATH . 'includes/class-ppn-notes-repository.php';
require_once PPN_PATH . 'includes/class-ppn-permissions.php';
require_once PPN_PATH . 'includes/class-ppn-install.php';
require_once PPN_PATH . 'includes/class-ppn-rest-controller.php';
require_once PPN_PATH . 'includes/class-ppn-plugin.php';
require_once PPN_PATH . 'includes/functions-compat.php';

// Activation must be registered outside any is_admin() guard so WP-CLI activation works too.
register_activation_hook( __FILE__, array( 'PPN_Install', 'activate' ) );

PPN_Plugin::get_instance();
