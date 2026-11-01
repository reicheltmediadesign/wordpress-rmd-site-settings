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
	private const PERSON  = 'Philipp Reichelt';
	private const STREET  = 'Mönchsstraße 13';
	private const CITY    = '09599 Freiberg';
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
			'<img src="%s" alt="" width="48" height="46" class="rmd-ss-support-logo">',
			esc_url( RMD_SS_URL . 'assets/images/rmd-logo.svg' )
		);
		echo '<span class="rmd-ss-support-company">' . esc_html( self::COMPANY ) . '</span>';
		echo '</div>';

		echo '<p class="rmd-ss-support-intro">' . esc_html__( 'Questions about your website, a change you would like or something is not working? I am happy to help.', 'rmd-site-settings' ) . '</p>';

		echo '<p class="rmd-ss-support-address">';
		echo esc_html( self::PERSON ) . '<br>' . esc_html( self::STREET ) . '<br>' . esc_html( self::CITY );
		echo '</p>';

		echo '<ul class="rmd-ss-support-links">';
		self::link_item( 'phone', __( 'Phone', 'rmd-site-settings' ), self::TEL_URI, self::PHONE );
		self::link_item( 'email', __( 'Email', 'rmd-site-settings' ), 'mailto:' . self::EMAIL, self::EMAIL );
		self::link_item( 'admin-site-alt3', __( 'Website', 'rmd-site-settings' ), self::WEBSITE, 'reicheltmedia.design', true );
		echo '</ul>';

		echo '</div>';
	}

	private static function link_item( string $icon, string $label, string $url, string $text, bool $external = false ): void {
		printf(
			'<li><span class="dashicons dashicons-%s" aria-hidden="true"></span><span class="screen-reader-text">%s: </span><a href="%s"%s>%s</a></li>',
			esc_attr( $icon ),
			esc_html( $label ),
			esc_url( $url, [ 'https', 'mailto', 'tel' ] ),
			$external ? ' target="_blank" rel="noopener noreferrer"' : '',
			esc_html( $text )
		);
	}
}
