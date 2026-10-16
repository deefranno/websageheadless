<?php
/**
 * Deployment secrets.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Support;

/**
 * Server-only secrets from wp-config constants or the environment. They are
 * never stored in the database, shown in wp-admin or returned by any route.
 */
final class Secrets {

	public const SERVER_API   = 'WSH_SERVER_API_SECRET';
	public const PREVIEW      = 'WSH_PREVIEW_SECRET';
	public const REVALIDATION = 'WSH_REVALIDATION_SECRET';

	public const MIN_LENGTH = 32;

	/**
	 * Secret value, or null when unset or shorter than MIN_LENGTH.
	 *
	 * @param string $name One of the class constants.
	 */
	public static function get( string $name ): ?string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );
		return is_string( $value ) && strlen( $value ) >= self::MIN_LENGTH ? $value : null;
	}

	/**
	 * Whether a secret is configured (for diagnostics; never the value).
	 *
	 * @param string $name One of the class constants.
	 */
	public static function configured( string $name ): bool {
		return null !== self::get( $name );
	}

	/**
	 * Constant-time check of a bearer token against a secret.
	 *
	 * @param string      $name   Secret name.
	 * @param string|null $header Authorization header value.
	 */
	public static function bearer_matches( string $name, ?string $header ): bool {
		$secret = self::get( $name );
		if ( null === $secret || null === $header || ! str_starts_with( $header, 'Bearer ' ) ) {
			return false;
		}
		return hash_equals( $secret, substr( $header, 7 ) );
	}
}
