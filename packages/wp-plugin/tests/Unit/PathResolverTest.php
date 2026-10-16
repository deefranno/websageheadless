<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebSage\HeadlessCore\Content\PathResolver;

final class PathResolverTest extends TestCase {

	/**
	 * @dataProvider valid
	 */
	public function test_valid_paths_normalize( string $raw, string $expected ): void {
		$this->assertSame( $expected, PathResolver::normalize( $raw ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function valid(): array {
		return array(
			'single'          => array( 'about', 'about' ),
			'nested'          => array( 'about/team', 'about/team' ),
			'trailing slash'  => array( 'about/team/', 'about/team' ),
			'encoded unicode' => array( rawurlencode( 'café' ), 'café' ),
		);
	}

	/**
	 * @dataProvider invalid
	 */
	public function test_unsafe_ambiguous_or_reserved_paths_are_rejected( string $raw ): void {
		$this->assertNull( PathResolver::normalize( $raw ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid(): array {
		return array(
			'empty'          => array( '' ),
			'traversal'      => array( '../wp-config' ),
			'dot segment'    => array( 'about/./team' ),
			'encoded dots'   => array( '%2e%2e/secret' ),
			'double encoded' => array( '%252e%252e/secret' ),
			'empty segment'  => array( 'about//team' ),
			'backslash'      => array( 'about\\team' ),
			'whitespace'     => array( 'about team' ),
			'null byte'      => array( "about\0" ),
			'query'          => array( 'about?x=1' ),
			'uppercase'      => array( 'About' ),
			'reserved blog'  => array( 'blog/hello' ),
			'reserved api'   => array( 'api' ),
			'too deep'       => array( implode( '/', array_fill( 0, 11, 'a' ) ) ),
			'too long'       => array( str_repeat( 'a', 2049 ) ),
			'invalid utf8'   => array( '%ff' ),
		);
	}
}
