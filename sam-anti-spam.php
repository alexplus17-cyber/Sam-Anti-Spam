<?php
/**
 * Plugin Name: Sam Anti Spam
 * Plugin URI:  https://github.com/alexplus17-cyber/Sam-Anti-Spam
 * Description: A premium WordPress anti-spam plugin with Invisible Anti-Spam Engine, Deep Integrations, and Traffic Control.
 * Version:     1.1.0
 * Author:      Alex
 * License:     GPL-2.0+
 */

namespace SamAntiSpam;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Define plugin constants
		$this->define_constants();

		// Register autoloader
		spl_autoload_register( array( $this, 'autoload' ) );

		// Hook into init
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		// Enqueue Admin Scripts/Styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Autoloader for plugin classes.
	 *
	 * @param string $class The fully-qualified class name.
	 */
	public function autoload( $class ) {
		// Project-specific namespace prefix
		$prefix   = 'SamAntiSpam\\';
		$base_dir = __DIR__ . '/includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// WordPress Coding Standards require lowercase "class-{name}.php" filenames.
		// Split the namespace (directory) portion from the class basename, then
		// map <Dir>/<ClassName> -> <Dir>/class-<classname>.php.
		$pos = strrpos( $relative_class, '\\' );
		if ( false !== $pos ) {
			$dir  = substr( $relative_class, 0, $pos );
			$name = substr( $relative_class, $pos + 1 );
		} else {
			$dir  = '';
			$name = $relative_class;
		}

		$relative_dir = str_replace( '\\', '/', $dir );
		$files        = array(
			$base_dir . $relative_dir . '/class-' . strtolower( $name ) . '.php',
			$base_dir . $relative_dir . '/' . $name . '.php',
		);

		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'SAM_ANTI_SPAM_VERSION', '1.1.0' );
		define( 'SAM_ANTI_SPAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'SAM_ANTI_SPAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'sam-anti-spam' ) !== false ) {
			wp_enqueue_style( 'sam-anti-spam-admin', SAM_ANTI_SPAM_PLUGIN_URL . 'assets/css/sam-admin.css', array(), SAM_ANTI_SPAM_VERSION );
			wp_enqueue_script( 'sam-anti-spam-admin-js', SAM_ANTI_SPAM_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SAM_ANTI_SPAM_VERSION, true );
			wp_localize_script(
				'sam-anti-spam-admin-js',
				'samAdmin',
				array(
					'nonce'      => wp_create_nonce( 'sam_admin_settings' ),
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'siteUrl'    => get_site_url(),
					'adminEmail' => get_option( 'admin_email' ),
				)
			);
		}
	}

	/**
	 * Initialize plugin modules.
	 */
	public function init() {
		// Initialize Bot Manager (early priority)
		$bot_manager = new Core\BotManager();
		$bot_manager->init();

		// Initialize Spam FireWall Manager
		$firewall = new Core\Firewall();
		$firewall->init();

		// Initialize Core Engine
		$ajax_handler = new Core\AjaxHandler();
		$ajax_handler->init();

		// Initialize Integrations
		$hooks = new Integration\Hooks();
		$hooks->init();

		// Initialize Traffic Control
		$rate_limiter = new TrafficControl\RateLimiter();
		$rate_limiter->init();

		// Initialize Admin if in dashboard
		if ( is_admin() ) {
			$settings = new Admin\Settings();
			$settings->init();

			$setup = new Admin\Setup();
			$setup->init();
		}
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate() {
		// Ensure the class is loaded since it's an activation hook
		require_once plugin_dir_path( __FILE__ ) . 'includes/Core/class-spamlogger.php';
		\SamAntiSpam\Core\SpamLogger::create_table();

		// Schedule cron job for SFW cache updates
		if ( ! wp_next_scheduled( 'sam_antispam_update_sfw_cache' ) ) {
			wp_schedule_event( time(), 'ten_minutes', 'sam_antispam_update_sfw_cache' );
		}
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate() {
		// Cleanup cron jobs
		wp_clear_scheduled_hook( 'sam_antispam_update_sfw_cache' );
	}
}

// Add custom 10 minute schedule interval
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['ten_minutes'] = array(
			'interval' => 600,
			'display'  => esc_html__( 'Every 10 Minutes' ),
		);
		return $schedules;
	}
);

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( '\\SamAntiSpam\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\SamAntiSpam\\Plugin', 'deactivate' ) );

// Initialize the plugin.
Plugin::get_instance();
