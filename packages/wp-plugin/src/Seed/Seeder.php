<?php
/**
 * Demo content.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Seed;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Fields\SeoMetabox;
use WebSage\HeadlessCore\Fields\TestimonialMetabox;
use WebSage\HeadlessCore\Settings\Settings;
use WP_Post;
use WP_Term;

/**
 * Creates or updates the demonstration site. Every item carries a stable
 * seed ID in `_wsh_seed_id`, so running the seeder again updates the same
 * items instead of creating duplicates. Content not created by the seeder is
 * never changed or deleted.
 */
final class Seeder {

	public const META = '_wsh_seed_id';

	/**
	 * Counts of created and updated items.
	 *
	 * @var array{created: int, updated: int}
	 */
	private array $stats = array(
		'created' => 0,
		'updated' => 0,
	);

	/**
	 * Seeds everything.
	 *
	 * @return array{created: int, updated: int}
	 */
	public function run(): array {
		$categories   = array(
			'news'   => $this->term( 'News', 'news' ),
			'guides' => $this->term( 'Guides', 'guides' ),
		);
		$testimonials = $this->testimonials();
		$cta          = $this->cta();
		$pages        = $this->pages( $testimonials, $cta );
		$this->posts( $categories );
		$this->menus( $pages );
		$this->settings();

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pages['home'] );
		update_option( 'blogdescription', 'Independent design and development for growing businesses.' );

		return $this->stats;
	}

	/**
	 * Serialized block.
	 *
	 * @param string                     $name     Block name.
	 * @param array<string, mixed>       $attrs    Attributes.
	 * @param list<array<string, mixed>> $children Parsed child blocks.
	 */
	private static function block( string $name, array $attrs = array(), array $children = array() ): string {
		return serialize_block( self::parsed( $name, $attrs, $children ) );
	}

	/**
	 * Parsed block shape.
	 *
	 * @param string                     $name     Block name.
	 * @param array<string, mixed>       $attrs    Attributes.
	 * @param list<array<string, mixed>> $children Parsed child blocks.
	 * @return array<string, mixed>
	 */
	private static function parsed( string $name, array $attrs = array(), array $children = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $children ), null ),
		);
	}

	/**
	 * Link attribute.
	 *
	 * @param string $label Label.
	 * @param string $href  Path or HTTPS URL.
	 * @return array{label: string, href: string, newTab: bool}
	 */
	private static function link( string $label, string $href ): array {
		return array(
			'label'  => $label,
			'href'   => $href,
			'newTab' => false,
		);
	}

	/**
	 * Creates or updates a post identified by its seed ID.
	 *
	 * @param string               $seed_id Stable seed ID.
	 * @param array<string, mixed> $args    wp_insert_post arguments.
	 */
	private function upsert( string $seed_id, array $args ): int {
		$existing = get_posts(
			array(
				'post_type'        => $args['post_type'],
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off seeding.
				'meta_value'       => $seed_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);
		$args     = array_merge( array( 'post_status' => 'publish' ), $args );
		if ( isset( $existing[0] ) ) {
			$args['ID'] = (int) $existing[0];
			wp_update_post( wp_slash( $args ) );
			++$this->stats['updated'];
			return (int) $existing[0];
		}
		$id = (int) wp_insert_post( wp_slash( $args ) );
		update_post_meta( $id, self::META, $seed_id );
		++$this->stats['created'];
		return $id;
	}

	/**
	 * Category by slug, created when missing.
	 *
	 * @param string $name Name.
	 * @param string $slug Slug.
	 */
	private function term( string $name, string $slug ): int {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term instanceof WP_Term ) {
			return $term->term_id;
		}
		$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		return is_array( $created ) ? (int) $created['term_id'] : 0;
	}

	/**
	 * Testimonial library.
	 *
	 * @return list<int>
	 */
	private function testimonials(): array {
		$people = array(
			array( 'Working with them felt like adding a senior team overnight. Our new site launched on time and our enquiries doubled.', 'Alex Kim', 'Managing Director', 'Northwind Studio' ),
			array( 'They explained every decision in plain language and handed over a site our team can update without calling a developer.', 'Priya Shah', 'Marketing Lead', 'Brightside Clinics' ),
			array( 'Fast, accessible and easy to edit. Exactly what we asked for, and the support since launch has been excellent.', 'Tomás Rivera', 'Founder', 'Rivera & Co.' ),
		);
		$ids    = array();
		foreach ( $people as $index => [ $quote, $author, $role, $company ] ) {
			$id = $this->upsert(
				"testimonial:{$index}",
				array(
					'post_type'    => PostTypes::TESTIMONIAL,
					'post_title'   => $author,
					'post_content' => $quote,
				)
			);
			update_post_meta( $id, TestimonialMetabox::AUTHOR, $author );
			update_post_meta( $id, TestimonialMetabox::ROLE, $role );
			update_post_meta( $id, TestimonialMetabox::COMPANY, $company );
			$ids[] = $id;
		}
		return $ids;
	}

	/**
	 * Global call to action.
	 */
	private function cta(): int {
		return $this->upsert(
			'cta:project',
			array(
				'post_type'    => PostTypes::CTA,
				'post_title'   => 'Start a project',
				'post_content' => self::block(
					'websage/cta',
					array(
						'heading' => 'Ready to start your project?',
						'body'    => 'Tell us what you need. We reply within one working day.',
						'link'    => self::link( 'Get in touch', '/contact' ),
						'theme'   => 'brand',
					)
				),
			)
		);
	}

	/**
	 * Demo pages.
	 *
	 * @param int[] $testimonials Testimonial IDs.
	 * @param int   $cta          CTA ID.
	 * @return array{home: int, about: int, services: int, contact: int}
	 */
	private function pages( array $testimonials, int $cta ): array {
		$features       = array(
			array( 'Strategy', 'A clear plan built around your goals, your audience and your budget.', '/services', 'Our strategy service' ),
			array( 'Design', '<strong>Accessible</strong>, on-brand design that works on every screen.', '/services', 'Our design service' ),
			array( 'Development', 'Fast, secure websites your team can update in WordPress.', '/services', 'Our development service' ),
		);
		$feature_blocks = array_map(
			static fn( array $f ) => self::parsed(
				'websage/feature',
				array(
					'title' => $f[0],
					'body'  => $f[1],
					// Descriptive link text: identical "Learn more" links are ambiguous out of context.
					'link'  => self::link( $f[3], $f[2] ),
				)
			),
			$features
		);
		$faq            = self::block(
			'websage/faq',
			array( 'heading' => 'Frequently asked questions' ),
			array(
				self::parsed(
					'websage/faq-item',
					array(
						'question' => 'How long does a project take?',
						'answer'   => 'Most websites launch in <em>six to ten weeks</em>, depending on content and scope.',
					)
				),
				self::parsed(
					'websage/faq-item',
					array(
						'question' => 'Can we update the site ourselves?',
						'answer'   => 'Yes. Every page is edited in WordPress, and changes appear on the website within seconds.',
					)
				),
				self::parsed(
					'websage/faq-item',
					array(
						'question' => 'Do you offer support after launch?',
						'answer'   => 'We offer monthly care plans covering updates, monitoring and small improvements.',
					)
				),
			)
		);

		$pages = array(
			'home'     => array(
				'Home',
				'home',
				self::block(
					'websage/hero',
					array(
						'eyebrow'   => 'WebSage Demo',
						'heading'   => 'Websites that grow with your business',
						'body'      => 'Independent design and development for teams who want a fast, accessible site they can manage themselves.',
						'primary'   => self::link( 'Start a project', '/contact' ),
						'secondary' => self::link( 'Our services', '/services' ),
						'theme'     => 'brand',
					)
				) . self::block(
					'websage/feature-grid',
					array(
						'heading'      => 'What we do',
						'introduction' => 'Everything you need to launch and run a modern website.',
						'columns'      => 3,
					),
					$feature_blocks
				) . self::block(
					'websage/testimonials',
					array(
						'heading' => 'What clients say',
						'theme'   => 'muted',
					),
					array_values( array_map( static fn( int $id ) => self::parsed( 'websage/testimonial-reference', array( 'testimonialId' => $id ) ), $testimonials ) )
				) . self::block( 'websage/cta-reference', array( 'ctaId' => $cta ) ),
				'Independent design and development for growing businesses.',
			),
			'about'    => array(
				'About',
				'about',
				self::block(
					'websage/hero',
					array(
						'heading' => 'About us',
						'body'    => 'A small, senior team that has shipped websites for clinics, studios and service businesses since 2014.',
						'theme'   => 'muted',
					)
				) . self::block(
					'websage/text-image',
					array(
						'heading'  => 'How we work',
						'body'     => '<p>We start with your goals, not a template. Every project includes:</p><ul><li>A discovery workshop</li><li>Accessible, responsive design</li><li>Training for your team</li></ul>',
						'position' => 'right',
					)
				) . $faq,
				'Meet the team behind WebSage Demo.',
			),
			'services' => array(
				'Services',
				'services',
				self::block(
					'websage/hero',
					array(
						'heading' => 'Services',
						'body'    => 'From first sketch to launch day and beyond.',
					)
				) . self::block(
					'websage/text-image',
					array(
						'heading'  => 'Built for your team',
						'body'     => '<p>Your content team edits pages visually in WordPress, while visitors get a fast, secure Next.js website.</p>',
						'position' => 'left',
						'theme'    => 'muted',
					)
				) . self::block(
					'websage/feature-grid',
					array(
						'heading' => 'Packages',
						'columns' => 3,
					),
					$feature_blocks
				) . '<!-- wp:heading --><h2 class="wp-block-heading">Pricing</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Projects start at a fixed fee agreed after discovery. <a href="/contact">Ask us for a quote</a>.</p><!-- /wp:paragraph -->'
				. self::block( 'websage/cta-reference', array( 'ctaId' => $cta ) ),
				'Strategy, design and development services.',
			),
			'contact'  => array(
				'Contact',
				'contact',
				self::block(
					'websage/hero',
					array(
						'heading' => 'Contact us',
						'body'    => 'Questions, quotes or a quick hello: we reply within one working day.',
					)
				) . self::block(
					'websage/contact',
					array(
						'heading'        => 'Send us a message',
						'body'           => 'Use the form below and we will get back to you.',
						'submitLabel'    => 'Send message',
						'successMessage' => 'Thank you. Your message has been sent and we will reply soon.',
						'theme'          => 'muted',
					)
				) . $faq,
				'Get in touch with WebSage Demo.',
			),
		);

		$save = function ( string $key ) use ( $pages ): int {
			[ $title, $slug, $content, $description ] = $pages[ $key ];

			$id = $this->upsert(
				"page:{$key}",
				array(
					'post_type'    => 'page',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
				)
			);
			update_post_meta( $id, SeoMetabox::DESCRIPTION, $description );
			return $id;
		};
		return array(
			'home'     => $save( 'home' ),
			'about'    => $save( 'about' ),
			'services' => $save( 'services' ),
			'contact'  => $save( 'contact' ),
		);
	}

	/**
	 * Blog posts: enough for two pages of results.
	 *
	 * @param array{news: int, guides: int} $categories Category IDs.
	 */
	private function posts( array $categories ): void {
		$titles = array(
			'Launching our new website',
			'Five signs your website needs a refresh',
			'Accessibility basics for small businesses',
			'How headless WordPress keeps editing simple',
			'Writing page copy that converts',
			'Choosing images that load fast',
			'What to prepare before a website project',
			'Measuring what matters after launch',
			'Keeping your site secure without the jargon',
			'Planning content for the year ahead',
			'Case study: a faster site for a busy clinic',
			'Our autumn availability',
		);
		foreach ( $titles as $index => $title ) {
			$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-01 09:00:00 UTC' ) + $index * 2 * DAY_IN_SECONDS );
			$this->upsert(
				"post:{$index}",
				array(
					'post_type'     => 'post',
					'post_title'    => $title,
					'post_name'     => sanitize_title( $title ),
					'post_date'     => $date,
					'post_date_gmt' => $date,
					'post_excerpt'  => 'A short read from the WebSage Demo team.',
					'post_category' => array( 0 === $index % 2 ? $categories['news'] : $categories['guides'] ),
					'post_content'  => '<!-- wp:paragraph --><p>This is a demonstration post. Replace it with your own news and guides in WordPress.</p><!-- /wp:paragraph -->'
						. '<!-- wp:heading --><h2 class="wp-block-heading">Why it matters</h2><!-- /wp:heading -->'
						. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Clear, useful content</li><!-- /wp:list-item --><!-- wp:list-item --><li>Fast pages on every device</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
				)
			);
		}
	}

	/**
	 * Primary and footer menus, rebuilt on each run.
	 *
	 * @param array{home: int, about: int, services: int, contact: int} $pages Page IDs.
	 */
	private function menus( array $pages ): void {
		$primary              = $this->menu(
			'WebSage Primary',
			array(
				array( 'page', $pages['home'], null ),
				array( 'page', $pages['about'], array( array( 'page', $pages['services'] ) ) ),
				array( 'page', $pages['services'], null ),
				array( 'custom', 'Blog', '/blog' ),
				array( 'page', $pages['contact'], null ),
			)
		);
		$footer               = $this->menu(
			'WebSage Footer',
			array(
				array( 'page', $pages['about'], null ),
				array( 'custom', 'Blog', '/blog' ),
				array( 'page', $pages['contact'], null ),
			)
		);
		$locations            = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = $primary;
		$locations['footer']  = $footer;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Creates or empties a menu, then adds items.
	 *
	 * @param string                        $name  Menu name.
	 * @param array<int, array<int, mixed>> $items [type, id-or-title, children|url].
	 */
	private function menu( string $name, array $items ): int {
		$menu = wp_get_nav_menu_object( $name );
		$id   = $menu ? (int) $menu->term_id : wp_create_nav_menu( $name );
		if ( ! is_int( $id ) ) {
			return 0;
		}
		foreach ( (array) wp_get_nav_menu_items( $id ) as $old ) {
			if ( $old instanceof WP_Post ) {
				wp_delete_post( $old->ID, true );
			}
		}
		foreach ( $items as $item ) {
			$parent = $this->menu_item( $id, $item, 0 );
			if ( 'page' === $item[0] && is_array( $item[2] ) ) {
				foreach ( $item[2] as $child ) {
					$this->menu_item( $id, $child, $parent );
				}
			}
		}
		return $id;
	}

	/**
	 * One menu item.
	 *
	 * @param int     $menu      Menu ID.
	 * @param mixed[] $item      [type, id-or-title, url?].
	 * @param int     $parent_id Parent item ID.
	 */
	private function menu_item( int $menu, array $item, int $parent_id ): int {
		$args                        = 'page' === $item[0]
			? array(
				'menu-item-object-id' => (int) $item[1],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
			)
			: array(
				'menu-item-title' => (string) $item[1],
				'menu-item-url'   => (string) $item[2],
				'menu-item-type'  => 'custom',
			);
		$args['menu-item-status']    = 'publish';
		$args['menu-item-parent-id'] = $parent_id;
		$id                          = wp_update_nav_menu_item( $menu, 0, $args );
		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Demo settings (merged; other settings are kept).
	 */
	private function settings(): void {
		$stored = get_option( Settings::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		update_option(
			Settings::OPTION,
			array_merge(
				$stored,
				array(
					'schema_version'         => Settings::SCHEMA_VERSION,
					'site_name'              => 'WebSage Demo',
					'announcement'           => 'Now booking projects for the new year',
					'announcement_link'      => '/contact',
					'footer_description'     => 'Independent design and development for growing businesses.',
					'copyright'              => 'WebSage Creative',
					'social'                 => array(
						array(
							'label' => 'LinkedIn',
							'url'   => 'https://www.linkedin.com/',
						),
					),
					'contact_email'          => 'hello@example.com',
					'contact_phone'          => '+1 555 0100',
					'contact_address'        => "100 Example Street\nSpringfield",
					'notification_recipient' => (string) get_option( 'admin_email' ),
					'organization_name'      => 'WebSage Demo',
				)
			),
			false
		);
	}
}
