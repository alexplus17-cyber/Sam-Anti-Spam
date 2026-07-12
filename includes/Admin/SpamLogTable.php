<?php
namespace SamAntiSpam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SpamLogTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'spam_log',
			'plural'   => 'spam_logs',
			'ajax'     => false
		) );
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'date_time'      => 'Date & Time',
			'ip_address'     => 'IP Address',
			'email'          => 'Email',
			'action_type'    => 'Action Type',
			'blocked_reason' => 'Reason'
		);
	}

	public function get_sortable_columns() {
		return array(
			'date_time'  => array( 'date_time', false ),
			'ip_address' => array( 'ip_address', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date_time':
			case 'ip_address':
			case 'email':
			case 'action_type':
			case 'blocked_reason':
				return esc_html( $item[ $column_name ] );
			default:
				return print_r( $item, true );
		}
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['id']
		);
	}

	private function get_spam_data() {
		// Dummy data for phase 2 UI testing
		return array(
			array(
				'id'             => 1,
				'date_time'      => current_time( 'mysql' ),
				'ip_address'     => '192.168.1.100',
				'email'          => 'spammer1@example.com',
				'action_type'    => 'Comment',
				'blocked_reason' => 'Honeypot Triggered'
			),
			array(
				'id'             => 2,
				'date_time'      => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 3600 ),
				'ip_address'     => '10.0.0.5',
				'email'          => 'fakeuser@botnet.org',
				'action_type'    => 'Registration',
				'blocked_reason' => 'Missing Token'
			),
			array(
				'id'             => 3,
				'date_time'      => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7200 ),
				'ip_address'     => '172.16.0.2',
				'email'          => 'buyer@scam.net',
				'action_type'    => 'WooCommerce Checkout',
				'blocked_reason' => 'Rate Limited'
			),
			array(
				'id'             => 4,
				'date_time'      => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 86400 ),
				'ip_address'     => '8.8.8.8',
				'email'          => 'contact@seo-spam.com',
				'action_type'    => 'Contact Form 7',
				'blocked_reason' => 'Honeypot Triggered'
			),
			array(
				'id'             => 5,
				'date_time'      => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 172800 ),
				'ip_address'     => '192.168.1.101',
				'email'          => 'spammer2@example.com',
				'action_type'    => 'Comment',
				'blocked_reason' => 'Missing Token'
			),
		);
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$data = $this->get_spam_data();

		// Sorting logic
		usort( $data, function( $a, $b ) {
			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'date_time';
			$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';

			$result = strcmp( $a[$orderby], $b[$orderby] );
			return ( $order === 'asc' ) ? $result : -$result;
		});

		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = count( $data );

		$this->items = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}
