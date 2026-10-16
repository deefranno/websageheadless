<?php
/**
 * Shared value normalizers matching packages/contracts primitives.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

/**
 * Converts stored attributes into contract-safe public values.
 */
final class Values {

	public const TEXT_MAX      = 500;
	public const RICH_TEXT_MAX = 20000;
	public const BODY_HTML_MAX = 100000;

	/** Block-level markup permitted in long-form content. */
	private const BODY_TAGS = array(
		'p'          => array(),
		'br'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'ul'         => array(),
		'ol'         => array(
			'start'    => true,
			'reversed' => true,
		),
		'li'         => array(),
		'strong'     => array(),
		'em'         => array(),
		'b'          => array(),
		'i'          => array(),
		'code'       => array(),
		'blockquote' => array(),
		'cite'       => array(),
		'figure'     => array(),
		'figcaption' => array(),
		'img'        => array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
		),
		'a'          => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
	);

	/** Inline markup permitted in RichText values. */
	private const RICH_TEXT_TAGS = array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'a'      => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
	);

	/**
	 * Plain text, tags stripped, bounded by the Text primitive.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $field Field name for diagnostics.
	 * @throws ContentException When too long.
	 */
	public static function text( mixed $value, string $field ): string {
		$text = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
		if ( mb_strlen( $text ) > self::TEXT_MAX ) {
			throw ContentException::too_large( "{$field} exceeds " . self::TEXT_MAX . ' characters' );
		}
		return $text;
	}

	/**
	 * Allowlisted inline HTML, bounded by the RichText primitive.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $field Field name for diagnostics.
	 * @throws ContentException When too long.
	 */
	public static function rich_text( mixed $value, string $field ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		if ( mb_strlen( $value ) > self::RICH_TEXT_MAX ) {
			throw ContentException::too_large( "{$field} exceeds " . self::RICH_TEXT_MAX . ' characters' );
		}
		return trim( wp_kses( self::drop_code( $value ), self::RICH_TEXT_TAGS, array( 'https', 'mailto', 'tel' ) ) );
	}

	/**
	 * Plain text from a library item, clipped to the Text limit instead of
	 * failing the whole page (library items are edited outside the page).
	 *
	 * @param mixed $value Stored value.
	 */
	public static function text_clipped( mixed $value ): string {
		$text = is_string( $value ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) ?? '' ) : '';
		return mb_substr( $text, 0, self::TEXT_MAX );
	}

	/**
	 * Allowlisted block HTML, bounded by the BodyHtml primitive. Level-one
	 * headings become h2 so the page keeps a single h1.
	 *
	 * @param mixed  $value Stored HTML.
	 * @param string $field Field name for diagnostics.
	 * @throws ContentException When too long.
	 */
	public static function body_html( mixed $value, string $field ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		if ( strlen( $value ) > self::BODY_HTML_MAX ) {
			throw ContentException::too_large( "{$field} exceeds " . self::BODY_HTML_MAX . ' bytes' );
		}
		$value = preg_replace( array( '/<h1(\s|>)/i', '/<\/h1>/i' ), array( '<h2$1', '</h2>' ), $value ) ?? '';
		$html  = trim( wp_kses( self::drop_code( $value ), self::BODY_TAGS, array( 'https', 'mailto', 'tel' ) ) );
		return strlen( $html ) > self::BODY_HTML_MAX ? throw ContentException::too_large( "{$field} exceeds " . self::BODY_HTML_MAX . ' bytes' ) : $html;
	}

	/**
	 * A safe frontend path or HTTPS URL. CMS-origin URLs become frontend paths,
	 * except uploaded media, which stays on the CMS.
	 *
	 * @param mixed $value Stored URL.
	 * @return string|null Null when unsafe or empty.
	 */
	public static function url( mixed $value ): ?string {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 2048 || 1 === preg_match( '/[\\\\\s\x00-\x1f]/', $value ) ) {
			return null;
		}
		if ( str_starts_with( $value, '/' ) ) {
			return str_starts_with( $value, '//' ) || self::has_dot_segment( $value ) ? null : $value;
		}

		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		$cms_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$is_media = str_starts_with( $parts['path'] ?? '', '/wp-content/uploads/' );
		if ( ! $is_media && strtolower( $parts['host'] ) === strtolower( (string) $cms_host ) ) {
			$path = ( $parts['path'] ?? '/' )
				. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
				. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
			return self::url( $path );
		}

		return 'https' === strtolower( $parts['scheme'] ?? '' ) ? $value : null;
	}

	/**
	 * A Link object, or null when absent or unusable.
	 *
	 * @param mixed  $value Stored link attribute.
	 * @param string $field Field name for diagnostics.
	 * @return array{label: string, href: string, newTab: bool}|null
	 */
	public static function link( mixed $value, string $field ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$label = self::text( $value['label'] ?? '', "{$field}.label" );
		$href  = self::url( $value['href'] ?? null );
		if ( '' === $label || null === $href ) {
			return null;
		}
		return array(
			'label'  => $label,
			'href'   => $href,
			'newTab' => true === ( $value['newTab'] ?? false ),
		);
	}

	/**
	 * Resolves an attachment ID into a public Image, or null.
	 *
	 * @param mixed $id Stored media ID.
	 * @return array{url: string, alt: string, width: int, height: int}|null
	 */
	public static function image( mixed $id ): ?array {
		if ( ! is_int( $id ) || $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		$source = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $source ) || ! str_starts_with( (string) $source[0], 'https://' ) || $source[1] < 1 || $source[2] < 1 ) {
			return null;
		}
		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
		return array(
			'url'    => (string) $source[0],
			'alt'    => mb_substr( trim( wp_strip_all_tags( is_string( $alt ) ? $alt : '' ) ), 0, self::TEXT_MAX ),
			'width'  => (int) $source[1],
			'height' => (int) $source[2],
		);
	}

	/**
	 * UTC ISO-8601 timestamp from a GMT MySQL datetime.
	 *
	 * @param string $gmt_datetime "Y-m-d H:i:s" in GMT.
	 */
	public static function timestamp( string $gmt_datetime ): string {
		$time = strtotime( $gmt_datetime . ' UTC' );
		return gmdate( 'Y-m-d\TH:i:s\Z', false === $time ? 0 : $time );
	}

	/**
	 * Removes script and style elements with their contents; kses alone
	 * strips the tags but would leave the code visible as text.
	 *
	 * @param string $html HTML.
	 */
	private static function drop_code( string $html ): string {
		return preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html ) ?? '';
	}

	/**
	 * Whether a path contains "." or ".." segments.
	 *
	 * @param string $path URL path.
	 */
	private static function has_dot_segment( string $path ): bool {
		$path = (string) strtok( $path, '?#' );
		foreach ( explode( '/', rawurldecode( $path ) ) as $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				return true;
			}
		}
		return false;
	}
}
