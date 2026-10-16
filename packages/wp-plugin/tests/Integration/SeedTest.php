<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Seed\Seeder;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

final class SeedTest extends ApiTestCase {

	/**
	 * Published item counts by type.
	 *
	 * @return array<string, int>
	 */
	private function counts(): array {
		$counts = array();
		foreach ( array( 'page', 'post', PostTypes::TESTIMONIAL, PostTypes::CTA, 'nav_menu_item' ) as $type ) {
			$counts[ $type ] = count(
				get_posts(
					array(
						'post_type'   => $type,
						'post_status' => 'any',
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				)
			);
		}
		return $counts;
	}

	public function test_seeding_twice_creates_no_duplicates_and_keeps_other_content(): void {
		$mine = $this->page(
			array(
				'post_name'  => 'my-own-page',
				'post_title' => 'Mine',
			)
		);

		$first       = ( new Seeder() )->run();
		$after_first = $this->counts();
		$second      = ( new Seeder() )->run();

		$this->assertGreaterThan( 0, $first['created'] );
		$this->assertSame( 0, $second['created'], 'second run only updates' );
		$this->assertSame( $first['created'], $second['updated'] );
		$this->assertSame( $after_first, $this->counts() );
		$this->assertSame( 'Mine', get_post( $mine )->post_title );
		$this->assertSame(
			array(
				'page'                 => 5,
				'post'                 => 12,
				PostTypes::TESTIMONIAL => 3,
				PostTypes::CTA         => 1,
			),
			array_diff_key( $after_first, array( 'nav_menu_item' => 0 ) )
		);
	}

	public function test_seeded_site_is_served_through_the_api(): void {
		( new Seeder() )->run();

		$site = $this->get( '/websage/v1/site' )->get_data()['data'];
		$this->assertSame( 'WebSage Demo', $site['name'] );
		$this->assertSame( array( 'Home', 'About', 'Services', 'Blog', 'Contact' ), array_column( $site['menus']['primary'], 'label' ) );
		$this->assertSame( array( 'Services' ), array_column( $site['menus']['primary'][1]['children'], 'label' ) );
		$this->assertSame( '/blog', $site['menus']['primary'][3]['href'] );

		$home = $this->get( '/websage/v1/pages/home' )->get_data()['data'];
		$this->assertSame( array( 'hero', 'feature-grid', 'testimonials', 'cta' ), array_column( $home['sections'], 'type' ) );
		$this->assertCount( 3, $home['sections'][2]['items'] );

		$services = $this->get( '/websage/v1/pages/services' )->get_data()['data'];
		$this->assertSame( array( 'hero', 'text-image', 'feature-grid', 'core-content', 'cta' ), array_column( $services['sections'], 'type' ) );
		$contact = $this->get( '/websage/v1/pages/contact' )->get_data()['data'];
		$this->assertSame( array( 'hero', 'contact', 'faq' ), array_column( $contact['sections'], 'type' ) );

		$posts = $this->get( '/websage/v1/posts?perPage=9' )->get_data()['data'];
		$this->assertSame( 12, $posts['pagination']['total'] );
		$this->assertSame( 2, $posts['pagination']['totalPages'] );
		$this->assertSame( 'Our autumn availability', $posts['items'][0]['title'] );
		$this->assertEqualsCanonicalizing( array( 'news', 'guides' ), array_column( $posts['categories'], 'slug' ) );

		$routes = array_column( $this->get( '/websage/v1/routes?perPage=500' )->get_data()['data']['items'], 'path' );
		$this->assertContains( '/services', $routes );
		$this->assertContains( '/blog/our-autumn-availability', $routes );
	}
}
