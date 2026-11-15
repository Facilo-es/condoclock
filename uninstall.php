<?php
/**
 * Removes records, files and settings, only if the owner ticked "Delete all data when uninstalled".
 * The Owners' Records and Notices pages and the user accounts stay: they belong to the site.
 *
 * @package CondoClock
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$condoclock_settings = get_option( 'condoclock_settings', array() );

wp_clear_scheduled_hook( 'condoclock_daily' );

if ( empty( $condoclock_settings['delete_on_uninstall'] ) ) {
	return;
}

$condoclock_ids = get_posts(
	array(
		'post_type'     => array( 'condoclock_record', 'condoclock_meeting' ),
		'post_status'   => 'any',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'no_found_rows' => true,
	)
);
foreach ( $condoclock_ids as $condoclock_id ) {
	wp_delete_post( $condoclock_id, true );
}

$condoclock_up = wp_upload_dir( null, false );
require_once ABSPATH . 'wp-admin/includes/file.php';
if ( WP_Filesystem() ) {
	global $wp_filesystem;
	$wp_filesystem->delete( $condoclock_up['basedir'] . '/condoclock-private', true );
}

foreach ( array( 'condoclock_unit', 'condoclock_employee', 'condoclock_invited', 'condoclock_last_login', 'condoclock_approved_by' ) as $condoclock_key ) {
	delete_metadata( 'user', 0, $condoclock_key, '', true );
}
foreach ( array( 'condoclock_owner', 'condoclock_board' ) as $condoclock_role ) {
	remove_role( $condoclock_role );
}
$condoclock_admin = get_role( 'administrator' );
if ( $condoclock_admin ) {
	$condoclock_admin->remove_cap( 'condoclock_manage' );
	$condoclock_admin->remove_cap( 'condoclock_view_records' );
}

foreach ( array( 'condoclock_settings', 'condoclock_na', 'condoclock_private_dir', 'condoclock_selftest', 'condoclock_selftest_url', 'condoclock_cron_last', 'condoclock_redirect', 'condoclock_roles_v', 'condoclock_cache_v', 'condoclock_setup_menu' ) as $condoclock_option ) {
	delete_option( $condoclock_option );
}
