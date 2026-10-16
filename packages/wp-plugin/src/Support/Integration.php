<?php
/**
 * Frontend integration configuration.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Support;

use WebSage\HeadlessCore\Content\ContentException;

/**
 * Reads deployment configuration from wp-config constants or the environment.
 *
 * Phase 3 adds the wp-admin Integration tab; constants keep precedence so
 * hosts can pin values outside the database.
 */
final class Integration {

	/**
	 * HTTPS origin of the public Next.js site, without a trailing slash.
	 *
	 * @throws ContentException When missing or not HTTPS.
	 */
	public static function frontend_origin(): string {
		$configured = defined( 'WSH_FRONTEND_ORIGIN' ) ? constant( 'WSH_FRONTEND_ORIGIN' ) : getenv( 'WSH_FRONTEND_ORIGIN' );
		$origin     = is_string( $configured ) ? rtrim( $configured, '/' ) : '';
		$parts      = wp_parse_url( $origin );

		$valid = is_array( $parts )
			&& 'https' === ( $parts['scheme'] ?? '' )
			&& ! empty( $parts['host'] )
			&& array() === array_intersect_key( $parts, array_flip( array( 'user', 'pass', 'path', 'query', 'fragment' ) ) );

		if ( ! $valid ) {
			throw ContentException::not_configured( 'WSH_FRONTEND_ORIGIN must be an HTTPS origin' );
		}
		return $origin;
	}
}
