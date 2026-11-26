<?php
/**
 * Rimozione dati di RSW UTM Grabber alla disinstallazione.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_name = 'rsw_utm_grabber_cookie_days';

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	if ( is_array( $site_ids ) ) {
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( $option_name );
			restore_current_blog();
		}
	}

	delete_site_option( $option_name );
} else {
	delete_option( $option_name );
}
