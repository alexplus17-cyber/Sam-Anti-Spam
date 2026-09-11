<?php
namespace SamAntiSpam\TrafficControl;

use SamAntiSpam\Core\ApiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateLimiter {
	public function init() {
		add_action( 'init', array( $this, 'check_rate_limit' ) );
	}

	public function check_rate_limit() {
		if ( is_admin() ) {
			return;
		}

		$ip = self::get_real_ip();
		if ( ! $ip ) {
			return;
		}

		$bot_manager = new \SamAntiSpam\Core\BotManager();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			return;
		}

		$ip_hash = md5( $ip );
		$transient_key = 'sam_rate_limit_' . $ip_hash;
		$transient_timeout_key = '_transient_timeout_' . $transient_key;
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
		} else {
			$requests++;

			if ( $requests > 60 ) {
				$api_client = new ApiClient();
				$api_client->report_spam( array(
					'ip'     => $ip,
					'email'  => '',
					'reason' => 'Rate Limit Exceeded (Over 60 requests/min)',
				) );

				wp_die( esc_html__( 'Rate limit exceeded. Please try again later.', 'sam-anti-spam' ), esc_html__( 'Sam Anti Spam', 'sam-anti-spam' ), array( 'response' => 429 ) );
			}

			$expiration = get_option( $transient_timeout_key );
			$remaining = 0;
			if ( false !== $expiration ) {
				$remaining = (int) $expiration - time();
			}

			if ( $remaining > 0 ) {
				set_transient( $transient_key, $requests, $remaining );
			}
		}
	}

	public static function get_real_ip() {
		$candidates = array();
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' !== $remote_addr ) {
			$candidates[] = $remote_addr;
		}

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			foreach ( explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) as $value ) {
				$candidate = trim( sanitize_text_field( $value ) );
				if ( '' !== $candidate ) {
					$candidates[] = $candidate;
				}
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
