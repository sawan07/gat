<?php
/**
 * Admin → Donations. Visible to Administrators and Donations Viewers
 * (gat_view_donations). Only Administrators can change records.
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
		add_menu_page( __( 'Donations', 'gat-core' ), __( 'Donations', 'gat-core' ), 'gat_view_donations', 'gat-donations', 'gat_core_donations_screen', 'dashicons-heart', 26 );
	}
);

/**
 * Filters from the URL.
 *
 * @return array
 */
function gat_donations_query() {
	$g = wp_unslash( $_GET ); // phpcs:ignore
	return array(
		'status'    => sanitize_key( $g['status'] ?? '' ),
		'frequency' => sanitize_key( $g['frequency'] ?? '' ),
		'source'    => sanitize_key( $g['source'] ?? '' ),
		'month'     => preg_match( '/^\d{4}-\d{2}$/', $g['month'] ?? '' ) ? $g['month'] : '',
		'gift_aid'  => ! empty( $g['gift_aid'] ) ? '1' : '',
		's'         => sanitize_text_field( $g['s'] ?? '' ),
	);
}

/**
 * WHERE for the filters.
 *
 * @param array $q Filters.
 * @return string
 */
function gat_donations_where( $q ) {
	global $wpdb;
	$w = array( '1=1' );
	if ( 'received' === $q['status'] ) {
		$w[] = "status IN ('paid','active','cancelled')";
	} elseif ( $q['status'] && isset( gat_labels()['donation'][ $q['status'] ] ) ) {
		$w[] = $wpdb->prepare( 'status = %s', $q['status'] );
	}
	if ( in_array( $q['frequency'], array( 'one_off', 'monthly' ), true ) ) {
		$w[] = $wpdb->prepare( 'frequency = %s', $q['frequency'] );
	}
	if ( in_array( $q['source'], array( 'website', 'assistant' ), true ) ) {
		$w[] = $wpdb->prepare( 'source = %s', $q['source'] );
	}
	if ( $q['month'] ) {
		$w[] = $wpdb->prepare( 'created_at >= %s AND created_at < %s', $q['month'] . '-01 00:00:00', gmdate( 'Y-m-01 00:00:00', strtotime( $q['month'] . '-01 +1 month' ) ) );
	}
	if ( $q['gift_aid'] ) {
		$w[] = 'gift_aid = 1';
	}
	if ( $q['s'] ) {
		$like = '%' . $wpdb->esc_like( $q['s'] ) . '%';
		$w[]  = $wpdb->prepare( '(ref LIKE %s OR donor_name LIKE %s OR email LIKE %s OR cause LIKE %s OR external_ref LIKE %s)', $like, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

/**
 * Donations list table.
 */
class GAT_Donations_Table extends WP_List_Table {

	/** Constructor. */
	public function __construct() {
		parent::__construct( array( 'singular' => 'donation', 'plural' => 'donations', 'ajax' => false ) );
	}

	/** Columns. */
	public function get_columns() {
		return array(
			'ref'        => __( 'Ref', 'gat-core' ),
			'created_at' => __( 'Date', 'gat-core' ),
			'donor'      => __( 'Donor', 'gat-core' ),
			'amount'     => __( 'Amount', 'gat-core' ),
			'cause'      => __( 'Cause', 'gat-core' ),
			'gift_aid'   => __( 'Gift Aid', 'gat-core' ),
			'source'     => __( 'Made via', 'gat-core' ),
			'status'     => __( 'Status', 'gat-core' ),
		);
	}

	/** Views. */
	protected function get_views() {
		global $wpdb;
		$c    = $wpdb->get_results( 'SELECT status, COUNT(*) n FROM ' . gat_table( 'donations' ) . ' GROUP BY status', OBJECT_K ); // phpcs:ignore
		$n    = fn( $k ) => (int) ( $c[ $k ]->n ?? 0 );
		$cur  = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore
		$base = admin_url( 'admin.php?page=gat-donations' );
		$link = fn( $k, $label, $count ) => sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $k ? add_query_arg( 'status', $k, $base ) : $base ), $cur === $k ? ' class="current"' : '', esc_html( $label ), $count );
		$all  = array_sum( array_map( fn( $r ) => (int) $r->n, $c ) );
		return array(
			'all'      => $link( '', __( 'All', 'gat-core' ), $all ),
			'received' => $link( 'received', __( 'Received', 'gat-core' ), $n( 'paid' ) + $n( 'active' ) + $n( 'cancelled' ) ),
			'active'   => $link( 'active', __( 'Monthly – active', 'gat-core' ), $n( 'active' ) ),
			'pending'  => $link( 'pending', __( 'Awaiting payment', 'gat-core' ), $n( 'pending' ) ),
			'expired'  => $link( 'expired', __( 'Not completed', 'gat-core' ), $n( 'expired' ) + $n( 'failed' ) ),
			'refunded' => $link( 'refunded', __( 'Refunded', 'gat-core' ), $n( 'refunded' ) ),
		);
	}

	/** Filters. */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$q = gat_donations_query();
		echo '<div class="alignleft actions">';
		echo '<select name="frequency"><option value="">' . esc_html__( 'One-off and monthly', 'gat-core' ) . '</option><option value="one_off"' . selected( $q['frequency'], 'one_off', false ) . '>' . esc_html__( 'One-off', 'gat-core' ) . '</option><option value="monthly"' . selected( $q['frequency'], 'monthly', false ) . '>' . esc_html__( 'Monthly', 'gat-core' ) . '</option></select>';
		echo '<select name="source"><option value="">' . esc_html__( 'Website and assistant', 'gat-core' ) . '</option><option value="website"' . selected( $q['source'], 'website', false ) . '>' . esc_html__( 'Donate page', 'gat-core' ) . '</option><option value="assistant"' . selected( $q['source'], 'assistant', false ) . '>' . esc_html__( 'Donation assistant', 'gat-core' ) . '</option></select>';
		echo '<input type="month" name="month" value="' . esc_attr( $q['month'] ) . '" aria-label="' . esc_attr__( 'Month', 'gat-core' ) . '">';
		echo '<label style="margin:0 8px"><input type="checkbox" name="gift_aid" value="1"' . checked( $q['gift_aid'], '1', false ) . '> ' . esc_html__( 'Gift Aid only', 'gat-core' ) . '</label>';
		if ( $q['status'] ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $q['status'] ) . '">';
		}
		submit_button( __( 'Filter', 'gat-core' ), '', 'filter_action', false );
		printf( ' <a class="button" href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( array_merge( $q, array( 'action' => 'gat_export_donations' ) ), admin_url( 'admin-post.php' ) ), 'gat_export_donations' ) ), esc_html__( 'Download CSV', 'gat-core' ) );
		if ( gat_setting( 'gift_aid' ) ) {
			printf( ' <a class="button" href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'gat_export_donations', 'giftaid_schedule' => 1, 'month' => $q['month'] ), admin_url( 'admin-post.php' ) ), 'gat_export_donations' ) ), esc_html__( 'Gift Aid claim (CSV)', 'gat-core' ) );
		}
		echo '</div>';
	}

	/** Load. */
	public function prepare_items() {
		global $wpdb;
		$per   = 25;
		$page  = $this->get_pagenum();
		$where = gat_donations_where( gat_donations_query() );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gat_table( 'donations' ) . " WHERE $where" ); // phpcs:ignore
		$this->items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . gat_table( 'donations' ) . " WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
	}

	/** Cells. */
	protected function column_default( $item, $col ) {
		$url = admin_url( 'admin.php?page=gat-donations&view=' . $item['id'] );
		switch ( $col ) {
			case 'ref':
				return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item['ref'] ) . '</strong></a>';
			case 'created_at':
				return esc_html( get_date_from_gmt( $item['created_at'], 'j M Y' ) );
			case 'donor':
				$out = '<a href="' . esc_url( $url ) . '">' . esc_html( $item['donor_name'] ?: '—' ) . '</a>';
				if ( $item['anonymous'] ) {
					$out .= ' <span class="gat-muted">(' . esc_html__( 'private', 'gat-core' ) . ')</span>';
				}
				return $out . '<br><span class="description">' . esc_html( $item['email'] ) . '</span>';
			case 'amount':
				$out = '<strong>' . esc_html( gat_money( $item['amount_pence'] ) ) . '</strong>';
				if ( 'monthly' === $item['frequency'] ) {
					$out .= ' / ' . esc_html__( 'month', 'gat-core' );
					if ( (int) $item['payments_count'] > 0 ) {
						/* translators: 1 count 2 total */
						$out .= '<br><span class="description">' . esc_html( sprintf( _n( '%1$d payment, %2$s', '%1$d payments, %2$s', (int) $item['payments_count'], 'gat-core' ), $item['payments_count'], gat_money( $item['total_paid_pence'] ) ) ) . '</span>';
					}
				}
				return $out;
			case 'cause':
				return esc_html( $item['cause'] ) . ( $item['dedication'] ? '<br><span class="description">' . esc_html( $item['dedication'] ) . '</span>' : '' );
			case 'gift_aid':
				return $item['gift_aid'] ? '✓ ' . esc_html( gat_money( (int) round( $item['amount_pence'] * 0.25 ) ) ) : '—';
			case 'source':
				return 'assistant' === $item['source'] ? esc_html__( 'Donation assistant', 'gat-core' ) : esc_html__( 'Donate page', 'gat-core' );
			case 'status':
				return gat_badge( 'donation', $item['status'] );
		}
		return '';
	}

	/** Empty. */
	public function no_items() {
		esc_html_e( 'No donations found.', 'gat-core' );
	}
}

/**
 * Summary figures (shared by the admin screen and the staff portal).
 *
 * @return array List of array( label, value ).
 */
function gat_donation_summary_data() {
	global $wpdb;
	$t      = gat_table( 'donations' );
	$month  = gmdate( 'Y-m-01 00:00:00' );
	$year   = gmdate( 'Y-01-01 00:00:00' );
	$sum    = fn( $sql, ...$a ) => (int) $wpdb->get_var( $a ? $wpdb->prepare( $sql, ...$a ) : $sql ); // phpcs:ignore
	$recv   = "status IN ('paid','active','cancelled')";
	$boxes  = array(
		array( __( 'Received this month', 'gat-core' ), gat_money( $sum( "SELECT SUM(CASE WHEN frequency='monthly' THEN amount_pence ELSE total_paid_pence END) FROM $t WHERE $recv AND last_paid_at >= %s", $month ) ) ),
		array( __( 'Received this year', 'gat-core' ), gat_money( $sum( "SELECT SUM(total_paid_pence) FROM $t WHERE $recv AND created_at >= %s", $year ) ) ),
		array( __( 'Active monthly donors', 'gat-core' ), $sum( "SELECT COUNT(*) FROM $t WHERE status = 'active'" ) . ' · ' . gat_money( $sum( "SELECT SUM(amount_pence) FROM $t WHERE status = 'active'" ) ) . '/' . __( 'month', 'gat-core' ) ),
	);
	if ( gat_setting( 'gift_aid' ) ) {
		$boxes[] = array( __( 'Gift Aid to claim this year', 'gat-core' ), gat_money( (int) round( 0.25 * $sum( "SELECT SUM(total_paid_pence) FROM $t WHERE $recv AND gift_aid = 1 AND created_at >= %s", $year ) ) ) );
	}
	return $boxes;
}

/**
 * Summary boxes.
 */
function gat_donation_summary() {
	echo '<div class="gat-stats">';
	foreach ( gat_donation_summary_data() as list( $label, $value ) ) {
		echo '<div class="gat-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}
	echo '</div>';
}

/* ---------------------------------------------------------------------------
 * Actions.
 * ------------------------------------------------------------------------- */
add_action(
	'admin_init',
	function () {
		if ( ( $_GET['page'] ?? '' ) !== 'gat-donations' || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore
			return;
		}
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['gat_donation_save'] ) ) { // phpcs:ignore
			$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore
			check_admin_referer( 'gat_donation_' . $id );
			$p   = wp_unslash( $_POST ); // phpcs:ignore
			$upd = array( 'notes' => sanitize_textarea_field( $p['notes'] ?? '' ) );
			if ( isset( gat_labels()['donation'][ $p['status'] ?? '' ] ) ) {
				$upd['status'] = $p['status'];
				$old           = gat_get( 'donations', $id );
				if ( $old && in_array( $p['status'], array( 'paid', 'active' ), true ) && ! (int) $old['total_paid_pence'] ) {
					$upd['payments_count']   = 1;
					$upd['total_paid_pence'] = $old['amount_pence'];
					$upd['last_paid_at']     = current_time( 'mysql', true );
				}
			}
			gat_update( 'donations', $id, $upd );
			wp_safe_redirect( admin_url( 'admin.php?page=gat-donations&view=' . $id . '&gat_msg=saved' ) );
			exit;
		}
		if ( isset( $_GET['gat_delete'] ) ) { // phpcs:ignore
			$id = absint( $_GET['gat_delete'] ); // phpcs:ignore
			check_admin_referer( 'gat_delete_donation_' . $id );
			gat_delete( 'donations', $id );
			wp_safe_redirect( admin_url( 'admin.php?page=gat-donations&gat_msg=deleted' ) );
			exit;
		}
	}
);

add_action(
	'admin_post_gat_export_donations',
	function () {
		if ( ! current_user_can( 'gat_view_donations' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'gat-core' ) );
		}
		check_admin_referer( 'gat_export_donations' );
		global $wpdb;
		$schedule = ! empty( $_GET['giftaid_schedule'] ); // phpcs:ignore
		$q        = gat_donations_query();
		if ( $schedule ) {
			$q['gift_aid'] = '1';
			$q['status']   = 'received';
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . gat_table( 'donations' ) . ' WHERE ' . gat_donations_where( $q ) . ' ORDER BY id', ARRAY_A ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gat-' . ( $schedule ? 'gift-aid' : 'donations' ) . '-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out  = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		$safe = fn( $v ) => is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
		if ( $schedule ) {
			// Columns as on HMRC's Gift Aid schedule spreadsheet.
			fputcsv( $out, array( 'Title', 'First name', 'Last name', 'House name or number', 'Postcode', 'Aggregated donations', 'Sponsored event', 'Donation date', 'Amount', 'Reference' ) );
			foreach ( $rows as $r ) {
				$parts = preg_split( '/\s+/', trim( $r['donor_name'] ) );
				$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
				$house = trim( strtok( (string) $r['address'], ",\n" ) );
				fputcsv( $out, array_map( $safe, array( '', implode( ' ', $parts ), $last, $house, $r['postcode'], '', 'No', get_date_from_gmt( $r['last_paid_at'] ?: $r['created_at'], 'd/m/y' ), number_format( ( (int) $r['total_paid_pence'] ?: (int) $r['amount_pence'] ) / 100, 2, '.', '' ), $r['ref'] ) ) );
			}
		} else {
			fputcsv( $out, array( 'Reference', 'Date', 'Donor name', 'Email', 'Keep name private', 'Amount (£)', 'Frequency', 'Payments so far', 'Total received (£)', 'Cause', 'Dedication', 'Gift Aid', 'Address', 'Postcode', 'Made via', 'Status', 'Assistant ref' ) );
			foreach ( $rows as $r ) {
				fputcsv(
					$out,
					array_map(
						$safe,
						array(
							$r['ref'],
							get_date_from_gmt( $r['created_at'], 'Y-m-d H:i' ),
							$r['donor_name'],
							$r['email'],
							$r['anonymous'] ? 'Yes' : 'No',
							number_format( $r['amount_pence'] / 100, 2, '.', '' ),
							'monthly' === $r['frequency'] ? 'Monthly' : 'One-off',
							$r['payments_count'],
							number_format( $r['total_paid_pence'] / 100, 2, '.', '' ),
							$r['cause'],
							$r['dedication'],
							$r['gift_aid'] ? 'Yes' : 'No',
							$r['address'],
							$r['postcode'],
							'assistant' === $r['source'] ? 'Donation assistant' : 'Donate page',
							gat_labels()['donation'][ $r['status'] ] ?? $r['status'],
							$r['external_ref'],
						)
					)
				);
			}
		}
		fclose( $out );
		exit;
	}
);

/**
 * Screen.
 */
function gat_core_donations_screen() {
	$msg = sanitize_key( $_GET['gat_msg'] ?? '' ); // phpcs:ignore
	echo '<div class="wrap gat-admin">';
	if ( 'saved' === $msg || 'deleted' === $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'saved' === $msg ? __( 'Saved.', 'gat-core' ) : __( 'Deleted.', 'gat-core' ) ) . '</p></div>';
	}
	if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore
		gat_core_donation_view( absint( $_GET['view'] ) ); // phpcs:ignore
	} else {
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Donations', 'gat-core' ) . '</h1>';
		printf( ' <a class="page-title-action" href="%s" target="_blank">%s</a>', esc_url( gat_page_url( 'donate' ) ), esc_html__( 'Open the Donate page', 'gat-core' ) );
		echo '<hr class="wp-header-end">';
		gat_donation_summary();
		$t = new GAT_Donations_Table();
		$t->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="gat-donations">';
		$t->views();
		$t->search_box( __( 'Search donations', 'gat-core' ), 'gat-donation' );
		$t->display();
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Payments are confirmed automatically by Stripe. Refunds and cancelling monthly gifts are done in the Stripe dashboard and show here automatically.', 'gat-core' ) . '</p>';
	}
	echo '</div>';
}

/**
 * One donation.
 *
 * @param int $id ID.
 */
function gat_core_donation_view( $id ) {
	$d = gat_get( 'donations', $id );
	if ( ! $d ) {
		echo '<p>' . esc_html__( 'Donation not found.', 'gat-core' ) . '</p>';
		return;
	}
	$admin = current_user_can( 'manage_options' );
	$test  = str_contains( (string) gat_setting( 'stripe_secret' ), '_test_' ) ? 'test/' : '';
	$rows  = array(
		__( 'Amount', 'gat-core' )      => gat_money( $d['amount_pence'] ) . ( 'monthly' === $d['frequency'] ? ' ' . __( 'per month', 'gat-core' ) : ' ' . __( 'one-off', 'gat-core' ) ),
		__( 'Received so far', 'gat-core' ) => gat_money( $d['total_paid_pence'] ) . ( 'monthly' === $d['frequency'] ? ' (' . (int) $d['payments_count'] . ')' : '' ),
		__( 'Cause', 'gat-core' )       => $d['cause'],
		__( 'Dedication', 'gat-core' )  => $d['dedication'],
		__( 'Donor', 'gat-core' )       => $d['donor_name'] . ( $d['anonymous'] ? ' (' . __( 'wishes to stay private', 'gat-core' ) . ')' : '' ),
		__( 'Email', 'gat-core' )       => $d['email'],
		__( 'Gift Aid', 'gat-core' )    => $d['gift_aid'] ? __( 'Yes', 'gat-core' ) . ' (+' . gat_money( (int) round( $d['amount_pence'] * 0.25 ) ) . ')' : __( 'No', 'gat-core' ),
		__( 'Address', 'gat-core' )     => trim( $d['address'] . "\n" . $d['postcode'] ),
		__( 'Message', 'gat-core' )     => $d['message'],
		__( 'Made via', 'gat-core' )    => 'assistant' === $d['source'] ? __( 'Donation assistant', 'gat-core' ) . ( $d['external_ref'] ? ' (' . $d['external_ref'] . ')' : '' ) : __( 'Donate page', 'gat-core' ),
		__( 'Date', 'gat-core' )        => get_date_from_gmt( $d['created_at'], 'j F Y, H:i' ),
		__( 'Last payment', 'gat-core' ) => $d['last_paid_at'] ? get_date_from_gmt( $d['last_paid_at'], 'j F Y, H:i' ) : '',
	);
	?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=gat-donations' ) ); ?>">← <?php esc_html_e( 'All donations', 'gat-core' ); ?></a></p>
	<h1><?php echo esc_html( gat_money( $d['amount_pence'] ) ); ?> <span class="gat-muted"><?php echo esc_html( $d['ref'] ); ?></span> <?php echo gat_badge( 'donation', $d['status'] ); // phpcs:ignore ?></h1>
	<div class="gat-cols">
		<div class="gat-box">
			<table class="widefat striped"><tbody>
				<?php foreach ( $rows as $label => $value ) : ?>
					<?php if ( '' === trim( (string) $value ) ) { continue; } ?>
					<tr><th style="width:170px"><?php echo esc_html( $label ); ?></th><td><?php echo nl2br( esc_html( $value ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<p>
				<?php if ( $d['stripe_payment_intent'] ) : ?>
					<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://dashboard.stripe.com/' . $test . 'payments/' . $d['stripe_payment_intent'] ); ?>"><?php esc_html_e( 'View payment in Stripe', 'gat-core' ); ?></a>
				<?php endif; ?>
				<?php if ( $d['stripe_subscription'] ) : ?>
					<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://dashboard.stripe.com/' . $test . 'subscriptions/' . $d['stripe_subscription'] ); ?>"><?php esc_html_e( 'Monthly gift in Stripe (cancel / change)', 'gat-core' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<div class="gat-box">
			<?php if ( $admin ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'gat_donation_' . $d['id'] ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
					<input type="hidden" name="gat_donation_save" value="1">
					<p><label for="f_status"><strong><?php esc_html_e( 'Status', 'gat-core' ); ?></strong></label><br>
					<select id="f_status" name="status">
						<?php foreach ( gat_labels()['donation'] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $d['status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
					</select></p>
					<p class="description"><?php esc_html_e( 'Updated automatically by Stripe. Change by hand only for money received another way.', 'gat-core' ); ?></p>
					<p><label for="f_notes"><strong><?php esc_html_e( 'Notes', 'gat-core' ); ?></strong></label><br>
					<textarea id="f_notes" name="notes" rows="6" class="large-text"><?php echo esc_textarea( (string) $d['notes'] ); ?></textarea></p>
					<?php submit_button( __( 'Save', 'gat-core' ), 'primary', 'submit', false ); ?>
					<a class="button-link-delete gat-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gat-donations&gat_delete=' . $d['id'] ), 'gat_delete_donation_' . $d['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this donation record permanently? (This does not refund or cancel anything in Stripe.)', 'gat-core' ) ); ?>');"><?php esc_html_e( 'Delete record', 'gat-core' ); ?></a>
				</form>
			<?php else : ?>
				<?php if ( $d['notes'] ) : ?><h2><?php esc_html_e( 'Notes', 'gat-core' ); ?></h2><p><?php echo nl2br( esc_html( $d['notes'] ) ); ?></p><?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* Shared admin styles for Students and Donations. */
add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'toplevel_page_gat-students', 'toplevel_page_gat-donations', 'settings_page_gat-settings' ), true ) ) {
			return;
		}
		echo '<style>
		.gat-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);border:1px solid color-mix(in srgb,var(--c) 35%,#fff);white-space:nowrap;margin:1px 2px 1px 0}
		.gat-muted{color:#646970;font-weight:400}
		.gat-admin h1 .gat-muted{font-size:.7em;margin-left:6px}
		.gat-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin:12px 0}
		.gat-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:6px 18px 14px;margin-bottom:16px}
		.gat-box h2{font-size:15px}
		.gat-record .form-table th{width:180px}
		.gat-decl{margin:6px 0 0}
		.gat-delete{margin-left:14px;vertical-align:middle}
		.gat-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:14px 0}
		.gat-stat{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px}
		.gat-stat span{display:block;color:#646970;font-size:13px}
		.gat-stat strong{display:block;font-size:20px;color:#0b3a75;margin-top:4px}
		.tablenav .actions select,.tablenav .actions input[type=month]{margin-right:6px}
		</style>';
	}
);
