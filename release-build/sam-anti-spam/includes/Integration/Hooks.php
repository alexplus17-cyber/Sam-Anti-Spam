<?php
namespace SamAntiSpam\Integration;

use SamAntiSpam\Core\AjaxHandler;
use SamAntiSpam\Core\ApiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	public function init() {
		// WordPress Core Hooks for processing
		add_filter( 'pre_comment_approved', array( $this, 'filter_comments' ), 99, 2 );
		add_filter( 'registration_errors', array( $this, 'filter_registrations' ), 10, 3 );

		// Output Honeypot in forms
		// We use standard WordPress hooks for comments and registration forms
		add_action( 'comment_form', array( '\SamAntiSpam\Core\AjaxHandler', 'output_honeypot' ) );
		add_action( 'register_form', array( '\SamAntiSpam\Core\AjaxHandler', 'output_honeypot' ) );

		// Third-party Plugin Hooks
		// Contact Form 7
		add_filter( 'wpcf7_validate', array( $this, 'filter_cf7' ), 20, 2 );
		
		// WooCommerce
		add_action( 'woocommerce_checkout_process', array( $this, 'filter_woo_checkout' ) );

		// Support Tickets (Business Sale)
		// Runs before TicketAjaxHandlers::submit_ticket (registered at priority 10)
		add_action( 'wp_ajax_bs_submit_ticket', array( $this, 'filter_support_ticket_ajax' ), 1 );
		// Catches non-AJAX (theme page template) ticket submissions
		add_filter( 'wp_insert_post_data', array( $this, 'filter_support_ticket_insert' ), 99, 2 );
	}

	public function filter_comments( $approved, $commentdata ) {
		$content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
		$email   = isset( $commentdata['comment_author_email'] ) ? $commentdata['comment_author_email'] : '';
		
		$is_spam = AjaxHandler::is_spam( 'Comment', $content, $email );
		if ( $is_spam ) {
			$this->report_to_cloud( $email, 'Local Heuristics Triggered (Comment)' );
			// Mark as spam ('spam') or hold for moderation ('0')
			return 'spam'; 
		}
		return $approved;
	}

	public function filter_registrations( $errors, $sanitized_user_login, $user_email ) {
		if ( AjaxHandler::is_spam( 'Registration', '', $user_email ) ) {
			$this->report_to_cloud( $user_email, 'Local Heuristics Triggered (Registration)' );
			$errors->add( 'spam_registration', __( '<strong>ERROR</strong>: Automated registration detected.', 'sam-anti-spam' ) );
		}
		return $errors;
	}

	public function filter_cf7( $result, $tags ) {
		// Attempt to extract email and content from CF7 submission
		$submission = \WPCF7_Submission::get_instance();
		$email = '';
		$content = '';
		if ( $submission ) {
			$data = $submission->get_posted_data();
			$email = isset( $data['your-email'] ) ? $data['your-email'] : '';
			$content = isset( $data['your-message'] ) ? $data['your-message'] : '';
		}

		if ( AjaxHandler::is_spam( 'Contact Form 7', $content, $email ) ) {
			$this->report_to_cloud( $email, 'Local Heuristics Triggered (Contact Form 7)' );
			$result->invalidate( $tags[0], __( 'Spam detected. Please try again.', 'sam-anti-spam' ) );
		}
		return $result;
	}

	public function filter_woo_checkout() {
		$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		if ( AjaxHandler::is_spam( 'WooCommerce Checkout', '', $email ) ) {
			$this->report_to_cloud( $email, 'Local Heuristics Triggered (WooCommerce Checkout)' );
			wc_add_notice( __( 'Checkout blocked due to suspicious activity.', 'sam-anti-spam' ), 'error' );
		}
	}

	/**
	 * Proactively blocks spam support ticket AJAX submissions.
	 * Runs before the Business Sale 'bs_submit_ticket' handler (priority 10).
	 */
	public function filter_support_ticket_ajax() {
		if ( ! $this->is_support_ticket_protection_enabled() ) {
			return;
		}

		$content = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$email   = is_user_logged_in() ? wp_get_current_user()->user_email : '';

		if ( AjaxHandler::is_spam( 'Support Ticket', $content, $email ) ) {
			$this->report_to_cloud( $email, 'Local Heuristics Triggered (Support Ticket)' );
			wp_send_json_error( array( 'message' => __( 'Spam detected. Your ticket was not submitted.', 'sam-anti-spam' ) ) );
		}
	}

	/**
	 * Blocks spam support ticket submissions that bypass the AJAX handler
	 * (e.g. the theme's classic POST form). Flags the post as trashed so it
	 * never appears as a live ticket.
	 *
	 * @param array $data    Slashed post data.
	 * @param array $postarr Post array as passed to wp_insert_post().
	 * @return array
	 */
	public function filter_support_ticket_insert( $data, $postarr ) {
		if ( ! $this->is_support_ticket_protection_enabled() ) {
			return $data;
		}

		// Only guard new support_ticket posts from front-end form submissions.
		if ( is_admin() ) {
			return $data;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $data;
		}
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			return $data;
		}
		if ( ! isset( $data['post_type'] ) || 'support_ticket' !== $data['post_type'] ) {
			return $data;
		}
		if ( ! empty( $postarr['ID'] ) ) {
			return $data;
		}

		$content = isset( $_POST['ticket_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ticket_message'] ) ) : '';
		$email   = is_user_logged_in() ? wp_get_current_user()->user_email : '';

		if ( AjaxHandler::is_spam( 'Support Ticket', $content, $email ) ) {
			$this->report_to_cloud( $email, 'Local Heuristics Triggered (Support Ticket)' );
			$data['post_status'] = 'trash';
		}

		return $data;
	}

	private function is_support_ticket_protection_enabled() {
		$options = get_option( 'sam_antispam_settings', array() );
		return ! empty( $options['enable_support_tickets'] );
	}

	private function report_to_cloud( $email, $reason ) {
		$api_client = new ApiClient();
		$ip = \SamAntiSpam\TrafficControl\RateLimiter::get_real_ip();
		$api_client->report_spam( array(
			'ip'     => $ip,
			'email'  => $email,
			'reason' => $reason
		) );
	}
}
