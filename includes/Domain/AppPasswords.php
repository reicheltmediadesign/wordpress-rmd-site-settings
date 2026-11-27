<?php
/**
 * Roles that may not use application passwords.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class AppPasswords {

	/**
	 * @param mixed             $value       Raw value.
	 * @param list<string>|null $known_roles Roles that may be configured; null skips that check.
	 * @return list<string> Sorted role names.
	 */
	public static function parse_roles( $value, ?array $known_roles = null ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$roles = [];
		foreach ( $value as $role ) {
			if ( ! is_string( $role ) || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $role ) ) {
				continue;
			}
			if ( null !== $known_roles && ! in_array( $role, $known_roles, true ) ) {
				continue;
			}
			$roles[] = $role;
		}
		$roles = array_values( array_unique( $roles ) );
		sort( $roles );
		return $roles;
	}

	/**
	 * A user loses application passwords only when every one of their roles is
	 * switched off, in line with WordPress where a user can do what any of
	 * their roles allows. Users without a role are not affected.
	 *
	 * @param list<string> $user_roles     Roles of the user.
	 * @param list<string> $disabled_roles As returned by parse_roles().
	 */
	public static function is_disabled( array $user_roles, array $disabled_roles ): bool {
		return [] !== $user_roles && [] === array_diff( $user_roles, $disabled_roles );
	}
}
