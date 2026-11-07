<?php
/**
 * Admin → Students (Sunnah Academy / Maktab applications).
 * Visible to Administrators and Academy Admins only (gat_manage_students).
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action(
	'admin_menu',
	function () {
		global $wpdb;
		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . gat_table( 'students' ) . " WHERE status = 'pending'" ); // phpcs:ignore
		$bubble  = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';
		add_menu_page( __( 'Students', 'gat-core' ), __( 'Students', 'gat-core' ) . $bubble, 'gat_manage_students', 'gat-students', 'gat_core_students_screen', 'dashicons-welcome-learn-more', 25 );
	}
);

/**
 * Build WHERE from the current filters.
 *
 * @param array $q Query vars.
 * @return string SQL (already prepared).
 */
function gat_students_where( $q ) {
	global $wpdb;
	$w = array( '1=1' );
	if ( ! empty( $q['status'] ) && isset( gat_labels()['application'][ $q['status'] ] ) ) {
		$w[] = $wpdb->prepare( 'status = %s', $q['status'] );
	}
	if ( ! empty( $q['payment'] ) && isset( gat_labels()['payment'][ $q['payment'] ] ) ) {
		$w[] = $wpdb->prepare( 'payment_status = %s', $q['payment'] );
	}
	if ( ! empty( $q['class'] ) ) {
		$w[] = $wpdb->prepare( 'class_name = %s', $q['class'] );
	}
	if ( ! empty( $q['s'] ) ) {
		$like = '%' . $wpdb->esc_like( $q['s'] ) . '%';
		$w[]  = $wpdb->prepare( '(ref LIKE %s OR student_name LIKE %s OR parent_name LIKE %s OR email LIKE %s OR phone LIKE %s OR postcode LIKE %s)', $like, $like, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

/**
 * Current filters from the URL.
 *
 * @return array
 */
function gat_students_query() {
	$g = wp_unslash( $_GET ); // phpcs:ignore
	return array(
		'status'  => sanitize_key( $g['status'] ?? '' ),
		'payment' => sanitize_key( $g['payment'] ?? '' ),
		'class'   => sanitize_text_field( $g['class'] ?? '' ),
		's'       => sanitize_text_field( $g['s'] ?? '' ),
	);
}

/**
 * List table.
 */
class GAT_Students_Table extends WP_List_Table {

	/** Constructor. */
	public function __construct() {
		parent::__construct( array( 'singular' => 'student', 'plural' => 'students', 'ajax' => false ) );
	}

	/** Columns (as in the content document). */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox">',
			'ref'          => __( 'Student ID', 'gat-core' ),
			'student_name' => __( 'Student Name', 'gat-core' ),
			'parent_name'  => __( 'Parent / Guardian', 'gat-core' ),
			'phone'        => __( 'Contact Number', 'gat-core' ),
			'class_name'   => __( 'Class', 'gat-core' ),
			'created_at'   => __( 'Enrolment Date', 'gat-core' ),
			'payment'      => __( 'Payment Status', 'gat-core' ),
			'status'       => __( 'Application Status', 'gat-core' ),
		);
	}

	/** Sortable. */
	protected function get_sortable_columns() {
		return array( 'ref' => array( 'id', false ), 'student_name' => array( 'student_name', false ), 'created_at' => array( 'id', true ) );
	}

	/** Bulk actions. */
	protected function get_bulk_actions() {
		return array(
			'approve' => __( 'Mark as Approved', 'gat-core' ),
			'decline' => __( 'Mark as Declined', 'gat-core' ),
			'pending' => __( 'Mark as Pending', 'gat-core' ),
		);
	}

	/** Status links. */
	protected function get_views() {
		global $wpdb;
		$counts = $wpdb->get_results( 'SELECT status, COUNT(*) n FROM ' . gat_table( 'students' ) . ' GROUP BY status', OBJECT_K ); // phpcs:ignore
		$cur    = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore
		$base   = admin_url( 'admin.php?page=gat-students' );
		$total  = array_sum( array_map( fn( $c ) => (int) $c->n, $counts ) );
		$views  = array( 'all' => sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $base ), $cur ? '' : ' class="current"', esc_html__( 'All', 'gat-core' ), $total ) );
		foreach ( gat_labels()['application'] as $k => $label ) {
			$views[ $k ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $k, $base ) ), $cur === $k ? ' class="current"' : '', esc_html( $label ), (int) ( $counts[ $k ]->n ?? 0 ) );
		}
		return $views;
	}

	/** Filter dropdowns. */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		global $wpdb;
		$q       = gat_students_query();
		$classes = $wpdb->get_col( 'SELECT DISTINCT class_name FROM ' . gat_table( 'students' ) . " WHERE class_name <> '' ORDER BY class_name" ); // phpcs:ignore
		echo '<div class="alignleft actions">';
		echo '<select name="payment"><option value="">' . esc_html__( 'All payments', 'gat-core' ) . '</option>';
		foreach ( gat_labels()['payment'] as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $q['payment'], $k, false ), esc_html( $l ) );
		}
		echo '</select>';
		if ( $classes ) {
			echo '<select name="class"><option value="">' . esc_html__( 'All classes', 'gat-core' ) . '</option>';
			foreach ( $classes as $c ) {
				printf( '<option%s>%s</option>', selected( $q['class'], $c, false ), esc_html( $c ) );
			}
			echo '</select>';
		}
		submit_button( __( 'Filter', 'gat-core' ), '', 'filter_action', false );
		printf(
			' <a class="button" href="%s">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( array_merge( $q, array( 'action' => 'gat_export_students' ) ), admin_url( 'admin-post.php' ) ), 'gat_export_students' ) ),
			esc_html__( 'Download CSV', 'gat-core' )
		);
		echo '</div>';
	}

	/** Load rows. */
	public function prepare_items() {
		global $wpdb;
		$per   = 25;
		$page  = $this->get_pagenum();
		$where = gat_students_where( gat_students_query() );
		$order = ( isset( $_GET['orderby'] ) && 'student_name' === $_GET['orderby'] ) ? 'student_name' : 'id'; // phpcs:ignore
		$dir   = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC'; // phpcs:ignore
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gat_table( 'students' ) . " WHERE $where" ); // phpcs:ignore
		$this->items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . gat_table( 'students' ) . " WHERE $where ORDER BY $order $dir LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
	}

	/** Checkbox. */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $item['id'] . '">';
	}

	/** Columns. */
	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'ref':
				return '<a href="' . esc_url( admin_url( 'admin.php?page=gat-students&view=' . $item['id'] ) ) . '"><strong>' . esc_html( $item['ref'] ) . '</strong></a>';
			case 'student_name':
				$age = $item['dob'] ? ( new DateTime( $item['dob'] ) )->diff( new DateTime( 'today' ) )->y : '';
				$out = '<a href="' . esc_url( admin_url( 'admin.php?page=gat-students&view=' . $item['id'] ) ) . '">' . esc_html( $item['student_name'] ) . '</a>';
				if ( '' !== $age ) {
					/* translators: %d age */
					$out .= '<br><span class="description">' . esc_html( sprintf( __( 'Age %d', 'gat-core' ), $age ) ) . '</span>';
				}
				return $out . $this->row_actions( array( 'view' => '<a href="' . esc_url( admin_url( 'admin.php?page=gat-students&view=' . $item['id'] ) ) . '">' . esc_html__( 'View / edit', 'gat-core' ) . '</a>' ) );
			case 'parent_name':
				return esc_html( $item['parent_name'] ) . '<br><span class="description">' . esc_html( $item['relationship'] ) . '</span>';
			case 'phone':
				return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $item['phone'] ) ) . '">' . esc_html( $item['phone'] ) . '</a><br><a href="mailto:' . esc_attr( $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>';
			case 'class_name':
				return esc_html( $item['class_name'] );
			case 'created_at':
				return esc_html( get_date_from_gmt( $item['created_at'], 'j M Y' ) );
			case 'payment':
				return gat_badge( 'payment', $item['payment_status'] ) . ( (int) $item['fee_pence'] > 0 ? '<br><span class="description">' . esc_html( gat_money( $item['fee_pence'] ) ) . '</span>' : '' );
			case 'status':
				return gat_badge( 'application', $item['status'] );
		}
		return '';
	}

	/** Empty. */
	public function no_items() {
		esc_html_e( 'No applications yet. They appear here as soon as a parent submits the enrolment form.', 'gat-core' );
	}
}

/* ---------------------------------------------------------------------------
 * Actions: bulk status, save, delete, export.
 * ------------------------------------------------------------------------- */
add_action(
	'admin_init',
	function () {
		if ( ( $_GET['page'] ?? '' ) !== 'gat-students' || ! current_user_can( 'gat_manage_students' ) ) { // phpcs:ignore
			return;
		}
		// Bulk.
		$action = sanitize_key( $_REQUEST['action'] ?? '' ); // phpcs:ignore
		if ( '-1' === $action || '' === $action ) {
			$action = sanitize_key( $_REQUEST['action2'] ?? '' ); // phpcs:ignore
		}
		$map = array( 'approve' => 'approved', 'decline' => 'declined', 'pending' => 'pending' );
		if ( isset( $map[ $action ] ) && ! empty( $_REQUEST['ids'] ) ) { // phpcs:ignore
			check_admin_referer( 'bulk-students' );
			foreach ( array_map( 'absint', (array) $_REQUEST['ids'] ) as $id ) { // phpcs:ignore
				gat_update( 'students', $id, array( 'status' => $map[ $action ] ) );
			}
			wp_safe_redirect( add_query_arg( 'gat_msg', 'bulk', remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ) ) ) );
			exit;
		}
		// Save one record.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gat_student_save'] ) ) { // phpcs:ignore
			$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore
			check_admin_referer( 'gat_student_' . $id );
			$p   = wp_unslash( $_POST ); // phpcs:ignore
			$upd = array();
			foreach ( array( 'student_name', 'postcode', 'parent_name', 'relationship', 'phone', 'email', 'emergency_name', 'emergency_phone', 'class_name' ) as $f ) {
				$upd[ $f ] = sanitize_text_field( $p[ $f ] ?? '' );
			}
			$upd['email'] = sanitize_email( $upd['email'] );
			foreach ( array( 'address', 'student_info', 'additional_info', 'notes' ) as $f ) {
				$upd[ $f ] = sanitize_textarea_field( $p[ $f ] ?? '' );
			}
			foreach ( array( 'dob', 'start_date' ) as $f ) {
				$d         = sanitize_text_field( $p[ $f ] ?? '' );
				$upd[ $f ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
			}
			if ( isset( gat_labels()['application'][ $p['status'] ?? '' ] ) ) {
				$upd['status'] = $p['status'];
			}
			if ( isset( gat_labels()['payment'][ $p['payment_status'] ?? '' ] ) ) {
				$old = gat_get( 'students', $id );
				$upd['payment_status'] = $p['payment_status'];
				if ( 'paid' === $p['payment_status'] && $old && ! $old['paid_at'] ) {
					$upd['paid_at'] = current_time( 'mysql', true );
				}
			}
			gat_update( 'students', $id, $upd );
			wp_safe_redirect( admin_url( 'admin.php?page=gat-students&view=' . $id . '&gat_msg=saved' ) );
			exit;
		}
		// Delete (administrators only).
		if ( isset( $_GET['gat_delete'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore
			$id = absint( $_GET['gat_delete'] ); // phpcs:ignore
			check_admin_referer( 'gat_delete_student_' . $id );
			gat_delete( 'students', $id );
			wp_safe_redirect( admin_url( 'admin.php?page=gat-students&gat_msg=deleted' ) );
			exit;
		}
	}
);

add_action(
	'admin_post_gat_export_students',
	function () {
		if ( ! current_user_can( 'gat_manage_students' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'gat-core' ) );
		}
		check_admin_referer( 'gat_export_students' );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . gat_table( 'students' ) . ' WHERE ' . gat_students_where( gat_students_query() ) . ' ORDER BY id', ARRAY_A ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sunnah-academy-students-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // Excel-friendly UTF-8.
		$cols = array( 'ref' => 'Student ID', 'student_name' => 'Student name', 'dob' => 'Date of birth', 'address' => 'Address', 'postcode' => 'Postcode', 'student_info' => 'Other information', 'parent_name' => 'Parent / guardian', 'relationship' => 'Relationship', 'phone' => 'Telephone', 'email' => 'Email', 'emergency_name' => 'Emergency contact', 'emergency_phone' => 'Emergency telephone', 'class_name' => 'Programme / class', 'start_date' => 'Preferred start', 'additional_info' => 'Additional information', 'fee_pence' => 'Fee (£)', 'payment_status' => 'Payment status', 'status' => 'Application status', 'created_at' => 'Enrolment date', 'notes' => 'Staff notes' );
		fputcsv( $out, array_values( $cols ) );
		foreach ( $rows as $r ) {
			$line = array();
			foreach ( array_keys( $cols ) as $c ) {
				$val = $r[ $c ];
				if ( 'fee_pence' === $c ) {
					$val = number_format( $val / 100, 2 );
				} elseif ( 'created_at' === $c ) {
					$val = get_date_from_gmt( $val, 'Y-m-d H:i' );
				} elseif ( 'payment_status' === $c ) {
					$val = gat_labels()['payment'][ $val ] ?? $val;
				} elseif ( 'status' === $c ) {
					$val = gat_labels()['application'][ $val ] ?? $val;
				}
				// Stop spreadsheet formula injection.
				if ( is_string( $val ) && preg_match( '/^[=+\-@]/', $val ) ) {
					$val = "'" . $val;
				}
				$line[] = $val;
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
		exit;
	}
);

/**
 * Screen router.
 */
function gat_core_students_screen() {
	$msgs = array(
		'saved'   => __( 'Saved.', 'gat-core' ),
		'bulk'    => __( 'Updated.', 'gat-core' ),
		'deleted' => __( 'Application deleted.', 'gat-core' ),
	);
	$msg = sanitize_key( $_GET['gat_msg'] ?? '' ); // phpcs:ignore
	echo '<div class="wrap gat-admin">';
	if ( isset( $msgs[ $msg ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $msg ] ) . '</p></div>';
	}
	if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore
		gat_core_student_view( absint( $_GET['view'] ) ); // phpcs:ignore
	} else {
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Sunnah Academy / Maktab students', 'gat-core' ) . '</h1>';
		printf( ' <a class="page-title-action" href="%s" target="_blank">%s</a>', esc_url( gat_page_url( 'sunnah-academy/enrol' ) ), esc_html__( 'Open the enrolment form', 'gat-core' ) );
		echo '<hr class="wp-header-end">';
		$t = new GAT_Students_Table();
		$t->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="gat-students">';
		$t->views();
		$t->search_box( __( 'Search students', 'gat-core' ), 'gat-student' );
		$t->display();
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Search by student or parent name, reference, email, phone or postcode. This list is private: only Administrators and Academy Admins can see it.', 'gat-core' ) . '</p>';
	}
	echo '</div>';
}

/**
 * View / edit one application.
 *
 * @param int $id ID.
 */
function gat_core_student_view( $id ) {
	$s = gat_get( 'students', $id );
	if ( ! $s ) {
		echo '<p>' . esc_html__( 'Application not found.', 'gat-core' ) . '</p>';
		return;
	}
	$decl = json_decode( (string) $s['declarations'], true );
	$back = admin_url( 'admin.php?page=gat-students' );
	$f    = function ( $name, $label, $type = 'text' ) use ( $s ) {
		echo '<tr><th><label for="f_' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea class="large-text" rows="3" id="f_' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $s[ $name ] ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="f_' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $s[ $name ] ) . '">';
		}
		echo '</td></tr>';
	};
	$test = str_contains( (string) gat_setting( 'stripe_secret' ), '_test_' ) ? 'test/' : '';
	?>
	<p><a href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'All students', 'gat-core' ); ?></a></p>
	<h1><?php echo esc_html( $s['student_name'] ); ?> <span class="gat-muted"><?php echo esc_html( $s['ref'] ); ?></span></h1>
	<p>
		<?php echo gat_badge( 'application', $s['status'] ); // phpcs:ignore ?>
		<?php echo gat_badge( 'payment', $s['payment_status'] ); // phpcs:ignore ?>
		<span class="gat-muted">
		<?php
		/* translators: %s date */
		echo esc_html( sprintf( __( 'Applied %s', 'gat-core' ), get_date_from_gmt( $s['created_at'], 'j F Y, H:i' ) ) );
		?>
		</span>
	</p>

	<form method="post" class="gat-record">
		<?php wp_nonce_field( 'gat_student_' . $s['id'] ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
		<input type="hidden" name="gat_student_save" value="1">

		<div class="gat-cols">
			<div class="gat-box">
				<h2><?php esc_html_e( 'Status', 'gat-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="f_status"><?php esc_html_e( 'Application', 'gat-core' ); ?></label></th><td><select id="f_status" name="status">
						<?php foreach ( gat_labels()['application'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
					</select></td></tr>
					<tr><th><label for="f_pay"><?php esc_html_e( 'Payment', 'gat-core' ); ?></label></th><td><select id="f_pay" name="payment_status">
						<?php foreach ( gat_labels()['payment'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['payment_status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
					</select>
					<p class="description">
						<?php echo esc_html( gat_money( $s['fee_pence'] ) ); ?>
						<?php if ( $s['paid_at'] ) : ?>
							· <?php /* translators: %s date */ echo esc_html( sprintf( __( 'paid %s', 'gat-core' ), get_date_from_gmt( $s['paid_at'], 'j M Y' ) ) ); ?>
						<?php endif; ?>
						<?php if ( $s['stripe_payment_intent'] ) : ?>
							· <a href="<?php echo esc_url( 'https://dashboard.stripe.com/' . $test . 'payments/' . $s['stripe_payment_intent'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View in Stripe', 'gat-core' ); ?></a>
						<?php endif; ?>
					</p>
					<p class="description"><?php esc_html_e( 'Updated automatically by Stripe. Change it by hand only for payments taken another way (e.g. cash). Refunds are made in Stripe and show here automatically.', 'gat-core' ); ?></p></td></tr>
					<tr><th><label for="f_notes"><?php esc_html_e( 'Staff notes', 'gat-core' ); ?></label></th><td><textarea class="large-text" rows="4" id="f_notes" name="notes"><?php echo esc_textarea( (string) $s['notes'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Private: never shown to the parent.', 'gat-core' ); ?></p></td></tr>
				</table>
			</div>

			<div class="gat-box">
				<h2><?php esc_html_e( 'Contact', 'gat-core' ); ?></h2>
				<p><strong><?php echo esc_html( $s['parent_name'] ); ?></strong> (<?php echo esc_html( $s['relationship'] ); ?>)<br>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $s['phone'] ) ); ?>"><?php echo esc_html( $s['phone'] ); ?></a><br>
				<a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a></p>
				<?php if ( $s['emergency_name'] || $s['emergency_phone'] ) : ?>
					<p><?php esc_html_e( 'Emergency:', 'gat-core' ); ?> <?php echo esc_html( trim( $s['emergency_name'] . ' ' . $s['emergency_phone'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( $decl ) : ?>
					<h3><?php esc_html_e( 'Declarations', 'gat-core' ); ?></h3>
					<p class="description">
						<?php
						/* translators: %s date */
						echo esc_html( sprintf( __( 'All accepted on %s:', 'gat-core' ), $decl['accepted_at'] ?? '' ) );
						?>
					</p>
					<ul class="gat-decl"><?php foreach ( (array) ( $decl['statements'] ?? array() ) as $t ) : ?><li>✓ <?php echo esc_html( $t ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
		</div>

		<div class="gat-box">
			<h2><?php esc_html_e( 'A. Student details', 'gat-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$f( 'student_name', __( 'Student full name', 'gat-core' ) );
				$f( 'dob', __( 'Date of birth', 'gat-core' ), 'date' );
				$f( 'address', __( 'Address', 'gat-core' ), 'textarea' );
				$f( 'postcode', __( 'Postcode', 'gat-core' ) );
				$f( 'student_info', __( 'Other information', 'gat-core' ), 'textarea' );
				?>
			</table>
			<h2><?php esc_html_e( 'B. Parent / guardian', 'gat-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$f( 'parent_name', __( 'Full name', 'gat-core' ) );
				$f( 'relationship', __( 'Relationship', 'gat-core' ) );
				$f( 'phone', __( 'Telephone', 'gat-core' ), 'tel' );
				$f( 'email', __( 'Email', 'gat-core' ), 'email' );
				$f( 'emergency_name', __( 'Emergency contact', 'gat-core' ) );
				$f( 'emergency_phone', __( 'Emergency telephone', 'gat-core' ), 'tel' );
				?>
			</table>
			<h2><?php esc_html_e( 'C. Enrolment details', 'gat-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$f( 'class_name', __( 'Programme or class', 'gat-core' ) );
				$f( 'start_date', __( 'Preferred start date', 'gat-core' ), 'date' );
				$f( 'additional_info', __( 'Additional information', 'gat-core' ), 'textarea' );
				?>
			</table>
		</div>

		<p class="submit">
			<?php submit_button( __( 'Save changes', 'gat-core' ), 'primary', 'submit', false ); ?>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="button-link-delete gat-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gat-students&gat_delete=' . $s['id'] ), 'gat_delete_student_' . $s['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this application permanently? This cannot be undone.', 'gat-core' ) ); ?>');"><?php esc_html_e( 'Delete application', 'gat-core' ); ?></a>
			<?php endif; ?>
		</p>
	</form>
	<?php
}
