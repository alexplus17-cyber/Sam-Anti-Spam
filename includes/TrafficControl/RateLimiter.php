<?php
namespace SamAntiSpam\TrafficControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateLimiter {
	public function init() {
		// Hook into early WordPress load
		add_action( 'init', array( $this, 'check_rate_limit' ) );
	}

	public function check_rate_limit() {
		if ( is_admin() ) {
			return; // Don't block admin actions here
		}

		$ip = self::get_real_ip();
		if ( ! $ip ) {
			return;
		}

		// Short-circuit if allowed bot
		$bot_manager = new \SamAntiSpam\Core\BotManager();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			return;
		}

		$ip_hash = md5( $ip );
		$transient_key = 'sam_rate_limit_' . $ip_hash;
		$transient_timeout_key = '_transient_timeout_' . $transient_key;

		// Get current request count for this IP
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			// First request, set transient to expire in 1 minute
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
		} else {
			$requests++;

			// If more than 60 requests per minute, block
			if ( $requests > 60 ) {
				wp_die( esc_html__( 'Rate limit exceeded. Please try again later.', 'sam-anti-spam' ), esc_html__( 'Sam Anti Spam', 'sam-anti-spam' ), array( 'response' => 429 ) );
			}

			// Update the transient value but preserve the original expiration time
			// get_option for the timeout to calculate the remaining time
			$expiration = get_option( $transient_timeout_key );
			$remaining = 0;
			if ( $expiration !== false ) {
				$remaining = $expiration - time();
			}

			// Only update if there is still time left, otherwise let it expire naturally
			if ( $remaining > 0 ) {
				set_transient( $transient_key, $requests, $remaining );
			}
		}
	}

	public static function get_real_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';

		// Check if behind a known proxy (Cloudflare, etc.)
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			// Cloudflare specific
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
	}
}
