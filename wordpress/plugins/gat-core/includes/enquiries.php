<?php
/**
 * Website enquiry forms (Contact Us and Advertising). Each enquiry is saved
 * under "Enquiries" in the admin panel and emailed to GAT.
 *
 * Shortcode: [gat_enquiry_form type="contact"] or [gat_enquiry_form type="advertising"]
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Form definitions.
 *
 * @return array
 */
function gat_core_enquiry_forms() {
	return array(
		'contact'     => array(
			'label'  => __( 'Contact', 'gat-core' ),
			'button' => __( 'Send Enquiry', 'gat-core' ),
			'fields' => array(
				'name'    => array( __( 'Full name', 'gat-core' ), 'text', true ),
				'email'   => array( __( 'Email address', 'gat-core' ), 'email', true ),
				'phone'   => array( __( 'Telephone number', 'gat-core' ), 'tel', false ),
				'topic'   => array(
					__( 'Enquiry type', 'gat-core' ),
					'select',
					true,
					array(
						__( 'General enquiry', 'gat-core' ),
						__( 'Sunnah Academy / Maktab', 'gat-core' ),
						__( 'Our services', 'gat-core' ),
						__( 'Donations', 'gat-core' ),
						__( 'Volunteering', 'gat-core' ),
						__( 'Advertising', 'gat-core' ),
						__( 'Other', 'gat-core' ),
					),
				),
				'subject' => array( __( 'Subject', 'gat-core' ), 'text', true ),
				'message' => array( __( 'Message', 'gat-core' ), 'textarea', true ),
			),
		),
		'advertising' => array(
			'label'  => __( 'Advertising', 'gat-core' ),
			'button' => __( 'Submit Enquiry', 'gat-core' ),
			'fields' => array(
				'company'      => array( __( 'Company name', 'gat-core' ), 'text', true ),
				'name'         => array( __( 'Contact person’s name', 'gat-core' ), 'text', true ),
				'email'        => array( __( 'Email address', 'gat-core' ), 'email', true ),
				'phone'        => array( __( 'Telephone number', 'gat-core' ), 'tel', true ),
				'website'      => array( __( 'Company website', 'gat-core' ), 'url', false ),
				'requirements' => array( __( 'Advertising requirements', 'gat-core' ), 'textarea', true ),
				'message'      => array( __( 'Additional message', 'gat-core' ), 'textarea', false ),
			),
		),
	);
}

/**
 * Register the Enquiries post type (admin only, never public).
 */
function gat_core_register_enquiries() {
	register_post_type(
		'gat_enquiry',
		array(
			'labels'       => array(
				'name'          => __( 'Enquiries', 'gat-core' ),
				'singular_name' => __( 'Enquiry', 'gat-core' ),
				'all_items'     => __( 'All enquiries', 'gat-core' ),
				'edit_item'     => __( 'Enquiry', 'gat-core' ),
				'search_items'  => __( 'Search enquiries', 'gat-core' ),
				'not_found'     => __( 'No enquiries yet', 'gat-core' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => false,
			'menu_position' => 24,
			'menu_icon'    => 'dashicons-email',
			'supports'     => array( 'title' ),
			'map_meta_cap' => true,
			// Primitive capabilities only: WordPress maps edit_post/read_post/
			// delete_post onto these itself (map_meta_cap).
			'capabilities' => array(
				'create_posts'           => 'do_not_allow',
				'edit_posts'             => 'gat_manage_enquiries',
				'edit_private_posts'     => 'gat_manage_enquiries',
				'delete_private_posts'   => 'gat_manage_enquiries',
				'edit_others_posts'      => 'gat_manage_enquiries',
				'delete_posts'           => 'gat_manage_enquiries',
				'delete_others_posts'    => 'gat_manage_enquiries',
				'publish_posts'          => 'gat_manage_enquiries',
				'read_private_posts'     => 'gat_manage_enquiries',
				'delete_published_posts' => 'gat_manage_enquiries',
				'edit_published_posts'   => 'gat_manage_enquiries',
			),
		)
	);
}
add_action( 'init', 'gat_core_register_enquiries' );

// Read-only view of an enquiry.
add_action(
	'add_meta_boxes_gat_enquiry',
	function () {
		remove_meta_box( 'submitdiv', 'gat_enquiry', 'side' );
		add_meta_box( 'gat_enquiry_view', __( 'Enquiry details', 'gat-core' ), 'gat_core_enquiry_box', 'gat_enquiry', 'normal', 'high' );
	}
);
add_action(
	'admin_head-post.php',
	function () {
		if ( 'gat_enquiry' === get_post_type() ) {
			echo '<style>#titlediv{display:none}</style>';
		}
	}
);

/**
 * Meta box.
 *
 * @param WP_Post $post Post.
 */
function gat_core_enquiry_box( $post ) {
	$type  = get_post_meta( $post->ID, '_gat_type', true );
	$forms = gat_core_enquiry_forms();
	$def   = $forms[ $type ] ?? $forms['contact'];
	echo '<table class="widefat striped"><tbody>';
	printf( '<tr><th style="width:200px">%s</th><td>%s</td></tr>', esc_html__( 'Form', 'gat-core' ), esc_html( $def['label'] ) );
	printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Received', 'gat-core' ), esc_html( get_the_date( 'j F Y, H:i', $post ) ) );
	foreach ( $def['fields'] as $key => $f ) {
		$val = (string) get_post_meta( $post->ID, '_gat_' . $key, true );
		if ( 'email' === $key && $val ) {
			$val_html = '<a href="mailto:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>';
		} elseif ( 'phone' === $key && $val ) {
			$val_html = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $val ) ) . '">' . esc_html( $val ) . '</a>';
		} elseif ( 'website' === $key && $val ) {
			$val_html = '<a href="' . esc_url( $val ) . '" target="_blank" rel="noopener">' . esc_html( $val ) . '</a>';
		} else {
			$val_html = $val ? nl2br( esc_html( $val ) ) : '—';
		}
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $f[0] ), $val_html ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	printf(
		'<p><a class="button" href="%s">%s</a></p>',
		esc_url( admin_url( 'edit.php?post_type=gat_enquiry' ) ),
		esc_html__( '← Back to all enquiries', 'gat-core' )
	);
}

add_filter(
	'manage_gat_enquiry_posts_columns',
	fn( $cols ) => array(
		'cb'        => $cols['cb'],
		'title'     => __( 'Enquiry', 'gat-core' ),
		'gat_type'  => __( 'Form', 'gat-core' ),
		'gat_email' => __( 'Email', 'gat-core' ),
		'date'      => __( 'Received', 'gat-core' ),
	)
);
add_action(
	'manage_gat_enquiry_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'gat_type' === $col ) {
			$forms = gat_core_enquiry_forms();
			$type  = get_post_meta( $post_id, '_gat_type', true );
			echo esc_html( $forms[ $type ]['label'] ?? $type );
		} elseif ( 'gat_email' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_gat_email', true ) );
		}
	},
	10,
	2
);
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'gat_enquiry' === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = str_replace( '>' . __( 'Edit' ) . '<', '>' . __( 'View', 'gat-core' ) . '<', $actions['edit'] );
			}
		}
		return $actions;
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * Handling submissions.
 * ------------------------------------------------------------------------- */

$GLOBALS['gat_form_state'] = array();

add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['gat_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$type  = sanitize_key( wp_unslash( $_POST['gat_form'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$forms = gat_core_enquiry_forms();
		if ( ! isset( $forms[ $type ] ) ) {
			return;
		}
		$state = array( 'type' => $type, 'errors' => array(), 'values' => array() );

		if ( ! isset( $_POST['gat_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gat_nonce'] ) ), 'gat_form_' . $type ) ) {
			$state['errors'][] = __( 'Your session expired. Please try again.', 'gat-core' );
		}

		// Honeypot: bots fill the hidden field; pretend success.
		if ( ! empty( $_POST['gat_website_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'gat_sent', $type, gat_core_form_page_url() ) . '#gat-form' );
			exit;
		}

		foreach ( $forms[ $type ]['fields'] as $key => $f ) {
			$raw = isset( $_POST[ 'gat_' . $key ] ) ? wp_unslash( $_POST[ 'gat_' . $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$val = 'textarea' === $f[1] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$val = mb_substr( $val, 0, 'textarea' === $f[1] ? 5000 : 200 );
			$state['values'][ $key ] = $val;
			if ( $f[2] && '' === trim( $val ) ) {
				/* translators: %s field label */
				$state['errors'][] = sprintf( __( 'Please fill in: %s', 'gat-core' ), $f[0] );
			} elseif ( 'email' === $f[1] && $val && ! is_email( $val ) ) {
				$state['errors'][] = __( 'Please enter a valid email address.', 'gat-core' );
			} elseif ( 'url' === $f[1] && $val && ! wp_http_validate_url( preg_match( '#^https?://#i', $val ) ? $val : 'https://' . $val ) ) {
				$state['errors'][] = __( 'Please enter a valid website address.', 'gat-core' );
			} elseif ( 'select' === $f[1] && $val && ! in_array( $val, $f[3], true ) ) {
				$state['errors'][] = __( 'Please choose an enquiry type.', 'gat-core' );
			}
		}
		if ( empty( $_POST['gat_consent'] ) ) {
			$state['errors'][] = __( 'Please confirm we can use your details to reply to your enquiry.', 'gat-core' );
		}

		// Simple rate limit: 5 enquiries per hour per visitor.
		$ip_key = 'gat_rl_' . md5( (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 5 ) {
			$state['errors'][] = __( 'You have sent several enquiries recently. Please try again later or contact us by phone or email.', 'gat-core' );
		}

		if ( $state['errors'] ) {
			$GLOBALS['gat_form_state'] = $state;
			return;
		}
		set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

		$v     = $state['values'];
		$title = 'advertising' === $type
			? sprintf( '%s (%s)', $v['company'], $v['name'] )
			: sprintf( '%s: %s', $v['name'], $v['subject'] );
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'gat_enquiry',
				'post_status' => 'private',
				'post_title'  => wp_strip_all_tags( $title ),
			)
		);
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_gat_type', $type );
			foreach ( $v as $k => $val ) {
				update_post_meta( $post_id, '_gat_' . $k, $val );
			}
		}

		// Email notification to GAT.
		$to = get_option( 'gat_enquiry_email' ) ?: get_theme_mod( 'gat_contact_email' ) ?: get_option( 'admin_email' );
		$lines = array();
		foreach ( $forms[ $type ]['fields'] as $k => $f ) {
			$lines[] = $f[0] . ': ' . ( '' !== $v[ $k ] ? $v[ $k ] : '-' );
		}
		$lines[] = '';
		$lines[] = __( 'View all enquiries:', 'gat-core' ) . ' ' . admin_url( 'edit.php?post_type=gat_enquiry' );
		wp_mail(
			$to,
			/* translators: 1 form name, 2 title */
			sprintf( __( 'New %1$s enquiry: %2$s', 'gat-core' ), strtolower( $forms[ $type ]['label'] ), $title ),
			implode( "\n", $lines ),
			array( 'Reply-To: ' . $v['name'] . ' <' . $v['email'] . '>' )
		);

		wp_safe_redirect( add_query_arg( 'gat_sent', $type, gat_core_form_page_url() ) . '#gat-form' );
		exit;
	}
);

add_shortcode(
	'gat_enquiry_form',
	function ( $atts ) {
		$atts  = shortcode_atts( array( 'type' => 'contact' ), $atts );
		$type  = sanitize_key( $atts['type'] );
		$forms = gat_core_enquiry_forms();
		if ( ! isset( $forms[ $type ] ) ) {
			return '';
		}
		$def   = $forms[ $type ];
		$state = $GLOBALS['gat_form_state'];
		$mine  = isset( $state['type'] ) && $state['type'] === $type;
		$vals  = $mine ? $state['values'] : array();

		ob_start();
		echo '<div id="gat-form">';
		if ( isset( $_GET['gat_sent'] ) && $type === $_GET['gat_sent'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="gat-alert gat-alert--ok" role="status"><strong>' . esc_html__( 'Thank you, your enquiry has been sent.', 'gat-core' ) . '</strong> ' . esc_html__( 'We will get back to you as soon as possible.', 'gat-core' ) . '</div>';
		}
		if ( $mine && $state['errors'] ) {
			echo '<div class="gat-alert gat-alert--err" role="alert"><strong>' . esc_html__( 'Please check the form:', 'gat-core' ) . '</strong><ul>';
			foreach ( $state['errors'] as $e ) {
				echo '<li>' . esc_html( $e ) . '</li>';
			}
			echo '</ul></div>';
		}
		?>
		<form class="gat-form" method="post" action="#gat-form" novalidate>
			<input type="hidden" name="gat_form" value="<?php echo esc_attr( $type ); ?>">
			<?php wp_nonce_field( 'gat_form_' . $type, 'gat_nonce' ); ?>
			<div class="gat-hp" aria-hidden="true"><label>Website <input type="text" name="gat_website_hp" tabindex="-1" autocomplete="off"></label></div>
			<?php
			$fields = $def['fields'];
			$keys   = array_keys( $fields );
			$i      = 0;
			while ( $i < count( $keys ) ) {
				$key  = $keys[ $i ];
				$f    = $fields[ $key ];
				$pair = ( 'textarea' !== $f[1] && isset( $keys[ $i + 1 ] ) && 'textarea' !== $fields[ $keys[ $i + 1 ] ][1] );
				if ( $pair ) {
					echo '<div class="row">';
					gat_core_render_field( $key, $f, $vals );
					gat_core_render_field( $keys[ $i + 1 ], $fields[ $keys[ $i + 1 ] ], $vals );
					echo '</div>';
					$i += 2;
				} else {
					gat_core_render_field( $key, $f, $vals );
					++$i;
				}
			}
			?>
			<label class="consent"><input type="checkbox" name="gat_consent" value="1" <?php checked( ! empty( $_POST['gat_consent'] ) ); // phpcs:ignore ?>> <span><?php esc_html_e( 'I agree that Global Alliance Trust can use these details to reply to my enquiry.', 'gat-core' ); ?></span></label>
			<button type="submit" class="btn btn--primary"><?php echo esc_html( $def['button'] ); ?></button>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}
);

/**
 * The page the form is on (to come back to after sending).
 *
 * @return string
 */
function gat_core_form_page_url() {
	$id = get_queried_object_id();
	return $id ? get_permalink( $id ) : home_url( '/' );
}

/**
 * Render one field.
 *
 * @param string $key  Key.
 * @param array  $f    Definition.
 * @param array  $vals Previous values.
 */
function gat_core_render_field( $key, $f, $vals ) {
	list( $label, $type, $required ) = $f;
	$id  = 'gat_' . $key;
	$val = $vals[ $key ] ?? '';
	echo '<div class="field">';
	printf(
		'<label for="%1$s">%2$s%3$s</label>',
		esc_attr( $id ),
		esc_html( $label ),
		$required ? '' : ' <span class="opt">' . esc_html__( '(optional)', 'gat-core' ) . '</span>'
	);
	$req = $required ? ' required' : '';
	if ( 'textarea' === $type ) {
		printf( '<textarea id="%1$s" name="%1$s"%2$s>%3$s</textarea>', esc_attr( $id ), $req, esc_textarea( $val ) ); // phpcs:ignore
	} elseif ( 'select' === $type ) {
		printf( '<select id="%1$s" name="%1$s"%2$s><option value="">%3$s</option>', esc_attr( $id ), $req, esc_html__( 'Please choose…', 'gat-core' ) ); // phpcs:ignore
		foreach ( $f[3] as $opt ) {
			printf( '<option%s>%s</option>', selected( $val, $opt, false ), esc_html( $opt ) );
		}
		echo '</select>';
	} else {
		$auto = array( 'name' => 'name', 'email' => 'email', 'phone' => 'tel', 'company' => 'organization', 'website' => 'url' )[ $key ] ?? 'off';
		printf(
			'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" autocomplete="%4$s"%5$s>',
			esc_attr( 'url' === $type ? 'text' : $type ),
			esc_attr( $id ),
			esc_attr( $val ),
			esc_attr( $auto ),
			$req // phpcs:ignore
		);
	}
	echo '</div>';
}
