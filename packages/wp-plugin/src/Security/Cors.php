<?php
/**
 * Closes CORS on the websage/v1 namespace.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Security;

use WebSage\HeadlessCore\Rest\Routes;
use WP_REST_Request;

/**
 * Next.js calls these routes server-side only, so no browser origin gets
 * CORS headers for them. Core routes keep WordPress's behaviour so the
 * block editor is unaffected.
 */
final class Cors {

	/**
	 * `rest_pre_serve_request` (priority 9, before core sends CORS headers).
	 *
	 * @param bool            $served  Whether the request was served.
	 * @param mixed           $result  Result.
	 * @param WP_REST_Request $request Request.
	 */
	public static function close( bool $served, mixed $result, WP_REST_Request $request ): bool {
		unset( $result );
		if ( str_starts_with( $request->get_route(), '/' . Routes::NAMESPACE . '/' ) ) {
			remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		}
		return $served;
	}
}
