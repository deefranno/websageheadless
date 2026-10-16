<?php
/**
 * Public page path normalization.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

/**
 * Turns a requested page slug into a canonical hierarchical path, or rejects it.
 */
final class PathResolver {

	public const HOME_ALIAS = 'home';

	/** First path segments owned by the frontend. */
	public const RESERVED = array( 'blog', 'api', 'preview', '_next', 'wsh-404' );

	private const MAX_LENGTH   = 2048;
	private const MAX_SEGMENTS = 10;

	/**
	 * Normalizes a raw slug into "parent/child" form.
	 *
	 * @param string $raw Requested slug, possibly percent-encoded once.
	 * @return string|null Normalized path, or null when invalid, ambiguous or reserved.
	 */
	public static function normalize( string $raw ): ?string {
		if ( '' === $raw || strlen( $raw ) > self::MAX_LENGTH ) {
			return null;
		}

		$decoded = rawurldecode( $raw );
		// A "%" surviving one decode means double encoding, which hides traversal.
		if ( str_contains( $decoded, '%' ) || 1 === preg_match( '/[\\\\\s\x00-\x1f\x7f?#]/u', $decoded ) ) {
			return null;
		}
		if ( ! mb_check_encoding( $decoded, 'UTF-8' ) ) {
			return null;
		}

		$segments = explode( '/', trim( $decoded, '/' ) );
		if ( count( $segments ) > self::MAX_SEGMENTS ) {
			return null;
		}
		foreach ( $segments as $segment ) {
			if ( 1 !== preg_match( '/^[\p{Ll}\p{Lo}\p{N}_-]+$/u', $segment ) ) {
				return null;
			}
		}
		if ( in_array( $segments[0], self::RESERVED, true ) ) {
			return null;
		}

		return implode( '/', $segments );
	}
}
