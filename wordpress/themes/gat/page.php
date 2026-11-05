<?php
/**
 * Standard page. The subtitle under the title comes from the page's Excerpt
 * (in the editor sidebar), if one is set.
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
			'subtitle' => has_excerpt() ? get_the_excerpt() : '',
			'eyebrow'  => $post->post_parent ? get_the_title( $post->post_parent ) : '',
		)
	);
	?>
	<div class="wrap page-body">
		<article <?php post_class( 'entry' ); ?>>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	</div>
	<?php
endwhile;
get_footer();
