<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Plugin;
use WebSage\HeadlessCore\Rest\SiteController;
use WebSage\HeadlessCore\Support\Cmb2Status;
use WP_Block_Type_Registry;
use WP_REST_Request;
use WP_UnitTestCase;

final class PluginBootstrapTest extends WP_UnitTestCase {

	public function test_activation_creates_versioned_settings_without_overwriting(): void {
		delete_option( Plugin::SETTINGS_OPTION );
		Plugin::activate();
		$this->assertSame( array( 'schema_version' => 1 ), get_option( Plugin::SETTINGS_OPTION ) );

		update_option(
			Plugin::SETTINGS_OPTION,
			array(
				'schema_version' => 1,
				'kept'           => true,
			)
		);
		Plugin::activate();
		$this->assertTrue( get_option( Plugin::SETTINGS_OPTION )['kept'] );
	}

	public function test_hero_block_is_registered_from_compiled_metadata(): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'websage/hero' );

		$this->assertNotNull( $block, 'Run the block build before integration tests.' );
		$this->assertSame( array( 'default', 'muted', 'brand' ), $block->attributes['theme']['enum'] );
		$this->assertNull( $block->render_callback, 'No PHP frontend renderer' );
	}

	public function test_site_endpoint_returns_contract_shaped_defaults(): void {
		update_option( 'blogname', 'Acme &amp; Sons' );
		$fixture = json_decode( (string) file_get_contents( __DIR__ . '/../../../contracts/fixtures/site-default.json' ), true, 512, JSON_THROW_ON_ERROR );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/websage/v1/site' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( self::key_shape( $fixture ), self::key_shape( $data ) );
		$this->assertSame( 'Acme & Sons', $data['data']['name'] );
		$this->assertSame( SiteController::DEFAULT_TOKENS, $data['data']['branding']['tokens'] );
	}

	public function test_bundled_cmb2_wins_version_negotiation_in_this_load_order(): void {
		$status = Cmb2Status::current();

		$this->assertTrue( class_exists( 'CMB2_Bootstrap_2110', false ), 'test-only legacy copy was loaded (' . WSH_TEST_CMB2_ORDER . ')' );
		$this->assertSame( '2.13.4', $status['version'] );
		$this->assertTrue( $status['bundled'], 'CMB2 loaded from ' . (string) $status['dir'] );
		$this->assertTrue( class_exists( 'CMB2', false ) );
	}

	/**
	 * Recursive key structure, ignoring list lengths.
	 *
	 * @param mixed $value Decoded JSON.
	 * @return mixed
	 */
	private static function key_shape( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return gettype( $value );
		}
		if ( array_is_list( $value ) ) {
			return 'list';
		}
		return array_map( array( self::class, 'key_shape' ), $value );
	}
}
