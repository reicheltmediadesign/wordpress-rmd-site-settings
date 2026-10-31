<?php
/**
 * Disables comments, trackbacks and pingbacks everywhere or for selected post
 * types. Notes (comment type "note", WordPress 6.9+) are editorial content in
 * the block editor and are left alone.
 *
 * @package RMD\SiteSettings
 */

namespace RMD\SiteSettings\Modules;

use RMD\SiteSettings\Domain\CommentScope;
use RMD\SiteSettings\Plugin;
use RMD\SiteSettings\Settings;
use WP_Admin_Bar;
use WP_Block;
use WP_Block_Editor_Context;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Comments {

	public const NOTE_TYPE = 'note';

	/** Core blocks that show comments or the comment form. */
	public const BLOCKS = [
		'core/comments',
		'core/comment-template',
		'core/comment-author-avatar',
		'core/comment-author-name',
		'core/comment-content',
		'core/comment-date',
		'core/comment-edit-link',
		'core/comment-reply-link',
		'core/comments-title',
		'core/comments-pagination',
		'core/comments-pagination-next',
		'core/comments-pagination-numbers',
		'core/comments-pagination-previous',
		'core/latest-comments',
		'core/post-comments',
		'core/post-comments-count',
		'core/post-comments-form',
		'core/post-comments-link',
	];

	/** @var list<string>|null Post types that supported comments before this module removed the support. */
	private static ?array $commentable = null;

	private static CommentScope $scope;

	public static function init(): void {
		self::$scope = Settings::comment_scope();

		add_action( 'wp_loaded', [ self::class, 'remember_commentable' ], 1 );
		if ( ! self::$scope->is_active() ) {
			return;
		}

		add_action( 'wp_loaded', [ self::class, 'remove_support' ] );
		add_filter( 'comments_open', [ self::class, 'filter_open' ], 20, 2 );
		add_filter( 'pings_open', [ self::class, 'filter_open' ], 20, 2 );
		add_action( 'template_redirect', [ self::class, 'front_end' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'dequeue_reply_script' ], 100 );
		add_action( 'admin_notices', [ self::class, 'discussion_notice' ] );

		if ( ! self::$scope->keeps_existing() ) {
			add_filter( 'comments_array', [ self::class, 'filter_comments_array' ], 20, 2 );
			add_filter( 'get_comments_number', [ self::class, 'filter_comments_number' ], 20, 2 );
			add_filter( 'render_block', [ self::class, 'filter_render_block' ], 10, 3 );
			add_filter( 'allowed_block_types_all', [ self::class, 'filter_allowed_blocks' ], 20, 2 );
		}

		if ( self::$scope->is_everywhere() ) {
			self::init_everywhere();
		}
	}

	private static function init_everywhere(): void {
		add_action( 'template_redirect', [ self::class, 'block_comment_feeds' ], 9 );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_filter( 'wp_headers', [ self::class, 'remove_pingback_header' ] );
		add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
		add_action( 'widgets_init', [ self::class, 'unregister_widget' ] );
		add_action( 'admin_bar_menu', [ self::class, 'remove_admin_bar_nodes' ], 999 );
		add_action( 'admin_menu', [ self::class, 'remove_admin_menus' ], 999 );
		add_action( 'admin_init', [ self::class, 'block_admin_screens' ] );
		add_action( 'wp_dashboard_setup', [ self::class, 'remove_dashboard_widget' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'admin_styles' ] );
		add_filter( 'rest_pre_dispatch', [ self::class, 'filter_rest' ], 10, 3 );
		add_filter( 'xmlrpc_methods', [ self::class, 'filter_xmlrpc_methods' ] );

		if ( ! self::$scope->keeps_existing() ) {
			add_filter( 'register_block_type_args', [ self::class, 'hide_blocks_from_inserter' ], 10, 2 );
		}
	}

	/**
	 * The list for the settings page, including post types whose support this module removes.
	 *
	 * @return list<string>
	 */
	public static function commentable_post_types(): array {
		return self::$commentable ?? array_values( get_post_types_by_support( 'comments' ) );
	}

	public static function remember_commentable(): void {
		self::$commentable = array_values( get_post_types_by_support( 'comments' ) );
	}

	public static function remove_support(): void {
		$types = array_unique( array_merge( get_post_types_by_support( 'comments' ), get_post_types_by_support( 'trackbacks' ) ) );
		foreach ( self::$scope->disabled_among( array_values( $types ) ) as $type ) {
			remove_post_type_support( $type, 'trackbacks' );
			if ( ! self::$scope->keeps_existing() ) {
				remove_post_type_support( $type, 'comments' );
			}
		}
	}

	/**
	 * @param int|\WP_Post|null $post Post ID or object; null means the current post.
	 */
	public static function is_disabled_for( $post = null ): bool {
		if ( self::$scope->is_everywhere() ) {
			return true;
		}
		$type = get_post_type( $post ?? get_the_ID() );
		return is_string( $type ) && self::$scope->is_disabled( $type );
	}

	/**
	 * @param bool              $open    Whether the post is open for comments or pings.
	 * @param int|\WP_Post|null $post_id Post ID or object.
	 */
	public static function filter_open( $open, $post_id ): bool {
		return self::is_disabled_for( $post_id ) ? false : (bool) $open;
	}

	/**
	 * @param array<int, \WP_Comment> $comments Comments of the post.
	 * @param int                     $post_id  Post ID.
	 * @return array<int, \WP_Comment>
	 */
	public static function filter_comments_array( $comments, $post_id ): array {
		return self::is_disabled_for( (int) $post_id ) ? [] : (array) $comments;
	}

	/**
	 * @param string|int $count   Number of comments.
	 * @param int        $post_id Post ID.
	 * @return string|int
	 */
	public static function filter_comments_number( $count, $post_id ) {
		return self::is_disabled_for( (int) $post_id ) ? 0 : $count;
	}

	public static function front_end(): void {
		if ( ! is_singular() || ! self::is_disabled_for( get_queried_object_id() ) ) {
			return;
		}
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		if ( ! self::$scope->keeps_existing() ) {
			add_filter( 'comments_template', [ self::class, 'empty_template' ], 20 );
		}
	}

	public static function empty_template(): string {
		return RMD_SS_DIR . 'assets/views/empty-comments.php';
	}

	public static function dequeue_reply_script(): void {
		if ( is_singular() && self::is_disabled_for( get_queried_object_id() ) ) {
			wp_dequeue_script( 'comment-reply' );
		}
	}

	/**
	 * Block themes render comments through blocks instead of comments_template.
	 *
	 * @param string   $content Rendered block.
	 * @param array    $block   Parsed block.
	 * @param WP_Block $instance Block instance.
	 */
	public static function filter_render_block( $content, $block, $instance ): string {
		if ( ! in_array( $block['blockName'] ?? '', self::BLOCKS, true ) ) {
			return (string) $content;
		}
		if ( self::$scope->is_everywhere() ) {
			return '';
		}
		$post_id = $instance instanceof WP_Block && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();
		return $post_id && self::is_disabled_for( $post_id ) ? '' : (string) $content;
	}

	/**
	 * Removes the comment blocks from the inserter when editing a post of a disabled type.
	 *
	 * @param bool|string[]           $allowed Allowed block types.
	 * @param WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	public static function filter_allowed_blocks( $allowed, $context ) {
		if ( false === $allowed || ! $context instanceof WP_Block_Editor_Context || null === $context->post ) {
			return $allowed;
		}
		if ( ! self::is_disabled_for( $context->post ) ) {
			return $allowed;
		}
		if ( true === $allowed ) {
			$allowed = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
		}
		return array_values( array_diff( (array) $allowed, self::BLOCKS ) );
	}

	/**
	 * Hides the comment blocks everywhere, including the site editor. Blocks
	 * already used in templates stay valid and simply render nothing.
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block name.
	 */
	public static function hide_blocks_from_inserter( $args, $block_type ): array {
		$args = (array) $args;
		if ( in_array( $block_type, self::BLOCKS, true ) ) {
			$args['supports']             = isset( $args['supports'] ) && is_array( $args['supports'] ) ? $args['supports'] : [];
			$args['supports']['inserter'] = false;
		}
		return $args;
	}

	public static function block_comment_feeds(): void {
		if ( is_comment_feed() ) {
			wp_die( esc_html__( 'Comments are disabled on this site.', 'rmd-site-settings' ), '', [ 'response' => 403 ] );
		}
	}

	/**
	 * @param array<string, string> $headers HTTP headers.
	 * @return array<string, string>
	 */
	public static function remove_pingback_header( $headers ): array {
		$headers = (array) $headers;
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function unregister_widget(): void {
		unregister_widget( 'WP_Widget_Recent_Comments' );
		add_filter( 'show_recent_comments_widget_style', '__return_false' );
	}

	public static function remove_admin_bar_nodes( WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'comments' );
		foreach ( array_keys( (array) $bar->get_nodes() ) as $id ) {
			if ( 1 === preg_match( '/^blog-\d+-c$/', (string) $id ) ) {
				$bar->remove_node( (string) $id );
			}
		}
	}

	public static function remove_admin_menus(): void {
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		if ( ! self::$scope->keeps_existing() ) {
			remove_menu_page( 'edit-comments.php' );
		}
	}

	public static function block_admin_screens(): void {
		global $pagenow;

		$blocked = [ 'options-discussion.php' ];
		if ( ! self::$scope->keeps_existing() ) {
			$blocked[] = 'edit-comments.php';
			$blocked[] = 'comment.php';
		}
		if ( in_array( $pagenow, $blocked, true ) ) {
			wp_die( esc_html__( 'Comments are disabled on this site.', 'rmd-site-settings' ), '', [ 'response' => 403 ] );
		}
	}

	public static function remove_dashboard_widget(): void {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Hides comment counts in "At a Glance" and "Activity" and the comment shortcuts on the profile screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function admin_styles( $hook ): void {
		if ( in_array( $hook, [ 'index.php', 'profile.php', 'user-edit.php' ], true ) ) {
			wp_enqueue_style( 'rmd-ss-comments-off', RMD_SS_URL . 'assets/css/comments-off.css', [], RMD_SS_VERSION );
		}
	}

	/**
	 * Rejects requests to the comments endpoints, except for notes.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param \WP_REST_Server $server  Server instance.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function filter_rest( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( 1 !== preg_match( '#^/wp/v2/comments(?:/(\d+))?/?$#', $request->get_route(), $match ) ) {
			return $result;
		}
		if ( self::NOTE_TYPE === $request->get_param( 'type' ) ) {
			return $result;
		}
		if ( isset( $match[1] ) ) {
			$comment = get_comment( (int) $match[1] );
			if ( $comment && self::NOTE_TYPE === $comment->comment_type ) {
				return $result;
			}
		}
		return new WP_Error( 'rmd_ss_comments_disabled', __( 'Comments are disabled on this site.', 'rmd-site-settings' ), [ 'status' => 403 ] );
	}

	/**
	 * @param array<string, mixed> $methods XML-RPC methods.
	 * @return array<string, mixed>
	 */
	public static function filter_xmlrpc_methods( $methods ): array {
		$methods = (array) $methods;
		unset( $methods['wp.newComment'], $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	public static function discussion_notice(): void {
		$screen = get_current_screen();
		if ( self::$scope->is_everywhere() || ! $screen || 'options-discussion' !== $screen->id || ! current_user_can( Plugin::CAP ) ) {
			return;
		}
		$labels = [];
		foreach ( Settings::section( 'comments' )['post_types'] as $type ) {
			$object   = get_post_type_object( $type );
			$labels[] = $object ? $object->labels->name : $type;
		}
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of post types */
					__( 'Comments are disabled for: %s. Settings on this page do not apply to them.', 'rmd-site-settings' ),
					implode( ', ', $labels )
				)
			),
			esc_url( \RMD\SiteSettings\Admin\SettingsPage::url() ),
			esc_html__( 'Change in Site settings', 'rmd-site-settings' )
		);
	}
}
