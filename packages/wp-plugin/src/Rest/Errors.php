<?php
/**
 * Error envelope for the websage/v1 namespace.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Produces `{error:{code,message,requestId}}` and adapts core REST errors to it.
 */
final class Errors {

	public const NAMESPACE_PREFIX = '/' . Routes::NAMESPACE;

	/** Generic public messages; details stay in server logs. */
	private const MESSAGES = array(
		400 => 'The request is invalid.',
		401 => 'Authentication is required.',
		403 => 'Access is not allowed.',
		404 => 'The requested resource is not available.',
		405 => 'The request method is not allowed.',
		413 => 'The content exceeds a size limit.',
		500 => 'An internal error occurred.',
		503 => 'The service is not configured or unavailable.',
	);

	/**
	 * Builds an error response.
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Stable machine-readable code.
	 */
	public static function response( int $status, string $code ): WP_REST_Response {
		$message = self::MESSAGES[ $status ] ?? self::MESSAGES[ $status >= 500 ? 500 : 400 ];
		return new WP_REST_Response(
			array(
				'error' => array(
					'code'      => $code,
					'message'   => $message,
					'requestId' => wp_generate_uuid4(),
				),
			),
			$status
		);
	}

	/**
	 * Logs a content failure and returns its public envelope.
	 *
	 * @param ContentException $e Failure.
	 */
	public static function from_exception( ContentException $e ): WP_REST_Response {
		$response = self::response( $e->status, $e->public_code );
		$data     = $response->get_data();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- server-side diagnostics only.
		error_log( sprintf( 'websage-headless [%s] %s: %s', $data['error']['requestId'], $e->public_code, $e->getMessage() ) );
		return $response;
	}

	/**
	 * `rest_post_dispatch` filter: normalizes errors and caching on our routes only.
	 *
	 * @param WP_HTTP_Response $result  Response.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 */
	public static function adapt( WP_HTTP_Response $result, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		unset( $server );
		if ( ! str_starts_with( $request->get_route(), self::NAMESPACE_PREFIX . '/' ) ) {
			return $result;
		}

		$status = $result->get_status();
		$data   = $result->get_data();
		if ( $status >= 400 && ! ( is_array( $data ) && isset( $data['error'] ) ) ) {
			$code    = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : 'error';
			$adapted = self::response( $status, sanitize_key( $code ) );
			foreach ( $result->get_headers() as $name => $value ) {
				$adapted->header( $name, $value );
			}
			$result = $adapted;
		}

		// Next.js owns caching; browsers and CDNs must not cache CMS responses.
		$result->header( 'Cache-Control', 'no-store' );
		return $result;
	}
}
