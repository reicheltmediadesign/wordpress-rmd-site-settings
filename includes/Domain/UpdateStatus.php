<?php
/**
 * Release dates of the installed plugins and themes as fetched from the
 * WordPress.org directory.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class UpdateStatus {

	/** Listed in the directory; the date is the one of the latest release. */
	public const FOUND = 'found';

	/** Closed in the directory: no further updates. */
	public const CLOSED = 'closed';

	/** Not in the directory. */
	public const MISSING = 'missing';

	public const STATES = [ self::FOUND, self::CLOSED, self::MISSING ];

	/**
	 * Age levels of the latest release, from fresh to stale: name => highest
	 * number of days. Everything older is AGE_STALE. A rough guide for a quick
	 * look, not a verdict on the plugin.
	 */
	public const AGE_LEVELS = [
		'fresh'  => 30,
		'recent' => 90,
		'aging'  => 180,
		'old'    => 365,
	];

	public const AGE_STALE = 'stale';

	/**
	 * Reads the stored release dates (see Modules\Updates::refresh()).
	 *
	 * @param mixed $raw Raw option value.
	 * @return array{checked: int, plugins: array<string, array{state: string, date: int}>, themes: array<string, array{state: string, date: int}>}
	 */
	public static function parse( $raw ): array {
		$raw = is_array( $raw ) ? $raw : [];
		return [
			'checked' => isset( $raw['checked'] ) && is_int( $raw['checked'] ) && $raw['checked'] > 0 ? $raw['checked'] : 0,
			'plugins' => self::parse_entries( $raw['plugins'] ?? null ),
			'themes'  => self::parse_entries( $raw['themes'] ?? null ),
		];
	}

	/**
	 * @return array{state: string, date: int}
	 */
	public static function entry( string $state, int $date = 0 ): array {
		return [
			'state' => $state,
			'date'  => self::FOUND === $state ? max( 0, $date ) : 0,
		];
	}

	/**
	 * Timestamp of a directory date such as "2025-03-11 9:15pm GMT" (plugins)
	 * or "2025-03-11" (themes); 0 for anything else.
	 */
	public static function timestamp( string $value ): int {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp || $timestamp <= 0 ? 0 : $timestamp;
	}

	/**
	 * Whole days between a release and now; 0 for a date in the future.
	 */
	public static function days_since( int $date, int $now ): int {
		return max( 0, intdiv( $now - $date, 86400 ) );
	}

	/**
	 * @return string A key of AGE_LEVELS or AGE_STALE.
	 */
	public static function age_level( int $days ): string {
		foreach ( self::AGE_LEVELS as $level => $max_days ) {
			if ( $days <= $max_days ) {
				return $level;
			}
		}
		return self::AGE_STALE;
	}

	/**
	 * @param mixed $raw Raw value.
	 * @return array<string, array{state: string, date: int}>
	 */
	private static function parse_entries( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$entries = [];
		foreach ( $raw as $slug => $entry ) {
			if ( ! is_string( $slug ) || '' === $slug || strlen( $slug ) > 200 || ! is_array( $entry ) ) {
				continue;
			}
			$state = $entry['state'] ?? null;
			$date  = $entry['date'] ?? 0;
			if ( ! in_array( $state, self::STATES, true ) || ! is_int( $date ) ) {
				continue;
			}
			if ( self::FOUND === $state && $date <= 0 ) {
				continue;
			}
			$entries[ $slug ] = self::entry( $state, $date );
		}
		return $entries;
	}
}
