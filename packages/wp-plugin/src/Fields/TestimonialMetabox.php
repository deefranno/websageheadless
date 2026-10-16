<?php
/**
 * Testimonial author details.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Fields;

use WebSage\HeadlessCore\Content\PostTypes;

/**
 * CMB2 metabox on the testimonial library.
 */
final class TestimonialMetabox {

	public const AUTHOR      = '_wsh_author';
	public const ROLE        = '_wsh_role';
	public const COMPANY     = '_wsh_company';
	public const PORTRAIT    = '_wsh_portrait';
	public const PORTRAIT_ID = '_wsh_portrait_id';

	/**
	 * `cmb2_admin_init` callback.
	 */
	public static function register(): void {
		$cmb = new_cmb2_box(
			array(
				'id'           => 'wsh_testimonial',
				'title'        => __( 'Who said it', 'websage-headless' ),
				'object_types' => array( PostTypes::TESTIMONIAL ),
				'context'      => 'normal',
				'priority'     => 'high',
				'show_in_rest' => false,
			)
		);
		foreach (
			array(
				self::AUTHOR  => __( 'Name', 'websage-headless' ),
				self::ROLE    => __( 'Role', 'websage-headless' ),
				self::COMPANY => __( 'Company', 'websage-headless' ),
			) as $id => $name
		) {
			$cmb->add_field(
				array(
					'id'              => $id,
					'name'            => $name,
					'type'            => 'text',
					'attributes'      => array( 'maxlength' => 500 ),
					'sanitization_cb' => array( Sanitize::class, 'text' ),
					'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
				)
			);
		}
		$cmb->add_field(
			array(
				'id'              => self::PORTRAIT,
				'name'            => __( 'Photo', 'websage-headless' ),
				'type'            => 'file',
				'options'         => array( 'url' => false ),
				'query_args'      => array( 'type' => array( 'image/jpeg', 'image/png', 'image/webp' ) ),
				'sanitization_cb' => array( Sanitize::class, 'image' ),
				'escape_cb'       => array( Sanitize::class, 'esc_url' ),
			)
		);
	}
}
