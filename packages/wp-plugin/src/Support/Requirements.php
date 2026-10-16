<?php
/**
 * Minimum platform requirements.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Support;

use DomainException;

/**
 * Validates the PHP and WordPress versions the plugin supports.
 */
final class Requirements {

	public const MIN_PHP = '8.1';
	public const MIN_WP  = '7.1.3';

	/**
	 * Throws when either version is below the supported minimum.
	 *
	 * @param string $php_version PHP version to check.
	 * @param string $wp_version  WordPress version to check.
	 * @throws DomainException With a translatable, user-facing message.
	 */
	public static function check( string $php_version, string $wp_version ): void {
		if ( version_compare( $php_version, self::MIN_PHP, '<' ) ) {
			throw new DomainException(
				sprintf(
					/* translators: 1: required PHP version, 2: running PHP version. */
					self::translate( 'WebSage Headless Core requires PHP %1$s or newer. This server runs PHP %2$s.' ),
					self::MIN_PHP,
					$php_version
				)
			);
		}
		if ( version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			throw new DomainException(
				sprintf(
					/* translators: 1: required WordPress version, 2: running WordPress version. */
					self::translate( 'WebSage Headless Core requires WordPress %1$s or newer. This site runs WordPress %2$s.' ),
					self::MIN_WP,
					$wp_version
				)
			);
		}
	}

	/**
	 * Checks the running environment.
	 *
	 * @throws DomainException When unsupported.
	 */
	public static function assert_supported(): void {
		self::check( PHP_VERSION, (string) get_bloginfo( 'version' ) );
	}

	/**
	 * Translates when WordPress is loaded; pure unit tests run without it.
	 *
	 * @param string $text Source string.
	 */
	private static function translate( string $text ): string {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- literals are passed in from this class.
		return function_exists( '__' ) ? __( $text, 'websage-headless' ) : $text;
	}
}
