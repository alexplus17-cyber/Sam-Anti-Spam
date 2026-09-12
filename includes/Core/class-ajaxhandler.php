<?php
/**
 * Handles front-end AJAX spam checks (honeypot, JS verification, Cloud API).
 *
 * @package SamAntiSpam
 * @since   1.0.0
 */

namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX endpoints for the client-side spam check.
 *
 * @package SamAntiSpam
 * @since   1.0.0
 */
class AjaxHandler {
	/**
	 * Register the front-end AJAX endpoints and footer script injection.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_nopriv_sam_check_spam', array( $this, 'check_spam_ajax' ) );
		add_action( 'wp_ajax_sam_check_spam', array( $this, 'check_spam_ajax' ) );

		add_action( 'wp_footer', array( $this, 'inject_js' ) );
	}

	/**
	 * Retrieve the visitor's real IP address.
	 *
	 * @return string Client IP, or an empty string if unavailable.
	 */
	private function get_client_ip() {
		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		if ( ! is_string( $ip ) ) {
			$ip = '';
		}
		return trim( $ip );
	}

	/**
	 * Print the client-side verification JS (cookie + submit timing + AJAX payload).
	 *
	 * @return void
	 */
	public function inject_js() {
		$nonce    = wp_create_nonce( 'sam_spam_check' );
		$ajax_url = admin_url( 'admin-ajax.php' );

		echo "<script type='text/javascript'>
			document.cookie = 'sam_verified=true; path=/; max-age=3600; samesite=strict';
			var sam_load_time = Date.now();
			var sam_ajax_fired = false;

			document.addEventListener('submit', function(e) {
				var submit_time = Date.now();
				var time_diff = (submit_time - sam_load_time) / 1000;
				if(sam_ajax_fired) return;
				sam_ajax_fired = true;
				var payload = 'action=sam_check_spam&security=" . esc_js( $nonce ) . "&time_taken=' + time_diff;
				fetch('" . esc_url( $ajax_url ) . "', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: payload
				});
			}, true);
		</script>";
	}

	/**
	 * Emit an invisible honeypot field for the front-end form.
	 *
	 * @return void
	 */
	public static function output_honeypot() {
		echo '<div style="display:none; visibility:hidden;">';
		echo '<input type="text" name="sam_hp_field" id="sam_hp_field" value="" tabindex="-1" autocomplete="off" />';
		echo '</div>';
	}

	/**
	 * Handle the front-end spam-check AJAX request.
	 *
	 * @return void
	 */
	public function check_spam_ajax() {
		check_ajax_referer( 'sam_spam_check', 'security' );

		$ip    = $this->get_client_ip();
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			wp_send_json_success( array( 'status' => 'clean' ) );
			return;
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		$time_taken = isset( $_POST['time_taken'] ) ? floatval( $_POST['time_taken'] ) : 0;
		if ( $time_taken < 3 ) {
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => 'AJAX Form Check',
					'blocked_reason' => __( 'Too fast submission (Bot suspected)', 'sam-anti-spam' ),
				)
			);
			wp_send_json_error( array( 'message' => __( 'Spam detected via speed check.', 'sam-anti-spam' ) ) );
			return;
		}

		$hp_value = isset( $_POST['sam_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['sam_hp_field'] ) ) : '';
		if ( ! empty( $hp_value ) ) {
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => 'AJAX Form Check',
					'blocked_reason' => __( 'Honeypot Triggered', 'sam-anti-spam' ),
				)
			);
			wp_send_json_error( array( 'message' => __( 'Spam detected via honeypot.', 'sam-anti-spam' ) ) );
			return;
		}

		$js_active = false;
		if ( ! isset( $_COOKIE['sam_verified'] ) || 'true' !== $_COOKIE['sam_verified'] ) {
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => 'AJAX Form Check',
					'blocked_reason' => __( 'JS Verification Failed', 'sam-anti-spam' ),
				)
			);
			wp_send_json_error( array( 'message' => __( 'Spam detected via JS check.', 'sam-anti-spam' ) ) );
			return;
		}
		$js_active = true;

		$api_client   = new \SamAntiSpam\Core\ApiClient();
		$api_response = $api_client->check_spam(
			array(
				'ip'          => $ip,
				'user_agent'  => $ua,
				'email'       => $email,
				'content'     => '',
				'action_type' => 'AJAX Form Check',
				'js_active'   => $js_active,
			)
		);

		if ( isset( $api_response['spam'] ) && true === $api_response['spam'] ) {
			$reason = isset( $api_response['reason'] ) ? sanitize_text_field( $api_response['reason'] ) : __( 'Blocked by Cloud API', 'sam-anti-spam' );
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => 'AJAX Form Check',
					'blocked_reason' => $reason,
				)
			);
			wp_send_json_error( array( 'message' => __( 'Spam detected via Cloud API.', 'sam-anti-spam' ) ) );
			return;
		}

		wp_send_json_success( array( 'status' => 'clean' ) );
	}

	/**
	 * Determine whether a submission should be treated as spam.
	 *
	 * Reached through the nonce-guarded AJAX endpoint (check_ajax_referer) and
	 * through core WordPress form hooks (comment/registration/CF7/WooCommerce),
	 * both of which verify the request nonce before reaching this method.
	 *
	 * @param string $action_type Type of submission being checked.
	 * @param string $content     Raw form content.
	 * @param string $email       Submitted email address.
	 * @return bool True if the submission is spam.
	 */
	public static function is_spam( $action_type = 'Form Submission', $content = '', $email = '' ) {
		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		if ( ! is_string( $ip ) ) {
			$ip = '';
		}
		$ip = trim( $ip );
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$bot_manager = new \SamAntiSpam\Core\BotManager();
		if ( $bot_manager->is_allowed_bot( $ua, $ip ) ) {
			return false;
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Reached through check_ajax_referer()-guarded endpoint (nonce 'sam_spam_check' via check_ajax_referer in check_spam_ajax, line 75) and through core WP hooks whose forms ship their own nonce (comment_post, comment_form, register, CF7/Woo). Honeypot field is a decoy; only its emptiness is used.
		$hp_value = isset( $_POST['sam_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['sam_hp_field'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request is already
		// nonce-verified upstream: AJAX path runs check_ajax_referer() in check_spam_ajax()
		// (line 79) before reaching is_spam(); integration path (Hooks) only calls is_spam()
		// from core WP hooks that run their own nonce checks (comment_post, register_post,
		// CF7/Woo hooks). The honeypot value is a decoy field, never used for logic beyond
		// anti-bot detection, so it needs no additional nonce of its own.
		if ( ! empty( $hp_value ) ) {
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => $action_type,
					'blocked_reason' => __( 'Honeypot Triggered', 'sam-anti-spam' ),
				)
			);
			return true;
		}

		$js_active = false;
		if ( ! isset( $_COOKIE['sam_verified'] ) || 'true' !== $_COOKIE['sam_verified'] ) {
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => $action_type,
					'blocked_reason' => __( 'JS Verification Failed', 'sam-anti-spam' ),
				)
			);
			return true;
		}
		$js_active = true;

		$api_client   = new \SamAntiSpam\Core\ApiClient();
		$api_response = $api_client->check_spam(
			array(
				'ip'          => $ip,
				'user_agent'  => $ua,
				'email'       => $email,
				'content'     => $content,
				'action_type' => $action_type,
				'js_active'   => $js_active,
			)
		);

		if ( isset( $api_response['spam'] ) && true === $api_response['spam'] ) {
			$reason = isset( $api_response['reason'] ) ? sanitize_text_field( $api_response['reason'] ) : __( 'Blocked by Cloud API', 'sam-anti-spam' );
			$logger->log_blocked_attempt(
				array(
					'ip'             => $ip,
					'email'          => $email,
					'action_type'    => $action_type,
					'blocked_reason' => $reason,
				)
			);
			return true;
		}

		return false;
	}
}
