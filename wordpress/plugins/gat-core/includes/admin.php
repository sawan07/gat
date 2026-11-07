<?php
/**
 * A simpler admin panel for GAT staff.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Comments are switched off everywhere (avoids spam on a charity site).
 * ------------------------------------------------------------------------- */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 10 );
add_action(
	'init',
	function () {
		foreach ( get_post_types() as $type ) {
			if ( post_type_supports( $type, 'comments' ) ) {
				remove_post_type_support( $type, 'comments' );
				remove_post_type_support( $type, 'trackbacks' );
			}
		}
	},
	100
);
add_action(
	'admin_menu',
	function () {
		remove_menu_page( 'edit-comments.php' );
	}
);
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'comments' );
	}
);

/* ---------------------------------------------------------------------------
 * "Posts" are called "News & Events" everywhere in the admin.
 * ------------------------------------------------------------------------- */
add_filter(
	'post_type_labels_post',
	function ( $labels ) {
		$labels->name               = __( 'News & Events', 'gat-core' );
		$labels->singular_name      = __( 'News item', 'gat-core' );
		$labels->menu_name          = __( 'News & Events', 'gat-core' );
		$labels->name_admin_bar     = __( 'News item', 'gat-core' );
		$labels->add_new            = __( 'Add news item', 'gat-core' );
		$labels->add_new_item       = __( 'Add news item', 'gat-core' );
		$labels->edit_item          = __( 'Edit news item', 'gat-core' );
		$labels->new_item           = __( 'New news item', 'gat-core' );
		$labels->view_item          = __( 'View news item', 'gat-core' );
		$labels->search_items       = __( 'Search news', 'gat-core' );
		$labels->not_found          = __( 'No news found', 'gat-core' );
		$labels->all_items          = __( 'All news & events', 'gat-core' );
		return $labels;
	}
);
add_action(
	'admin_menu',
	function () {
		global $menu;
		foreach ( (array) $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'edit.php' === $item[2] ) {
				$menu[ $i ][6] = 'dashicons-megaphone'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	},
	999
);

/* ---------------------------------------------------------------------------
 * Tidy menus for staff roles.
 * ------------------------------------------------------------------------- */
add_action(
	'admin_menu',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		remove_menu_page( 'tools.php' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			remove_menu_page( 'profile.php' );
			add_menu_page( __( 'My profile', 'gat-core' ), __( 'My profile', 'gat-core' ), 'read', 'profile.php', '', 'dashicons-admin-users', 90 );
		}
	},
	998
);

/* ---------------------------------------------------------------------------
 * Dashboard: one clear "what can I do here" box per role.
 * ------------------------------------------------------------------------- */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			foreach ( array( 'dashboard_primary', 'dashboard_quick_press', 'dashboard_site_health', 'dashboard_activity', 'dashboard_right_now' ) as $w ) {
				remove_meta_box( $w, 'dashboard', 'normal' );
				remove_meta_box( $w, 'dashboard', 'side' );
			}
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}
		wp_add_dashboard_widget( 'gat_welcome', __( 'Global Alliance Trust: quick links', 'gat-core' ), 'gat_core_dashboard_widget', null, null, 'normal', 'high' );
	}
);

/**
 * Dashboard widget.
 */
function gat_core_dashboard_widget() {
	$links = array();
	if ( current_user_can( 'edit_pages' ) ) {
		$links[] = array( admin_url( 'edit.php?post_type=page' ), __( 'Edit website pages', 'gat-core' ), 'dashicons-admin-page' );
	}
	if ( current_user_can( 'edit_posts' ) ) {
		$links[] = array( admin_url( 'post-new.php' ), __( 'Add a news item or event', 'gat-core' ), 'dashicons-megaphone' );
	}
	if ( current_user_can( 'edit_theme_options' ) ) {
		$links[] = array( admin_url( 'customize.php?autofocus[panel]=gat_home' ), __( 'Change the homepage words and photos', 'gat-core' ), 'dashicons-admin-home' );
		$links[] = array( admin_url( 'customize.php?autofocus[section]=gat_contact' ), __( 'Contact details & social links', 'gat-core' ), 'dashicons-phone' );
		$links[] = array( admin_url( 'nav-menus.php' ), __( 'Edit the menus', 'gat-core' ), 'dashicons-menu' );
	}
	if ( current_user_can( 'edit_posts' ) && post_type_exists( 'gat_advertiser' ) ) {
		$links[] = array( admin_url( 'edit.php?post_type=gat_advertiser' ), __( 'Advertisers', 'gat-core' ), 'dashicons-store' );
	}
	if ( current_user_can( 'gat_manage_enquiries' ) ) {
		$links[] = array( admin_url( 'edit.php?post_type=gat_enquiry' ), __( 'Enquiries from the website', 'gat-core' ), 'dashicons-email' );
	}
	if ( current_user_can( 'rank_math_titles' ) && defined( 'RANK_MATH_VERSION' ) ) {
		$links[] = array( admin_url( 'admin.php?page=rank-math' ), __( 'Rank Math SEO', 'gat-core' ), 'dashicons-chart-line' );
	}
	if ( current_user_can( 'gat_manage_students' ) ) {
		$links[] = array( admin_url( 'admin.php?page=gat-students' ), __( 'Sunnah Academy students', 'gat-core' ), 'dashicons-welcome-learn-more' );
	}
	if ( current_user_can( 'gat_view_donations' ) ) {
		$links[] = array( admin_url( 'admin.php?page=gat-donations' ), __( 'Donations', 'gat-core' ), 'dashicons-heart' );
	}
	$links[] = array( admin_url( 'profile.php' ), __( 'My profile & language', 'gat-core' ), 'dashicons-admin-users' );

	echo '<ul class="gat-quick">';
	foreach ( $links as list( $url, $label, $icon ) ) {
		printf( '<li><a href="%s"><span class="dashicons %s" aria-hidden="true"></span>%s</a></li>', esc_url( $url ), esc_attr( $icon ), esc_html( $label ) );
	}
	echo '</ul>';
	echo '<p class="gat-quick-tip">' . esc_html__( 'Tip: you can switch the admin panel to Bengali (বাংলা) or another language under My profile → Language. This only changes your own screens.', 'gat-core' ) . '</p>';
	$support = get_option( 'gat_support_contact', '' );
	if ( $support ) {
		/* translators: %s support contact */
		echo '<p class="gat-quick-tip">' . esc_html( sprintf( __( 'Need help? Contact Amverio: %s', 'gat-core' ), $support ) ) . '</p>';
	}
}

add_action(
	'admin_head',
	function () {
		echo '<style>
		.gat-quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px;margin:4px 0 12px}
		.gat-quick a{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;color:#0b3a75;background:#f6f9fd}
		.gat-quick a:hover{border-color:#1d7ce0;background:#eaf3fd}
		.gat-quick .dashicons{color:#1d7ce0}
		.gat-quick-tip{color:#50575e;margin:6px 0 0}
		</style>';
	}
);

add_filter( 'admin_footer_text', fn() => esc_html__( 'Global Alliance Trust website · built and supported by Amverio', 'gat-core' ) );
