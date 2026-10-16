<?php
/**
 * Navigation menus as public link trees.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Content;

use WP_Post;

/**
 * Normalizes a native menu location into `MenuItem[]`: depth ≤ 3,
 * ≤ 100 items, plain-text labels, contract-safe links. Items pointing at
 * unpublished content are dropped together with their children.
 */
final class Menus {

	public const MAX_DEPTH = 3;
	public const MAX_ITEMS = 100;

	/**
	 * Tree for a location; empty when nothing is assigned.
	 *
	 * @param string $location Menu location.
	 * @return list<array{label: string, href: string, newTab: bool, children: list<mixed>}>
	 */
	public static function tree( string $location ): array {
		$locations = get_nav_menu_locations();
		$menu_id   = (int) ( $locations[ $location ] ?? 0 );
		if ( $menu_id < 1 ) {
			return array();
		}
		$items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
		if ( ! is_array( $items ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $items as $item ) {
			if ( $item instanceof WP_Post ) {
				$by_parent[ (int) self::prop( $item, 'menu_item_parent' ) ][] = $item;
			}
		}
		$count = 0;
		return self::branch( $by_parent, 0, 1, $count );
	}

	/**
	 * Builds one level of the tree.
	 *
	 * @param array<int, list<WP_Post>> $by_parent Items keyed by parent ID.
	 * @param int                       $parent_id Parent item ID.
	 * @param int                       $depth     Current depth.
	 * @param int                       $count     Items emitted so far.
	 * @return list<array{label: string, href: string, newTab: bool, children: list<mixed>}>
	 */
	private static function branch( array $by_parent, int $parent_id, int $depth, int &$count ): array {
		$nodes = array();
		foreach ( $by_parent[ $parent_id ] ?? array() as $item ) {
			if ( $count >= self::MAX_ITEMS ) {
				break;
			}
			$node = self::node( $item );
			if ( null === $node ) {
				continue;
			}
			++$count;
			$node['children'] = $depth < self::MAX_DEPTH ? self::branch( $by_parent, $item->ID, $depth + 1, $count ) : array();
			$nodes[]          = $node;
		}
		return $nodes;
	}

	/**
	 * One item, or null when its target is not public or its link is unsafe.
	 *
	 * @param WP_Post $item Menu item (decorated by wp_setup_nav_menu_item).
	 * @return array{label: string, href: string, newTab: bool, children: list<mixed>}|null
	 */
	private static function node( WP_Post $item ): ?array {
		if ( 'post_type' === self::prop( $item, 'type' ) ) {
			$target = get_post( (int) self::prop( $item, 'object_id' ) );
			if ( ! $target instanceof WP_Post || 'publish' !== $target->post_status || '' !== $target->post_password ) {
				return null;
			}
		}
		$label = trim( wp_strip_all_tags( (string) self::prop( $item, 'title' ) ) );
		$href  = Values::url( Paths::frontend_url_for_menu( $item ) );
		if ( '' === $label || null === $href ) {
			return null;
		}
		return array(
			'label'    => mb_substr( $label, 0, Values::TEXT_MAX ),
			'href'     => $href,
			'newTab'   => '_blank' === self::prop( $item, 'target' ),
			'children' => array(),
		);
	}

	/**
	 * Property added to nav menu items by wp_setup_nav_menu_item().
	 *
	 * @param WP_Post $item Menu item.
	 * @param string  $name Property name.
	 */
	private static function prop( WP_Post $item, string $name ): string {
		$value = get_object_vars( $item )[ $name ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}
}
