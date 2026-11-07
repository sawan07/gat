<?php
/**
 * Settings → GAT Settings (administrators only).
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults.
 *
 * @return array
 */
function gat_settings_defaults() {
	return array(
		'stripe_secret'         => '',
		'stripe_webhook_secret' => '',
		'enrol_open'            => 1,
		'enrol_fee'             => '',
		'enrol_classes'         => '',
		'academy_email'         => '',
		'donate_amounts'        => '10, 25, 50, 100',
		'donate_causes'         => "General Fund\nCommunity Services\nSunnah Academy / Maktab\nYouth & Children's Services\nElderly Services\nCounselling, Wellbeing & Advice",
		'donate_monthly'        => 1,
		'gift_aid'              => 0,
		'gift_aid_text'         => 'I want to Gift Aid my donation and any donations I make in the future or have made in the past 4 years to Global Alliance Trust. I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that tax year it is my responsibility to pay any difference.',
		'donations_email'       => '',
		'assistant_secret'      => '',
	);
}

/**
 * A setting (wp-config constants win for the Stripe keys).
 *
 * @param string $key Key.
 * @return mixed
 */
function gat_setting( $key ) {
	if ( 'stripe_secret' === $key && defined( 'GAT_STRIPE_SECRET_KEY' ) ) {
		return GAT_STRIPE_SECRET_KEY;
	}
	if ( 'stripe_webhook_secret' === $key && defined( 'GAT_STRIPE_WEBHOOK_SECRET' ) ) {
		return GAT_STRIPE_WEBHOOK_SECRET;
	}
	$all = wp_parse_args( (array) get_option( 'gat_settings', array() ), gat_settings_defaults() );
	return $all[ $key ] ?? '';
}

/**
 * Lines of a textarea setting.
 *
 * @param string $key Key.
 * @return string[]
 */
function gat_setting_lines( $key ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) gat_setting( $key ) ) ) ) );
}

/**
 * Enrolment fee in pence (0 = no fee).
 *
 * @return int
 */
function gat_enrol_fee_pence() {
	$fee = trim( (string) gat_setting( 'enrol_fee' ) );
	return '' === $fee ? 0 : (int) round( (float) $fee * 100 );
}

/**
 * Where staff notifications go.
 *
 * @param string $key academy_email|donations_email.
 * @return string
 */
function gat_notify_email( $key ) {
	$to = trim( (string) gat_setting( $key ) );
	return $to ?: ( get_theme_mod( 'gat_contact_email' ) ?: get_option( 'admin_email' ) );
}

add_action(
	'admin_menu',
	function () {
		add_options_page( __( 'GAT Settings', 'gat-core' ), __( 'GAT Settings', 'gat-core' ), 'manage_options', 'gat-settings', 'gat_core_settings_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'gat_settings',
			'gat_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'gat_core_sanitize_settings',
				'default'           => gat_settings_defaults(),
			)
		);
		// Create the assistant secret once.
		$s = (array) get_option( 'gat_settings', array() );
		if ( empty( $s['assistant_secret'] ) ) {
			$s['assistant_secret'] = wp_generate_password( 40, false );
			update_option( 'gat_settings', $s );
		}
	}
);

/**
 * Sanitise.
 *
 * @param array $in Input.
 * @return array
 */
function gat_core_sanitize_settings( $in ) {
	$old = wp_parse_args( (array) get_option( 'gat_settings', array() ), gat_settings_defaults() );
	$out = $old;
	$in  = (array) $in;

	// Secret keys: an empty box keeps the saved key; "clear" removes it.
	foreach ( array( 'stripe_secret', 'stripe_webhook_secret' ) as $k ) {
		$v = trim( (string) ( $in[ $k ] ?? '' ) );
		if ( 'clear' === strtolower( $v ) ) {
			$out[ $k ] = '';
		} elseif ( '' !== $v ) {
			$out[ $k ] = sanitize_text_field( $v );
		}
	}
	if ( $out['stripe_secret'] && ! preg_match( '/^(sk|rk)_(test|live)_/', $out['stripe_secret'] ) ) {
		add_settings_error( 'gat_settings', 'stripe', __( 'The Stripe key should start with sk_ or rk_ (test or live). It was saved, but please check it.', 'gat-core' ), 'warning' );
	}
	if ( $out['stripe_webhook_secret'] && ! str_starts_with( $out['stripe_webhook_secret'], 'whsec_' ) ) {
		add_settings_error( 'gat_settings', 'whsec', __( 'The webhook signing secret should start with whsec_.', 'gat-core' ), 'warning' );
	}

	foreach ( array( 'enrol_open', 'donate_monthly', 'gift_aid' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	$fee              = trim( (string) ( $in['enrol_fee'] ?? '' ) );
	$out['enrol_fee'] = '' === $fee ? '' : number_format( max( 0, (float) str_replace( array( '£', ',' ), '', $fee ) ), 2, '.', '' );
	foreach ( array( 'enrol_classes', 'donate_causes', 'gift_aid_text' ) as $k ) {
		$out[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
	}
	$amounts               = array_filter( array_map( 'absint', explode( ',', (string) ( $in['donate_amounts'] ?? '' ) ) ) );
	$out['donate_amounts'] = $amounts ? implode( ', ', $amounts ) : '10, 25, 50, 100';
	foreach ( array( 'academy_email', 'donations_email' ) as $k ) {
		$out[ $k ] = sanitize_email( $in[ $k ] ?? '' );
	}
	// Set internally (first run); the form never posts this field.
	if ( ! empty( $in['assistant_secret'] ) && empty( $old['assistant_secret'] ) ) {
		$out['assistant_secret'] = sanitize_text_field( $in['assistant_secret'] );
	}
	if ( ! empty( $in['regenerate_secret'] ) || empty( $out['assistant_secret'] ) ) {
		$out['assistant_secret'] = wp_generate_password( 40, false );
	}
	return $out;
}

/**
 * Settings page.
 */
function gat_core_settings_page() {
	$s       = wp_parse_args( (array) get_option( 'gat_settings', array() ), gat_settings_defaults() );
	$key     = gat_setting( 'stripe_secret' );
	$wh      = gat_setting( 'stripe_webhook_secret' );
	$mode    = str_contains( $key, '_live_' ) ? 'live' : ( $key ? 'test' : '' );
	$webhook = rest_url( 'gat/v1/stripe-webhook' );
	$mask    = fn( $v ) => $v ? substr( $v, 0, 8 ) . '…' . substr( $v, -4 ) : '';
	?>
	<div class="wrap gat-settings">
		<h1><?php esc_html_e( 'GAT Settings', 'gat-core' ); ?></h1>
		<?php settings_errors( 'gat_settings' ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'gat_settings' ); ?>

			<h2><?php esc_html_e( 'Payments (Stripe)', 'gat-core' ); ?></h2>
			<p><?php esc_html_e( 'Donations and enrolment fees are paid on Stripe’s secure page. Use Global Alliance Trust’s own Stripe account: the money goes to whichever account these keys belong to.', 'gat-core' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="gat_stripe_secret"><?php esc_html_e( 'Stripe secret key', 'gat-core' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="gat_stripe_secret" name="gat_settings[stripe_secret]" value="" autocomplete="off" placeholder="<?php echo esc_attr( $key ? $mask( $key ) : 'rk_test_… or sk_test_…' ); ?>" <?php disabled( defined( 'GAT_STRIPE_SECRET_KEY' ) ); ?>>
						<p class="description">
							<?php if ( $mode ) : ?>
								<strong style="color:<?php echo 'live' === $mode ? '#1a7f37' : '#9a6700'; ?>"><?php echo 'live' === $mode ? esc_html__( 'LIVE mode: real payments.', 'gat-core' ) : esc_html__( 'TEST mode: no real money moves.', 'gat-core' ); ?></strong>
							<?php else : ?>
								<strong style="color:#b32d2e"><?php esc_html_e( 'Not set: online payments are switched off.', 'gat-core' ); ?></strong>
							<?php endif; ?>
							<?php esc_html_e( 'Leave empty to keep the saved key; type "clear" to remove it. A restricted key (rk_…) needs Checkout Sessions: Write.', 'gat-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="gat_stripe_wh"><?php esc_html_e( 'Webhook signing secret', 'gat-core' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="gat_stripe_wh" name="gat_settings[stripe_webhook_secret]" value="" autocomplete="off" placeholder="<?php echo esc_attr( $wh ? $mask( $wh ) : 'whsec_…' ); ?>" <?php disabled( defined( 'GAT_STRIPE_WEBHOOK_SECRET' ) ); ?>>
						<p class="description">
							<?php esc_html_e( 'In Stripe: Developers → Webhooks → Add endpoint, with this address:', 'gat-core' ); ?><br>
							<code><?php echo esc_html( $webhook ); ?></code><br>
							<?php esc_html_e( 'Events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired, charge.refunded, invoice.paid, customer.subscription.deleted. Then paste the signing secret here.', 'gat-core' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Sunnah Academy enrolment', 'gat-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Online enrolment', 'gat-core' ); ?></th>
					<td><label><input type="checkbox" name="gat_settings[enrol_open]" value="1" <?php checked( $s['enrol_open'] ); ?>> <?php esc_html_e( 'Open: parents can apply online', 'gat-core' ); ?></label></td>
				</tr>
				<tr>
					<th><label for="gat_fee"><?php esc_html_e( 'Enrolment fee (£)', 'gat-core' ); ?></label></th>
					<td><input type="text" inputmode="decimal" id="gat_fee" name="gat_settings[enrol_fee]" value="<?php echo esc_attr( $s['enrol_fee'] ); ?>" class="small-text" placeholder="0.00">
					<p class="description"><?php esc_html_e( 'Leave empty or 0 for no fee (applications are submitted without payment).', 'gat-core' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gat_classes"><?php esc_html_e( 'Programmes / classes', 'gat-core' ); ?></label></th>
					<td><textarea id="gat_classes" name="gat_settings[enrol_classes]" rows="5" class="large-text"><?php echo esc_textarea( $s['enrol_classes'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line; parents choose from this list. Leave empty to let parents type it.', 'gat-core' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gat_academy_email"><?php esc_html_e( 'Send new applications to', 'gat-core' ); ?></label></th>
					<td><input type="email" id="gat_academy_email" name="gat_settings[academy_email]" value="<?php echo esc_attr( $s['academy_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( gat_notify_email( 'academy_email' ) ); ?>"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Donations', 'gat-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="gat_amounts"><?php esc_html_e( 'Suggested amounts (£)', 'gat-core' ); ?></label></th>
					<td><input type="text" id="gat_amounts" name="gat_settings[donate_amounts]" value="<?php echo esc_attr( $s['donate_amounts'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Separated by commas. Donors can always enter another amount.', 'gat-core' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gat_causes"><?php esc_html_e( 'Causes', 'gat-core' ); ?></label></th>
					<td><textarea id="gat_causes" name="gat_settings[donate_causes]" rows="6" class="large-text"><?php echo esc_textarea( $s['donate_causes'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. The first is the default.', 'gat-core' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Monthly giving', 'gat-core' ); ?></th>
					<td><label><input type="checkbox" name="gat_settings[donate_monthly]" value="1" <?php checked( $s['donate_monthly'] ); ?>> <?php esc_html_e( 'Let donors give monthly', 'gat-core' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gift Aid', 'gat-core' ); ?></th>
					<td><label><input type="checkbox" name="gat_settings[gift_aid]" value="1" <?php checked( $s['gift_aid'] ); ?>> <?php esc_html_e( 'Offer Gift Aid (only if GAT is registered with HMRC for Gift Aid)', 'gat-core' ); ?></label>
					<p><textarea name="gat_settings[gift_aid_text]" rows="4" class="large-text"><?php echo esc_textarea( $s['gift_aid_text'] ); ?></textarea></p>
					<p class="description"><?php esc_html_e( 'Declaration shown to donors. When they tick it, their home address and postcode are required (HMRC needs them for claims).', 'gat-core' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gat_don_email"><?php esc_html_e( 'Tell this email about new donations (optional)', 'gat-core' ); ?></label></th>
					<td><input type="email" id="gat_don_email" name="gat_settings[donations_email]" value="<?php echo esc_attr( $s['donations_email'] ); ?>" class="regular-text"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Donation assistant (AI chat)', 'gat-core' ); ?></h2>
			<p><?php esc_html_e( 'Lets the donation assistant record its donations here, so every donation appears under Donations. Set these two values on the assistant (in Coolify):', 'gat-core' ); ?></p>
			<table class="form-table" role="presentation">
				<tr><th>GAT_WP_URL</th><td><code><?php echo esc_html( untrailingslashit( home_url() ) ); ?></code></td></tr>
				<tr><th>GAT_WP_SECRET</th><td><code style="user-select:all"><?php echo esc_html( $s['assistant_secret'] ); ?></code>
				<p><label><input type="checkbox" name="gat_settings[regenerate_secret]" value="1"> <?php esc_html_e( 'Make a new secret (the assistant must then be updated)', 'gat-core' ); ?></label></p></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Reminder until payments are set up.
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) || gat_setting( 'stripe_secret' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'dashboard', 'settings_page_gat-settings', 'toplevel_page_gat-donations', 'toplevel_page_gat-students' ), true ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Online payments are not switched on yet: add the Stripe keys in', 'gat-core' ),
				esc_url( admin_url( 'options-general.php?page=gat-settings' ) ),
				esc_html__( 'Settings → GAT Settings', 'gat-core' )
			);
		}
	}
);
