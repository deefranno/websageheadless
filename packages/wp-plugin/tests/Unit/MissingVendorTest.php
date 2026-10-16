<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Loads the real plugin entry file without vendor/ in a child process.
 */
final class MissingVendorTest extends TestCase {

	public function test_missing_dependencies_produce_an_admin_notice_and_activation_message_instead_of_a_fatal(): void {
		$dir = sys_get_temp_dir() . '/wsh-missing-vendor-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		copy( dirname( __DIR__, 2 ) . '/websage-headless-core.php', $dir . '/websage-headless-core.php' );

		$command = sprintf(
			'%s %s %s 2>&1',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( __DIR__ . '/fixtures/missing-vendor-harness.php' ),
			escapeshellarg( $dir . '/websage-headless-core.php' )
		);
		exec( $command, $output, $status );
		unlink( $dir . '/websage-headless-core.php' );
		rmdir( $dir );

		$text = implode( "\n", $output );
		$this->assertSame( 0, $status, $text );
		$this->assertStringContainsString( 'NOTICE: <div class="notice notice-error"><p>WebSage Headless Core is missing its bundled dependencies.', $text );
		$this->assertStringContainsString( 'ACTIVATION: WebSage Headless Core is missing its bundled dependencies.', $text );
		$this->assertStringContainsString( 'PLUGIN CLASS LOADED: no', $text );
	}
}
