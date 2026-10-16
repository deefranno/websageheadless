<?php
/**
 * Plugin composition root.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore;

use DomainException;
use WebSage\HeadlessCore\Admin\Diagnostics;
use WebSage\HeadlessCore\Admin\SubmissionsScreen;
use WebSage\HeadlessCore\Contact\Mailer;
use WebSage\HeadlessCore\Contact\Schema;
use WebSage\HeadlessCore\Contact\Submissions;
use WebSage\HeadlessCore\Blocks\Registry;
use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Fields\SeoMetabox;
use WebSage\HeadlessCore\Fields\SettingsPage;
use WebSage\HeadlessCore\Fields\TestimonialMetabox;
use WebSage\HeadlessCore\Preview\AdminLink;
use WebSage\HeadlessCore\Rest\Errors;
use WebSage\HeadlessCore\Rest\Routes;
use WebSage\HeadlessCore\Revalidation\Dispatcher;
use WebSage\HeadlessCore\Revalidation\Events;
use WebSage\HeadlessCore\Seed\Command;
use WebSage\HeadlessCore\Security\Cors;
use WebSage\HeadlessCore\Security\Hardening;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Support\Requirements;

/**
 * Wires hooks. Every feature registers here so the plugin's surface is visible in one place.
 */
final class Plugin {

	public const SETTINGS_OPTION         = Settings::OPTION;
	public const SETTINGS_SCHEMA_VERSION = Settings::SCHEMA_VERSION;

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

		add_action( 'init', array( PostTypes::class, 'register' ) );
		add_action( 'init', array( Registry::class, 'register' ) );
		add_action( 'init', array( Settings::class, 'migrate' ), 5 );
		add_filter( 'use_block_editor_for_post_type', array( PostTypes::class, 'use_block_editor' ), 10, 2 );
		add_filter( 'allowed_block_types_all', array( Registry::class, 'allowed' ), 10, 2 );

		add_action( 'cmb2_admin_init', array( SettingsPage::class, 'register' ) );
		add_action( 'cmb2_admin_init', array( SeoMetabox::class, 'register' ) );
		add_action( 'cmb2_admin_init', array( TestimonialMetabox::class, 'register' ) );
		add_action( 'cmb2_options-page_process_fields_' . SettingsPage::BOX_ID, array( SettingsPage::class, 'capture_before' ) );
		add_action( 'cmb2_save_options-page_fields_' . SettingsPage::BOX_ID, array( SettingsPage::class, 'enforce_contrast' ) );
		add_action( 'admin_notices', array( SettingsPage::class, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( SettingsPage::class, 'assets' ) );
		Diagnostics::register();

		add_action( 'rest_api_init', array( Routes::class, 'register' ) );
		add_filter( 'rest_post_dispatch', array( Errors::class, 'adapt' ), 10, 3 );
		add_filter( 'rest_pre_dispatch', array( PostTypes::class, 'guard_library_rest' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( Cors::class, 'close' ), 9, 3 );

		add_action( 'plugins_loaded', array( Schema::class, 'maybe_install' ) );
		add_action( Mailer::RETRY_HOOK, array( Mailer::class, 'retry' ) );
		add_action( Submissions::RETENTION, array( Submissions::class, 'apply_retention' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		SubmissionsScreen::register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'websage', Command::class );
		}

		Events::register();
		Dispatcher::register();
		AdminLink::register();
		add_action( 'init', array( Hardening::class, 'register' ) );
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
		Settings::migrate();
		Schema::install();
		self::schedule();
	}

	/**
	 * Daily housekeeping: submission retention and expired rate-limit rows.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( Submissions::RETENTION ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Submissions::RETENTION );
		}
	}

	/**
	 * Deactivation: stop scheduled housekeeping (data is kept).
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( Submissions::RETENTION );
		wp_clear_scheduled_hook( Mailer::RETRY_HOOK );
	}
}
