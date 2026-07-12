<?php
/**
 * Sam Anti Spam - Early Access Interceptor
 *
 * This file is executed BEFORE WordPress loads, via an auto_prepend_file directive in .htaccess.
 * It provides server-level IP blocking to save PHP/Database execution time.
 * NO WORDPRESS FUNCTIONS ARE ALLOWED HERE.
 */

// Basic proxy-aware IP retrieval
$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';

if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
	$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$ip  = trim( $ips[0] );
} elseif ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );

if ( ! $ip ) {
	return; // Let request proceed if we can't determine IP
}

// Check against cached blocked IPs
// Assuming cache file is located in wp-content for accessibility
$cache_file = __DIR__ . '/wp-content/sam-sfw-cache.txt';

if ( file_exists( $cache_file ) && is_readable( $cache_file ) ) {
	$blocked_ips = file( $cache_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( $blocked_ips && in_array( $ip, $blocked_ips, true ) ) {
		header( 'HTTP/1.1 403 Forbidden' );
		die( 'Access Denied by Sam Anti Spam FireWall.' );
	}
}
