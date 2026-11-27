<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\AppPasswords;
use RMD\SiteSettings\Domain\Settings;

final class AppPasswordsTest extends TestCase {

	public function test_only_valid_role_names_are_kept(): void {
		$roles = AppPasswords::parse_roles( [ '', 'subscriber', 'Bad Role', 'editor', 'subscriber', 5, [ 'author' ] ] );
		$this->assertSame( [ 'editor', 'subscriber' ], $roles );
		$this->assertSame( [], AppPasswords::parse_roles( 'editor' ) );
	}

	public function test_roles_are_limited_to_known_ones(): void {
		$this->assertSame( [ 'editor' ], AppPasswords::parse_roles( [ 'editor', 'gone' ], [ 'editor', 'author' ] ) );
	}

	public function test_long_role_names_are_kept(): void {
		$role = 'a_custom_role_with_a_long_name';
		$this->assertSame( [ $role ], AppPasswords::parse_roles( [ $role ] ) );
	}

	public function test_user_is_disabled_only_when_all_roles_are(): void {
		$disabled = [ 'author', 'editor' ];
		$this->assertTrue( AppPasswords::is_disabled( [ 'editor' ], $disabled ) );
		$this->assertTrue( AppPasswords::is_disabled( [ 'author', 'editor' ], $disabled ) );
		$this->assertFalse( AppPasswords::is_disabled( [ 'editor', 'administrator' ], $disabled ) );
		$this->assertFalse( AppPasswords::is_disabled( [ 'administrator' ], $disabled ) );
		$this->assertFalse( AppPasswords::is_disabled( [], $disabled ) );
		$this->assertFalse( AppPasswords::is_disabled( [ 'editor' ], [] ) );
	}

	public function test_settings_parse_disabled_roles(): void {
		$this->assertSame( [], Settings::defaults()['app_passwords']['disabled_roles'] );

		$parsed = Settings::parse( [ 'app_passwords' => [ 'disabled_roles' => [ '', 'subscriber', 'editor', 'gone' ] ] ], null, null, [ 'editor', 'subscriber' ] );
		$this->assertSame( [ 'editor', 'subscriber' ], $parsed['app_passwords']['disabled_roles'] );
		$this->assertSame( $parsed, Settings::parse( $parsed ) );
	}
}
