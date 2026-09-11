<?php
namespace SamAntiSpam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_log_bulk_actions' ) );
		add_action( 'admin_post_sam_restore_htaccess', array( $this, 'handle_restore_htaccess' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
		
		// AJAX handlers for registration
		add_action( 'wp_ajax_sam_register_cloud', array( $this, 'ajax_register_cloud' ) );
		add_action( 'wp_ajax_sam_disconnect_cloud', array( $this, 'ajax_disconnect_cloud' ) );
	}

	public function add_settings_page() {
		$hook = add_options_page(
			'Sam Anti Spam Settings',
			'Sam Anti Spam',
			'manage_options',
			'sam-anti-spam',
			array( $this, 'render_settings_page' )
		);

		add_action( "load-{$hook}", array( $this, 'load_settings_page' ) );
	}

	public function load_settings_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		if ( 'log' === $tab ) {
			add_screen_option( 'per_page', array(
				'label'   => 'Logs per page',
				'default' => 20,
				'option'  => 'sam_spam_logs_per_page',
			) );
		}
	}

	public function set_screen_option( $status, $option, $value ) {
		if ( 'sam_spam_logs_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	public function register_settings() {
		// Register with sanitize callback to merge options
		register_setting( 'sam_antispam_settings', 'sam_antispam_settings', array(
			'sanitize_callback' => array( $this, 'sanitize_settings' )
		) );

		// General Tab (API connection UI handled custom, SFW handled standard)
		add_settings_section( 'sam_antispam_general', 'General', null, 'sam-anti-spam-general' );
		add_settings_field( 'api_connection', 'Cloud Connection', array( $this, 'render_api_connection_ui' ), 'sam-anti-spam-general', 'sam_antispam_general' );
		add_settings_field( 'cloud_api_url', 'Cloud API Base URL', array( $this, 'render_text_field' ), 'sam-anti-spam-general', 'sam_antispam_general', array( 'label_for' => 'cloud_api_url' ) );
		add_settings_field( 'enable_sfw', 'Enable Spam FireWall (SFW)', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-general', 'sam_antispam_general', array( 'label_for' => 'enable_sfw' ) );

		// Integrations Tab
		add_settings_section( 'sam_antispam_integrations', 'Integrations', null, 'sam-anti-spam-integrations' );
		add_settings_field( 'enable_comments', 'Protect Comments', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_comments' ) );
		add_settings_field( 'enable_registrations', 'Protect Registrations', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_registrations' ) );
		add_settings_field( 'enable_cf7', 'Protect Contact Form 7', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_cf7' ) );
		add_settings_field( 'enable_woo', 'Protect WooCommerce', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_woo' ) );
		add_settings_field( 'enable_support_tickets', 'Protect Support Tickets', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_support_tickets' ) );

		// Traffic Control Tab
		add_settings_section( 'sam_antispam_traffic', 'Traffic Control', array( $this, 'render_traffic_text' ), 'sam-anti-spam-traffic' );

		// Whitelist/Blacklist Tab
		add_settings_section( 'sam_antispam_lists', 'Whitelist / Blacklist', null, 'sam-anti-spam-lists' );
		add_settings_field( 'whitelist_emails', 'Whitelist Emails', array( $this, 'render_textarea_field' ), 'sam-anti-spam-lists', 'sam_antispam_lists', array( 'label_for' => 'whitelist_emails' ) );
		add_settings_field( 'blacklist_ips', 'Blacklist IPs', array( $this, 'render_textarea_field' ), 'sam-anti-spam-lists', 'sam_antispam_lists', array( 'label_for' => 'blacklist_ips' ) );
	}

	/**
	 * Get the base URL for the Sam Anti Spam Cloud API.
	 *
	 * Uses the hosted cloud API by default unless a custom base URL is configured.
	 *
	 * @return string
	 */
	public static function get_cloud_base_url() {
		$options = get_option( 'sam_antispam_settings', array() );
		if ( ! empty( $options['cloud_api_url'] ) ) {
			return untrailingslashit( esc_url_raw( $options['cloud_api_url'] ) );
		}

		return 'https://api.samantispam.com';
	}

	public function render_api_connection_ui() {
		$options = get_option( 'sam_antispam_settings', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$api_key = isset( $options['api_key'] ) ? sanitize_text_field( $options['api_key'] ) : '';

		$site_url    = get_site_url();
		$admin_email = get_option( 'admin_email' );

		$cloud_svg = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M7 18a4 4 0 0 1-.5-7.97A5 5 0 0 1 16.9 8.5 3.5 3.5 0 0 1 17 18H7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';

		echo '<div id="sam-api-status" class="sam-cloud">';

		if ( empty( $api_key ) ) {
			// State 1: Not Connected
			echo '<div class="sam-cloud__icon is-warning" aria-hidden="true">' . $cloud_svg . '</div>';
			echo '<div class="sam-cloud__body">';
			echo '<p class="sam-cloud__status"><span class="sam-pill is-warning"><span class="dot" aria-hidden="true"></span>Not Connected</span></p>';
			echo '<p class="sam-cloud__detail">Site <code><span id="sam-site-url">' . esc_html( $site_url ) . '</span></code> &middot; Email <code><span id="sam-admin-email">' . esc_html( $admin_email ) . '</span></code></p>';
			echo '</div>';
			echo '<div class="sam-cloud__actions">';
			echo '<button type="button" class="button button-primary" id="sam-connect-btn">Connect to Cloud</button>';
			echo '<span id="sam-connect-spinner" class="spinner"></span>';
			echo '</div>';
		} else {
			// State 2: Connected
			$masked_key = strlen( $api_key ) > 8
				? substr( $api_key, 0, 4 ) . str_repeat( '*', 20 ) . substr( $api_key, -4 )
				: substr( $api_key, 0, 2 ) . '****';
			echo '<div class="sam-cloud__icon is-success" aria-hidden="true">' . $cloud_svg . '</div>';
			echo '<div class="sam-cloud__body">';
			echo '<p class="sam-cloud__status"><span class="sam-pill is-success"><span class="dot" aria-hidden="true"></span>Cloud Connected</span></p>';
			echo '<p class="sam-cloud__detail">API Key <code>' . esc_html( $masked_key ) . '</code></p>';
			echo '</div>';
			echo '<div class="sam-cloud__actions">';
			echo '<button type="button" class="button button-secondary" id="sam-disconnect-btn">Disconnect</button>';
			echo '<span id="sam-connect-spinner" class="spinner"></span>';
			echo '</div>';
		}

		echo '</div>';
	}

	public function ajax_register_cloud() {
		check_ajax_referer( 'sam_admin_settings', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$site_url = isset( $_POST['site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) : '';
		$admin_email = isset( $_POST['admin_email'] ) ? sanitize_email( wp_unslash( $_POST['admin_email'] ) ) : '';

		$register_url = rtrim( self::get_cloud_base_url(), '/' ) . '/v1/register';

		$response = wp_remote_post( $register_url, array(
			'body'    => wp_json_encode( array( 'site_url' => $site_url, 'admin_email' => $admin_email ) ),
			'headers' => array( 'Content-Type' => 'application/json' ),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Connection to cloud failed: ' . $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['success'] ) && $data['success'] === true && ! empty( $data['api_key'] ) ) {
			$options = get_option( 'sam_antispam_settings', array() );
			$options['api_key'] = sanitize_text_field( $data['api_key'] );
			update_option( 'sam_antispam_settings', $options );
			
			wp_send_json_success( array( 
				'message' => 'Successfully connected to cloud.',
				'api_key' => sanitize_text_field( $data['api_key'] )
			) );
		} else {
			$err = isset( $data['error'] ) ? sanitize_text_field( $data['error'] ) : 'Unknown error from API.';
			wp_send_json_error( array( 'message' => 'Registration failed: ' . $err ) );
		}
	}

	public function ajax_disconnect_cloud() {
		check_ajax_referer( 'sam_admin_settings', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$options = get_option( 'sam_antispam_settings', array() );
		if ( isset( $options['api_key'] ) ) {
			unset( $options['api_key'] );
			update_option( 'sam_antispam_settings', $options );
		}

		wp_send_json_success( array( 'message' => 'Disconnected from cloud.' ) );
	}

	public function handle_restore_htaccess() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'sam_restore_htaccess' );

		$firewall = new \SamAntiSpam\Core\Firewall();
		if ( $firewall->restore_htaccess() ) {
			add_settings_error( 'sam_antispam_settings', 'sam_sfw_restored', '.htaccess successfully restored.', 'success' );
		} else {
			add_settings_error( 'sam_antispam_settings', 'sam_sfw_restore_failed', 'Failed to restore .htaccess (no backup found or permission denied).', 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect( admin_url( 'options-general.php?page=sam-anti-spam&tab=general' ) );
		exit;
	}

	public function sanitize_settings( $input ) {
		// Merge new inputs with existing settings to prevent data loss across tabs
		$existing = get_option( 'sam_antispam_settings', array() );
		
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				// Use correct sanitization based on field type
				if ( in_array( $key, array( 'whitelist_emails', 'blacklist_ips' ), true ) ) {
					$existing[ $key ] = sanitize_textarea_field( $value );
				} else {
					$existing[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		// Handle unchecking of checkboxes (they aren't sent in POST if unchecked)
		$tab = isset( $_POST['sam_active_tab'] ) ? sanitize_text_field( $_POST['sam_active_tab'] ) : 'general';
		
		if ( $tab === 'general' ) {
			if ( ! isset( $input['enable_sfw'] ) ) {
				unset( $existing['enable_sfw'] );
				// If disabled, remove firewall rules
				$firewall = new \SamAntiSpam\Core\Firewall();
				$firewall->remove_firewall();
			} else {
				// If enabled, setup firewall rules
				$firewall = new \SamAntiSpam\Core\Firewall();
				$firewall->setup_firewall();
			}
		} elseif ( $tab === 'integrations' ) {
			$checkboxes = array( 'enable_comments', 'enable_registrations', 'enable_cf7', 'enable_woo', 'enable_support_tickets' );
			foreach ( $checkboxes as $cb ) {
				if ( ! isset( $input[ $cb ] ) ) {
					unset( $existing[ $cb ] );
				}
			}
		}

		return $existing;
	}

	public function render_text_field( $args ) {
		$options = get_option( 'sam_antispam_settings' );
		$value = isset( $options[ $args['label_for'] ] ) ? esc_attr( $options[ $args['label_for'] ] ) : '';
		echo '<input type="text" id="' . esc_attr( $args['label_for'] ) . '" name="sam_antispam_settings[' . esc_attr( $args['label_for'] ) . ']" value="' . $value . '" class="regular-text" />';
	}

	public function render_checkbox_field( $args ) {
		$options = get_option( 'sam_antispam_settings' );
		$checked = isset( $options[ $args['label_for'] ] ) ? checked( 1, $options[ $args['label_for'] ], false ) : '';
		echo '<input type="checkbox" id="' . esc_attr( $args['label_for'] ) . '" name="sam_antispam_settings[' . esc_attr( $args['label_for'] ) . ']" value="1" ' . $checked . '/>';
		if ( $args['label_for'] === 'enable_sfw' ) {
			echo '<p class="description">Requires .htaccess modification. Be sure you know what you are doing. <strong>Note: This may not work correctly on FastCGI/PHP-FPM server environments.</strong></p>';
		}
	}

	public function render_traffic_text() {
		echo '<p>Rate limiting is active by default (60 requests/minute per IP). Requests exceeding this threshold are blocked and reported.</p>';
	}

	public function render_textarea_field( $args ) {
		$options = get_option( 'sam_antispam_settings' );
		$value = isset( $options[ $args['label_for'] ] ) ? esc_textarea( $options[ $args['label_for'] ] ) : '';
		echo '<textarea id="' . esc_attr( $args['label_for'] ) . '" name="sam_antispam_settings[' . esc_attr( $args['label_for'] ) . ']" rows="5" cols="50">' . $value . '</textarea>';
	}

	public function handle_log_bulk_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';
		$tab  = isset( $_REQUEST['tab'] ) ? sanitize_text_field( $_REQUEST['tab'] ) : '';

		if ( 'sam-anti-spam' !== $page || 'log' !== $tab ) {
			return;
		}

		$action = '';
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$action = sanitize_text_field( $_REQUEST['action'] );
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_text_field( $_REQUEST['action2'] );
		}

		if ( empty( $action ) ) {
			return;
		}

		// Verify Nonce
		if ( isset( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'bulk-spam_logs' ) ) {
			return;
		}

		$logger = new \SamAntiSpam\Core\SpamLogger();

		if ( 'clear_all' === $action ) {
			$logger->clear_all_logs();
			add_settings_error( 'sam_antispam_settings', 'sam_log_cleared', 'All spam logs cleared successfully.', 'success' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_redirect( admin_url( 'options-general.php?page=sam-anti-spam&tab=log' ) );
			exit;
		}

		if ( 'blacklist_ip' === $action && ! empty( $_REQUEST['ip'] ) ) {
			$ip      = sanitize_text_field( $_REQUEST['ip'] );
			$options = get_option( 'sam_antispam_settings', array() );
			$current = isset( $options['blacklist_ips'] ) ? $options['blacklist_ips'] : '';
			$ips     = array_filter( array_map( 'trim', explode( "\n", $current ) ) );
			if ( ! in_array( $ip, $ips, true ) ) {
				$ips[]                    = $ip;
				$options['blacklist_ips'] = implode( "\n", $ips );
				update_option( 'sam_antispam_settings', $options );
				add_settings_error( 'sam_antispam_settings', 'sam_ip_blacklisted', sprintf( 'IP %s added to blacklist.', esc_html( $ip ) ), 'success' );
			} else {
				add_settings_error( 'sam_antispam_settings', 'sam_ip_exists', sprintf( 'IP %s is already blacklisted.', esc_html( $ip ) ), 'info' );
			}
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_redirect( admin_url( 'options-general.php?page=sam-anti-spam&tab=log' ) );
			exit;
		}

		$ids = isset( $_REQUEST['spam_log'] ) ? (array) $_REQUEST['spam_log'] : array();
		$ids = array_map( 'intval', array_filter( $ids, 'is_numeric' ) );

		if ( empty( $ids ) ) {
			return;
		}

		if ( 'delete' === $action || 'bulk-delete' === $action ) {
			$deleted = $logger->delete_logs( $ids );
			add_settings_error( 'sam_antispam_settings', 'sam_logs_deleted', sprintf( '%d spam log entry/entries deleted.', $deleted ), 'success' );
		} elseif ( 'blacklist_ips' === $action ) {
			$logs    = $logger->get_logs_by_ids( $ids );
			$options = get_option( 'sam_antispam_settings', array() );
			$current = isset( $options['blacklist_ips'] ) ? $options['blacklist_ips'] : '';
			$ips     = array_filter( array_map( 'trim', explode( "\n", $current ) ) );
			$added   = 0;
			foreach ( $logs as $row ) {
				if ( ! empty( $row['ip'] ) && ! in_array( $row['ip'], $ips, true ) ) {
					$ips[] = $row['ip'];
					$added++;
				}
			}
			if ( $added > 0 ) {
				$options['blacklist_ips'] = implode( "\n", $ips );
				update_option( 'sam_antispam_settings', $options );
				add_settings_error( 'sam_antispam_settings', 'sam_ips_blacklisted', sprintf( '%d IP(s) added to blacklist.', $added ), 'success' );
			} else {
				add_settings_error( 'sam_antispam_settings', 'sam_ips_no_new', 'Selected IP(s) are already blacklisted.', 'info' );
			}
		} elseif ( 'whitelist_emails' === $action ) {
			$logs    = $logger->get_logs_by_ids( $ids );
			$options = get_option( 'sam_antispam_settings', array() );
			$current = isset( $options['whitelist_emails'] ) ? $options['whitelist_emails'] : '';
			$emails  = array_filter( array_map( 'trim', explode( "\n", $current ) ) );
			$added   = 0;
			foreach ( $logs as $row ) {
				if ( ! empty( $row['email'] ) && ! in_array( $row['email'], $emails, true ) ) {
					$emails[] = $row['email'];
					$added++;
				}
			}
			if ( $added > 0 ) {
				$options['whitelist_emails'] = implode( "\n", $emails );
				update_option( 'sam_antispam_settings', $options );
				add_settings_error( 'sam_antispam_settings', 'sam_emails_whitelisted', sprintf( '%d email(s) added to whitelist.', $added ), 'success' );
			} else {
				add_settings_error( 'sam_antispam_settings', 'sam_emails_no_new', 'Selected email(s) are already whitelisted or empty.', 'info' );
			}
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect( admin_url( 'options-general.php?page=sam-anti-spam&tab=log' ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include __DIR__ . '/views/settings-page.php';
	}
}
