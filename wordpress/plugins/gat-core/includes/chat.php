<?php
/**
 * The donation assistant as a chat bubble on every page.
 *
 * The assistant itself runs separately (Coolify app gat-ai, default
 * https://gat.amverio.co.uk); this only loads a small script that talks to
 * its /api/new-thread and /api/chat endpoints. The assistant only allows
 * calls from the websites listed in its GAT_ALLOWED_ORIGINS setting.
 *
 * Any link or button can open the chat: give it the attribute
 * data-gat-chat-open, or link it to #gat-chat.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the bubble shows on this request.
 */
function gat_chat_enabled() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
		return false;
	}
	return (bool) apply_filters( 'gat_chat_enabled', (bool) gat_setting( 'chat_enabled' ) );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! gat_chat_enabled() ) {
			return;
		}
		$api = untrailingslashit( (string) gat_setting( 'chat_api' ) );
		if ( ! $api || ! str_starts_with( $api, 'https://' ) ) {
			return;
		}
		wp_enqueue_style( 'gat-chat', GAT_CORE_URL . 'assets/chat.css', array(), GAT_CORE_VERSION );
		wp_enqueue_script( 'gat-chat', GAT_CORE_URL . 'assets/chat.js', array(), GAT_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'gat-chat',
			'GAT_CHAT',
			array(
				'api'       => $api,
				'logo'      => GAT_CORE_URL . 'assets/chat-logo.png',
				'donateUrl' => gat_page_url( 'donate' ),
				'enrolUrl'  => gat_page_url( 'sunnah-academy/enrol' ),
				'greeting'  => __( 'Hello and welcome to Global Alliance Trust. I can help you make a donation, or answer questions about GAT and the Sunnah Academy. What would you like to do?', 'gat-core' ),
				'chips'     => array(
					__( 'Make a donation', 'gat-core' ),
					__( 'About Sunnah Academy', 'gat-core' ),
					__( 'How do I enrol?', 'gat-core' ),
				),
				'i18n'      => array(
					'open'        => __( 'Chat with us', 'gat-core' ),
					'title'       => __( 'GAT Assistant', 'gat-core' ),
					'subtitle'    => __( 'Donations & questions about GAT', 'gat-core' ),
					'close'       => __( 'Close chat', 'gat-core' ),
					'restart'     => __( 'Start a new chat', 'gat-core' ),
					'placeholder' => __( 'Type your message…', 'gat-core' ),
					'send'        => __( 'Send', 'gat-core' ),
					'note'        => __( 'Automated assistant. Please don’t share card details or children’s details here.', 'gat-core' ),
					'offline'     => __( 'Sorry, the assistant can’t be reached right now. You can still donate on our Donate page.', 'gat-core' ),
					'expired'     => __( 'This chat has expired, so we’ve started a new one. Please send your message again.', 'gat-core' ),
					'payMonthly'  => __( 'Set up monthly gift', 'gat-core' ),
					'pay'         => __( 'Pay %s securely', 'gat-core' ),
					'aMonth'      => __( 'a month', 'gat-core' ),
					'payNote'     => __( 'Opens Stripe’s secure payment page in a new tab.', 'gat-core' ),
					'donatePage'  => __( 'Donate page', 'gat-core' ),
				),
			)
		);
	}
);
