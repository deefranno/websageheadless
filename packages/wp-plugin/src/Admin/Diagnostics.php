<?php
/**
 * Integration diagnostics screen.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Admin;

use WebSage\HeadlessCore\Content\ContentException;
use WebSage\HeadlessCore\Revalidation\Dispatcher;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Support\Cmb2Status;
use WebSage\HeadlessCore\Support\Integration;
use WebSage\HeadlessCore\Support\Secrets;
use WebSage\HeadlessCore\Support\Requirements;

/**
 * Read-only health overview for administrators, plus two explicit actions
 * (reachability check, test webhook). Secrets show only "configured".
 */
final class Diagnostics {

	public const SLUG   = 'wsh-diagnostics';
	public const ACTION = 'wsh_diagnostics';
	public const RESULT = 'wsh_diagnostics_result';

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Adds the screen under WebSage Settings.
	 */
	public static function menu(): void {
		add_submenu_page( Settings::OPTION, __( 'Diagnostics', 'websage-headless' ), __( 'Diagnostics', 'websage-headless' ), 'manage_options', self::SLUG, array( self::class, 'render' ) );
	}

	/**
	 * Status rows as label => [ok, detail].
	 *
	 * @return array<string, array{0: bool, 1: string}>
	 */
	public static function checks(): array {
		global $wp_version;
		$cmb2 = Cmb2Status::current();
		try {
			$origin = Integration::frontend_origin();
			$front  = array( true, $origin );
		} catch ( ContentException ) {
			$front = array( false, __( 'Not set. Add the website address under WebSage Settings → Integration.', 'websage-headless' ) );
		}
		$last = get_option( Dispatcher::LAST_OPTION );
		$jobs = Dispatcher::jobs();
		$late = array_filter( $jobs, static fn( $job ) => $job['due'] < time() - 600 );
		$rows = array(
			__( 'PHP version', 'websage-headless' )       => array( version_compare( PHP_VERSION, Requirements::MIN_PHP, '>=' ), PHP_VERSION ),
			__( 'WordPress version', 'websage-headless' ) => array( version_compare( (string) $wp_version, Requirements::MIN_WP, '>=' ), (string) $wp_version ),
			__( 'Plugin version', 'websage-headless' )    => array( true, WSH_VERSION ),
			__( 'CMB2', 'websage-headless' )              => array( null !== $cmb2['version'], sprintf( '%s — %s%s', $cmb2['version'] ?? '?', $cmb2['dir'] ?? '?', $cmb2['bundled'] ? '' : ' ' . __( '(another plugin’s copy, newer than the bundled one)', 'websage-headless' ) ) ),
			__( 'Website address', 'websage-headless' )   => $front,
			__( 'Revalidation endpoint', 'websage-headless' ) => array( null !== Dispatcher::endpoint(), Dispatcher::endpoint() ?? '—' ),
			__( 'Pending webhook retries', 'websage-headless' ) => array( array() === $late, array() === $late ? (string) count( $jobs ) : __( 'Overdue: WP-Cron may not be running. Configure a real cron job.', 'websage-headless' ) ),
			__( 'Last webhook delivery', 'websage-headless' ) => is_array( $last )
				? array( (bool) $last['ok'], sprintf( '%s (HTTP %d) — %s', (string) $last['message'], (int) $last['status'], wp_date( 'Y-m-d H:i:s', (int) $last['time'] ) ) )
				: array( true, __( 'None yet', 'websage-headless' ) ),
		);
		foreach ( array( Secrets::SERVER_API, Secrets::PREVIEW, Secrets::REVALIDATION ) as $name ) {
			$rows[ $name ] = Secrets::configured( $name )
				? array( true, __( 'Configured', 'websage-headless' ) )
				/* translators: %d: minimum secret length. */
				: array( false, sprintf( __( 'Missing or shorter than %d characters', 'websage-headless' ), Secrets::MIN_LENGTH ) );
		}
		return $rows;
	}

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = get_transient( self::RESULT . '_' . get_current_user_id() );
		delete_transient( self::RESULT . '_' . get_current_user_id() );

		echo '<div class="wrap"><h1>' . esc_html__( 'WebSage Diagnostics', 'websage-headless' ) . '</h1>';
		if ( is_array( $result ) ) {
			printf( '<div class="notice notice-%s"><p>%s</p></div>', $result['ok'] ? 'success' : 'error', esc_html( (string) $result['message'] ) );
		}
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( self::checks() as $label => [ $ok, $detail ] ) {
			printf(
				'<tr><th scope="row" style="width:240px">%s</th><td><span aria-hidden="true">%s</span> <span class="screen-reader-text">%s</span> %s</td></tr>',
				esc_html( $label ),
				$ok ? '✅' : '⚠️',
				esc_html( $ok ? __( 'OK:', 'websage-headless' ) : __( 'Problem:', 'websage-headless' ) ),
				esc_html( $detail )
			);
		}
		echo '</tbody></table><p>';
		foreach ( array(
			'reachability' => __( 'Check the website is reachable', 'websage-headless' ),
			'webhook'      => __( 'Send a test refresh', 'websage-headless' ),
		) as $check => $label ) {
			printf(
				'<a class="button" href="%s">%s</a> ',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action' => self::ACTION,
								'check'  => $check,
							),
							admin_url( 'admin-post.php' )
						),
						self::ACTION
					)
				),
				esc_html( $label )
			);
		}
		echo '</p></div>';
	}

	/**
	 * Runs an explicit check and returns to the screen.
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run diagnostics.', 'websage-headless' ), '', array( 'response' => 403 ) );
		}
		$check = isset( $_GET['check'] ) ? sanitize_key( wp_unslash( $_GET['check'] ) ) : '';
		set_transient( self::RESULT . '_' . get_current_user_id(), 'webhook' === $check ? self::webhook() : self::reachability(), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * HEAD request to the frontend.
	 *
	 * @return array{ok: bool, message: string}
	 */
	private static function reachability(): array {
		try {
			$origin = Integration::frontend_origin();
		} catch ( ContentException ) {
			return array(
				'ok'      => false,
				'message' => __( 'The website address is not set.', 'websage-headless' ),
			);
		}
		$response = wp_remote_head(
			$origin . '/',
			array(
				'timeout'     => 5,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				/* translators: %s: error message. */
				'message' => sprintf( __( 'The website could not be reached: %s', 'websage-headless' ), $response->get_error_message() ),
			);
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'ok'      => $status > 0 && $status < 500,
			/* translators: %d: HTTP status. */
			'message' => sprintf( __( 'The website answered with HTTP %d.', 'websage-headless' ), $status ),
		);
	}

	/**
	 * Signed test event.
	 *
	 * @return array{ok: bool, message: string}
	 */
	private static function webhook(): array {
		$result = Dispatcher::test();
		return array(
			'ok'      => $result['ok'],
			'message' => $result['ok']
				? __( 'Test refresh delivered: the website accepted the signed webhook.', 'websage-headless' )
				/* translators: %s: failure detail. */
				: sprintf( __( 'Test refresh failed: %s', 'websage-headless' ), $result['message'] ),
		);
	}
}
