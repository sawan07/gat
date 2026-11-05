<?php
/**
 * Tools → GAT Setup: creates the pages (content from GAT's website content
 * document), menus and site settings, plus clearly marked sample news and
 * advertisers. Safe to run more than once: existing pages are never
 * overwritten.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Small helpers that write valid block-editor markup.
 * ------------------------------------------------------------------------- */

/** Paragraph. */
function gat_b_p( $html, $style = '' ) {
	$attrs = $style ? ' {"className":"is-style-' . $style . '"}' : '';
	$cls   = $style ? ' class="is-style-' . $style . '"' : '';
	return "<!-- wp:paragraph{$attrs} -->\n<p{$cls}>{$html}</p>\n<!-- /wp:paragraph -->\n\n";
}

/** Heading. */
function gat_b_h( $text, $level = 2, $anchor = '' ) {
	$a = array();
	if ( 2 !== $level ) {
		$a['level'] = $level;
	}
	if ( $anchor ) {
		$a['anchor'] = $anchor;
	}
	$json = $a ? ' ' . wp_json_encode( $a ) : '';
	$id   = $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';
	return "<!-- wp:heading{$json} -->\n<h{$level} class=\"wp-block-heading\"{$id}>{$text}</h{$level}>\n<!-- /wp:heading -->\n\n";
}

/** List (ul or ol), optional block style. */
function gat_b_list( $items, $ordered = false, $style = '' ) {
	$a = array();
	if ( $ordered ) {
		$a['ordered'] = true;
	}
	if ( $style ) {
		$a['className'] = 'is-style-' . $style;
	}
	$json = $a ? ' ' . wp_json_encode( $a ) : '';
	$tag  = $ordered ? 'ol' : 'ul';
	$cls  = 'wp-block-list' . ( $style ? ' is-style-' . $style : '' );
	$out  = "<!-- wp:list{$json} -->\n<{$tag} class=\"{$cls}\">";
	foreach ( $items as $item ) {
		$out .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->";
	}
	return $out . "</{$tag}>\n<!-- /wp:list -->\n\n";
}

/** Buttons: array of [label, url, outline?]. */
function gat_b_buttons( $buttons ) {
	$out = "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">";
	foreach ( $buttons as $b ) {
		$outline = ! empty( $b[2] );
		$attrs   = $outline ? ' {"className":"is-style-outline"}' : '';
		$cls     = 'wp-block-button' . ( $outline ? ' is-style-outline' : '' );
		$out    .= "<!-- wp:button{$attrs} -->\n<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $b[1] ) . "\">{$b[0]}</a></div>\n<!-- /wp:button -->";
	}
	return $out . "</div>\n<!-- /wp:buttons -->\n\n";
}

/** Group with a block style. */
function gat_b_group( $inner, $style ) {
	return "<!-- wp:group {\"className\":\"is-style-{$style}\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group is-style-{$style}\">{$inner}</div>\n<!-- /wp:group -->\n\n";
}

/** Columns (each item is inner markup), optional "cards" style. */
function gat_b_columns( $cols, $style = 'gat-cards' ) {
	$attrs = $style ? ' {"className":"is-style-' . $style . '"}' : '';
	$cls   = 'wp-block-columns' . ( $style ? ' is-style-' . $style : '' );
	$out   = "<!-- wp:columns{$attrs} -->\n<div class=\"{$cls}\">";
	foreach ( $cols as $c ) {
		$out .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$c}</div>\n<!-- /wp:column -->";
	}
	return $out . "</div>\n<!-- /wp:columns -->\n\n";
}

/** Shortcode block. */
function gat_b_sc( $code ) {
	return "<!-- wp:shortcode -->\n{$code}\n<!-- /wp:shortcode -->\n\n";
}

/* ---------------------------------------------------------------------------
 * Page content (from "GAT – Complete Website Content").
 * ------------------------------------------------------------------------- */

/**
 * All pages: slug => [title, excerpt (subtitle), content, parent slug].
 *
 * @return array
 */
function gat_core_starter_pages() {
	$pages = array();

	$pages['home'] = array( 'Home', '', '', '' );

	$pages['about-us'] = array(
		'About Us',
		'Serving the Community with Purpose',
		gat_b_p( 'Global Alliance Trust (GAT) is a non-profit organisation focused on community service, education, welfare and charitable initiatives.' )
		. gat_b_p( 'We aim to create opportunities for individuals and families to access support, participate in community activities and benefit from educational and social initiatives.' )
		. gat_b_p( 'Our website provides information about our services, educational programmes, charitable activities and opportunities to support our work.' )
		. gat_b_columns(
			array(
				gat_b_h( 'Our Vision', 3 ) . gat_b_p( 'Our vision is to contribute towards a stronger, more connected and supportive community where individuals and families have opportunities to learn, participate and thrive.' ),
				gat_b_h( 'Our Mission', 3 ) . gat_b_p( 'Our mission is to support the community through education, welfare, charitable activities and community engagement.' ),
			)
		)
		. gat_b_h( 'We aim to' )
		. gat_b_list(
			array(
				'Encourage community participation and cooperation.',
				'Promote access to educational opportunities.',
				'Support initiatives that benefit individuals and families.',
				'Encourage the wellbeing and social inclusion of community members.',
				'Create opportunities for volunteering and charitable involvement.',
				'Develop services that respond to community needs.',
			),
			false,
			'gat-checks'
		)
		. gat_b_h( 'Our Values' )
		. gat_b_columns(
			array(
				gat_b_h( 'Compassion', 3 ) . gat_b_p( 'We recognise the importance of understanding, kindness and support within the community.' ),
				gat_b_h( 'Community', 3 ) . gat_b_p( 'We believe that working together can help create positive and lasting change.' ),
				gat_b_h( 'Education', 3 ) . gat_b_p( 'We value learning as a way to develop knowledge, confidence and opportunities.' ),
			)
		)
		. gat_b_columns(
			array(
				gat_b_h( 'Respect', 3 ) . gat_b_p( 'We aim to treat individuals and families with dignity and consideration.' ),
				gat_b_h( 'Cooperation', 3 ) . gat_b_p( 'We welcome opportunities to work with volunteers, businesses, organisations and community members.' ),
			)
		)
		. gat_b_group(
			gat_b_h( 'Working Together' )
			. gat_b_p( 'We believe that a stronger community is built through participation, mutual support and shared responsibility.' )
			. gat_b_p( 'Whether you are looking for information about our services, interested in educational opportunities, considering volunteering or wishing to support our work, we welcome your interest in Global Alliance Trust.' )
			. gat_b_buttons( array( array( 'Explore Our Services', '/our-services/' ), array( 'Contact Us', '/contact-us/' ), array( 'Make a Donation', '/donate/' ) ) ),
			'gat-highlight'
		),
		'',
	);

	$services = array(
		array( 'community-services', 'Community Services', array( 'We aim to support community members through practical assistance, volunteering and community-focused initiatives.', 'Our community service areas may include food bank support, clothing bank initiatives and opportunities for volunteers to contribute their time and skills.', '<strong>Interested in community support or volunteering?</strong> <a href="/contact-us/">Contact us</a> to enquire about available services and opportunities.' ) ),
		array( 'elderly-services', 'Elderly Services', array( 'Older people can benefit from companionship, social interaction and opportunities to remain connected with their community.', 'Our elderly services aim to encourage participation, reduce social isolation and create opportunities for older community members to connect with others.', 'Activities and support arrangements will depend on the programmes available.' ) ),
		array( 'womens-services', 'Women’s Services', array( 'We aim to encourage women’s participation in community life and promote access to relevant health, wellbeing and educational activities.', 'Our women’s services section will provide information about available activities, programmes and opportunities to get involved.' ) ),
		array( 'youth-services', 'Youth Services', array( 'Young people benefit from positive opportunities to learn, develop skills and participate in their community.', 'Our youth services aim to encourage engagement, personal development and constructive community participation through relevant activities and programmes.' ) ),
		array( 'childrens-services', 'Children’s Services', array( 'We recognise the importance of children’s learning, development and educational support.', 'Our children’s services may include tuition and educational activities designed to support learning and encourage confidence.', 'Please <a href="/contact-us/">contact us</a> for information about available programmes, age groups and enrolment requirements.' ) ),
		array( 'education', 'Education', array( 'Education is an important part of building a stronger community.', 'Global Alliance Trust provides a dedicated section for Sunnah Academy / Maktab, where parents and guardians can access programme information and complete student enrolment online.' ) ),
		array( 'wellbeing-and-counselling', 'Wellbeing and Counselling', array( 'Personal wellbeing and access to appropriate support are important for individuals and families.', 'This section provides information about relevant wellbeing and counselling services. Visitors can contact the Trust to enquire about available support or appropriate referral options.' ) ),
		array( 'legal-and-advisory-services', 'Legal and Advisory Services', array( 'Finding appropriate advice can be difficult when dealing with personal, family or practical matters.', 'Our legal and advisory section aims to help visitors find information about relevant support and advisory services.', 'Any legal advice or representation will be subject to the arrangements and qualifications of the relevant provider.' ) ),
		array( 'religious-activities', 'Religious Activities', array( 'We aim to support religious education and community activities that encourage learning, participation and positive community relationships.', 'Visitors can contact us for further information about activities, programmes and educational opportunities.' ) ),
	);
	$svc_html = gat_b_p( 'Global Alliance Trust aims to support the community through a range of educational, charitable, social and wellbeing initiatives. Explore the service areas below to find out more.' );
	foreach ( $services as $i => list( $anchor, $title, $paras ) ) {
		$inner = gat_b_h( ( $i + 1 ) . '. ' . $title, 2, $anchor );
		foreach ( $paras as $p ) {
			$inner .= gat_b_p( $p );
		}
		if ( 'education' === $anchor ) {
			$inner .= gat_b_buttons( array( array( 'Visit Sunnah Academy / Maktab', '/sunnah-academy/' ) ) );
		}
		$svc_html .= gat_b_group( $inner, 'gat-panel' );
	}
	$svc_html .= gat_b_group(
		gat_b_h( 'Need More Information?' )
		. gat_b_p( 'If you would like to learn more about any of our services, please contact Global Alliance Trust.' )
		. gat_b_buttons( array( array( 'Contact Us', '/contact-us/' ), array( 'Get Involved', '/contact-us/' ), array( 'Support Our Work', '/donate/' ) ) ),
		'gat-highlight'
	);
	$pages['our-services'] = array( 'Our Services', 'Supporting Individuals, Families and the Community', $svc_html, '' );

	$pages['management'] = array(
		'Management',
		'Working Together to Serve the Community',
		gat_b_p( 'Global Alliance Trust is committed to developing its community activities and services through responsible management, cooperation and community involvement.' )
		. gat_b_p( 'This page introduces the people responsible for guiding and supporting the organisation’s work.' )
		. gat_b_h( 'Meet Our Team' )
		. gat_b_p( 'Team profiles will be added here once names, roles, photographs and biographies have been confirmed by the organisation.', 'gat-note' )
		. gat_b_h( 'Our Approach' )
		. gat_b_p( 'We aim to manage our activities responsibly, communicate clearly and work collaboratively towards the organisation’s objectives.' )
		. gat_b_p( 'Our management team supports the development of community services, educational programmes and charitable initiatives.' )
		. gat_b_group(
			gat_b_h( 'Contact the Management Team' )
			. gat_b_p( 'For general enquiries, partnership opportunities or questions about Global Alliance Trust, please contact us through our Contact Us page.' )
			. gat_b_buttons( array( array( 'Contact Us', '/contact-us/' ) ) ),
			'gat-highlight'
		),
		'',
	);

	$pages['donate'] = array(
		'Donate',
		'Together, We Can Make a Difference',
		gat_b_p( 'Your support can help Global Alliance Trust develop its community services, educational activities and charitable initiatives.' )
		. gat_b_p( 'Donations provide an opportunity for individuals and organisations to contribute towards work that aims to benefit the community.' )
		. gat_b_group(
			gat_b_h( 'Make a Donation' )
			. gat_b_p( 'Choose an amount or enter the amount you wish to contribute: £10, £25, £50, £100 or any other amount, as a one-off or monthly gift.' )
			. gat_b_p( 'Our online donation assistant will guide you through choosing a cause and send you a secure Stripe payment link.' )
			. gat_b_buttons( array( array( 'Donate Now', 'https://gat.amverio.co.uk/' ) ) ),
			'gat-highlight'
		)
		. gat_b_h( 'Why Support Us?' )
		. gat_b_p( 'Your contribution can help support the development of community activities and services. Depending on the Trust’s approved priorities and available funding, donations may contribute towards:' )
		. gat_b_list(
			array(
				'Community welfare initiatives.',
				'Educational programmes.',
				'Activities for children and young people.',
				'Support for older community members.',
				'Charitable and community engagement activities.',
				'The development and delivery of services.',
			),
			false,
			'gat-checks'
		)
		. gat_b_h( 'Secure Online Donations' )
		. gat_b_p( 'Donations are processed securely by Stripe, the payment provider integrated into our website. Please review the payment information displayed before completing your donation.' )
		. gat_b_p( 'You will receive a payment confirmation following a successful transaction.' )
		. gat_b_h( 'Other Ways to Support Us' )
		. gat_b_p( 'You can also support Global Alliance Trust by sharing information about our work, volunteering where opportunities are available, or exploring partnership and advertising opportunities.' )
		. gat_b_buttons( array( array( 'Contact Us', '/contact-us/', true ), array( 'Volunteer', '/contact-us/', true ), array( 'Advertise With Us', '/advertising/', true ) ) ),
		'',
	);

	$pages['sunnah-academy'] = array(
		'Sunnah Academy / Maktab',
		'Learn, Grow and Build Your Future',
		gat_b_p( 'Welcome to the Sunnah Academy / Maktab section of Global Alliance Trust.' )
		. gat_b_p( 'This section provides information about the educational programme and offers parents and guardians an online facility to submit student enrolment details. Our aim is to make the enrolment process straightforward and accessible.' )
		. gat_b_h( 'About the Academy' )
		. gat_b_p( 'Sunnah Academy / Maktab is an educational programme within Global Alliance Trust’s community offering. Parents and guardians can use this page to learn about the programme, review the enrolment requirements and register their child online.' )
		. gat_b_buttons( array( array( 'Enrol Your Child', '/sunnah-academy/enrol/' ) ) )
		. gat_b_h( 'Student Enrolment' )
		. gat_b_p( 'Parents and guardians can complete the online enrolment form by providing the information requested by the Academy.' )
		. gat_b_columns(
			array(
				gat_b_h( 'Student information', 3 ) . gat_b_list( array( 'Student’s full name', 'Date of birth', 'Gender, if required', 'Address', 'Previous educational information, if applicable' ) ),
				gat_b_h( 'Parent / guardian information', 3 ) . gat_b_list( array( 'Parent or guardian’s full name', 'Relationship to student', 'Contact telephone number', 'Email address', 'Emergency contact information, if required' ) ),
			)
		)
		. gat_b_p( 'Any further information required for enrolment will be explained within the application form. Please ensure that the information submitted is accurate and complete.' )
		. gat_b_h( 'Enrolment Fee' )
		. gat_b_p( 'The enrolment fee will be shown here once it has been confirmed by the Academy. Parents or guardians can pay the required fee online using the payment facility provided on the website, and the application process will guide you through the form and payment steps.', 'gat-note' )
		. gat_b_h( 'How to Enrol', 2, 'how-to-enrol' )
		. gat_b_list(
			array(
				'<strong>Complete</strong> the student enrolment form.',
				'<strong>Review</strong> the information entered and submit the application.',
				'<strong>Pay</strong> the applicable enrolment fee through the online payment gateway.',
				'<strong>Receive</strong> confirmation of the application and payment.',
				'<strong>The Academy will review</strong> the application and contact the parent or guardian regarding the next steps.',
			),
			true,
			'gat-steps'
		)
		. gat_b_p( 'Payment does not automatically guarantee a place unless this is confirmed by the Academy’s enrolment policy.' )
		. gat_b_h( 'Student Records' )
		. gat_b_p( 'The Academy uses a secure administration system to manage student enrolment records. Only authorised staff can view and manage student details and review enrolment and payment information.' )
		. gat_b_p( 'Student information is handled in accordance with the organisation’s privacy arrangements and applicable data protection requirements.' )
		. gat_b_h( 'Important Information for Parents' )
		. gat_b_p( 'Before submitting an application, please ensure that:' )
		. gat_b_list(
			array(
				'All required information is accurate.',
				'Contact details are correct.',
				'You have reviewed the applicable fees.',
				'You have read the relevant privacy information.',
				'You understand that enrolment is subject to the Academy’s confirmed admission arrangements.',
			),
			false,
			'gat-checks'
		)
		. gat_b_group(
			gat_b_h( 'Questions About Enrolment?' )
			. gat_b_p( 'For questions about the programme, fees, classes or application process, please contact Global Alliance Trust.' )
			. gat_b_buttons( array( array( 'Enrol Now', '/sunnah-academy/enrol/' ), array( 'Contact Us', '/contact-us/' ) ) ),
			'gat-highlight'
		),
		'',
	);

	$pages['enrol'] = array(
		'Student Enrolment',
		'Online Student Registration',
		gat_b_p( 'Online enrolment for Sunnah Academy / Maktab is opening soon. In the meantime, please <a href="/contact-us/">contact us</a> and we will help you register your child.', 'gat-note' )
		. gat_b_p( 'When the online form opens, you will be asked for the following:' )
		. gat_b_columns(
			array(
				gat_b_h( 'A. Student details', 3 ) . gat_b_list( array( 'Student full name', 'Date of birth', 'Address and postcode', 'Other information required by the Academy' ) ),
				gat_b_h( 'B. Parent / guardian details', 3 ) . gat_b_list( array( 'Full name', 'Relationship to student', 'Telephone number', 'Email address', 'Emergency contact, if required' ) ),
			)
		)
		. gat_b_columns(
			array(
				gat_b_h( 'C. Enrolment details', 3 ) . gat_b_list( array( 'Programme or class', 'Preferred start date, if applicable', 'Additional information relevant to the application' ) ),
				gat_b_h( 'D. Declarations', 3 ) . gat_b_list( array( 'The information provided is accurate to the best of your knowledge', 'You have read the enrolment terms and privacy information', 'The application will be reviewed in line with the Academy’s enrolment arrangements' ) ),
			)
		)
		. gat_b_p( 'After submitting the form you will continue to secure online payment of the enrolment fee, and receive confirmation using the contact details provided. The Academy will contact you if further information is required.' )
		. gat_b_buttons( array( array( 'Contact Us', '/contact-us/' ), array( 'Back to Sunnah Academy', '/sunnah-academy/', true ) ) ),
		'sunnah-academy',
	);

	$pages['advertising'] = array(
		'Advertising',
		'Promote Your Business. Support Your Community.',
		gat_b_p( 'Global Alliance Trust welcomes enquiries from businesses and organisations interested in advertising through our website.' )
		. gat_b_p( 'Our website’s advertising section showcases participating businesses through a scrolling logo banner and dedicated advertiser displays. This provides businesses with an opportunity to increase their visibility while supporting a community-focused organisation.' )
		. gat_b_h( 'Our Advertisers' )
		. gat_b_sc( '[gat_advertisers]' )
		. gat_b_h( 'Advertising Opportunities' )
		. gat_b_columns(
			array(
				gat_b_h( 'Website Logo Advertising', 3 ) . gat_b_p( 'Display your company logo within the website’s scrolling advertisement section.' ),
				gat_b_h( 'Business Website Link', 3 ) . gat_b_p( 'Where agreed, your logo can link directly to your company’s website.' ),
				gat_b_h( 'Advertiser Showcase', 3 ) . gat_b_p( 'Participating businesses may be featured within the website’s advertiser section.' ),
			)
		)
		. gat_b_p( 'The available advertising options will depend on the agreed advertising package.' )
		. gat_b_h( 'How It Works' )
		. gat_b_list(
			array(
				'Contact Global Alliance Trust to enquire about advertising.',
				'Provide your company name, logo and website address.',
				'Agree the advertising package, duration and applicable fee.',
				'Following approval and any required payment, your advertisement can be added to the website.',
			),
			true,
			'gat-steps'
		)
		. gat_b_h( 'Become an Advertiser', 2, 'enquire' )
		. gat_b_p( 'We welcome enquiries from businesses interested in supporting community initiatives through advertising.' )
		. gat_b_sc( '[gat_enquiry_form type="advertising"]' ),
		'',
	);

	$pages['news-and-events'] = array( 'News & Events', 'Stay Connected With Our Community', '', '' );

	$pages['contact-us'] = array(
		'Contact Us',
		'We Would Love to Hear From You',
		gat_b_p( 'If you have a question about our services, educational programmes, donations, volunteering opportunities or advertising, please get in touch. Our team welcomes enquiries from community members, parents, volunteers, businesses and organisations.' )
		. gat_b_sc( '[gat_contact]' ),
		'',
	);

	$legal_note = gat_b_p( 'This page is being prepared and will be published once it has been approved by Global Alliance Trust.', 'gat-note' );
	$pages['privacy-policy']     = array( 'Privacy Policy', '', $legal_note, '' );
	$pages['website-terms']      = array( 'Website Terms & Conditions', '', $legal_note, '' );
	$pages['cookie-policy']      = array( 'Cookie Policy', '', $legal_note, '' );
	$pages['donation-terms']     = array( 'Donation Terms', '', $legal_note, '' );
	$pages['enrolment-terms']    = array( 'Enrolment Terms', '', $legal_note, '' );

	return $pages;
}

/* ---------------------------------------------------------------------------
 * Admin screen.
 * ------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_management_page( __( 'GAT Setup', 'gat-core' ), __( 'GAT Setup', 'gat-core' ), 'manage_options', 'gat-setup', 'gat_core_setup_page' );
	}
);

/**
 * Setup page.
 */
function gat_core_setup_page() {
	$log = array();
	if ( isset( $_POST['gat_setup_action'] ) && check_admin_referer( 'gat_setup' ) && current_user_can( 'manage_options' ) ) {
		$action = sanitize_key( wp_unslash( $_POST['gat_setup_action'] ) );
		if ( 'run' === $action ) {
			$log = gat_core_run_setup( ! empty( $_POST['gat_samples'] ) );
		} elseif ( 'remove_samples' === $action ) {
			$log = gat_core_remove_samples();
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'GAT Setup', 'gat-core' ); ?></h1>
		<?php if ( $log ) : ?>
			<div class="notice notice-success"><ul style="list-style:disc;padding-left:20px">
				<?php foreach ( $log as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Creates the website pages (with the approved content), the menus and the basic settings. Pages that already exist are left untouched, so this is safe to run again.', 'gat-core' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'gat_setup' ); ?>
			<input type="hidden" name="gat_setup_action" value="run">
			<p><label><input type="checkbox" name="gat_samples" value="1" checked> <?php esc_html_e( 'Also add sample news and sample advertisers (for previewing the design; remove before launch)', 'gat-core' ); ?></label></p>
			<?php submit_button( __( 'Set up the website', 'gat-core' ), 'primary', 'submit', false ); ?>
		</form>
		<hr>
		<form method="post">
			<?php wp_nonce_field( 'gat_setup' ); ?>
			<input type="hidden" name="gat_setup_action" value="remove_samples">
			<p><?php esc_html_e( 'Before launch: delete the sample news and sample advertisers (anything else is kept).', 'gat-core' ); ?></p>
			<?php submit_button( __( 'Remove sample content', 'gat-core' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Do the setup.
 *
 * @param bool $samples Add sample news/advertisers.
 * @return string[] log
 */
function gat_core_run_setup( $samples ) {
	$log = array();
	$ids = array();

	foreach ( gat_core_starter_pages() as $slug => list( $title, $excerpt, $content, $parent ) ) {
		$existing = get_page_by_path( $parent ? $parent . '/' . $slug : $slug );
		if ( ! $existing && 'privacy-policy' === $slug && (int) get_option( 'wp_page_for_privacy_policy' ) ) {
			$existing = get_post( (int) get_option( 'wp_page_for_privacy_policy' ) );
		}
		if ( $existing ) {
			$ids[ $slug ] = $existing->ID;
			if ( 'privacy-policy' === $slug && 'publish' !== $existing->post_status ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish', 'post_name' => 'privacy-policy', 'post_content' => $content ) );
			}
			/* translators: %s page title */
			$log[] = sprintf( __( 'Kept existing page: %s', 'gat-core' ), $title );
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
				'post_parent'  => $parent && isset( $ids[ $parent ] ) ? $ids[ $parent ] : 0,
				'menu_order'   => count( $ids ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $slug ] = $id;
			/* translators: %s page title */
			$log[] = sprintf( __( 'Created page: %s', 'gat-core' ), $title );
		}
	}

	// Remove WordPress's default "Sample Page" if it was never edited.
	$sample = get_page_by_path( 'sample-page' );
	if ( $sample && $sample->post_modified === $sample->post_date ) {
		wp_delete_post( $sample->ID, true );
		$log[] = __( 'Removed the default “Sample Page”.', 'gat-core' );
	}

	// Reading, permalinks, region.
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home'] );
	update_option( 'page_for_posts', $ids['news-and-events'] );
	update_option( 'wp_page_for_privacy_policy', $ids['privacy-policy'] );
	update_option( 'blogdescription', 'Serving the Community' );
	update_option( 'timezone_string', 'Europe/London' );
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'start_of_week', 1 );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'posts_per_page', 9 );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
	$log[] = __( 'Settings: homepage, news page, UK time zone and date format, comments off, tidy web addresses.', 'gat-core' );

	// Menus.
	$page_url = fn( $slug ) => 'page:' . $ids[ $slug ];
	$menus    = array(
		'primary' => array(
			'Main menu',
			array(
				array( 'Home', $page_url( 'home' ) ),
				array( 'About Us', $page_url( 'about-us' ), array( array( 'About GAT', $page_url( 'about-us' ) ), array( 'Management', $page_url( 'management' ) ), array( 'News & Events', $page_url( 'news-and-events' ) ) ) ),
				array(
					'Our Services',
					$page_url( 'our-services' ),
					array(
						array( 'All Services', $page_url( 'our-services' ) ),
						array( 'Community Services', '/our-services/#community-services' ),
						array( 'Elderly Services', '/our-services/#elderly-services' ),
						array( 'Women’s Services', '/our-services/#womens-services' ),
						array( 'Youth Services', '/our-services/#youth-services' ),
						array( 'Children’s Services', '/our-services/#childrens-services' ),
						array( 'Wellbeing & Counselling', '/our-services/#wellbeing-and-counselling' ),
						array( 'Legal & Advisory', '/our-services/#legal-and-advisory-services' ),
						array( 'Religious Activities', '/our-services/#religious-activities' ),
					),
				),
				array( 'Sunnah Academy / Maktab', $page_url( 'sunnah-academy' ), array( array( 'About the Academy', $page_url( 'sunnah-academy' ) ), array( 'How to Enrol', '/sunnah-academy/#how-to-enrol' ), array( 'Enrol Online', $page_url( 'enrol' ) ) ) ),
				array( 'Advertising', $page_url( 'advertising' ) ),
				array( 'Management', $page_url( 'management' ) ),
				array( 'Contact', $page_url( 'contact-us' ) ),
			),
		),
		'footer'  => array(
			'Footer quick links',
			array(
				array( 'Home', $page_url( 'home' ) ),
				array( 'Donate', $page_url( 'donate' ) ),
				array( 'About Us', $page_url( 'about-us' ) ),
				array( 'Advertising', $page_url( 'advertising' ) ),
				array( 'Our Services', $page_url( 'our-services' ) ),
				array( 'Management', $page_url( 'management' ) ),
				array( 'Sunnah Academy / Maktab', $page_url( 'sunnah-academy' ) ),
				array( 'News & Events', $page_url( 'news-and-events' ) ),
				array( 'Contact Us', $page_url( 'contact-us' ) ),
			),
		),
		'legal'   => array(
			'Footer legal links',
			array(
				array( 'Privacy Policy', $page_url( 'privacy-policy' ) ),
				array( 'Website Terms & Conditions', $page_url( 'website-terms' ) ),
				array( 'Cookie Policy', $page_url( 'cookie-policy' ) ),
				array( 'Donation Terms', $page_url( 'donation-terms' ) ),
				array( 'Enrolment Terms', $page_url( 'enrolment-terms' ) ),
			),
		),
	);
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( $menus as $loc => list( $name, $items ) ) {
		if ( ! empty( $locations[ $loc ] ) && wp_get_nav_menu_object( $locations[ $loc ] ) ) {
			/* translators: %s menu name */
			$log[] = sprintf( __( 'Kept existing menu: %s', 'gat-core' ), $name );
			continue;
		}
		$menu_obj = wp_get_nav_menu_object( $name );
		$menu_id  = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu( $name );
		if ( ! $menu_obj ) {
			foreach ( $items as $pos => $item ) {
				$parent_id = wp_update_nav_menu_item( $menu_id, 0, gat_core_menu_item_args( $item, $pos + 1 ) );
				foreach ( $item[2] ?? array() as $cpos => $child ) {
					wp_update_nav_menu_item( $menu_id, 0, gat_core_menu_item_args( $child, $cpos + 1, $parent_id ) );
				}
			}
		}
		$locations[ $loc ] = $menu_id;
		/* translators: %s menu name */
		$log[] = sprintf( __( 'Created menu: %s', 'gat-core' ), $name );
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	if ( $samples ) {
		$log = array_merge( $log, gat_core_add_samples() );
	}
	return $log;
}

/**
 * Menu item args: 'page:ID' links to the page itself (follows the page if its
 * address changes, and highlights the current page); anything else is a link.
 *
 * @param array $item   [title, target].
 * @param int   $pos    Position.
 * @param int   $parent Parent item ID.
 * @return array
 */
function gat_core_menu_item_args( $item, $pos, $parent = 0 ) {
	$args = array(
		'menu-item-title'     => $item[0],
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $pos,
		'menu-item-parent-id' => $parent,
	);
	if ( str_starts_with( $item[1], 'page:' ) ) {
		$args['menu-item-type']      = 'post_type';
		$args['menu-item-object']    = 'page';
		$args['menu-item-object-id'] = (int) substr( $item[1], 5 );
	} else {
		$args['menu-item-type'] = 'custom';
		$args['menu-item-url']  = $item[1];
	}
	return $args;
}

/**
 * Copy a bundled image into the media library.
 *
 * @param string $file    Filename in /starter.
 * @param int    $post_id Attach to.
 * @return int Attachment ID or 0.
 */
function gat_core_sideload_starter( $file, $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$src = GAT_CORE_DIR . 'starter/' . $file;
	if ( ! file_exists( $src ) ) {
		return 0;
	}
	$tmp = wp_tempnam( $file );
	copy( $src, $tmp );
	$id = media_handle_sideload( array( 'name' => 'sample-' . $file, 'tmp_name' => $tmp ), $post_id );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, '_gat_sample', 1 );
	return $id;
}

/**
 * Sample news + advertisers (marked so they can be removed in one click).
 *
 * @return string[]
 */
function gat_core_add_samples() {
	$log = array();
	if ( get_posts( array( 'post_type' => array( 'post', 'gat_advertiser' ), 'meta_key' => '_gat_sample', 'posts_per_page' => 1, 'post_status' => 'any' ) ) ) {
		return array( __( 'Sample content already exists; not added again.', 'gat-core' ) );
	}

	// Remove WordPress's "Hello world!" post.
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello ) {
		wp_delete_post( $hello->ID, true );
	}

	$cat = term_exists( 'Sample', 'category' );
	$cat = $cat ? (int) $cat['term_id'] : (int) wp_insert_term( 'Sample', 'category' )['term_id'];

	$news = array(
		array( 'Community Food Drive Brings Smiles to Local Families', 'Our latest food drive helped local families with essential groceries, thanks to the generosity of donors and volunteers.', 'news1.jpg', 3 ),
		array( 'New Education Programme Launches at Sunnah Academy', 'We are excited to announce a new programme at Sunnah Academy / Maktab for children and young people.', 'news2.jpg', 10 ),
		array( 'Volunteers Make a Difference in Medway', 'A big thank you to all our volunteers who give their time and support to the community.', 'news3.jpg', 18 ),
		array( 'Support Our Winter Appeal', 'Help us provide warmth and essential items to those in need this winter.', 'news4.jpg', 26 ),
	);
	foreach ( $news as list( $title, $text, $img, $days ) ) {
		$id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_excerpt'  => $text,
				'post_content'  => gat_b_p( 'Sample news item for previewing the website design. Replace or delete it before launch (Tools → GAT Setup → Remove sample content).', 'gat-note' ) . gat_b_p( $text ),
				'post_date'     => wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
				'post_category' => array( $cat ),
			)
		);
		update_post_meta( $id, '_gat_sample', 1 );
		$thumb = gat_core_sideload_starter( $img, $id );
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
		}
	}
	$log[] = __( 'Added 4 sample news items.', 'gat-core' );

	$ads = array( array( 'Al Rahma Halal Meat', 'partner1.jpg' ), array( 'Bright Future Education Centre', 'partner2.jpg' ), array( 'Medway Pharmacy', 'partner3.jpg' ), array( 'Al Rahma Halal Meat ', 'partner1.jpg' ), array( 'Bright Future Education Centre ', 'partner2.jpg' ), array( 'Medway Pharmacy ', 'partner3.jpg' ) );
	foreach ( $ads as $i => list( $name, $img ) ) {
		$id = wp_insert_post( array( 'post_type' => 'gat_advertiser', 'post_status' => 'publish', 'post_title' => trim( $name ) . ' (sample)', 'menu_order' => $i ) );
		update_post_meta( $id, '_gat_sample', 1 );
		$thumb = gat_core_sideload_starter( $img, $id );
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
		}
	}
	$log[] = __( 'Added 6 sample advertisers.', 'gat-core' );
	return $log;
}

/**
 * Delete everything marked as sample.
 *
 * @return string[]
 */
function gat_core_remove_samples() {
	$items = get_posts( array( 'post_type' => array( 'post', 'gat_advertiser', 'attachment' ), 'meta_key' => '_gat_sample', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $items as $id ) {
		if ( 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		} else {
			wp_delete_post( $id, true );
		}
	}
	$cat = get_category_by_slug( 'sample' );
	if ( $cat ) {
		wp_delete_category( $cat->term_id );
	}
	/* translators: %d count */
	return array( sprintf( __( 'Removed %d sample items.', 'gat-core' ), count( $items ) ) );
}

// Pages get an Excerpt box (used as the subtitle under the page title).
add_action( 'init', fn() => add_post_type_support( 'page', 'excerpt' ) );
