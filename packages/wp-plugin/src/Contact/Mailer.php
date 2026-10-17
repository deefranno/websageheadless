<?php
/**
 * Notification email for new submissions.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Contact;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Content\Seo;
use WebSage\HeadlessCore\Settings\Settings;
use WP_Post;

/**
 * Sends a plain-text notification to the configured private recipient. The
 * visitor's address is in the body only, never in headers, so submissions
 * cannot inject mail headers. Failures retry after 5, 30 and 120 minutes;
 * visitors are never asked to resubmit.
 */
final class Mailer {

	public const RETRY_HOOK = 'wsh_contact_mail_retry';
	public const ATTEMPTS   = '_wsh_mail_attempts';
	public const DELAYS     = array( 300, 1800, 7200 );

	/**
	 * Cron callback for a scheduled retry.
	 *
	 * @param int $submission_id Submission post ID.
	 */
	public static function retry( int $submission_id ): void {
		self::send( $submission_id );
	}

	/**
	 * Sends (or retries) the notification for a stored submission.
	 *
	 * @param int $submission_id Submission post ID.
	 */
	public static function send( int $submission_id ): bool {
		$post = get_post( $submission_id );
		if ( ! $post instanceof WP_Post || PostTypes::SUBMISSION !== $post->post_type ) {
			return false;
		}

		$recipient = (string) Settings::get( 'notification_recipient' );
		$to        = is_email( $recipient ) ? $recipient : (string) get_option( 'admin_email' );
		$site      = Seo::site_name();
		$email     = (string) get_post_meta( $submission_id, Submissions::EMAIL, true );
		/* translators: %s: site name. */
		$subject = sprintf( __( 'New message from the %s website', 'websage-headless' ), $site );
		$body    = implode(
			"\n",
			array(
				/* translators: %s: visitor name. */
				sprintf( __( 'Name: %s', 'websage-headless' ), $post->post_title ),
				/* translators: %s: visitor email. */
				sprintf( __( 'Email: %s', 'websage-headless' ), $email ),
				'',
				$post->post_content,
				'',
				/* translators: %s: admin URL. */
				sprintf( __( 'View in WordPress: %s', 'websage-headless' ), admin_url( 'post.php?post=' . $submission_id . '&action=edit' ) ),
			)
		);

		$sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		if ( $sent ) {
			update_post_meta( $submission_id, Submissions::MAIL_STATUS, 'sent' );
			return true;
		}

		$attempts = (int) get_post_meta( $submission_id, self::ATTEMPTS, true );
		update_post_meta( $submission_id, self::ATTEMPTS, $attempts + 1 );
		$delay = self::DELAYS[ $attempts ] ?? null;
		if ( null === $delay ) {
			update_post_meta( $submission_id, Submissions::MAIL_STATUS, 'failed' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- operator diagnostics, no personal data.
			error_log( "websage-headless: notification for submission {$submission_id} failed after retries" );
			return false;
		}
		update_post_meta( $submission_id, Submissions::MAIL_STATUS, 'retrying' );
		wp_schedule_single_event( time() + $delay, self::RETRY_HOOK, array( $submission_id ) );
		return false;
	}
}
