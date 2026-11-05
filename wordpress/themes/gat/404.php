<?php
/**
 * Page not found.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page-header', null, array( 'title' => __( 'Page not found', 'gat' ), 'subtitle' => __( 'Sorry, we couldn’t find that page.', 'gat' ) ) );
?>
<div class="wrap page-body page-body--narrow">
	<div class="entry-content">
		<p><?php esc_html_e( 'It may have moved. Try searching, or go back to the homepage.', 'gat' ); ?></p>
		<?php get_search_form(); ?>
		<p><a class="btn btn--navy" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span><?php esc_html_e( 'Go to the homepage', 'gat' ); ?></span></a></p>
	</div>
</div>
<?php
get_footer();
