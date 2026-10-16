<?php
/**
 * Plugin Name:       WebSage Headless Core
 * Plugin URI:        https://websagecreative.com/
 * Description:       Turns WordPress into the headless CMS for a WebSage Headless Next.js frontend.
 * Version:           0.1.0
 * Requires at least: 7.1.3
 * Requires PHP:      8.1
 * Author:            WebSage Creative
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       websage-headless
 * Domain Path:       /languages
 *
 * This file must stay parseable by old PHP versions so unsupported hosts get
 * a readable notice instead of a fatal error. Modern syntax lives in src/.
 *
 * @package WebSage\HeadlessCore
 */

defined( 'ABSPATH' ) || exit;

define( 'WSH_PLUGIN_FILE', __FILE__ );
define( 'WSH_PLUGIN_DIR', __DIR__ );
define( 'WSH_VERSION', '0.1.0' );

/**
 * Shows a bootstrap failure to administrators and keeps the plugin inert.
 *
 * @param string $message Plain-text message.
 * @return void
 */
function wsh_bootstrap_failure( $message ) {
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
			}
		}
	);
	register_activation_hook(
		__FILE__,
		static function () use ( $message ) {
			wp_die( esc_html( $message ), esc_html__( 'Plugin activation failed', 'websage-headless' ), array( 'back_link' => true ) );
		}
	);
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	/* translators: %s: running PHP version. */
	wsh_bootstrap_failure( sprintf( __( 'WebSage Headless Core requires PHP 8.1 or newer. This server runs PHP %s.', 'websage-headless' ), PHP_VERSION ) );
	return;
}

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) || ! is_readable( __DIR__ . '/vendor/cmb2/cmb2/init.php' ) ) {
	wsh_bootstrap_failure( __( 'WebSage Headless Core is missing its bundled dependencies. Reinstall the plugin from the distributed zip file, or run "composer install --no-dev" in the plugin directory.', 'websage-headless' ) );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
// CMB2's own bootstrap negotiates between bundled copies; the newest one loads.
require_once __DIR__ . '/vendor/cmb2/cmb2/init.php';

register_activation_hook( __FILE__, array( \WebSage\HeadlessCore\Plugin::class, 'activate' ) );
\WebSage\HeadlessCore\Plugin::register();
