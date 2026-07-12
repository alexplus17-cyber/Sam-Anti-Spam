<?php
/**
 * Plugin Name: Sam Anti Spam
 * Plugin URI:  https://github.com/alexplus17-cyber/Sam-Anti-Spam
 * Description: A premium WordPress anti-spam plugin with Invisible Anti-Spam Engine, Deep Integrations, and Traffic Control.
 * Version:     1.0.0
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
		$prefix = 'SamAntiSpam\\';
		$base_dir = __DIR__ . '/includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Replace the namespace prefix with the base directory, replace namespace
		// separators with directory separators in the relative class name, append
		// with .php
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
		}
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'SAM_ANTI_SPAM_VERSION', '1.0.0' );
		define( 'SAM_ANTI_SPAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'SAM_ANTI_SPAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'sam-anti-spam' ) !== false ) {
			wp_enqueue_style( 'sam-anti-spam-admin', SAM_ANTI_SPAM_PLUGIN_URL . 'assets/css/sam-admin.css', array(), SAM_ANTI_SPAM_VERSION );
		}
	}

	/**
	 * Initialize plugin modules.
	 */
	public function init() {
		// Initialize Bot Manager (early priority)
		$bot_manager = new Core\BotManager();
		$bot_manager->init();

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
			// Replace Dashboard.php with Settings.php
			$settings = new Admin\Settings();
			$settings->init();
		}
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate() {
		// Setup transients, initial DB options, etc.
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate() {
		// Cleanup transients, scheduled events, etc.
	}
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( '\\SamAntiSpam\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\SamAntiSpam\\Plugin', 'deactivate' ) );

// Initialize the plugin.
Plugin::get_instance();
