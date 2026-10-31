<?php
/**
 * Settings → Site settings: one tab per module, saved with the Settings API.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Admin;

use RMD\SiteSettings\Domain\Settings as SettingsData;
use RMD\SiteSettings\Modules\CommentCleanup;
use RMD\SiteSettings\Modules\Comments;
use RMD\SiteSettings\Plugin;
use RMD\SiteSettings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'rmd-site-settings';

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
				'confirmDelete' => __( 'Delete these comments permanently? This cannot be undone.', 'rmd-site-settings' ),
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
		echo '<table class="form-table" role="presentation"><tbody>';
		match ( $tab ) {
			'xmlrpc' => self::render_xmlrpc(),
			'head'   => self::render_head(),
			'admin'  => self::render_admin(),
			default  => self::render_comments(),
		};
		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		if ( 'comments' === $tab ) {
			self::render_cleanup();
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

	private static function row_start( string $label, string $row_class = '', string $label_for = '' ): void {
		printf( '<tr%s><th scope="row">', '' === $row_class ? '' : ' class="' . esc_attr( $row_class ) . '"' );
		if ( '' === $label_for ) {
			echo esc_html( $label );
		} else {
			printf( '<label for="%s">%s</label>', esc_attr( $label_for ), esc_html( $label ) );
		}
		echo '</th><td>';
	}

	private static function row_end(): void {
		echo '</td></tr>';
	}
}
