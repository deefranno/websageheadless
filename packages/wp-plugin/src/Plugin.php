<?php
/**
 * Plugin composition root.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore;

use DomainException;
use WebSage\HeadlessCore\Blocks\Registry;
use WebSage\HeadlessCore\Rest\Errors;
use WebSage\HeadlessCore\Rest\Routes;
use WebSage\HeadlessCore\Support\Requirements;

/**
 * Wires hooks. Every feature registers here so the plugin's surface is visible in one place.
 */
final class Plugin {

	public const SETTINGS_OPTION         = 'wsh_settings';
	public const SETTINGS_SCHEMA_VERSION = 1;

	/**
	 * Registers runtime hooks, unless the environment is unsupported.
	 */
	public static function register(): void {
		try {
			Requirements::assert_supported();
		} catch ( DomainException $e ) {
			$message = $e->getMessage();
			add_action(
				'admin_notices',
				static function () use ( $message ): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
					}
				}
			);
			return;
		}

		add_action( 'init', array( Registry::class, 'register' ) );
		add_action( 'rest_api_init', array( Routes::class, 'register' ) );
		add_filter( 'rest_post_dispatch', array( Errors::class, 'adapt' ), 10, 3 );
	}

	/**
	 * Activation hook: refuses unsupported environments with a readable message.
	 */
	public static function activate(): void {
		try {
			Requirements::assert_supported();
		} catch ( DomainException $e ) {
			deactivate_plugins( plugin_basename( WSH_PLUGIN_FILE ) );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'Plugin activation failed', 'websage-headless' ), array( 'back_link' => true ) );
		}

		// Versioned settings container; later releases migrate from schema_version.
		add_option( self::SETTINGS_OPTION, array( 'schema_version' => self::SETTINGS_SCHEMA_VERSION ), '', false );
	}
}
