<?php
/**
 * Compact news card (homepage strip).
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

$gat_p = $args['post'] ?? get_post();
?>
<article class="news-mini">
	<a class="news-mini__img" href="<?php echo esc_url( get_permalink( $gat_p ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail( $gat_p ) ) : ?>
			<?php echo get_the_post_thumbnail( $gat_p, 'gat-thumb', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="news-mini__placeholder"><?php echo gat_icon( 'calendar', 26 ); // phpcs:ignore ?></span>
		<?php endif; ?>
	</a>
	<div class="news-mini__body">
		<time datetime="<?php echo esc_attr( get_the_date( 'c', $gat_p ) ); ?>"><?php echo esc_html( get_the_date( 'j M Y', $gat_p ) ); ?></time>
		<h3><a href="<?php echo esc_url( get_permalink( $gat_p ) ); ?>"><?php echo esc_html( get_the_title( $gat_p ) ); ?></a></h3>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $gat_p ), 12, '…' ) ); ?></p>
	</div>
</article>
