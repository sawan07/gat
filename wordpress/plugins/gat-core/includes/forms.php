<?php
/**
 * Shared helpers for the public Donate and Enrolment forms.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['gat_pub_form'] = array();

/**
 * Remember errors/values for re-displaying a form.
 *
 * @param string $form   Form id.
 * @param array  $errors field => message.
 * @param array  $values Values.
 */
function gat_form_fail( $form, $errors, $values ) {
	$GLOBALS['gat_pub_form'][ $form ] = array( 'errors' => $errors, 'values' => $values );
}

/**
 * Previous state of a form.
 *
 * @param string $form Form id.
 * @return array
 */
function gat_form_state( $form ) {
	return $GLOBALS['gat_pub_form'][ $form ] ?? array( 'errors' => array(), 'values' => array() );
}

/**
 * Rate limit by visitor.
 *
 * @param string $bucket Name.
 * @param int    $max    Per hour.
 * @return bool True if over the limit.
 */
function gat_rate_limited( $bucket, $max ) {
	$ip  = (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore
	$key = 'gat_rl_' . $bucket . '_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return true;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return false;
}

/**
 * Read a posted field.
 *
 * @param string $key  Name (without gat_ prefix).
 * @param bool   $long Textarea?
 * @return string
 */
function gat_post( $key, $long = false ) {
	$raw = isset( $_POST[ 'gat_' . $key ] ) ? wp_unslash( $_POST[ 'gat_' . $key ] ) : ''; // phpcs:ignore
	if ( is_array( $raw ) ) {
		return '';
	}
	$v = $long ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
	return mb_substr( trim( $v ), 0, $long ? 3000 : 191 );
}

/**
 * Is a UK-style postcode (loose check)?
 *
 * @param string $pc Postcode.
 * @return bool
 */
function gat_valid_postcode( $pc ) {
	return (bool) preg_match( '/^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/i', trim( $pc ) );
}

/**
 * Is a plausible phone number?
 *
 * @param string $p Phone.
 * @return bool
 */
function gat_valid_phone( $p ) {
	$digits = preg_replace( '/\D/', '', $p );
	return strlen( $digits ) >= 10 && strlen( $digits ) <= 15;
}

/**
 * Render a form field.
 *
 * @param string $name   Field name (without gat_).
 * @param string $label  Label.
 * @param array  $o      type, required, options, hint, attrs, value, class.
 * @param array  $state  Form state.
 */
function gat_field( $name, $label, $o, $state ) {
	$type  = $o['type'] ?? 'text';
	$req   = ! empty( $o['required'] );
	$id    = 'gat_' . $name;
	$val   = $state['values'][ $name ] ?? ( $o['value'] ?? '' );
	$err   = $state['errors'][ $name ] ?? '';
	$attrs = '';
	foreach ( ( $o['attrs'] ?? array() ) as $k => $v ) {
		$attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	$desc = $err ? $id . '-err' : ( ! empty( $o['hint'] ) ? $id . '-hint' : '' );
	printf( '<div class="field%s%s">', $err ? ' has-error' : '', ! empty( $o['class'] ) ? ' ' . esc_attr( $o['class'] ) : '' );
	printf(
		'<label for="%1$s">%2$s%3$s</label>',
		esc_attr( $id ),
		esc_html( $label ),
		$req ? '' : ' <span class="opt">' . esc_html__( '(optional)', 'gat-core' ) . '</span>'
	);
	$common = sprintf( ' id="%1$s" name="%1$s"%2$s%3$s%4$s', esc_attr( $id ), $req ? ' required aria-required="true"' : '', $err ? ' aria-invalid="true"' : '', $desc ? ' aria-describedby="' . esc_attr( $desc ) . '"' : '' );
	if ( 'textarea' === $type ) {
		printf( '<textarea%s%s>%s</textarea>', $common, $attrs, esc_textarea( $val ) ); // phpcs:ignore
	} elseif ( 'select' === $type ) {
		printf( '<select%s%s><option value="">%s</option>', $common, $attrs, esc_html__( 'Please choose…', 'gat-core' ) ); // phpcs:ignore
		foreach ( $o['options'] as $opt ) {
			printf( '<option%s>%s</option>', selected( $val, $opt, false ), esc_html( $opt ) );
		}
		echo '</select>';
	} else {
		printf( '<input type="%s" value="%s"%s%s>', esc_attr( $type ), esc_attr( $val ), $common, $attrs ); // phpcs:ignore
	}
	if ( ! empty( $o['hint'] ) && ! $err ) {
		printf( '<p class="hint" id="%s-hint">%s</p>', esc_attr( $id ), esc_html( $o['hint'] ) );
	}
	if ( $err ) {
		printf( '<p class="error" id="%s-err">%s</p>', esc_attr( $id ), esc_html( $err ) );
	}
	echo '</div>';
}

/**
 * Error summary at the top of a form.
 *
 * @param array $state Form state.
 */
function gat_error_summary( $state ) {
	if ( ! $state['errors'] ) {
		return;
	}
	echo '<div class="gat-alert gat-alert--err" role="alert" tabindex="-1" id="gat-errors"><strong>' . esc_html__( 'Please check the highlighted boxes:', 'gat-core' ) . '</strong><ul>';
	foreach ( $state['errors'] as $field => $msg ) {
		printf( '<li><a href="#gat_%s">%s</a></li>', esc_attr( $field ), esc_html( $msg ) );
	}
	echo '</ul></div>';
}

/**
 * Hidden anti-spam field.
 */
function gat_honeypot() {
	echo '<div class="gat-hp" aria-hidden="true"><label>Leave empty <input type="text" name="gat_company_hp" tabindex="-1" autocomplete="off"></label></div>';
}

/**
 * Was the honeypot filled (bot)?
 *
 * @return bool
 */
function gat_is_bot() {
	return ! empty( $_POST['gat_company_hp'] ); // phpcs:ignore
}

/**
 * Signed token so a parent can retry payment for their own application.
 *
 * @param string $ref Reference.
 * @return string
 */
function gat_ref_token( $ref ) {
	return substr( hash_hmac( 'sha256', $ref, wp_salt( 'auth' ) ), 0, 20 );
}

/**
 * Page URL by path, with a fallback.
 *
 * @param string $path Page path (e.g. donate/thank-you).
 * @return string
 */
function gat_page_url( $path ) {
	$p = get_page_by_path( $path );
	return $p ? get_permalink( $p ) : home_url( '/' . trim( $path, '/' ) . '/' );
}

/**
 * Send a simple branded email.
 *
 * @param string $to      Recipient.
 * @param string $subject Subject.
 * @param array  $lines   Paragraphs (plain text).
 * @param array  $headers Extra headers.
 * @return bool
 */
function gat_mail( $to, $subject, $lines, $headers = array() ) {
	$body = implode( "\n\n", $lines ) . "\n\n— Global Alliance Trust\n" . home_url( '/' );
	return wp_mail( $to, $subject, $body, $headers );
}
