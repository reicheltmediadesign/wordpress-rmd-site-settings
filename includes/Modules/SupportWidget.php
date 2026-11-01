<?php
/**
 * Dashboard widget with the contact details of reichelt media.design. All
 * data and the logo ship with the plugin, nothing is loaded from outside.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class SupportWidget {

	public const ID = 'rmd_ss_support';

	private const COMPANY = 'reichelt media.design';
	private const PHONE   = '01520 5200552';
	private const TEL_URI = 'tel:+4915205200552';
	private const EMAIL   = 'info@reicheltmedia.design';
	private const WEBSITE = 'https://reicheltmedia.design';

	public static function init(): void {
		if ( ! is_admin() || ! Settings::section( 'admin' )['support_widget'] ) {
			return;
		}
		add_action( 'wp_dashboard_setup', [ self::class, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function register(): void {
		wp_add_dashboard_widget(
			self::ID,
			__( 'Need help or support?', 'rmd-site-settings' ),
			[ self::class, 'render' ],
			null,
			null,
			'side',
			'high'
		);
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rmd-ss-support-widget', RMD_SS_URL . 'assets/css/support-widget.css', [ 'dashicons' ], RMD_SS_VERSION );
	}

	public static function render(): void {
		echo '<div class="rmd-ss-support">';

		echo '<div class="rmd-ss-support-brand">';
		printf(
			'<img src="%s" alt="%s" width="193" height="74" class="rmd-ss-support-logo">',
			esc_url( RMD_SS_URL . 'assets/images/rmd-logo.svg' ),
			esc_attr( self::COMPANY )
		);
		printf(
			'<a href="%s" class="rmd-ss-support-info" target="_blank" rel="noopener noreferrer" title="%2$s"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></a>',
			esc_url( self::WEBSITE ),
			/* translators: %s: company name */
			esc_attr( sprintf( __( 'About %s (opens the website in a new tab)', 'rmd-site-settings' ), self::COMPANY ) )
		);
		echo '</div>';

		echo '<p class="rmd-ss-support-intro">' . esc_html__( 'Questions about your website, a change you would like or something is not working? I am happy to help.', 'rmd-site-settings' ) . '</p>';

		echo '<p class="rmd-ss-support-actions">';
		self::button( 'email', __( 'Write an email', 'rmd-site-settings' ), 'mailto:' . self::EMAIL, self::EMAIL, true );
		self::button(
			'phone',
			self::PHONE,
			self::TEL_URI,
			/* translators: %s: phone number */
			sprintf( __( 'Call %s', 'rmd-site-settings' ), self::PHONE )
		);
		echo '</p>';

		echo '</div>';
	}

	private static function button( string $icon, string $text, string $url, string $title, bool $primary = false ): void {
		printf(
			'<a href="%s" class="button%s rmd-ss-support-button" title="%s"><span class="dashicons dashicons-%s" aria-hidden="true"></span>%s</a>',
			esc_url( $url, [ 'mailto', 'tel' ] ),
			$primary ? ' button-primary' : '',
			esc_attr( $title ),
			esc_attr( $icon ),
			esc_html( $text )
		);
	}
}
