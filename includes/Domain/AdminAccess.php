<?php
/**
 * Extra access per user role to post types and admin pages of plugins and
 * themes.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class AdminAccess {

	public const POST_TYPES = 'post_types';
	public const PAGES      = 'pages';

	/**
	 * Capabilities that are never granted, whatever an admin page asks for:
	 * they would let a user manage users, roles, plugins, themes, core
	 * updates or the network and thereby give themselves more rights.
	 */
	public const PROTECTED_CAPS = [
		'do_not_allow',
		'list_users',
		'create_users',
		'edit_users',
		'delete_users',
		'promote_users',
		'remove_users',
		'add_users',
		'create_roles',
		'edit_roles',
		'delete_roles',
		'list_roles',
		'activate_plugins',
		'install_plugins',
		'update_plugins',
		'delete_plugins',
		'edit_plugins',
		'switch_themes',
		'install_themes',
		'update_themes',
		'delete_themes',
		'edit_themes',
		'edit_files',
		'update_core',
		'unfiltered_upload',
		'manage_network',
		'manage_sites',
		'manage_network_users',
		'manage_network_plugins',
		'manage_network_themes',
		'manage_network_options',
		'upgrade_network',
		'setup_network',
	];

	/**
	 * Settings API groups that a granted admin page may never save: the
	 * WordPress settings screens and this plugin's own settings.
	 */
	public const PROTECTED_OPTION_GROUPS = [
		'general',
		'writing',
		'reading',
		'discussion',
		'media',
		'privacy',
		'permalink',
		'options',
		'rmd_ss_settings_group',
	];

	private const SLUG_PATTERNS = [
		self::POST_TYPES => '/^[a-z0-9_-]{1,20}$/',
		self::PAGES      => '/^[A-Za-z0-9_-][A-Za-z0-9_.\/-]{0,199}$/',
	];

	/**
	 * Role => list of post types or admin page slugs. Roles without entries
	 * are dropped.
	 *
	 * @param mixed             $value       Raw value.
	 * @param string            $kind        self::POST_TYPES or self::PAGES.
	 * @param list<string>|null $known_roles Roles that may be configured; null skips that check.
	 * @return array<string, list<string>>
	 */
	public static function parse_grants( $value, string $kind, ?array $known_roles = null ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$pattern = self::SLUG_PATTERNS[ $kind ] ?? self::SLUG_PATTERNS[ self::PAGES ];
		$grants  = [];
		foreach ( $value as $role => $items ) {
			if ( ! is_string( $role ) || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $role ) || ! is_array( $items ) ) {
				continue;
			}
			if ( null !== $known_roles && ! in_array( $role, $known_roles, true ) ) {
				continue;
			}
			$slugs = [];
			foreach ( $items as $item ) {
				if ( is_string( $item ) && 1 === preg_match( $pattern, $item ) && ( self::POST_TYPES === $kind || self::is_page_slug( $item ) ) ) {
					$slugs[] = $item;
				}
			}
			$slugs = array_values( array_unique( $slugs ) );
			if ( [] !== $slugs ) {
				sort( $slugs );
				$grants[ $role ] = $slugs;
			}
		}
		ksort( $grants );
		return $grants;
	}

	/**
	 * Everything granted to at least one of the roles.
	 *
	 * @param list<string>                $user_roles Roles of the user.
	 * @param array<string, list<string>> $grants     As returned by parse_grants().
	 * @return list<string>
	 */
	public static function items( array $user_roles, array $grants ): array {
		$items = [];
		foreach ( $user_roles as $role ) {
			foreach ( $grants[ $role ] ?? [] as $item ) {
				$items[] = $item;
			}
		}
		return array_values( array_unique( $items ) );
	}

	/**
	 * Menu slug of an admin page registered by a plugin or theme
	 * (admin.php?page=…), as opposed to a WordPress screen like "edit.php"
	 * or a URL.
	 */
	public static function is_page_slug( string $slug ): bool {
		return '' !== $slug
			&& ! str_contains( $slug, '.php' )
			&& ! str_contains( $slug, '?' )
			&& ! str_contains( $slug, ':' )
			&& ! str_starts_with( $slug, 'separator' );
	}

	/**
	 * @param list<string> $caps Capabilities an admin page needs.
	 */
	public static function is_grantable( array $caps ): bool {
		return [] === array_intersect( $caps, self::PROTECTED_CAPS );
	}

	public static function is_protected_cap( string $cap ): bool {
		return in_array( $cap, self::PROTECTED_CAPS, true );
	}

	public static function is_protected_option_group( string $group ): bool {
		return in_array( $group, self::PROTECTED_OPTION_GROUPS, true );
	}

	/**
	 * The page slug of an admin.php?page=… URL, null for any other URL.
	 */
	public static function page_from_url( string $url ): ?string {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Domain code is free of WordPress.
		if ( ! is_array( $parts ) || ! isset( $parts['path'], $parts['query'] ) || 'admin.php' !== basename( $parts['path'] ) ) {
			return null;
		}
		parse_str( $parts['query'], $query );
		$page = $query['page'] ?? null;
		return is_string( $page ) && self::is_page_slug( $page ) ? $page : null;
	}

	/**
	 * Plain text of an admin menu title: drops count bubbles like
	 * <span class="update-plugins">3</span> and other markup.
	 */
	public static function menu_title( string $html ): string {
		$text = (string) preg_replace( '#<span[^>]*>.*?</span>#s', '', $html );
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Domain code is free of WordPress.
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Reads the stored list of admin pages (see Modules\AccessManager::capture()).
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<string, array{title: string, caps: list<string>, pages: list<string>, submenus: list<string>}>
	 */
	public static function parse_menu( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$menu = [];
		foreach ( $raw as $slug => $entry ) {
			if ( ! is_string( $slug ) || ! self::is_page_slug( $slug ) || ! is_array( $entry ) ) {
				continue;
			}
			$caps  = self::strings( $entry['caps'] ?? null );
			$pages = array_values( array_filter( self::strings( $entry['pages'] ?? null ), [ self::class, 'is_page_slug' ] ) );
			if ( [] === $caps ) {
				continue;
			}
			$menu[ $slug ] = [
				'title'    => is_string( $entry['title'] ?? null ) && '' !== $entry['title'] ? $entry['title'] : $slug,
				'caps'     => $caps,
				'pages'    => in_array( $slug, $pages, true ) ? $pages : array_merge( [ $slug ], $pages ),
				'submenus' => self::strings( $entry['submenus'] ?? null ),
			];
		}
		return $menu;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return list<string>
	 */
	private static function strings( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_values( array_unique( array_filter( $value, static fn( $item ): bool => is_string( $item ) && '' !== $item ) ) );
	}
}
