<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Preview\AdminLink;
use WebSage\HeadlessCore\Preview\Tokens;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

final class PreviewTest extends ApiTestCase {

	private const BEARER = 'Bearer ' . WSH_SERVER_API_SECRET;

	private function draft(): int {
		return $this->page(
			array(
				'post_status'  => 'draft',
				'post_title'   => 'Unpublished',
				'post_name'    => 'draft',
				'post_content' => self::block( 'websage/hero', array( 'heading' => 'Draft heading' ) ),
			)
		);
	}

	public function test_preview_requires_the_server_secret_and_a_matching_grant(): void {
		$id    = $this->draft();
		$other = $this->draft();
		$grant = (string) Tokens::issue( $id, $id, '/draft' );

		$this->assert_error( $this->get( "/websage/v1/preview/{$id}" ), 401 );
		$this->assert_error(
			$this->get(
				"/websage/v1/preview/{$id}",
				array(
					'Authorization'       => 'Bearer wrong',
					'X-WSH-Preview-Grant' => $grant,
				)
			),
			401
		);
		$this->assert_error( $this->get( "/websage/v1/preview/{$id}", array( 'Authorization' => self::BEARER ) ), 403 );
		$this->assert_error(
			$this->get(
				"/websage/v1/preview/{$other}",
				array(
					'Authorization'       => self::BEARER,
					'X-WSH-Preview-Grant' => $grant,
				)
			),
			403
		);

		$expired = (string) Tokens::issue( $id, $id, '/draft', time() - 400 );
		$this->assert_error(
			$this->get(
				"/websage/v1/preview/{$id}",
				array(
					'Authorization'       => self::BEARER,
					'X-WSH-Preview-Grant' => $expired,
				)
			),
			403
		);

		$tampered = $grant . 'x';
		$this->assert_error(
			$this->get(
				"/websage/v1/preview/{$id}",
				array(
					'Authorization'       => self::BEARER,
					'X-WSH-Preview-Grant' => $tampered,
				)
			),
			403
		);
	}

	public function test_valid_grant_returns_unpublished_content_marked_noindex(): void {
		$id    = $this->draft();
		$grant = (string) Tokens::issue( $id, $id, '/draft' );

		$response = $this->get(
			"/websage/v1/preview/{$id}",
			array(
				'Authorization'       => self::BEARER,
				'X-WSH-Preview-Grant' => $grant,
			)
		);

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$data = $response->get_data()['data'];
		$this->assertSame(
			self::shape( self::fixture( 'preview-page' )['data'] ),
			self::shape(
				array(
					'kind'    => $data['kind'],
					'content' => array_merge( $data['content'], array( 'sections' => array() ) ),
				)
			)
		);
		$this->assertSame( 'page', $data['kind'] );
		$this->assertSame( '/draft', $data['content']['path'] );
		$this->assertSame( 'Draft heading', $data['content']['sections'][0]['heading'] );
		$this->assertTrue( $data['content']['seo']['noindex'] );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_admin_preview_targets_the_editors_latest_autosave(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$page     = $this->page(
			array(
				'post_name'    => 'about',
				'post_content' => self::block( 'websage/hero', array( 'heading' => 'Live' ) ),
			)
		);
		$autosave = wp_create_post_autosave(
			wp_slash(
				array(
					'post_ID'      => $page,
					'post_type'    => 'page',
					'post_title'   => 'About',
					'post_content' => self::block( 'websage/hero', array( 'heading' => 'Unsaved change' ) ),
				)
			)
		);
		$this->assertIsInt( $autosave );

		$url = (string) AdminLink::target( get_post( $page ), $editor );
		$this->assertStringStartsWith( 'https://www.example.com/api/preview?token=', $url );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$grant = Tokens::verify( (string) $query['token'] );
		$this->assertSame( $autosave, $grant['id'] );
		$this->assertSame( $page, $grant['parentId'] );
		$this->assertSame( '/about', $grant['path'] );

		$content = $this->get(
			"/websage/v1/preview/{$autosave}",
			array(
				'Authorization'       => self::BEARER,
				'X-WSH-Preview-Grant' => (string) $query['token'],
			)
		);
		$this->assertSame( 'Unsaved change', $content->get_data()['data']['content']['sections'][0]['heading'] );

		$this->assertStringContainsString( 'action=wsh_preview', AdminLink::link( 'https://cms/?p=1', get_post( $page ) ) );
	}
}
