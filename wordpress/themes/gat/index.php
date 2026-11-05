<?php
/**
 * News & Events listing, archives and search results.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_search() ) {
	/* translators: %s search term */
	$gat_title = sprintf( __( 'Search results for “%s”', 'gat' ), get_search_query() );
	$gat_sub   = '';
} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
	$gat_title = get_the_title( get_option( 'page_for_posts' ) );
	$gat_sub   = has_excerpt( get_option( 'page_for_posts' ) ) ? get_the_excerpt( get_option( 'page_for_posts' ) ) : __( 'Discover the latest news, announcements and activities from Global Alliance Trust.', 'gat' );
} elseif ( is_archive() ) {
	$gat_title = wp_strip_all_tags( get_the_archive_title() );
	$gat_sub   = wp_strip_all_tags( get_the_archive_description() );
} else {
	$gat_title = __( 'News & Events', 'gat' );
	$gat_sub   = '';
}
get_template_part( 'template-parts/page-header', null, array( 'title' => $gat_title, 'subtitle' => $gat_sub ) );
?>
<div class="wrap page-body">
	<?php if ( have_posts() ) : ?>
		<div class="news-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				if ( is_search() && 'post' !== get_post_type() ) :
					?>
					<article class="news-card news-card--plain">
						<div class="news-card__body">
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						</div>
					</article>
					<?php
				else :
					get_template_part( 'template-parts/news-card' );
				endif;
			endwhile;
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 1,
				'prev_text' => __( 'Newer', 'gat' ),
				'next_text' => __( 'Older', 'gat' ),
			)
		);
		?>
	<?php else : ?>
		<div class="entry-content">
			<p><?php echo is_search() ? esc_html__( 'Nothing matched your search. Try different words.', 'gat' ) : esc_html__( 'No news yet. Please check back soon.', 'gat' ); ?></p>
			<?php if ( is_search() ) { get_search_form(); } ?>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
