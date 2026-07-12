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
		if ( AjaxHandler::is_spam() ) {
			// Mark as spam ('spam') or hold for moderation ('0')
			return 'spam';
		}
		return $approved;
	}

	public function filter_registrations( $errors, $sanitized_user_login, $user_email ) {
		if ( AjaxHandler::is_spam() ) {
			$errors->add( 'spam_registration', __( '<strong>ERROR</strong>: Automated registration detected.', 'sam-anti-spam' ) );
		}
		return $errors;
	}

	public function filter_cf7( $result, $tags ) {
		if ( AjaxHandler::is_spam() ) {
			$result->invalidate( $tags[0], __( 'Spam detected. Please try again.', 'sam-anti-spam' ) );
		}
		return $result;
	}

	public function filter_woo_checkout() {
		if ( AjaxHandler::is_spam() ) {
			wc_add_notice( __( 'Checkout blocked due to suspicious activity.', 'sam-anti-spam' ), 'error' );
		}
	}
}
