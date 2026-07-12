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
		// Inject JS to set a verification cookie
		echo "<script type='text/javascript'>
			document.cookie = 'sam_verified=true; path=/; max-age=3600; samesite=strict';
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
		// Verify Nonce (assuming one is passed in real implementation, simplified here)
		// check_ajax_referer( 'sam_spam_check', 'security' );

		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		// Short-circuit if allowed bot
		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			wp_send_json_success( array( 'status' => 'clean' ) );
			return;
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		// Check Honeypot
		if ( isset( $_POST['sam_hp_field'] ) && ! empty( $_POST['sam_hp_field'] ) ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => 'Honeypot Triggered'
			) );
			wp_send_json_error( array( 'message' => 'Spam detected via honeypot.' ) );
			return;
		}

		// Check JS Cookie
		$js_active = false;
		if ( ! isset( $_COOKIE['sam_verified'] ) || $_COOKIE['sam_verified'] !== 'true' ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => 'JS Verification Failed'
			) );
			wp_send_json_error( array( 'message' => 'Spam detected via JS check.' ) );
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
			$reason = isset( $api_response['reason'] ) ? $api_response['reason'] : 'Blocked by Cloud API';
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => 'AJAX Form Check',
				'blocked_reason' => $reason
			) );
			wp_send_json_error( array( 'message' => 'Spam detected via Cloud API.' ) );
			return;
		}

		wp_send_json_success( array( 'status' => 'clean' ) );
	}

	// Helper for server-side validation during form submissions
	public static function is_spam( $action_type = 'Form Submission', $content = '', $email = '' ) {
		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		// Short-circuit if allowed bot
		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			return false; // Not spam
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		// Check Honeypot
		if ( isset( $_POST['sam_hp_field'] ) && ! empty( $_POST['sam_hp_field'] ) ) {
			$logger->log_blocked_attempt( array(
				'ip'             => $ip,
				'email'          => $email,
				'action_type'    => $action_type,
				'blocked_reason' => 'Honeypot Triggered'
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
				'blocked_reason' => 'JS Verification Failed'
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
			$reason = isset( $api_response['reason'] ) ? $api_response['reason'] : 'Blocked by Cloud API';
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
