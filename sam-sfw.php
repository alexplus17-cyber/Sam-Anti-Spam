<?php
/**
 * Sam Anti Spam - Early Access Interceptor
 *
 * This file may be executed before WordPress loads through .htaccess.
 * It provides a minimal, safe server-level check for cached blocked IPs.
 * It intentionally avoids WordPress APIs and fatal errors when server settings are not compatible.
 */

$ip          = '';
$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
if ( is_string( $remote_addr ) && '' !== trim( $remote_addr ) && filter_var( trim( $remote_addr ), FILTER_VALIDATE_IP ) ) {
	$ip = trim( $remote_addr );
}

if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
	$cf_ip = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
	if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
		$ip = $cf_ip;
	}
}

if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
	foreach ( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $candidate ) {
		$clean = trim( (string) $candidate );
		if ( '' !== $clean && filter_var( $clean, FILTER_VALIDATE_IP ) ) {
			$ip = $clean;
			break;
		}
	}
}

if ( '' === $ip ) {
	return;
}

$cache_file = __DIR__ . '/wp-content/sam-sfw-cache.txt';
if ( ! is_file( $cache_file ) || ! is_readable( $cache_file ) ) {
	return;
}

$blocked_ips = file( $cache_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
if ( ! is_array( $blocked_ips ) ) {
	return;
}

foreach ( $blocked_ips as $blocked_ip ) {
	if ( $ip === trim( (string) $blocked_ip ) ) {
		header( 'HTTP/1.1 403 Forbidden' );
		exit( 'Access Denied by Sam Anti Spam FireWall.' );
	}
}
