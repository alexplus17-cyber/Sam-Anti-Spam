<?php
namespace SamAntiSpam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu_page() {
		add_menu_page(
			'Sam Anti Spam Settings',
			'Sam Anti Spam',
			'manage_options',
			'sam-anti-spam',
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			80
		);
	}

	public function register_settings() {
		register_setting( 'sam_anti_spam_options', 'sam_anti_spam_settings' );

		add_settings_section(
			'sam_anti_spam_general',
			'General Settings',
			null,
			'sam-anti-spam'
		);

		add_settings_field(
			'sam_anti_spam_enable_honeypot',
			'Enable Honeypot',
			array( $this, 'render_checkbox_field' ),
			'sam-anti-spam',
			'sam_anti_spam_general',
			array( 'label_for' => 'enable_honeypot' )
		);
	}

	public function render_checkbox_field( $args ) {
		$options = get_option( 'sam_anti_spam_settings' );
		$checked = isset( $options[ $args['label_for'] ] ) ? checked( 1, $options[ $args['label_for'] ], false ) : '';
		echo '<input type="checkbox" id="' . esc_attr( $args['label_for'] ) . '" name="sam_anti_spam_settings[' . esc_attr( $args['label_for'] ) . ']" value="1" ' . $checked . '/>';
	}

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'sam_anti_spam_options' );
				do_settings_sections( 'sam-anti-spam' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}
}
