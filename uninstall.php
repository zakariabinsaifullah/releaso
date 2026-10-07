<?php
/**
 * Uninstall: removes everything only when "Delete all data on uninstall" is ticked in
 * Releaso → Settings. Otherwise products and releases stay for a later reinstall.
 *
 * @package Releaso
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$releaso_settings = get_option( 'releaso_settings', array() );

wp_clear_scheduled_hook( 'releaso_sync' );
wp_clear_scheduled_hook( 'releaso_resync' );

if ( empty( $releaso_settings['delete_data'] ) ) {
	return;
}

global $wpdb;

foreach ( array( 'releaso_product', 'releaso_release' ) as $releaso_type ) {
	$releaso_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $releaso_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $releaso_ids as $releaso_id ) {
		wp_delete_post( (int) $releaso_id, true );
	}
}

foreach ( array( 'releaso_settings', 'releaso_db_version', 'releaso_cache_version', 'releaso_activated' ) as $releaso_option ) {
	delete_option( $releaso_option );
}

// Transients, locks and per-user notices.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_releaso\\_%' OR option_name LIKE '\\_transient\\_timeout\\_releaso\\_%' OR option_name LIKE 'releaso\\_lock\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
