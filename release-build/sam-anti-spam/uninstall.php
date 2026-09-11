<?php
/**
 * Sam Anti Spam Uninstall Routine
 * 
 * Fired when the plugin is uninstalled via the WP Admin.
 */

// If uninstall is not called from WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 0. Clear scheduled cron jobs before anything else.
wp_clear_scheduled_hook( 'sam_antispam_update_sfw_cache' );
delete_transient( 'settings_errors' );

// 1. Drop the custom database table
$table_name = $wpdb->prefix . 'sam_spam_log';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

// 2. Delete plugin settings
delete_option( 'sam_antispam_settings' );
delete_option( 'sam_sfw_htaccess_backup' );

// 3. Delete the SFW cache file
$cache_file = ABSPATH . 'wp-content/sam-sfw-cache.txt';
if ( file_exists( $cache_file ) ) {
	unlink( $cache_file );
}

// 4. Remove SFW from .htaccess
require_once( ABSPATH . 'wp-admin/includes/misc.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
$htaccess_file = get_home_path() . '.htaccess';
if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
	insert_with_markers( $htaccess_file, 'Sam Anti Spam SFW', array() );
}

// 5. Optionally remove the sam-sfw.php file from root
$sfw_dest = ABSPATH . 'sam-sfw.php';
if ( file_exists( $sfw_dest ) ) {
	unlink( $sfw_dest );
}
