<?php
/**
 * "Preview" in wp-admin opens the Next.js site in Draft Mode.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Preview;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Content\Paths;
use WebSage\HeadlessCore\Support\Integration;
use WP_Post;

/**
 * Replaces the core preview link with a nonce-protected admin action that
 * checks capability, picks the editor's latest autosave, issues a 5-minute
 * token and redirects to {frontend}/api/preview.
 */
final class AdminLink {

	public const ACTION = 'wsh_preview';

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_filter( 'preview_post_link', array( self::class, 'link' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * `preview_post_link` filter for pages and posts.
	 *
	 * @param string  $link Core preview link.
	 * @param WP_Post $post Post being previewed.
	 */
	public static function link( string $link, WP_Post $post ): string {
		if ( ! in_array( $post->post_type, array( 'page', 'post' ), true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $link;
		}
		// A raw URL: the editor uses it as-is (wp_nonce_url() would HTML-escape "&").
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'post'     => $post->ID,
				'_wpnonce' => wp_create_nonce( self::ACTION . '_' . $post->ID ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * `admin_post_wsh_preview` handler.
	 */
	public static function handle(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		check_admin_referer( self::ACTION . '_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to preview this item.', 'websage-headless' ), '', array( 'response' => 403 ) );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			wp_die( esc_html__( 'This item no longer exists.', 'websage-headless' ), '', array( 'response' => 404 ) );
		}

		$url = self::target( $post, get_current_user_id() );
		if ( null === $url ) {
			wp_die( esc_html__( 'Preview is not configured. Set the website address in WebSage Settings and WSH_PREVIEW_SECRET on the server.', 'websage-headless' ), '', array( 'response' => 503 ) );
		}
		header( 'Referrer-Policy: no-referrer' );
		header( 'Cache-Control: no-store' );
		wp_redirect( $url, 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- target is the configured frontend origin.
		exit;
	}

	/**
	 * Frontend preview URL for the newest version this user can see.
	 *
	 * @param WP_Post $post    Page or post.
	 * @param int     $user_id Editor.
	 */
	public static function target( WP_Post $post, int $user_id ): ?string {
		$content  = $post;
		$autosave = wp_get_post_autosave( $post->ID, $user_id );
		if ( $autosave instanceof WP_Post && strtotime( $autosave->post_modified_gmt ) >= strtotime( $post->post_modified_gmt ) ) {
			$content = $autosave;
		}

		$path = Paths::for_post( $post );
		if ( null === $path || ( '/' !== $path && '' === $post->post_name ) ) {
			// Unsaved slugs: preview at a stable placeholder path.
			$path = ( 'post' === $post->post_type ? '/blog/' : '/' ) . 'preview-' . $post->ID;
		}

		try {
			$origin = Integration::frontend_origin();
		} catch ( ContentException ) {
			return null;
		}
		$token = Tokens::issue( $content->ID, $post->ID, $path );
		return null === $token ? null : $origin . '/api/preview?token=' . rawurlencode( $token );
	}
}
