<?php
/**
 * Staff roles.
 *
 * - Administrator     Amverio only. Everything.
 * - GAT Editor        Pages, News, Advertisers, Enquiries, menus, homepage (Customizer).
 * - SEO Manager       Rank Math SEO tools + titles/meta on pages and news. No plugins,
 *                     users, enquiries, students or donations. Cannot delete content.
 * - Academy Admin     Sunnah Academy students & enrolments only.
 * - Donations Viewer  Donations list & export only.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math capabilities given to the SEO Manager.
 *
 * @return string[]
 */
function gat_core_rank_math_caps() {
	return array(
		'rank_math_titles',
		'rank_math_general',
		'rank_math_sitemap',
		'rank_math_404_monitor',
		'rank_math_redirections',
		'rank_math_link_builder',
		'rank_math_analytics',
		'rank_math_site_analysis',
		'rank_math_onpage_analysis',
		'rank_math_onpage_general',
		'rank_math_onpage_advanced',
		'rank_math_onpage_snippet',
		'rank_math_onpage_social',
		'rank_math_admin_bar',
	);
}

/**
 * Create / refresh roles.
 */
function gat_core_install_roles() {
	$editor = get_role( 'editor' );
	$editor_caps = $editor ? $editor->capabilities : array();

	// GAT Editor: WordPress Editor + menus/homepage + enquiries.
	remove_role( 'gat_editor' );
	add_role(
		'gat_editor',
		__( 'GAT Editor', 'gat-core' ),
		array_merge(
			$editor_caps,
			array(
				'edit_theme_options'   => true,
				'gat_manage_enquiries' => true,
				'unfiltered_html'      => false,
			)
		)
	);

	// SEO Manager.
	remove_role( 'seo_manager' );
	add_role(
		'seo_manager',
		__( 'SEO Manager', 'gat-core' ),
		array_merge(
			array(
				'read'                   => true,
				'upload_files'           => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'edit_pages'             => true,
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'manage_categories'      => true,
			),
			array_fill_keys( gat_core_rank_math_caps(), true )
		)
	);

	// Academy Admin.
	remove_role( 'academy_admin' );
	add_role(
		'academy_admin',
		__( 'Academy Admin', 'gat-core' ),
		array(
			'read'                => true,
			'gat_manage_students' => true,
		)
	);

	// Donations Viewer.
	remove_role( 'donations_viewer' );
	add_role(
		'donations_viewer',
		__( 'Donations Viewer', 'gat-core' ),
		array(
			'read'               => true,
			'gat_view_donations' => true,
		)
	);

	// Administrators get every custom capability.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array( 'gat_manage_enquiries', 'gat_manage_students', 'gat_view_donations' ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	update_option( 'gat_core_roles_version', GAT_CORE_VERSION );
}
