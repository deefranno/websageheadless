<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Integration;

use WebSage\HeadlessCore\Fields\SettingsPage;
use WebSage\HeadlessCore\Settings\Settings;
use WebSage\HeadlessCore\Tests\Integration\Support\ApiTestCase;

final class SettingsSiteTest extends ApiTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( ! cmb2_get_metabox( SettingsPage::BOX_ID ) ) {
			SettingsPage::register();
		}
	}

	/**
	 * Saves the options page exactly as a form submission would.
	 *
	 * @param array<string, mixed> $values Submitted fields.
	 */
	private function save_settings( array $values ): void {
		// CMB2 caches the option per request; each form save is a new request.
		$option = cmb2_options( Settings::OPTION );
		$cache  = new \ReflectionProperty( $option, 'options' );
		$cache->setAccessible( true );
		$cache->setValue( $option, array() );
		$cmb = cmb2_get_metabox( SettingsPage::BOX_ID, Settings::OPTION, 'options-page' );
		$cmb->save_fields( Settings::OPTION, 'options-page', $values );
	}

	public function test_migration_is_idempotent_and_backs_up(): void {
		update_option(
			Settings::OPTION,
			array(
				'schema_version' => 0,
				'announcement'   => 'Hi',
			)
		);
		Settings::migrate();
		Settings::migrate();

		$this->assertSame( 1, get_option( Settings::OPTION )['schema_version'] );
		$this->assertSame( 'Hi', get_option( Settings::OPTION )['announcement'] );
		$this->assertSame(
			array(
				'schema_version' => 0,
				'announcement'   => 'Hi',
			),
			get_option( Settings::OPTION . '_backup_v0' )
		);
	}

	public function test_saving_the_options_page_keeps_the_schema_version_and_reaches_site(): void {
		update_option( Settings::OPTION, array( 'schema_version' => 1 ) );
		$this->save_settings(
			array(
				'site_name'          => 'Acme Studio',
				'primary'            => '#123456',
				'announcement'       => 'Spring offer',
				'announcement_link'  => '/offers',
				'footer_description' => 'Independent expertise.',
				'social'             => array(
					array(
						'label' => 'LinkedIn',
						'url'   => 'https://www.linkedin.com/company/acme',
					),
					array(
						'label' => 'Bad',
						'url'   => 'javascript:alert(1)',
					),
				),
				'contact_email'      => 'hello@example.com',
				'font_heading'       => 'system',
			)
		);

		$this->assertSame( 1, get_option( Settings::OPTION )['schema_version'] );
		$site    = $this->get( '/websage/v1/site' )->get_data()['data'];
		$fixture = self::fixture( 'site-default' )['data'];
		foreach ( array( 'branding', 'header', 'footer', 'menus', 'contact' ) as $group ) {
			$this->assertSame( array_keys( $fixture[ $group ] ), array_keys( $site[ $group ] ), $group );
		}
		$this->assertSame( array_keys( $fixture ), array_keys( $site ) );
		$this->assertSame( 'Acme Studio', $site['name'] );
		$this->assertSame( '#123456', $site['branding']['tokens']['primary'] );
		$this->assertSame( 'system', $site['branding']['fonts']['heading'] );
		$this->assertSame(
			array(
				'label'  => 'Spring offer',
				'href'   => '/offers',
				'newTab' => false,
			),
			$site['header']['announcementLink']
		);
		$this->assertSame(
			array(
				array(
					'label' => 'LinkedIn',
					'url'   => 'https://www.linkedin.com/company/acme',
				),
			),
			$site['footer']['social']
		);
		$this->assertSame( 'hello@example.com', $site['contact']['email'] );
	}

	public function test_invalid_values_are_rejected_not_converted(): void {
		update_option(
			Settings::OPTION,
			array(
				'schema_version' => 1,
				'primary'        => '#174F46',
				'contact_email'  => 'ok@example.com',
				'frontend_url'   => '',
			)
		);
		$this->save_settings(
			array(
				'primary'       => 'red; background:url(x)',
				'contact_email' => 'not-an-email',
				'frontend_url'  => 'http://insecure.example.com/path',
				'font_body'     => 'comic-sans',
			)
		);

		$stored = get_option( Settings::OPTION );
		$this->assertSame( '#174F46', $stored['primary'] );
		$this->assertSame( 'ok@example.com', $stored['contact_email'] );
		$this->assertSame( '', Settings::get( 'frontend_url' ) );
		$this->assertSame( 'montserrat', Settings::get( 'font_body' ) );
		$this->assertNotEmpty( get_transient( 'wsh_settings_notices' ) );
	}

	public function test_unreadable_colour_pairs_are_rolled_back(): void {
		update_option( Settings::OPTION, array( 'schema_version' => 1 ) );
		$this->save_settings(
			array(
				'foreground' => '#EEEEEE',
				'background' => '#FFFFFF',
				'primary'    => '#000000',
			)
		);

		$stored = get_option( Settings::OPTION );
		$this->assertNotSame( '#EEEEEE', $stored['foreground'] ?? '' );
		$this->assertSame( '#000000', $stored['primary'], 'readable changes still save' );
	}

	public function test_menus_are_normalized_link_trees(): void {
		$about = $this->page(
			array(
				'post_name'  => 'about',
				'post_title' => 'About',
			)
		);
		$draft = $this->page(
			array(
				'post_name'   => 'draft',
				'post_status' => 'draft',
			)
		);
		$menu  = wp_create_nav_menu( 'Main' );
		$top   = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-object-id' => $about,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'     => 'Docs',
				'menu-item-url'       => 'https://docs.example.com',
				'menu-item-target'    => '_blank',
				'menu-item-parent-id' => $top,
				'menu-item-status'    => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-object-id' => $draft,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'  => 'Evil',
				'menu-item-url'    => 'javascript:alert(1)',
				'menu-item-status' => 'publish',
			)
		);
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );

		$primary = $this->get( '/websage/v1/site' )->get_data()['data']['menus']['primary'];

		$this->assertSame(
			array(
				array(
					'label'    => 'About',
					'href'     => '/about',
					'newTab'   => false,
					'children' => array(
						array(
							'label'    => 'Docs',
							'href'     => 'https://docs.example.com',
							'newTab'   => true,
							'children' => array(),
						),
					),
				),
			),
			$primary
		);
	}
}
