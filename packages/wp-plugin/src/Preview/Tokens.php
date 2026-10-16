<?php
/**
 * Short-lived signed preview tokens.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Preview;

use WebSage\HeadlessCore\Support\Secrets;

/**
 * `base64url(json) . "." . base64url(HMAC-SHA256(base64url(json)))` with the
 * dedicated preview secret. Payload: {v, id, parentId, path, iat, exp, jti}.
 * Next.js verifies the same format (apps/web/lib/preview/token.ts) and sends
 * the token back as the preview grant.
 */
final class Tokens {

	public const LIFETIME = 300;

	/**
	 * Issues a token for a post or revision.
	 *
	 * @param int    $id        Post, revision or autosave ID to preview.
	 * @param int    $parent_id Routable post ID.
	 * @param string $path      Frontend path to open.
	 * @param int    $now       Issue time.
	 * @return string|null Null when the preview secret is not configured.
	 */
	public static function issue( int $id, int $parent_id, string $path, ?int $now = null ): ?string {
		$secret = Secrets::get( Secrets::PREVIEW );
		if ( null === $secret ) {
			return null;
		}
		$now     = $now ?? time();
		$payload = self::b64(
			(string) wp_json_encode(
				array(
					'v'        => 1,
					'id'       => $id,
					'parentId' => $parent_id,
					'path'     => $path,
					'iat'      => $now,
					'exp'      => $now + self::LIFETIME,
					'jti'      => wp_generate_uuid4(),
				)
			)
		);
		return $payload . '.' . self::b64( hash_hmac( 'sha256', $payload, $secret, true ) );
	}

	/**
	 * Verified payload, or null when forged, malformed or expired.
	 *
	 * @param string $token Token.
	 * @param int    $now   Current time.
	 * @return array{v: int, id: int, parentId: int, path: string, iat: int, exp: int, jti: string}|null
	 */
	public static function verify( string $token, ?int $now = null ): ?array {
		$secret = Secrets::get( Secrets::PREVIEW );
		$parts  = explode( '.', $token );
		if ( null === $secret || 2 !== count( $parts ) || strlen( $token ) > 2048 ) {
			return null;
		}
		[ $payload, $signature ] = $parts;
		if ( ! hash_equals( self::b64( hash_hmac( 'sha256', $payload, $secret, true ) ), $signature ) ) {
			return null;
		}
		$data = json_decode( (string) base64_decode( strtr( $payload, '-_', '+/' ), true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- token decoding.
		$now  = $now ?? time();
		if (
			! is_array( $data ) || 1 !== ( $data['v'] ?? null )
			|| ! is_int( $data['id'] ?? null ) || ! is_int( $data['parentId'] ?? null )
			|| ! is_string( $data['path'] ?? null ) || ! is_int( $data['iat'] ?? null )
			|| ! is_int( $data['exp'] ?? null ) || ! is_string( $data['jti'] ?? null )
			|| $data['exp'] < $now || $data['iat'] > $now + 30 || $data['exp'] - $data['iat'] > self::LIFETIME
		) {
			return null;
		}
		return array(
			'v'        => 1,
			'id'       => $data['id'],
			'parentId' => $data['parentId'],
			'path'     => $data['path'],
			'iat'      => $data['iat'],
			'exp'      => $data['exp'],
			'jti'      => $data['jti'],
		);
	}

	/**
	 * Unpadded base64url.
	 *
	 * @param string $bytes Bytes.
	 */
	private static function b64( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- token encoding.
	}
}
