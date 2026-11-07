<?php
/**
 * Stripe: Checkout Sessions (one-off, monthly, enrolment fee), the signed
 * webhook that keeps payment statuses up to date, and the endpoint the
 * donation assistant uses to record its donations.
 *
 * No card details ever touch this site: donors and parents pay on Stripe's
 * own page.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is Stripe configured?
 *
 * @return bool
 */
function gat_stripe_enabled() {
	return (bool) gat_setting( 'stripe_secret' );
}

/**
 * Call the Stripe API.
 *
 * @param string $method GET|POST.
 * @param string $path   e.g. checkout/sessions.
 * @param array  $fields Form fields (flat, Stripe bracket notation).
 * @return array|WP_Error Decoded response.
 */
function gat_stripe_request( $method, $path, $fields = array() ) {
	$key = gat_setting( 'stripe_secret' );
	if ( ! $key ) {
		return new WP_Error( 'gat_stripe', 'Stripe is not configured.' );
	}
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array(
			'Authorization'  => 'Bearer ' . $key,
			'Stripe-Version' => '2024-06-20',
		),
	);
	$url = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );
	if ( 'POST' === $method ) {
		$args['body'] = $fields;
	} elseif ( $fields ) {
		$url = add_query_arg( $fields, $url );
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 || ! is_array( $body ) ) {
		$msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
		error_log( '[GAT] Stripe ' . $path . ' failed: ' . $msg ); // phpcs:ignore
		return new WP_Error( 'gat_stripe', $msg );
	}
	return $body;
}

/**
 * Create a Checkout Session and return it.
 *
 * @param array $o {
 *     @type string $kind        donation|enrolment.
 *     @type string $ref         Our reference.
 *     @type int    $amount      Pence.
 *     @type string $name        Line item name.
 *     @type string $email       Customer email.
 *     @type bool   $monthly     Subscription?
 *     @type string $success_url Success URL ({CHECKOUT_SESSION_ID} is filled by Stripe).
 *     @type string $cancel_url  Cancel URL.
 *     @type array  $metadata    Extra metadata.
 * }
 * @return array|WP_Error
 */
function gat_stripe_checkout( $o ) {
	$meta = array_merge( array( 'gat_kind' => $o['kind'], 'gat_ref' => $o['ref'], 'site' => wp_parse_url( home_url(), PHP_URL_HOST ) ), $o['metadata'] ?? array() );
	$f    = array(
		'mode'                                         => ! empty( $o['monthly'] ) ? 'subscription' : 'payment',
		'client_reference_id'                          => $o['ref'],
		'customer_email'                               => $o['email'],
		'success_url'                                  => $o['success_url'],
		'cancel_url'                                   => $o['cancel_url'],
		'line_items[0][quantity]'                      => 1,
		'line_items[0][price_data][currency]'          => 'gbp',
		'line_items[0][price_data][unit_amount]'       => (int) $o['amount'],
		'line_items[0][price_data][product_data][name]' => mb_substr( $o['name'], 0, 250 ),
		'locale'                                       => 'en-GB',
	);
	foreach ( $meta as $k => $v ) {
		$f[ "metadata[$k]" ] = mb_substr( (string) $v, 0, 500 );
	}
	if ( ! empty( $o['monthly'] ) ) {
		$f['line_items[0][price_data][recurring][interval]'] = 'month';
		foreach ( $meta as $k => $v ) {
			$f[ "subscription_data[metadata][$k]" ] = mb_substr( (string) $v, 0, 500 );
		}
	} else {
		if ( 'donation' === $o['kind'] ) {
			$f['submit_type'] = 'donate';
		} else {
			$f['submit_type'] = 'pay';
		}
		foreach ( $meta as $k => $v ) {
			$f[ "payment_intent_data[metadata][$k]" ] = mb_substr( (string) $v, 0, 500 );
		}
		$f['payment_intent_data[description]'] = mb_substr( $o['name'], 0, 250 );
	}
	return gat_stripe_request( 'POST', 'checkout/sessions', $f );
}

/**
 * Apply a completed/updated Checkout Session to our records.
 * Used by both the webhook and the thank-you page (so status is right even
 * if the webhook is slow or not set up yet).
 *
 * @param array  $s     Session object.
 * @param string $event Event type or 'return'.
 */
function gat_apply_session( $s, $event = 'checkout.session.completed' ) {
	$kind = $s['metadata']['gat_kind'] ?? '';
	$ref  = $s['client_reference_id'] ?? '';
	$paid = in_array( $s['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true );

	// Donations made through the assistant's payment links.
	if ( ! $kind && ! empty( $s['payment_link'] ) ) {
		$row = gat_get_by( 'donations', 'payment_link_id', $s['payment_link'] );
		if ( ! $row ) {
			return;
		}
		$kind = 'donation';
		$ref  = $row['ref'];
	}
	if ( ! $ref ) {
		return;
	}

	if ( 'enrolment' === $kind ) {
		$row = gat_get_by( 'students', 'ref', $ref );
		if ( ! $row ) {
			return;
		}
		$upd = array( 'stripe_session' => $s['id'] );
		if ( ! empty( $s['payment_intent'] ) ) {
			$upd['stripe_payment_intent'] = is_array( $s['payment_intent'] ) ? $s['payment_intent']['id'] : $s['payment_intent'];
		}
		$was_paid = 'paid' === $row['payment_status'];
		if ( $paid && 'refunded' !== $row['payment_status'] ) {
			$upd['payment_status'] = 'paid';
			$upd['paid_at']        = $row['paid_at'] ?: current_time( 'mysql', true );
		}
		gat_update( 'students', $row['id'], $upd );
		if ( $paid && ! $was_paid ) {
			gat_enrolment_emails( gat_get( 'students', $row['id'] ) );
		}
		return;
	}

	if ( 'donation' === $kind ) {
		$row = gat_get_by( 'donations', 'ref', $ref );
		if ( ! $row ) {
			return;
		}
		$upd = array( 'stripe_session' => $s['id'] );
		if ( ! empty( $s['payment_intent'] ) ) {
			$upd['stripe_payment_intent'] = is_array( $s['payment_intent'] ) ? $s['payment_intent']['id'] : $s['payment_intent'];
		}
		if ( ! empty( $s['subscription'] ) ) {
			$upd['stripe_subscription'] = is_array( $s['subscription'] ) ? $s['subscription']['id'] : $s['subscription'];
			$upd['frequency']           = 'monthly';
		}
		// Assistant donations: Stripe has the donor's details.
		if ( empty( $row['email'] ) && ! empty( $s['customer_details']['email'] ) ) {
			$upd['email'] = sanitize_email( $s['customer_details']['email'] );
		}
		if ( empty( $row['donor_name'] ) && ! empty( $s['customer_details']['name'] ) ) {
			$upd['donor_name'] = sanitize_text_field( $s['customer_details']['name'] );
		}
		$newly_paid = false;
		if ( $paid && in_array( $row['status'], array( 'pending', 'failed', 'expired' ), true ) ) {
			$upd['status']           = ! empty( $upd['stripe_subscription'] ) ? 'active' : 'paid';
			$upd['payments_count']   = max( 1, (int) $row['payments_count'] );
			$upd['total_paid_pence'] = max( (int) $row['total_paid_pence'], (int) ( $s['amount_total'] ?? $row['amount_pence'] ) );
			$upd['last_paid_at']     = current_time( 'mysql', true );
			$newly_paid              = true;
		} elseif ( 'checkout.session.async_payment_failed' === $event ) {
			$upd['status'] = 'failed';
		} elseif ( 'checkout.session.expired' === $event && 'pending' === $row['status'] ) {
			$upd['status'] = 'expired';
		}
		gat_update( 'donations', $row['id'], $upd );
		if ( $newly_paid ) {
			gat_donation_emails( gat_get( 'donations', $row['id'] ) );
		}
	}
}

/**
 * Verify a Stripe webhook signature.
 *
 * @param string $payload Raw body.
 * @param string $header  Stripe-Signature header.
 * @param string $secret  whsec_… secret.
 * @return bool
 */
function gat_stripe_verify( $payload, $header, $secret ) {
	if ( ! $secret || ! $header ) {
		return false;
	}
	$t   = null;
	$sig = array();
	foreach ( explode( ',', $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$t = (int) $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$sig[] = $kv[1];
		}
	}
	if ( ! $t || ! $sig || abs( time() - $t ) > 600 ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
	foreach ( $sig as $s ) {
		if ( hash_equals( $expected, $s ) ) {
			return true;
		}
	}
	return false;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'gat/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => 'gat_stripe_webhook',
				'permission_callback' => '__return_true', // Authenticated by the Stripe signature.
			)
		);
		register_rest_route(
			'gat/v1',
			'/assistant-donation',
			array(
				'methods'             => 'POST',
				'callback'            => 'gat_assistant_donation',
				'permission_callback' => function ( WP_REST_Request $r ) {
					$secret = (string) gat_setting( 'assistant_secret' );
					return $secret && hash_equals( $secret, (string) $r->get_header( 'x-gat-secret' ) );
				},
			)
		);
	}
);

/**
 * Webhook handler.
 *
 * @param WP_REST_Request $r Request.
 * @return WP_REST_Response
 */
function gat_stripe_webhook( WP_REST_Request $r ) {
	$payload = $r->get_body();
	if ( ! gat_stripe_verify( $payload, (string) $r->get_header( 'stripe-signature' ), (string) gat_setting( 'stripe_webhook_secret' ) ) ) {
		return new WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
	}
	$event = json_decode( $payload, true );
	$type  = $event['type'] ?? '';
	$obj   = $event['data']['object'] ?? array();

	switch ( $type ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
		case 'checkout.session.async_payment_failed':
		case 'checkout.session.expired':
			gat_apply_session( $obj, $type );
			break;

		case 'charge.refunded':
			$pi = $obj['payment_intent'] ?? '';
			if ( $pi && ! empty( $obj['refunded'] ) ) {
				$st = gat_get_by( 'students', 'stripe_payment_intent', $pi );
				if ( $st ) {
					gat_update( 'students', $st['id'], array( 'payment_status' => 'refunded' ) );
				}
				$dn = gat_get_by( 'donations', 'stripe_payment_intent', $pi );
				if ( $dn ) {
					gat_update( 'donations', $dn['id'], array( 'status' => 'refunded' ) );
				}
			}
			break;

		case 'invoice.paid':
			// Monthly donations: count each month's payment.
			$sub = $obj['subscription'] ?? '';
			$dn  = $sub ? gat_get_by( 'donations', 'stripe_subscription', $sub ) : null;
			if ( $dn && 'subscription_create' !== ( $obj['billing_reason'] ?? '' ) ) {
				gat_update(
					'donations',
					$dn['id'],
					array(
						'status'           => 'active',
						'payments_count'   => (int) $dn['payments_count'] + 1,
						'total_paid_pence' => (int) $dn['total_paid_pence'] + (int) ( $obj['amount_paid'] ?? 0 ),
						'last_paid_at'     => current_time( 'mysql', true ),
					)
				);
			}
			break;

		case 'customer.subscription.deleted':
			$dn = gat_get_by( 'donations', 'stripe_subscription', $obj['id'] ?? '' );
			if ( $dn ) {
				gat_update( 'donations', $dn['id'], array( 'status' => 'cancelled' ) );
			}
			break;
	}
	return new WP_REST_Response( array( 'received' => true ), 200 );
}

/**
 * The donation assistant records a donation (with its Stripe payment link).
 *
 * @param WP_REST_Request $r Request.
 * @return WP_REST_Response
 */
function gat_assistant_donation( WP_REST_Request $r ) {
	$p      = (array) $r->get_json_params();
	$amount = (int) round( (float) ( $p['amount'] ?? 0 ) * 100 );
	if ( $amount <= 0 ) {
		return new WP_REST_Response( array( 'error' => 'amount' ), 400 );
	}
	$link_id = sanitize_text_field( $p['payment_link_id'] ?? '' );
	if ( $link_id && gat_get_by( 'donations', 'payment_link_id', $link_id ) ) {
		return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
	}
	$dedication = '';
	if ( ! empty( $p['dedication_type'] ) && 'none' !== $p['dedication_type'] && ! empty( $p['dedication_name'] ) ) {
		$labels     = array( 'in_memory_of' => 'In memory of', 'in_honor_of' => 'In honour of', 'on_behalf_of' => 'On behalf of' );
		$dedication = ( $labels[ $p['dedication_type'] ] ?? '' ) . ' ' . sanitize_text_field( $p['dedication_name'] );
	}
	$row = gat_insert(
		'donations',
		array(
			'source'          => 'assistant',
			'external_ref'    => sanitize_text_field( $p['donation_id'] ?? '' ),
			'status'          => 'pending',
			'amount_pence'    => $amount,
			'frequency'       => 'monthly' === ( $p['frequency'] ?? '' ) ? 'monthly' : 'one_off',
			'cause'           => sanitize_text_field( $p['campaign_name'] ?? '' ),
			'donor_name'      => sanitize_text_field( $p['donor_name'] ?? '' ),
			'email'           => sanitize_email( $p['donor_email'] ?? '' ),
			'anonymous'       => empty( $p['anonymous'] ) ? 0 : 1,
			'gift_aid'        => empty( $p['gift_aid'] ) ? 0 : 1,
			'dedication'      => trim( $dedication ),
			'message'         => sanitize_textarea_field( $p['message'] ?? '' ),
			'payment_link_id' => $link_id,
		)
	);
	return new WP_REST_Response( array( 'ok' => (bool) $row, 'ref' => $row['ref'] ?? '' ), $row ? 201 : 500 );
}
