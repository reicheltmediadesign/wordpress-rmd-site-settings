<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\AdminAccess;
use RMD\SiteSettings\Domain\Settings;

final class AdminAccessTest extends TestCase {

	public function test_grants_keep_valid_slugs_per_role(): void {
		$grants = AdminAccess::parse_grants(
			[
				'editor'     => [ 'blumenschoene', '', 'options-general.php', 'edit.php?post_type=x', 'wpseo_dashboard', 'blumenschoene' ],
				'author'     => [ 'separator1', 'https://example.com' ],
				'Bad Role'   => [ 'foo' ],
				'subscriber' => 'foo',
			],
			AdminAccess::PAGES
		);
		$this->assertSame( [ 'editor' => [ 'blumenschoene', 'wpseo_dashboard' ] ], $grants );
		$this->assertSame( [], AdminAccess::parse_grants( 'editor', AdminAccess::PAGES ) );
	}

	public function test_post_type_grants_use_post_type_names(): void {
		$grants = AdminAccess::parse_grants(
			[
				'editor' => [ 'blumenstraeusse', 'Blumen', 'a_name_that_is_far_too_long' ],
				'author' => [ 'product' ],
			],
			AdminAccess::POST_TYPES,
			[ 'editor' ]
		);
		$this->assertSame( [ 'editor' => [ 'blumenstraeusse' ] ], $grants );
	}

	public function test_items_of_all_roles_are_combined(): void {
		$grants = [
			'author' => [ 'a', 'b' ],
			'editor' => [ 'b', 'c' ],
		];
		$this->assertSame( [ 'a', 'b', 'c' ], AdminAccess::items( [ 'author', 'editor' ], $grants ) );
		$this->assertSame( [], AdminAccess::items( [ 'subscriber' ], $grants ) );
	}

	public function test_protected_capabilities_are_not_grantable(): void {
		$this->assertTrue( AdminAccess::is_grantable( [ 'manage_options', 'wpcf7_read_contact_forms' ] ) );
		$this->assertFalse( AdminAccess::is_grantable( [ 'manage_options', 'activate_plugins' ] ) );
		$this->assertTrue( AdminAccess::is_protected_cap( 'promote_users' ) );
		$this->assertTrue( AdminAccess::is_protected_option_group( 'general' ) );
		$this->assertTrue( AdminAccess::is_protected_option_group( 'rmd_ss_settings_group' ) );
		$this->assertFalse( AdminAccess::is_protected_option_group( 'blumenschoene_settings' ) );
	}

	public function test_page_is_read_from_admin_urls_only(): void {
		$this->assertSame( 'blumenschoene', AdminAccess::page_from_url( 'https://example.com/wp-admin/admin.php?page=blumenschoene&settings-updated=true' ) );
		$this->assertSame( 'blumenschoene', AdminAccess::page_from_url( '/wp-admin/admin.php?page=blumenschoene' ) );
		$this->assertNull( AdminAccess::page_from_url( '/wp-admin/options-general.php?page=blumenschoene' ) );
		$this->assertNull( AdminAccess::page_from_url( '/wp-admin/admin.php' ) );
		$this->assertNull( AdminAccess::page_from_url( '/wp-admin/admin.php?page=edit.php' ) );
	}

	public function test_menu_title_drops_markup_and_counts(): void {
		$this->assertSame( 'Yoast SEO', AdminAccess::menu_title( 'Yoast SEO <span class="update-plugins count-2"><span class="plugin-count">2</span></span>' ) );
		$this->assertSame( 'Blumen Schöne', AdminAccess::menu_title( 'Blumen Sch&ouml;ne' ) );
	}

	public function test_menu_is_parsed(): void {
		$menu = AdminAccess::parse_menu(
			[
				'blumenschoene' => [
					'title'    => 'Blumen Schöne',
					'caps'     => [ 'manage_options', '', 'manage_options' ],
					'pages'    => [ 'blumenschoene-extra', 'edit.php' ],
					'submenus' => [ 'Extra', 3 ],
				],
				'no-caps'       => [ 'title' => 'x' ],
				'tools.php'     => [ 'caps' => [ 'read' ] ],
			]
		);
		$this->assertSame(
			[
				'blumenschoene' => [
					'title'    => 'Blumen Schöne',
					'caps'     => [ 'manage_options' ],
					'pages'    => [ 'blumenschoene', 'blumenschoene-extra' ],
					'submenus' => [ 'Extra' ],
				],
			],
			$menu
		);
		$this->assertSame( $menu, AdminAccess::parse_menu( $menu ) );
		$this->assertSame( [], AdminAccess::parse_menu( 'x' ) );
	}

	public function test_settings_parse_access(): void {
		$this->assertSame(
			[
				'post_types' => [],
				'pages'      => [],
			],
			Settings::defaults()['access']
		);

		$parsed = Settings::parse(
			[
				'access' => [
					'post_types' => [ 'editor' => [ '', 'blumenstraeusse' ] ],
					'pages'      => [
						'editor' => [ '', 'blumenschoene' ],
						'ghost'  => [ 'blumenschoene' ],
					],
				],
			],
			null,
			null,
			[ 'editor', 'author' ]
		);
		$this->assertSame( [ 'editor' => [ 'blumenstraeusse' ] ], $parsed['access']['post_types'] );
		$this->assertSame( [ 'editor' => [ 'blumenschoene' ] ], $parsed['access']['pages'] );
		$this->assertSame( $parsed, Settings::parse( $parsed ) );
	}
}
