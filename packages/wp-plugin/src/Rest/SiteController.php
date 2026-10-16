<?php
/**
 * GET /websage/v1/site.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\Values;
use WP_REST_Response;

/**
 * Site settings. Foundation returns explicit plugin defaults plus the real
 * site title; the CMB2 settings page and menus replace these in Phase 3.
 */
final class SiteController {

	public const DEFAULT_TOKENS = array(
		'primary'    => '#174F46',
		'on_primary' => '#FFFFFF',
		'background' => '#FFFFFF',
		'foreground' => '#17201E',
		'muted'      => '#F2F5F4',
		'on_muted'   => '#35443F',
		'border'     => '#62736D',
		'focus'      => '#126BDA',
	);

	/**
	 * Route callback.
	 */
	public static function show(): WP_REST_Response {
		try {
			return new WP_REST_Response( array( 'data' => self::data() ), 200 );
		} catch ( ContentException $e ) {
			return Errors::from_exception( $e );
		}
	}

	/**
	 * Site payload in the SiteSchema contract shape.
	 *
	 * @return array<string, mixed>
	 * @throws ContentException When the site title exceeds limits.
	 */
	public static function data(): array {
		return array(
			'name'     => Values::text( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), 'name' ),
			'branding' => array(
				'logo'   => null,
				'fonts'  => array(
					'heading' => 'sora',
					'body'    => 'montserrat',
				),
				'tokens' => self::DEFAULT_TOKENS,
			),
			'header'   => array(
				'announcement'     => '',
				'announcementLink' => null,
			),
			'footer'   => array(
				'description' => '',
				'copyright'   => '',
				'social'      => array(),
			),
			'menus'    => array(
				'primary' => array(),
				'footer'  => array(),
			),
			'contact'  => array(
				'email'   => '',
				'phone'   => '',
				'address' => '',
			),
		);
	}
}
