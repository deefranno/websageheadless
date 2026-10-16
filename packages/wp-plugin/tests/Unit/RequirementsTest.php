<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Unit;

use DomainException;
use PHPUnit\Framework\TestCase;
use WebSage\HeadlessCore\Support\Requirements;

final class RequirementsTest extends TestCase {

	public function test_minimum_versions_are_accepted(): void {
		Requirements::check( '8.1.0', '7.1.3' );
		Requirements::check( '8.3.12', '7.2' );
		$this->addToAssertionCount( 2 );
	}

	/**
	 * @dataProvider unsupported
	 */
	public function test_unsupported_versions_are_rejected_with_a_readable_message( string $php, string $wp, string $mentions ): void {
		$this->expectException( DomainException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $mentions, '/' ) . '/' );
		Requirements::check( $php, $wp );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function unsupported(): array {
		return array(
			'old php'       => array( '8.0.30', '7.1.3', 'PHP 8.1' ),
			'old wordpress' => array( '8.3.0', '7.0', 'WordPress 7.1.3' ),
		);
	}
}
