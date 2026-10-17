<?php
/**
 * Maps published posts to the PostSummary and Post contracts.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WP_Post;
use WP_Term;

/**
 * Blog posts. No author identity is ever exposed.
 */
final class PostSerializer {

	/**
	 * List item.
	 *
	 * @param WP_Post $post  Published post (or a revision when previewing).
	 * @param WP_Post $route Post whose route applies (parent for revisions).
	 * @return array<string, mixed>
	 */
	public static function summary( WP_Post $post, ?WP_Post $route = null ): array {
		$route = $route ?? $post;
		$slug  = rawurldecode( $route->post_name );
		return array(
			'slug'       => $slug,
			'path'       => '/blog/' . $slug,
			'title'      => Values::text_clipped( $post->post_title ),
			'excerpt'    => self::excerpt( $post ),
			'published'  => Values::timestamp( '0000-00-00 00:00:00' === $route->post_date_gmt ? $route->post_modified_gmt : $route->post_date_gmt ),
			'modified'   => Values::timestamp( $post->post_modified_gmt ),
			'image'      => Values::image( (int) get_post_thumbnail_id( $route ) ),
			'categories' => self::categories( $route ),
		);
	}

	/**
	 * Full post.
	 *
	 * @param WP_Post $post  Published post or revision.
	 * @param WP_Post $route Post whose route and SEO apply.
	 * @return array<string, mixed>
	 * @throws ContentException When content exceeds limits or the site is misconfigured.
	 */
	public static function serialize( WP_Post $post, ?WP_Post $route = null ): array {
		$route   = $route ?? $post;
		$summary = self::summary( $post, $route );
		$data    = array_merge(
			$summary,
			array(
				'sections' => Sections::build( $post->post_content )->sections(),
				'seo'      => Seo::for_post( $route, (string) $summary['path'], (string) $summary['excerpt'], $post->post_title ),
			)
		);
		return PageSerializer::bounded( $data, $route->ID );
	}

	/**
	 * Category choices for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return list<array{slug: string, name: string}>
	 */
	public static function categories( WP_Post $post ): array {
		$terms = get_the_terms( $post, 'category' );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_values( array_map( array( self::class, 'category' ), $terms ) );
	}

	/**
	 * One category.
	 *
	 * @param WP_Term $term Term.
	 * @return array{slug: string, name: string}
	 */
	public static function category( WP_Term $term ): array {
		return array(
			'slug' => rawurldecode( $term->slug ),
			'name' => Values::text_clipped( html_entity_decode( $term->name, ENT_QUOTES ) ),
		);
	}

	/**
	 * Manual excerpt, or the opening words of the content as plain text.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function excerpt( WP_Post $post ): string {
		$source = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( excerpt_remove_blocks( strip_shortcodes( $post->post_content ) ), 40, '…' );
		return Values::text_clipped( html_entity_decode( $source, ENT_QUOTES ) );
	}
}
