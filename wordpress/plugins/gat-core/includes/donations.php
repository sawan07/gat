<?php
/**
 * Online donations.
 *
 * [gat_donate_form]        The donation form (Donate page).
 * [gat_donation_result]    Thank-you page after Stripe.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suggested amounts (whole pounds).
 *
 * @return int[]
 */
function gat_donate_amounts() {
	return array_values( array_filter( array_map( 'absint', explode( ',', (string) gat_setting( 'donate_amounts' ) ) ) ) );
}

/* ---------------------------------------------------------------------------
 * Submit → record → Stripe Checkout.
 * ------------------------------------------------------------------------- */
add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ( $_POST['gat_action'] ?? '' ) !== 'donate' ) { // phpcs:ignore
			return;
		}
		$back = get_permalink( get_queried_object_id() ) ?: gat_page_url( 'donate' );
		if ( gat_is_bot() ) {
			wp_safe_redirect( $back );
			exit;
		}

		$v      = array();
		$errors = array();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gat_nonce'] ?? '' ) ), 'gat_donate' ) ) { // phpcs:ignore
			$errors['amount'] = __( 'Your session expired. Please try again.', 'gat-core' );
		}

		$choice      = gat_post( 'amount_choice' );
		$v['amount_choice'] = $choice;
		$v['amount_other']  = gat_post( 'amount_other' );
		$pounds      = 'other' === $choice ? (float) str_replace( array( '£', ',' ), '', $v['amount_other'] ) : (float) $choice;
		$pence       = (int) round( $pounds * 100 );
		if ( $pence < 100 ) {
			$errors['amount_other'] = __( 'Please choose or enter an amount of at least £1.', 'gat-core' );
		} elseif ( $pence > 1000000 ) {
			$errors['amount_other'] = __( 'For donations over £10,000 please contact us.', 'gat-core' );
		}

		$v['frequency'] = 'monthly' === gat_post( 'frequency' ) && gat_setting( 'donate_monthly' ) ? 'monthly' : 'one_off';
		$causes         = gat_setting_lines( 'donate_causes' );
		$v['cause']     = gat_post( 'cause' );
		if ( $causes && ! in_array( $v['cause'], $causes, true ) ) {
			$v['cause'] = $causes[0];
		}
		$v['name']  = gat_post( 'name' );
		$v['email'] = sanitize_email( gat_post( 'email' ) );
		if ( '' === $v['name'] ) {
			$errors['name'] = __( 'Please enter your name.', 'gat-core' );
		}
		if ( ! is_email( $v['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address for your receipt.', 'gat-core' );
		}
		$v['anonymous']  = empty( $_POST['gat_anonymous'] ) ? '' : '1'; // phpcs:ignore
		$v['dedication'] = gat_post( 'dedication' );
		$v['gift_aid']   = gat_setting( 'gift_aid' ) && ! empty( $_POST['gat_gift_aid'] ) ? '1' : ''; // phpcs:ignore
		$v['address']    = gat_post( 'address', true );
		$v['postcode']   = strtoupper( gat_post( 'postcode' ) );
		if ( $v['gift_aid'] ) {
			if ( '' === $v['address'] ) {
				$errors['address'] = __( 'For Gift Aid, please enter your home address.', 'gat-core' );
			}
			if ( ! gat_valid_postcode( $v['postcode'] ) ) {
				$errors['postcode'] = __( 'For Gift Aid, please enter a valid UK postcode.', 'gat-core' );
			}
		}

		if ( ! $errors && gat_rate_limited( 'donate', 10 ) ) {
			$errors['amount'] = __( 'Too many attempts. Please wait a while and try again.', 'gat-core' );
		}
		if ( ! $errors && ! gat_stripe_enabled() ) {
			$errors['amount'] = __( 'Online donations are not available right now. Please contact us to donate.', 'gat-core' );
		}
		if ( $errors ) {
			gat_form_fail( 'donate', $errors, $v );
			return;
		}

		$row = gat_insert(
			'donations',
			array(
				'source'       => 'website',
				'status'       => 'pending',
				'amount_pence' => $pence,
				'frequency'    => $v['frequency'],
				'cause'        => $v['cause'],
				'donor_name'   => $v['name'],
				'email'        => $v['email'],
				'anonymous'    => $v['anonymous'] ? 1 : 0,
				'gift_aid'     => $v['gift_aid'] ? 1 : 0,
				'address'      => $v['gift_aid'] ? $v['address'] : '',
				'postcode'     => $v['gift_aid'] ? $v['postcode'] : '',
				'dedication'   => $v['dedication'],
				'notes'        => $v['gift_aid'] ? 'Gift Aid declaration accepted ' . current_time( 'mysql' ) . ': ' . gat_setting( 'gift_aid_text' ) : '',
			)
		);
		if ( ! $row ) {
			gat_form_fail( 'donate', array( 'amount' => __( 'Sorry, something went wrong. Please try again.', 'gat-core' ) ), $v );
			return;
		}

		$monthly = 'monthly' === $v['frequency'];
		$thanks  = add_query_arg( array( 'ref' => $row['ref'] ), gat_page_url( 'donate/thank-you' ) );
		$session = gat_stripe_checkout(
			array(
				'kind'        => 'donation',
				'ref'         => $row['ref'],
				'amount'      => $pence,
				'monthly'     => $monthly,
				'name'        => sprintf( 'Donation to Global Alliance Trust: %s%s', $v['cause'], $monthly ? ' (monthly)' : '' ),
				'email'       => $v['email'],
				'success_url' => $thanks . '&session_id={CHECKOUT_SESSION_ID}',
				'cancel_url'  => add_query_arg( 'gat_cancelled', '1', $back ) . '#donate-form',
				'metadata'    => array( 'cause' => $v['cause'], 'gift_aid' => $v['gift_aid'] ? 'yes' : 'no' ),
			)
		);
		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			gat_update( 'donations', $row['id'], array( 'status' => 'failed', 'notes' => trim( $row['notes'] . "\nStripe: " . ( is_wp_error( $session ) ? $session->get_error_message() : 'no url' ) ) ) );
			gat_form_fail( 'donate', array( 'amount' => __( 'We could not open the secure payment page. Please try again in a moment.', 'gat-core' ) ), $v );
			return;
		}
		gat_update( 'donations', $row['id'], array( 'stripe_session' => $session['id'] ) );
		wp_redirect( $session['url'], 303 ); // phpcs:ignore WordPress.Security.SafeRedirect -- Stripe Checkout.
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * The form.
 * ------------------------------------------------------------------------- */
add_shortcode(
	'gat_donate_form',
	function () {
		$state   = gat_form_state( 'donate' );
		$v       = $state['values'];
		$amounts = gat_donate_amounts();
		$causes  = gat_setting_lines( 'donate_causes' );
		$choice  = $v['amount_choice'] ?? (string) ( $amounts[1] ?? $amounts[0] ?? 'other' );
		$freq    = $v['frequency'] ?? 'one_off';
		$giftaid = (bool) gat_setting( 'gift_aid' );

		ob_start();
		echo '<div id="donate-form" class="gat-donate">';
		if ( isset( $_GET['gat_cancelled'] ) ) { // phpcs:ignore
			echo '<div class="gat-alert gat-alert--info" role="status">' . esc_html__( 'Your payment was not completed and nothing was taken. You can try again below.', 'gat-core' ) . '</div>';
		}
		if ( ! gat_stripe_enabled() ) {
			echo '<div class="gat-alert gat-alert--info" role="status">' . esc_html__( 'Online donations are being set up. Please contact us if you would like to donate in the meantime.', 'gat-core' ) . '</div>';
		}
		gat_error_summary( $state );
		?>
		<form class="gat-form gat-form--donate" method="post" action="#donate-form" novalidate data-gat-donate>
			<input type="hidden" name="gat_action" value="donate">
			<?php wp_nonce_field( 'gat_donate', 'gat_nonce' ); ?>
			<?php gat_honeypot(); ?>

			<?php if ( gat_setting( 'donate_monthly' ) ) : ?>
			<fieldset class="gat-seg">
				<legend><?php esc_html_e( 'How often?', 'gat-core' ); ?></legend>
				<label><input type="radio" name="gat_frequency" value="one_off" <?php checked( 'one_off', $freq ); ?>><span><?php esc_html_e( 'One-off', 'gat-core' ); ?></span></label>
				<label><input type="radio" name="gat_frequency" value="monthly" <?php checked( 'monthly', $freq ); ?>><span><?php esc_html_e( 'Monthly', 'gat-core' ); ?></span></label>
			</fieldset>
			<?php endif; ?>

			<fieldset class="gat-amounts<?php echo isset( $state['errors']['amount_other'] ) ? ' has-error' : ''; ?>">
				<legend><?php esc_html_e( 'Choose an amount', 'gat-core' ); ?></legend>
				<div class="gat-amounts__grid">
					<?php foreach ( $amounts as $a ) : ?>
						<label class="gat-amount"><input type="radio" name="gat_amount_choice" value="<?php echo esc_attr( $a ); ?>" <?php checked( (string) $a, $choice ); ?>><span>£<?php echo esc_html( $a ); ?></span></label>
					<?php endforeach; ?>
					<label class="gat-amount"><input type="radio" name="gat_amount_choice" value="other" <?php checked( 'other', $choice ); ?>><span><?php esc_html_e( 'Other', 'gat-core' ); ?></span></label>
				</div>
				<div class="gat-other" <?php echo 'other' === $choice ? '' : 'hidden'; ?>>
					<label for="gat_amount_other"><?php esc_html_e( 'Other amount (£)', 'gat-core' ); ?></label>
					<div class="gat-money"><span aria-hidden="true">£</span><input type="text" inputmode="decimal" id="gat_amount_other" name="gat_amount_other" value="<?php echo esc_attr( $v['amount_other'] ?? '' ); ?>" placeholder="0.00"></div>
				</div>
				<?php if ( isset( $state['errors']['amount_other'] ) ) : ?><p class="error"><?php echo esc_html( $state['errors']['amount_other'] ); ?></p><?php endif; ?>
			</fieldset>

			<?php if ( count( $causes ) > 1 ) : ?>
				<?php gat_field( 'cause', __( 'What would you like to support?', 'gat-core' ), array( 'type' => 'select', 'required' => true, 'options' => $causes, 'value' => $causes[0] ), $state ); ?>
			<?php elseif ( $causes ) : ?>
				<input type="hidden" name="gat_cause" value="<?php echo esc_attr( $causes[0] ); ?>">
			<?php endif; ?>

			<div class="row">
				<?php gat_field( 'name', __( 'Full name', 'gat-core' ), array( 'required' => true, 'attrs' => array( 'autocomplete' => 'name' ) ), $state ); ?>
				<?php gat_field( 'email', __( 'Email address', 'gat-core' ), array( 'type' => 'email', 'required' => true, 'hint' => __( 'For your payment confirmation.', 'gat-core' ), 'attrs' => array( 'autocomplete' => 'email' ) ), $state ); ?>
			</div>
			<?php gat_field( 'dedication', __( 'Dedicate this gift', 'gat-core' ), array( 'hint' => __( 'For example: In memory of …, or On behalf of …', 'gat-core' ) ), $state ); ?>
			<label class="check"><input type="checkbox" name="gat_anonymous" value="1" <?php checked( ! empty( $v['anonymous'] ) ); ?>> <span><?php esc_html_e( 'Please keep my name private (we still need it for our records).', 'gat-core' ); ?></span></label>

			<?php if ( $giftaid ) : ?>
			<fieldset class="gat-giftaid">
				<legend><?php esc_html_e( 'Gift Aid', 'gat-core' ); ?></legend>
				<p class="gat-giftaid__boost" data-gat-giftaid-boost><?php esc_html_e( 'If you are a UK taxpayer, Gift Aid adds 25p to every £1 you give, at no extra cost to you.', 'gat-core' ); ?></p>
				<label class="check"><input type="checkbox" name="gat_gift_aid" value="1" <?php checked( ! empty( $v['gift_aid'] ) ); ?> data-gat-giftaid> <span><?php echo esc_html( gat_setting( 'gift_aid_text' ) ); ?></span></label>
				<div class="gat-giftaid__address" <?php echo empty( $v['gift_aid'] ) ? 'hidden' : ''; ?>>
					<?php gat_field( 'address', __( 'Home address', 'gat-core' ), array( 'type' => 'textarea', 'required' => true, 'attrs' => array( 'rows' => 3, 'autocomplete' => 'street-address' ) ), $state ); ?>
					<?php gat_field( 'postcode', __( 'Postcode', 'gat-core' ), array( 'required' => true, 'class' => 'short', 'attrs' => array( 'autocomplete' => 'postal-code' ) ), $state ); ?>
				</div>
			</fieldset>
			<?php endif; ?>

			<p class="gat-small"><?php
				printf(
					/* translators: %s: link */
					esc_html__( 'You will pay on Stripe’s secure page. We never see your card details. %s', 'gat-core' ),
					'<a href="' . esc_url( gat_page_url( 'donation-terms' ) ) . '">' . esc_html__( 'Donation terms', 'gat-core' ) . '</a>'
				); ?></p>
			<button type="submit" class="btn btn--primary btn--lg" data-gat-donate-btn><?php esc_html_e( 'Donate Now', 'gat-core' ); ?></button>
		</form>
		<script>
		(function () {
			var f = document.querySelector('[data-gat-donate]'); if (!f) return;
			var other = f.querySelector('.gat-other'), btn = f.querySelector('[data-gat-donate-btn]');
			var ga = f.querySelector('[data-gat-giftaid]'), gaBox = f.querySelector('.gat-giftaid__address');
			function amount() {
				var c = f.querySelector('input[name=gat_amount_choice]:checked'); if (!c) return 0;
				return c.value === 'other' ? parseFloat((f.querySelector('#gat_amount_other').value || '').replace(/[£,]/g, '')) || 0 : parseFloat(c.value);
			}
			function update() {
				var c = f.querySelector('input[name=gat_amount_choice]:checked');
				if (other) other.hidden = !(c && c.value === 'other');
				var m = f.querySelector('input[name=gat_frequency]:checked'), monthly = m && m.value === 'monthly', a = amount();
				btn.textContent = a >= 1 ? 'Donate £' + (a % 1 ? a.toFixed(2) : a) + (monthly ? ' a month' : '') : 'Donate Now';
				if (ga && gaBox) gaBox.hidden = !ga.checked;
			}
			f.addEventListener('change', update); f.addEventListener('input', update); update();
			var errs = document.getElementById('gat-errors'); if (errs) errs.focus();
		})();
		</script>
		<?php
		echo '</div>';
		return ob_get_clean();
	}
);

/* ---------------------------------------------------------------------------
 * Thank-you page.
 * ------------------------------------------------------------------------- */
add_shortcode(
	'gat_donation_result',
	function () {
		$ref = sanitize_text_field( wp_unslash( $_GET['ref'] ?? '' ) ); // phpcs:ignore
		$sid = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) ); // phpcs:ignore
		$row = $ref ? gat_get_by( 'donations', 'ref', $ref ) : null;
		if ( ! $row || ! $sid || $row['stripe_session'] !== $sid ) {
			return '<p>' . esc_html__( 'Thank you for supporting Global Alliance Trust.', 'gat-core' ) . '</p>';
		}
		// Confirm with Stripe straight away (the webhook also does this).
		if ( 'pending' === $row['status'] ) {
			$s = gat_stripe_request( 'GET', 'checkout/sessions/' . rawurlencode( $sid ) );
			if ( ! is_wp_error( $s ) && ( $s['client_reference_id'] ?? '' ) === $row['ref'] ) {
				gat_apply_session( $s, 'return' );
				$row = gat_get( 'donations', $row['id'] );
			}
		}
		$first   = strtok( $row['donor_name'], ' ' );
		$monthly = 'monthly' === $row['frequency'];
		ob_start();
		if ( in_array( $row['status'], array( 'paid', 'active' ), true ) ) {
			echo '<div class="gat-result gat-result--ok">';
			/* translators: %s first name */
			echo '<h2>' . esc_html( sprintf( __( 'Thank you, %s!', 'gat-core' ), $first ) ) . '</h2>';
			echo '<p>' . esc_html(
				$monthly
					/* translators: 1 amount 2 cause */
					? sprintf( __( 'Your monthly donation of %1$s to %2$s is set up.', 'gat-core' ), gat_money( $row['amount_pence'] ), $row['cause'] )
					/* translators: 1 amount 2 cause */
					: sprintf( __( 'Your donation of %1$s to %2$s has been received.', 'gat-core' ), gat_money( $row['amount_pence'] ), $row['cause'] )
			) . '</p>';
		} else {
			echo '<div class="gat-result gat-result--wait">';
			echo '<h2>' . esc_html__( 'Thank you, we are confirming your payment', 'gat-core' ) . '</h2>';
			echo '<p>' . esc_html__( 'This usually takes a few moments. You will receive a confirmation by email.', 'gat-core' ) . '</p>';
		}
		/* translators: %s reference */
		echo '<p class="gat-ref">' . esc_html( sprintf( __( 'Your reference: %s', 'gat-core' ), $row['ref'] ) ) . '</p>';
		/* translators: %s email */
		echo '<p>' . esc_html( sprintf( __( 'A confirmation has been sent to %s.', 'gat-core' ), $row['email'] ) ) . '</p>';
		echo '</div>';
		return ob_get_clean();
	}
);

/**
 * Emails once a donation is paid.
 *
 * @param array $d Donation row.
 */
function gat_donation_emails( $d ) {
	if ( ! $d ) {
		return;
	}
	$monthly = 'monthly' === $d['frequency'];
	if ( $d['email'] ) {
		$lines = array(
			/* translators: %s name */
			sprintf( __( 'Dear %s,', 'gat-core' ), $d['donor_name'] ?: __( 'friend', 'gat-core' ) ),
			$monthly
				/* translators: 1 amount 2 cause */
				? sprintf( __( 'Thank you for your monthly donation of %1$s to %2$s. It will be taken each month until you cancel it.', 'gat-core' ), gat_money( $d['amount_pence'] ), $d['cause'] ?: 'Global Alliance Trust' )
				/* translators: 1 amount 2 cause */
				: sprintf( __( 'Thank you for your donation of %1$s to %2$s.', 'gat-core' ), gat_money( $d['amount_pence'] ), $d['cause'] ?: 'Global Alliance Trust' ),
			/* translators: %s ref */
			sprintf( __( 'Your reference: %s', 'gat-core' ), $d['ref'] ),
		);
		if ( $d['gift_aid'] ) {
			$lines[] = __( 'You chose to Gift Aid this donation. Thank you. Please tell us if you stop paying enough UK tax, change your name or home address.', 'gat-core' );
		}
		if ( $monthly ) {
			$lines[] = __( 'To change or cancel your monthly gift at any time, just reply to this email or contact us.', 'gat-core' );
		}
		$lines[] = __( 'Your support helps us serve the community through education, welfare, wellbeing and charitable services.', 'gat-core' );
		gat_mail( $d['email'], __( 'Thank you for your donation to Global Alliance Trust', 'gat-core' ), $lines );
	}
	$staff = trim( (string) gat_setting( 'donations_email' ) );
	if ( $staff ) {
		gat_mail(
			$staff,
			/* translators: 1 amount 2 ref */
			sprintf( __( 'New donation: %1$s (%2$s)', 'gat-core' ), gat_money( $d['amount_pence'] ) . ( $monthly ? '/month' : '' ), $d['ref'] ),
			array(
				/* translators: 1 name 2 cause */
				sprintf( __( 'From: %1$s · Cause: %2$s', 'gat-core' ), $d['anonymous'] ? $d['donor_name'] . ' (wishes to stay anonymous)' : $d['donor_name'], $d['cause'] ),
				admin_url( 'admin.php?page=gat-donations&view=' . $d['id'] ),
			)
		);
	}
}
