<?php
/**
 * Turns content changes into revalidation events.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Revalidation;

use WebSage\HeadlessCore\Content\Paths;
use WebSage\HeadlessCore\Content\PostTypes;
use WebSage\HeadlessCore\Content\References;
use WebSage\HeadlessCore\Settings\Settings;
use WP_Post;

/**
 * Hooks content changes and queues events for the Dispatcher. Tags follow
 * the shared grammar: site | posts | routes | page:/path | post:slug.
 */
final class Events {

	/**
	 * Paths of posts before an update, to invalidate old URLs after renames.
	 *
	 * @var array<int, string>
	 */
	private static array $old_paths = array();

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_action( 'pre_post_update', array( self::class, 'remember_path' ), 10, 1 );
		add_action( 'wp_trash_post', array( self::class, 'remember_path' ), 10, 1 );
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 20, 3 );
		add_action( 'save_post', array( self::class, 'on_save' ), 20, 2 );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ), 10, 2 );
		foreach ( array( 'wp_create_nav_menu', 'wp_update_nav_menu', 'wp_delete_nav_menu', 'wp_update_nav_menu_item', 'wp_add_nav_menu_item' ) as $hook ) {
			add_action( $hook, array( self::class, 'on_menu' ) );
		}
		add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), array( self::class, 'on_menu' ) );
		foreach ( array( Settings::OPTION, 'blogname', 'blogdescription', 'show_on_front', 'page_on_front' ) as $option ) {
			add_action( 'update_option_' . $option, array( self::class, 'on_settings' ) );
		}
	}

	/**
	 * Remembers the current path before an edit or trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function remember_path( int $post_id ): void {
		$post = get_post( $post_id );
		// Keep the first path seen: trashing renames the slug mid-request.
		if ( ! isset( self::$old_paths[ $post_id ] ) && $post instanceof WP_Post && 'publish' === $post->post_status ) {
			$path = Paths::for_post( $post );
			if ( null !== $path ) {
				self::$old_paths[ $post_id ] = $path;
			}
		}
	}

	/**
	 * Publish, unpublish and trash.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$event = 'publish' === $new_status ? 'content.updated' : 'content.deleted';
		self::content_event( $post, $event );
	}

	/**
	 * Updates to already-published content (no status change).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		References::index( $post );
	}

	/**
	 * Permanent deletion of published content.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function on_delete( int $post_id, ?WP_Post $post = null ): void {
		$post = $post ?? get_post( $post_id );
		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			self::content_event( $post, 'content.deleted' );
		}
	}

	/**
	 * Menu edits and location assignments.
	 */
	public static function on_menu(): void {
		Dispatcher::queue( 'menu.updated', 'menu', array(), array( 'site' ) );
	}

	/**
	 * Settings and core options that change every page.
	 */
	public static function on_settings(): void {
		Dispatcher::queue( 'settings.updated', 'settings', array(), array( 'site', 'routes', 'posts' ) );
	}

	/**
	 * Queues an event for a changed post of any supported type.
	 *
	 * @param WP_Post $post  Post.
	 * @param string  $event Event name.
	 */
	public static function content_event( WP_Post $post, string $event ): void {
		$paths = array();
		$tags  = array();

		if ( in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			$old = self::$old_paths[ $post->ID ] ?? null;
			unset( self::$old_paths[ $post->ID ] );
			foreach ( array( $old, 'publish' === $post->post_status ? Paths::for_post( $post ) : null ) as $path ) {
				if ( null !== $path ) {
					$paths[] = $path;
					$tags[]  = self::tag_for( $post->post_type, $path );
				}
			}
			$tags[] = 'routes';
			if ( 'post' === $post->post_type ) {
				$tags[] = 'posts';
			}
			Dispatcher::queue( $event, $post->post_type, $paths, $tags );
			return;
		}

		$kind = match ( $post->post_type ) {
			PostTypes::CTA         => array( 'cta', 'cta' ),
			PostTypes::TESTIMONIAL => array( 'testimonial', 'testimonial' ),
			'wp_block'             => array( 'pattern', 'page' ),
			default                => null,
		};
		if ( null === $kind ) {
			return;
		}
		foreach ( References::users( $kind[0] . ':' . $post->ID ) as $user ) {
			$path = Paths::for_post( $user );
			if ( null !== $path ) {
				$paths[] = $path;
				$tags[]  = self::tag_for( $user->post_type, $path );
			}
		}
		if ( array() !== $tags ) {
			Dispatcher::queue( 'content.updated', $kind[1], $paths, $tags );
		}
	}

	/**
	 * Cache tag for a page or post path.
	 *
	 * @param string $type Post type.
	 * @param string $path Frontend path.
	 */
	public static function tag_for( string $type, string $path ): string {
		return 'post' === $type ? 'post:' . substr( $path, strlen( '/blog/' ) ) : 'page:' . $path;
	}
}
