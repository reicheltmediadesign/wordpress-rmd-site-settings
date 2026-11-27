<?php
/**
 * Switches off application passwords per role, lists the existing ones and
 * deletes them (admin-post.php?action=rmd_ss_delete_app_passwords).
 *
 * WordPress asks the filter both when a user signs in with an application
 * password and when it shows the section in the profile, so existing
 * passwords of a switched-off role stop working without being deleted.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Admin\SettingsPage;
use RMD\SiteSettings\Domain\AppPasswords as AppPasswordRoles;
use RMD\SiteSettings\Plugin;
use RMD\SiteSettings\Settings;
use WP_Application_Passwords;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class AppPasswords {

	public const ACTION = 'rmd_ss_delete_app_passwords';

	public static function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		}
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
	 * "disabled" also covers passwords that do not work because application
	 * passwords are switched off for the whole site.
	 *
	 * @return list<array{user: WP_User, uuid: string, name: string, created: int, last_used: int, disabled: bool}>
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
			$disabled = ! wp_is_application_passwords_available_for_user( $user );
			foreach ( WP_Application_Passwords::get_user_application_passwords( $user->ID ) as $item ) {
				$uuid = (string) ( $item['uuid'] ?? '' );
				if ( '' === $uuid ) {
					continue;
				}
				$entries[] = [
					'user'      => $user,
					'uuid'      => $uuid,
					'name'      => (string) ( $item['name'] ?? '' ),
					'created'   => (int) ( $item['created'] ?? 0 ),
					'last_used' => (int) ( $item['last_used'] ?? 0 ),
					'disabled'  => $disabled,
				];
			}
		}
		return $entries;
	}

	/**
	 * Field value that identifies one application password in the delete form.
	 */
	public static function key( int $user_id, string $uuid ): string {
		return $user_id . ':' . $uuid;
	}

	/**
	 * Deletes the given application passwords, or all of them. Only passwords
	 * of users of this site that are in the list can be deleted.
	 *
	 * @param list<string>|null $keys Values of key(); null deletes all.
	 * @return array{deleted: int, failed: int}
	 */
	public static function delete( ?array $keys ): array {
		$result = [
			'deleted' => 0,
			'failed'  => 0,
		];
		foreach ( self::entries() as $entry ) {
			$user_id = (int) $entry['user']->ID;
			if ( null !== $keys && ! in_array( self::key( $user_id, $entry['uuid'] ), $keys, true ) ) {
				continue;
			}
			if ( true === WP_Application_Passwords::delete_application_password( $user_id, $entry['uuid'] ) ) {
				++$result['deleted'];
			} else {
				++$result['failed'];
			}
		}
		return $result;
	}

	public static function handle(): void {
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rmd-site-settings' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::ACTION );

		$keys = null;
		if ( ! isset( $_POST['delete_all'] ) ) {
			$keys = isset( $_POST['app_passwords'] ) && is_array( $_POST['app_passwords'] )
				? array_values( array_map( 'sanitize_text_field', wp_unslash( $_POST['app_passwords'] ) ) )
				: [];
			if ( [] === $keys ) {
				self::redirect( [ 'rmd_ss_notice' => 'app_delete_nothing' ] );
			}
		}

		$result = self::delete( $keys );
		self::redirect(
			[
				'rmd_ss_notice'  => $result['failed'] > 0 ? 'app_delete_failed' : 'app_deleted',
				'rmd_ss_deleted' => $result['deleted'],
			]
		);
	}

	/**
	 * @param array<string, string|int> $args Query arguments.
	 */
	private static function redirect( array $args ): never {
		wp_safe_redirect( SettingsPage::url( [ 'tab' => 'security' ] + $args ) );
		exit;
	}
}
