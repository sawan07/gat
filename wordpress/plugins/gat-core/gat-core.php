<?php
/**
 * Plugin Name: GAT Core
 * Description: Global Alliance Trust site features: online donations and Sunnah Academy enrolment (Stripe), Students and Donations admin, advertisers, enquiry forms, staff roles, simplified admin panel and starter content.
 * Version: 1.1.0
 * Author: Amverio
 * Author URI: https://amverio.co.uk
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: gat-core
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

define( 'GAT_CORE_VERSION', '1.1.0' );
define( 'GAT_CORE_FILE', __FILE__ );
define( 'GAT_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAT_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once GAT_CORE_DIR . 'includes/db.php';
require_once GAT_CORE_DIR . 'includes/settings.php';
require_once GAT_CORE_DIR . 'includes/roles.php';
require_once GAT_CORE_DIR . 'includes/admin.php';
require_once GAT_CORE_DIR . 'includes/advertisers.php';
require_once GAT_CORE_DIR . 'includes/enquiries.php';
require_once GAT_CORE_DIR . 'includes/contact.php';
require_once GAT_CORE_DIR . 'includes/setup.php';
require_once GAT_CORE_DIR . 'includes/forms.php';
require_once GAT_CORE_DIR . 'includes/stripe.php';
require_once GAT_CORE_DIR . 'includes/donations.php';
require_once GAT_CORE_DIR . 'includes/enrolment.php';
require_once GAT_CORE_DIR . 'includes/admin-students.php';
require_once GAT_CORE_DIR . 'includes/admin-donations.php';

/**
 * Allow photo uploads up to 32 MB. The standard WordPress server image keeps
 * PHP's 2 MB default; on Apache these lines in .htaccess raise it.
 */
function gat_core_upload_limits() {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$file = ABSPATH . '.htaccess';
	if ( ( file_exists( $file ) && is_writable( $file ) ) || ( ! file_exists( $file ) && is_writable( ABSPATH ) ) ) {
		insert_with_markers(
			$file,
			'GAT upload limits',
			array(
				'<IfModule mod_php.c>',
				'php_value upload_max_filesize 32M',
				'php_value post_max_size 34M',
				'php_value memory_limit 256M',
				'php_value max_execution_time 120',
				'</IfModule>',
			)
		);
	}
	update_option( 'gat_core_limits_version', GAT_CORE_VERSION );
}

register_activation_hook(
	__FILE__,
	function () {
		gat_core_upload_limits();
		gat_core_install_tables();
		gat_core_install_roles();
		gat_core_register_advertisers();
		gat_core_register_enquiries();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Keep roles in sync when the plugin is updated (no re-activation needed).
add_action(
	'admin_init',
	function () {
		if ( get_option( 'gat_core_roles_version' ) !== GAT_CORE_VERSION ) {
			gat_core_install_roles();
		}
		if ( get_option( 'gat_core_limits_version' ) !== GAT_CORE_VERSION ) {
			gat_core_upload_limits();
		}
	}
);
