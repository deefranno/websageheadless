<?php
/**
 * Per-page SEO fields.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Fields;

/**
 * CMB2 metabox on pages and posts. Values are read by Content\Seo.
 */
final class SeoMetabox {

	public const TITLE       = '_wsh_seo_title';
	public const DESCRIPTION = '_wsh_seo_description';
	public const IMAGE       = '_wsh_seo_image';
	public const IMAGE_ID    = '_wsh_seo_image_id';
	public const NOINDEX     = '_wsh_seo_noindex';

	/**
	 * `cmb2_admin_init` callback.
	 */
	public static function register(): void {
		$cmb = new_cmb2_box(
			array(
				'id'           => 'wsh_seo',
				'title'        => __( 'Search & sharing', 'websage-headless' ),
				'object_types' => array( 'page', 'post' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_in_rest' => false,
			)
		);
		$cmb->add_field(
			array(
				'id'              => self::TITLE,
				'name'            => __( 'Search title', 'websage-headless' ),
				'desc'            => __( 'Leave empty to use the site title template.', 'websage-headless' ),
				'type'            => 'text',
				'attributes'      => array( 'maxlength' => 500 ),
				'sanitization_cb' => array( Sanitize::class, 'text' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
			)
		);
		$cmb->add_field(
			array(
				'id'              => self::DESCRIPTION,
				'name'            => __( 'Search description', 'websage-headless' ),
				'type'            => 'textarea_small',
				'attributes'      => array( 'maxlength' => 500 ),
				'sanitization_cb' => array( Sanitize::class, 'textarea' ),
				'escape_cb'       => array( Sanitize::class, 'esc_textarea' ),
			)
		);
		$cmb->add_field(
			array(
				'id'              => self::IMAGE,
				'name'            => __( 'Sharing image', 'websage-headless' ),
				'type'            => 'file',
				'options'         => array( 'url' => false ),
				'query_args'      => array( 'type' => array( 'image/jpeg', 'image/png', 'image/webp' ) ),
				'sanitization_cb' => array( Sanitize::class, 'image' ),
				'escape_cb'       => array( Sanitize::class, 'esc_url' ),
			)
		);
		$cmb->add_field(
			array(
				'id'              => self::NOINDEX,
				'name'            => __( 'Hide from search engines', 'websage-headless' ),
				'desc'            => __( 'Adds noindex and leaves this page out of the sitemap.', 'websage-headless' ),
				'type'            => 'checkbox',
				'sanitization_cb' => array( Sanitize::class, 'checkbox' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
			)
		);
	}
}
