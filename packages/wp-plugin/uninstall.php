<?php
/**
 * Removes plugin-owned options. User content (pages, posts, media) is never touched.
 *
 * @package WebSage\HeadlessCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wsh_settings' );
