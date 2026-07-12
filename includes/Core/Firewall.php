<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Firewall {

	public function init() {
		add_action( 'sam_antispam_update_sfw_cache', array( $this, 'update_firewall_cache' ) );
	}

	/**
	 * Updates the local cache file with bad IPs from the remote API.
	 */
	public function update_firewall_cache() {
		// Only run if SFW is enabled
		$options = get_option( 'sam_antispam_settings', array() );
		if ( empty( $options['enable_sfw'] ) ) {
			return;
		}

		// Dummy API call for Phase 4 demonstrating the logic
		// In a real scenario, this queries the backend for the worst IPs
		$api_url = 'https://api.samantispam.com/v2/bad_ips';

		$response = wp_remote_get( $api_url, array( 'timeout' => 5 ) );

		// If the API call fails, do not wipe the cache file (graceful failure)
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$ips = json_decode( $body, true );

		// If json_decode fails (not an array), do not wipe the cache file
		if ( ! is_array( $ips ) ) {
			return;
		}

		// If the API explicitly returns an empty array, it means there are no bad IPs, so we clear the cache
		$cache_file = ABSPATH . 'wp-content/sam-sfw-cache.txt';
		$cache_content = empty( $ips ) ? '' : implode( "\n", $ips );

		// Use WP Filesystem if available, fallback to basic file_put_contents with lock
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->put_contents( $cache_file, $cache_content, FS_CHMOD_FILE );
		} else {
			file_put_contents( $cache_file, $cache_content, LOCK_EX );
		}
	}

	/**
	 * WARNING: MODIFYING .HTACCESS CAN BREAK THE SITE.
	 *
	 * This method writes an auto_prepend_file directive to the root .htaccess file.
	 * It uses WordPress's insert_with_markers to safely wrap the rule in custom comments.
	 *
	 * Always ensure you have a backup of .htaccess before modifying it manually.
	 */
	public function setup_firewall() {
		require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		$htaccess_file = get_home_path() . '.htaccess';
		$sfw_source    = SAM_ANTI_SPAM_PLUGIN_DIR . 'sam-sfw.php';
		$sfw_dest      = ABSPATH . 'sam-sfw.php';
		$cache_file    = ABSPATH . 'wp-content/sam-sfw-cache.txt';

		// Copy sam-sfw.php to root if it's not already there or if it's out of date
		if ( file_exists( $sfw_source ) ) {
			copy( $sfw_source, $sfw_dest );
		}

		// Initialize empty cache file if it doesn't exist so sam-sfw.php doesn't throw errors
		if ( ! file_exists( $cache_file ) ) {
			if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
				global $wp_filesystem;
				$wp_filesystem->put_contents( $cache_file, '', FS_CHMOD_FILE );
			} else {
				file_put_contents( $cache_file, '', LOCK_EX );
			}
		}

		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) || ! file_exists( $sfw_dest ) ) {
			return false;
		}

		// Backup existing .htaccess
		$current_contents = file_get_contents( $htaccess_file );
		update_option( 'sam_sfw_htaccess_backup', $current_contents, false );

		// Wrap in IfModule to prevent FastCGI 500 errors
		$rule = "<IfModule mod_php.c>\n";
		$rule .= "php_value auto_prepend_file '{$sfw_dest}'\n";
		$rule .= "</IfModule>\n";
		$rule .= "<IfModule mod_php5.c>\n";
		$rule .= "php_value auto_prepend_file '{$sfw_dest}'\n";
		$rule .= "</IfModule>\n";
		$rule .= "<IfModule mod_php7.c>\n";
		$rule .= "php_value auto_prepend_file '{$sfw_dest}'\n";
		$rule .= "</IfModule>";

		$rules = explode( "\n", $rule );

		// Insert with markers: # BEGIN Sam Anti Spam SFW / # END Sam Anti Spam SFW
		return insert_with_markers( $htaccess_file, 'Sam Anti Spam SFW', $rules );
	}

	/**
	 * Restores .htaccess from the backup option.
	 */
	public function restore_htaccess() {
		$backup = get_option( 'sam_sfw_htaccess_backup' );
		if ( $backup !== false ) {
			$htaccess_file = get_home_path() . '.htaccess';
			if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, $backup, LOCK_EX );
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes the firewall rules from .htaccess.
	 */
	public function remove_firewall() {
		require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		$htaccess_file = get_home_path() . '.htaccess';

		if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
			insert_with_markers( $htaccess_file, 'Sam Anti Spam SFW', array() );
		}

		// Optionally remove the file from root, though leaving it is harmless
		// $sfw_dest = ABSPATH . 'sam-sfw.php';
		// if ( file_exists( $sfw_dest ) ) { unlink( $sfw_dest ); }
	}
}
