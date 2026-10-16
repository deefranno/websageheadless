<?php
/**
 * WP-CLI command: wp websage seed.
 *
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Seed;

use WP_CLI;

/**
 * Loads the demonstration site.
 */
final class Command {

	/**
	 * Creates or updates the demo pages, posts, testimonials, CTA, menus and
	 * settings, and sets the front page. Safe to run more than once: items are
	 * matched by seed ID and updated, never duplicated.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation. Demo settings and the two demo menus are overwritten.
	 *
	 * ## EXAMPLES
	 *
	 *     wp websage seed --yes
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public function seed( array $args, array $assoc_args ): void {
		unset( $args );
		WP_CLI::confirm( 'This overwrites demo settings and menus and sets the front page. Continue?', $assoc_args );
		$stats = ( new Seeder() )->run();
		WP_CLI::success( sprintf( 'Demo content ready: %d created, %d updated.', $stats['created'], $stats['updated'] ) );
	}
}
