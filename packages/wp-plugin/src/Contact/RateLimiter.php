<?php
/**
 * Atomic per-client rate limiting.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Contact;

/**
 * Fixed-window counters in a custom table, incremented with a single atomic
 * INSERT … ON DUPLICATE KEY UPDATE (no read-then-write race). Keys are
 * hashes of the already-hashed client identifier; rows expire within a day.
 */
final class RateLimiter {

	public const LIMIT  = 5;
	public const WINDOW = 900;

	/**
	 * Records one attempt and reports whether it is allowed.
	 *
	 * @param string $client_hash HMAC of client IP and date, from Next.js.
	 * @param int    $now         Current time.
	 * @return array{allowed: bool, retryAfter: int}
	 */
	public static function hit( string $client_hash, ?int $now = null ): array {
		global $wpdb;
		$now    = $now ?? time();
		$window = intdiv( $now, self::WINDOW ) * self::WINDOW;
		$bucket = hash( 'sha256', $client_hash . ':' . $window );
		$table  = Schema::rate_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table; values are prepared.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bucket, hits, expires_at) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE hits = hits + 1",
				$bucket,
				$window + self::WINDOW
			)
		);
		$hits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM {$table} WHERE bucket = %s", $bucket ) );
		// phpcs:enable

		return array(
			'allowed'    => $hits <= self::LIMIT,
			'retryAfter' => max( 1, $window + self::WINDOW - $now ),
		);
	}

	/**
	 * Deletes expired counters (keeps nothing older than a day).
	 */
	public static function purge(): void {
		global $wpdb;
		$table = Schema::rate_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table; value prepared.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %d", time() ) );
	}
}
