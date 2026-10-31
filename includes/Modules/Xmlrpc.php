<?php
/**
 * Switches off XML-RPC completely: no methods, no pingback header, no RSD link.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class Xmlrpc {

	public static function init(): void {
		if ( ! Settings::section( 'xmlrpc' )['disable'] ) {
			return;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			add_action( 'init', [ self::class, 'reject_request' ], 1 );
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array', 999 );
		add_filter( 'wp_headers', [ self::class, 'remove_pingback_header' ] );
		add_filter( 'pings_open', '__return_false', 20 );
		add_action( 'init', [ self::class, 'remove_head_link' ] );
	}

	/**
	 * Even with no methods the server would still answer system.listMethods.
	 * wp_die() is no option here: its XML-RPC handler prints nothing before the server exists.
	 */
	public static function reject_request(): void {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html__( 'XML-RPC is disabled on this site.', 'rmd-site-settings' );
		exit;
	}

	/**
	 * @param array<string, string> $headers HTTP headers.
	 * @return array<string, string>
	 */
	public static function remove_pingback_header( $headers ): array {
		$headers = (array) $headers;
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function remove_head_link(): void {
		remove_action( 'wp_head', 'rsd_link' );
	}
}
