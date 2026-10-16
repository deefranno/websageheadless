<?php
/**
 * WordPress test-suite configuration, driven by environment variables.
 *
 * Defaults match the DDEV test database created by `ddev websage-test`.
 * The suite drops and recreates every table in this database: never point
 * it at a database holding real content.
 *
 * @package WebSage\HeadlessCore
 */

$wsh_env = static function ( string $name, string $fallback ): string {
	$value = getenv( $name );
	return false === $value || '' === $value ? $fallback : $value;
};

define( 'ABSPATH', rtrim( $wsh_env( 'WP_CORE_DIR', '/var/www/html/.wordpress' ), '/' ) . '/' );

define( 'DB_NAME', $wsh_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', $wsh_env( 'WP_TESTS_DB_USER', 'db' ) );
define( 'DB_PASSWORD', $wsh_env( 'WP_TESTS_DB_PASSWORD', 'db' ) );
define( 'DB_HOST', $wsh_env( 'WP_TESTS_DB_HOST', 'db' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- required by WordPress.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );

define( 'WSH_FRONTEND_ORIGIN', 'https://www.example.com' );
