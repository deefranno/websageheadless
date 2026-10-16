<?php
/**
 * Boots a real WordPress test install with WebSage Headless Core loaded.
 *
 * WSH_CMB2_ORDER selects whether the older test-only CMB2 copy loads before
 * ("legacy-first", default) or after ("bundled-first") this plugin, so the
 * suite runs once per load order.
 *
 * @package WebSage\HeadlessCore
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( false === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

$wsh_tests_dir = (string) getenv( 'WP_PHPUNIT__DIR' );
require_once $wsh_tests_dir . '/includes/functions.php';

$wsh_order = getenv( 'WSH_CMB2_ORDER' );
$wsh_order = false === $wsh_order || '' === $wsh_order ? 'legacy-first' : $wsh_order;
if ( ! in_array( $wsh_order, array( 'legacy-first', 'bundled-first' ), true ) ) {
	fwrite( STDERR, "WSH_CMB2_ORDER must be legacy-first or bundled-first.\n" );
	exit( 1 );
}
define( 'WSH_TEST_CMB2_ORDER', $wsh_order );

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wsh_order ): void {
		$legacy = dirname( __DIR__ ) . '/vendor/websage-test/cmb2-legacy/init.php';
		if ( 'legacy-first' === $wsh_order ) {
			require_once $legacy;
		}
		require_once dirname( __DIR__ ) . '/websage-headless-core.php';
		if ( 'bundled-first' === $wsh_order ) {
			require_once $legacy;
		}
	}
);

require $wsh_tests_dir . '/includes/bootstrap.php';
