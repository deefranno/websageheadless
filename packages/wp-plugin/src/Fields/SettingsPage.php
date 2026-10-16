<?php
/**
 * WebSage settings screen (CMB2 options page).
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Fields;

use CMB2;
use WebSage\HeadlessCore\Settings\Contrast;
use WebSage\HeadlessCore\Settings\Settings;

/**
 * One options page storing every field in the `wsh_settings` option. Tabs
 * are a light progressive enhancement: without JavaScript all sections show.
 */
final class SettingsPage {

	public const BOX_ID = 'wsh_settings_page';

	/**
	 * Tab keys and translated labels.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'branding'    => __( 'Branding', 'websage-headless' ),
			'header'      => __( 'Header', 'websage-headless' ),
			'footer'      => __( 'Footer', 'websage-headless' ),
			'contact'     => __( 'Contact', 'websage-headless' ),
			'seo'         => __( 'SEO', 'websage-headless' ),
			'integration' => __( 'Integration', 'websage-headless' ),
		);
	}
	/** Colour pairs that must stay readable (WCAG AA, 4.5:1). */
	public const CONTRAST_PAIRS = array(
		array( 'foreground', 'background' ),
		array( 'on_primary', 'primary' ),
		array( 'on_muted', 'muted' ),
	);

	/**
	 * Colours stored before the current save, for contrast rollback.
	 *
	 * @var array<string, mixed>
	 */
	private static array $before = array();

	/**
	 * `cmb2_admin_init` callback.
	 */
	public static function register(): void {
		$cmb = new_cmb2_box(
			array(
				'id'           => self::BOX_ID,
				'title'        => __( 'WebSage Settings', 'websage-headless' ),
				'object_types' => array( 'options-page' ),
				'option_key'   => Settings::OPTION,
				'capability'   => 'manage_options',
				'icon_url'     => 'dashicons-admin-site-alt3',
				'position'     => 59,
				'save_button'  => __( 'Save settings', 'websage-headless' ),
				'show_in_rest' => false,
				'classes'      => 'wsh-settings',
			)
		);

		self::branding( $cmb );
		self::header( $cmb );
		self::footer( $cmb );
		self::contact( $cmb );
		self::seo( $cmb );
		self::integration( $cmb );
	}

	/**
	 * Common field arguments for one tab.
	 *
	 * @param string               $tab   Tab key.
	 * @param array<string, mixed> $field Field arguments.
	 * @return array<string, mixed>
	 */
	private static function field( string $tab, array $field ): array {
		return array_merge(
			array(
				'classes'      => 'wsh-tab wsh-tab-' . $tab,
				'show_in_rest' => false,
			),
			$field
		);
	}

	/**
	 * Section heading row for a tab.
	 *
	 * @param CMB2   $cmb Box.
	 * @param string $tab Tab key.
	 * @param string $description Help text.
	 */
	private static function heading( CMB2 $cmb, string $tab, string $description ): void {
		$cmb->add_field(
			self::field(
				$tab,
				array(
					'id'   => 'wsh_heading_' . $tab,
					'name' => self::tabs()[ $tab ],
					'desc' => $description,
					'type' => 'title',
				)
			)
		);
	}

	/**
	 * Text field shorthand.
	 *
	 * @param string $tab  Tab key.
	 * @param string $id   Field ID.
	 * @param string $name Label.
	 * @param string $desc Help text.
	 * @return array<string, mixed>
	 */
	private static function text( string $tab, string $id, string $name, string $desc = '' ): array {
		return self::field(
			$tab,
			array(
				'id'              => $id,
				'name'            => $name,
				'desc'            => $desc,
				'type'            => 'text',
				'sanitization_cb' => array( Sanitize::class, 'text' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
				'attributes'      => array( 'maxlength' => 500 ),
			)
		);
	}

	/**
	 * Textarea shorthand.
	 *
	 * @param string $tab  Tab key.
	 * @param string $id   Field ID.
	 * @param string $name Label.
	 * @return array<string, mixed>
	 */
	private static function textarea( string $tab, string $id, string $name ): array {
		return self::field(
			$tab,
			array(
				'id'              => $id,
				'name'            => $name,
				'type'            => 'textarea_small',
				'sanitization_cb' => array( Sanitize::class, 'textarea' ),
				'escape_cb'       => array( Sanitize::class, 'esc_textarea' ),
				'attributes'      => array( 'maxlength' => 500 ),
			)
		);
	}

	/**
	 * Image picker shorthand; CMB2 stores the attachment ID in `{id}_id`.
	 *
	 * @param string $tab  Tab key.
	 * @param string $id   Field ID.
	 * @param string $name Label.
	 * @return array<string, mixed>
	 */
	private static function image( string $tab, string $id, string $name ): array {
		return self::field(
			$tab,
			array(
				'id'              => $id,
				'name'            => $name,
				'type'            => 'file',
				'options'         => array( 'url' => false ),
				'query_args'      => array( 'type' => array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' ) ),
				'preview_size'    => 'medium',
				'sanitization_cb' => array( Sanitize::class, 'image' ),
				'escape_cb'       => array( Sanitize::class, 'esc_url' ),
			)
		);
	}

	/**
	 * Checkbox shorthand.
	 *
	 * @param string $tab  Tab key.
	 * @param string $id   Field ID.
	 * @param string $name Label.
	 * @param string $desc Help text.
	 * @return array<string, mixed>
	 */
	private static function checkbox( string $tab, string $id, string $name, string $desc ): array {
		return self::field(
			$tab,
			array(
				'id'              => $id,
				'name'            => $name,
				'desc'            => $desc,
				'type'            => 'checkbox',
				'default_cb'      => static fn() => Settings::defaults()[ $id ] ? 'on' : '',
				'sanitization_cb' => array( Sanitize::class, 'checkbox' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
			)
		);
	}

	/**
	 * Branding tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function branding( CMB2 $cmb ): void {
		self::heading( $cmb, 'branding', __( 'Logo, fonts and brand colours. Changes restyle the whole site.', 'websage-headless' ) );
		$cmb->add_field( self::text( 'branding', 'site_name', __( 'Site name', 'websage-headless' ), __( 'Leave empty to use the WordPress site title.', 'websage-headless' ) ) );
		$cmb->add_field( self::image( 'branding', 'logo', __( 'Logo', 'websage-headless' ) ) );

		foreach (
			array(
				'font_heading' => array(
					__( 'Heading font', 'websage-headless' ),
					array(
						'sora'   => 'Sora',
						'system' => __( 'System font', 'websage-headless' ),
					),
				),
				'font_body'    => array(
					__( 'Body font', 'websage-headless' ),
					array(
						'montserrat' => 'Montserrat',
						'system'     => __( 'System font', 'websage-headless' ),
					),
				),
			) as $id => [ $name, $options ]
		) {
			$cmb->add_field(
				self::field(
					'branding',
					array(
						'id'              => $id,
						'name'            => $name,
						'type'            => 'select',
						'options'         => $options,
						'default'         => Settings::defaults()[ $id ],
						'sanitization_cb' => array( Sanitize::class, 'choice' ),
						'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
					)
				)
			);
		}

		$labels = array(
			'primary'    => __( 'Primary', 'websage-headless' ),
			'on_primary' => __( 'Text on primary', 'websage-headless' ),
			'background' => __( 'Background', 'websage-headless' ),
			'foreground' => __( 'Text', 'websage-headless' ),
			'muted'      => __( 'Muted background', 'websage-headless' ),
			'on_muted'   => __( 'Text on muted', 'websage-headless' ),
			'border'     => __( 'Borders', 'websage-headless' ),
			'focus'      => __( 'Focus outline', 'websage-headless' ),
		);
		foreach ( $labels as $id => $name ) {
			$cmb->add_field(
				self::field(
					'branding',
					array(
						'id'              => $id,
						'name'            => $name,
						'type'            => 'colorpicker',
						'default'         => Settings::DEFAULT_TOKENS[ $id ],
						'attributes'      => array( 'data-colorpicker' => wp_json_encode( array( 'palettes' => false ) ) ),
						'sanitization_cb' => array( Sanitize::class, 'color' ),
						'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
					)
				)
			);
		}
	}

	/**
	 * Header tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function header( CMB2 $cmb ): void {
		self::heading( $cmb, 'header', __( 'The bar above the navigation. Menus are edited under Appearance → Menus.', 'websage-headless' ) );
		$cmb->add_field( self::text( 'header', 'announcement', __( 'Announcement text', 'websage-headless' ), __( 'Leave empty to hide the bar.', 'websage-headless' ) ) );
		$cmb->add_field(
			self::field(
				'header',
				array(
					'id'              => 'announcement_link',
					'name'            => __( 'Announcement link', 'websage-headless' ),
					'desc'            => __( 'A page path such as /offers, or an https:// address.', 'websage-headless' ),
					'type'            => 'text',
					'sanitization_cb' => array( Sanitize::class, 'link' ),
					'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
				)
			)
		);
	}

	/**
	 * Footer tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function footer( CMB2 $cmb ): void {
		self::heading( $cmb, 'footer', __( 'Footer text and social links.', 'websage-headless' ) );
		$cmb->add_field( self::textarea( 'footer', 'footer_description', __( 'Short description', 'websage-headless' ) ) );
		$cmb->add_field( self::text( 'footer', 'copyright', __( 'Copyright holder', 'websage-headless' ) ) );

		$group = $cmb->add_field(
			self::field(
				'footer',
				array(
					'id'         => 'social',
					'name'       => __( 'Social links', 'websage-headless' ),
					'type'       => 'group',
					'repeatable' => true,
					'options'    => array(
						'group_title'   => __( 'Link {#}', 'websage-headless' ),
						'add_button'    => __( 'Add link', 'websage-headless' ),
						'remove_button' => __( 'Remove', 'websage-headless' ),
						'sortable'      => true,
					),
				)
			)
		);
		if ( false === $group ) {
			return;
		}
		$cmb->add_group_field(
			$group,
			array(
				'id'              => 'label',
				'name'            => __( 'Label', 'websage-headless' ),
				'type'            => 'text',
				'sanitization_cb' => array( Sanitize::class, 'text' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
			)
		);
		$cmb->add_group_field(
			$group,
			array(
				'id'              => 'url',
				'name'            => __( 'Address', 'websage-headless' ),
				'type'            => 'text',
				'sanitization_cb' => array( Sanitize::class, 'link' ),
				'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
			)
		);
	}

	/**
	 * Contact tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function contact( CMB2 $cmb ): void {
		self::heading( $cmb, 'contact', __( 'Public contact details, and where contact form messages go.', 'websage-headless' ) );
		foreach (
			array(
				'contact_email'          => array( __( 'Public email', 'websage-headless' ), __( 'Shown on the website.', 'websage-headless' ) ),
				'notification_recipient' => array( __( 'Send form messages to', 'websage-headless' ), __( 'Private; never shown on the website.', 'websage-headless' ) ),
			) as $id => [ $name, $desc ]
		) {
			$cmb->add_field(
				self::field(
					'contact',
					array(
						'id'              => $id,
						'name'            => $name,
						'desc'            => $desc,
						'type'            => 'text_email',
						'sanitization_cb' => array( Sanitize::class, 'email' ),
						'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
					)
				)
			);
		}
		$cmb->add_field( self::text( 'contact', 'contact_phone', __( 'Phone', 'websage-headless' ) ) );
		$cmb->add_field( self::textarea( 'contact', 'contact_address', __( 'Address', 'websage-headless' ) ) );
		$cmb->add_field(
			self::field(
				'contact',
				array(
					'id'              => 'retention_days',
					'name'            => __( 'Keep form messages for (days)', 'websage-headless' ),
					'type'            => 'text_small',
					'default'         => '30',
					'attributes'      => array(
						'type' => 'number',
						'min'  => 1,
						'max'  => 365,
					),
					'sanitization_cb' => array( Sanitize::class, 'bounded_int' ),
					'escape_cb'       => array( Sanitize::class, 'esc_attr' ),
				)
			)
		);
		$cmb->add_field( self::checkbox( 'contact', 'delete_submissions_on_uninstall', __( 'Delete messages on uninstall', 'websage-headless' ), __( 'Remove stored form messages if the plugin is deleted.', 'websage-headless' ) ) );
	}

	/**
	 * SEO tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function seo( CMB2 $cmb ): void {
		self::heading( $cmb, 'seo', __( 'Defaults used when a page or post has no SEO fields of its own.', 'websage-headless' ) );
		$cmb->add_field( self::text( 'seo', 'seo_title_template', __( 'Title template', 'websage-headless' ), __( 'Use {title} for the page title and {site} for the site name.', 'websage-headless' ) ) );
		$cmb->add_field( self::textarea( 'seo', 'seo_description', __( 'Default description', 'websage-headless' ) ) );
		$cmb->add_field( self::image( 'seo', 'seo_og_image', __( 'Default social sharing image', 'websage-headless' ) ) );
		$cmb->add_field( self::text( 'seo', 'organization_name', __( 'Organization name', 'websage-headless' ) ) );
	}

	/**
	 * Integration tab.
	 *
	 * @param CMB2 $cmb Box.
	 */
	private static function integration( CMB2 $cmb ): void {
		self::heading( $cmb, 'integration', __( 'Connection to the Next.js website. Secrets are set in the server environment, never here.', 'websage-headless' ) );
		$cmb->add_field(
			self::field(
				'integration',
				array(
					'id'              => 'frontend_url',
					'name'            => __( 'Website address', 'websage-headless' ),
					'desc'            => __( 'The public https:// origin of the Next.js site. A WSH_FRONTEND_ORIGIN constant overrides this.', 'websage-headless' ),
					'type'            => 'text_url',
					'protocols'       => array( 'https' ),
					'sanitization_cb' => array( Sanitize::class, 'https_origin' ),
					'escape_cb'       => array( Sanitize::class, 'esc_url' ),
				)
			)
		);
		$cmb->add_field( self::checkbox( 'integration', 'hardening', __( 'Hide this WordPress site from search engines', 'websage-headless' ), __( 'Sends visitors of the WordPress front end to the website and marks it noindex. Admin, login, REST API and media are unaffected.', 'websage-headless' ) ) );
		$cmb->add_field( self::checkbox( 'integration', 'disable_xmlrpc', __( 'Disable XML-RPC', 'websage-headless' ), __( 'Recommended unless a mobile app or remote publishing tool needs it.', 'websage-headless' ) ) );
		$cmb->add_field( self::checkbox( 'integration', 'disable_user_enumeration', __( 'Hide user accounts from the public', 'websage-headless' ), __( 'Blocks anonymous access to the REST user list and author archives.', 'websage-headless' ) ) );
	}

	/**
	 * Remembers colours before CMB2 saves.
	 */
	public static function capture_before(): void {
		self::$before = Settings::all();
	}

	/**
	 * Restores colour pairs whose new contrast falls below WCAG AA.
	 */
	public static function enforce_contrast(): void {
		$stored = get_option( Settings::OPTION, array() );
		if ( ! is_array( $stored ) || array() === self::$before ) {
			return;
		}
		$values  = Settings::all();
		$changed = false;
		foreach ( self::CONTRAST_PAIRS as [ $text, $surface ] ) {
			$ratio = Contrast::ratio( (string) $values[ $text ], (string) $values[ $surface ] );
			if ( $ratio >= Contrast::AA_NORMAL ) {
				continue;
			}
			$stored[ $text ]    = self::$before[ $text ];
			$stored[ $surface ] = self::$before[ $surface ];
			$changed            = true;

			$notices   = get_transient( Sanitize::NOTICE_TRANSIENT );
			$notices   = is_array( $notices ) ? $notices : array();
			$notices[] = sprintf(
				/* translators: 1: text colour setting, 2: surface colour setting, 3: contrast ratio. */
				__( 'Colours "%1$s" and "%2$s" were not saved: their contrast is %3$s:1, below the 4.5:1 needed for readable text.', 'websage-headless' ),
				$text,
				$surface,
				number_format_i18n( $ratio, 2 )
			);
			set_transient( Sanitize::NOTICE_TRANSIENT, $notices, MINUTE_IN_SECONDS );
		}
		if ( $changed ) {
			// Through CMB2's option object so its in-request cache stays in sync.
			cmb2_options( Settings::OPTION )->set( $stored );
		}
	}

	/**
	 * Shows queued validation notices on the settings screen.
	 */
	public static function notices(): void {
		$notices = get_transient( Sanitize::NOTICE_TRANSIENT );
		if ( ! is_array( $notices ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( Sanitize::NOTICE_TRANSIENT );
		foreach ( $notices as $notice ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( (string) $notice ) );
		}
	}

	/**
	 * Tab navigation and light styling, only on this screen.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . Settings::OPTION !== $hook ) {
			return;
		}
		wp_register_style( 'wsh-admin', false, array(), WSH_VERSION );
		wp_enqueue_style( 'wsh-admin' );
		wp_add_inline_style(
			'wsh-admin',
			'.wsh-tabs{display:flex;gap:4px;margin:16px 0 0;border-bottom:1px solid #c3c4c7}'
			. '.wsh-tabs button{border:1px solid transparent;border-bottom:0;background:none;padding:8px 14px;font-size:14px;cursor:pointer;border-radius:4px 4px 0 0}'
			. '.wsh-tabs button[aria-selected=true]{background:#fff;border-color:#c3c4c7;margin-bottom:-1px;font-weight:600}'
			. '.wsh-settings .cmb-row{padding:14px 0}.wsh-settings .cmb-type-title{border-bottom:0}'
			. '.wsh-settings .cmb2-wrap{max-width:860px}'
		);

		wp_register_script( 'wsh-admin', false, array(), WSH_VERSION, true );
		wp_enqueue_script( 'wsh-admin' );
		$tabs = wp_json_encode( self::tabs() );
		wp_add_inline_script(
			'wsh-admin',
			"(function(){var tabs={$tabs};var form=document.querySelector('.wsh-settings');if(!form)return;"
			. "var nav=document.createElement('div');nav.className='wsh-tabs';nav.setAttribute('role','tablist');"
			. "function show(key){form.querySelectorAll('.wsh-tab').forEach(function(row){row.hidden=!row.classList.contains('wsh-tab-'+key);});"
			. "nav.querySelectorAll('button').forEach(function(b){b.setAttribute('aria-selected',String(b.dataset.tab===key));});"
			. "try{sessionStorage.setItem('wsh-tab',key);}catch(e){}}"
			. "Object.keys(tabs).forEach(function(key){var b=document.createElement('button');b.type='button';b.setAttribute('role','tab');b.dataset.tab=key;b.textContent=tabs[key];b.addEventListener('click',function(){show(key);});nav.appendChild(b);});"
			. "form.parentNode.insertBefore(nav,form);var start='branding';try{start=sessionStorage.getItem('wsh-tab')||start;}catch(e){}show(tabs[start]?start:'branding');})();"
		);
	}
}
