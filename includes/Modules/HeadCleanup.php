<?php
/**
 * Removes optional output from wp_head and the HTTP headers, each part on its own switch.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class HeadCleanup {

	public static function init(): void {
		add_action( 'init', [ self::class, 'apply' ] );
		// The admin hooks are added by wp-admin/includes/admin-filters.php, which loads after init.
		add_action( 'admin_init', [ self::class, 'apply_admin' ] );
	}

	public static function apply_admin(): void {
		if ( Settings::section( 'head' )['emoji'] ) {
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	}

	public static function apply(): void {
		$head = Settings::section( 'head' );

		if ( $head['emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'embed_head', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
			add_filter( 'tiny_mce_plugins', [ self::class, 'remove_tinymce_emoji' ] );
		}

		if ( $head['generator'] ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( $head['shortlink'] ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( $head['oembed_links'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}

		if ( $head['rest_link'] ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}

		if ( $head['rsd'] ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
	}

	/**
	 * @param string[] $plugins TinyMCE plugins.
	 * @return string[]
	 */
	public static function remove_tinymce_emoji( $plugins ): array {
		return array_values( array_diff( (array) $plugins, [ 'wpemoji' ] ) );
	}
}
