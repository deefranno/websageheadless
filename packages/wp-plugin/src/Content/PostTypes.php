<?php
/**
 * Plugin-owned content types and menu locations.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WP_REST_Request;

/**
 * Reusable content libraries (testimonials, calls to action) and the
 * private submission store. None of them has a public URL; their content
 * reaches the frontend only inside page sections.
 */
final class PostTypes {

	public const TESTIMONIAL = 'ws_testimonial';
	public const CTA         = 'ws_cta';
	public const SUBMISSION  = 'ws_submission';

	public const MENU_LOCATIONS = array( 'primary', 'footer' );

	/**
	 * `init` callback.
	 */
	public static function register(): void {
		$library = array(
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			// The block editor needs REST; Rest\LibraryGuard limits it to editors.
			'show_in_rest'        => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type(
			self::TESTIMONIAL,
			array_merge(
				$library,
				array(
					'labels'        => array(
						'name'          => __( 'Testimonials', 'websage-headless' ),
						'singular_name' => __( 'Testimonial', 'websage-headless' ),
						'add_new_item'  => __( 'Add testimonial', 'websage-headless' ),
						'edit_item'     => __( 'Edit testimonial', 'websage-headless' ),
					),
					'menu_icon'     => 'dashicons-format-quote',
					'menu_position' => 21,
					'supports'      => array( 'title', 'editor', 'revisions' ),
				)
			)
		);

		register_post_type(
			self::CTA,
			array_merge(
				$library,
				array(
					'labels'        => array(
						'name'          => __( 'Calls to action', 'websage-headless' ),
						'singular_name' => __( 'Call to action', 'websage-headless' ),
						'add_new_item'  => __( 'Add call to action', 'websage-headless' ),
						'edit_item'     => __( 'Edit call to action', 'websage-headless' ),
					),
					'menu_icon'     => 'dashicons-megaphone',
					'menu_position' => 22,
					'supports'      => array( 'title', 'editor', 'revisions' ),
					'template'      => array( array( 'websage/cta' ) ),
					'template_lock' => 'all',
				)
			)
		);

		register_post_type(
			self::SUBMISSION,
			array(
				'labels'              => array(
					'name'          => __( 'Form messages', 'websage-headless' ),
					'singular_name' => __( 'Form message', 'websage-headless' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-email-alt',
				'menu_position'       => 23,
				'supports'            => array( 'title' ),
				// Only administrators read messages; nobody creates them in wp-admin.
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts'        => 'do_not_allow',
					'edit_posts'          => 'manage_options',
					'edit_others_posts'   => 'manage_options',
					'delete_posts'        => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'read_private_posts'  => 'manage_options',
					'publish_posts'       => 'do_not_allow',
				),
				'map_meta_cap'        => true,
			)
		);

		register_nav_menus(
			array(
				'primary' => __( 'Main navigation', 'websage-headless' ),
				'footer'  => __( 'Footer navigation', 'websage-headless' ),
			)
		);
	}

	/**
	 * Testimonials are a plain quote, so they use the classic editor.
	 *
	 * @param bool   $enabled   Whether to use the block editor.
	 * @param string $post_type Post type.
	 */
	public static function use_block_editor( bool $enabled, string $post_type ): bool {
		return self::TESTIMONIAL === $post_type ? false : $enabled;
	}

	/**
	 * `rest_pre_dispatch`: library routes in wp/v2 are for editors only.
	 *
	 * @param mixed           $result  Response so far.
	 * @param mixed           $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard_library_rest( mixed $result, mixed $server, WP_REST_Request $request ): mixed {
		unset( $server );
		if ( null !== $result || 1 !== preg_match( '#^/wp/v2/(?:' . self::TESTIMONIAL . '|' . self::CTA . ')(?:/|$)#', $request->get_route() ) ) {
			return $result;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return $result;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'websage-headless' ), array( 'status' => rest_authorization_required_code() ) );
	}
}
