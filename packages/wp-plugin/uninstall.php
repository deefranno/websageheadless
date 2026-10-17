<?php
/**
 * Removes plugin-owned options and operational state. Pages, posts, media,
 * testimonials and calls to action are never touched. Form messages are
 * deleted only when the administrator opted in.
 *
 * @package WebSage\HeadlessCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wsh_settings = get_option( 'wsh_settings', array() );
if ( is_array( $wsh_settings ) && 'on' === ( $wsh_settings['delete_submissions_on_uninstall'] ?? '' ) ) {
	$wsh_ids = get_posts(
		array(
			'post_type'   => 'ws_submission',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $wsh_ids as $wsh_id ) {
		wp_delete_post( (int) $wsh_id, true );
	}
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wsh_rate_limits' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.

foreach ( array( 'wsh_settings', 'wsh_settings_backup_v0', 'wsh_revalidation_jobs', 'wsh_revalidation_last', 'wsh_db_version' ) as $wsh_option ) {
	delete_option( $wsh_option );
}
foreach ( array( 'wsh_revalidation_retry', 'wsh_contact_retention', 'wsh_contact_mail_retry' ) as $wsh_hook ) {
	wp_clear_scheduled_hook( $wsh_hook );
}
