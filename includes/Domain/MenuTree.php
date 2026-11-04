<?php
/**
 * Nests flat menu items by their parent.
 *
 * @package RMD\SiteSettings
 */

declare(strict_types=1);

namespace RMD\SiteSettings\Domain;

final class MenuTree {

	/**
	 * Children per parent, in the given order. Items whose parent is not part
	 * of the menu (or that point to themselves) are shown at the top level.
	 *
	 * @param array<int, int> $parents Item ID => parent item ID (0 = top level).
	 * @return array<int, list<int>> Parent item ID => child item IDs.
	 */
	public static function children( array $parents ): array {
		$children = [];
		foreach ( $parents as $id => $parent ) {
			if ( $parent === $id || ! isset( $parents[ $parent ] ) ) {
				$parent = 0;
			}
			$children[ $parent ][] = $id;
		}
		return $children;
	}
}
