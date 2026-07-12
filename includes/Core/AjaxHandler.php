<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AjaxHandler {
	public function init() {
		add_action( 'wp_ajax_nopriv_sam_check_spam', array( $this, 'check_spam_ajax' ) );
		add_action( 'wp_ajax_sam_check_spam', array( $this, 'check_spam_ajax' ) );

		// Only inject the JS in the footer, NOT the honeypot HTML.
		add_action( 'wp_footer', array( $this, 'inject_js' ) );
	}

	public function inject_js() {
		// Generate nonce
		$nonce = wp_create_nonce( 'sam_spam_check' );
		$ajax_url = admin_url( 'admin-ajax.php' );

		// Inject JS to set a verification cookie and send the AJAX request with the nonce
		// ONLY upon form submission to prevent DoS, and measure load time.
		echo "<script type='text/javascript'>
			document.cookie = 'sam_verified=true; path=/; max-age=3600; samesite=strict';
			var sam_load_time = Date.now();
			var sam_ajax_fired = false;

			document.addEventListener('submit', function(e) {
				// Calculate time taken to submit
				var submit_time = Date.now();
				var time_diff = (submit_time - sam_load_time) / 1000; // seconds

				// Prevent double firing if multiple forms exist
				if(sam_ajax_fired) return;
				sam_ajax_fired = true;

				// Append time_diff to the body payload
				var payload = 'action=sam_check_spam&security=" . esc_js( $nonce ) . "&time_taken=' + time_diff;

				// Simple AJAX fetch to notify the backend
				fetch('" . esc_url( $ajax_url ) . "', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: payload
				});
			}, true); // Use capturing phase to ensure it catches all submits
		</script>";
	}

	/**
	 * Output the HTML for the honeypot field.
	 * This should be called inside form rendering hooks.
	 */
	public static function output_honeypot() {
		echo '<div style="display:none; visibility:hidden;">';
		echo '<input type="text" name="sam_hp_field" id="sam_hp_field" value="" tabindex="-1" autocomplete="off" />';
		echo '</div>';
	}

	public function check_spam_ajax() {
		// Verify Nonce
		check_ajax_referer( 'sam_spam_check', 'security' );

		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Short-circuit if allowed bot
		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			wp_send_json_success( array( 'status' => 'clean' ) );
			return;
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		// Check Client-Side Time (less than 3 seconds usually means bot)
		$time_taken = isset( $_POST['time_taken'] ) ? floatval( $_POST['time_taken'] ) : 0;
		if ( $time_taken < 3 ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => __( 'Too fast submission (Bot suspected)', 'sam-anti-spam' )
			) );
			wp_send_json_error( array( 'message' => __( 'Spam detected via speed check.', 'sam-anti-spam' ) ) );
			return;
		}

		// Check Honeypot with Sanitization
		$hp_value = isset( $_POST['sam_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['sam_hp_field'] ) ) : '';
		if ( ! empty( $hp_value ) ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => __( 'Honeypot Triggered', 'sam-anti-spam' )
			) );
			wp_send_json_error( array( 'message' => __( 'Spam detected via honeypot.', 'sam-anti-spam' ) ) );
			return;
		}

		// Check JS Cookie
		$js_active = false;
		if ( ! isset( $_COOKIE['sam_verified'] ) || $_COOKIE['sam_verified'] !== 'true' ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => __( 'JS Verification Failed', 'sam-anti-spam' )
			) );
			wp_send_json_error( array( 'message' => __( 'Spam detected via JS check.', 'sam-anti-spam' ) ) );
			return;
		} else {
			$js_active = true;
		}

		// Cloud API Fallback
		$api_client = new \SamAntiSpam\Core\ApiClient();
		$api_response = $api_client->check_spam( array(
			'ip'          => $ip,
			'user_agent'  => $ua,
			'email'       => $email,
			'content'     => '',
			'action_type' => 'AJAX Form Check',
			'js_active'   => $js_active
		) );

		if ( isset( $api_response['spam'] ) && $api_response['spam'] === true ) {
			$reason = isset( $api_response['reason'] ) ? sanitize_text_field( $api_response['reason'] ) : __( 'Blocked by Cloud API', 'sam-anti-spam' );
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => $reason
			) );
			wp_send_json_error( array( 'message' => __( 'Spam detected via Cloud API.', 'sam-anti-spam' ) ) );
			return;
		}

		wp_send_json_success( array( 'status' => 'clean' ) );
	}

	// Helper for server-side validation during form submissions
	public static function is_spam( $action_type = 'Form Submission', $content = '', $email = '' ) {
		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Short-circuit if allowed bot
		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			return false; // Not spam
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		// Note: Time check is difficult server-side without JS tokens.
		// Handled via the AJAX pre-flight check predominantly.

		// Check Honeypot with Sanitization
		$hp_value = isset( $_POST['sam_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['sam_hp_field'] ) ) : '';
		if ( ! empty( $hp_value ) ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => $action_type,
				'blocked_reason' => __( 'Honeypot Triggered', 'sam-anti-spam' )
			) );
			return true;
		}

		// Check JS Cookie
		$js_active = false;
		if ( ! isset( $_COOKIE['sam_verified'] ) || $_COOKIE['sam_verified'] !== 'true' ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => $action_type,
				'blocked_reason' => __( 'JS Verification Failed', 'sam-anti-spam' )
			) );
			return true;
		} else {
			$js_active = true;
		}

		// Cloud API Fallback
		$api_client = new \SamAntiSpam\Core\ApiClient();
		$api_response = $api_client->check_spam( array(
			'ip'          => $ip,
			'user_agent'  => $ua,
			'email'       => $email,
			'content'     => $content,
			'action_type' => $action_type,
			'js_active'   => $js_active
		) );

		if ( isset( $api_response['spam'] ) && $api_response['spam'] === true ) {
			$reason = isset( $api_response['reason'] ) ? sanitize_text_field( $api_response['reason'] ) : __( 'Blocked by Cloud API', 'sam-anti-spam' );
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => $action_type,
				'blocked_reason' => $reason
			) );
			return true;
		}

		return false;
	}
}
