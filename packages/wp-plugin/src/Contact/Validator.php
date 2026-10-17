<?php
/**
 * Contact input validation (mirrors ContactInputSchema in packages/contracts).
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Contact;

/**
 * Validates and normalizes a submission. Field codes match the contract:
 * required | too_long | invalid.
 */
final class Validator {

	public const FIELDS = array( 'name', 'email', 'message', 'website', 'consent', 'idempotencyKey' );

	/**
	 * Result of validation.
	 *
	 * @param array<string, mixed> $input Decoded JSON body.
	 * @return array{ok: true, data: array{name: string, email: string, message: string, website: string, consent: true, idempotencyKey: string}}|array{ok: false, fields: array<string, string>}
	 */
	public static function validate( array $input ): array {
		$fields = array();

		$name = self::trimmed( $input['name'] ?? null );
		if ( null === $name || '' === $name ) {
			$fields['name'] = 'required';
		} elseif ( mb_strlen( $name ) > 120 ) {
			$fields['name'] = 'too_long';
		} elseif ( 1 === preg_match( '/[\x00-\x1f\x7f]/', $name ) ) {
			$fields['name'] = 'invalid';
		}

		$email = self::trimmed( $input['email'] ?? null );
		if ( null === $email || '' === $email ) {
			$fields['email'] = 'invalid';
		} elseif ( strlen( $email ) > 254 ) {
			$fields['email'] = 'too_long';
		} elseif ( ! is_email( $email ) || 1 === preg_match( '/[\x00-\x1f\x7f]/', $email ) ) {
			$fields['email'] = 'invalid';
		}

		$message = self::trimmed( $input['message'] ?? null );
		if ( null === $message || '' === $message ) {
			$fields['message'] = 'required';
		} elseif ( mb_strlen( $message ) > 5000 ) {
			$fields['message'] = 'too_long';
		} elseif ( 1 === preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $message ) ) {
			$fields['message'] = 'invalid';
		}

		$website = $input['website'] ?? null;
		if ( ! is_string( $website ) ) {
			$fields['website'] = 'invalid';
		} elseif ( mb_strlen( $website ) > 200 ) {
			$fields['website'] = 'too_long';
		}

		if ( true !== ( $input['consent'] ?? null ) ) {
			$fields['consent'] = 'required';
		}

		$key = $input['idempotencyKey'] ?? null;
		if ( ! is_string( $key ) || ! wp_is_uuid( $key ) ) {
			$fields['idempotencyKey'] = 'invalid';
		}

		if ( array() !== $fields ) {
			return array(
				'ok'     => false,
				'fields' => $fields,
			);
		}
		return array(
			'ok'   => true,
			'data' => array(
				'name'           => (string) $name,
				'email'          => (string) $email,
				'message'        => (string) $message,
				'website'        => (string) $website,
				'consent'        => true,
				'idempotencyKey' => strtolower( (string) $key ),
			),
		);
	}

	/**
	 * Keys that are not part of the contract.
	 *
	 * @param array<string, mixed> $input Decoded body.
	 * @return list<string>
	 */
	public static function unknown_keys( array $input ): array {
		return array_values( array_diff( array_map( 'strval', array_keys( $input ) ), self::FIELDS ) );
	}

	/**
	 * Trimmed string, or null for non-strings.
	 *
	 * @param mixed $value Value.
	 */
	private static function trimmed( mixed $value ): ?string {
		return is_string( $value ) ? trim( $value ) : null;
	}
}
