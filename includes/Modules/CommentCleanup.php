<?php
/**
 * Deletes existing comments: all, by post type or spam only. Notes are never
 * deleted. Comment counts are recalculated per post afterwards.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Admin\SettingsPage;
use RMD\SiteSettings\Plugin;

defined( 'ABSPATH' ) || exit;

final class CommentCleanup {

	public const ACTION = 'rmd_ss_delete_comments';
	public const MODES  = [ 'all', 'post_types', 'spam' ];

	private const BATCH = 500;

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * @param list<string> $post_types Post types for mode "post_types".
	 */
	public static function count( string $mode, array $post_types = [] ): int {
		global $wpdb;

		$query = self::select( 'COUNT(*)', $mode, $post_types );
		if ( null === $query ) {
			return 0;
		}
		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared in select().
	}

	/**
	 * Comment counts per post type for the settings page.
	 *
	 * @return array<string, int>
	 */
	public static function counts_by_post_type(): array {
		global $wpdb;

		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.post_type, COUNT(*) AS total FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE c.comment_type <> %s GROUP BY p.post_type",
				Comments::NOTE_TYPE
			)
		);
		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->post_type ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * @param list<string> $post_types Post types for mode "post_types".
	 * @return int Number of deleted comments.
	 */
	public static function delete( string $mode, array $post_types = [] ): int {
		global $wpdb;

		$deleted  = 0;
		$post_ids = [];

		while ( true ) {
			$query = self::select( 'c.comment_ID, c.comment_post_ID', $mode, $post_types, self::BATCH );
			if ( null === $query ) {
				break;
			}
			$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared in select().
			if ( empty( $rows ) ) {
				break;
			}

			$ids = array_map( static fn( $row ): int => (int) $row->comment_ID, $rows );
			foreach ( $rows as $row ) {
				$post_ids[ (int) $row->comment_post_ID ] = true;
			}

			$in = implode( ',', $ids );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in holds integers only.
			$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ($in)" );
			$removed = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ($in)" );
			// phpcs:enable

			if ( false === $removed ) {
				break;
			}
			$deleted += (int) $removed;
			clean_comment_cache( $ids );

			if ( count( $ids ) < self::BATCH ) {
				break;
			}
		}

		foreach ( array_keys( $post_ids ) as $post_id ) {
			if ( $post_id > 0 ) {
				wp_update_comment_count_now( $post_id );
				wp_cache_delete( 'comments-' . $post_id, 'counts' );
			}
		}
		wp_cache_delete( 'comments-0', 'counts' );
		delete_transient( 'wc_count_comments' );

		/**
		 * Fires after comments were deleted with the cleanup tool.
		 *
		 * @param int          $deleted    Number of deleted comments.
		 * @param string       $mode       all, post_types or spam.
		 * @param list<string> $post_types Selected post types.
		 */
		do_action( 'rmd_ss_comments_deleted', $deleted, $mode, $post_types );

		return $deleted;
	}

	public static function handle(): void {
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rmd-site-settings' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::ACTION );

		$mode       = isset( $_POST['delete_mode'] ) ? sanitize_key( wp_unslash( $_POST['delete_mode'] ) ) : '';
		$post_types = isset( $_POST['delete_post_types'] ) && is_array( $_POST['delete_post_types'] )
			? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['delete_post_types'] ) ), get_post_types() ) )
			: [];
		$confirmed  = ! empty( $_POST['delete_confirm'] );

		if ( ! in_array( $mode, self::MODES, true ) || ( 'post_types' === $mode && [] === $post_types ) ) {
			self::redirect( [ 'rmd_ss_notice' => 'delete_nothing' ] );
		}
		if ( ! $confirmed ) {
			self::redirect( [ 'rmd_ss_notice' => 'delete_unconfirmed' ] );
		}

		$deleted = self::delete( $mode, $post_types );
		self::redirect(
			[
				'rmd_ss_notice'  => 'deleted',
				'rmd_ss_deleted' => $deleted,
			]
		);
	}

	/**
	 * @param array<string, string|int> $args Query arguments.
	 */
	private static function redirect( array $args ): never {
		wp_safe_redirect( SettingsPage::url( [ 'tab' => 'comments' ] + $args ) );
		exit;
	}

	/**
	 * Builds the prepared query for the comments a mode affects, or null for an invalid selection.
	 *
	 * @param list<string> $post_types Post types for mode "post_types".
	 */
	private static function select( string $columns, string $mode, array $post_types, int $limit = 0 ): ?string {
		global $wpdb;

		$join   = '';
		$where  = 'c.comment_type <> %s';
		$params = [ Comments::NOTE_TYPE ];

		switch ( $mode ) {
			case 'all':
				break;
			case 'spam':
				$where   .= ' AND c.comment_approved = %s';
				$params[] = 'spam';
				break;
			case 'post_types':
				if ( [] === $post_types ) {
					return null;
				}
				$join   = "INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID";
				$where .= ' AND p.post_type IN (' . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ')';
				$params = array_merge( $params, $post_types );
				break;
			default:
				return null;
		}

		$sql = "SELECT {$columns} FROM {$wpdb->comments} c {$join} WHERE {$where}";
		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d';
			$params[] = $limit;
		}

		return $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are built above.
	}
}
