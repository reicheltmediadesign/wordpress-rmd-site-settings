<?php
/**
 * The only place that defines the shape of the stored settings. Free of
 * WordPress so it can be tested with plain PHPUnit.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class Settings {

	public const MODE_OFF        = 'off';
	public const MODE_EVERYWHERE = 'everywhere';
	public const MODE_POST_TYPES = 'post_types';
	public const COMMENT_MODES   = [ self::MODE_OFF, self::MODE_EVERYWHERE, self::MODE_POST_TYPES ];

	public const FOOTER_TEXT_MAX = 500;

	/**
	 * @return array{
	 *     comments: array{mode: string, post_types: list<string>, keep_existing: bool},
	 *     xmlrpc: array{disable: bool},
	 *     head: array{emoji: bool, generator: bool, shortlink: bool, oembed_links: bool, rest_link: bool, rsd: bool},
	 *     admin: array{support_widget: bool, welcome_panel: bool, events_news: bool, quick_draft: bool, footer_text: string}
	 * }
	 */
	public static function defaults(): array {
		return [
			'comments' => [
				'mode'          => self::MODE_OFF,
				'post_types'    => [],
				'keep_existing' => false,
			],
			'xmlrpc'   => [
				'disable' => false,
			],
			'head'     => [
				'emoji'        => false,
				'generator'    => false,
				'shortlink'    => false,
				'oembed_links' => false,
				'rest_link'    => false,
				'rsd'          => false,
			],
			'admin'    => [
				'support_widget' => true,
				'welcome_panel'  => false,
				'events_news'    => false,
				'quick_draft'    => false,
				'footer_text'    => '',
			],
		];
	}

	/**
	 * Reads unknown data (stored option or form input). Invalid or missing
	 * values fall back to the default; unknown keys are dropped.
	 *
	 * @param mixed             $raw              Raw value.
	 * @param list<string>|null $known_post_types Post types that may be selected; null skips that check
	 *                                            (post types are not registered yet when the option is read early).
	 * @return array<string, array<string, mixed>>
	 */
	public static function parse( $raw, ?array $known_post_types = null ): array {
		$defaults = self::defaults();
		$raw      = is_array( $raw ) ? $raw : [];
		$result   = [];

		foreach ( $defaults as $section => $section_defaults ) {
			$input = isset( $raw[ $section ] ) && is_array( $raw[ $section ] ) ? $raw[ $section ] : [];
			foreach ( $section_defaults as $key => $default ) {
				$value = $input[ $key ] ?? null;
				if ( is_bool( $default ) ) {
					$result[ $section ][ $key ] = null === $value ? $default : self::bool( $value, $default );
				} elseif ( is_array( $default ) ) {
					$result[ $section ][ $key ] = self::slugs( $value );
				} else {
					$result[ $section ][ $key ] = is_string( $value ) ? $value : $default;
				}
			}
		}

		if ( ! in_array( $result['comments']['mode'], self::COMMENT_MODES, true ) ) {
			$result['comments']['mode'] = $defaults['comments']['mode'];
		}
		if ( null !== $known_post_types ) {
			$result['comments']['post_types'] = array_values( array_intersect( $result['comments']['post_types'], $known_post_types ) );
		}

		$footer = trim( $result['admin']['footer_text'] );
		if ( strlen( $footer ) > self::FOOTER_TEXT_MAX ) {
			$footer = substr( $footer, 0, self::FOOTER_TEXT_MAX );
		}
		$result['admin']['footer_text'] = $footer;

		return $result;
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private static function bool( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || ( is_string( $value ) && in_array( $value, [ '0', '1' ], true ) ) ) {
			return 1 === (int) $value;
		}
		return $fallback;
	}

	/**
	 * Post type names: lowercase letters, digits, "_" and "-", at most 20 characters (WordPress limit).
	 *
	 * @param mixed $value Raw value.
	 * @return list<string>
	 */
	private static function slugs( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$slugs = [];
		foreach ( $value as $item ) {
			if ( is_string( $item ) && 1 === preg_match( '/^[a-z0-9_-]{1,20}$/', $item ) ) {
				$slugs[] = $item;
			}
		}
		return array_values( array_unique( $slugs ) );
	}
}
