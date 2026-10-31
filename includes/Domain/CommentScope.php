<?php
/**
 * Decides whether comments are disabled for a post type.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class CommentScope {

	/**
	 * @param array{mode: string, post_types: list<string>, keep_existing: bool} $comments The "comments" settings section.
	 */
	public function __construct( private array $comments ) {
	}

	public function is_active(): bool {
		return Settings::MODE_OFF !== $this->comments['mode'];
	}

	public function is_everywhere(): bool {
		return Settings::MODE_EVERYWHERE === $this->comments['mode'];
	}

	public function keeps_existing(): bool {
		return $this->comments['keep_existing'];
	}

	public function is_disabled( string $post_type ): bool {
		return match ( $this->comments['mode'] ) {
			Settings::MODE_EVERYWHERE => true,
			Settings::MODE_POST_TYPES => in_array( $post_type, $this->comments['post_types'], true ),
			default                   => false,
		};
	}

	/**
	 * Post types out of $candidates whose comments are disabled.
	 *
	 * @param list<string> $candidates Post types to check.
	 * @return list<string>
	 */
	public function disabled_among( array $candidates ): array {
		return array_values( array_filter( $candidates, [ $this, 'is_disabled' ] ) );
	}
}
