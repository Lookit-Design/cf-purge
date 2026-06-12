<?php
/**
 * Uninstall routine for Lookit Cloudflare Cache Purge.
 *
 * Removes the stored Cloudflare credentials so the API token does not linger
 * in the database after the plugin is deleted.
 *
 * @package Lookit_CF_Purge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$lookit_cf_purge_option = 'lookit_cf_purge_settings';

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $lookit_cf_purge_site_id ) {
		switch_to_blog( $lookit_cf_purge_site_id );
		delete_option( $lookit_cf_purge_option );
		restore_current_blog();
	}
} else {
	delete_option( $lookit_cf_purge_option );
}
