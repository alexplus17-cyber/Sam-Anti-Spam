<?php
namespace SamAntiSpam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Setup {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_setup_page' ) );
		add_action( 'admin_post_sam_setup_cloud_db', array( $this, 'handle_setup' ) );
	}

	public function add_setup_page() {
		add_submenu_page(
			'options-general.php',
			'Sam Anti Spam Setup',
			'Sam Setup',
			'manage_options',
			'sam-anti-spam-setup',
			array( $this, 'render_setup_page' )
		);
	}

	public static function get_backend_config_path() {
		return SAM_ANTI_SPAM_PLUGIN_DIR . 'backend/config.php';
	}

	public static function is_configured() {
		$path = self::get_backend_config_path();
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$cfg = include $path;
		if ( ! is_array( $cfg ) || empty( $cfg['db'] ) ) {
			return false;
		}

		try {
			$dsn = "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['db_name']};charset={$cfg['db']['charset']}";
			$pdo = new \PDO( $dsn, $cfg['db']['user'], $cfg['db']['pass'], array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ) );
			$stmt = $pdo->query( "SHOW TABLES LIKE 'api_keys'" );
			return $stmt->rowCount() > 0;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function render_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$step       = isset( $_GET['step'] ) ? max( 1, min( 4, (int) $_GET['step'] ) ) : 1;
		$configured = self::is_configured();
		$requirements = self::get_requirements();

		// If everything is already configured, land on the final step.
		if ( $configured && 1 === $step ) {
			$step = 4;
		}

		include __DIR__ . '/views/setup-page.php';
	}

	public function handle_setup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'sam_setup_cloud_db' );

		$host = isset( $_POST['db_host'] ) ? sanitize_text_field( wp_unslash( $_POST['db_host'] ) ) : '';
		$db   = isset( $_POST['db_name'] ) ? sanitize_text_field( wp_unslash( $_POST['db_name'] ) ) : '';
		$user = isset( $_POST['db_user'] ) ? sanitize_text_field( wp_unslash( $_POST['db_user'] ) ) : '';
		// Password may contain special characters; do not over-sanitize.
		$pass = isset( $_POST['db_pass'] ) ? (string) wp_unslash( $_POST['db_pass'] ) : '';

		$message = '';
		$ok      = false;

		if ( ! $host || ! $db || ! $user ) {
			$message = 'DB Host, Database Name and DB User are all required.';
		} else {
			try {
				$this->run_setup( $host, $db, $user, $pass );
				$ok      = true;
				$message = 'Cloud database created, schema imported, and config.php written.';
			} catch ( \Exception $e ) {
				$message = 'Setup failed: ' . $e->getMessage();
			}
		}

		add_settings_error(
			'sam_setup',
			$ok ? 'sam_setup_ok' : 'sam_setup_err',
			$message,
			$ok ? 'success' : 'error'
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		$next = $ok ? 4 : 3;
		wp_redirect( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=' . $next ) );
		exit;
	}

	/**
	 * System requirements check shown in wizard step 2.
	 *
	 * @return array List of checks with 'label' and 'ok' keys.
	 */
	public static function get_requirements() {
		$config_path = self::get_backend_config_path();
		$config_writable = is_writable( $config_path )
			|| ( ! file_exists( $config_path ) && is_writable( dirname( $config_path ) ) );

		return array(
			array(
				'label' => 'PHP 7.4 or higher (current: ' . PHP_VERSION . ')',
				'ok'    => version_compare( PHP_VERSION, '7.4.0', '>=' ),
			),
			array(
				'label' => 'PDO MySQL extension enabled',
				'ok'    => extension_loaded( 'pdo' ) && extension_loaded( 'pdo_mysql' ),
			),
			array(
				'label' => 'backend/config.php is writable',
				'ok'    => $config_writable,
			),
		);
	}

	/**
	 * Create the cloud database, import the schema, and write backend/config.php.
	 *
	 * @param string $host DB host.
	 * @param string $db   Database name.
	 * @param string $user DB user.
	 * @param string $pass DB password.
	 * @throws \Exception When any step fails.
	 */
	public function run_setup( $host, $db, $user, $pass ) {
		// 1. Connect to the server (no DB yet) and create the database.
		$pdo = new \PDO(
			"mysql:host={$host}",
			$user,
			$pass,
			array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION )
		);
		$pdo->exec( "CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );

		// 2. Connect to the new database and import the schema.
		$pdo2 = new \PDO(
			"mysql:host={$host};dbname={$db};charset=utf8mb4",
			$user,
			$pass,
			array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION )
		);

		$schema = file_get_contents( SAM_ANTI_SPAM_PLUGIN_DIR . 'backend/schema.sql' );
		$statements = array_filter( array_map( 'trim', explode( ';', $schema ) ) );
		foreach ( $statements as $stmt ) {
			if ( '' !== $stmt ) {
				$pdo2->exec( $stmt );
			}
		}

		// 3. Write backend/config.php with the supplied credentials.
		$this->write_config( $host, $db, $user, $pass );
	}

	protected function write_config( $host, $db, $user, $pass ) {
		$path = self::get_backend_config_path();

		$export = var_export( array(
			'db' => array(
				'host'    => $host,
				'db_name' => $db,
				'user'    => $user,
				'pass'    => $pass,
				'charset' => 'utf8mb4',
			),
		), true );

		$content = "<?php\n" .
			"// Auto-generated by Sam Anti Spam Setup. Edit credentials here if needed.\n" .
			'return ' . $export . ";\n";

		$result = file_put_contents( $path, $content );
		if ( false === $result ) {
			throw new \Exception( 'Could not write config.php to ' . $path . '. Please make the file writable or create it manually.' );
		}
	}
}
