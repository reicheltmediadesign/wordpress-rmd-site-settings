<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\CommentScope;
use RMD\SiteSettings\Domain\Settings;

final class CommentScopeTest extends TestCase {

	private static function scope( string $mode, array $post_types = [], bool $keep = false ): CommentScope {
		return new CommentScope(
			[
				'mode'          => $mode,
				'post_types'    => $post_types,
				'keep_existing' => $keep,
			]
		);
	}

	public function test_off_disables_nothing(): void {
		$scope = self::scope( Settings::MODE_OFF, [ 'post' ] );
		$this->assertFalse( $scope->is_active() );
		$this->assertFalse( $scope->is_disabled( 'post' ) );
	}

	public function test_everywhere_disables_every_type(): void {
		$scope = self::scope( Settings::MODE_EVERYWHERE );
		$this->assertTrue( $scope->is_active() );
		$this->assertTrue( $scope->is_everywhere() );
		$this->assertTrue( $scope->is_disabled( 'product' ) );
	}

	public function test_post_types_disables_selected_only(): void {
		$scope = self::scope( Settings::MODE_POST_TYPES, [ 'post' ], true );
		$this->assertTrue( $scope->is_disabled( 'post' ) );
		$this->assertFalse( $scope->is_disabled( 'page' ) );
		$this->assertFalse( $scope->is_everywhere() );
		$this->assertTrue( $scope->keeps_existing() );
		$this->assertSame( [ 'post' ], $scope->disabled_among( [ 'page', 'post', 'attachment' ] ) );
	}
}
