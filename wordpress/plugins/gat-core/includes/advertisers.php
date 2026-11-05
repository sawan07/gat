<?php
/**
 * Advertisers: logo, website link and the dates the advert should show.
 * Expired adverts disappear from the site automatically.
 *
 * Shortcode: [gat_advertisers] shows all current advertisers as a grid.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Advertisers post type.
 */
function gat_core_register_advertisers() {
	register_post_type(
		'gat_advertiser',
		array(
			'labels'          => array(
				'name'               => __( 'Advertisers', 'gat-core' ),
				'singular_name'      => __( 'Advertiser', 'gat-core' ),
				'add_new'            => __( 'Add advertiser', 'gat-core' ),
				'add_new_item'       => __( 'Add advertiser', 'gat-core' ),
				'edit_item'          => __( 'Edit advertiser', 'gat-core' ),
				'all_items'          => __( 'All advertisers', 'gat-core' ),
				'search_items'       => __( 'Search advertisers', 'gat-core' ),
				'not_found'          => __( 'No advertisers yet', 'gat-core' ),
				'featured_image'     => __( 'Logo', 'gat-core' ),
				'set_featured_image' => __( 'Set logo', 'gat-core' ),
				'remove_featured_image' => __( 'Remove logo', 'gat-core' ),
				'use_featured_image' => __( 'Use as logo', 'gat-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 22,
			'menu_icon'       => 'dashicons-store',
			'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'gat_core_register_advertisers' );

add_filter(
	'enter_title_here',
	fn( $text, $post ) => 'gat_advertiser' === $post->post_type ? __( 'Business name', 'gat-core' ) : $text,
	10,
	2
);

add_action(
	'add_meta_boxes_gat_advertiser',
	function () {
		add_meta_box( 'gat_ad_details', __( 'Advert details', 'gat-core' ), 'gat_core_advertiser_box', 'gat_advertiser', 'normal', 'high' );
	}
);

/**
 * Meta box.
 *
 * @param WP_Post $post Post.
 */
function gat_core_advertiser_box( $post ) {
	wp_nonce_field( 'gat_ad_save', 'gat_ad_nonce' );
	$url   = get_post_meta( $post->ID, '_gat_ad_url', true );
	$start = get_post_meta( $post->ID, '_gat_ad_start', true );
	$end   = get_post_meta( $post->ID, '_gat_ad_end', true );
	?>
	<p><?php esc_html_e( 'Add the logo using “Set logo” on the right (a square or wide logo on a white background works best).', 'gat-core' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="gat_ad_url"><?php esc_html_e( 'Business website (optional)', 'gat-core' ); ?></label></th>
			<td><input type="url" class="regular-text" id="gat_ad_url" name="gat_ad_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://"></td>
		</tr>
		<tr>
			<th><label for="gat_ad_start"><?php esc_html_e( 'Show from (optional)', 'gat-core' ); ?></label></th>
			<td><input type="date" id="gat_ad_start" name="gat_ad_start" value="<?php echo esc_attr( $start ); ?>"></td>
		</tr>
		<tr>
			<th><label for="gat_ad_end"><?php esc_html_e( 'Show until (optional)', 'gat-core' ); ?></label></th>
			<td><input type="date" id="gat_ad_end" name="gat_ad_end" value="<?php echo esc_attr( $end ); ?>">
			<p class="description"><?php esc_html_e( 'After this date the advert stops showing on the website automatically.', 'gat-core' ); ?></p></td>
		</tr>
	</table>
	<?php
}

add_action(
	'save_post_gat_advertiser',
	function ( $post_id ) {
		if ( ! isset( $_POST['gat_ad_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gat_ad_nonce'] ) ), 'gat_ad_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = isset( $_POST['gat_ad_url'] ) ? esc_url_raw( wp_unslash( $_POST['gat_ad_url'] ) ) : '';
		update_post_meta( $post_id, '_gat_ad_url', $url );
		foreach ( array( 'start', 'end' ) as $k ) {
			$v = isset( $_POST[ 'gat_ad_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gat_ad_' . $k ] ) ) : '';
			update_post_meta( $post_id, '_gat_ad_' . $k, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '' );
		}
	}
);

// Admin list columns.
add_filter(
	'manage_gat_advertiser_posts_columns',
	function ( $cols ) {
		return array(
			'cb'        => $cols['cb'],
			'gat_logo'  => __( 'Logo', 'gat-core' ),
			'title'     => __( 'Business', 'gat-core' ),
			'gat_url'   => __( 'Website', 'gat-core' ),
			'gat_dates' => __( 'Showing', 'gat-core' ),
		);
	}
);
add_action(
	'manage_gat_advertiser_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'gat_logo' === $col ) {
			echo get_the_post_thumbnail( $post_id, array( 60, 60 ) );
		} elseif ( 'gat_url' === $col ) {
			$url = get_post_meta( $post_id, '_gat_ad_url', true );
			echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>' : '—';
		} elseif ( 'gat_dates' === $col ) {
			$start = get_post_meta( $post_id, '_gat_ad_start', true );
			$end   = get_post_meta( $post_id, '_gat_ad_end', true );
			$live  = gat_core_ad_is_live( $post_id );
			echo $live ? '<strong style="color:#1d7a3a">' . esc_html__( 'Live', 'gat-core' ) . '</strong>' : '<strong style="color:#b32d2e">' . esc_html__( 'Not showing', 'gat-core' ) . '</strong>';
			if ( $start || $end ) {
				echo '<br>' . esc_html( ( $start ? $start : '…' ) . ' → ' . ( $end ? $end : '…' ) );
			}
		}
	},
	10,
	2
);

/**
 * Is an advert within its dates and published?
 *
 * @param int $post_id Post.
 * @return bool
 */
function gat_core_ad_is_live( $post_id ) {
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	$today = wp_date( 'Y-m-d' );
	$start = get_post_meta( $post_id, '_gat_ad_start', true );
	$end   = get_post_meta( $post_id, '_gat_ad_end', true );
	return ( ! $start || $start <= $today ) && ( ! $end || $end >= $today );
}

/**
 * Current advertisers, for the theme.
 *
 * @return array of [ name, url, logo ]
 */
function gat_core_active_advertisers() {
	$posts = get_posts(
		array(
			'post_type'      => 'gat_advertiser',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	$out = array();
	foreach ( $posts as $p ) {
		if ( ! gat_core_ad_is_live( $p->ID ) ) {
			continue;
		}
		$out[] = array(
			'name' => get_the_title( $p ),
			'url'  => get_post_meta( $p->ID, '_gat_ad_url', true ),
			'logo' => get_the_post_thumbnail_url( $p, 'medium' ),
		);
	}
	return $out;
}

add_shortcode(
	'gat_advertisers',
	function () {
		$ads = gat_core_active_advertisers();
		if ( ! $ads ) {
			return '<p>' . esc_html__( 'Our advertisers will be featured here soon.', 'gat-core' ) . '</p>';
		}
		$html = '<ul class="gat-ad-grid">';
		foreach ( $ads as $ad ) {
			$inner = $ad['logo'] ? '<img src="' . esc_url( $ad['logo'] ) . '" alt="" loading="lazy">' : '';
			$inner .= '<span class="screen-reader-text">' . esc_html( $ad['name'] ) . '</span>';
			$html .= '<li>' . ( $ad['url']
				? '<a href="' . esc_url( $ad['url'] ) . '" target="_blank" rel="noopener sponsored">' . $inner . '</a>'
				: '<span>' . $inner . '</span>' ) . '</li>';
		}
		return $html . '</ul>';
	}
);
