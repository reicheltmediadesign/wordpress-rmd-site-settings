<?php
/**
 * Switches off application passwords per role and lists the existing ones.
 *
 * WordPress asks the filter both when a user signs in with an application
 * password and when it shows the section in the profile, so existing
 * passwords of a switched-off role stop working without being deleted.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Domain\AppPasswords as AppPasswordRoles;
use RMD\SiteSettings\Settings;
use WP_Application_Passwords;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class AppPasswords {

	public static function init(): void {
		if ( [] === Settings::section( 'app_passwords' )['disabled_roles'] ) {
			return;
		}
		add_filter( 'wp_is_application_passwords_available_for_user', [ self::class, 'available_for_user' ], 10, 2 );
	}

	/**
	 * @param bool    $available Whether the user may use application passwords.
	 * @param WP_User $user      The user.
	 */
	public static function available_for_user( $available, $user ): bool {
		if ( ! $available || ! $user instanceof WP_User ) {
			return (bool) $available;
		}
		return ! self::is_disabled_for( $user );
	}

	public static function is_disabled_for( WP_User $user ): bool {
		return AppPasswordRoles::is_disabled(
			array_values( array_map( 'strval', (array) $user->roles ) ),
			Settings::section( 'app_passwords' )['disabled_roles']
		);
	}

	/**
	 * All application passwords of the users of this site.
	 *
	 * @return list<array{user: WP_User, name: string, created: int, last_used: int, disabled: bool}>
	 */
	public static function entries(): array {
		$users = get_users(
			[
				'meta_key'     => WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only on the settings tab.
				'meta_compare' => 'EXISTS',
				'orderby'      => 'display_name',
			]
		);

		$entries = [];
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			$disabled = self::is_disabled_for( $user );
			foreach ( WP_Application_Passwords::get_user_application_passwords( $user->ID ) as $item ) {
				$entries[] = [
					'user'      => $user,
					'name'      => (string) ( $item['name'] ?? '' ),
					'created'   => (int) ( $item['created'] ?? 0 ),
					'last_used' => (int) ( $item['last_used'] ?? 0 ),
					'disabled'  => $disabled,
				];
			}
		}
		return $entries;
	}
}
