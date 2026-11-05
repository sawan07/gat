<?php
/**
 * Site footer.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

$gat_contact = gat_contact();
$gat_has_contact = $gat_contact['address'] || $gat_contact['phone'] || $gat_contact['email'];
?>
</main>

<footer class="site-footer">
	<div class="wrap site-footer__grid<?php echo $gat_has_contact ? '' : ' no-contact'; ?>">
		<div class="site-footer__brand">
			<?php gat_brand( 'footer' ); ?>
			<p class="site-footer__about"><?php esc_html_e( 'Supporting the community through education, welfare, wellbeing and charitable initiatives.', 'gat' ); ?></p>
			<?php gat_social_links( 'gat-socials gat-socials--footer' ); ?>
		</div>

		<div class="site-footer__links">
			<h2 class="site-footer__title"><?php esc_html_e( 'Quick Links', 'gat' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'quick-links',
					'depth'          => 1,
					'fallback_cb'    => function () {
						echo '<ul class="quick-links">';
						wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
						echo '</ul>';
					},
				)
			);
			?>
		</div>

		<?php if ( $gat_has_contact ) : ?>
		<div class="site-footer__contact">
			<h2 class="site-footer__title"><?php esc_html_e( 'Contact Us', 'gat' ); ?></h2>
			<ul class="contact-list">
				<?php if ( $gat_contact['address'] ) : ?>
					<li><?php echo gat_icon( 'pin', 18 ); // phpcs:ignore ?><span><?php echo nl2br( esc_html( $gat_contact['address'] ) ); ?></span></li>
				<?php endif; ?>
				<?php if ( $gat_contact['email'] ) : ?>
					<li><?php echo gat_icon( 'mail', 18 ); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( $gat_contact['email'] ); ?>"><?php echo esc_html( $gat_contact['email'] ); ?></a></li>
				<?php endif; ?>
				<?php if ( $gat_contact['phone'] ) : ?>
					<li><?php echo gat_icon( 'phone', 18 ); // phpcs:ignore ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $gat_contact['phone'] ) ); ?>"><?php echo esc_html( $gat_contact['phone'] ); ?></a></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php endif; ?>
	</div>

	<div class="wrap site-footer__bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'gat' ); ?></p>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'legal',
				'container'      => 'nav',
				'container_class' => 'legal-nav',
				'container_aria_label' => __( 'Legal', 'gat' ),
				'menu_class'     => 'legal-links',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
