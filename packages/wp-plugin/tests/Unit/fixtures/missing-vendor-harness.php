<?php
/**
 * Minimal WordPress function doubles for loading the entry file in isolation.
 *
 * Usage: php missing-vendor-harness.php /path/to/websage-headless-core.php
 *
 * @package WebSage\HeadlessCore
 */

define( 'ABSPATH', '/' );

$GLOBALS['wsh_actions']    = array();
$GLOBALS['wsh_activation'] = null;

function add_action( $hook, $callback ) {
	$GLOBALS['wsh_actions'][ $hook ][] = $callback;
}
function register_activation_hook( $file, $callback ) {
	$GLOBALS['wsh_activation'] = $callback;
}
function current_user_can( $capability ) {
	return 'activate_plugins' === $capability;
}
function __( $text ) {
	return $text;
}
function esc_html__( $text ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function wp_die( $message ) {
	echo 'ACTIVATION: ' . $message . "\n";
}

require $argv[1];

foreach ( $GLOBALS['wsh_actions']['admin_notices'] ?? array() as $callback ) {
	ob_start();
	$callback();
	echo 'NOTICE: ' . ob_get_clean() . "\n";
}
if ( null !== $GLOBALS['wsh_activation'] ) {
	( $GLOBALS['wsh_activation'] )();
}
echo 'PLUGIN CLASS LOADED: ' . ( class_exists( 'WebSage\\HeadlessCore\\Plugin', false ) ? 'yes' : 'no' ) . "\n";
