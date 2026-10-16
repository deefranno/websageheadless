<?php
/**
 * GET /websage/v1/posts and /posts/{slug}.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\PostSerializer;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Published, non-protected blog posts, newest first.
 */
final class PostsController {

	/**
	 * Route arguments for the list endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function list_args(): array {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 10000,
				'default' => 1,
			),
			'perPage'  => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
				'default' => 10,
			),
			'category' => array(
				'type'      => 'string',
				'maxLength' => 200,
				'pattern'   => '^[\\p{Ll}\\p{Lo}\\p{N}_-]+$',
			),
		);
	}

	/**
	 * List callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'perPage' );
		$category = $request->get_param( 'category' );

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'has_password'        => false,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
		);
		if ( is_string( $category ) && '' !== $category ) {
			$args['category_name'] = $category;
		}
		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;

		try {
			$posts = array_filter( (array) $query->posts, static fn( $p ) => $p instanceof WP_Post );
			$items = array_map( array( PostSerializer::class, 'summary' ), array_values( $posts ) );
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}

		$terms      = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'number'     => 500,
				'orderby'    => 'name',
			)
		);
		$categories = is_array( $terms ) ? array_values( array_map( array( PostSerializer::class, 'category' ), $terms ) ) : array();

		return new WP_REST_Response(
			array(
				'data' => array(
					'items'      => $items,
					'pagination' => array(
						'page'       => $page,
						'perPage'    => $per_page,
						'total'      => $total,
						'totalPages' => 0 === $total ? 0 : (int) ceil( $total / $per_page ),
					),
					'categories' => $categories,
				),
			),
			200
		);
	}

	/**
	 * Single post callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$slug = (string) $request->get_param( 'slug' );
		if ( 1 !== preg_match( '/^[\p{Ll}\p{Lo}\p{N}_-]{1,200}$/u', $slug ) ) {
			return Errors::response( 404, 'not_found' );
		}
		$posts = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'name'             => sanitize_title_for_query( $slug ),
				'has_password'     => false,
				'numberposts'      => 1,
				'suppress_filters' => false,
			)
		);
		$post  = $posts[0] ?? null;
		if ( ! $post instanceof WP_Post ) {
			return Errors::response( 404, 'not_found' );
		}
		try {
			return new WP_REST_Response( array( 'data' => PostSerializer::serialize( $post ) ), 200 );
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}
	}
}
