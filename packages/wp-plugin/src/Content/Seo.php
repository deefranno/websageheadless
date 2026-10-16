<?php
/**
 * SEO metadata with site-wide fallbacks.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WebSage\HeadlessCore\Fields\SeoMetabox;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Support\Integration;
use WP_Post;

/**
 * Builds the `Seo` contract: per-item fields first, then settings defaults.
 * Canonicals always derive from the frontend origin and route.
 */
final class Seo {

	/**
	 * SEO for a page or post.
	 *
	 * @param WP_Post $post        Post.
	 * @param string  $path        Frontend path.
	 * @param string  $description Fallback description (e.g. post excerpt).
	 * @return array{title: string, description: string, canonical: string, openGraphImage: array<string, mixed>|null, noindex: bool}
	 * @throws ContentException When the frontend origin is not configured.
	 */
	public static function for_post( WP_Post $post, string $path, string $description = '' ): array {
		$settings = Settings::all();
		$title    = Values::text_clipped( get_post_meta( $post->ID, SeoMetabox::TITLE, true ) );
		if ( '' === $title ) {
			$title = self::apply_template( (string) $settings['seo_title_template'], Values::text_clipped( $post->post_title ) );
		}

		$summary = Values::text_clipped( get_post_meta( $post->ID, SeoMetabox::DESCRIPTION, true ) );
		foreach ( array( $description, (string) $settings['seo_description'], (string) get_bloginfo( 'description' ) ) as $fallback ) {
			if ( '' !== $summary ) {
				break;
			}
			$summary = Values::text_clipped( html_entity_decode( $fallback, ENT_QUOTES ) );
		}

		$image = Values::image( (int) get_post_meta( $post->ID, SeoMetabox::IMAGE_ID, true ) )
			?? Values::image( (int) get_post_thumbnail_id( $post ) )
			?? Values::image( (int) $settings['seo_og_image_id'] );

		return array(
			'title'          => $title,
			'description'    => $summary,
			'canonical'      => Integration::frontend_origin() . $path,
			'openGraphImage' => $image,
			'noindex'        => self::noindex( $post ),
		);
	}

	/**
	 * Whether an editor hid this item from search engines.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function noindex( WP_Post $post ): bool {
		return 'on' === get_post_meta( $post->ID, SeoMetabox::NOINDEX, true );
	}

	/**
	 * Public site name: the settings override, or the WordPress title.
	 */
	public static function site_name(): string {
		$name = (string) Settings::get( 'site_name' );
		return Values::text_clipped( '' !== $name ? $name : html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/**
	 * Applies "{title} | {site}".
	 *
	 * @param string $template Template.
	 * @param string $title    Item title.
	 */
	private static function apply_template( string $template, string $title ): string {
		if ( '' === $title ) {
			return self::site_name();
		}
		$template = '' === trim( $template ) ? '{title} | {site}' : $template;
		return Values::text_clipped(
			strtr(
				$template,
				array(
					'{title}' => $title,
					'{site}'  => self::site_name(),
				)
			)
		);
	}
}
