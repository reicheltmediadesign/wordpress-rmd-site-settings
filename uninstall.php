<?php
/**
 * Removes the plugin's options on every site. Deleted comments stay deleted;
 * everything else returns to the WordPress defaults once the plugin is gone.
 *
 * @package RMD\SiteSettings
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the options of the current site.
 */
function rmd_ss_uninstall_site(): void {
	foreach ( [ 'rmd_ss_settings', 'rmd_ss_version', 'external_updates-rmd-site-settings' ] as $rmd_ss_option ) {
		delete_option( $rmd_ss_option );
	}
}

if ( is_multisite() ) {
	$rmd_ss_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);
	foreach ( $rmd_ss_site_ids as $rmd_ss_site_id ) {
		switch_to_blog( (int) $rmd_ss_site_id );
		rmd_ss_uninstall_site();
		restore_current_blog();
	}
} else {
	rmd_ss_uninstall_site();
}
