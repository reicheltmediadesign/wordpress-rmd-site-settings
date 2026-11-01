<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\Settings;

final class SettingsTest extends TestCase {

	public function test_invalid_input_gives_defaults(): void {
		$this->assertSame( Settings::defaults(), Settings::parse( null ) );
		$this->assertSame( Settings::defaults(), Settings::parse( 'garbage' ) );
		$this->assertSame( Settings::defaults(), Settings::parse( [ 'comments' => 'x', 'head' => [ 'emoji' => 'yes' ] ] ) );
	}

	public function test_form_values_are_converted(): void {
		$parsed = Settings::parse(
			[
				'comments' => [
					'mode'          => 'post_types',
					'post_types'    => [ '', 'post', 'page', 'post' ],
					'keep_existing' => '1',
				],
				'xmlrpc'   => [ 'disable' => '1' ],
				'head'     => [
					'emoji'     => '1',
					'generator' => '0',
				],
				'admin'    => [ 'footer_text' => '  Made by <a href="https://example.com">us</a>  ' ],
			]
		);

		$this->assertSame( 'post_types', $parsed['comments']['mode'] );
		$this->assertSame( [ 'post', 'page' ], $parsed['comments']['post_types'] );
		$this->assertTrue( $parsed['comments']['keep_existing'] );
		$this->assertTrue( $parsed['xmlrpc']['disable'] );
		$this->assertTrue( $parsed['head']['emoji'] );
		$this->assertFalse( $parsed['head']['generator'] );
		$this->assertFalse( $parsed['head']['rsd'] );
		$this->assertSame( 'Made by <a href="https://example.com">us</a>', $parsed['admin']['footer_text'] );
	}

	public function test_unknown_mode_falls_back_to_off(): void {
		$this->assertSame( Settings::MODE_OFF, Settings::parse( [ 'comments' => [ 'mode' => 'sometimes' ] ] )['comments']['mode'] );
	}

	public function test_post_types_are_limited_to_known_ones(): void {
		$parsed = Settings::parse( [ 'comments' => [ 'post_types' => [ 'post', 'gone', 'Bad Type', 'page' ] ] ], [ 'page', 'post' ] );
		$this->assertSame( [ 'post', 'page' ], $parsed['comments']['post_types'] );
	}

	public function test_unknown_keys_are_dropped(): void {
		$parsed = Settings::parse( [ 'other' => [ 'x' => 1 ], 'xmlrpc' => [ 'disable' => true, 'extra' => 1 ] ] );
		$this->assertSame( [ 'disable' => true ], $parsed['xmlrpc'] );
		$this->assertArrayNotHasKey( 'other', $parsed );
	}

	public function test_footer_text_is_limited(): void {
		$parsed = Settings::parse( [ 'admin' => [ 'footer_text' => str_repeat( 'a', 600 ) ] ] );
		$this->assertSame( Settings::FOOTER_TEXT_MAX, strlen( $parsed['admin']['footer_text'] ) );
	}

	public function test_support_widget_is_on_by_default(): void {
		$this->assertTrue( Settings::defaults()['admin']['support_widget'] );
		$this->assertTrue( Settings::parse( [ 'admin' => [ 'welcome_panel' => true ] ] )['admin']['support_widget'] );
		$this->assertFalse( Settings::parse( [ 'admin' => [ 'support_widget' => '0' ] ] )['admin']['support_widget'] );
	}

	public function test_parse_is_idempotent(): void {
		$once = Settings::parse( [ 'comments' => [ 'mode' => 'everywhere' ] ] );
		$this->assertSame( $once, Settings::parse( $once ) );
	}
}
