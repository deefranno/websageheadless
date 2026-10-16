<?php
/**
 * Webhook signatures.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Revalidation;

/**
 * HMAC-SHA256 over `timestamp + "." + eventId + "." + rawBody`, hex encoded.
 * Mirrored by apps/web/lib/revalidate/verify.ts.
 */
final class Signer {

	/**
	 * Hex signature.
	 *
	 * @param string $secret    Shared secret.
	 * @param int    $timestamp Unix seconds.
	 * @param string $event_id  Event UUID.
	 * @param string $body      Exact request body.
	 */
	public static function sign( string $secret, int $timestamp, string $event_id, string $body ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $event_id . '.' . $body, $secret );
	}
}
