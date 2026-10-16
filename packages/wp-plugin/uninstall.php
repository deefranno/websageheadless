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

foreach ( array( 'wsh_settings', 'wsh_settings_backup_v0', 'wsh_revalidation_jobs', 'wsh_revalidation_last' ) as $wsh_option ) {
	delete_option( $wsh_option );
}
wp_clear_scheduled_hook( 'wsh_revalidation_retry' );
