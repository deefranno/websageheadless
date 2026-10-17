<?php
/**
 * POST /websage/v1/contact (server-to-server only).
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Contact\Mailer;
use WebSage\HeadlessCore\Contact\RateLimiter;
use WebSage\HeadlessCore\Contact\Submissions;
use WebSage\HeadlessCore\Contact\Validator;
use WebSage\HeadlessCore\Support\Secrets;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Accepts contact submissions from the Next.js route handler. Browsers never
 * call this route: it requires the server API secret.
 */
final class ContactController {

	public const MAX_BYTES = 16384;

	/**
	 * Permission callback: bearer secret.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function authorize( WP_REST_Request $request ): bool|WP_Error {
		return Secrets::bearer_matches( Secrets::SERVER_API, $request->get_header( 'authorization' ) )
			? true
			: new WP_Error( 'unauthorized', '', array( 'status' => 401 ) );
	}

	/**
	 * Route callback.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function create( WP_REST_Request $request ): WP_REST_Response {
		if ( strlen( $request->get_body() ) > self::MAX_BYTES ) {
			return Errors::response( 413, 'too_large' );
		}
		$input = json_decode( $request->get_body(), true );
		if ( ! is_array( $input ) || array() !== Validator::unknown_keys( $input ) ) {
			return Errors::response( 400, 'invalid_request' );
		}
		$client = (string) $request->get_header( 'x_wsh_client_hash' );
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $client ) ) {
			return Errors::response( 400, 'invalid_client_hash' );
		}

		$result = Validator::validate( $input );
		if ( ! $result['ok'] ) {
			$response = Errors::response( 422, 'validation_failed' );
			$data     = $response->get_data();

			$data['error']['message'] = __( 'Please check the highlighted fields.', 'websage-headless' );
			$data['error']['fields']  = $result['fields'];
			$response->set_data( $data );
			return $response;
		}
		$data = $result['data'];

		// Retried requests: same payload is the same acceptance; changed payload is a conflict.
		$existing = Submissions::find_by_key( $data['idempotencyKey'] );
		if ( null !== $existing ) {
			$same = hash_equals( (string) get_post_meta( $existing->ID, Submissions::PAYLOAD_HASH, true ), Submissions::payload_hash( $data ) );
			return $same ? self::accepted() : Errors::response( 409, 'conflict' );
		}

		$limit = RateLimiter::hit( $client );
		if ( ! $limit['allowed'] ) {
			$response = Errors::response( 429, 'rate_limited' );
			$response->header( 'Retry-After', (string) $limit['retryAfter'] );
			return $response;
		}

		// Honeypot filled: look successful, store nothing, send nothing.
		if ( '' !== $data['website'] ) {
			return self::accepted();
		}

		$id = Submissions::store( $data );
		if ( 0 === $id ) {
			return Errors::response( 503, 'storage_unavailable' );
		}
		Mailer::send( $id );
		return self::accepted();
	}

	/**
	 * 201 acceptance.
	 */
	private static function accepted(): WP_REST_Response {
		return new WP_REST_Response( array( 'data' => array( 'accepted' => true ) ), 201 );
	}
}
