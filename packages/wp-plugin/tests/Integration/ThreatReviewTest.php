<?php
/**
 * Phase 5 security review: threats from the architecture threat list that
 * earlier suites did not exercise directly.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Admin\Diagnostics;
use WebSage\HeadlessCore\Admin\SubmissionsScreen;
use WebSage\HeadlessCore\Preview\AdminLink;
use WebSage\HeadlessCore\Security\Hardening;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;
use WPDieException;
use WP_REST_Request;

final class ThreatReviewTest extends ApiTestCase {

	public function tear_down(): void {
		unset( $_GET['check'], $_GET['post'], $_GET['author'], $_REQUEST['_wpnonce'] );
		parent::tear_down();
	}

	public function test_user_listing_is_blocked_whatever_the_letter_case(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post( array( 'post_author' => $author ) );
		foreach ( array( '/wp/v2/users', '/wp/v2/USERS', '/wp/v2/Users/' . $author, '/WP/V2/users/me' ) as $route ) {
			$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status(), $route );
		}
	}

	public function test_oembed_does_not_reveal_author_names(): void {
		$author  = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Secret Author',
			)
		);
		$id      = $this->post( array( 'post_author' => $author ) );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_query_params( array( 'url' => get_permalink( $id ) ) );
		$response = rest_do_request( $request );
		$this->assertStringNotContainsString( 'Secret Author', (string) wp_json_encode( $response->get_data() ) );
	}

	public function test_author_probes_are_not_found(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post( array( 'post_author' => $author ) );
		$this->go_to( home_url( '/?author=' . $author ) );
		$_GET['author'] = (string) $author;
		// With hardening on, redirect_front_end (priority 0) first sends the probe to the website's home page.
		$this->assertSame( 'https://www.example.com/', Hardening::target_for_request() );
		// With the redirect off, the probe is a 404 (status headers cannot be sent under PHPUnit).
		@Hardening::block_author_archives(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertTrue( is_404() );
	}

	/**
	 * Admin actions need a valid nonce and the right capability.
	 */
	public function test_diagnostics_actions_need_a_nonce_and_manage_options(): void {
		$_GET['check'] = 'reachability';
		foreach ( array( 'subscriber', 'editor' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			$_REQUEST['_wpnonce'] = wp_create_nonce( Diagnostics::ACTION );
			$this->assertDies( static fn() => Diagnostics::handle(), "{$role} with a valid nonce" );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = 'forged';
		$this->assertDies( static fn() => Diagnostics::handle(), 'administrator without a valid nonce' );
	}

	public function test_preview_links_need_a_nonce_and_edit_rights_for_that_post(): void {
		$owner        = self::factory()->user->create( array( 'role' => 'author' ) );
		$id           = $this->post(
			array(
				'post_author' => $owner,
				'post_status' => 'draft',
			)
		);
		$_GET['post'] = (string) $id;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 'core-link', AdminLink::link( 'core-link', get_post( $id ) ), 'no signed link for other authors' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( AdminLink::ACTION . '_' . $id );
		$this->assertDies( static fn() => AdminLink::handle(), 'another author with a valid nonce' );

		wp_set_current_user( $owner );
		$_REQUEST['_wpnonce'] = wp_create_nonce( AdminLink::ACTION . '_' . ( $id + 1 ) );
		$this->assertDies( static fn() => AdminLink::handle(), 'owner with a nonce for a different post' );
	}

	public function test_submission_screens_escape_visitor_input(): void {
		global $wpdb;
		$id = $this->post(
			array(
				'post_type'   => 'ws_submission',
				'post_status' => 'private',
			)
		);
		// Raw values, as if stored without kses (the endpoint stores plain text).
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'   => '<script>alert(1)</script>',
				'post_content' => '<img src=x onerror=alert(2)>',
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
		update_post_meta( $id, '_wsh_email', '"><script>alert(3)</script>' );

		ob_start();
		SubmissionsScreen::render( get_post( $id ) );
		SubmissionsScreen::column( 'wsh_email', $id );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	/**
	 * Asserts the callable ends with wp_die() (as check_admin_referer() and
	 * capability failures do) instead of running the action.
	 *
	 * @param callable $action Handler.
	 * @param string   $label  Case description.
	 */
	private function assertDies( callable $action, string $label ): void {
		try {
			$action();
		} catch ( WPDieException ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		$this->fail( "Expected wp_die() for {$label}" );
	}
}
