<?php
/**
 * [gat_contact]: contact details, map and the contact form, side by side.
 * Details come from Appearance → Customize → Contact & Social.
 *
 * @package gat-core
 */

defined( 'ABSPATH' ) || exit;

add_shortcode(
	'gat_contact',
	function () {
		$c = array(
			'address' => trim( (string) get_theme_mod( 'gat_contact_address', '' ) ),
			'phone'   => trim( (string) get_theme_mod( 'gat_contact_phone', '' ) ),
			'email'   => trim( (string) get_theme_mod( 'gat_contact_email', '' ) ),
			'hours'   => trim( (string) get_theme_mod( 'gat_contact_hours', '' ) ),
			'map'     => trim( (string) get_theme_mod( 'gat_contact_map', '' ) ),
		);
		$icon = fn( $n ) => function_exists( 'gat_icon' ) ? gat_icon( $n, 18 ) : '';

		ob_start();
		?>
		<div class="gat-contact-grid">
			<div class="gat-contact-card">
				<h2><?php esc_html_e( 'Contact Information', 'gat-core' ); ?></h2>
				<?php if ( $c['address'] || $c['phone'] || $c['email'] || $c['hours'] ) : ?>
					<ul>
						<?php if ( $c['address'] ) : ?><li><?php echo $icon( 'pin' ); // phpcs:ignore ?><span><?php echo nl2br( esc_html( $c['address'] ) ); ?></span></li><?php endif; ?>
						<?php if ( $c['phone'] ) : ?><li><?php echo $icon( 'phone' ); // phpcs:ignore ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $c['phone'] ) ); ?>"><?php echo esc_html( $c['phone'] ); ?></a></li><?php endif; ?>
						<?php if ( $c['email'] ) : ?><li><?php echo $icon( 'mail' ); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a></li><?php endif; ?>
						<?php if ( $c['hours'] ) : ?><li><?php echo $icon( 'clock' ); // phpcs:ignore ?><span><?php echo nl2br( esc_html( $c['hours'] ) ); ?></span></li><?php endif; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'Please use the form to get in touch and we will reply by email.', 'gat-core' ); ?></p>
				<?php endif; ?>
				<?php if ( function_exists( 'gat_social_links' ) ) { gat_social_links(); } ?>
				<?php if ( $c['map'] ) : ?>
					<h3><?php esc_html_e( 'Find Us', 'gat-core' ); ?></h3>
					<iframe class="gat-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php esc_attr_e( 'Map', 'gat-core' ); ?>"
						src="https://www.google.com/maps?output=embed&amp;q=<?php echo rawurlencode( $c['map'] ); ?>"></iframe>
					<p><small><?php esc_html_e( 'Please check the location and contact details before travelling.', 'gat-core' ); ?></small></p>
				<?php endif; ?>
			</div>
			<div>
				<h2 style="margin-top:0"><?php esc_html_e( 'Send Us a Message', 'gat-core' ); ?></h2>
				<p><?php esc_html_e( 'Please complete the form below and provide a brief description of your enquiry.', 'gat-core' ); ?></p>
				<?php echo do_shortcode( '[gat_enquiry_form type="contact"]' ); // phpcs:ignore ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
);
