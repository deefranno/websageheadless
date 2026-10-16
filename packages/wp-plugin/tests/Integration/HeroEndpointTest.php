<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * GET /websage/v1/pages/{slug} against real WordPress posts and parse_blocks().
 */
final class HeroEndpointTest extends WP_UnitTestCase {

	private const FIXTURE = __DIR__ . '/../../../contracts/fixtures/page-hero.json';

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Demo' );
		update_option( 'blogdescription', 'Our demonstration site.' );
	}

	/**
	 * Serialized hero block exactly as the editor stores it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private static function hero( array $attributes ): string {
		return serialize_block(
			array(
				'blockName'    => 'websage/hero',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Post arguments.
	 */
	private function page( array $args ): int {
		// wp_insert_post() expects slashed input, exactly as the editor submits it.
		return self::factory()->post->create( wp_slash( array_merge( array( 'post_type' => 'page' ), $args ) ) );
	}

	private function set_front_page( int $id ): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
	}

	/**
	 * Dispatches like WP_REST_Server::serve_request(), including rest_post_dispatch.
	 *
	 * @param string $route REST route.
	 */
	private function get( string $route ): WP_REST_Response {
		$request  = new WP_REST_Request( 'GET', $route );
		$response = rest_ensure_response( rest_do_request( $request ) );
		return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function fixture(): array {
		return json_decode( (string) file_get_contents( self::FIXTURE ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function assert_error( WP_REST_Response $response, int $status, string $code ): void {
		$this->assertSame( $status, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( array( 'code', 'message', 'requestId' ), array_keys( $data['error'] ) );
		$this->assertSame( $code, $data['error']['code'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $data['error']['requestId'] );
	}

	public function test_published_front_page_matches_the_shared_contract_fixture(): void {
		$expected = self::fixture();
		$section  = $expected['data']['sections'][0];
		unset( $section['type'], $section['key'], $section['image'] );
		$id = $this->page(
			array(
				'post_title'   => 'Home',
				'post_content' => self::hero( $section ),
			)
		);
		$this->set_front_page( $id );

		$response = $this->get( '/websage/v1/pages/home' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$data = $response->get_data();
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $data['data']['modified'] );
		$data['data']['modified'] = $expected['data']['modified'];
		// Same keys, order and values as the TypeScript contract fixture.
		$this->assertSame( $expected, $data );
	}

	public function test_response_carries_no_author_or_internal_data(): void {
		$author = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'secret-author@example.org',
			)
		);
		$this->page(
			array(
				'post_name'    => 'about',
				'post_author'  => $author,
				'post_content' => self::hero( array( 'heading' => 'About' ) ),
			)
		);

		$json = (string) wp_json_encode( $this->get( '/websage/v1/pages/about' )->get_data() );

		$this->assertStringNotContainsString( 'secret-author', $json );
		$this->assertStringNotContainsString( '"author', $json );
		$this->assertStringNotContainsString( '"id"', $json );
	}

	public function test_nested_paths_resolve(): void {
		$parent = $this->page( array( 'post_name' => 'about' ) );
		$this->page(
			array(
				'post_name'   => 'team',
				'post_parent' => $parent,
			)
		);

		$response = $this->get( '/websage/v1/pages/about/team' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '/about/team', $response->get_data()['data']['path'] );
		$this->assertSame( 'https://www.example.com/about/team', $response->get_data()['data']['seo']['canonical'] );
	}

	/**
	 * @dataProvider hidden_pages
	 *
	 * @param array<string, mixed> $args Post arguments.
	 */
	public function test_unpublished_and_protected_pages_are_indistinguishable_from_absent( array $args ): void {
		$this->page( array_merge( array( 'post_name' => 'hidden' ), $args ) );

		$this->assert_error( $this->get( '/websage/v1/pages/hidden' ), 404, 'not_found' );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function hidden_pages(): array {
		return array(
			'draft'    => array( array( 'post_status' => 'draft' ) ),
			'pending'  => array( array( 'post_status' => 'pending' ) ),
			'private'  => array( array( 'post_status' => 'private' ) ),
			'future'   => array(
				array(
					'post_status' => 'future',
					'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				),
			),
			'password' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	public function test_child_of_unpublished_parent_is_hidden(): void {
		$parent = $this->page(
			array(
				'post_name'   => 'internal',
				'post_status' => 'private',
			)
		);
		$this->page(
			array(
				'post_name'   => 'child',
				'post_parent' => $parent,
			)
		);

		$this->assert_error( $this->get( '/websage/v1/pages/internal/child' ), 404, 'not_found' );
	}

	public function test_absent_reserved_and_malformed_paths_are_404(): void {
		$this->page( array( 'post_name' => 'blog' ) );

		foreach ( array( 'missing', 'blog', '..%2fwp-config', '%252e%252e/x', 'a//b' ) as $slug ) {
			$this->assert_error( $this->get( '/websage/v1/pages/' . $slug ), 404, 'not_found' );
		}
	}

	public function test_missing_front_page_is_a_configuration_error_not_a_404(): void {
		update_option( 'show_on_front', 'posts' );
		$this->assert_error( $this->get( '/websage/v1/pages/home' ), 503, 'not_configured' );

		$this->set_front_page( $this->page( array( 'post_status' => 'draft' ) ) );
		$this->assert_error( $this->get( '/websage/v1/pages/home' ), 503, 'not_configured' );
	}

	public function test_unedited_legacy_hero_is_migrated_on_read(): void {
		$this->page(
			array(
				'post_name'    => 'legacy',
				'post_content' => self::hero(
					array(
						'schemaVersion' => 0,
						'title'         => 'Legacy',
					)
				),
			)
		);

		$section = $this->get( '/websage/v1/pages/legacy' )->get_data()['data']['sections'][0];

		$this->assertSame( 1, $section['schemaVersion'] );
		$this->assertSame( 'Legacy', $section['heading'] );
		$this->assertArrayNotHasKey( 'title', $section );
	}

	public function test_future_hero_version_fails_safely(): void {
		$this->page(
			array(
				'post_name'    => 'future-hero',
				'post_content' => self::hero( array( 'schemaVersion' => 99 ) ),
			)
		);

		$this->assert_error( $this->get( '/websage/v1/pages/future-hero' ), 500, 'content_invalid' );
	}

	public function test_content_limits_return_a_safe_413(): void {
		$cases = array(
			'long-body' => self::hero( array( 'body' => str_repeat( 'a', 20001 ) ) ),
			'long-head' => self::hero( array( 'heading' => str_repeat( 'a', 501 ) ) ),
			'too-many'  => str_repeat( self::hero( array( 'heading' => 'x' ) ), 101 ),
			'too-deep'  => str_repeat( '<!-- wp:group -->', 9 ) . str_repeat( '<!-- /wp:group -->', 9 ),
		);
		foreach ( $cases as $slug => $content ) {
			$this->page(
				array(
					'post_name'    => $slug,
					'post_content' => $content,
				)
			);
			$response = $this->get( '/websage/v1/pages/' . $slug );
			$this->assert_error( $response, 413, 'content_too_large' );
			$this->assertStringNotContainsString( 'characters', (string) wp_json_encode( $response->get_data() ), 'diagnostics stay in logs' );
		}
	}

	public function test_unknown_blocks_expose_only_the_section_envelope(): void {
		$this->page(
			array(
				'post_name'    => 'mixed',
				'post_content' => '<!-- wp:websage/future {"schemaVersion":2,"secret":"x"} /--><!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame(
			array(
				array(
					'type'          => 'future',
					'schemaVersion' => 2,
					'key'           => '0',
				),
				array(
					'type'          => 'core-content',
					'schemaVersion' => 1,
					'key'           => '1',
				),
			),
			$this->get( '/websage/v1/pages/mixed' )->get_data()['data']['sections']
		);
	}

	public function test_untrusted_values_are_sanitized_to_contract_shapes(): void {
		// An administrator has unfiltered_html, so save-time kses is skipped and
		// only the serializer stands between stored attributes and the API.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->page(
			array(
				'post_name'    => 'hostile',
				'post_content' => self::hero(
					array(
						'heading'   => '<script>x</script>Plain',
						'body'      => '<p onclick="x()">Hi <script>bad()</script><a href="javascript:alert(1)">link</a></p>',
						'primary'   => array(
							'label' => 'Go',
							'href'  => 'javascript:alert(1)',
						),
						'secondary' => array(
							'label'  => 'About',
							'href'   => home_url( '/about/?x=1' ),
							'newTab' => true,
						),
						'imageId'   => 999999,
						'theme'     => 'url(evil)',
					)
				),
			)
		);

		$section = $this->get( '/websage/v1/pages/hostile' )->get_data()['data']['sections'][0];

		$this->assertSame( 'Plain', $section['heading'] );
		$this->assertStringNotContainsString( 'script', $section['body'] );
		$this->assertStringNotContainsString( 'onclick', $section['body'] );
		$this->assertStringNotContainsString( 'javascript', $section['body'] );
		$this->assertNull( $section['primary'] );
		$this->assertSame(
			array(
				'label'  => 'About',
				'href'   => '/about/?x=1',
				'newTab' => true,
			),
			$section['secondary'],
			'CMS-origin links become frontend paths'
		);
		$this->assertNull( $section['image'] );
		$this->assertSame( 'default', $section['theme'] );
	}

	public function test_core_rest_errors_in_namespace_use_the_envelope(): void {
		$response = $this->get( '/websage/v1/does-not-exist' );

		$this->assert_error( $response, 404, 'rest_no_route' );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}
}
