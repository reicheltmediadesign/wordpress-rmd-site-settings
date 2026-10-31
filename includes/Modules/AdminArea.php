<?php
/**
 * Tidies the dashboard and sets an own admin footer text.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminArea {

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin = Settings::section( 'admin' );

		if ( $admin['welcome_panel'] ) {
			add_action( 'admin_init', [ self::class, 'remove_welcome_panel' ] );
		}
		if ( $admin['events_news'] || $admin['quick_draft'] ) {
			add_action( 'wp_dashboard_setup', [ self::class, 'remove_widgets' ], 20 );
		}
		if ( '' !== $admin['footer_text'] ) {
			add_filter( 'admin_footer_text', [ self::class, 'footer_text' ], 20 );
		}
	}

	public static function remove_welcome_panel(): void {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	public static function remove_widgets(): void {
		$admin = Settings::section( 'admin' );
		if ( $admin['events_news'] ) {
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		}
		if ( $admin['quick_draft'] ) {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		}
	}

	/**
	 * The text was filtered with wp_kses when it was saved.
	 */
	public static function footer_text(): string {
		return '<span id="footer-thankyou">' . wp_kses_post( Settings::section( 'admin' )['footer_text'] ) . '</span>';
	}
}
