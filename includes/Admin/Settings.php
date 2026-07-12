<?php
namespace SamAntiSpam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_sam_restore_htaccess', array( $this, 'handle_restore_htaccess' ) );
	}

	public function add_settings_page() {
		add_options_page(
			'Sam Anti Spam Settings',
			'Sam Anti Spam',
			'manage_options',
			'sam-anti-spam',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		// Register with sanitize callback to merge options
		register_setting( 'sam_antispam_settings', 'sam_antispam_settings', array(
			'sanitize_callback' => array( $this, 'sanitize_settings' )
		) );

		// General Tab
		add_settings_section( 'sam_antispam_general', 'General', null, 'sam-anti-spam-general' );
		add_settings_field( 'api_key', 'API Key', array( $this, 'render_text_field' ), 'sam-anti-spam-general', 'sam_antispam_general', array( 'label_for' => 'api_key' ) );
		add_settings_field( 'enable_sfw', 'Enable Spam FireWall (SFW)', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-general', 'sam_antispam_general', array( 'label_for' => 'enable_sfw' ) );

		// Integrations Tab
		add_settings_section( 'sam_antispam_integrations', 'Integrations', null, 'sam-anti-spam-integrations' );
		add_settings_field( 'enable_comments', 'Protect Comments', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_comments' ) );
		add_settings_field( 'enable_registrations', 'Protect Registrations', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_registrations' ) );
		add_settings_field( 'enable_cf7', 'Protect Contact Form 7', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_cf7' ) );
		add_settings_field( 'enable_woo', 'Protect WooCommerce', array( $this, 'render_checkbox_field' ), 'sam-anti-spam-integrations', 'sam_antispam_integrations', array( 'label_for' => 'enable_woo' ) );

		// Traffic Control Tab
		add_settings_section( 'sam_antispam_traffic', 'Traffic Control', array( $this, 'render_traffic_text' ), 'sam-anti-spam-traffic' );

		// Whitelist/Blacklist Tab
		add_settings_section( 'sam_antispam_lists', 'Whitelist / Blacklist', null, 'sam-anti-spam-lists' );
		add_settings_field( 'whitelist_emails', 'Whitelist Emails', array( $this, 'render_textarea_field' ), 'sam-anti-spam-lists', 'sam_antispam_lists', array( 'label_for' => 'whitelist_emails' ) );
		add_settings_field( 'blacklist_ips', 'Blacklist IPs', array( $this, 'render_textarea_field' ), 'sam-anti-spam-lists', 'sam_antispam_lists', array( 'label_for' => 'blacklist_ips' ) );
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
			$checkboxes = array( 'enable_comments', 'enable_registrations', 'enable_cf7', 'enable_woo' );
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

	public function render_textarea_field( $args ) {
		$options = get_option( 'sam_antispam_settings' );
		$value = isset( $options[ $args['label_for'] ] ) ? esc_textarea( $options[ $args['label_for'] ] ) : '';
		echo '<textarea id="' . esc_attr( $args['label_for'] ) . '" name="sam_antispam_settings[' . esc_attr( $args['label_for'] ) . ']" rows="5" cols="50">' . $value . '</textarea>';
	}

	public function render_traffic_text() {
		echo '<p>Current Rate Limit: 60 requests per minute.</p>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include __DIR__ . '/views/settings-page.php';
	}
}
