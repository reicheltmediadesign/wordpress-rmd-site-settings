<?php
/**
 * Plugin bootstrap: wires all modules to WordPress hooks.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Only administrators manage the site settings. */
	public const CAP = 'manage_options';

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', [ self::class, 'load_textdomain' ], 1 );
		add_action( 'init', [ Upgrade::class, 'run' ], 5 );

		Updater::init();
		Settings::init();

		Modules\Comments::init();
		Modules\Xmlrpc::init();
		Modules\HeadCleanup::init();
		Modules\AdminArea::init();

		if ( is_admin() ) {
			Modules\CommentCleanup::init();
			Admin\SettingsPage::init();
		}
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'rmd-site-settings', false, dirname( RMD_SS_BASENAME ) . '/languages' );
	}
}
