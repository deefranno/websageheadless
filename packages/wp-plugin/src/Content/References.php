<?php
/**
 * Index of reusable content used by pages and posts.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use Throwable;
use WP_Post;

/**
 * Stores one `_wsh_ref` meta row per referenced item ("cta:12",
 * "testimonial:7", "pattern:3") on each page or post, so changing a library
 * item can invalidate exactly the pages that show it.
 */
final class References {

	public const META = '_wsh_ref';

	/**
	 * Recomputes references for a page or post.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function index( WP_Post $post ): void {
		if ( ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}
		try {
			$refs = Sections::build( $post->post_content )->references();
		} catch ( Throwable ) {
			$refs = array();
		}
		delete_post_meta( $post->ID, self::META );
		foreach ( $refs as $ref ) {
			add_post_meta( $post->ID, self::META, $ref );
		}
	}

	/**
	 * Published pages and posts that reference an item.
	 *
	 * @param string $ref "type:id".
	 * @return list<WP_Post>
	 */
	public static function users( string $ref ): array {
		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post' ),
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed lookup of a small set.
				'meta_value'       => $ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);
		return array_values( $posts );
	}
}
