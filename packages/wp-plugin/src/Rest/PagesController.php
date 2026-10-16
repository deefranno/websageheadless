<?php
/**
 * GET /websage/v1/pages/{slug}.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\PageSerializer;
use WebSage\HeadlessCore\Content\PathResolver;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves published, non-password-protected pages by hierarchical path.
 */
final class PagesController {

	/**
	 * Route callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function show( WP_REST_Request $request ): WP_REST_Response {
		try {
			$slug = (string) $request->get_param( 'slug' );
			$page = PathResolver::HOME_ALIAS === $slug ? self::front_page() : self::find( $slug );
			if ( null === $page ) {
				return Errors::response( 404, 'not_found' );
			}
			return new WP_REST_Response( array( 'data' => PageSerializer::serialize( $page ) ), 200 );
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}
	}

	/**
	 * The configured static front page.
	 *
	 * @throws ContentException When no published front page is configured.
	 */
	private static function front_page(): WP_Post {
		$id   = (int) get_option( 'page_on_front' );
		$page = 'page' === get_option( 'show_on_front' ) && $id > 0 ? get_post( $id ) : null;
		if ( ! $page instanceof WP_Post || ! self::is_public( $page ) ) {
			throw ContentException::not_configured( 'no published static front page is configured' );
		}
		return $page;
	}

	/**
	 * Looks up a page by path; drafts, private and protected pages are absent.
	 *
	 * @param string $slug Requested slug.
	 */
	private static function find( string $slug ): ?WP_Post {
		$path = PathResolver::normalize( $slug );
		if ( null === $path ) {
			return null;
		}
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( ! $page instanceof WP_Post || ! self::is_public( $page ) ) {
			return null;
		}
		foreach ( get_post_ancestors( $page ) as $ancestor_id ) {
			$ancestor = get_post( $ancestor_id );
			if ( ! $ancestor instanceof WP_Post || ! self::is_public( $ancestor ) ) {
				return null;
			}
		}
		return $page;
	}

	/**
	 * Published and not password protected.
	 *
	 * @param WP_Post $page Page.
	 */
	private static function is_public( WP_Post $page ): bool {
		return 'page' === $page->post_type && 'publish' === $page->post_status && '' === $page->post_password;
	}
}
