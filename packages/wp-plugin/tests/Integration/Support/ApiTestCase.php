<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration\Support;

use WebSage\HeadlessCore\Revalidation\Dispatcher;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Helpers shared by endpoint tests.
 */
abstract class ApiTestCase extends WP_UnitTestCase {

	public const FIXTURES = __DIR__ . '/../../../../contracts/fixtures/';

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Demo' );
		update_option( 'blogdescription', '' );
		Dispatcher::discard();
	}

	public function tear_down(): void {
		Dispatcher::discard();
		parent::tear_down();
	}

	/**
	 * Serialized block as the editor stores it.
	 *
	 * @param string                           $name     Block name.
	 * @param array<string, mixed>             $attrs    Attributes.
	 * @param list<array<string, mixed>>       $children Inner blocks (parsed shape).
	 */
	public static function block( string $name, array $attrs = array(), array $children = array() ): string {
		return serialize_block( self::parsed( $name, $attrs, $children ) );
	}

	/**
	 * Parsed-block shape for nesting.
	 *
	 * @param string                     $name     Block name.
	 * @param array<string, mixed>       $attrs    Attributes.
	 * @param list<array<string, mixed>> $children Inner blocks.
	 * @return array<string, mixed>
	 */
	public static function parsed( string $name, array $attrs = array(), array $children = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $children ), null ),
		);
	}

	/**
	 * Creates a post (slashed, as the editor submits).
	 *
	 * @param array<string, mixed> $args Post arguments.
	 */
	protected function post( array $args ): int {
		return self::factory()->post->create( wp_slash( $args ) );
	}

	/**
	 * Creates a published page.
	 *
	 * @param array<string, mixed> $args Post arguments.
	 */
	protected function page( array $args ): int {
		return $this->post( array_merge( array( 'post_type' => 'page' ), $args ) );
	}

	protected function set_front_page( int $id ): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
	}

	/**
	 * Dispatches like WP_REST_Server::serve_request(), including rest_post_dispatch.
	 *
	 * @param string                $route   Route with optional query string.
	 * @param array<string, string> $headers Request headers.
	 */
	protected function get( string $route, array $headers = array() ): WP_REST_Response {
		$parts   = explode( '?', $route, 2 );
		$request = new WP_REST_Request( 'GET', $parts[0] );
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
			$request->set_query_params( $query );
		}
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		$response = rest_ensure_response( rest_do_request( $request ) );
		return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected static function fixture( string $name ): array {
		return json_decode( (string) file_get_contents( self::FIXTURES . $name . '.json' ), true, 512, JSON_THROW_ON_ERROR );
	}

	protected function assert_error( WP_REST_Response $response, int $status, ?string $code = null ): void {
		$this->assertSame( $status, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( array( 'code', 'message', 'requestId' ), array_keys( $data['error'] ) );
		if ( null !== $code ) {
			$this->assertSame( $code, $data['error']['code'] );
		}
	}

	/**
	 * Recursive key structure, ignoring list lengths and scalar values.
	 *
	 * @param mixed $value Decoded JSON.
	 * @return mixed
	 */
	protected static function shape( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return null === $value ? 'null' : 'scalar';
		}
		if ( array_is_list( $value ) ) {
			return array() === $value ? 'list' : array( self::shape( $value[0] ) );
		}
		return array_map( array( self::class, 'shape' ), $value );
	}
}
