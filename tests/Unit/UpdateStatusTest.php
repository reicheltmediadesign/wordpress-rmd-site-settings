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

	public function test_days_since_counts_whole_days(): void {
		$now = gmmktime( 12, 0, 0, 11, 27, 2026 );
		$this->assertSame( 0, UpdateStatus::days_since( gmmktime( 9, 0, 0, 11, 27, 2026 ), $now ) );
		$this->assertSame( 0, UpdateStatus::days_since( gmmktime( 13, 0, 0, 11, 26, 2026 ), $now ) );
		$this->assertSame( 1, UpdateStatus::days_since( gmmktime( 12, 0, 0, 11, 26, 2026 ), $now ) );
		$this->assertSame( 18, UpdateStatus::days_since( gmmktime( 12, 0, 0, 11, 9, 2026 ), $now ) );
		$this->assertSame( 0, UpdateStatus::days_since( gmmktime( 12, 0, 0, 12, 1, 2026 ), $now ) );
	}

	public function test_age_levels_step_from_fresh_to_stale(): void {
		$this->assertSame( 'fresh', UpdateStatus::age_level( 0 ) );
		$this->assertSame( 'fresh', UpdateStatus::age_level( 30 ) );
		$this->assertSame( 'recent', UpdateStatus::age_level( 31 ) );
		$this->assertSame( 'recent', UpdateStatus::age_level( 90 ) );
		$this->assertSame( 'aging', UpdateStatus::age_level( 91 ) );
		$this->assertSame( 'aging', UpdateStatus::age_level( 180 ) );
		$this->assertSame( 'old', UpdateStatus::age_level( 181 ) );
		$this->assertSame( 'old', UpdateStatus::age_level( 365 ) );
		$this->assertSame( UpdateStatus::AGE_STALE, UpdateStatus::age_level( 366 ) );
		$this->assertSame( UpdateStatus::AGE_STALE, UpdateStatus::age_level( 5000 ) );
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
