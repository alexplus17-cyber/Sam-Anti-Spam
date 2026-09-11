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

	public function get_bulk_actions() {
		return array(
			'bulk-delete'      => 'Delete Selected',
			'blacklist_ips'    => 'Blacklist Selected IPs',
			'whitelist_emails' => 'Whitelist Selected Emails',
			'clear_all'        => 'Clear All Logs',
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'log_date':
			case 'email':
			case 'action_type':
			case 'blocked_reason':
				return esc_html( $item[ $column_name ] );
			default:
				return print_r( $item, true );
		}
	}

	protected function column_ip( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'     => 'sam-anti-spam',
					'tab'      => 'log',
					'action'   => 'delete',
					'spam_log' => array( $item['id'] ),
				),
				admin_url( 'options-general.php' )
			),
			'bulk-spam_logs'
		);

		$blacklist_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'     => 'sam-anti-spam',
					'tab'      => 'log',
					'action'   => 'blacklist_ip',
					'ip'       => $item['ip'],
				),
				admin_url( 'options-general.php' )
			),
			'bulk-spam_logs'
		);

		$actions = array(
			'delete'       => sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), 'Delete' ),
			'blacklist_ip' => sprintf( '<a href="%s">%s</a>', esc_url( $blacklist_url ), 'Blacklist IP' ),
		);

		return sprintf( '%1$s %2$s', esc_html( $item['ip'] ), $this->row_actions( $actions ) );
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'spam_log',
			$item['id']
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			$current_per_page = isset( $this->_args['per_page'] ) ? (int) $this->_args['per_page'] : 20;
			$options          = array( 10, 20, 50, 100, 200, 500 );
			echo '<div class="alignleft actions">';
			echo '<select name="per_page" id="sam_per_page_select" onchange="this.form.submit()">';
			foreach ( $options as $opt ) {
				$selected = selected( $current_per_page, $opt, false );
				echo '<option value="' . esc_attr( $opt ) . '" ' . $selected . '>' . esc_html( $opt ) . ' per page</option>';
			}
			echo '</select>';
			echo '<input type="submit" class="button" value="Filter" />';
			echo '</div>';
		}
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$logger = new \SamAntiSpam\Core\SpamLogger();

		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen ? $screen->get_option( 'per_page', 'option' ) : 'sam_spam_logs_per_page';
		$saved    = get_user_meta( $user, $option, true );

		if ( isset( $_REQUEST['per_page'] ) && intval( $_REQUEST['per_page'] ) > 0 ) {
			$per_page = intval( $_REQUEST['per_page'] );
		} elseif ( ! empty( $saved ) && intval( $saved ) > 0 ) {
			$per_page = intval( $saved );
		} else {
			$per_page = 20;
		}

		$this->_args['per_page'] = $per_page;

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
