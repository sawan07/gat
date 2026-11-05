<?php
/**
 * Customizer: Homepage content and Contact & Social details.
 *
 * Appearance → Customize → "Homepage" / "Contact & Social".
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitise a link: allow site paths (/donate/, #id) and full URLs.
 *
 * @param string $value Value.
 * @return string
 */
function gat_sanitize_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( str_starts_with( $value, '/' ) || str_starts_with( $value, '#' ) ) {
		return sanitize_text_field( $value );
	}
	return esc_url_raw( $value );
}

/**
 * Register settings.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function gat_customize_register( $wp_customize ) {
	$d = gat_home_defaults();

	$wp_customize->add_panel(
		'gat_home',
		array(
			'title'       => __( 'Homepage', 'gat' ),
			'description' => __( 'Change the words, pictures and buttons on the homepage. Links can be a page address such as /donate/ or a full web address.', 'gat' ),
			'priority'    => 20,
		)
	);

	$sections = array(
		'gat_home_hero'     => __( '1. Top banner', 'gat' ),
		'gat_home_ticker'   => __( '2. News ticker', 'gat' ),
		'gat_home_about'    => __( '3. About & advertisers', 'gat' ),
		'gat_home_services' => __( '4. Key services', 'gat' ),
		'gat_home_academy'  => __( '5. Sunnah Academy', 'gat' ),
		'gat_home_news'     => __( '6. Latest news', 'gat' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'gat_home' ) );
	}

	$fields = array(
		// key, section, label, type.
		array( 'hero_eyebrow', 'gat_home_hero', __( 'Small text above title', 'gat' ), 'text' ),
		array( 'hero_title', 'gat_home_hero', __( 'Title', 'gat' ), 'text' ),
		array( 'hero_script', 'gat_home_hero', __( 'Handwritten line', 'gat' ), 'text' ),
		array( 'hero_text', 'gat_home_hero', __( 'Text', 'gat' ), 'textarea' ),
		array( 'hero_image', 'gat_home_hero', __( 'Photo (wide, at least 1400px)', 'gat' ), 'image' ),
		array( 'hero_badge', 'gat_home_hero', __( 'Handwritten badge on photo (one line per row, leave empty to hide)', 'gat' ), 'textarea' ),
		array( 'hero_btn1_label', 'gat_home_hero', __( 'Button 1 text', 'gat' ), 'text' ),
		array( 'hero_btn1_url', 'gat_home_hero', __( 'Button 1 link', 'gat' ), 'link' ),
		array( 'hero_btn2_label', 'gat_home_hero', __( 'Button 2 text', 'gat' ), 'text' ),
		array( 'hero_btn2_url', 'gat_home_hero', __( 'Button 2 link', 'gat' ), 'link' ),

		array( 'ticker_show', 'gat_home_ticker', __( 'Show the news ticker (uses your latest News posts)', 'gat' ), 'checkbox' ),
		array( 'ticker_label', 'gat_home_ticker', __( 'Label', 'gat' ), 'text' ),

		array( 'about_eyebrow', 'gat_home_about', __( 'Small text above title', 'gat' ), 'text' ),
		array( 'about_title', 'gat_home_about', __( 'Title', 'gat' ), 'text' ),
		array( 'about_text', 'gat_home_about', __( 'Text', 'gat' ), 'textarea' ),
		array( 'about_image', 'gat_home_about', __( 'Photo', 'gat' ), 'image' ),
		array( 'about_btn_label', 'gat_home_about', __( 'Button text', 'gat' ), 'text' ),
		array( 'about_btn_url', 'gat_home_about', __( 'Button link', 'gat' ), 'link' ),
		array( 'ads_title', 'gat_home_about', __( 'Advertisers box title (add advertisers under Advertisers in the admin menu)', 'gat' ), 'text' ),

		array( 'services_title', 'gat_home_services', __( 'Section title', 'gat' ), 'text' ),
		array( 'svc1_title', 'gat_home_services', __( 'Card 1 (blue) title', 'gat' ), 'text' ),
		array( 'svc1_text', 'gat_home_services', __( 'Card 1 text', 'gat' ), 'textarea' ),
		array( 'svc1_url', 'gat_home_services', __( 'Card 1 link', 'gat' ), 'link' ),
		array( 'svc2_title', 'gat_home_services', __( 'Card 2 (green) title', 'gat' ), 'text' ),
		array( 'svc2_text', 'gat_home_services', __( 'Card 2 text', 'gat' ), 'textarea' ),
		array( 'svc2_url', 'gat_home_services', __( 'Card 2 link', 'gat' ), 'link' ),
		array( 'svc3_title', 'gat_home_services', __( 'Card 3 (teal) title', 'gat' ), 'text' ),
		array( 'svc3_text', 'gat_home_services', __( 'Card 3 text', 'gat' ), 'textarea' ),
		array( 'svc3_url', 'gat_home_services', __( 'Card 3 link', 'gat' ), 'link' ),
		array( 'svc_more_label', 'gat_home_services', __( 'Card button text', 'gat' ), 'text' ),
		array( 'services_btn_label', 'gat_home_services', __( 'Bottom button text', 'gat' ), 'text' ),
		array( 'services_btn_url', 'gat_home_services', __( 'Bottom button link', 'gat' ), 'link' ),

		array( 'academy_eyebrow', 'gat_home_academy', __( 'Small text above title', 'gat' ), 'text' ),
		array( 'academy_title', 'gat_home_academy', __( 'Title (use | to separate words)', 'gat' ), 'text' ),
		array( 'academy_text', 'gat_home_academy', __( 'Text', 'gat' ), 'textarea' ),
		array( 'academy_points', 'gat_home_academy', __( 'Tick list (one per line)', 'gat' ), 'textarea' ),
		array( 'academy_image', 'gat_home_academy', __( 'Photo', 'gat' ), 'image' ),
		array( 'academy_btn_label', 'gat_home_academy', __( 'Button text', 'gat' ), 'text' ),
		array( 'academy_btn_url', 'gat_home_academy', __( 'Button link', 'gat' ), 'link' ),
		array( 'academy_card_title', 'gat_home_academy', __( 'Side card title', 'gat' ), 'text' ),
		array( 'academy_card_text', 'gat_home_academy', __( 'Side card text', 'gat' ), 'textarea' ),
		array( 'academy_card_btn', 'gat_home_academy', __( 'Side card button text', 'gat' ), 'text' ),
		array( 'academy_card_url', 'gat_home_academy', __( 'Side card button link', 'gat' ), 'link' ),

		array( 'news_title', 'gat_home_news', __( 'Section title', 'gat' ), 'text' ),
		array( 'news_count', 'gat_home_news', __( 'How many news posts to show', 'gat' ), 'number' ),
	);

	foreach ( $fields as list( $key, $section, $label, $type ) ) {
		$sanitize = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'link'     => 'gat_sanitize_link',
			'image'    => 'absint',
			'checkbox' => 'wp_validate_boolean',
			'number'   => 'absint',
		)[ $type ];

		$wp_customize->add_setting(
			'gat_' . $key,
			array(
				'default'           => $d[ $key ],
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			)
		);

		if ( 'image' === $type ) {
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					'gat_' . $key,
					array( 'label' => $label, 'section' => $section, 'mime_type' => 'image' )
				)
			);
			continue;
		}

		$wp_customize->add_control(
			'gat_' . $key,
			array(
				'label'       => $label,
				'section'     => $section,
				'type'        => 'link' === $type ? 'text' : $type,
				'input_attrs' => 'number' === $type ? array( 'min' => 2, 'max' => 8 ) : array(),
			)
		);
	}

	// Contact & social (used in the footer and on the Contact page).
	$wp_customize->add_section(
		'gat_contact',
		array(
			'title'       => __( 'Contact & Social', 'gat' ),
			'description' => __( 'Shown in the footer and on the Contact page. Leave a box empty to hide it.', 'gat' ),
			'priority'    => 25,
		)
	);
	$contact = array(
		'gat_contact_address'  => array( __( 'Address', 'gat' ), 'textarea', 'sanitize_textarea_field' ),
		'gat_contact_phone'    => array( __( 'Telephone', 'gat' ), 'text', 'sanitize_text_field' ),
		'gat_contact_email'    => array( __( 'Email', 'gat' ), 'email', 'sanitize_email' ),
		'gat_contact_hours'    => array( __( 'Opening hours', 'gat' ), 'textarea', 'sanitize_textarea_field' ),
		'gat_contact_map'      => array( __( 'Google Maps search text for the map (e.g. the full address)', 'gat' ), 'text', 'sanitize_text_field' ),
		'gat_social_facebook'  => array( __( 'Facebook page link', 'gat' ), 'url', 'esc_url_raw' ),
		'gat_social_instagram' => array( __( 'Instagram link', 'gat' ), 'url', 'esc_url_raw' ),
		'gat_social_youtube'   => array( __( 'YouTube link', 'gat' ), 'url', 'esc_url_raw' ),
		'gat_social_x'         => array( __( 'X (Twitter) link', 'gat' ), 'url', 'esc_url_raw' ),
	);
	foreach ( $contact as $id => list( $label, $type, $sanitize ) ) {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => $sanitize ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'gat_contact', 'type' => $type ) );
	}
}
add_action( 'customize_register', 'gat_customize_register' );
