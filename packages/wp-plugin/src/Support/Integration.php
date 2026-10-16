<?php
/**
 * Frontend integration configuration.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Support;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Settings\Settings;

/**
 * Frontend origin from, in order: the WSH_FRONTEND_ORIGIN constant, the
 * environment variable of the same name, then the Integration settings tab.
 */
final class Integration {

	/**
	 * HTTPS origin of the public Next.js site, without a trailing slash.
	 *
	 * @throws ContentException When missing or not HTTPS.
	 */
	public static function frontend_origin(): string {
		$configured = defined( 'WSH_FRONTEND_ORIGIN' ) ? constant( 'WSH_FRONTEND_ORIGIN' ) : getenv( 'WSH_FRONTEND_ORIGIN' );
		if ( ! is_string( $configured ) || '' === $configured ) {
			$configured = Settings::get( 'frontend_url' );
		}
		$origin = is_string( $configured ) ? rtrim( $configured, '/' ) : '';
		$parts  = wp_parse_url( $origin );

		$valid = is_array( $parts )
			&& 'https' === ( $parts['scheme'] ?? '' )
			&& ! empty( $parts['host'] )
			&& array() === array_intersect_key( $parts, array_flip( array( 'user', 'pass', 'path', 'query', 'fragment' ) ) );

		if ( ! $valid ) {
			throw ContentException::not_configured( 'frontend origin (WSH_FRONTEND_ORIGIN or the Integration setting) must be an HTTPS origin' );
		}
		return $origin;
	}
}
