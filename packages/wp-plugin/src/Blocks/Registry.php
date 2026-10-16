<?php
/**
 * Block registration, allowed blocks and page templates.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Blocks;

use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Content\Sections;
use WP_Block_Editor_Context;

/**
 * Registers websage/* blocks from compiled metadata, limits the inserter to
 * blocks the frontend can render, and gives new pages a starting layout.
 */
final class Registry {

	public const SECTION_BLOCKS = array(
		'websage/hero',
		'websage/text-image',
		'websage/feature-grid',
		'websage/cta',
		'websage/cta-reference',
		'websage/testimonials',
		'websage/faq',
		'websage/contact',
	);

	public const CHILD_BLOCKS = array(
		'websage/feature',
		'websage/testimonial-reference',
		'websage/faq-item',
	);

	/**
	 * `init` callback.
	 */
	public static function register(): void {
		$build    = WSH_PLUGIN_DIR . '/build';
		$manifest = $build . '/blocks-manifest.php';
		if ( ! is_readable( $manifest ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- missing build is a packaging error.
			error_log( 'websage-headless: build/blocks-manifest.php missing; run the block build.' );
			return;
		}
		wp_register_block_types_from_metadata_collection( $build, $manifest );
		self::templates();
		self::patterns();
	}

	/**
	 * `allowed_block_types_all`: only blocks the frontend can render.
	 *
	 * @param bool|array<int, string> $allowed Current allowance.
	 * @param WP_Block_Editor_Context $context Editor context.
	 * @return bool|array<int, string>
	 */
	public static function allowed( bool|array $allowed, WP_Block_Editor_Context $context ): bool|array {
		$type = $context->post?->post_type;
		if ( PostTypes::CTA === $type ) {
			return array( 'websage/cta' );
		}
		if ( in_array( $type, array( 'page', 'post' ), true ) ) {
			return array_merge( self::SECTION_BLOCKS, self::CHILD_BLOCKS, Sections::CORE_BLOCKS, array( 'core/block' ) );
		}
		return $allowed;
	}

	/**
	 * Default layout for new pages (reorderable, not locked).
	 */
	private static function templates(): void {
		$page = get_post_type_object( 'page' );
		if ( null !== $page ) {
			$page->template = array(
				array( 'websage/hero', array( 'heading' => '' ) ),
				array( 'websage/text-image' ),
				array( 'websage/cta' ),
			);
		}
	}

	/**
	 * Starting layouts for home and contact pages, in the pattern inserter.
	 */
	private static function patterns(): void {
		register_block_pattern_category( 'websage', array( 'label' => __( 'WebSage page layouts', 'websage-headless' ) ) );
		register_block_pattern(
			'websage/home',
			array(
				'title'      => __( 'Home page', 'websage-headless' ),
				'categories' => array( 'websage' ),
				'postTypes'  => array( 'page' ),
				'content'    => '<!-- wp:websage/hero /--><!-- wp:websage/feature-grid --><!-- wp:websage/feature /--><!-- wp:websage/feature /--><!-- wp:websage/feature /--><!-- /wp:websage/feature-grid --><!-- wp:websage/testimonials /--><!-- wp:websage/cta /-->',
			)
		);
		register_block_pattern(
			'websage/contact',
			array(
				'title'      => __( 'Contact page', 'websage-headless' ),
				'categories' => array( 'websage' ),
				'postTypes'  => array( 'page' ),
				'content'    => '<!-- wp:websage/hero /--><!-- wp:websage/contact /--><!-- wp:websage/faq --><!-- wp:websage/faq-item /--><!-- /wp:websage/faq -->',
			)
		);
	}
}
