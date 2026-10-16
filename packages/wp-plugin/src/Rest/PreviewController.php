<?php
/**
 * GET /websage/v1/preview/{id} (server-to-server only).
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\PageSerializer;
use WebSage\HeadlessCore\Content\PostSerializer;
use WebSage\HeadlessCore\Preview\Tokens;
use WebSage\HeadlessCore\Support\Secrets;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Draft or revision content for Next.js Draft Mode. Requires both the
 * server API secret and a preview grant bound to the requested ID.
 */
final class PreviewController {

	/**
	 * Permission callback: bearer secret plus a valid grant for this ID.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function authorize( WP_REST_Request $request ): bool|WP_Error {
		if ( ! Secrets::bearer_matches( Secrets::SERVER_API, $request->get_header( 'authorization' ) ) ) {
			return new WP_Error( 'unauthorized', '', array( 'status' => 401 ) );
		}
		$grant = Tokens::verify( (string) $request->get_header( 'x_wsh_preview_grant' ) );
		if ( null === $grant || (int) $request->get_param( 'id' ) !== $grant['id'] ) {
			return new WP_Error( 'forbidden', '', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Route callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$grant   = Tokens::verify( (string) $request->get_header( 'x_wsh_preview_grant' ) );
		$content = get_post( (int) $request->get_param( 'id' ) );
		if ( null === $grant || ! $content instanceof WP_Post ) {
			return Errors::response( 404, 'not_found' );
		}

		$route = 'revision' === $content->post_type ? get_post( $content->post_parent ) : $content;
		if ( ! $route instanceof WP_Post || $route->ID !== $grant['parentId'] || ! in_array( $route->post_type, array( 'page', 'post' ), true ) ) {
			return Errors::response( 404, 'not_found' );
		}
		if ( in_array( $route->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return Errors::response( 404, 'not_found' );
		}

		try {
			$data = 'page' === $route->post_type
				? array(
					'kind'    => 'page',
					'content' => PageSerializer::serialize( $content, $route ),
				)
				: array(
					'kind'    => 'post',
					'content' => PostSerializer::serialize( $content, $route ),
				);
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}

		$data['content']['path']           = $grant['path'];
		$data['content']['seo']['noindex'] = true;
		$response                          = new WP_REST_Response( array( 'data' => $data ), 200 );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}
}
