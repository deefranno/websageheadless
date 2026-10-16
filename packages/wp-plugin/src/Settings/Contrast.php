<?php
/**
 * WCAG contrast calculation.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Settings;

/**
 * Relative-luminance contrast ratio per WCAG 2.2.
 */
final class Contrast {

	public const AA_NORMAL = 4.5;

	/**
	 * Contrast ratio between two #RRGGBB colours (1–21). Invalid input is 1.
	 *
	 * @param string $first  First colour.
	 * @param string $second Second colour.
	 */
	public static function ratio( string $first, string $second ): float {
		$a = self::luminance( $first );
		$b = self::luminance( $second );
		if ( null === $a || null === $b ) {
			return 1.0;
		}
		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * Relative luminance, or null for malformed colours.
	 *
	 * @param string $hex Colour.
	 */
	private static function luminance( string $hex ): ?float {
		if ( 1 !== preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m ) ) {
			return null;
		}
		$channels = array_map(
			static function ( string $part ): float {
				$c = hexdec( $part ) / 255;
				return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			array( $m[1], $m[2], $m[3] )
		);
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
