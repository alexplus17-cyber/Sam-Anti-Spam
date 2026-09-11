<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiClient {
	private string $api_url = 'https://api.samantispam.com/v1/check';
	private string $report_url = 'https://api.samantispam.com/v1/report';
	private string $api_key = '';

	public function __construct() {
		$options = get_option( 'sam_antispam_settings', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! empty( $options['api_key'] ) ) {
			$this->api_key = sanitize_text_field( $options['api_key'] );
		}

		$base = 'https://api.samantispam.com';
		if ( class_exists( '\\SamAntiSpam\\Admin\\Settings' ) ) {
			$configured = \SamAntiSpam\Admin\Settings::get_cloud_base_url();
			if ( is_string( $configured ) && '' !== trim( $configured ) ) {
				$base = trim( $configured );
			}
		}

		$base = rtrim( $base, '/' );
		if ( '' !== $base ) {
			$this->api_url    = $base . '/v1/check';
			$this->report_url = $base . '/v1/report';
		}
	}

	public function check_spam( array $data ): array {
		$fallback = array(
			'allow'  => true,
			'spam'   => false,
			'reason' => 'API Unreachable',
		);

		if ( empty( $data ) ) {
			return $fallback;
		}

		$args = array(
			'body'    => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 5,
		);

		$response = wp_remote_post( $this->api_url, $args );
		if ( is_wp_error( $response ) ) {
			return $fallback;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return $fallback;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $fallback;
		}

		$parsed = json_decode( $body, true );
		if ( ! is_array( $parsed ) ) {
			return $fallback;
		}

		return $parsed;
	}

	public function report_spam( array $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$args = array(
			'body'     => wp_json_encode( $data ),
			'headers'  => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout'  => 5,
			'blocking' => false,
		);

		$response = wp_remote_post( $this->report_url, $args );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		return 200 === $status_code || 202 === $status_code;
	}
}
