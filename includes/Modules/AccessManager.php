<?php
/**
 * Gives roles access to post types and to admin pages of plugins and themes
 * they could not open otherwise.
 *
 * Post types: the primitive capabilities of the post type object are granted
 * everywhere (lists, editor, REST API), like a role editor would.
 *
 * Admin pages (admin.php?page=…): the capabilities the page and its submenu
 * pages ask for are granted only
 *   - while the admin menu is built, so the pages register their callbacks,
 *   - in requests for one of these pages,
 *   - when the page's Settings API form is saved via options.php.
 * Afterwards the menu items are switched to "read" so they stay in the menu,
 * and everything else that became visible is removed again. Capabilities in
 * Domain\AdminAccess::PROTECTED_CAPS are never granted.
 *
 * Which capabilities a page needs is only known once the menu is built, so the
 * menu as an administrator sees it is stored in an option (capture()).
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Domain\AdminAccess;
use RMD\SiteSettings\Plugin;
use RMD\SiteSettings\Settings;
use WP_Post_Type;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class AccessManager {

	public const MENU_OPTION = 'rmd_ss_admin_menu';

	/** Capabilities of a post type object that are no primitive ones. */
	private const POST_TYPE_META_CAPS = [ 'edit_post', 'read_post', 'delete_post', 'read' ];

	/** Admin pages of this plugin that are never offered. */
	private const OWN_PAGES = [ MenuManager::READ_SLUG ];

	private static bool $building_menu = false;

	private static bool $suspended = false;

	/** @var array<int, array{post_type_caps: list<string>, menus: list<string>, pages: list<string>, page_caps: list<string>}> */
	private static array $cache = [];

	private static ?string $requested_page = null;

	private static bool $page_resolved = false;

	public static function init(): void {
		$settings  = Settings::section( 'access' );
		$has_types = [] !== $settings['post_types'];
		$has_pages = [] !== $settings['pages'] && is_admin();

		if ( $has_types || $has_pages ) {
			add_filter( 'user_has_cap', [ self::class, 'grant' ], 10, 4 );
		}
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', [ self::class, 'capture' ], PHP_INT_MAX );
		if ( $has_pages ) {
			add_action( 'admin_menu', [ self::class, 'menu_start' ], PHP_INT_MIN );
			add_action( 'admin_menu', [ self::class, 'menu_end' ], PHP_INT_MAX );
			add_action( 'admin_init', [ self::class, 'hide_menu_items' ] );
		}
	}

	/**
	 * Admin pages of plugins and themes as an administrator last saw them.
	 *
	 * @return array<string, array{title: string, caps: list<string>, pages: list<string>, submenus: list<string>}>
	 */
	public static function menu(): array {
		return AdminAccess::parse_menu( get_option( self::MENU_OPTION, [] ) );
	}

	/**
	 * Post types with an own entry in the admin menu that are not part of
	 * WordPress. Internal types of plugins (show_in_menu false) are left out.
	 *
	 * @return array<string, WP_Post_Type> Name => object, sorted by label.
	 */
	public static function post_types(): array {
		$types = array_filter(
			get_post_types(
				[
					'show_ui'  => true,
					'_builtin' => false,
				],
				'objects'
			),
			static fn( WP_Post_Type $type ): bool => false !== $type->show_in_menu
		);
		uasort( $types, static fn( WP_Post_Type $a, WP_Post_Type $b ): int => strnatcasecmp( (string) $a->labels->name, (string) $b->labels->name ) );
		return $types;
	}

	/**
	 * Primitive capabilities that give access to a post type.
	 *
	 * @return list<string>
	 */
	public static function post_type_caps( WP_Post_Type $type ): array {
		$caps = array_diff_key( (array) $type->cap, array_flip( self::POST_TYPE_META_CAPS ) );
		return array_values( array_unique( array_filter( array_map( 'strval', $caps ), static fn( string $cap ): bool => ! AdminAccess::is_protected_cap( $cap ) ) ) );
	}

	/**
	 * Stores the admin pages registered by plugins and themes with the
	 * capabilities they need. Runs for administrators only and writes only
	 * when something changed.
	 */
	public static function capture(): void {
		global $menu, $submenu;

		if ( is_network_admin() || is_user_admin() || ! self::natively_can( Plugin::CAP ) ) {
			return;
		}

		$captured = [];
		foreach ( (array) $menu as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			if ( ! AdminAccess::is_page_slug( $slug ) || in_array( $slug, self::OWN_PAGES, true ) ) {
				continue;
			}
			$title = AdminAccess::menu_title( (string) ( $item[0] ?? '' ) );
			$caps  = [ (string) ( $item[1] ?? '' ) ];
			$pages = [ $slug ];
			$subs  = [];
			foreach ( $submenu[ $slug ] ?? [] as $sub ) {
				$sub_slug = (string) ( $sub[2] ?? '' );
				if ( ! AdminAccess::is_page_slug( $sub_slug ) ) {
					continue;
				}
				$caps[]    = (string) ( $sub[1] ?? '' );
				$pages[]   = $sub_slug;
				$sub_title = AdminAccess::menu_title( (string) ( $sub[0] ?? '' ) );
				if ( '' !== $sub_title && $sub_title !== $title ) {
					$subs[] = $sub_title;
				}
			}
			$captured[ $slug ] = [
				'title'    => '' !== $title ? $title : $slug,
				'caps'     => array_values( array_unique( array_filter( $caps ) ) ),
				'pages'    => array_values( array_unique( $pages ) ),
				'submenus' => array_values( array_unique( $subs ) ),
			];
		}
		$captured = AdminAccess::parse_menu( $captured );

		if ( self::menu() !== $captured ) {
			update_option( self::MENU_OPTION, $captured, true );
		}
	}

	/**
	 * @param array<string, bool> $allcaps Capabilities of the user.
	 * @param string[]            $caps    Primitive capabilities being checked.
	 * @param array<int, mixed>   $args    Original arguments; [0] is the requested capability.
	 * @param WP_User             $user    The user.
	 * @return array<string, bool>
	 */
	public static function grant( $allcaps, $caps, $args, $user ): array {
		$allcaps = (array) $allcaps;
		if ( self::$suspended || ! $user instanceof WP_User ) {
			return $allcaps;
		}
		$caps   = array_map( 'strval', (array) $caps );
		$access = self::access( $user );

		foreach ( $caps as $cap ) {
			if ( in_array( $cap, $access['post_type_caps'], true ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		if ( [] === $access['page_caps'] || ! self::in_page_scope( $user, $access ) ) {
			return $allcaps;
		}
		$requested = is_string( $args[0] ?? null ) ? $args[0] : '';
		foreach ( $caps as $cap ) {
			if ( AdminAccess::is_protected_cap( $cap ) ) {
				continue;
			}
			// A page may ask for a meta capability; then its mapped primitives count.
			if ( in_array( $requested, $access['page_caps'], true ) || in_array( $cap, $access['page_caps'], true ) ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	public static function menu_start(): void {
		if ( [] !== self::access( wp_get_current_user() )['menus'] ) {
			self::$building_menu = true;
		}
	}

	/**
	 * Keeps the granted pages in the menu ("read") and removes submenu items
	 * that were only registered because the capabilities were granted while
	 * the menu was built. WordPress itself checks submenus only before
	 * admin_menu, so they are marked as not accessible here.
	 */
	public static function menu_end(): void {
		global $menu, $submenu, $_wp_submenu_nopriv;

		$access = self::access( wp_get_current_user() );
		if ( [] === $access['menus'] ) {
			return;
		}
		self::$building_menu = false;

		foreach ( (array) $menu as $position => $item ) {
			if ( in_array( (string) ( $item[2] ?? '' ), $access['menus'], true ) ) {
				$menu[ $position ][1] = 'read'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Only adjusts the capability of a granted item.
			}
		}

		foreach ( (array) $submenu as $parent => $items ) {
			$granted_parent = in_array( (string) $parent, $access['menus'], true );
			foreach ( $items as $position => $item ) {
				$slug = (string) ( $item[2] ?? '' );
				if ( $granted_parent && AdminAccess::is_page_slug( $slug ) ) {
					$submenu[ $parent ][ $position ][1] = 'read'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Only adjusts the capability of a granted item.
					continue;
				}
				if ( ! current_user_can( (string) ( $item[1] ?? '' ) ) ) {
					unset( $submenu[ $parent ][ $position ] );
					$_wp_submenu_nopriv[ $parent ][ $slug ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same bookkeeping WordPress does for submenus.
				}
			}
			if ( empty( $submenu[ $parent ] ) ) {
				unset( $submenu[ $parent ] );
			}
		}
	}

	/**
	 * On a granted page the capabilities stay granted for the whole request,
	 * so WordPress would show e.g. the whole Settings menu. The access check
	 * for the page is done by now; remove what the user cannot open otherwise.
	 */
	public static function hide_menu_items(): void {
		global $menu, $submenu;

		$user   = wp_get_current_user();
		$access = self::access( $user );
		if ( null === self::requested_page() || ! self::in_page_scope( $user, $access ) ) {
			return;
		}

		self::$suspended = true;
		try {
			foreach ( (array) $submenu as $parent => $items ) {
				if ( in_array( (string) $parent, $access['menus'], true ) ) {
					continue;
				}
				foreach ( $items as $position => $item ) {
					if ( ! current_user_can( (string) ( $item[1] ?? '' ) ) ) {
						unset( $submenu[ $parent ][ $position ] );
					}
				}
				if ( empty( $submenu[ $parent ] ) ) {
					unset( $submenu[ $parent ] );
				}
			}
			foreach ( (array) $menu as $position => $item ) {
				$slug = (string) ( $item[2] ?? '' );
				if ( in_array( $slug, $access['menus'], true ) || ! empty( $submenu[ $slug ] ) ) {
					continue;
				}
				if ( ! current_user_can( (string) ( $item[1] ?? '' ) ) ) {
					unset( $menu[ $position ] );
				}
			}
		} finally {
			self::$suspended = false;
		}
	}

	/**
	 * @param array{pages: list<string>} $access As returned by access().
	 */
	private static function in_page_scope( WP_User $user, array $access ): bool {
		if ( get_current_user_id() !== $user->ID ) {
			return false;
		}
		if ( self::$building_menu ) {
			return true;
		}
		$page = self::requested_page();
		return null !== $page && in_array( $page, $access['pages'], true );
	}

	/**
	 * The admin page of this request: admin.php?page=…, or the page whose
	 * Settings API form is being saved via options.php.
	 */
	private static function requested_page(): ?string {
		global $pagenow;

		if ( self::$page_resolved ) {
			return self::$requested_page;
		}
		self::$page_resolved = true;

		if ( ! is_admin() || wp_doing_ajax() ) {
			return null;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Routing only; WordPress and the page check their nonces.
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) ) {
			$page                 = plugin_basename( sanitize_text_field( wp_unslash( $_GET['page'] ) ) );
			self::$requested_page = AdminAccess::is_page_slug( $page ) ? $page : null;
		} elseif ( 'options.php' === $pagenow && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$group   = isset( $_POST['option_page'] ) ? sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) : '';
			$referer = wp_get_referer();
			if ( '' !== $group && ! AdminAccess::is_protected_option_group( $group ) && false !== $referer ) {
				self::$requested_page = AdminAccess::page_from_url( $referer );
			}
		}
		// phpcs:enable

		return self::$requested_page;
	}

	/**
	 * What the plugin grants this user.
	 *
	 * @return array{post_type_caps: list<string>, menus: list<string>, pages: list<string>, page_caps: list<string>}
	 */
	private static function access( WP_User $user ): array {
		if ( isset( self::$cache[ $user->ID ] ) ) {
			return self::$cache[ $user->ID ];
		}

		$roles    = array_values( array_map( 'strval', (array) $user->roles ) );
		$settings = Settings::section( 'access' );

		$post_type_caps = [];
		foreach ( AdminAccess::items( $roles, $settings['post_types'] ) as $name ) {
			$type = get_post_type_object( $name );
			if ( $type ) {
				$post_type_caps = array_merge( $post_type_caps, self::post_type_caps( $type ) );
			}
		}

		$menus     = [];
		$pages     = [];
		$page_caps = [];
		$granted   = AdminAccess::items( $roles, $settings['pages'] );
		if ( [] !== $granted ) {
			$known = self::menu();
			foreach ( $granted as $slug ) {
				if ( ! isset( $known[ $slug ] ) || ! AdminAccess::is_grantable( $known[ $slug ]['caps'] ) ) {
					continue;
				}
				$menus[]   = $slug;
				$pages     = array_merge( $pages, $known[ $slug ]['pages'] );
				$page_caps = array_merge( $page_caps, $known[ $slug ]['caps'] );
			}
		}

		$access = [
			'post_type_caps' => array_values( array_unique( $post_type_caps ) ),
			'menus'          => $menus,
			'pages'          => array_values( array_unique( $pages ) ),
			'page_caps'      => array_values( array_unique( $page_caps ) ),
		];

		// Post types are registered during init; earlier results are incomplete.
		if ( did_action( 'wp_loaded' ) ) {
			self::$cache[ $user->ID ] = $access;
		}
		return $access;
	}

	private static function natively_can( string $cap ): bool {
		self::$suspended = true;
		try {
			return current_user_can( $cap );
		} finally {
			self::$suspended = false;
		}
	}
}
