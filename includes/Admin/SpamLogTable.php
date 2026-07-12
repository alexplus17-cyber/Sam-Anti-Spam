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
			'log_date'       => 'Date & Time',
			'ip'             => 'IP Address',
			'email'          => 'Email',
			'action_type'    => 'Action Type',
			'blocked_reason' => 'Reason'
		);
	}

	public function get_sortable_columns() {
		return array(
			'log_date'  => array( 'log_date', false ),
			'ip'        => array( 'ip', false ),
			'email'     => array( 'email', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'log_date':
			case 'ip':
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

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$logger = new \SamAntiSpam\Core\SpamLogger();

		$per_page     = 10;
		$current_page = $this->get_pagenum();

		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'orderby' => ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'log_date',
			'order'   => ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc',
		);

		$this->items = $logger->get_logs( $args );
		$total_items = $logger->get_total_logs();

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}
