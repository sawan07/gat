<?php
/**
 * Template helpers.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default homepage content (from GAT's approved content document).
 * Every value can be changed in Appearance → Customize → Homepage.
 *
 * @return array
 */
function gat_home_defaults() {
	return array(
		// Hero.
		'hero_eyebrow'      => 'Welcome to',
		'hero_title'        => 'Global Alliance Trust',
		'hero_script'       => 'Serving the Community',
		'hero_text'         => 'Supporting our community through education, welfare, wellbeing and charitable services.',
		'hero_image'        => '',
		'hero_btn1_label'   => 'Donate Now',
		'hero_btn1_url'     => '/donate/',
		'hero_btn2_label'   => 'Learn More About Us',
		'hero_btn2_url'     => '/about-us/',
		'hero_badge'        => '',
		// News ticker.
		'ticker_show'       => true,
		'ticker_label'      => 'Latest News',
		// About.
		'about_eyebrow'     => 'About Us',
		'about_title'       => 'Building a Stronger Community Together',
		'about_text'        => 'Global Alliance Trust is a non-profit organisation dedicated to supporting individuals and families through a range of services, education, wellbeing and charitable initiatives.',
		'about_image'       => '',
		'about_btn_label'   => 'About Us',
		'about_btn_url'     => '/about-us/',
		'ads_title'         => 'Our Advertisers',
		// Key services.
		'services_title'    => 'Our Key Services',
		'svc1_title'        => 'Educating the Future',
		'svc1_text'         => 'Sunnah Academy & Maktab education programmes.',
		'svc1_url'          => '/sunnah-academy/',
		'svc2_title'        => 'Health & Wellbeing',
		'svc2_text'         => 'Helping individuals access wellbeing support and counselling information.',
		'svc2_url'          => '/our-services/#wellbeing-and-counselling',
		'svc3_title'        => 'Community Development',
		'svc3_text'         => 'Food support, clothing assistance and volunteering opportunities.',
		'svc3_url'          => '/our-services/#community-services',
		'svc_more_label'    => 'Read More',
		'services_btn_label' => 'Other Services',
		'services_btn_url'  => '/our-services/',
		// Sunnah Academy.
		'academy_eyebrow'   => 'Sunnah Academy / Maktab',
		'academy_title'     => 'Learn | Grow | Build Your Future',
		'academy_text'      => 'Enrol your child in our Sunnah Academy / Maktab and give them the foundation for a bright future.',
		'academy_points'    => "Online enrolment\nSecure online payment\nSimple step-by-step application\nInformation kept private and secure",
		'academy_image'     => '',
		'academy_btn_label' => 'Enrol Now',
		'academy_btn_url'   => '/sunnah-academy/enrol/',
		'academy_card_title' => 'How to Enrol',
		'academy_card_text' => 'Complete the form, pay the enrolment fee online, and the Academy will contact you about next steps.',
		'academy_card_btn'  => 'See the Steps',
		'academy_card_url'  => '/sunnah-academy/#how-to-enrol',
		// News.
		'news_title'        => 'Latest News & Events',
		'news_count'        => 4,
	);
}

/**
 * Homepage setting with default.
 *
 * @param string $key Key.
 * @return mixed
 */
function gat_home( $key ) {
	$defaults = gat_home_defaults();
	return get_theme_mod( 'gat_' . $key, $defaults[ $key ] ?? '' );
}

/**
 * Turn a site-relative path ("/donate/") into a full URL; leave full URLs alone.
 *
 * @param string $url URL or path.
 * @return string
 */
function gat_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( str_starts_with( $url, '/' ) || str_starts_with( $url, '#' ) ) {
		return home_url( $url );
	}
	return $url;
}

/**
 * Image URL for a homepage image setting (attachment ID), falling back to the
 * placeholder bundled with the theme.
 *
 * @param string $key      Setting key.
 * @param string $fallback Theme asset filename.
 * @param string $size     Image size.
 * @return string
 */
function gat_home_image( $key, $fallback, $size = 'large' ) {
	$id = absint( gat_home( $key ) );
	if ( $id ) {
		$src = wp_get_attachment_image_url( $id, $size );
		if ( $src ) {
			return $src;
		}
	}
	return gat_asset( 'assets/img/' . $fallback );
}

/**
 * URL of a file in the theme, stamped with the theme version so Cloudflare
 * and browsers fetch the new file after every theme update.
 *
 * @param string $path Path inside the theme.
 * @return string
 */
function gat_asset( $path ) {
	return add_query_arg( 'ver', GAT_THEME_VERSION, get_theme_file_uri( $path ) );
}

/**
 * Site-wide contact details (Customize → Contact & Social).
 *
 * @return array
 */
function gat_contact() {
	return array(
		'address'   => trim( (string) get_theme_mod( 'gat_contact_address', '' ) ),
		'phone'     => trim( (string) get_theme_mod( 'gat_contact_phone', '' ) ),
		'email'     => trim( (string) get_theme_mod( 'gat_contact_email', '' ) ),
		'hours'     => trim( (string) get_theme_mod( 'gat_contact_hours', '' ) ),
		'map'       => trim( (string) get_theme_mod( 'gat_contact_map', '' ) ),
	);
}

/**
 * Social links that have been filled in.
 *
 * @return array network => url
 */
function gat_socials() {
	$out = array();
	foreach ( array( 'facebook', 'youtube', 'x', 'instagram' ) as $net ) {
		$url = trim( (string) get_theme_mod( 'gat_social_' . $net, '' ) );
		if ( $url ) {
			$out[ $net ] = $url;
		}
	}
	return $out;
}

/**
 * Logo URL: custom logo if set, else bundled logo.
 *
 * @return string
 */
function gat_logo_url() {
	$id = get_theme_mod( 'custom_logo' );
	if ( $id ) {
		$src = wp_get_attachment_image_url( $id, 'medium' );
		if ( $src ) {
			return $src;
		}
	}
	return gat_asset( 'assets/img/gat-logo.png' );
}

/**
 * Inline SVG icons (decorative unless a label is given).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 * @return string
 */
function gat_icon( $name, $size = 20 ) {
	$paths = array(
		'heart'     => '<path fill="currentColor" stroke="none" d="M12 21.2 4.2 13.5l-1-1.1a5.5 5.5 0 0 1 7.8-7.8l1 1.1 1-1.1a5.5 5.5 0 0 1 7.8 7.8l-1 1.1z"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
		'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
		'megaphone' => '<path fill="currentColor" stroke="none" d="M3 10v4a1 1 0 0 0 1 1h2l4 4V5L6 9H4a1 1 0 0 0-1 1zm10.5-4.6v13.2c2.9-1 5-3.7 5-6.6s-2.1-5.6-5-6.6z"/>',
		'chevron-l' => '<path d="m15 6-6 6 6 6"/>',
		'chevron-r' => '<path d="m9 6 6 6-6 6"/>',
		'pause'     => '<path d="M9 6v12M15 6v12"/>',
		'play'      => '<path fill="currentColor" stroke="none" d="M8 5v14l11-7z"/>',
		'book'      => '<path d="M3 5.5C5.5 4 9 4 12 6c3-2 6.5-2 9-.5V19c-2.5-1.5-6-1.5-9 .5-3-2-6.5-2-9-.5z"/><path d="M12 6v13.5"/>',
		'wellbeing' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/><path d="M6 12h3l1.5-3 3 6 1.5-3h3"/>',
		'community' => '<circle cx="12" cy="7" r="3"/><circle cx="5" cy="9" r="2.2"/><circle cx="19" cy="9" r="2.2"/><path d="M6.5 20v-2.5a5.5 5.5 0 0 1 11 0V20M1.5 19v-1.5a3.5 3.5 0 0 1 4-3.4M22.5 19v-1.5a3.5 3.5 0 0 0-4-3.4"/>',
		'people'    => '<circle cx="12" cy="8" r="3.5"/><circle cx="5" cy="10" r="2.5"/><circle cx="19" cy="10" r="2.5"/><path fill="currentColor" stroke="none" d="M5.5 21v-2a6.5 6.5 0 0 1 13 0v2zM0 21v-1.5a4.5 4.5 0 0 1 4.6-4.5 8 8 0 0 0-1.3 4.5V21zm24 0v-1.5a4.5 4.5 0 0 0-4.6-4.5 8 8 0 0 1 1.3 4.5V21z"/>',
		'check'     => '<path d="M5 12.5 10 17 19 7.5"/>',
		'pin'       => '<path fill="currentColor" stroke="none" d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2" fill="currentColor" stroke="none"/><path d="m3.5 6 8.5 7 8.5-7" stroke="#0b3a75"/>',
		'phone'     => '<path fill="currentColor" stroke="none" d="M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'facebook'  => '<path fill="currentColor" stroke="none" d="M14 8h3V4h-3c-2.8 0-4.5 1.8-4.5 4.6V11H7v4h2.5v9h4v-9h3l.5-4h-3.5V8.8c0-.5.3-.8.5-.8z"/>',
		'youtube'   => '<path fill="currentColor" stroke="none" d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.7 15.1V8.9l5.8 3.1z"/>',
		'x'         => '<path fill="currentColor" stroke="none" d="M17.8 2h3.4l-7.4 8.5L22.5 22h-6.8l-5.3-7-6.1 7H.9l7.9-9.1L.5 2h7l4.8 6.4zm-1.2 18h1.9L6.5 3.9h-2z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="gat-icon gat-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		(int) $size,
		$paths[ $name ]
	);
}

/**
 * Social icon links.
 *
 * @param string $class Wrapper class.
 */
function gat_social_links( $class = 'gat-socials' ) {
	$socials = gat_socials();
	if ( ! $socials ) {
		return;
	}
	$labels = array( 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'x' => 'X', 'instagram' => 'Instagram' );
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $socials as $net => $url ) {
		printf(
			'<li><a class="gat-social gat-social--%1$s" href="%2$s" target="_blank" rel="noopener"><span class="screen-reader-text">%3$s</span>%4$s</a></li>',
			esc_attr( $net ),
			esc_url( $url ),
			esc_html( $labels[ $net ] ),
			gat_icon( $net, 18 ) // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		);
	}
	echo '</ul>';
}

/**
 * Active advertisers (from the GAT Core plugin), or an empty array.
 *
 * @return array of [ 'name', 'url', 'logo' ]
 */
function gat_advertisers() {
	return function_exists( 'gat_core_active_advertisers' ) ? gat_core_active_advertisers() : array();
}

/**
 * Branding block used in header and footer.
 *
 * @param string $context header|footer.
 */
function gat_brand( $context = 'header' ) {
	?>
	<a class="gat-brand gat-brand--<?php echo esc_attr( $context ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
		<span class="gat-brand__logo"><img src="<?php echo esc_url( gat_logo_url() ); ?>" alt="" width="56" height="56"></span>
		<span class="gat-brand__text">
			<span class="gat-brand__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<span class="gat-brand__tag"><?php esc_html_e( 'Non-Profit Organisation', 'gat' ); ?><span class="sep" aria-hidden="true">|</span><?php esc_html_e( 'Serving the Community', 'gat' ); ?></span>
		</span>
	</a>
	<?php
}
