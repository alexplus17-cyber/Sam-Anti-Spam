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
		// Check Honeypot
		if ( isset( $_POST['sam_hp_field'] ) && ! empty( $_POST['sam_hp_field'] ) ) {
			wp_send_json_error( array( 'message' => 'Spam detected via honeypot.' ) );
			return;
		}

		// Check JS Cookie
		if ( ! isset( $_COOKIE['sam_verified'] ) || $_COOKIE['sam_verified'] !== 'true' ) {
			wp_send_json_error( array( 'message' => 'Spam detected via JS check.' ) );
			return;
		}

		wp_send_json_success( array( 'status' => 'clean' ) );
	}

	// Helper for server-side validation during form submissions
	public static function is_spam() {
		// Check Honeypot
		if ( isset( $_POST['sam_hp_field'] ) && ! empty( $_POST['sam_hp_field'] ) ) {
			return true;
		}

		// Check JS Cookie
		if ( ! isset( $_COOKIE['sam_verified'] ) || $_COOKIE['sam_verified'] !== 'true' ) {
			return true;
		}

		return false;
	}
}
