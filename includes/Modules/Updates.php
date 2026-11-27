<?php
/**
 * Overview of the installed plugins and themes: version, pending update,
 * automatic updates and the date of the latest release in the WordPress.org
 * directory.
 *
 * Versions and pending updates come from what WordPress already knows (the
 * update transients). The release dates need one request per plugin and theme
 * to api.wordpress.org, so they are fetched only on demand
 * (admin-post.php?action=rmd_ss_refresh_release_dates) and stored in an option.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Admin\SettingsPage;
use RMD\SiteSettings\Domain\UpdateStatus;
use RMD\SiteSettings\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Updates {

	public const ACTION = 'rmd_ss_refresh_release_dates';
	public const OPTION = 'rmd_ss_release_dates';

	/** Seconds after which refresh() stops asking the directory. */
	private const TIME_LIMIT = 20;

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * @return array{checked: int, plugins: array<string, array{state: string, date: int}>, themes: array<string, array{state: string, date: int}>}
	 */
	public static function releases(): array {
		return UpdateStatus::parse( get_option( self::OPTION, [] ) );
	}

	/**
	 * When WordPress last looked for plugin updates; 0 if unknown.
	 */
	public static function last_checked(): int {
		$updates = get_site_transient( 'update_plugins' );
		return is_object( $updates ) && isset( $updates->last_checked ) ? (int) $updates->last_checked : 0;
	}

	/**
	 * Rows for the overview. "slug" is the name to ask the WordPress.org
	 * directory for, null if the item has another update source. "known" says
	 * whether any update source reported on the item; "release" is null until
	 * the release dates were fetched.
	 *
	 * @return array{
	 *     plugins: list<array{name: string, version: string, active: bool, known: bool, new_version: string, auto_update: bool, slug: ?string, release: ?array{state: string, date: int}}>,
	 *     themes: list<array{name: string, version: string, active: bool, known: bool, new_version: string, auto_update: bool, slug: ?string, release: ?array{state: string, date: int}}>
	 * }
	 */
	public static function items(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$releases = self::releases();

		$updates = get_site_transient( 'update_plugins' );
		$auto    = array_map( 'strval', (array) get_site_option( 'auto_update_plugins', [] ) );
		$plugins = [];
		foreach ( get_plugins() as $file => $data ) {
			$file   = (string) $file;
			$status = self::update_status( $updates, $file );
			$slug   = self::directory_slug(
				'' !== $status['slug'] ? $status['slug'] : ( '.' === dirname( $file ) ? basename( $file, '.php' ) : dirname( $file ) ),
				(string) ( $data['UpdateURI'] ?? '' ),
				$status['id']
			);

			$plugins[] = [
				'name'        => (string) ( $data['Name'] ?? $file ),
				'version'     => (string) ( $data['Version'] ?? '' ),
				'active'      => is_plugin_active( $file ),
				'known'       => $status['known'],
				'new_version' => $status['new_version'],
				'auto_update' => in_array( $file, $auto, true ),
				'slug'        => $slug,
				'release'     => null === $slug ? null : ( $releases['plugins'][ $slug ] ?? null ),
			];
		}

		$updates = get_site_transient( 'update_themes' );
		$auto    = array_map( 'strval', (array) get_site_option( 'auto_update_themes', [] ) );
		$active  = [ get_stylesheet(), get_template() ];
		$themes  = [];
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$stylesheet = (string) $stylesheet;
			$status     = self::update_status( $updates, $stylesheet );
			$slug       = self::directory_slug( $stylesheet, (string) $theme->get( 'UpdateURI' ), $status['id'] );

			$themes[] = [
				'name'        => (string) $theme->get( 'Name' ),
				'version'     => (string) $theme->get( 'Version' ),
				'active'      => in_array( $stylesheet, $active, true ),
				'known'       => $status['known'],
				'new_version' => $status['new_version'],
				'auto_update' => in_array( $stylesheet, $auto, true ),
				'slug'        => $slug,
				'release'     => null === $slug ? null : ( $releases['themes'][ $slug ] ?? null ),
			];
		}
		usort( $themes, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );

		return [
			'plugins' => $plugins,
			'themes'  => $themes,
		];
	}

	/**
	 * Asks the WordPress.org directory for the latest release of every
	 * installed plugin and theme and stores the answers. Items that could not
	 * be asked (network error, time limit) keep their stored value.
	 *
	 * @return array{fetched: int, failed: int}
	 */
	public static function refresh(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$stored = self::releases();
		$data   = [
			'checked' => time(),
			'plugins' => [],
			'themes'  => [],
		];
		$result = [
			'fetched' => 0,
			'failed'  => 0,
		];
		$start  = microtime( true );

		foreach ( self::items() as $kind => $rows ) {
			foreach ( $rows as $row ) {
				$slug = $row['slug'];
				if ( null === $slug || isset( $data[ $kind ][ $slug ] ) ) {
					continue;
				}

				$entry = null;
				if ( microtime( true ) - $start <= self::TIME_LIMIT ) {
					$entry = self::release_entry(
						'plugins' === $kind
							? plugins_api(
								'plugin_information',
								[
									'slug'   => $slug,
									'fields' => [
										'sections'     => false,
										'tags'         => false,
										'icons'        => false,
										'banners'      => false,
										'contributors' => false,
										'last_updated' => true,
									],
								]
							)
							: themes_api(
								'theme_information',
								[
									'slug'   => $slug,
									'fields' => [
										'sections'     => false,
										'tags'         => false,
										'last_updated' => true,
									],
								]
							)
					);
				}

				if ( null !== $entry ) {
					$data[ $kind ][ $slug ] = $entry;
					++$result['fetched'];
					continue;
				}
				++$result['failed'];
				if ( isset( $stored[ $kind ][ $slug ] ) ) {
					$data[ $kind ][ $slug ] = $stored[ $kind ][ $slug ];
				}
			}
		}

		update_option( self::OPTION, $data, false );
		return $result;
	}

	public static function handle(): void {
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rmd-site-settings' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::ACTION );

		$result = self::refresh();
		wp_safe_redirect(
			SettingsPage::url(
				[
					'tab'           => 'security',
					'rmd_ss_notice' => $result['failed'] > 0 ? 'releases_partial' : 'releases_refreshed',
				]
			)
		);
		exit;
	}

	/**
	 * What an update transient says about a plugin file or theme stylesheet.
	 * Plugin entries are objects, theme entries arrays.
	 *
	 * @param mixed $updates Value of the update_plugins or update_themes transient.
	 * @return array{known: bool, new_version: string, slug: string, id: string}
	 */
	private static function update_status( $updates, string $key ): array {
		$pending  = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? ( $updates->response[ $key ] ?? null ) : null;
		$current  = is_object( $updates ) && isset( $updates->no_update ) && is_array( $updates->no_update ) ? ( $updates->no_update[ $key ] ?? null ) : null;
		$entry    = (array) ( $pending ?? $current ?? [] );
		$text     = static fn( string $field ): string => isset( $entry[ $field ] ) && is_scalar( $entry[ $field ] ) ? (string) $entry[ $field ] : '';
		$has_data = null !== $pending || null !== $current;

		return [
			'known'       => $has_data,
			'new_version' => null !== $pending ? $text( 'new_version' ) : '',
			'slug'        => $text( 'slug' ),
			'id'          => $text( 'id' ),
		];
	}

	/**
	 * The slug to ask the directory for, or null when the item says it is
	 * updated from somewhere else (Update URI header or a foreign update id).
	 */
	private static function directory_slug( string $slug, string $update_uri, string $update_id ): ?string {
		if ( '' === $slug ) {
			return null;
		}
		if ( '' !== $update_id && ! str_starts_with( $update_id, 'w.org/' ) ) {
			return null;
		}
		if ( '' !== $update_uri ) {
			$host = (string) wp_parse_url( $update_uri, PHP_URL_HOST );
			if ( ! in_array( $host, [ 'wordpress.org', 'w.org' ], true ) && ! str_starts_with( $update_uri, 'w.org/' ) ) {
				return null;
			}
		}
		return $slug;
	}

	/**
	 * @param object|array<string, mixed>|WP_Error $response Answer of plugins_api() or themes_api().
	 * @return array{state: string, date: int}|null Null when the directory gave no usable answer.
	 */
	private static function release_entry( $response ): ?array {
		if ( $response instanceof WP_Error ) {
			$message = (string) $response->get_error_message();
			if ( 'closed' === $message ) {
				return UpdateStatus::entry( UpdateStatus::CLOSED );
			}
			if ( false !== stripos( $message, 'not found' ) ) {
				return UpdateStatus::entry( UpdateStatus::MISSING );
			}
			return null;
		}
		$response = (array) $response;
		$date     = isset( $response['last_updated'] ) && is_string( $response['last_updated'] ) ? UpdateStatus::timestamp( $response['last_updated'] ) : 0;
		return $date > 0 ? UpdateStatus::entry( UpdateStatus::FOUND, $date ) : null;
	}
}
