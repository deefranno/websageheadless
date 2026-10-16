<?php
/**
 * Route registration for the websage/v1 namespace.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WP_REST_Server;

/**
 * Every websage/v1 route. Read routes that only return published content
 * use public_read(); everything else has a real permission callback.
 */
final class Routes {

	public const NAMESPACE = 'websage/v1';

	/**
	 * `rest_api_init` callback.
	 */
	public static function register(): void {
		$public = array( self::class, 'public_read' );

		register_rest_route(
			self::NAMESPACE,
			'/site',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( SiteController::class, 'show' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/pages/(?P<slug>.+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( PagesController::class, 'show' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( PostsController::class, 'index' ),
				'permission_callback' => $public,
				'args'                => PostsController::list_args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<slug>[^/]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( PostsController::class, 'show' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/routes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( RoutesController::class, 'index' ),
				'permission_callback' => $public,
				'args'                => RoutesController::args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/contact',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( ContactController::class, 'create' ),
				'permission_callback' => array( ContactController::class, 'authorize' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/preview/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( PreviewController::class, 'show' ),
				'permission_callback' => array( PreviewController::class, 'authorize' ),
			)
		);
	}

	/**
	 * Explicit permission callback for anonymous read routes that only ever
	 * return published, public data.
	 */
	public static function public_read(): bool {
		return true;
	}
}
