<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Content\References;
use WebSage\HeadlessCore\Fields\SeoMetabox;
use WebSage\HeadlessCore\Fields\TestimonialMetabox;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

/**
 * Every block type through parse_blocks() to the shared contract fixture.
 */
final class SectionsTest extends ApiTestCase {

	private function testimonial( string $quote, string $status = 'publish' ): int {
		$id = $this->post(
			array(
				'post_type'    => PostTypes::TESTIMONIAL,
				'post_status'  => $status,
				'post_title'   => 'Alex',
				'post_content' => $quote,
			)
		);
		update_post_meta( $id, TestimonialMetabox::AUTHOR, 'Alex Kim' );
		update_post_meta( $id, TestimonialMetabox::ROLE, 'Director' );
		update_post_meta( $id, TestimonialMetabox::COMPANY, 'Acme' );
		return $id;
	}

	private function cta( string $status = 'publish' ): int {
		return $this->post(
			array(
				'post_type'    => PostTypes::CTA,
				'post_status'  => $status,
				'post_title'   => 'Global CTA',
				'post_content' => self::block(
					'websage/cta',
					array(
						'heading' => 'Ready?',
						'body'    => "Let's talk.",
						'link'    => array(
							'label'  => 'Contact',
							'href'   => '/contact',
							'newTab' => false,
						),
						'theme'   => 'default',
					)
				),
			)
		);
	}

	public function test_every_section_type_matches_the_shared_fixture(): void {
		$testimonial = $this->testimonial( 'They delivered.' );
		$cta         = $this->cta();
		$content     = implode(
			'',
			array(
				self::block(
					'websage/hero',
					array(
						'heading' => 'Services',
						'theme'   => 'brand',
					)
				),
				self::block(
					'websage/text-image',
					array(
						'heading'  => 'How we work',
						'body'     => '<p>Clear plans.</p><ul><li>Discovery</li></ul>',
						'position' => 'right',
					)
				),
				self::block(
					'websage/feature-grid',
					array(
						'heading'      => 'What you get',
						'introduction' => 'Everything included.',
						'columns'      => 3,
						'theme'        => 'muted',
					),
					array(
						self::parsed(
							'websage/feature',
							array(
								'title' => 'Strategy',
								'body'  => 'A roadmap.',
								'link'  => array(
									'label'  => 'Learn more',
									'href'   => '/strategy',
									'newTab' => false,
								),
							)
						),
						self::parsed(
							'websage/feature',
							array(
								'title' => 'Design',
								'body'  => '<strong>Accessible</strong> design.',
							)
						),
					)
				),
				self::block( 'websage/testimonials', array( 'heading' => 'Clients say' ), array( self::parsed( 'websage/testimonial-reference', array( 'testimonialId' => $testimonial ) ) ) ),
				self::block(
					'websage/faq',
					array( 'heading' => 'Questions' ),
					array(
						self::parsed(
							'websage/faq-item',
							array(
								'question' => 'How long?',
								'answer'   => 'Usually <em>six weeks</em>.',
							)
						),
					)
				),
				'<!-- wp:heading --><h2 class="wp-block-heading">Pricing</h2><!-- /wp:heading -->',
				'<!-- wp:paragraph --><p>Ask us.</p><!-- /wp:paragraph -->',
				self::block(
					'websage/cta-reference',
					array(
						'ctaId' => $cta,
						'theme' => 'brand',
					)
				),
				self::block(
					'websage/contact',
					array(
						'heading'        => 'Contact us',
						'body'           => 'We reply within a day.',
						'submitLabel'    => 'Send',
						'successMessage' => "Thanks, we'll be in touch.",
						'theme'          => 'muted',
					)
				),
			)
		);
		$id          = $this->page(
			array(
				'post_title'   => 'Services',
				'post_name'    => 'services',
				'post_content' => $content,
			)
		);
		update_post_meta( $id, SeoMetabox::DESCRIPTION, 'What we do.' );

		$response = $this->get( '/websage/v1/pages/services' );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$actual   = $response->get_data();
		$expected = self::fixture( 'page-sections' );

		// The fixture's image needs a real upload; image resolution is covered elsewhere.
		$expected['data']['sections'][1]['image'] = null;
		$actual['data']['modified']               = $expected['data']['modified'];
		$this->assertSame( $expected, $actual );

		$this->assertEqualsCanonicalizing( array( "testimonial:{$testimonial}", "cta:{$cta}" ), get_post_meta( $id, References::META, false ) );
	}

	public function test_synced_patterns_expand_in_place_and_cycles_stop(): void {
		$pattern = $this->post(
			array(
				'post_type'    => 'wp_block',
				'post_title'   => 'Shared',
				'post_content' => self::block( 'websage/faq', array( 'heading' => 'Shared FAQ' ) ),
			)
		);
		// Pattern that includes itself.
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $pattern,
					'post_content' => self::block( 'websage/faq', array( 'heading' => 'Shared FAQ' ) ) . self::block( 'core/block', array( 'ref' => $pattern ) ),
				)
			)
		);
		$this->page(
			array(
				'post_name'    => 'patterned',
				'post_content' => self::block( 'websage/hero', array( 'heading' => 'Top' ) ) . self::block( 'core/block', array( 'ref' => $pattern ) ),
			)
		);

		$sections = $this->get( '/websage/v1/pages/patterned' )->get_data()['data']['sections'];

		$this->assertSame( array( 'hero', 'faq' ), array_column( $sections, 'type' ) );
		$this->assertSame( 'Shared FAQ', $sections[1]['heading'] );
	}

	public function test_unpublished_library_items_are_left_out(): void {
		$draft_cta   = $this->cta( 'draft' );
		$draft_quote = $this->testimonial( 'Secret', 'draft' );
		$live_quote  = $this->testimonial( 'Public' );
		$this->page(
			array(
				'post_name'    => 'library',
				'post_content' => self::block( 'websage/cta-reference', array( 'ctaId' => $draft_cta ) )
					. self::block(
						'websage/testimonials',
						array(),
						array(
							self::parsed( 'websage/testimonial-reference', array( 'testimonialId' => $draft_quote ) ),
							self::parsed( 'websage/testimonial-reference', array( 'testimonialId' => $live_quote ) ),
						)
					),
			)
		);

		$sections = $this->get( '/websage/v1/pages/library' )->get_data()['data']['sections'];

		$this->assertSame( array( 'testimonials' ), array_column( $sections, 'type' ) );
		$this->assertSame( array( 'Public' ), array_column( $sections[0]['items'], 'quote' ) );
	}

	public function test_cta_without_a_link_is_omitted_and_limits_cap_child_items(): void {
		$children = array_fill( 0, 15, self::parsed( 'websage/feature', array( 'title' => 'x' ) ) );
		$this->page(
			array(
				'post_name'    => 'limits',
				'post_content' => self::block( 'websage/cta', array( 'heading' => 'No link' ) )
					. self::block( 'websage/feature-grid', array( 'columns' => 7 ), $children ),
			)
		);

		$sections = $this->get( '/websage/v1/pages/limits' )->get_data()['data']['sections'];

		$this->assertSame( array( 'feature-grid' ), array_column( $sections, 'type' ) );
		$this->assertCount( 12, $sections[0]['items'] );
		$this->assertSame( 3, $sections[0]['columns'], 'unsupported column count falls back' );
	}

	public function test_core_content_is_allowlisted_and_keeps_a_single_h1(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->page(
			array(
				'post_name'    => 'core',
				'post_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Title</h1><!-- /wp:heading -->'
					. '<!-- wp:paragraph --><p style="color:red" onclick="x()">Text <script>bad()</script></p><!-- /wp:paragraph -->'
					. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
					. '<!-- wp:html --><iframe src="https://evil.example"></iframe><!-- /wp:html -->'
					. '<!-- wp:shortcode -->[gallery]<!-- /wp:shortcode -->',
			)
		);

		$sections = $this->get( '/websage/v1/pages/core' )->get_data()['data']['sections'];

		$this->assertSame(
			array(
				array(
					'type'          => 'core-content',
					'schemaVersion' => 1,
					'key'           => '0',
					'html'          => '<h2>Title</h2><p>Text </p><ul><li>One</li></ul>',
				),
			),
			$sections
		);
	}
}
