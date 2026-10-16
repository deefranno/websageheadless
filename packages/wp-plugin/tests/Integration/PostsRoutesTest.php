<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Fields\SeoMetabox;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

final class PostsRoutesTest extends ApiTestCase {

	private function blog_post( string $slug, string $date, array $extra = array() ): int {
		return $this->post(
			array_merge(
				array(
					'post_type'     => 'post',
					'post_title'    => ucfirst( $slug ),
					'post_name'     => $slug,
					'post_date'     => $date,
					'post_date_gmt' => $date,
					'post_excerpt'  => '',
					'post_content'  => '<!-- wp:paragraph --><p>Body of ' . $slug . '.</p><!-- /wp:paragraph -->',
				),
				$extra
			)
		);
	}

	public function test_post_list_matches_the_contract_and_paginates_newest_first(): void {
		$news = self::factory()->category->create(
			array(
				'slug' => 'news',
				'name' => 'News',
			)
		);
		$this->blog_post( 'older', '2026-01-01 10:00:00', array( 'post_category' => array( $news ) ) );
		$this->blog_post( 'newer', '2026-02-01 10:00:00' );
		$this->blog_post( 'draft', '2026-03-01 10:00:00', array( 'post_status' => 'draft' ) );
		$this->blog_post( 'locked', '2026-03-02 10:00:00', array( 'post_password' => 'x' ) );

		$first = $this->get( '/websage/v1/posts?perPage=1' )->get_data()['data'];

		$this->assertSame( self::shape( self::fixture( 'posts-list' )['data'] ), self::shape( $first ) );
		$this->assertSame( array( 'newer' ), array_column( $first['items'], 'slug' ) );
		$this->assertSame(
			array(
				'page'       => 1,
				'perPage'    => 1,
				'total'      => 2,
				'totalPages' => 2,
			),
			$first['pagination']
		);
		$this->assertContains(
			array(
				'slug' => 'news',
				'name' => 'News',
			),
			$first['categories']
		);
		$this->assertSame( '/blog/newer', $first['items'][0]['path'] );
		$this->assertSame( 'Body of newer.', $first['items'][0]['excerpt'] );

		$filtered = $this->get( '/websage/v1/posts?category=news' )->get_data()['data'];
		$this->assertSame( array( 'older' ), array_column( $filtered['items'], 'slug' ) );

		$none = $this->get( '/websage/v1/posts?category=missing' )->get_data()['data'];
		$this->assertSame( array(), $none['items'] );
		$this->assertSame( 0, $none['pagination']['totalPages'] );

		$beyond = $this->get( '/websage/v1/posts?page=9' )->get_data()['data'];
		$this->assertSame( array(), $beyond['items'] );
	}

	public function test_invalid_list_parameters_use_the_error_envelope(): void {
		foreach ( array( 'perPage=0', 'perPage=101', 'page=0', 'category=Bad%20Slug' ) as $query ) {
			$this->assert_error( $this->get( '/websage/v1/posts?' . $query ), 400 );
		}
	}

	public function test_single_post_and_hidden_posts(): void {
		$author = self::factory()->user->create( array( 'user_email' => 'writer-secret@example.org' ) );
		$this->blog_post( 'hello', '2026-01-01 10:00:00', array( 'post_author' => $author ) );
		$this->blog_post( 'secret', '2026-01-01 10:00:00', array( 'post_status' => 'private' ) );

		$response = $this->get( '/websage/v1/posts/hello' );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( self::shape( self::fixture( 'post' ) ), self::shape( $data ) );
		$this->assertSame( 'Hello | Demo', $data['data']['seo']['title'] );
		$this->assertSame( 'https://www.example.com/blog/hello', $data['data']['seo']['canonical'] );
		$this->assertStringNotContainsString( 'writer-secret', (string) wp_json_encode( $data ) );

		$this->assert_error( $this->get( '/websage/v1/posts/secret' ), 404, 'not_found' );
		$this->assert_error( $this->get( '/websage/v1/posts/missing' ), 404, 'not_found' );
	}

	public function test_routes_list_public_indexable_urls_once(): void {
		$home = $this->page(
			array(
				'post_title' => 'Home',
				'post_name'  => 'home-page',
			)
		);
		$this->set_front_page( $home );
		$about = $this->page( array( 'post_name' => 'about' ) );
		$this->page(
			array(
				'post_name'   => 'team',
				'post_parent' => $about,
			)
		);
		$this->page(
			array(
				'post_name'   => 'draft-page',
				'post_status' => 'draft',
			)
		);
		$hidden = $this->page( array( 'post_name' => 'hidden' ) );
		update_post_meta( $hidden, SeoMetabox::NOINDEX, 'on' );
		$this->page( array( 'post_name' => 'blog' ) ); // Collides with the frontend's /blog.
		$this->blog_post( 'hello', '2026-01-01 10:00:00' );

		$data = $this->get( '/websage/v1/routes' )->get_data()['data'];

		$this->assertSame( self::shape( self::fixture( 'routes' )['data'] ), self::shape( $data ) );
		$this->assertSame( array( '/', '/about', '/about/team', '/blog', '/blog/hello' ), array_column( $data['items'], 'path' ) );
		$this->assertSame( array( 'page', 'page', 'page', 'index', 'post' ), array_column( $data['items'], 'kind' ) );

		$paged = $this->get( '/websage/v1/routes?perPage=2&page=2' )->get_data()['data'];
		$this->assertSame( array( '/about/team', '/blog' ), array_column( $paged['items'], 'path' ) );
		$this->assertSame( 3, $paged['pagination']['totalPages'] );
	}

	public function test_routes_without_a_front_page_is_a_configuration_error(): void {
		update_option( 'show_on_front', 'posts' );
		$this->assert_error( $this->get( '/websage/v1/routes' ), 503, 'not_configured' );
	}
}
