<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Contact\Mailer;
use WebSage\HeadlessCore\Contact\Submissions;
use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;
use WP_REST_Request;
use WP_REST_Response;

final class ContactTest extends ApiTestCase {

	private string $client;

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();
		// A fresh client per test keeps rate-limit buckets independent.
		$this->client = hash( 'sha256', uniqid( 'client', true ) );
	}

	/**
	 * @param array<string, mixed> $body    JSON body.
	 * @param array<string, string> $headers Headers (defaults: valid secret and client hash).
	 */
	private function submit( array $body, ?array $headers = null ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/websage/v1/contact' );
		$request->set_header( 'content-type', 'application/json' );
		foreach ( $headers ?? array(
			'Authorization'     => 'Bearer ' . WSH_SERVER_API_SECRET,
			'X-WSH-Client-Hash' => $this->client,
		) as $name => $value ) {
			$request->set_header( $name, $value );
		}
		$request->set_body( (string) wp_json_encode( $body ) );
		$response = rest_ensure_response( rest_do_request( $request ) );
		return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
	}

	/**
	 * @param array<string, mixed> $change Overrides.
	 * @return array<string, mixed>
	 */
	private function valid( array $change = array() ): array {
		return array_merge( self::fixture( 'contact-input' ), array( 'idempotencyKey' => wp_generate_uuid4() ), $change );
	}

	private function count_submissions(): int {
		return count(
			get_posts(
				array(
					'post_type'   => PostTypes::SUBMISSION,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	public function test_a_valid_submission_is_stored_privately_and_emailed(): void {
		update_option(
			Settings::OPTION,
			array(
				'schema_version'         => 1,
				'notification_recipient' => 'owner@example.com',
			)
		);

		$response = $this->submit( $this->valid( array( 'message' => "Line one\nLine two" ) ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( 'data' => array( 'accepted' => true ) ), $response->get_data() );
		$posts = get_posts(
			array(
				'post_type'   => PostTypes::SUBMISSION,
				'post_status' => 'any',
			)
		);
		$this->assertCount( 1, $posts );
		$this->assertSame( 'private', $posts[0]->post_status );
		$this->assertSame( "Line one\nLine two", $posts[0]->post_content );
		$this->assertSame( 'visitor@example.com', get_post_meta( $posts[0]->ID, Submissions::EMAIL, true ) );
		$this->assertSame( 'sent', get_post_meta( $posts[0]->ID, Submissions::MAIL_STATUS, true ) );

		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'owner@example.com', $mail->to[0][0] );
		$this->assertStringContainsString( 'visitor@example.com', $mail->body );
		$this->assertStringNotContainsString( 'visitor@example.com', $mail->header, 'visitor address never in headers' );
	}

	public function test_requests_need_the_server_secret_and_a_client_hash(): void {
		$this->assert_error( $this->submit( $this->valid(), array( 'X-WSH-Client-Hash' => $this->client ) ), 401 );
		$this->assert_error(
			$this->submit(
				$this->valid(),
				array(
					'Authorization'     => 'Bearer wrong',
					'X-WSH-Client-Hash' => $this->client,
				)
			),
			401
		);
		$this->assert_error(
			$this->submit(
				$this->valid(),
				array(
					'Authorization'     => 'Bearer ' . WSH_SERVER_API_SECRET,
					'X-WSH-Client-Hash' => '127.0.0.1',
				)
			),
			400,
			'invalid_client_hash'
		);
		$this->assertSame( 0, $this->count_submissions() );
	}

	public function test_oversized_and_unknown_fields_are_refused(): void {
		$this->assert_error( $this->submit( $this->valid( array( 'message' => str_repeat( 'x', 17000 ) ) ) ), 413, 'too_large' );
		$this->assert_error( $this->submit( $this->valid( array( 'cc' => 'victim@example.com' ) ) ), 400, 'invalid_request' );
	}

	public function test_validation_errors_use_the_contract_field_codes(): void {
		$response = $this->submit(
			$this->valid(
				array(
					'email'   => 'nope',
					'consent' => false,
					'name'    => "Eve\r\nBcc: x@example.com",
				)
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$error = $response->get_data()['error'];
		$this->assertSame( array_keys( self::fixture( 'contact-validation-error' )['error'] ), array_keys( $error ) );
		$this->assertSame(
			array(
				'name'    => 'invalid',
				'email'   => 'invalid',
				'consent' => 'required',
			),
			$error['fields']
		);
		$this->assertSame( 0, $this->count_submissions() );
	}

	public function test_retries_are_idempotent_and_conflicting_reuse_is_rejected(): void {
		$body = $this->valid();
		$this->assertSame( 201, $this->submit( $body )->get_status() );
		$this->assertSame( 201, $this->submit( $body )->get_status() );
		$this->assertSame( 1, $this->count_submissions() );
		$this->assertCount( 1, array_filter( array( tests_retrieve_phpmailer_instance()->get_sent( 0 ), tests_retrieve_phpmailer_instance()->get_sent( 1 ) ) ) );

		$this->assert_error( $this->submit( array_merge( $body, array( 'message' => 'Different' ) ) ), 409, 'conflict' );
	}

	public function test_sixth_attempt_in_fifteen_minutes_is_rate_limited(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( 201, $this->submit( $this->valid() )->get_status(), "attempt {$i}" );
		}
		$response = $this->submit( $this->valid() );

		$this->assert_error( $response, 429, 'rate_limited' );
		$retry = (int) $response->get_headers()['Retry-After'];
		$this->assertGreaterThan( 0, $retry );
		$this->assertLessThanOrEqual( 900, $retry );
		$this->assertSame( 5, $this->count_submissions() );

		$this->client = hash( 'sha256', 'someone else' );
		$this->assertSame( 201, $this->submit( $this->valid() )->get_status(), 'other clients are unaffected' );
	}

	public function test_honeypot_submissions_look_accepted_but_are_dropped(): void {
		$this->assertSame( 201, $this->submit( $this->valid( array( 'website' => 'http://spam.example' ) ) )->get_status() );
		$this->assertSame( 0, $this->count_submissions() );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_mail_failure_keeps_the_submission_and_schedules_a_retry(): void {
		add_filter( 'pre_wp_mail', '__return_false' );
		$this->assertSame( 201, $this->submit( $this->valid() )->get_status() );
		$id = get_posts(
			array(
				'post_type'   => PostTypes::SUBMISSION,
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		)[0];

		$this->assertSame( 'retrying', get_post_meta( $id, Submissions::MAIL_STATUS, true ) );
		$this->assertNotFalse( wp_next_scheduled( Mailer::RETRY_HOOK, array( $id ) ) );

		remove_filter( 'pre_wp_mail', '__return_false' );
		Mailer::retry( $id );
		$this->assertSame( 'sent', get_post_meta( $id, Submissions::MAIL_STATUS, true ) );
	}

	public function test_submissions_are_never_public_and_retention_deletes_old_ones(): void {
		$this->submit( $this->valid() );
		$old = self::factory()->post->create(
			array(
				'post_type'     => PostTypes::SUBMISSION,
				'post_status'   => 'private',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ),
			)
		);

		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/ws_submission' ) )->get_status() );
		$this->assertFalse( current_user_can( 'read_post', $old ) );

		Submissions::apply_retention();
		$this->assertNull( get_post( $old ) );
		$this->assertSame( 1, $this->count_submissions() );
	}
}
