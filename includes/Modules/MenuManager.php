<?php
/**
 * Lets roles without "edit_theme_options" manage or view the navigation menus.
 *
 * Write access reuses the WordPress menu screen (nav-menus.php): the
 * capability is granted only while that screen or one of its AJAX actions
 * runs, so widgets, the Customizer and theme options stay closed. Read access
 * gets an own page that only lists the menus.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Domain\MenuAccess;
use RMD\SiteSettings\Domain\MenuTree;
use RMD\SiteSettings\Settings;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class MenuManager {

	public const READ_SLUG = 'rmd-ss-menus';

	private const CAP = 'edit_theme_options';

	/** AJAX actions of nav-menus.php that check edit_theme_options. */
	private const AJAX_ACTIONS = [ 'add-menu-item', 'menu-get-metabox', 'menu-quick-search', 'menu-locations-save' ];

	/** Admin menu position right below Appearance (60). */
	private const POSITION = 61;

	private static ?bool $menu_request = null;

	public static function init(): void {
		if ( ! is_admin() || [] === Settings::section( 'menus' )['roles'] ) {
			return;
		}
		add_filter( 'user_has_cap', [ self::class, 'grant' ], 10, 4 );
		add_filter( 'map_meta_cap', [ self::class, 'deny_customizer' ], 10, 3 );
		add_action( 'admin_menu', [ self::class, 'admin_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	/**
	 * Roles that do not have edit_theme_options and can get menu access.
	 *
	 * @return array<string, string> Role => translated name.
	 */
	public static function assignable_roles(): array {
		$roles = [];
		foreach ( wp_roles()->roles as $role => $data ) {
			if ( empty( $data['capabilities'][ self::CAP ] ) ) {
				$roles[ (string) $role ] = translate_user_role( $data['name'] );
			}
		}
		return $roles;
	}

	/**
	 * Level the plugin gives this user; users who manage menus anyway get "none".
	 */
	public static function level( WP_User $user ): string {
		if ( ! empty( $user->allcaps[ self::CAP ] ) ) {
			return MenuAccess::NONE;
		}
		return MenuAccess::level( array_values( (array) $user->roles ), Settings::section( 'menus' )['roles'] );
	}

	/**
	 * @param array<string, bool> $allcaps Capabilities of the user.
	 * @param string[]            $caps    Primitive capabilities being checked.
	 * @param array<int, mixed>   $args    Original arguments.
	 * @param WP_User             $user    The user.
	 * @return array<string, bool>
	 */
	public static function grant( $allcaps, $caps, $args, $user ): array {
		$allcaps = (array) $allcaps;
		if ( ! empty( $allcaps[ self::CAP ] ) || ! in_array( self::CAP, (array) $caps, true ) || ! $user instanceof WP_User ) {
			return $allcaps;
		}
		if ( self::is_menu_request() && MenuAccess::WRITE === self::level( $user ) ) {
			$allcaps[ self::CAP ] = true;
		}
		return $allcaps;
	}

	/**
	 * "customize" maps to edit_theme_options; keep the Customizer closed for
	 * users who only got the capability for the menu screen.
	 *
	 * @param string[] $caps    Primitive capabilities.
	 * @param string   $cap     Meta capability.
	 * @param int      $user_id User ID.
	 * @return string[]
	 */
	public static function deny_customizer( $caps, $cap, $user_id ): array {
		$caps = (array) $caps;
		if ( 'customize' !== $cap || ! self::is_menu_request() ) {
			return $caps;
		}
		$user = get_userdata( (int) $user_id );
		if ( $user && MenuAccess::WRITE === self::level( $user ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}

	public static function admin_menu(): void {
		if ( ! current_theme_supports( 'menus' ) ) {
			return;
		}
		$level = self::level( wp_get_current_user() );

		if ( MenuAccess::WRITE === $level ) {
			if ( self::is_menu_request() ) {
				self::hide_appearance();
			}
			add_menu_page( __( 'Menus', 'rmd-site-settings' ), __( 'Menus', 'rmd-site-settings' ), 'read', 'nav-menus.php', '', 'dashicons-menu', self::POSITION );
		} elseif ( MenuAccess::READ === $level ) {
			add_menu_page( __( 'Menus', 'rmd-site-settings' ), __( 'Menus', 'rmd-site-settings' ), 'read', self::READ_SLUG, [ self::class, 'render' ], 'dashicons-menu', self::POSITION );
		}
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ): void {
		if ( 'toplevel_page_' . self::READ_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rmd-ss-menus', RMD_SS_URL . 'assets/css/menus.css', [], RMD_SS_VERSION );
	}

	public static function render(): void {
		if ( MenuAccess::READ !== self::level( wp_get_current_user() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rmd-site-settings' ) );
		}

		$menus     = wp_get_nav_menus();
		$locations = get_registered_nav_menus();
		$assigned  = array_filter( array_map( 'intval', get_nav_menu_locations() ) );

		echo '<div class="wrap rmd-ss-menus">';
		echo '<h1>' . esc_html__( 'Menus', 'rmd-site-settings' ) . '</h1>';
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'You can view the menus of this site here. To change them, please contact the administrator.', 'rmd-site-settings' ) . '</p></div>';

		if ( [] !== $locations ) {
			echo '<h2>' . esc_html__( 'Menu locations', 'rmd-site-settings' ) . '</h2>';
			echo '<table class="widefat striped rmd-ss-menus-locations"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Location', 'rmd-site-settings' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Menu', 'rmd-site-settings' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $locations as $location => $description ) {
				$menu = isset( $assigned[ $location ] ) ? wp_get_nav_menu_object( $assigned[ $location ] ) : false;
				printf(
					'<tr><td>%s</td><td>%s</td></tr>',
					esc_html( $description ),
					$menu ? esc_html( $menu->name ) : '<span class="rmd-ss-menus-muted">' . esc_html__( 'No menu assigned', 'rmd-site-settings' ) . '</span>'
				);
			}
			echo '</tbody></table>';
		}

		if ( [] === $menus ) {
			echo '<p>' . esc_html__( 'No menus have been created yet.', 'rmd-site-settings' ) . '</p>';
		}

		foreach ( $menus as $menu ) {
			echo '<div class="rmd-ss-menus-menu">';
			echo '<h2>' . esc_html( $menu->name ) . '</h2>';

			$shown_in = [];
			foreach ( $assigned as $location => $menu_id ) {
				if ( $menu_id === (int) $menu->term_id && isset( $locations[ $location ] ) ) {
					$shown_in[] = $locations[ $location ];
				}
			}
			if ( [] !== $shown_in ) {
				/* translators: %s: comma-separated list of menu locations */
				echo '<p class="description">' . esc_html( sprintf( __( 'Shown in: %s', 'rmd-site-settings' ), implode( ', ', $shown_in ) ) ) . '</p>';
			}

			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! is_array( $items ) || [] === $items ) {
				echo '<p class="rmd-ss-menus-muted">' . esc_html__( 'This menu has no items.', 'rmd-site-settings' ) . '</p>';
			} else {
				self::render_items( $items );
			}
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * @param array<int, object> $items Menu items from wp_get_nav_menu_items().
	 */
	private static function render_items( array $items ): void {
		$by_id   = [];
		$parents = [];
		foreach ( $items as $item ) {
			$id             = (int) $item->ID;
			$by_id[ $id ]   = $item;
			$parents[ $id ] = (int) $item->menu_item_parent;
		}
		self::render_level( 0, MenuTree::children( $parents ), $by_id );
	}

	/**
	 * @param int                   $parent_id Parent item ID.
	 * @param array<int, list<int>> $children  Parent item ID => child item IDs.
	 * @param array<int, object>    $by_id     Item ID => item.
	 * @param array<int, true>      $seen      Items already shown (guards against parent loops).
	 */
	private static function render_level( int $parent_id, array $children, array $by_id, array &$seen = [] ): void {
		$ids = array_filter( $children[ $parent_id ] ?? [], static fn( int $id ): bool => ! isset( $seen[ $id ] ) );
		if ( [] === $ids ) {
			return;
		}
		echo '<ul class="rmd-ss-menus-items">';
		foreach ( $ids as $id ) {
			$seen[ $id ] = true;
			$item        = $by_id[ $id ];
			$title       = '' !== trim( (string) $item->title ) ? (string) $item->title : __( '(no label)', 'rmd-site-settings' );
			$invalid     = ! empty( $item->_invalid );

			printf( '<li><div class="rmd-ss-menus-item%s">', $invalid ? ' rmd-ss-menus-invalid' : '' );
			echo '<span class="rmd-ss-menus-title">' . esc_html( wp_strip_all_tags( $title ) ) . '</span>';
			echo ' <span class="rmd-ss-menus-type">' . esc_html( $invalid ? __( 'Invalid', 'rmd-site-settings' ) : (string) ( $item->type_label ?? '' ) ) . '</span>';
			if ( ! empty( $item->url ) ) {
				printf( ' <a href="%1$s" class="rmd-ss-menus-url" target="_blank" rel="noopener noreferrer">%1$s</a>', esc_url( (string) $item->url ) );
			}
			echo '</div>';
			self::render_level( $id, $children, $by_id, $seen );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * While edit_theme_options is granted for the menu screen, WordPress would
	 * show Appearance with Widgets, Customize etc.; the user gets only the own
	 * "Menus" item instead.
	 */
	private static function hide_appearance(): void {
		global $submenu;

		foreach ( $submenu['themes.php'] ?? [] as $position => $item ) {
			if ( 'nav-menus.php' === $item[2] || in_array( $item[1], [ self::CAP, 'customize' ], true ) ) {
				unset( $submenu['themes.php'][ $position ] );
			}
		}
		if ( empty( $submenu['themes.php'] ) ) {
			unset( $submenu['themes.php'] );
			remove_menu_page( 'themes.php' );
		}
	}

	private static function is_menu_request(): bool {
		if ( null === self::$menu_request ) {
			if ( wp_doing_ajax() ) {
				$action             = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; the AJAX handlers check their nonces.
				self::$menu_request = in_array( $action, self::AJAX_ACTIONS, true );
			} else {
				self::$menu_request = 'nav-menus.php' === ( $GLOBALS['pagenow'] ?? '' );
			}
		}
		return self::$menu_request;
	}
}
