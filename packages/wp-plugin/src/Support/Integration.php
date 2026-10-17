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
	 * Origin of the public Next.js site, without a trailing slash.
	 *
	 * @throws ContentException When missing or invalid.
	 */
	public static function frontend_origin(): string {
		$configured = defined( 'WSH_FRONTEND_ORIGIN' ) ? constant( 'WSH_FRONTEND_ORIGIN' ) : getenv( 'WSH_FRONTEND_ORIGIN' );
		if ( ! is_string( $configured ) || '' === $configured ) {
			$configured = Settings::get( 'frontend_url' );
		}
		$origin = is_string( $configured ) ? rtrim( $configured, '/' ) : '';
		if ( ! self::is_valid_origin( $origin ) ) {
			throw ContentException::not_configured( 'frontend origin (WSH_FRONTEND_ORIGIN or the Integration setting) must be an HTTPS origin' );
		}
		return $origin;
	}

	/**
	 * A bare origin (scheme, host, optional port): HTTPS, or plain HTTP for a
	 * loopback host only, which browsers treat as a secure context. That lets
	 * a local development server receive previews and webhooks.
	 *
	 * @param string $origin Candidate origin.
	 */
	public static function is_valid_origin( string $origin ): bool {
		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['host'] )
			|| array() !== array_intersect_key( $parts, array_flip( array( 'user', 'pass', 'path', 'query', 'fragment' ) ) ) ) {
			return false;
		}
		$scheme = $parts['scheme'] ?? '';
		return 'https' === $scheme
			|| ( 'http' === $scheme && in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '[::1]' ), true ) );
	}
}
