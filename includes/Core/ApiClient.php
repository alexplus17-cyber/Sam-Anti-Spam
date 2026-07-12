<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiClient {

	private $api_url = 'https://api.samantispam.com/v2/check';

	public function __construct() {
		// Override default API URL if set in settings
		$options = get_option( 'sam_antispam_settings' );
		if ( ! empty( $options['api_key'] ) ) {
			// In a real scenario, the API key might dictate a specific endpoint or be passed as a header
			// We just keep the default endpoint for now.
		}
	}

	/**
	 * Send data to the Cloud API for verification.
	 *
	 * @param array $data Data to send (ip, user_agent, email, content, action_type, js_active).
	 * @return array Expected format: ['spam' => bool, 'reason' => string, 'token' => string]
	 */
	public function check_spam( array $data ): array {

		// Fallback response if API fails
		$fallback = array( 'allow' => true, 'spam' => false, 'reason' => 'API Unreachable' );

		$args = array(
			'body'    => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 2, // Critical: 2 seconds max
		);

		$response = wp_remote_post( $this->api_url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'Sam Anti Spam API Connection Failed: ' . $response->get_error_message() );
			return $fallback;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			error_log( 'Sam Anti Spam API Connection Failed: HTTP ' . $status_code );
			return $fallback;
		}

		$body = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $body, true );

		if ( ! is_array( $parsed ) ) {
			error_log( 'Sam Anti Spam API Connection Failed: Invalid JSON response.' );
			return $fallback;
		}

		return $parsed;
	}
}
