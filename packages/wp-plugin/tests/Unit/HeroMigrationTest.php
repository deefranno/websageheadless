<?php
/**
 * @package WebSage\HeadlessCore
 */

declare(strict_types=1);

namespace WebSage\HeadlessCore\Tests\Unit;

use DomainException;
use PHPUnit\Framework\TestCase;
use WebSage\HeadlessCore\Blocks\HeroMigration;

final class HeroMigrationTest extends TestCase {

	public function test_legacy_title_becomes_heading_without_an_editor_save(): void {
		$this->assertSame(
			array(
				'schemaVersion' => 1,
				'eyebrow'       => '',
				'heading'       => 'Legacy',
				'body'          => '',
				'imageId'       => null,
				'primary'       => null,
				'secondary'     => null,
				'theme'         => 'default',
			),
			HeroMigration::normalize(
				array(
					'schemaVersion' => 0,
					'title'         => 'Legacy',
				)
			)
		);
	}

	public function test_current_values_survive_and_missing_version_means_current(): void {
		$current = HeroMigration::normalize(
			array(
				'heading' => 'Current',
				'theme'   => 'brand',
			)
		);
		$this->assertSame( 1, $current['schemaVersion'] );
		$this->assertSame( 'Current', $current['heading'] );
		$this->assertSame( 'brand', $current['theme'] );
	}

	public function test_unknown_theme_falls_back_to_default(): void {
		$this->assertSame( 'default', HeroMigration::normalize( array( 'theme' => 'red; background:url(x)' ) )['theme'] );
	}

	/**
	 * @dataProvider unsupported_versions
	 */
	public function test_future_or_malformed_versions_are_not_reinterpreted( mixed $version ): void {
		$this->expectException( DomainException::class );
		HeroMigration::normalize( array( 'schemaVersion' => $version ) );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function unsupported_versions(): array {
		return array(
			'future'   => array( 99 ),
			'negative' => array( -1 ),
			'string'   => array( '1' ),
		);
	}
}
