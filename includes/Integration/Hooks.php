<?php
namespace SamAntiSpam\Integration;

use SamAntiSpam\Core\AjaxHandler;

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
	}

	public function filter_comments( $approved, $commentdata ) {
		$content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
		$email   = isset( $commentdata['comment_author_email'] ) ? $commentdata['comment_author_email'] : '';

		if ( AjaxHandler::is_spam( 'Comment', $content, $email ) ) {
			// Mark as spam ('spam') or hold for moderation ('0')
			return 'spam';
		}
		return $approved;
	}

	public function filter_registrations( $errors, $sanitized_user_login, $user_email ) {
		if ( AjaxHandler::is_spam( 'Registration', '', $user_email ) ) {
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
			$result->invalidate( $tags[0], __( 'Spam detected. Please try again.', 'sam-anti-spam' ) );
		}
		return $result;
	}

	public function filter_woo_checkout() {
		$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		if ( AjaxHandler::is_spam( 'WooCommerce Checkout', '', $email ) ) {
			wc_add_notice( __( 'Checkout blocked due to suspicious activity.', 'sam-anti-spam' ), 'error' );
		}
	}
}
