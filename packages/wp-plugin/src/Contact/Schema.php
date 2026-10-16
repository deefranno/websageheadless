<?php
/**
 * Custom tables owned by the plugin.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Contact;

/**
 * Creates and upgrades plugin tables with dbDelta.
 */
final class Schema {

	public const VERSION        = 1;
	public const VERSION_OPTION = 'wsh_db_version';

	/**
	 * Rate-limit table name.
	 */
	public static function rate_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wsh_rate_limits';
	}

	/**
	 * Installs or upgrades tables when the stored version is behind.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Creates tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::rate_table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
  bucket char(64) NOT NULL,
  hits int(10) unsigned NOT NULL DEFAULT 0,
  expires_at bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (bucket),
  KEY expires_at (expires_at)
) {$collate};"
		);
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Drops tables (uninstall).
	 */
	public static function drop(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::rate_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name is internal.
		delete_option( self::VERSION_OPTION );
	}
}
