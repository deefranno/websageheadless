<?php
/**
 * Block content to the public `sections[]` contract.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use DomainException;
use WebSage\HeadlessCore\Blocks\HeroMigration;
use WebSage\HeadlessCore\Fields\TestimonialMetabox;
use WP_Post;

/**
 * Walks parse_blocks() output and builds typed sections from block
 * attributes only. Never renders blocks, shortcodes or embeds.
 *
 * - websage/* blocks become their section types (unknown ones keep only
 *   the envelope, which the frontend skips);
 * - runs of curated core blocks become one `core-content` section;
 * - synced patterns (core/block) are expanded, with cycle detection;
 * - CTA and testimonial references resolve to published library items.
 *
 * Every referenced reusable item is recorded so Content\References can
 * invalidate the pages that show it.
 */
final class Sections {

	public const MAX_SECTIONS  = 100;
	public const MAX_DEPTH     = 8;
	public const MAX_FEATURES  = 12;
	public const MAX_QUOTES    = 12;
	public const MAX_QUESTIONS = 30;

	/** Core blocks clients may use; they become `core-content`. */
	public const CORE_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/quote',
	);

	/**
	 * Sections built so far.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sections = array();

	/**
	 * Pending core-content HTML fragments.
	 *
	 * @var list<string>
	 */
	private array $core = array();

	/**
	 * Reusable items referenced, as "type:id".
	 *
	 * @var array<string, true>
	 */
	private array $references = array();

	/**
	 * Synced pattern IDs on the current expansion path.
	 *
	 * @var array<int, true>
	 */
	private array $visiting = array();

	/**
	 * Parses content into sections.
	 *
	 * @param string $content Raw post content.
	 * @throws ContentException When limits are exceeded or stored data is unsupported.
	 */
	public static function build( string $content ): self {
		$builder = new self();
		$builder->walk( parse_blocks( $content ), 1 );
		$builder->flush();
		return $builder;
	}

	/**
	 * Sections in document order.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function sections(): array {
		return $this->sections;
	}

	/**
	 * Referenced reusable items, as "cta:12", "testimonial:7", "pattern:3".
	 *
	 * @return list<string>
	 */
	public function references(): array {
		return array_keys( $this->references );
	}

	/**
	 * Processes a list of sibling blocks.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @param int                                     $depth  Nesting depth.
	 * @throws ContentException When limits are exceeded.
	 */
	private function walk( array $blocks, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			throw ContentException::too_large( 'block nesting deeper than ' . self::MAX_DEPTH );
		}
		foreach ( $blocks as $block ) {
			$name       = $block['blockName'] ?? null;
			$attributes = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

			if ( null === $name ) {
				// Whitespace between blocks, or classic-editor HTML with no blocks.
				$html = trim( (string) ( $block['innerHTML'] ?? '' ) );
				if ( '' !== $html ) {
					$this->core[] = $html;
				}
				continue;
			}

			$this->assert_depth( $block, $depth );

			if ( in_array( $name, self::CORE_BLOCKS, true ) ) {
				$this->core[] = self::block_html( $block );
				continue;
			}

			if ( 'core/block' === $name ) {
				$this->pattern( (int) ( $attributes['ref'] ?? 0 ), $depth );
				continue;
			}

			if ( ! str_starts_with( (string) $name, 'websage/' ) ) {
				self::log( "skipped unsupported block {$name}" );
				continue;
			}

			$this->flush();
			$section = $this->section( $block, (string) count( $this->sections ) );
			if ( null !== $section ) {
				$this->push( $section );
			}
		}
	}

	/**
	 * Expands a synced pattern in place.
	 *
	 * @param int $id    wp_block post ID.
	 * @param int $depth Current depth.
	 * @throws ContentException When limits are exceeded.
	 */
	private function pattern( int $id, int $depth ): void {
		if ( $id < 1 ) {
			return;
		}
		$this->references[ "pattern:{$id}" ] = true;
		if ( isset( $this->visiting[ $id ] ) ) {
			self::log( "skipped synced pattern {$id}: it includes itself" );
			return;
		}
		$pattern = get_post( $id );
		if ( ! $pattern instanceof WP_Post || 'wp_block' !== $pattern->post_type || 'publish' !== $pattern->post_status ) {
			self::log( "skipped synced pattern {$id}: missing or unpublished" );
			return;
		}
		$this->visiting[ $id ] = true;
		$this->walk( parse_blocks( $pattern->post_content ), $depth + 1 );
		unset( $this->visiting[ $id ] );
	}

	/**
	 * Emits pending core HTML as one core-content section.
	 *
	 * @throws ContentException When the HTML is too large.
	 */
	private function flush(): void {
		if ( array() === $this->core ) {
			return;
		}
		$html       = Values::body_html( implode( '', $this->core ), 'core-content' );
		$this->core = array();
		if ( '' === $html ) {
			return;
		}
		$this->push(
			array(
				'type'          => 'core-content',
				'schemaVersion' => 1,
				'key'           => (string) count( $this->sections ),
				'html'          => $html,
			)
		);
	}

	/**
	 * Appends a section within the page limit.
	 *
	 * @param array<string, mixed> $section Section.
	 * @throws ContentException Over MAX_SECTIONS.
	 */
	private function push( array $section ): void {
		if ( count( $this->sections ) >= self::MAX_SECTIONS ) {
			throw ContentException::too_large( 'more than ' . self::MAX_SECTIONS . ' sections' );
		}
		$this->sections[] = $section;
	}

	/**
	 * One websage/* block as a section, or null when it cannot be shown.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $key   Section key.
	 * @return array<string, mixed>|null
	 * @throws ContentException On unsupported stored versions or limits.
	 */
	private function section( array $block, string $key ): ?array {
		$name       = substr( (string) $block['blockName'], strlen( 'websage/' ) );
		$attributes = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$children   = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();

		try {
			$known = array( 'text-image', 'feature-grid', 'cta', 'cta-reference', 'testimonials', 'faq', 'contact' );
			if ( in_array( $name, $known, true ) ) {
				self::version( $attributes );
			}
			$stored = $attributes['schemaVersion'] ?? 1;
			return match ( $name ) {
				'hero'          => self::hero( $attributes, $key ),
				'text-image'    => self::text_image( $attributes, $key ),
				'feature-grid'  => self::feature_grid( $attributes, $children, $key ),
				'cta'           => self::cta( $attributes, $key ),
				'cta-reference' => $this->cta_reference( $attributes, $key ),
				'testimonials'  => $this->testimonials( $attributes, $children, $key ),
				'faq'           => self::faq( $attributes, $children, $key ),
				'contact'       => self::contact( $attributes, $key ),
				// Child blocks outside their parent carry no section of their own.
				'feature', 'faq-item', 'testimonial-reference' => null,
				// Newer section type: envelope only, skipped by the frontend.
				default         => array(
					'type'          => $name,
					'schemaVersion' => is_int( $stored ) && $stored >= 0 ? $stored : 1,
					'key'           => $key,
				),
			};
		} catch ( DomainException $e ) {
			throw new ContentException( 500, 'content_invalid', "websage/{$name}: " . $e->getMessage() );
		}
	}

	/**
	 * Stored schema version for blocks without a dedicated migration yet.
	 * Missing means version 1 (defaults are not serialized).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @throws DomainException For versions this plugin does not understand.
	 */
	private static function version( array $attributes ): int {
		$version = $attributes['schemaVersion'] ?? 1;
		if ( 1 !== $version ) {
			throw new DomainException( 'unsupported schemaVersion' );
		}
		return 1;
	}

	/**
	 * Theme attribute, defaulting safely.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private static function theme( array $attributes ): string {
		$theme = $attributes['theme'] ?? 'default';
		return in_array( $theme, HeroMigration::THEMES, true ) ? $theme : 'default';
	}

	/**
	 * Hero.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $key        Key.
	 * @return array<string, mixed>
	 */
	private static function hero( array $attributes, string $key ): array {
		$hero = HeroMigration::normalize( $attributes );
		return array(
			'type'          => 'hero',
			'schemaVersion' => $hero['schemaVersion'],
			'key'           => $key,
			'eyebrow'       => Values::text( $hero['eyebrow'], 'hero.eyebrow' ),
			'heading'       => Values::text( $hero['heading'], 'hero.heading' ),
			'body'          => Values::rich_text( $hero['body'], 'hero.body' ),
			'image'         => Values::image( $hero['imageId'] ),
			'primary'       => Values::link( $hero['primary'], 'hero.primary' ),
			'secondary'     => Values::link( $hero['secondary'], 'hero.secondary' ),
			'theme'         => $hero['theme'],
		);
	}

	/**
	 * Text and image.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $key        Key.
	 * @return array<string, mixed>
	 */
	private static function text_image( array $attributes, string $key ): array {
		return array(
			'type'          => 'text-image',
			'schemaVersion' => 1,
			'key'           => $key,
			'heading'       => Values::text( $attributes['heading'] ?? '', 'text-image.heading' ),
			'body'          => Values::body_html( self::paragraph( $attributes['body'] ?? '' ), 'text-image.body' ),
			'image'         => Values::image( $attributes['imageId'] ?? null ),
			'position'      => 'left' === ( $attributes['position'] ?? 'right' ) ? 'left' : 'right',
			'theme'         => self::theme( $attributes ),
		);
	}

	/**
	 * Feature grid with its feature children.
	 *
	 * @param array<string, mixed>             $attributes Attributes.
	 * @param array<int, array<string, mixed>> $children   Inner blocks.
	 * @param string                           $key        Key.
	 * @return array<string, mixed>
	 */
	private static function feature_grid( array $attributes, array $children, string $key ): array {
		$items = array();
		foreach ( self::children( $children, 'websage/feature', self::MAX_FEATURES, 'feature-grid' ) as $child ) {
			$a       = $child['attrs'];
			$items[] = array(
				'title' => Values::text( $a['title'] ?? '', 'feature.title' ),
				'body'  => Values::rich_text( $a['body'] ?? '', 'feature.body' ),
				'image' => Values::image( $a['imageId'] ?? null ),
				'link'  => Values::link( $a['link'] ?? null, 'feature.link' ),
			);
		}
		$columns = $attributes['columns'] ?? 3;
		return array(
			'type'          => 'feature-grid',
			'schemaVersion' => 1,
			'key'           => $key,
			'heading'       => Values::text( $attributes['heading'] ?? '', 'feature-grid.heading' ),
			'introduction'  => Values::rich_text( $attributes['introduction'] ?? '', 'feature-grid.introduction' ),
			'columns'       => in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : 3,
			'theme'         => self::theme( $attributes ),
			'items'         => $items,
		);
	}

	/**
	 * Call to action; omitted when it has no usable link.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $key        Key.
	 * @return array<string, mixed>|null
	 */
	private static function cta( array $attributes, string $key ): ?array {
		$link = Values::link( $attributes['link'] ?? null, 'cta.link' );
		if ( null === $link ) {
			self::log( 'omitted cta section without a valid link' );
			return null;
		}
		return array(
			'type'          => 'cta',
			'schemaVersion' => 1,
			'key'           => $key,
			'heading'       => Values::text( $attributes['heading'] ?? '', 'cta.heading' ),
			'body'          => Values::rich_text( $attributes['body'] ?? '', 'cta.body' ),
			'link'          => $link,
			'theme'         => self::theme( $attributes ),
		);
	}

	/**
	 * A global CTA from the library, with the referencing block's theme.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $key        Key.
	 * @return array<string, mixed>|null
	 */
	private function cta_reference( array $attributes, string $key ): ?array {
		$id = (int) ( $attributes['ctaId'] ?? 0 );
		if ( $id < 1 ) {
			return null;
		}
		$this->references[ "cta:{$id}" ] = true;
		$post                            = get_post( $id );
		if ( ! $post instanceof WP_Post || PostTypes::CTA !== $post->post_type || 'publish' !== $post->post_status ) {
			self::log( "omitted cta-reference: call to action {$id} is missing or unpublished" );
			return null;
		}
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if ( 'websage/cta' === ( $block['blockName'] ?? null ) ) {
				$cta_attributes = $block['attrs'];
				if ( isset( $attributes['theme'] ) ) {
					$cta_attributes['theme'] = $attributes['theme'];
				}
				return self::cta( $cta_attributes, $key );
			}
		}
		self::log( "omitted cta-reference: call to action {$id} has no CTA block" );
		return null;
	}

	/**
	 * Testimonials resolved from the library.
	 *
	 * @param array<string, mixed>             $attributes Attributes.
	 * @param array<int, array<string, mixed>> $children   Inner blocks.
	 * @param string                           $key        Key.
	 * @return array<string, mixed>
	 */
	private function testimonials( array $attributes, array $children, string $key ): array {
		$items = array();
		foreach ( self::children( $children, 'websage/testimonial-reference', self::MAX_QUOTES, 'testimonials' ) as $child ) {
			$id = (int) ( $child['attrs']['testimonialId'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$this->references[ "testimonial:{$id}" ] = true;
			$post                                    = get_post( $id );
			if ( ! $post instanceof WP_Post || PostTypes::TESTIMONIAL !== $post->post_type || 'publish' !== $post->post_status ) {
				self::log( "skipped testimonial {$id}: missing or unpublished" );
				continue;
			}
			$items[] = array(
				'quote'   => Values::text_clipped( $post->post_content ),
				'author'  => Values::text_clipped( get_post_meta( $id, TestimonialMetabox::AUTHOR, true ) ),
				'role'    => Values::text_clipped( get_post_meta( $id, TestimonialMetabox::ROLE, true ) ),
				'company' => Values::text_clipped( get_post_meta( $id, TestimonialMetabox::COMPANY, true ) ),
				'image'   => Values::image( (int) get_post_meta( $id, TestimonialMetabox::PORTRAIT_ID, true ) ),
			);
		}
		return array(
			'type'          => 'testimonials',
			'schemaVersion' => 1,
			'key'           => $key,
			'heading'       => Values::text( $attributes['heading'] ?? '', 'testimonials.heading' ),
			'theme'         => self::theme( $attributes ),
			'items'         => $items,
		);
	}

	/**
	 * FAQ with question children.
	 *
	 * @param array<string, mixed>             $attributes Attributes.
	 * @param array<int, array<string, mixed>> $children   Inner blocks.
	 * @param string                           $key        Key.
	 * @return array<string, mixed>
	 */
	private static function faq( array $attributes, array $children, string $key ): array {
		$items = array();
		foreach ( self::children( $children, 'websage/faq-item', self::MAX_QUESTIONS, 'faq' ) as $child ) {
			$question = Values::text( $child['attrs']['question'] ?? '', 'faq-item.question' );
			if ( '' === $question ) {
				continue;
			}
			$items[] = array(
				'question' => $question,
				'answer'   => Values::rich_text( $child['attrs']['answer'] ?? '', 'faq-item.answer' ),
			);
		}
		return array(
			'type'          => 'faq',
			'schemaVersion' => 1,
			'key'           => $key,
			'heading'       => Values::text( $attributes['heading'] ?? '', 'faq.heading' ),
			'theme'         => self::theme( $attributes ),
			'items'         => $items,
		);
	}

	/**
	 * Contact form section (presentation only; recipients live in settings).
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @param string               $key        Key.
	 * @return array<string, mixed>
	 */
	private static function contact( array $attributes, string $key ): array {
		$submit  = Values::text( $attributes['submitLabel'] ?? '', 'contact.submitLabel' );
		$success = Values::text( $attributes['successMessage'] ?? '', 'contact.successMessage' );
		return array(
			'type'           => 'contact',
			'schemaVersion'  => 1,
			'key'            => $key,
			'heading'        => Values::text( $attributes['heading'] ?? '', 'contact.heading' ),
			'body'           => Values::rich_text( $attributes['body'] ?? '', 'contact.body' ),
			'submitLabel'    => '' === $submit ? __( 'Send message', 'websage-headless' ) : $submit,
			'successMessage' => '' === $success ? __( 'Thank you. We will be in touch soon.', 'websage-headless' ) : $success,
			'theme'          => self::theme( $attributes ),
		);
	}

	/**
	 * Child blocks of one type, capped at the contract limit.
	 *
	 * @param array<int, array<string, mixed>> $children Inner blocks.
	 * @param string                           $name     Allowed child block name.
	 * @param int                              $max      Maximum kept.
	 * @param string                           $owner    Parent block name for diagnostics.
	 * @return list<array{attrs: array<string, mixed>}>
	 * @throws DomainException On unsupported child versions.
	 */
	private static function children( array $children, string $name, int $max, string $owner ): array {
		$kept = array();
		foreach ( $children as $child ) {
			if ( ( $child['blockName'] ?? null ) !== $name ) {
				continue;
			}
			$attrs = is_array( $child['attrs'] ?? null ) ? $child['attrs'] : array();
			self::version( $attrs );
			if ( count( $kept ) >= $max ) {
				self::log( "{$owner}: ignored items beyond {$max}" );
				break;
			}
			$kept[] = array( 'attrs' => $attrs );
		}
		return $kept;
	}

	/**
	 * Saved HTML of a static block, with inner blocks placed back in.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function block_html( array $block ): string {
		$html  = '';
		$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
		$index = 0;
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
			if ( is_string( $chunk ) ) {
				$html .= $chunk;
			} elseif ( isset( $inner[ $index ] ) ) {
				$html .= self::block_html( $inner[ $index++ ] );
			}
		}
		return trim( $html );
	}

	/**
	 * Wraps inline RichText output in a paragraph.
	 *
	 * @param mixed $value Stored value.
	 */
	private static function paragraph( mixed $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value || 1 === preg_match( '/^<(p|h[2-6]|ul|ol|blockquote)[\s>]/i', $value ) ) {
			return $value;
		}
		return '<p>' . $value . '</p>';
	}

	/**
	 * Depth guard for a block subtree.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param int                  $depth Depth of $block.
	 * @throws ContentException When too deep.
	 */
	private function assert_depth( array $block, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			throw ContentException::too_large( 'block nesting deeper than ' . self::MAX_DEPTH );
		}
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
			$this->assert_depth( $child, $depth + 1 );
		}
	}

	/**
	 * Diagnostic for content that is valid but not shown.
	 *
	 * @param string $message Message.
	 */
	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in diagnostics.
			error_log( 'websage-headless: ' . $message );
		}
	}
}
