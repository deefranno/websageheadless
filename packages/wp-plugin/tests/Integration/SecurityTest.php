<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Security\Cors;
use WebSage\HeadlessCore\Security\Hardening;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;
use WP_REST_Request;

final class SecurityTest extends ApiTestCase {

	public function test_cors_headers_are_withheld_on_the_namespace_only(): void {
		add_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		Cors::close( false, null, new WP_REST_Request( 'GET', '/wp/v2/posts' ) );
		$this->assertNotFalse( has_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' ) );

		Cors::close( false, null, new WP_REST_Request( 'GET', '/websage/v1/site' ) );
		$this->assertFalse( has_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' ) );
	}

	public function test_anonymous_visitors_cannot_list_users_or_library_items(): void {
		foreach ( array( '/wp/v2/users', '/wp/v2/ws_testimonial', '/wp/v2/ws_cta' ) as $route ) {
			$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status(), $route );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/ws_testimonial' ) )->get_status() );
	}

	public function test_submissions_have_no_rest_route_or_public_url(): void {
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/ws_submission' ) )->get_status() );
		$this->assertFalse( get_post_type_object( 'ws_submission' )->publicly_queryable );
	}

	public function test_xmlrpc_and_core_sitemap_are_disabled_by_default(): void {
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertFalse( apply_filters( 'wp_sitemaps_enabled', true ) );
		$this->assertTrue( apply_filters( 'wp_robots', array() )['noindex'] );
	}

	public function test_cms_front_end_requests_map_to_frontend_routes(): void {
		$id = $this->page( array( 'post_name' => 'about' ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertSame( 'https://www.example.com/about', Hardening::target_for_request() );

		$this->go_to( home_url( '/?p=999999' ) );
		$this->assertSame( 'https://www.example.com/', Hardening::target_for_request() );
	}
}
