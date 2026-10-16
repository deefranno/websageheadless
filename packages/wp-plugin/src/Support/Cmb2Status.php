<?php
/**
 * Reports which CMB2 copy won version negotiation.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Support;

/**
 * Read-only view of the loaded CMB2 runtime, for diagnostics.
 */
final class Cmb2Status {

	/**
	 * Loaded CMB2 version and source directory, or nulls before `init`.
	 *
	 * @return array{version: ?string, dir: ?string, bundled: bool}
	 */
	public static function current(): array {
		$version = defined( 'CMB2_VERSION' ) ? (string) constant( 'CMB2_VERSION' ) : null;
		$dir     = defined( 'CMB2_DIR' ) ? (string) constant( 'CMB2_DIR' ) : null;
		return array(
			'version' => $version,
			'dir'     => $dir,
			'bundled' => null !== $dir && self::normalize( $dir ) === self::normalize( self::bundled_dir() ),
		);
	}

	/**
	 * Directory of the CMB2 copy shipped inside this plugin.
	 */
	public static function bundled_dir(): string {
		return WSH_PLUGIN_DIR . '/vendor/cmb2/cmb2/';
	}

	/**
	 * Compares directories independent of trailing slashes and symlinks.
	 *
	 * @param string $dir Directory path.
	 */
	private static function normalize( string $dir ): string {
		$real = realpath( $dir );
		return rtrim( false === $real ? $dir : $real, '/' );
	}
}
