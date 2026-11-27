<?php
/**
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RMD\SiteSettings\Domain\UpdateStatus;

final class UpdateStatusTest extends TestCase {

	public function test_directory_dates_become_timestamps(): void {
		$this->assertSame( gmmktime( 21, 15, 0, 3, 11, 2025 ), UpdateStatus::timestamp( '2025-03-11 9:15pm GMT' ) );
		$this->assertSame( gmmktime( 0, 0, 0, 3, 11, 2025 ), UpdateStatus::timestamp( '2025-03-11' ) );
		$this->assertSame( 0, UpdateStatus::timestamp( '' ) );
		$this->assertSame( 0, UpdateStatus::timestamp( 'yesterday' ) );
	}

	public function test_outdated_after_two_years(): void {
		$now = gmmktime( 0, 0, 0, 11, 27, 2026 );
		$this->assertTrue( UpdateStatus::is_outdated( gmmktime( 0, 0, 0, 11, 1, 2024 ), $now ) );
		$this->assertFalse( UpdateStatus::is_outdated( gmmktime( 0, 0, 0, 12, 15, 2024 ), $now ) );
		$this->assertFalse( UpdateStatus::is_outdated( 0, $now ) );
	}

	public function test_invalid_stored_data_gives_empty_lists(): void {
		$empty = [
			'checked' => 0,
			'plugins' => [],
			'themes'  => [],
		];
		$this->assertSame( $empty, UpdateStatus::parse( null ) );
		$this->assertSame( $empty, UpdateStatus::parse( [ 'checked' => 'now', 'plugins' => 'x' ] ) );
	}

	public function test_stored_entries_are_validated(): void {
		$parsed = UpdateStatus::parse(
			[
				'checked' => 1700000000,
				'plugins' => [
					'polylang'  => [ 'state' => 'found', 'date' => 1690000000 ],
					'gone'      => [ 'state' => 'closed', 'date' => 1690000000 ],
					'private'   => [ 'state' => 'missing', 'date' => 0 ],
					'no-date'   => [ 'state' => 'found', 'date' => 0 ],
					'bad-state' => [ 'state' => 'maybe', 'date' => 1 ],
					'bad-date'  => [ 'state' => 'found', 'date' => '1690000000' ],
					0           => [ 'state' => 'found', 'date' => 1 ],
				],
				'themes'  => [
					'twentytwentyfive' => [ 'state' => 'found', 'date' => 1695000000, 'extra' => 1 ],
				],
			]
		);

		$this->assertSame( 1700000000, $parsed['checked'] );
		$this->assertSame(
			[
				'polylang' => [ 'state' => 'found', 'date' => 1690000000 ],
				'gone'     => [ 'state' => 'closed', 'date' => 0 ],
				'private'  => [ 'state' => 'missing', 'date' => 0 ],
			],
			$parsed['plugins']
		);
		$this->assertSame( [ 'twentytwentyfive' => [ 'state' => 'found', 'date' => 1695000000 ] ], $parsed['themes'] );
		$this->assertSame( $parsed, UpdateStatus::parse( $parsed ) );
	}
}
