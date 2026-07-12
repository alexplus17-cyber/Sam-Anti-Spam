<?php
namespace SamAntiSpam\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpamLogger {

	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sam_spam_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ip varchar(45) NOT NULL,
			email varchar(100) DEFAULT '' NOT NULL,
			action_type varchar(50) NOT NULL,
			blocked_reason text NOT NULL,
			log_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function log_blocked_attempt( array $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sam_spam_log';

		$wpdb->insert(
			$table_name,
			array(
				'ip'             => sanitize_text_field( $data['ip'] ),
				'email'          => sanitize_email( $data['email'] ),
				'action_type'    => sanitize_text_field( $data['action_type'] ),
				'blocked_reason' => sanitize_textarea_field( $data['blocked_reason'] ),
				'log_date'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function get_logs( $args = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sam_spam_log';

		$orderby = isset( $args['orderby'] ) ? sanitize_text_field( $args['orderby'] ) : 'log_date';
		$order   = isset( $args['order'] ) && strtolower( $args['order'] ) === 'asc' ? 'ASC' : 'DESC';

		// Allowed columns for sorting to prevent SQL injection
		$allowed_orderby = array( 'id', 'ip', 'email', 'action_type', 'log_date' );
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'log_date';
		}

		$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 10;
		$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

		$sql = $wpdb->prepare(
			"SELECT * FROM $table_name ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function get_total_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sam_spam_log';
		return (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
	}
}
