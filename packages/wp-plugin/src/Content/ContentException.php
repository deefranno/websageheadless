<?php
/**
 * Typed failures raised while building public payloads.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use RuntimeException;

/**
 * Carries an HTTP status and public error code; the message is for logs only.
 */
final class ContentException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param int    $status      HTTP status for the public response.
	 * @param string $public_code Stable machine-readable error code.
	 * @param string $log_message Diagnostic detail; never sent to clients.
	 */
	public function __construct( public readonly int $status, public readonly string $public_code, string $log_message ) {
		parent::__construct( $log_message );
	}

	/**
	 * Stored content exceeds a published limit.
	 *
	 * @param string $detail Diagnostic detail.
	 */
	public static function too_large( string $detail ): self {
		return new self( 413, 'content_too_large', $detail );
	}

	/**
	 * The site is missing configuration the endpoint depends on.
	 *
	 * @param string $detail Diagnostic detail.
	 */
	public static function not_configured( string $detail ): self {
		return new self( 503, 'not_configured', $detail );
	}
}
