<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\MenuAccess;
use RMD\SiteSettings\Domain\MenuTree;
use RMD\SiteSettings\Domain\Settings;

final class MenuAccessTest extends TestCase {

	public function test_only_read_and_write_are_kept(): void {
		$roles = MenuAccess::parse_roles(
			[
				'editor'      => 'write',
				'author'      => 'read',
				'contributor' => 'none',
				'subscriber'  => 'admin',
				'Bad Role'    => 'read',
				0             => 'write',
			]
		);
		$this->assertSame(
			[
				'author' => 'read',
				'editor' => 'write',
			],
			$roles
		);
		$this->assertSame( [], MenuAccess::parse_roles( 'write' ) );
	}

	public function test_roles_are_limited_to_known_ones(): void {
		$roles = MenuAccess::parse_roles(
			[
				'editor'        => 'write',
				'administrator' => 'read',
			],
			[ 'editor', 'author' ]
		);
		$this->assertSame( [ 'editor' => 'write' ], $roles );
	}

	public function test_highest_level_of_all_roles_wins(): void {
		$configured = [
			'author' => 'read',
			'editor' => 'write',
		];
		$this->assertSame( MenuAccess::WRITE, MenuAccess::level( [ 'author', 'editor' ], $configured ) );
		$this->assertSame( MenuAccess::READ, MenuAccess::level( [ 'subscriber', 'author' ], $configured ) );
		$this->assertSame( MenuAccess::NONE, MenuAccess::level( [ 'subscriber' ], $configured ) );
		$this->assertSame( MenuAccess::NONE, MenuAccess::level( [], $configured ) );
	}

	public function test_settings_parse_menu_roles(): void {
		$this->assertSame( [], Settings::defaults()['menus']['roles'] );

		$parsed = Settings::parse( [ 'menus' => [ 'roles' => [ 'editor' => 'write', 'author' => 'none' ] ] ], null, [ 'editor', 'author' ] );
		$this->assertSame( [ 'editor' => 'write' ], $parsed['menus']['roles'] );
		$this->assertSame( $parsed, Settings::parse( $parsed ) );
	}

	public function test_tree_nests_items_and_lifts_orphans(): void {
		$children = MenuTree::children(
			[
				10 => 0,
				11 => 10,
				12 => 10,
				13 => 99,
				14 => 14,
				15 => 11,
			]
		);
		$this->assertSame(
			[
				0  => [ 10, 13, 14 ],
				10 => [ 11, 12 ],
				11 => [ 15 ],
			],
			$children
		);
	}
}
