<?php
/**
 * Database tables for student enrolments and donations.
 *
 * Personal data lives in its own tables (not posts), so it never shows up in
 * site search, feeds, sitemaps or the REST API, and only the screens in
 * admin-students.php / admin-donations.php can read it.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

define( 'GAT_CORE_DB_VERSION', '1.0.0' );

/**
 * Table names.
 *
 * @param string $which students|donations.
 * @return string
 */
function gat_table( $which ) {
	global $wpdb;
	return $wpdb->prefix . 'gat_' . $which;
}

/**
 * Create / upgrade tables.
 */
function gat_core_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . gat_table( 'students' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ref varchar(20) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			payment_status varchar(20) NOT NULL DEFAULT 'pending',
			fee_pence int(11) NOT NULL DEFAULT 0,
			student_name varchar(191) NOT NULL DEFAULT '',
			dob date NULL,
			address text NULL,
			postcode varchar(20) NOT NULL DEFAULT '',
			student_info text NULL,
			parent_name varchar(191) NOT NULL DEFAULT '',
			relationship varchar(60) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			emergency_name varchar(191) NOT NULL DEFAULT '',
			emergency_phone varchar(40) NOT NULL DEFAULT '',
			class_name varchar(191) NOT NULL DEFAULT '',
			start_date date NULL,
			additional_info text NULL,
			declarations text NULL,
			notes text NULL,
			stripe_session varchar(255) NOT NULL DEFAULT '',
			stripe_payment_intent varchar(255) NOT NULL DEFAULT '',
			paid_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY status (status),
			KEY payment_status (payment_status),
			KEY stripe_session (stripe_session(64)),
			KEY stripe_payment_intent (stripe_payment_intent(64))
		) $charset;"
	);

	dbDelta(
		'CREATE TABLE ' . gat_table( 'donations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ref varchar(20) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'website',
			external_ref varchar(60) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			amount_pence int(11) NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT 'GBP',
			frequency varchar(10) NOT NULL DEFAULT 'one_off',
			cause varchar(191) NOT NULL DEFAULT '',
			donor_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			anonymous tinyint(1) NOT NULL DEFAULT 0,
			gift_aid tinyint(1) NOT NULL DEFAULT 0,
			address text NULL,
			postcode varchar(20) NOT NULL DEFAULT '',
			dedication varchar(255) NOT NULL DEFAULT '',
			message text NULL,
			notes text NULL,
			stripe_session varchar(255) NOT NULL DEFAULT '',
			stripe_payment_intent varchar(255) NOT NULL DEFAULT '',
			stripe_subscription varchar(255) NOT NULL DEFAULT '',
			payment_link_id varchar(255) NOT NULL DEFAULT '',
			payments_count int(11) NOT NULL DEFAULT 0,
			total_paid_pence int(11) NOT NULL DEFAULT 0,
			last_paid_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY status (status),
			KEY stripe_session (stripe_session(64)),
			KEY stripe_payment_intent (stripe_payment_intent(64)),
			KEY stripe_subscription (stripe_subscription(64)),
			KEY payment_link_id (payment_link_id(64))
		) $charset;"
	);

	update_option( 'gat_core_db_version', GAT_CORE_DB_VERSION );
}

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'gat_core_db_version' ) !== GAT_CORE_DB_VERSION ) {
			gat_core_install_tables();
		}
	}
);

/**
 * Insert a row and give it a readable reference (SA00001 / DN00001).
 *
 * @param string $which  students|donations.
 * @param array  $data   Columns.
 * @return array|null The saved row.
 */
function gat_insert( $which, $data ) {
	global $wpdb;
	$now                = current_time( 'mysql', true );
	$data['created_at'] = $now;
	$data['updated_at'] = $now;
	$data['ref']        = 'tmp-' . wp_generate_password( 10, false );
	if ( ! $wpdb->insert( gat_table( $which ), $data ) ) {
		return null;
	}
	$id  = (int) $wpdb->insert_id;
	$ref = ( 'students' === $which ? 'SA' : 'DN' ) . str_pad( (string) $id, 5, '0', STR_PAD_LEFT );
	$wpdb->update( gat_table( $which ), array( 'ref' => $ref ), array( 'id' => $id ) );
	return gat_get( $which, $id );
}

/**
 * Update a row.
 *
 * @param string $which students|donations.
 * @param int    $id    ID.
 * @param array  $data  Columns.
 * @return bool
 */
function gat_update( $which, $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql', true );
	return false !== $wpdb->update( gat_table( $which ), $data, array( 'id' => (int) $id ) );
}

/**
 * Get a row by ID.
 *
 * @param string $which students|donations.
 * @param int    $id    ID.
 * @return array|null
 */
function gat_get( $which, $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gat_table( $which ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

/**
 * Get a row by a column value.
 *
 * @param string $which  students|donations.
 * @param string $column Column (whitelisted).
 * @param string $value  Value.
 * @return array|null
 */
function gat_get_by( $which, $column, $value ) {
	global $wpdb;
	$allowed = array( 'ref', 'stripe_session', 'stripe_payment_intent', 'stripe_subscription', 'payment_link_id' );
	if ( ! in_array( $column, $allowed, true ) || '' === (string) $value ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gat_table( $which ) . " WHERE {$column} = %s LIMIT 1", $value ), ARRAY_A ); // phpcs:ignore
}

/**
 * Delete a row.
 *
 * @param string $which students|donations.
 * @param int    $id    ID.
 */
function gat_delete( $which, $id ) {
	global $wpdb;
	$wpdb->delete( gat_table( $which ), array( 'id' => (int) $id ) );
}

/**
 * Money: pence → "£12.50".
 *
 * @param int $pence Pence.
 * @return string
 */
function gat_money( $pence ) {
	return '£' . number_format( ( (int) $pence ) / 100, 2 );
}

/**
 * Status labels.
 *
 * @return array
 */
function gat_labels() {
	return array(
		'application' => array(
			'pending'  => __( 'Pending', 'gat-core' ),
			'approved' => __( 'Approved', 'gat-core' ),
			'declined' => __( 'Declined', 'gat-core' ),
		),
		'payment'     => array(
			'pending'      => __( 'Pending', 'gat-core' ),
			'paid'         => __( 'Paid', 'gat-core' ),
			'refunded'     => __( 'Refunded', 'gat-core' ),
			'not_required' => __( 'No fee', 'gat-core' ),
		),
		'donation'    => array(
			'pending'   => __( 'Awaiting payment', 'gat-core' ),
			'paid'      => __( 'Paid', 'gat-core' ),
			'active'    => __( 'Monthly – active', 'gat-core' ),
			'failed'    => __( 'Payment failed', 'gat-core' ),
			'expired'   => __( 'Not completed', 'gat-core' ),
			'refunded'  => __( 'Refunded', 'gat-core' ),
			'cancelled' => __( 'Monthly – cancelled', 'gat-core' ),
		),
	);
}

/**
 * Coloured status badge (admin).
 *
 * @param string $group  application|payment|donation.
 * @param string $status Status key.
 * @return string HTML.
 */
function gat_badge( $group, $status ) {
	$labels = gat_labels()[ $group ];
	$colors = array(
		'pending'      => '#9a6700',
		'approved'     => '#1a7f37',
		'declined'     => '#b32d2e',
		'paid'         => '#1a7f37',
		'active'       => '#1a7f37',
		'refunded'     => '#6f42c1',
		'not_required' => '#50575e',
		'failed'       => '#b32d2e',
		'expired'      => '#50575e',
		'cancelled'    => '#50575e',
	);
	$c = $colors[ $status ] ?? '#50575e';
	return sprintf(
		'<span class="gat-badge" style="--c:%s">%s</span>',
		esc_attr( $c ),
		esc_html( $labels[ $status ] ?? $status )
	);
}
