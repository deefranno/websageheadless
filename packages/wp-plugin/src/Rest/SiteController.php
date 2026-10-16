<?php
/**
 * GET /websage/v1/site.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Rest;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\Menus;
use WebSage\HeadlessCore\Content\Seo;
use WebSage\HeadlessCore\Content\Values;
use WebSage\HeadlessCore\Settings\Settings;
use WP_REST_Response;

/**
 * Site settings, branding, header, footer, menus and public contact details.
 */
final class SiteController {

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
	 */
	public static function data(): array {
		$s      = Settings::all();
		$tokens = array();
		foreach ( Settings::DEFAULT_TOKENS as $key => $default ) {
			$value          = strtoupper( (string) $s[ $key ] );
			$tokens[ $key ] = 1 === preg_match( '/^#[0-9A-F]{6}$/', $value ) ? $value : $default;
		}

		$announcement = Values::text_clipped( $s['announcement'] );
		$link_href    = Values::url( $s['announcement_link'] );

		$social = array();
		foreach ( (array) $s['social'] as $entry ) {
			$label = Values::text_clipped( is_array( $entry ) ? ( $entry['label'] ?? '' ) : '' );
			$url   = Values::url( is_array( $entry ) ? ( $entry['url'] ?? '' ) : '' );
			if ( '' !== $label && null !== $url && count( $social ) < 20 ) {
				$social[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		$email = (string) $s['contact_email'];
		return array(
			'name'     => Seo::site_name(),
			'branding' => array(
				'logo'   => Values::image( (int) $s['logo_id'] ),
				'fonts'  => array(
					'heading' => in_array( $s['font_heading'], Settings::HEADING_FONTS, true ) ? $s['font_heading'] : 'sora',
					'body'    => in_array( $s['font_body'], Settings::BODY_FONTS, true ) ? $s['font_body'] : 'montserrat',
				),
				'tokens' => $tokens,
			),
			'header'   => array(
				'announcement'     => $announcement,
				'announcementLink' => '' !== $announcement && null !== $link_href
					? array(
						'label'  => $announcement,
						'href'   => $link_href,
						'newTab' => false,
					)
					: null,
			),
			'footer'   => array(
				'description' => Values::text_clipped( $s['footer_description'] ),
				'copyright'   => Values::text_clipped( $s['copyright'] ),
				'social'      => $social,
			),
			'menus'    => array(
				'primary' => Menus::tree( 'primary' ),
				'footer'  => Menus::tree( 'footer' ),
			),
			'contact'  => array(
				'email'   => is_email( $email ) ? $email : '',
				'phone'   => Values::text_clipped( $s['contact_phone'] ),
				'address' => Values::text_clipped( $s['contact_address'] ),
			),
		);
	}
}
