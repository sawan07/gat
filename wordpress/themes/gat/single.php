<?php
/**
 * Single news / event article.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'eyebrow'  => __( 'News & Events', 'gat' ),
			'subtitle' => get_the_date( 'j F Y' ),
		)
	);
	?>
	<div class="wrap page-body page-body--narrow">
		<article <?php post_class( 'entry' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="entry-featured"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
			<nav class="post-nav" aria-label="<?php esc_attr_e( 'More news', 'gat' ); ?>">
				<?php
				previous_post_link( '<span class="post-nav__prev">%link</span>', '← %title' );
				next_post_link( '<span class="post-nav__next">%link</span>', '%title →' );
				?>
			</nav>
		</article>
	</div>
	<?php
endwhile;
get_footer();
