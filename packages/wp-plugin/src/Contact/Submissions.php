<?php
/**
 * Private storage of contact form messages.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Contact;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Settings\Settings;
use WP_Post;

/**
 * Stores submissions as private ws_submission posts readable only by
 * administrators, and applies the configured retention period.
 */
final class Submissions {

	public const EMAIL        = '_wsh_email';
	public const KEY          = '_wsh_idempotency_key';
	public const PAYLOAD_HASH = '_wsh_payload_hash';
	public const MAIL_STATUS  = '_wsh_mail_status';
	public const RETENTION    = 'wsh_contact_retention';

	/**
	 * Stable fingerprint of what the visitor sent.
	 *
	 * @param array{name: string, email: string, message: string} $data Valid input.
	 */
	public static function payload_hash( array $data ): string {
		return hash( 'sha256', (string) wp_json_encode( array( $data['name'], strtolower( $data['email'] ), $data['message'] ) ) );
	}

	/**
	 * Existing submission for an idempotency key.
	 *
	 * @param string $key Idempotency key.
	 */
	public static function find_by_key( string $key ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'        => PostTypes::SUBMISSION,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'meta_key'         => self::KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single indexed lookup.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);
		return $posts[0] ?? null;
	}

	/**
	 * Stores a submission; returns its ID, or 0 on failure.
	 *
	 * @param array{name: string, email: string, message: string, idempotencyKey: string} $data Valid input.
	 */
	public static function store( array $data ): int {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => PostTypes::SUBMISSION,
					'post_status'  => 'private',
					'post_title'   => mb_substr( sanitize_text_field( $data['name'] ), 0, 120 ),
					'post_content' => sanitize_textarea_field( $data['message'] ),
					'meta_input'   => array(
						self::EMAIL        => sanitize_email( $data['email'] ),
						self::KEY          => $data['idempotencyKey'],
						self::PAYLOAD_HASH => self::payload_hash( $data ),
						self::MAIL_STATUS  => 'pending',
					),
				)
			),
			true
		);
		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Deletes submissions older than the retention period.
	 */
	public static function apply_retention(): void {
		$days = max( 1, min( 365, (int) Settings::get( 'retention_days' ) ) );
		$old  = get_posts(
			array(
				'post_type'   => PostTypes::SUBMISSION,
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
				'date_query'  => array(
					array(
						'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
						'column' => 'post_date_gmt',
					),
				),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
		RateLimiter::purge();
	}
}
