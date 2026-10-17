<?php
/**
 * Headless hardening of the CMS host.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Security;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\Paths;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Support\Integration;
use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Keeps the WordPress origin out of search results and reduces its public
 * surface. Each measure follows its Integration setting.
 */
final class Hardening {

	/**
	 * Registers hooks according to settings.
	 */
	public static function register(): void {
		$settings = Settings::all();

		if ( $settings['hardening'] ) {
			add_filter( 'wp_sitemaps_enabled', '__return_false' );
			add_filter( 'wp_robots', array( self::class, 'noindex' ) );
			add_action( 'template_redirect', array( self::class, 'redirect_front_end' ), 0 );
		}
		if ( $settings['disable_xmlrpc'] ) {
			if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				self::refuse_xmlrpc();
			}
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', array( self::class, 'remove_pingback_header' ) );
		}
		if ( $settings['disable_user_enumeration'] ) {
			add_filter( 'rest_pre_dispatch', array( self::class, 'block_user_listing' ), 10, 3 );
			add_action( 'template_redirect', array( self::class, 'block_author_archives' ), 1 );
			add_filter( 'oembed_response_data', array( self::class, 'hide_oembed_author' ) );
		}
	}

	/**
	 * `wp_robots`: noindex the CMS front end.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function noindex( array $robots ): array {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
		return $robots;
	}

	/**
	 * Public front-end requests on the CMS go to the website. wp-admin, login,
	 * REST, cron, CLI and media files never reach template_redirect.
	 */
	public static function redirect_front_end(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_robots() || is_favicon() ) {
			return;
		}
		$target = self::target_for_request();
		if ( null !== $target ) {
			wp_redirect( $target, 301, 'WebSage Headless' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- configured frontend origin.
			exit;
		}
	}

	/**
	 * Where the current front-end request should go, or null to stay.
	 */
	public static function target_for_request(): ?string {
		try {
			$origin = Integration::frontend_origin();
		} catch ( ContentException ) {
			return null;
		}
		// Never redirect to ourselves (same scheme, host and port).
		if ( self::origin_of( $origin ) === self::origin_of( home_url() ) ) {
			return null;
		}
		$object = get_queried_object();
		if ( is_singular() && $object instanceof WP_Post ) {
			$path = Paths::for_post( $object );
			return $origin . ( $path ?? '/' );
		}
		if ( is_home() ) {
			return $origin . '/blog';
		}
		return $origin . '/';
	}

	/**
	 * Scheme, host and port of a URL, lowercased.
	 *
	 * @param string $url URL.
	 */
	private static function origin_of( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$port   = $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 );
		return $scheme . '://' . strtolower( $parts['host'] ?? '' ) . ':' . $port;
	}

	/**
	 * Ends an XML-RPC request: the endpoint is off, including system methods.
	 */
	private static function refuse_xmlrpc(): void {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html__( 'XML-RPC is disabled on this site.', 'websage-headless' );
		exit;
	}

	/**
	 * `wp_headers`: drop X-Pingback.
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public static function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * `rest_pre_dispatch`: anonymous visitors cannot list users. Core matches
	 * routes case-insensitively, so this check does too.
	 *
	 * @param mixed           $result  Result so far.
	 * @param mixed           $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function block_user_listing( mixed $result, mixed $server, WP_REST_Request $request ): mixed {
		unset( $server );
		if ( null === $result && ! is_user_logged_in() && 1 === preg_match( '#^/wp/v2/users(?:/|$)#i', $request->get_route() ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'websage-headless' ), array( 'status' => 401 ) );
		}
		return $result;
	}

	/**
	 * `oembed_response_data`: embeds do not name the author or link to the
	 * (blocked) author archive.
	 *
	 * @param array<string, mixed> $data oEmbed response.
	 * @return array<string, mixed>
	 */
	public static function hide_oembed_author( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}

	/**
	 * Author archives (and ?author=N probes) are not public.
	 */
	public static function block_author_archives(): void {
		if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only probe check.
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
}
