<?php
/**
 * Block type registration.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Blocks;

/**
 * Registers websage/* blocks from compiled metadata in build/.
 */
final class HeroBlock {

	/**
	 * `init` callback.
	 */
	public static function register(): void {
		$build    = WSH_PLUGIN_DIR . '/build';
		$manifest = $build . '/blocks-manifest.php';
		if ( ! is_readable( $manifest ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- missing build is a packaging error.
			error_log( 'websage-headless: build/blocks-manifest.php missing; run the block build.' );
			return;
		}
		wp_register_block_types_from_metadata_collection( $build, $manifest );
	}
}
