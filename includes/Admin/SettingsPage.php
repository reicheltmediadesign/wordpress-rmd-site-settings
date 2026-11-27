<?php
/**
 * Settings → Site settings: one tab per module, saved with the Settings API.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Admin;

use RMD\SiteSettings\Domain\AdminAccess;
use RMD\SiteSettings\Domain\MenuAccess;
use RMD\SiteSettings\Domain\Settings as SettingsData;
use RMD\SiteSettings\Domain\UpdateStatus;
use RMD\SiteSettings\Modules\AccessManager;
use RMD\SiteSettings\Modules\AppPasswords;
use RMD\SiteSettings\Modules\CommentCleanup;
use RMD\SiteSettings\Modules\Comments;
use RMD\SiteSettings\Modules\MenuManager;
use RMD\SiteSettings\Modules\Updates;
use RMD\SiteSettings\Plugin;
use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'rmd-site-settings';

	private const ROLE_USERS_LIMIT = 25;

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_filter( 'option_page_capability_' . Settings::GROUP, static fn(): string => Plugin::CAP );
		add_filter( 'plugin_action_links_' . RMD_SS_BASENAME, [ self::class, 'action_links' ] );
	}

	public static function menu(): void {
		add_options_page(
			__( 'Site settings', 'rmd-site-settings' ),
			__( 'Site settings', 'rmd-site-settings' ),
			Plugin::CAP,
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	/**
	 * @param array<string, string|int> $args Extra query arguments.
	 */
	public static function url( array $args = [] ): string {
		return add_query_arg( [ 'page' => self::SLUG ] + $args, admin_url( 'options-general.php' ) );
	}

	/**
	 * @param string[] $links Plugin action links.
	 * @return string[]
	 */
	public static function action_links( $links ): array {
		$links = (array) $links;
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'rmd-site-settings' ) ) );
		return $links;
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rmd-ss-admin', RMD_SS_URL . 'assets/css/admin.css', [], RMD_SS_VERSION );
		wp_enqueue_script( 'rmd-ss-admin', RMD_SS_URL . 'assets/js/admin.js', [], RMD_SS_VERSION, true );
		wp_localize_script(
			'rmd-ss-admin',
			'rmdSsAdmin',
			[
				'confirmDelete'                => __( 'Delete these comments permanently? This cannot be undone.', 'rmd-site-settings' ),
				'confirmDeleteAppPasswords'    => __( 'Delete the selected application passwords permanently? Applications that use them can no longer sign in.', 'rmd-site-settings' ),
				'confirmDeleteAllAppPasswords' => __( 'Delete all application passwords of all users permanently? Applications that use them can no longer sign in.', 'rmd-site-settings' ),
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function tabs(): array {
		return [
			'comments' => __( 'Comments', 'rmd-site-settings' ),
			'xmlrpc'   => __( 'XML-RPC', 'rmd-site-settings' ),
			'head'     => __( 'Page head', 'rmd-site-settings' ),
			'admin'    => __( 'Admin area', 'rmd-site-settings' ),
			'menus'    => __( 'Menus', 'rmd-site-settings' ),
			'access'   => __( 'Admin access', 'rmd-site-settings' ),
			'security' => __( 'Security', 'rmd-site-settings' ),
		];
	}

	private static function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'comments';
	}

	public static function render(): void {
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rmd-site-settings' ) );
		}

		$tab = self::current_tab();

		echo '<div class="wrap rmd-ss">';
		echo '<h1>' . esc_html__( 'Site settings', 'rmd-site-settings' ) . '</h1>';
		self::render_notices();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( self::tabs() as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( [ 'tab' => $key ] ) ),
				$key === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<form method="post" action="options.php">';
		settings_fields( Settings::GROUP );
		if ( 'menus' === $tab ) {
			self::render_menus_intro();
		} elseif ( 'access' === $tab ) {
			self::render_access_intro();
		} elseif ( 'security' === $tab ) {
			self::render_app_passwords_intro();
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		match ( $tab ) {
			'xmlrpc'        => self::render_xmlrpc(),
			'head'          => self::render_head(),
			'admin'         => self::render_admin(),
			'menus'         => self::render_menus(),
			'access'        => self::render_access(),
			'security'      => self::render_app_passwords(),
			default         => self::render_comments(),
		};
		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		if ( 'comments' === $tab ) {
			self::render_cleanup();
		} elseif ( 'security' === $tab ) {
			self::render_app_password_list();
			self::render_updates();
		}

		echo '</div>';
	}

	private static function render_notices(): void {
		settings_errors( Settings::OPTION );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display of a redirect result only.
		$notice = isset( $_GET['rmd_ss_notice'] ) ? sanitize_key( wp_unslash( $_GET['rmd_ss_notice'] ) ) : '';
		$count  = isset( $_GET['rmd_ss_deleted'] ) ? absint( $_GET['rmd_ss_deleted'] ) : 0;
		// phpcs:enable

		$messages = [
			'deleted'            => [
				'success',
				sprintf(
					/* translators: %s: number of comments */
					_n( '%s comment deleted.', '%s comments deleted.', $count, 'rmd-site-settings' ),
					number_format_i18n( $count )
				),
			],
			'delete_nothing'     => [ 'error', __( 'Nothing was deleted: choose what to delete.', 'rmd-site-settings' ) ],
			'delete_unconfirmed' => [ 'error', __( 'Nothing was deleted: please confirm that the comments will be deleted permanently.', 'rmd-site-settings' ) ],
			'app_deleted'        => [
				'success',
				sprintf(
					/* translators: %s: number of application passwords */
					_n( '%s application password deleted.', '%s application passwords deleted.', $count, 'rmd-site-settings' ),
					number_format_i18n( $count )
				),
			],
			'app_delete_nothing' => [ 'error', __( 'Nothing was deleted: select at least one application password.', 'rmd-site-settings' ) ],
			'app_delete_failed'  => [ 'error', __( 'Not all application passwords could be deleted. Check the list below.', 'rmd-site-settings' ) ],
			'releases_refreshed' => [ 'success', __( 'Release dates fetched from WordPress.org.', 'rmd-site-settings' ) ],
			'releases_partial'   => [ 'warning', __( 'Not all release dates could be fetched from WordPress.org. Please try again later.', 'rmd-site-settings' ) ],
		];
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}
	}

	private static function render_comments(): void {
		$settings = Settings::section( 'comments' );
		$name     = self::name( 'comments' );

		self::row_start( __( 'Comments', 'rmd-site-settings' ) );
		echo '<fieldset class="rmd-ss-mode">';
		$modes = [
			SettingsData::MODE_OFF        => [ __( 'Enabled (WordPress default)', 'rmd-site-settings' ), '' ],
			SettingsData::MODE_EVERYWHERE => [ __( 'Disabled everywhere', 'rmd-site-settings' ), __( 'Also removes the Comments menu, the discussion settings, comment feeds, the comments REST endpoint and comment blocks.', 'rmd-site-settings' ) ],
			SettingsData::MODE_POST_TYPES => [ __( 'Disabled for selected post types', 'rmd-site-settings' ), '' ],
		];
		foreach ( $modes as $value => [ $label, $description ] ) {
			printf(
				'<label><input type="radio" name="%s" value="%s" %s> %s</label>',
				esc_attr( $name . '[mode]' ),
				esc_attr( $value ),
				checked( $settings['mode'], $value, false ),
				esc_html( $label )
			);
			if ( '' !== $description ) {
				echo '<p class="description rmd-ss-indent">' . esc_html( $description ) . '</p>';
			}
			echo '<br>';
		}
		echo '</fieldset>';
		self::row_end();

		self::row_start( __( 'Post types', 'rmd-site-settings' ), 'rmd-ss-post-types' );
		echo '<fieldset>';
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $name . '[post_types][]' ) );
		foreach ( self::post_type_labels( Comments::commentable_post_types() ) as $type => $label ) {
			printf(
				'<label><input type="checkbox" name="%s" value="%s" %s> %s</label><br>',
				esc_attr( $name . '[post_types][]' ),
				esc_attr( $type ),
				checked( in_array( $type, $settings['post_types'], true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		self::row_end();

		self::checkbox_row(
			'comments',
			'keep_existing',
			__( 'Existing comments', 'rmd-site-settings' ),
			__( 'Keep showing existing comments', 'rmd-site-settings' ),
			__( 'New comments are not possible, but approved comments stay visible and can still be moderated.', 'rmd-site-settings' )
		);
	}

	private static function render_xmlrpc(): void {
		self::checkbox_row(
			'xmlrpc',
			'disable',
			__( 'XML-RPC', 'rmd-site-settings' ),
			__( 'Disable XML-RPC', 'rmd-site-settings' ),
			__( 'xmlrpc.php answers no requests, pingbacks are off and the RSD link is removed. Leave this off if you use the Jetpack plugin or an app that publishes via XML-RPC.', 'rmd-site-settings' )
		);
	}

	private static function render_head(): void {
		$items = [
			'emoji'        => [ __( 'Emoji script', 'rmd-site-settings' ), __( 'Browsers show emoji natively; removes the detection script, its styles and the DNS prefetch to s.w.org.', 'rmd-site-settings' ) ],
			'generator'    => [ __( 'Generator tag', 'rmd-site-settings' ), __( 'Hides the WordPress version in the page source and feeds.', 'rmd-site-settings' ) ],
			'shortlink'    => [ __( 'Shortlink', 'rmd-site-settings' ), __( 'Removes the ?p= shortlink from the page head and the HTTP headers.', 'rmd-site-settings' ) ],
			'oembed_links' => [ __( 'oEmbed discovery links', 'rmd-site-settings' ), __( 'Other sites can still embed your posts by URL.', 'rmd-site-settings' ) ],
			'rest_link'    => [ __( 'REST API link', 'rmd-site-settings' ), __( 'Removes the link to the REST API from the page head and the HTTP headers. The REST API itself keeps working.', 'rmd-site-settings' ) ],
			'rsd'          => [ __( 'RSD link', 'rmd-site-settings' ), __( 'Only used by XML-RPC clients.', 'rmd-site-settings' ) ],
		];
		foreach ( $items as $key => [ $label, $description ] ) {
			self::checkbox_row( 'head', $key, $label, __( 'Remove', 'rmd-site-settings' ), $description );
		}
	}

	private static function render_admin(): void {
		self::checkbox_row(
			'admin',
			'support_widget',
			__( 'Support widget', 'rmd-site-settings' ),
			__( 'Show the "Need help or support?" widget on the dashboard', 'rmd-site-settings' ),
			__( 'Contact details of reichelt media.design for all users who can see the dashboard. Each user can still hide it under Screen Options.', 'rmd-site-settings' )
		);
		self::checkbox_row( 'admin', 'welcome_panel', __( 'Welcome panel', 'rmd-site-settings' ), __( 'Hide the welcome panel on the dashboard', 'rmd-site-settings' ) );
		self::checkbox_row( 'admin', 'events_news', __( 'Events and news', 'rmd-site-settings' ), __( 'Hide the "WordPress Events and News" widget', 'rmd-site-settings' ) );
		self::checkbox_row( 'admin', 'quick_draft', __( 'Quick draft', 'rmd-site-settings' ), __( 'Hide the "Quick Draft" widget', 'rmd-site-settings' ) );

		self::row_start( __( 'Footer text', 'rmd-site-settings' ), '', 'rmd-ss-footer-text' );
		printf(
			'<input type="text" id="rmd-ss-footer-text" name="%s" value="%s" class="large-text" maxlength="%d">',
			esc_attr( self::name( 'admin' ) . '[footer_text]' ),
			esc_attr( Settings::section( 'admin' )['footer_text'] ),
			(int) SettingsData::FOOTER_TEXT_MAX
		);
		echo '<p class="description">' . esc_html__( 'Replaces "Thank you for creating with WordPress" at the bottom of every admin page. Allowed HTML: a, strong, em. Leave empty for the default text.', 'rmd-site-settings' ) . '</p>';
		self::row_end();
	}

	private static function render_menus_intro(): void {
		echo '<p>' . esc_html__( 'Choose which roles get access to the navigation menus. They find them under "Menus" in the admin menu. "Edit" opens the WordPress menu screen without access to widgets, the Customizer or theme settings; "View only" shows the menus without being able to change them.', 'rmd-site-settings' ) . '</p>';
		if ( ! current_theme_supports( 'menus' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The active theme has no menu locations (for example a block theme). The setting has no effect until a theme with menus is active.', 'rmd-site-settings' ) . '</p></div>';
		}
	}

	private static function render_menus(): void {
		$configured = Settings::section( 'menus' )['roles'];
		$assignable = MenuManager::assignable_roles();
		$levels     = [
			MenuAccess::NONE  => __( 'No access', 'rmd-site-settings' ),
			MenuAccess::READ  => __( 'View only', 'rmd-site-settings' ),
			MenuAccess::WRITE => __( 'Edit', 'rmd-site-settings' ),
		];
		$counts     = count_users()['avail_roles'];

		foreach ( wp_roles()->get_names() as $role => $name ) {
			$role  = (string) $role;
			$id    = 'rmd-ss-menus-' . sanitize_html_class( $role );
			$users = self::role_users( $role, (int) ( $counts[ $role ] ?? 0 ) );

			if ( ! isset( $assignable[ $role ] ) ) {
				self::row_start( translate_user_role( $name ), '', '', $users );
				echo '<span class="rmd-ss-count">' . esc_html__( 'Full access (WordPress default)', 'rmd-site-settings' ) . '</span>';
				self::row_end();
				continue;
			}

			self::row_start( $assignable[ $role ], '', $id, $users );
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( self::name( 'menus' ) . '[roles][' . $role . ']' ) );
			foreach ( $levels as $value => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $value ),
					selected( $configured[ $role ] ?? MenuAccess::NONE, $value, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			self::row_end();
		}
	}

	private static function render_access_intro(): void {
		echo '<p>' . esc_html__( 'Give roles access to post types and to admin pages of plugins and the theme that they cannot open otherwise. A post type comes with all its permissions (create, edit, publish, delete). An admin page opens with the permissions it needs only on its own screens and when its settings are saved; the rest of the admin area stays as it is.', 'rmd-site-settings' ) . '</p>';
		echo '<p>' . esc_html__( 'Pages that need permissions for users, roles, plugins, themes or updates cannot be shared, and neither can the WordPress settings screens.', 'rmd-site-settings' ) . '</p>';
	}

	private static function render_access(): void {
		$settings = Settings::section( 'access' );
		$types    = AccessManager::post_types();
		$pages    = AccessManager::menu();
		$counts   = count_users()['avail_roles'];
		$name     = self::name( 'access' );

		if ( [] === $types && [] === $pages ) {
			self::row_start( __( 'Admin access', 'rmd-site-settings' ) );
			echo esc_html__( 'There are no post types or admin pages of plugins or the theme.', 'rmd-site-settings' );
			self::row_end();
			return;
		}

		foreach ( wp_roles()->get_names() as $role => $role_name ) {
			$role   = (string) $role;
			$object = get_role( $role );
			if ( ! $object ) {
				continue;
			}
			self::row_start( translate_user_role( $role_name ), '', '', self::role_users( $role, (int) ( $counts[ $role ] ?? 0 ) ) );

			if ( $object->has_cap( Plugin::CAP ) ) {
				echo '<span class="rmd-ss-count">' . esc_html__( 'Full access (WordPress default)', 'rmd-site-settings' ) . '</span>';
				self::row_end();
				continue;
			}

			if ( [] !== $types ) {
				$field = $name . '[post_types][' . $role . '][]';
				echo '<fieldset class="rmd-ss-access-group"><legend>' . esc_html__( 'Post types', 'rmd-site-settings' ) . '</legend>';
				printf( '<input type="hidden" name="%s" value="">', esc_attr( $field ) );
				foreach ( $types as $type_name => $type ) {
					$shared = match ( (string) $type->cap->edit_posts ) {
						'edit_posts' => __( 'uses the permissions of posts', 'rmd-site-settings' ),
						'edit_pages' => __( 'uses the permissions of pages', 'rmd-site-settings' ),
						default      => '',
					};
					self::access_checkbox(
						$field,
						(string) $type_name,
						sprintf( '%s (%s)', $type->labels->name, $type_name ),
						in_array( $type_name, $settings['post_types'][ $role ] ?? [], true ),
						$object->has_cap( (string) $type->cap->edit_posts ),
						false,
						$shared
					);
				}
				echo '</fieldset>';
			}

			if ( [] !== $pages ) {
				$field = $name . '[pages][' . $role . '][]';
				echo '<fieldset class="rmd-ss-access-group"><legend>' . esc_html__( 'Admin pages', 'rmd-site-settings' ) . '</legend>';
				printf( '<input type="hidden" name="%s" value="">', esc_attr( $field ) );
				foreach ( $pages as $slug => $page ) {
					self::access_checkbox(
						$field,
						$slug,
						$page['title'],
						in_array( $slug, $settings['pages'][ $role ] ?? [], true ),
						$object->has_cap( $page['caps'][0] ),
						! AdminAccess::is_grantable( $page['caps'] ),
						implode( ', ', $page['submenus'] )
					);
				}
				echo '</fieldset>';
			}

			self::row_end();
		}
	}

	private static function render_app_passwords_intro(): void {
		echo '<h2>' . esc_html__( 'Application passwords', 'rmd-site-settings' ) . '</h2>';
		echo '<p>' . esc_html__( 'Application passwords let apps and scripts sign in to the REST API and XML-RPC as a user, without the login form and without two-factor authentication. Switch them off for roles that do not need them: existing application passwords of these roles stop working at once but are not deleted, and the users can no longer create new ones in their profile.', 'rmd-site-settings' ) . '</p>';
		echo '<p>' . esc_html__( 'Users with several roles keep their application passwords as long as one of their roles may use them.', 'rmd-site-settings' ) . '</p>';
		if ( ! wp_is_application_passwords_available() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Application passwords are currently not available on this site at all: they need HTTPS, or another plugin has switched them off.', 'rmd-site-settings' ) . '</p></div>';
		}
	}

	private static function render_app_passwords(): void {
		$disabled = Settings::section( 'app_passwords' )['disabled_roles'];
		$counts   = count_users()['avail_roles'];
		$field    = self::name( 'app_passwords' ) . '[disabled_roles][]';
		$first    = true;

		foreach ( wp_roles()->get_names() as $role => $name ) {
			$role = (string) $role;
			$id   = 'rmd-ss-app-passwords-' . sanitize_html_class( $role );

			self::row_start( translate_user_role( $name ), '', '', self::role_users( $role, (int) ( $counts[ $role ] ?? 0 ) ) );
			if ( $first ) {
				printf( '<input type="hidden" name="%s" value="">', esc_attr( $field ) );
				$first = false;
			}
			printf(
				'<label for="%s"><input type="checkbox" id="%s" name="%s" value="%s" %s> %s</label>',
				esc_attr( $id ),
				esc_attr( $id ),
				esc_attr( $field ),
				esc_attr( $role ),
				checked( in_array( $role, $disabled, true ), true, false ),
				esc_html__( 'Disable application passwords', 'rmd-site-settings' )
			);
			self::row_end();
		}
	}

	private static function render_app_password_list(): void {
		$entries = AppPasswords::entries();

		echo '<h3>' . esc_html__( 'Existing application passwords', 'rmd-site-settings' ) . '</h3>';
		if ( [] === $entries ) {
			echo '<p class="description">' . esc_html__( 'No user has an application password.', 'rmd-site-settings' ) . '</p>';
			return;
		}

		$role_names = wp_roles()->get_names();
		$format     = (string) get_option( 'date_format' );

		printf( '<form method="post" action="%s" class="rmd-ss-app-passwords-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( AppPasswords::ACTION ) );
		wp_nonce_field( AppPasswords::ACTION );

		// The check-column classes make the WordPress admin script handle "select all".
		echo '<table class="widefat striped rmd-ss-app-passwords"><thead><tr>';
		echo '<td class="manage-column check-column"><input type="checkbox" id="rmd-ss-app-passwords-all"><label for="rmd-ss-app-passwords-all"><span class="screen-reader-text">' . esc_html__( 'Select all', 'rmd-site-settings' ) . '</span></label></td>';
		foreach ( [ __( 'User', 'rmd-site-settings' ), __( 'Role', 'rmd-site-settings' ), __( 'Name', 'rmd-site-settings' ), __( 'Created', 'rmd-site-settings' ), __( 'Last used', 'rmd-site-settings' ), __( 'Status', 'rmd-site-settings' ) ] as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $entries as $index => $entry ) {
			$user  = $entry['user'];
			$id    = 'rmd-ss-app-password-' . $index;
			$roles = [];
			foreach ( (array) $user->roles as $role ) {
				$roles[] = isset( $role_names[ $role ] ) ? translate_user_role( $role_names[ $role ] ) : (string) $role;
			}

			$label = esc_html( $user->display_name );
			if ( current_user_can( 'edit_user', $user->ID ) ) {
				$label = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_user_link( $user->ID ) . '#application-passwords-section' ), $label );
			}
			if ( $user->display_name !== $user->user_login ) {
				$label .= ' <span class="rmd-ss-role-users-login">' . esc_html( $user->user_login ) . '</span>';
			}

			echo '<tr>';
			printf(
				'<th scope="row" class="check-column"><input type="checkbox" id="%s" name="app_passwords[]" value="%s"><label for="%s"><span class="screen-reader-text">%s</span></label></th>',
				esc_attr( $id ),
				esc_attr( AppPasswords::key( (int) $user->ID, $entry['uuid'] ) ),
				esc_attr( $id ),
				/* translators: 1: name of the application password, 2: user name */
				esc_html( sprintf( __( 'Select %1$s of %2$s', 'rmd-site-settings' ), $entry['name'], $user->display_name ) )
			);
			echo '<td>' . $label . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			echo '<td>' . esc_html( [] === $roles ? __( 'No role', 'rmd-site-settings' ) : implode( ', ', $roles ) ) . '</td>';
			echo '<td>' . esc_html( $entry['name'] ) . '</td>';
			echo '<td>' . esc_html( $entry['created'] > 0 ? (string) wp_date( $format, $entry['created'] ) : '–' ) . '</td>';
			echo '<td>' . esc_html( $entry['last_used'] > 0 ? (string) wp_date( $format, $entry['last_used'] ) : __( 'Never', 'rmd-site-settings' ) ) . '</td>';
			if ( $entry['disabled'] ) {
				echo '<td><span class="rmd-ss-app-passwords-off">' . esc_html__( 'Disabled', 'rmd-site-settings' ) . '</span></td>';
			} else {
				echo '<td>' . esc_html__( 'Active', 'rmd-site-settings' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Deleted application passwords cannot be restored. Passwords with the status "Disabled" are not accepted at the moment, but would work again as soon as application passwords are enabled.', 'rmd-site-settings' ) . '</p>';
		echo '<p class="submit">';
		submit_button( __( 'Delete selected', 'rmd-site-settings' ), 'delete', 'delete_selected', false );
		echo ' ';
		submit_button( __( 'Delete all', 'rmd-site-settings' ), 'delete', 'delete_all', false );
		echo '</p></form>';
	}

	private static function render_updates(): void {
		$items    = Updates::items();
		$releases = Updates::releases();
		$format   = (string) get_option( 'date_format' );
		$checked  = Updates::last_checked();

		echo '<hr><h2>' . esc_html__( 'Updates', 'rmd-site-settings' ) . '</h2>';
		echo '<p>' . esc_html__( 'Installed plugins and themes with their update status. "Last updated" is the date of the latest release in the WordPress.org directory: a plugin or theme without a release for more than two years is probably no longer maintained and should be replaced.', 'rmd-site-settings' ) . '</p>';

		self::updates_table( __( 'Plugins', 'rmd-site-settings' ), $items['plugins'], $format );
		self::updates_table( __( 'Themes', 'rmd-site-settings' ), $items['themes'], $format );

		echo '<p class="description">';
		echo esc_html(
			$checked > 0
				/* translators: %s: date and time */
				? sprintf( __( 'WordPress last checked for updates: %s.', 'rmd-site-settings' ), (string) wp_date( $format . ' ' . get_option( 'time_format' ), $checked ) )
				: __( 'WordPress has not checked for updates yet.', 'rmd-site-settings' )
		);
		echo ' ';
		echo esc_html(
			$releases['checked'] > 0
				/* translators: %s: date and time */
				? sprintf( __( 'Release dates last fetched: %s.', 'rmd-site-settings' ), (string) wp_date( $format . ' ' . get_option( 'time_format' ), $releases['checked'] ) )
				: __( 'Release dates have not been fetched yet.', 'rmd-site-settings' )
		);
		echo '</p>';

		printf( '<form method="post" action="%s" class="rmd-ss-updates-form">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( Updates::ACTION ) );
		wp_nonce_field( Updates::ACTION );
		echo '<p class="submit">';
		submit_button( __( 'Fetch release dates from WordPress.org', 'rmd-site-settings' ), 'secondary', 'submit', false );
		if ( current_user_can( 'update_core' ) ) {
			printf( ' <a class="button" href="%s">%s</a>', esc_url( self_admin_url( 'update-core.php' ) ), esc_html__( 'Open the WordPress updates screen', 'rmd-site-settings' ) );
		}
		echo '</p></form>';
	}

	/**
	 * @param list<array{name: string, version: string, active: bool, known: bool, new_version: string, auto_update: bool, slug: ?string, release: ?array{state: string, date: int}}> $rows As returned by Updates::items().
	 */
	private static function updates_table( string $title, array $rows, string $date_format ): void {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( [] === $rows ) {
			echo '<p class="description">' . esc_html__( 'None installed.', 'rmd-site-settings' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped rmd-ss-updates"><thead><tr>';
		foreach ( [ __( 'Name', 'rmd-site-settings' ), __( 'Version', 'rmd-site-settings' ), __( 'Update', 'rmd-site-settings' ), __( 'Automatic updates', 'rmd-site-settings' ), __( 'Last updated', 'rmd-site-settings' ) ] as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		$now = time();
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['name'] );
			if ( ! $row['active'] ) {
				echo ' <span class="rmd-ss-count">(' . esc_html__( 'inactive', 'rmd-site-settings' ) . ')</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( '' !== $row['version'] ? $row['version'] : '–' ) . '</td>';

			if ( '' !== $row['new_version'] ) {
				/* translators: %s: version number */
				echo '<td><span class="rmd-ss-warn">' . esc_html( sprintf( __( 'Update available: %s', 'rmd-site-settings' ), $row['new_version'] ) ) . '</span></td>';
			} elseif ( $row['known'] ) {
				echo '<td>' . esc_html__( 'Up to date', 'rmd-site-settings' ) . '</td>';
			} else {
				echo '<td><span class="rmd-ss-count">' . esc_html__( 'Unknown: no update source', 'rmd-site-settings' ) . '</span></td>';
			}

			echo '<td>' . esc_html( $row['auto_update'] ? __( 'On', 'rmd-site-settings' ) : __( 'Off', 'rmd-site-settings' ) ) . '</td>';

			$release = $row['release'];
			if ( null === $row['slug'] || ( null !== $release && UpdateStatus::MISSING === $release['state'] ) ) {
				echo '<td><span class="rmd-ss-count">' . esc_html__( 'Not in the WordPress.org directory', 'rmd-site-settings' ) . '</span></td>';
			} elseif ( null === $release ) {
				echo '<td><span class="rmd-ss-count">' . esc_html__( 'Not fetched yet', 'rmd-site-settings' ) . '</span></td>';
			} elseif ( UpdateStatus::CLOSED === $release['state'] ) {
				echo '<td><span class="rmd-ss-warn">' . esc_html__( 'Closed in the WordPress.org directory', 'rmd-site-settings' ) . '</span></td>';
			} elseif ( UpdateStatus::is_outdated( $release['date'], $now ) ) {
				/* translators: %s: date */
				echo '<td><span class="rmd-ss-warn">' . esc_html( sprintf( __( '%s (more than two years ago)', 'rmd-site-settings' ), (string) wp_date( $date_format, $release['date'] ) ) ) . '</span></td>';
			} else {
				echo '<td>' . esc_html( (string) wp_date( $date_format, $release['date'] ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param bool   $native    The role has access anyway: shown checked and disabled.
	 * @param bool   $locked    The item needs protected permissions: shown disabled.
	 * @param string $note      Plain text shown after the label.
	 */
	private static function access_checkbox( string $field, string $value, string $label, bool $checked, bool $native, bool $locked, string $note ): void {
		if ( $native ) {
			$note = __( 'has access anyway', 'rmd-site-settings' );
		} elseif ( $locked ) {
			$note = __( 'not possible: needs protected permissions', 'rmd-site-settings' );
		}
		printf(
			'<label><input type="checkbox" name="%s" value="%s" %s %s> %s</label>%s<br>',
			esc_attr( $field ),
			esc_attr( $value ),
			checked( $native || ( $checked && ! $locked ), true, false ),
			disabled( $native || $locked, true, false ),
			esc_html( $label ),
			'' === $note ? '' : ' <span class="rmd-ss-access-note">– ' . esc_html( $note ) . '</span>'
		);
	}

	/**
	 * Badge with the number of users of a role; lists them on hover and focus.
	 *
	 * @return string Escaped HTML.
	 */
	private static function role_users( string $role, int $count ): string {
		/* translators: %s: number of users */
		$label = esc_html( sprintf( _n( '%s user', '%s users', $count, 'rmd-site-settings' ), number_format_i18n( $count ) ) );
		$badge = '<span class="rmd-ss-role-users-count" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>'
			. '<span class="screen-reader-text">' . $label . '</span>';

		if ( 0 === $count ) {
			return '<span class="rmd-ss-role-users rmd-ss-role-users-empty" title="' . esc_attr( $label ) . '">' . $badge . '</span>';
		}

		$users = get_users(
			[
				'role'    => $role,
				'number'  => self::ROLE_USERS_LIMIT,
				'orderby' => 'display_name',
				'fields'  => [ 'user_login', 'display_name' ],
			]
		);

		$items = '';
		foreach ( $users as $user ) {
			$items .= '<li>' . esc_html( $user->display_name );
			if ( $user->display_name !== $user->user_login ) {
				$items .= ' <span class="rmd-ss-role-users-login">' . esc_html( $user->user_login ) . '</span>';
			}
			$items .= '</li>';
		}
		$more = $count - count( $users );
		if ( $more > 0 ) {
			/* translators: %s: number of further users */
			$items .= '<li class="rmd-ss-role-users-more">' . esc_html( sprintf( _n( 'and %s more', 'and %s more', $more, 'rmd-site-settings' ), number_format_i18n( $more ) ) ) . '</li>';
		}

		return '<span class="rmd-ss-role-users" tabindex="0">' . $badge
			. '<span class="rmd-ss-role-users-list"><ul>' . $items . '</ul></span></span>';
	}

	private static function render_cleanup(): void {
		$counts = CommentCleanup::counts_by_post_type();
		$total  = CommentCleanup::count( 'all' );
		$spam   = CommentCleanup::count( 'spam' );

		echo '<hr><h2>' . esc_html__( 'Delete comments', 'rmd-site-settings' ) . '</h2>';
		echo '<p>' . esc_html__( 'Deletes existing comments, trackbacks and pingbacks permanently from the database. Notes in the block editor are kept. Make a backup first.', 'rmd-site-settings' ) . '</p>';

		if ( 0 === $total ) {
			echo '<p class="description">' . esc_html__( 'There are no comments.', 'rmd-site-settings' ) . '</p>';
			return;
		}

		printf( '<form method="post" action="%s" class="rmd-ss-cleanup">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( CommentCleanup::ACTION ) );
		wp_nonce_field( CommentCleanup::ACTION );
		echo '<table class="form-table" role="presentation"><tbody>';

		self::row_start( __( 'What to delete', 'rmd-site-settings' ) );
		echo '<fieldset class="rmd-ss-delete-mode">';
		$modes = [
			'all'        => [ __( 'All comments', 'rmd-site-settings' ), $total ],
			'post_types' => [ __( 'Comments on selected post types', 'rmd-site-settings' ), null ],
			'spam'       => [ __( 'Spam only', 'rmd-site-settings' ), $spam ],
		];
		foreach ( $modes as $value => [ $label, $count ] ) {
			printf(
				'<label><input type="radio" name="delete_mode" value="%s"> %s%s</label><br>',
				esc_attr( $value ),
				esc_html( $label ),
				null === $count ? '' : ' <span class="rmd-ss-count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>'
			);
		}
		echo '</fieldset>';
		self::row_end();

		self::row_start( __( 'Post types', 'rmd-site-settings' ), 'rmd-ss-delete-post-types' );
		echo '<fieldset>';
		foreach ( self::post_type_labels( array_keys( $counts ) ) as $type => $label ) {
			printf(
				'<label><input type="checkbox" name="delete_post_types[]" value="%s"> %s <span class="rmd-ss-count">(%s)</span></label><br>',
				esc_attr( $type ),
				esc_html( $label ),
				esc_html( number_format_i18n( $counts[ $type ] ) )
			);
		}
		echo '</fieldset>';
		self::row_end();

		self::row_start( __( 'Confirmation', 'rmd-site-settings' ) );
		echo '<label><input type="checkbox" name="delete_confirm" value="1"> ' . esc_html__( 'I understand that the comments are deleted permanently.', 'rmd-site-settings' ) . '</label>';
		self::row_end();

		echo '</tbody></table>';
		submit_button( __( 'Delete comments', 'rmd-site-settings' ), 'delete', 'submit', true, [ 'id' => 'rmd-ss-delete-submit' ] );
		echo '</form>';
	}

	/**
	 * @param list<string> $types Post type names.
	 * @return array<string, string> Name => label, sorted by label.
	 */
	private static function post_type_labels( array $types ): array {
		$labels = [];
		foreach ( $types as $type ) {
			$object = get_post_type_object( $type );
			if ( $object && ! $object->show_ui && 'attachment' !== $type ) {
				continue;
			}
			$labels[ $type ] = $object ? sprintf( '%s (%s)', $object->labels->name, $type ) : $type;
		}
		asort( $labels, SORT_NATURAL | SORT_FLAG_CASE );
		return $labels;
	}

	private static function name( string $section ): string {
		return Settings::OPTION . '[' . $section . ']';
	}

	private static function checkbox_row( string $section, string $key, string $label, string $checkbox_label, string $description = '' ): void {
		$id   = 'rmd-ss-' . $section . '-' . str_replace( '_', '-', $key );
		$name = self::name( $section ) . '[' . $key . ']';

		self::row_start( $label );
		printf( '<input type="hidden" name="%s" value="0">', esc_attr( $name ) );
		printf(
			'<label for="%s"><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( $name ),
			checked( (bool) Settings::section( $section )[ $key ], true, false ),
			esc_html( $checkbox_label )
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		self::row_end();
	}

	/**
	 * @param string $after_label Escaped HTML placed after the label.
	 */
	private static function row_start( string $label, string $row_class = '', string $label_for = '', string $after_label = '' ): void {
		printf( '<tr%s><th scope="row">', '' === $row_class ? '' : ' class="' . esc_attr( $row_class ) . '"' );
		if ( '' === $label_for ) {
			echo esc_html( $label );
		} else {
			printf( '<label for="%s">%s</label>', esc_attr( $label_for ), esc_html( $label ) );
		}
		if ( '' !== $after_label ) {
			echo ' ' . $after_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
		}
		echo '</th><td>';
	}

	private static function row_end(): void {
		echo '</td></tr>';
	}
}
