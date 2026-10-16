<?php
/**
 * Plugin settings repository.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Settings;

/**
 * Reads the single `wsh_settings` option with code defaults materialized,
 * and migrates stored settings between schema versions.
 *
 * The CMB2 options page (Fields\SettingsPage) writes the same option; CMB2
 * updates only its own keys, so `schema_version` survives every save.
 */
final class Settings {

	public const OPTION         = 'wsh_settings';
	public const SCHEMA_VERSION = 1;

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

	public const HEADING_FONTS = array( 'sora', 'system' );
	public const BODY_FONTS    = array( 'montserrat', 'system' );

	/**
	 * Every setting with its default. Keys match CMB2 field IDs.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_merge(
			array(
				'site_name'                       => '',
				'logo_id'                         => 0,
				'font_heading'                    => 'sora',
				'font_body'                       => 'montserrat',
				'announcement'                    => '',
				'announcement_link'               => '',
				'copyright'                       => '',
				'footer_description'              => '',
				'social'                          => array(),
				'contact_email'                   => '',
				'contact_phone'                   => '',
				'contact_address'                 => '',
				'notification_recipient'          => '',
				'retention_days'                  => 30,
				'delete_submissions_on_uninstall' => false,
				'seo_title_template'              => '{title} | {site}',
				'seo_description'                 => '',
				'seo_og_image_id'                 => 0,
				'organization_name'               => '',
				'frontend_url'                    => '',
				'hardening'                       => true,
				'disable_xmlrpc'                  => true,
				'disable_user_enumeration'        => true,
			),
			self::DEFAULT_TOKENS
		);
	}

	/**
	 * All settings, stored values over defaults, typed and normalized.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = self::defaults();

		foreach ( $values as $key => $fallback ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			$values[ $key ] = self::coerce( $fallback, $stored[ $key ] );
		}
		return $values;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Upgrades stored settings to SCHEMA_VERSION. Idempotent; keeps a backup
	 * of the previous shape before changing anything.
	 */
	public static function migrate(): void {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			add_option( self::OPTION, array( 'schema_version' => self::SCHEMA_VERSION ), '', false );
			return;
		}

		$version = (int) ( $stored['schema_version'] ?? 0 );
		if ( $version >= self::SCHEMA_VERSION ) {
			return;
		}

		update_option( self::OPTION . '_backup_v' . $version, $stored, false );
		// Version 0 (Foundation) held only the version marker; future
		// migrations transform keys here, one version step at a time.
		$stored['schema_version'] = self::SCHEMA_VERSION;
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Casts a stored value to the default's type; unusable values fall back.
	 *
	 * @param mixed $fallback Default value.
	 * @param mixed $value    Stored value.
	 * @return mixed
	 */
	private static function coerce( mixed $fallback, mixed $value ): mixed {
		if ( is_bool( $fallback ) ) {
			return 'on' === $value || true === $value || '1' === $value;
		}
		if ( is_int( $fallback ) ) {
			return is_numeric( $value ) ? (int) $value : $fallback;
		}
		if ( is_array( $fallback ) ) {
			return is_array( $value ) ? $value : $fallback;
		}
		return is_string( $value ) ? $value : $fallback;
	}
}
