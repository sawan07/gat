<?php
/**
 * Sunnah Academy / Maktab online enrolment.
 *
 * [gat_enrolment_form]     Sections A–D from GAT's content document, then
 *                          "Continue to Payment" (Stripe) when a fee is set.
 * [gat_enrolment_result]   Thank-you page.
 *
 * Only the information the document lists is collected.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declarations (Section D), exactly as worded in the content document.
 *
 * @return array key => text
 */
function gat_enrol_declarations() {
	return array(
		'accurate' => __( 'I confirm that the information provided is accurate to the best of my knowledge.', 'gat-core' ),
		'terms'    => __( 'I have read the applicable enrolment terms and privacy information.', 'gat-core' ),
		'review'   => __( 'I understand that the application will be reviewed in accordance with the Academy’s enrolment arrangements.', 'gat-core' ),
	);
}

/**
 * Relationship options.
 *
 * @return string[]
 */
function gat_relationships() {
	return array( __( 'Mother', 'gat-core' ), __( 'Father', 'gat-core' ), __( 'Guardian', 'gat-core' ), __( 'Grandparent', 'gat-core' ), __( 'Other family member', 'gat-core' ), __( 'Other', 'gat-core' ) );
}

/**
 * Start Stripe Checkout for an application; returns the URL or WP_Error.
 *
 * @param array $row Student row.
 * @return string|WP_Error
 */
function gat_enrol_checkout( $row ) {
	$enrol   = gat_page_url( 'sunnah-academy/enrol' );
	$session = gat_stripe_checkout(
		array(
			'kind'        => 'enrolment',
			'ref'         => $row['ref'],
			'amount'      => (int) $row['fee_pence'],
			'name'        => sprintf( 'Sunnah Academy / Maktab enrolment fee: %s', $row['student_name'] ),
			'email'       => $row['email'],
			'success_url' => add_query_arg( 'ref', $row['ref'], gat_page_url( 'sunnah-academy/enrol/thank-you' ) ) . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'  => add_query_arg( array( 'gat_unpaid' => $row['ref'], 'k' => gat_ref_token( $row['ref'] ) ), $enrol ) . '#enrol-form',
		)
	);
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	if ( empty( $session['url'] ) ) {
		return new WP_Error( 'gat_stripe', 'no url' );
	}
	gat_update( 'students', $row['id'], array( 'stripe_session' => $session['id'] ) );
	return $session['url'];
}

/* ---------------------------------------------------------------------------
 * Submit / retry payment.
 * ------------------------------------------------------------------------- */
add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore
			return;
		}
		$action = $_POST['gat_action'] ?? ''; // phpcs:ignore

		// Retry payment for a saved application (from the "not completed" notice).
		if ( 'enrol_pay' === $action ) {
			$ref = sanitize_text_field( wp_unslash( $_POST['gat_ref'] ?? '' ) ); // phpcs:ignore
			$k   = sanitize_text_field( wp_unslash( $_POST['gat_k'] ?? '' ) ); // phpcs:ignore
			$row = $ref ? gat_get_by( 'students', 'ref', $ref ) : null;
			if ( $row && hash_equals( gat_ref_token( $ref ), $k ) && 'pending' === $row['payment_status'] && (int) $row['fee_pence'] > 0 ) {
				$url = gat_enrol_checkout( $row );
				if ( ! is_wp_error( $url ) ) {
					wp_redirect( $url, 303 ); // phpcs:ignore
					exit;
				}
			}
			wp_safe_redirect( gat_page_url( 'sunnah-academy/enrol' ) );
			exit;
		}

		if ( 'enrol' !== $action ) {
			return;
		}
		if ( gat_is_bot() ) {
			wp_safe_redirect( gat_page_url( 'sunnah-academy/enrol' ) );
			exit;
		}
		$v = array();
		$e = array();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gat_nonce'] ?? '' ) ), 'gat_enrol' ) ) { // phpcs:ignore
			$e['student_name'] = __( 'Your session expired. Please check the form and submit again.', 'gat-core' );
		}
		if ( ! gat_setting( 'enrol_open' ) ) {
			$e['student_name'] = __( 'Online enrolment is closed at the moment.', 'gat-core' );
		}

		// Section A.
		$v['student_name'] = gat_post( 'student_name' );
		$v['dob']          = gat_post( 'dob' );
		$v['address']      = gat_post( 'address', true );
		$v['postcode']     = strtoupper( gat_post( 'postcode' ) );
		$v['student_info'] = gat_post( 'student_info', true );
		if ( mb_strlen( $v['student_name'] ) < 3 ) {
			$e['student_name'] = __( 'Please enter the student’s full name.', 'gat-core' );
		}
		$dob = DateTime::createFromFormat( 'Y-m-d', $v['dob'] );
		if ( ! $dob || $dob->format( 'Y-m-d' ) !== $v['dob'] || $dob > new DateTime( 'today' ) || $dob < new DateTime( '-30 years' ) ) {
			$e['dob'] = __( 'Please enter a valid date of birth.', 'gat-core' );
		}
		if ( '' === $v['address'] ) {
			$e['address'] = __( 'Please enter the address.', 'gat-core' );
		}
		if ( ! gat_valid_postcode( $v['postcode'] ) ) {
			$e['postcode'] = __( 'Please enter a valid UK postcode.', 'gat-core' );
		}

		// Section B.
		$v['parent_name']     = gat_post( 'parent_name' );
		$v['relationship']    = gat_post( 'relationship' );
		$v['phone']           = gat_post( 'phone' );
		$v['email']           = sanitize_email( gat_post( 'email' ) );
		$v['emergency_name']  = gat_post( 'emergency_name' );
		$v['emergency_phone'] = gat_post( 'emergency_phone' );
		if ( mb_strlen( $v['parent_name'] ) < 3 ) {
			$e['parent_name'] = __( 'Please enter your full name.', 'gat-core' );
		}
		if ( ! in_array( $v['relationship'], gat_relationships(), true ) ) {
			$e['relationship'] = __( 'Please choose your relationship to the student.', 'gat-core' );
		}
		if ( ! gat_valid_phone( $v['phone'] ) ) {
			$e['phone'] = __( 'Please enter a valid telephone number.', 'gat-core' );
		}
		if ( ! is_email( $v['email'] ) ) {
			$e['email'] = __( 'Please enter a valid email address.', 'gat-core' );
		}
		if ( $v['emergency_phone'] && ! gat_valid_phone( $v['emergency_phone'] ) ) {
			$e['emergency_phone'] = __( 'Please enter a valid emergency telephone number.', 'gat-core' );
		}

		// Section C.
		$classes             = gat_setting_lines( 'enrol_classes' );
		$v['class_name']     = gat_post( 'class_name' );
		$v['start_date']     = gat_post( 'start_date' );
		$v['additional_info'] = gat_post( 'additional_info', true );
		if ( $classes ? ! in_array( $v['class_name'], $classes, true ) : '' === $v['class_name'] ) {
			$e['class_name'] = __( 'Please choose the programme or class.', 'gat-core' );
		}
		if ( $v['start_date'] ) {
			$sd = DateTime::createFromFormat( 'Y-m-d', $v['start_date'] );
			if ( ! $sd || $sd->format( 'Y-m-d' ) !== $v['start_date'] ) {
				$e['start_date'] = __( 'Please enter a valid start date.', 'gat-core' );
			}
		}

		// Section D.
		$decl = array();
		foreach ( gat_enrol_declarations() as $k => $text ) {
			$v[ 'decl_' . $k ] = empty( $_POST[ 'gat_decl_' . $k ] ) ? '' : '1'; // phpcs:ignore
			if ( ! $v[ 'decl_' . $k ] ) {
				$e[ 'decl_' . $k ] = __( 'Please confirm this declaration.', 'gat-core' );
			}
			$decl[ $k ] = $text;
		}

		if ( ! $e && gat_rate_limited( 'enrol', 6 ) ) {
			$e['student_name'] = __( 'Too many applications from this connection. Please try again later or contact us.', 'gat-core' );
		}
		if ( $e ) {
			gat_form_fail( 'enrol', $e, $v );
			return;
		}

		$fee = gat_enrol_fee_pence();
		$row = gat_insert(
			'students',
			array(
				'status'          => 'pending',
				'payment_status'  => $fee > 0 ? 'pending' : 'not_required',
				'fee_pence'       => $fee,
				'student_name'    => $v['student_name'],
				'dob'             => $v['dob'],
				'address'         => $v['address'],
				'postcode'        => $v['postcode'],
				'student_info'    => $v['student_info'],
				'parent_name'     => $v['parent_name'],
				'relationship'    => $v['relationship'],
				'phone'           => $v['phone'],
				'email'           => $v['email'],
				'emergency_name'  => $v['emergency_name'],
				'emergency_phone' => $v['emergency_phone'],
				'class_name'      => $v['class_name'],
				'start_date'      => $v['start_date'] ?: null,
				'additional_info' => $v['additional_info'],
				'declarations'    => wp_json_encode( array( 'accepted_at' => current_time( 'mysql' ), 'statements' => $decl ) ),
			)
		);
		if ( ! $row ) {
			gat_form_fail( 'enrol', array( 'student_name' => __( 'Sorry, something went wrong saving your application. Please try again.', 'gat-core' ) ), $v );
			return;
		}

		$thanks = add_query_arg( 'ref', $row['ref'], gat_page_url( 'sunnah-academy/enrol/thank-you' ) );
		if ( $fee > 0 && gat_stripe_enabled() ) {
			$url = gat_enrol_checkout( $row );
			if ( ! is_wp_error( $url ) ) {
				wp_redirect( $url, 303 ); // phpcs:ignore
				exit;
			}
			gat_update( 'students', $row['id'], array( 'notes' => 'Online payment could not be started: ' . $url->get_error_message() ) );
		}
		// No fee, or payment unavailable: the application is saved and staff are told.
		gat_enrolment_emails( $row );
		wp_safe_redirect( add_query_arg( 'k', gat_ref_token( $row['ref'] ), $thanks ) );
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * The form.
 * ------------------------------------------------------------------------- */
add_shortcode(
	'gat_enrolment_form',
	function () {
		$state   = gat_form_state( 'enrol' );
		$v       = $state['values'];
		$fee     = gat_enrol_fee_pence();
		$classes = gat_setting_lines( 'enrol_classes' );
		ob_start();
		echo '<div id="enrol-form" class="gat-enrol">';

		// Returned from Stripe without paying.
		$unpaid = sanitize_text_field( wp_unslash( $_GET['gat_unpaid'] ?? '' ) ); // phpcs:ignore
		$k      = sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) ); // phpcs:ignore
		if ( $unpaid && hash_equals( gat_ref_token( $unpaid ), $k ) ) {
			$row = gat_get_by( 'students', 'ref', $unpaid );
			if ( $row && 'pending' === $row['payment_status'] ) {
				?>
				<div class="gat-alert gat-alert--info" role="status">
					<p><strong><?php esc_html_e( 'Your payment was not completed.', 'gat-core' ); ?></strong>
					<?php
					/* translators: 1 ref 2 student */
					echo esc_html( sprintf( __( 'Your application %1$s for %2$s has been saved. Please complete the enrolment fee payment to finish.', 'gat-core' ), $row['ref'], $row['student_name'] ) );
					?></p>
					<form method="post" action="">
						<input type="hidden" name="gat_action" value="enrol_pay">
						<input type="hidden" name="gat_ref" value="<?php echo esc_attr( $row['ref'] ); ?>">
						<input type="hidden" name="gat_k" value="<?php echo esc_attr( $k ); ?>">
						<button class="btn btn--primary" type="submit">
							<?php
							/* translators: %s amount */
							echo esc_html( sprintf( __( 'Pay %s now', 'gat-core' ), gat_money( $row['fee_pence'] ) ) );
							?>
						</button>
					</form>
				</div>
				<?php
				echo '</div>';
				return ob_get_clean();
			}
		}

		if ( ! gat_setting( 'enrol_open' ) ) {
			echo '<div class="gat-alert gat-alert--info">' . esc_html__( 'Online enrolment is closed at the moment. Please contact us for information about places.', 'gat-core' ) . '</div></div>';
			return ob_get_clean();
		}

		gat_error_summary( $state );
		?>
		<p><?php esc_html_e( 'Please complete the form below to apply for enrolment at Sunnah Academy / Maktab. Boxes marked (optional) can be left empty.', 'gat-core' ); ?></p>
		<form class="gat-form gat-form--enrol" method="post" action="#enrol-form" novalidate>
			<input type="hidden" name="gat_action" value="enrol">
			<?php wp_nonce_field( 'gat_enrol', 'gat_nonce' ); ?>
			<?php gat_honeypot(); ?>

			<fieldset class="gat-section">
				<legend><span class="num">A</span> <?php esc_html_e( 'Student details', 'gat-core' ); ?></legend>
				<div class="row">
					<?php gat_field( 'student_name', __( 'Student full name', 'gat-core' ), array( 'required' => true ), $state ); ?>
					<?php gat_field( 'dob', __( 'Date of birth', 'gat-core' ), array( 'type' => 'date', 'required' => true, 'attrs' => array( 'max' => wp_date( 'Y-m-d' ) ) ), $state ); ?>
				</div>
				<?php gat_field( 'address', __( 'Address', 'gat-core' ), array( 'type' => 'textarea', 'required' => true, 'attrs' => array( 'rows' => 3, 'autocomplete' => 'street-address' ) ), $state ); ?>
				<?php gat_field( 'postcode', __( 'Postcode', 'gat-core' ), array( 'required' => true, 'class' => 'short', 'attrs' => array( 'autocomplete' => 'postal-code' ) ), $state ); ?>
				<?php gat_field( 'student_info', __( 'Other information the Academy should know', 'gat-core' ), array( 'type' => 'textarea', 'hint' => __( 'For example, medical needs or allergies, or previous Qur’an / Islamic studies learning.', 'gat-core' ), 'attrs' => array( 'rows' => 3 ) ), $state ); ?>
			</fieldset>

			<fieldset class="gat-section">
				<legend><span class="num">B</span> <?php esc_html_e( 'Parent / guardian details', 'gat-core' ); ?></legend>
				<div class="row">
					<?php gat_field( 'parent_name', __( 'Full name', 'gat-core' ), array( 'required' => true, 'attrs' => array( 'autocomplete' => 'name' ) ), $state ); ?>
					<?php gat_field( 'relationship', __( 'Relationship to student', 'gat-core' ), array( 'type' => 'select', 'required' => true, 'options' => gat_relationships() ), $state ); ?>
				</div>
				<div class="row">
					<?php gat_field( 'phone', __( 'Telephone number', 'gat-core' ), array( 'type' => 'tel', 'required' => true, 'attrs' => array( 'autocomplete' => 'tel' ) ), $state ); ?>
					<?php gat_field( 'email', __( 'Email address', 'gat-core' ), array( 'type' => 'email', 'required' => true, 'attrs' => array( 'autocomplete' => 'email' ) ), $state ); ?>
				</div>
				<div class="row">
					<?php gat_field( 'emergency_name', __( 'Emergency contact name', 'gat-core' ), array(), $state ); ?>
					<?php gat_field( 'emergency_phone', __( 'Emergency contact telephone', 'gat-core' ), array( 'type' => 'tel' ), $state ); ?>
				</div>
			</fieldset>

			<fieldset class="gat-section">
				<legend><span class="num">C</span> <?php esc_html_e( 'Enrolment details', 'gat-core' ); ?></legend>
				<div class="row">
					<?php
					if ( $classes ) {
						gat_field( 'class_name', __( 'Programme or class', 'gat-core' ), array( 'type' => 'select', 'required' => true, 'options' => $classes ), $state );
					} else {
						gat_field( 'class_name', __( 'Programme or class', 'gat-core' ), array( 'required' => true, 'hint' => __( 'Which programme or class are you applying for?', 'gat-core' ) ), $state );
					}
					gat_field( 'start_date', __( 'Preferred start date', 'gat-core' ), array( 'type' => 'date', 'attrs' => array( 'min' => wp_date( 'Y-m-d' ) ) ), $state );
					?>
				</div>
				<?php gat_field( 'additional_info', __( 'Additional information relevant to the application', 'gat-core' ), array( 'type' => 'textarea', 'attrs' => array( 'rows' => 3 ) ), $state ); ?>
			</fieldset>

			<fieldset class="gat-section">
				<legend><span class="num">D</span> <?php esc_html_e( 'Declarations', 'gat-core' ); ?></legend>
				<?php foreach ( gat_enrol_declarations() as $k => $text ) : ?>
					<?php $err = $state['errors'][ 'decl_' . $k ] ?? ''; ?>
					<label class="check<?php echo $err ? ' has-error' : ''; ?>" id="gat_decl_<?php echo esc_attr( $k ); ?>-wrap">
						<input type="checkbox" id="gat_decl_<?php echo esc_attr( $k ); ?>" name="gat_decl_<?php echo esc_attr( $k ); ?>" value="1" required <?php checked( ! empty( $v[ 'decl_' . $k ] ) ); ?>>
						<span><?php echo esc_html( $text ); ?>
						<?php if ( 'terms' === $k ) : ?>
							(<a href="<?php echo esc_url( gat_page_url( 'enrolment-terms' ) ); ?>" target="_blank"><?php esc_html_e( 'Enrolment terms', 'gat-core' ); ?></a> · <a href="<?php echo esc_url( get_privacy_policy_url() ?: gat_page_url( 'privacy-policy' ) ); ?>" target="_blank"><?php esc_html_e( 'Privacy information', 'gat-core' ); ?></a>)
						<?php endif; ?>
						</span>
					</label>
					<?php if ( $err ) : ?><p class="error"><?php echo esc_html( $err ); ?></p><?php endif; ?>
				<?php endforeach; ?>
			</fieldset>

			<?php if ( $fee > 0 ) : ?>
				<div class="gat-pay-summary">
					<span><?php esc_html_e( 'Enrolment fee', 'gat-core' ); ?></span>
					<strong><?php echo esc_html( gat_money( $fee ) ); ?></strong>
				</div>
				<p class="gat-small"><?php esc_html_e( 'You will be taken to Stripe’s secure page to pay. We never see your card details.', 'gat-core' ); ?></p>
				<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Continue to Payment', 'gat-core' ); ?></button>
			<?php else : ?>
				<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Submit Application', 'gat-core' ); ?></button>
			<?php endif; ?>
		</form>
		<script>(function(){var e=document.getElementById('gat-errors');if(e)e.focus();})();</script>
		<?php
		echo '</div>';
		return ob_get_clean();
	}
);

/* ---------------------------------------------------------------------------
 * Thank-you page (wording from the content document).
 * ------------------------------------------------------------------------- */
add_shortcode(
	'gat_enrolment_result',
	function () {
		$ref = sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) ); // phpcs:ignore
		$sid = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) ); // phpcs:ignore
		$k   = sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) ); // phpcs:ignore
		$row = $ref ? gat_get_by( 'students', 'ref', $ref ) : null;
		$ok  = $row && ( ( $sid && $row['stripe_session'] === $sid ) || ( $k && hash_equals( gat_ref_token( $ref ), $k ) ) );

		if ( $ok && $sid && 'pending' === $row['payment_status'] ) {
			$s = gat_stripe_request( 'GET', 'checkout/sessions/' . rawurlencode( $sid ) );
			if ( ! is_wp_error( $s ) && ( $s['client_reference_id'] ?? '' ) === $row['ref'] ) {
				gat_apply_session( $s, 'return' );
				$row = gat_get( 'students', $row['id'] );
			}
		}

		ob_start();
		echo '<div class="gat-result gat-result--ok">';
		echo '<h2>' . esc_html__( 'Thank You for Your Application', 'gat-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Your enrolment application has been submitted.', 'gat-core' ) . '</p>';
		if ( $ok ) {
			/* translators: %s ref */
			echo '<p class="gat-ref">' . esc_html( sprintf( __( 'Application reference: %s', 'gat-core' ), $row['ref'] ) ) . '</p>';
			if ( 'paid' === $row['payment_status'] ) {
				/* translators: %s amount */
				echo '<p>' . esc_html( sprintf( __( 'Enrolment fee of %s received. Thank you.', 'gat-core' ), gat_money( $row['fee_pence'] ) ) ) . '</p>';
			} elseif ( 'pending' === $row['payment_status'] && $sid ) {
				echo '<p>' . esc_html__( 'We are confirming your payment. This usually takes a few moments.', 'gat-core' ) . '</p>';
			} elseif ( 'pending' === $row['payment_status'] ) {
				echo '<p>' . esc_html__( 'The Academy will contact you about paying the enrolment fee.', 'gat-core' ) . '</p>';
			}
		}
		echo '<p>' . esc_html__( 'Once your payment and application have been processed, you will receive confirmation using the contact details provided.', 'gat-core' ) . '</p>';
		echo '<p>' . esc_html__( 'The Academy will contact you if further information is required.', 'gat-core' ) . '</p>';
		echo '</div>';
		return ob_get_clean();
	}
);

/**
 * Emails when an application is submitted (no fee) or its fee is paid.
 *
 * @param array $s Student row.
 */
function gat_enrolment_emails( $s ) {
	if ( ! $s ) {
		return;
	}
	$pay = gat_labels()['payment'][ $s['payment_status'] ] ?? $s['payment_status'];
	if ( $s['email'] ) {
		gat_mail(
			$s['email'],
			/* translators: %s ref */
			sprintf( __( 'Sunnah Academy / Maktab application received (%s)', 'gat-core' ), $s['ref'] ),
			array(
				/* translators: %s name */
				sprintf( __( 'Dear %s,', 'gat-core' ), $s['parent_name'] ),
				/* translators: 1 student 2 class */
				sprintf( __( 'Thank you. We have received the enrolment application for %1$s (%2$s).', 'gat-core' ), $s['student_name'], $s['class_name'] ),
				/* translators: 1 ref 2 payment */
				sprintf( __( 'Application reference: %1$s · Enrolment fee: %2$s', 'gat-core' ), $s['ref'], (int) $s['fee_pence'] > 0 ? gat_money( $s['fee_pence'] ) . ' (' . $pay . ')' : $pay ),
				__( 'The Academy will review the application and contact you about the next steps. Payment does not automatically guarantee a place.', 'gat-core' ),
			)
		);
	}
	gat_mail(
		gat_notify_email( 'academy_email' ),
		/* translators: 1 ref 2 student */
		sprintf( __( 'New Sunnah Academy application %1$s: %2$s', 'gat-core' ), $s['ref'], $s['student_name'] ),
		array(
			/* translators: 1 class 2 payment */
			sprintf( __( 'Class: %1$s · Payment: %2$s', 'gat-core' ), $s['class_name'], $pay ),
			__( 'Review it in the admin panel (Students):', 'gat-core' ) . ' ' . admin_url( 'admin.php?page=gat-students&view=' . $s['id'] ),
		)
	);
}
