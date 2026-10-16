<?php
/**
 * Frontend paths for WordPress content.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WP_Post;
use WP_Term;

/**
 * The one place that knows how WordPress objects map to frontend routes:
 * pages by hierarchical path ("/" for the front page), posts under /blog.
 */
final class Paths {

	/**
	 * Frontend path for a routable post, or null for other types.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function for_post( WP_Post $post ): ?string {
		if ( 'page' === $post->post_type && self::is_front_page( $post ) ) {
			return '/';
		}
		if ( '' === $post->post_name ) {
			return null; // Not routable until it has a slug.
		}
		if ( 'page' === $post->post_type ) {
			return '/' . rawurldecode( (string) get_page_uri( $post ) );
		}
		if ( 'post' === $post->post_type ) {
			return '/blog/' . rawurldecode( $post->post_name );
		}
		return null;
	}

	/**
	 * Whether a page is the configured static front page.
	 *
	 * @param WP_Post $page Page.
	 */
	public static function is_front_page( WP_Post $page ): bool {
		return 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page->ID;
	}

	/**
	 * Raw link target for a nav menu item, before Values::url() validation.
	 *
	 * @param WP_Post $item Decorated nav menu item.
	 */
	public static function frontend_url_for_menu( WP_Post $item ): string {
		if ( 'post_type' === self::prop( $item, 'type' ) ) {
			$target = get_post( (int) self::prop( $item, 'object_id' ) );
			$path   = $target instanceof WP_Post ? self::for_post( $target ) : null;
			return $path ?? '';
		}
		if ( 'taxonomy' === self::prop( $item, 'type' ) && 'category' === self::prop( $item, 'object' ) ) {
			$term = get_term( (int) self::prop( $item, 'object_id' ), 'category' );
			return $term instanceof WP_Term ? '/blog?category=' . rawurlencode( $term->slug ) : '';
		}
		return (string) self::prop( $item, 'url' );
	}

	/**
	 * Property added to nav menu items by wp_setup_nav_menu_item().
	 *
	 * @param WP_Post $item Menu item.
	 * @param string  $name Property name.
	 */
	private static function prop( WP_Post $item, string $name ): string {
		$value = get_object_vars( $item )[ $name ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}
}
