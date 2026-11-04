<?php
/**
 * Access levels to the navigation menus per user role.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class MenuAccess {

	public const NONE   = 'none';
	public const READ   = 'read';
	public const WRITE  = 'write';
	public const LEVELS = [ self::NONE, self::READ, self::WRITE ];

	/**
	 * Role => level. Only "read" and "write" are stored; "none" and invalid
	 * entries are dropped.
	 *
	 * @param mixed             $value       Raw value.
	 * @param list<string>|null $known_roles Roles that may be configured; null skips that check.
	 * @return array<string, string>
	 */
	public static function parse_roles( $value, ?array $known_roles = null ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$roles = [];
		foreach ( $value as $role => $level ) {
			if ( ! is_string( $role ) || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $role ) ) {
				continue;
			}
			if ( ! in_array( $level, [ self::READ, self::WRITE ], true ) ) {
				continue;
			}
			if ( null !== $known_roles && ! in_array( $role, $known_roles, true ) ) {
				continue;
			}
			$roles[ $role ] = $level;
		}
		ksort( $roles );
		return $roles;
	}

	/**
	 * The highest level of all roles of a user.
	 *
	 * @param list<string>          $user_roles Roles of the user.
	 * @param array<string, string> $configured Role => level, as returned by parse_roles().
	 */
	public static function level( array $user_roles, array $configured ): string {
		$level = self::NONE;
		foreach ( $user_roles as $role ) {
			$role_level = $configured[ $role ] ?? self::NONE;
			if ( array_search( $role_level, self::LEVELS, true ) > array_search( $level, self::LEVELS, true ) ) {
				$level = $role_level;
			}
		}
		return $level;
	}
}
