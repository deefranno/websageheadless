<?php
/**
 * Maps published pages to the public Page contract.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WP_Post;

/**
 * Builds `{path,title,modified,sections,seo}`. Never exposes author or
 * internal meta.
 */
final class PageSerializer {

	public const MAX_BYTES = 1048576;

	/**
	 * Serializes a page (published, or a preview revision of one).
	 *
	 * @param WP_Post $page Page or revision.
	 * @param WP_Post $route Post whose route and SEO apply (the parent for revisions).
	 * @return array<string, mixed>
	 * @throws ContentException When content exceeds limits or the site is misconfigured.
	 */
	public static function serialize( WP_Post $page, ?WP_Post $route = null ): array {
		$route = $route ?? $page;
		$path  = Paths::for_post( $route ) ?? '/';
		$data  = array(
			'path'     => $path,
			'title'    => Values::text( $page->post_title, 'title' ),
			'modified' => Values::timestamp( $page->post_modified_gmt ),
			'sections' => Sections::build( $page->post_content )->sections(),
			'seo'      => Seo::for_post( $route, $path, '', $page->post_title ),
		);
		return self::bounded( $data, $route->ID );
	}

	/**
	 * Rejects payloads above 1 MiB.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @param int                  $id   Post ID for diagnostics.
	 * @return array<string, mixed>
	 * @throws ContentException Over the limit.
	 */
	public static function bounded( array $data, int $id ): array {
		$encoded = wp_json_encode( $data );
		if ( false === $encoded || strlen( $encoded ) > self::MAX_BYTES ) {
			throw ContentException::too_large( "post {$id} payload exceeds 1 MiB" );
		}
		return $data;
	}
}
