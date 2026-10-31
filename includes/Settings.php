<?php
/**
 * Plugin settings stored in one option, registered with the Settings API.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings;

use RMD\SiteSettings\Domain\CommentScope;
use RMD\SiteSettings\Domain\Settings as SettingsData;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'rmd_ss_settings';
	public const GROUP  = 'rmd_ss_settings_group';

	/** HTML allowed in the admin footer text. */
	private const FOOTER_HTML = [
		'a'      => [
			'href'   => true,
			'target' => true,
			'rel'    => true,
		],
		'strong' => [],
		'em'     => [],
	];

	private static ?array $cache = null;

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'update_option_' . self::OPTION, [ self::class, 'flush' ] );
		add_action( 'add_option_' . self::OPTION, [ self::class, 'flush' ] );
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'default'           => SettingsData::defaults(),
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize' ],
			]
		);
	}

	/**
	 * Every tab of the settings page posts only its own section, so sections
	 * missing from the input keep their stored values.
	 *
	 * @param mixed $value Raw option value.
	 */
	public static function sanitize( $value ): array {
		$value  = is_array( $value ) ? $value : [];
		$merged = array_replace( self::get(), array_intersect_key( $value, SettingsData::defaults() ) );

		$data = SettingsData::parse( $merged, Modules\Comments::commentable_post_types() );

		$data['admin']['footer_text'] = wp_kses( $data['admin']['footer_text'], self::FOOTER_HTML );

		if ( SettingsData::MODE_POST_TYPES === $data['comments']['mode'] && [] === $data['comments']['post_types'] ) {
			add_settings_error( self::OPTION, 'rmd_ss_no_post_types', __( 'No post type selected: comments stay enabled everywhere until you select at least one.', 'rmd-site-settings' ), 'warning' );
		}

		return $data;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get(): array {
		if ( null === self::$cache ) {
			self::$cache = SettingsData::parse( get_option( self::OPTION, [] ) );
		}
		return self::$cache;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function section( string $section ): array {
		return self::get()[ $section ] ?? [];
	}

	public static function comment_scope(): CommentScope {
		return new CommentScope( self::get()['comments'] );
	}

	public static function flush(): void {
		self::$cache = null;
	}
}
