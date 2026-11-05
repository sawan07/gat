<?php
/**
 * News card (news listing).
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'news-card' ); ?>>
	<a class="news-card__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'gat-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="news-card__placeholder"><?php echo gat_icon( 'calendar', 36 ); // phpcs:ignore ?></span>
		<?php endif; ?>
	</a>
	<div class="news-card__body">
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
		<a class="text-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'gat' ); ?><span class="screen-reader-text">: <?php the_title(); ?></span> <?php echo gat_icon( 'arrow', 14 ); // phpcs:ignore ?></a>
	</div>
</article>
