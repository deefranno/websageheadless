<?php
/**
 * Server-side attribute migrations for websage/hero.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Blocks;

use DomainException;

/**
 * Upgrades stored hero attributes to the current schema.
 *
 * Editor deprecations only run when a post is reopened in the editor, so
 * content that is never re-saved must be migrated here before serializing.
 * Block defaults are not written to post content, so a missing
 * schemaVersion means version 1; later versions must store it explicitly.
 */
final class HeroMigration {

	public const CURRENT_VERSION = 1;
	public const THEMES          = array( 'default', 'muted', 'brand' );

	/**
	 * Returns a complete, current-version attribute array.
	 *
	 * @param array<string, mixed> $attributes Stored block attributes.
	 * @return array{schemaVersion: int, eyebrow: mixed, heading: mixed, body: mixed, imageId: mixed, primary: mixed, secondary: mixed, theme: string}
	 * @throws DomainException For versions newer than this plugin understands.
	 */
	public static function normalize( array $attributes ): array {
		$version = $attributes['schemaVersion'] ?? self::CURRENT_VERSION;
		if ( ! is_int( $version ) || $version < 0 || $version > self::CURRENT_VERSION ) {
			throw new DomainException( 'Unsupported websage/hero schemaVersion.' );
		}

		if ( 0 === $version ) {
			// v0 stored the heading as "title".
			$attributes['heading'] = $attributes['heading'] ?? ( $attributes['title'] ?? '' );
			unset( $attributes['title'] );
		}

		$theme = $attributes['theme'] ?? 'default';

		return array(
			'schemaVersion' => self::CURRENT_VERSION,
			'eyebrow'       => $attributes['eyebrow'] ?? '',
			'heading'       => $attributes['heading'] ?? '',
			'body'          => $attributes['body'] ?? '',
			'imageId'       => $attributes['imageId'] ?? null,
			'primary'       => $attributes['primary'] ?? null,
			'secondary'     => $attributes['secondary'] ?? null,
			'theme'         => in_array( $theme, self::THEMES, true ) ? $theme : 'default',
		);
	}
}
