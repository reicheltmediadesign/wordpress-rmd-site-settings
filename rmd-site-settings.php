<?php
/**
 * Plugin Name:       RMD Site Settings
 * Plugin URI:        https://github.com/reicheltmediadesign/wordpress-rmd-site-settings
 * Description:       Basic site settings in one place: disable comments globally or per post type, switch off XML-RPC, clean up wp_head and tidy the dashboard.
 * Version:           0.2.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Philipp Reichelt, reichelt media.design
 * Author URI:        https://reicheltmedia.design
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rmd-site-settings
 * Domain Path:       /languages
 * Update URI:        https://github.com/reicheltmediadesign/wordpress-rmd-site-settings
 *
 * @package RMD\SiteSettings
 */

// This file must stay parseable by PHP 7.x so the version notice below can be shown instead of a fatal error.

defined( 'ABSPATH' ) || exit;

define( 'RMD_SS_VERSION', '0.2.0' );
define( 'RMD_SS_FILE', __FILE__ );
define( 'RMD_SS_DIR', plugin_dir_path( __FILE__ ) );
define( 'RMD_SS_URL', plugin_dir_url( __FILE__ ) );
define( 'RMD_SS_BASENAME', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'RMD Site Settings requires PHP %1$s or newer. This site runs PHP %2$s, so the plugin stays inactive.', 'rmd-site-settings' ),
					'8.1',
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'RMD\\SiteSettings\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = RMD_SS_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

if ( is_readable( RMD_SS_DIR . 'vendor/autoload.php' ) ) {
	require RMD_SS_DIR . 'vendor/autoload.php';
}

RMD\SiteSettings\Plugin::boot();
