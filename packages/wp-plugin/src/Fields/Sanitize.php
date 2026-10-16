<?php
/**
 * Explicit CMB2 sanitization and escaping callbacks.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Fields;

use CMB2_Field;

/**
 * Every CMB2 field names one of these; none relies on CMB2 defaults.
 *
 * Validators reject invalid input by keeping the previously stored value
 * and reporting why, instead of silently saving a converted value.
 */
final class Sanitize {

	public const NOTICE_TRANSIENT = 'wsh_settings_notices';

	/**
	 * Single-line plain text.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function text( mixed $value ): string {
		return mb_substr( sanitize_text_field( is_string( $value ) ? $value : '' ), 0, 500 );
	}

	/**
	 * Multi-line plain text.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function textarea( mixed $value ): string {
		return mb_substr( sanitize_textarea_field( is_string( $value ) ? $value : '' ), 0, 500 );
	}

	/**
	 * Email address, or empty. Invalid input keeps the stored value.
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function email( mixed $value, mixed $args, CMB2_Field $field ): string {
		$value = trim( is_string( $value ) ? $value : '' );
		if ( '' === $value ) {
			return '';
		}
		$email = sanitize_email( $value );
		if ( ! is_email( $email ) || strlen( $email ) > 254 ) {
			return self::reject( $field, __( 'is not a valid email address', 'websage-headless' ) );
		}
		return $email;
	}

	/**
	 * Six-digit hex colour. Invalid input keeps the stored value.
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function color( mixed $value, mixed $args, CMB2_Field $field ): string {
		$value = is_string( $value ) ? strtoupper( trim( $value ) ) : '';
		if ( 1 !== preg_match( '/^#[0-9A-F]{6}$/', $value ) ) {
			return self::reject( $field, __( 'must be a six-digit hex colour such as #174F46', 'websage-headless' ) );
		}
		return $value;
	}

	/**
	 * A frontend link: a path starting with one slash, or an HTTPS URL.
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function link( mixed $value, mixed $args, CMB2_Field $field ): string {
		$value = trim( is_string( $value ) ? $value : '' );
		if ( '' === $value ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		$ok    = str_starts_with( $value, '/' )
			? ! str_starts_with( $value, '//' ) && 1 !== preg_match( '/[\\\\\s]/', $value )
			: is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] );
		if ( ! $ok ) {
			return self::reject( $field, __( 'must be a path such as /contact or an https:// address', 'websage-headless' ) );
		}
		return esc_url_raw( $value, array( 'https' ) ) ? $value : self::reject( $field, __( 'is not a valid address', 'websage-headless' ) );
	}

	/**
	 * Bare HTTPS origin (scheme, host, optional port).
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function https_origin( mixed $value, mixed $args, CMB2_Field $field ): string {
		$value = rtrim( trim( is_string( $value ) ? $value : '' ), '/' );
		if ( '' === $value ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		$ok    = is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] )
			&& array() === array_intersect_key( $parts, array_flip( array( 'user', 'pass', 'path', 'query', 'fragment' ) ) );
		return $ok ? $value : self::reject( $field, __( 'must be an https:// origin without a path, such as https://www.example.com', 'websage-headless' ) );
	}

	/**
	 * Allowlisted choice. Invalid input keeps the stored value.
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments (uses `options`).
	 * @param CMB2_Field $field Field.
	 */
	public static function choice( mixed $value, mixed $args, CMB2_Field $field ): string {
		$options = (array) $field->args( 'options' );
		return is_string( $value ) && array_key_exists( $value, $options ) ? $value : self::reject( $field, __( 'is not an allowed choice', 'websage-headless' ) );
	}

	/**
	 * Integer within the field's `attributes` min/max.
	 *
	 * @param mixed      $value Submitted value.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function bounded_int( mixed $value, mixed $args, CMB2_Field $field ): string {
		$attributes = (array) $field->args( 'attributes' );
		$min        = (int) ( $attributes['min'] ?? 0 );
		$max        = (int) ( $attributes['max'] ?? PHP_INT_MAX );
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d+$/', $value ) || (int) $value < $min || (int) $value > $max ) {
			/* translators: 1: minimum, 2: maximum. */
			return self::reject( $field, sprintf( __( 'must be a whole number from %1$d to %2$d', 'websage-headless' ), $min, $max ) );
		}
		return (string) (int) $value;
	}

	/**
	 * Checkbox: "on" or empty.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function checkbox( mixed $value ): string {
		return 'on' === $value ? 'on' : '';
	}

	/**
	 * Image attachment URL from a CMB2 file field; CMB2 stores the ID in
	 * `{id}_id`, which is what the plugin reads. Non-images are refused.
	 *
	 * @param mixed      $value Submitted URL.
	 * @param mixed      $args  Field arguments.
	 * @param CMB2_Field $field Field.
	 */
	public static function image( mixed $value, mixed $args, CMB2_Field $field ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$id = attachment_url_to_postid( $value );
		if ( $id < 1 || ! wp_attachment_is_image( $id ) ) {
			return self::reject( $field, __( 'must be an image from the media library', 'websage-headless' ) );
		}
		return esc_url_raw( $value );
	}

	/**
	 * Escaping for single-line values in admin inputs.
	 *
	 * @param mixed $value Stored value.
	 */
	public static function esc_attr( mixed $value ): string {
		return esc_attr( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Escaping for textarea values.
	 *
	 * @param mixed $value Stored value.
	 */
	public static function esc_textarea( mixed $value ): string {
		return esc_textarea( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Escaping for URL inputs.
	 *
	 * @param mixed $value Stored value.
	 */
	public static function esc_url( mixed $value ): string {
		return esc_url( is_string( $value ) ? $value : '' );
	}

	/**
	 * Keeps the stored value and queues a notice for the next admin screen.
	 *
	 * @param CMB2_Field $field  Field.
	 * @param string     $reason Translated reason fragment.
	 */
	public static function reject( CMB2_Field $field, string $reason ): string {
		$notices   = get_transient( self::NOTICE_TRANSIENT );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = sprintf( '%s %s.', (string) $field->args( 'name' ), $reason );
		set_transient( self::NOTICE_TRANSIENT, $notices, MINUTE_IN_SECONDS );

		$previous = $field->value();
		return is_scalar( $previous ) ? (string) $previous : '';
	}
}
