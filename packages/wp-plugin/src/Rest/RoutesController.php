<?php
/**
 * GET /websage/v1/routes.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\Paths;
use WebSage\HeadlessCore\Content\PathResolver;
use WebSage\HeadlessCore\Content\Seo;
use WebSage\HeadlessCore\Content\Values;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Every public, indexable URL with its modified date: pages, posts and the
 * synthetic /blog index. Used for the sitemap, static params and the
 * frontend's 404 check.
 */
final class RoutesController {

	/**
	 * Route arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function args(): array {
		return array(
			'page'    => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 10000,
				'default' => 1,
			),
			'perPage' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 500,
				'default' => 100,
			),
		);
	}

	/**
	 * Route callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		try {
			$items = self::all();
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'perPage' );
		$total    = count( $items );

		return new WP_REST_Response(
			array(
				'data' => array(
					'items'      => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
					'pagination' => array(
						'page'       => $page,
						'perPage'    => $per_page,
						'total'      => $total,
						'totalPages' => 0 === $total ? 0 : (int) ceil( $total / $per_page ),
					),
				),
			),
			200
		);
	}

	/**
	 * All routes, front page first.
	 *
	 * @return list<array{path: string, kind: string, modified: string}>
	 * @throws ContentException When no published front page is configured.
	 */
	public static function all(): array {
		$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$front    = $front_id > 0 ? get_post( $front_id ) : null;
		if ( ! $front instanceof WP_Post || 'publish' !== $front->post_status || '' !== $front->post_password ) {
			throw ContentException::not_configured( 'no published static front page is configured' );
		}

		$items = array();
		$pages = self::published( 'page' );
		$posts = self::published( 'post' );

		foreach ( $pages as $page ) {
			$path = Paths::for_post( $page );
			if ( null === $path || Seo::noindex( $page ) || ! self::ancestors_public( $page ) ) {
				continue;
			}
			// Paths owned by the frontend (e.g. /blog) cannot be CMS pages.
			if ( '/' !== $path && null === PathResolver::normalize( ltrim( $path, '/' ) ) ) {
				continue;
			}
			$item    = array(
				'path'     => $path,
				'kind'     => 'page',
				'modified' => Values::timestamp( $page->post_modified_gmt ),
			);
			$items[] = $item;
		}
		// Front page first, then pages by path: stable and sitemap-friendly.
		usort(
			$items,
			static function ( array $a, array $b ): int {
				$front = ( '/' === $b['path'] ) <=> ( '/' === $a['path'] );
				return 0 !== $front ? $front : strcmp( $a['path'], $b['path'] );
			}
		);

		$latest = $front->post_modified_gmt;
		foreach ( $posts as $post ) {
			$latest = max( $latest, $post->post_modified_gmt );
		}
		$items[] = array(
			'path'     => '/blog',
			'kind'     => 'index',
			'modified' => Values::timestamp( $latest ),
		);

		foreach ( $posts as $post ) {
			if ( Seo::noindex( $post ) ) {
				continue;
			}
			$items[] = array(
				'path'     => (string) Paths::for_post( $post ),
				'kind'     => 'post',
				'modified' => Values::timestamp( $post->post_modified_gmt ),
			);
		}
		return $items;
	}

	/**
	 * Published, unprotected posts of a type.
	 *
	 * @param string $type Post type.
	 * @return list<WP_Post>
	 */
	private static function published( string $type ): array {
		$posts = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'has_password'     => false,
				'numberposts'      => -1,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'suppress_filters' => false,
			)
		);
		return array_values( $posts );
	}

	/**
	 * Whether every ancestor page is public.
	 *
	 * @param WP_Post $page Page.
	 */
	private static function ancestors_public( WP_Post $page ): bool {
		foreach ( get_post_ancestors( $page ) as $id ) {
			$ancestor = get_post( $id );
			if ( ! $ancestor instanceof WP_Post || 'publish' !== $ancestor->post_status || '' !== $ancestor->post_password ) {
				return false;
			}
		}
		return true;
	}
}
