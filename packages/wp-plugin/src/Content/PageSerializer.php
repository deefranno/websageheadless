<?php
/**
 * Maps published pages to the public Page contract.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use DomainException;
use WebSage\HeadlessCore\Blocks\HeroMigration;
use WebSage\HeadlessCore\Support\Integration;
use WP_Post;

/**
 * Builds `{path,title,modified,sections,seo}` from block attributes only.
 *
 * Never renders blocks or shortcodes; never exposes author or internal meta.
 */
final class PageSerializer {

	public const MAX_SECTIONS = 100;
	public const MAX_DEPTH    = 8;
	public const MAX_BYTES    = 1048576;

	/**
	 * Serializes a published page.
	 *
	 * @param WP_Post $page Published page.
	 * @return array<string, mixed>
	 * @throws ContentException When content exceeds limits or the site is misconfigured.
	 */
	public static function serialize( WP_Post $page ): array {
		$path  = self::path( $page );
		$title = Values::text( $page->post_title, 'title' );
		$site  = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$data  = array(
			'path'     => $path,
			'title'    => $title,
			'modified' => Values::timestamp( $page->post_modified_gmt ),
			'sections' => self::sections( $page->post_content ),
			'seo'      => array(
				'title'          => Values::text( '' === $title ? $site : "{$title} | {$site}", 'seo.title' ),
				// Site tagline is the default until the Phase 3 SEO metabox.
				'description'    => Values::text( html_entity_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ), 'seo.description' ),
				'canonical'      => Integration::frontend_origin() . $path,
				'openGraphImage' => null,
				'noindex'        => false,
			),
		);

		$encoded = wp_json_encode( $data );
		if ( false === $encoded || strlen( $encoded ) > self::MAX_BYTES ) {
			throw ContentException::too_large( "page {$page->ID} payload exceeds 1 MiB" );
		}
		return $data;
	}

	/**
	 * Frontend path for a page; the configured front page is "/".
	 *
	 * @param WP_Post $page Page.
	 */
	public static function path( WP_Post $page ): string {
		if ( (int) get_option( 'page_on_front' ) === $page->ID && 'page' === get_option( 'show_on_front' ) ) {
			return '/';
		}
		return '/' . get_page_uri( $page );
	}

	/**
	 * Top-level blocks to sections, in document order.
	 *
	 * @param string $content Raw post content.
	 * @return list<array<string, mixed>>
	 * @throws ContentException When limits are exceeded.
	 */
	public static function sections( string $content ): array {
		$sections = array();
		foreach ( parse_blocks( $content ) as $block ) {
			if ( null === $block['blockName'] ) {
				continue; // Whitespace between blocks.
			}
			self::assert_depth( $block, 1 );
			if ( count( $sections ) >= self::MAX_SECTIONS ) {
				throw ContentException::too_large( 'more than ' . self::MAX_SECTIONS . ' sections' );
			}
			$sections[] = self::section( $block, (string) count( $sections ) );
		}
		return $sections;
	}

	/**
	 * One section. Types without a serializer yet keep only the envelope,
	 * which the frontend skips; their attributes never cross the boundary.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $key   Deterministic traversal key.
	 * @return array<string, mixed>
	 * @throws ContentException When the hero data is invalid.
	 */
	private static function section( array $block, string $key ): array {
		$attributes = is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( 'websage/hero' === $block['blockName'] ) {
			return self::hero( $attributes, $key );
		}

		$name    = (string) $block['blockName'];
		$version = $attributes['schemaVersion'] ?? 1;
		return array(
			'type'          => str_starts_with( $name, 'websage/' ) ? substr( $name, 8 ) : 'core-content',
			'schemaVersion' => is_int( $version ) && $version >= 0 ? $version : 1,
			'key'           => $key,
		);
	}

	/**
	 * Hero section in the HeroSection contract shape.
	 *
	 * @param array<string, mixed> $attributes Stored attributes.
	 * @param string               $key        Section key.
	 * @return array<string, mixed>
	 * @throws ContentException When the stored version is unsupported or values exceed limits.
	 */
	private static function hero( array $attributes, string $key ): array {
		try {
			$hero = HeroMigration::normalize( $attributes );
		} catch ( DomainException $e ) {
			throw new ContentException( 500, 'content_invalid', $e->getMessage() );
		}

		return array(
			'type'          => 'hero',
			'schemaVersion' => $hero['schemaVersion'],
			'key'           => $key,
			'eyebrow'       => Values::text( $hero['eyebrow'], 'hero.eyebrow' ),
			'heading'       => Values::text( $hero['heading'], 'hero.heading' ),
			'body'          => Values::rich_text( $hero['body'], 'hero.body' ),
			'image'         => Values::image( $hero['imageId'] ),
			'primary'       => Values::link( $hero['primary'], 'hero.primary' ),
			'secondary'     => Values::link( $hero['secondary'], 'hero.secondary' ),
			'theme'         => $hero['theme'],
		);
	}

	/**
	 * Rejects pathologically nested block trees.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param int                  $depth Current depth.
	 * @throws ContentException When deeper than MAX_DEPTH.
	 */
	private static function assert_depth( array $block, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			throw ContentException::too_large( 'block nesting deeper than ' . self::MAX_DEPTH );
		}
		foreach ( $block['innerBlocks'] ?? array() as $child ) {
			self::assert_depth( $child, $depth + 1 );
		}
	}
}
