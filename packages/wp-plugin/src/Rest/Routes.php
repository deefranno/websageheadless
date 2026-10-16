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
 * Registers every public read route. Write routes arrive in later phases and
 * must use a real permission callback, never public_read().
 */
final class Routes {

	public const NAMESPACE = 'websage/v1';

	/**
	 * `rest_api_init` callback.
	 */
	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( SiteController::class, 'show' ),
				'permission_callback' => array( self::class, 'public_read' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/pages/(?P<slug>.+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( PagesController::class, 'show' ),
				'permission_callback' => array( self::class, 'public_read' ),
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
