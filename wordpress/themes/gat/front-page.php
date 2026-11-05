<?php
/**
 * Homepage. Every text, photo and link here is edited in
 * Appearance → Customize → Homepage.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

get_header();

$gat_news = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => max( 6, (int) gat_home( 'news_count' ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
$gat_news_posts = $gat_news->posts;

$gat_hero_badge = array_filter( array_map( 'trim', explode( "\n", (string) gat_home( 'hero_badge' ) ) ) );
?>

<section class="hero" style="--hero-img:url('<?php echo esc_url( gat_home_image( 'hero_image', 'hero.jpg', 'full' ) ); ?>')">
	<div class="wrap hero__inner">
		<div class="hero__content">
			<?php if ( gat_home( 'hero_eyebrow' ) ) : ?>
				<p class="hero__eyebrow"><?php echo esc_html( gat_home( 'hero_eyebrow' ) ); ?></p>
			<?php endif; ?>
			<h1 class="hero__title"><?php echo esc_html( gat_home( 'hero_title' ) ); ?></h1>
			<?php if ( gat_home( 'hero_script' ) ) : ?>
				<p class="hero__script"><?php echo esc_html( gat_home( 'hero_script' ) ); ?></p>
			<?php endif; ?>
			<p class="hero__text"><?php echo esc_html( gat_home( 'hero_text' ) ); ?></p>
			<div class="hero__buttons">
				<?php if ( gat_home( 'hero_btn1_label' ) ) : ?>
					<a class="btn btn--primary btn--lg" href="<?php echo esc_url( gat_url( gat_home( 'hero_btn1_url' ) ) ); ?>"><?php echo gat_icon( 'heart', 17 ); // phpcs:ignore ?><span><?php echo esc_html( gat_home( 'hero_btn1_label' ) ); ?></span><?php echo gat_icon( 'arrow', 17 ); // phpcs:ignore ?></a>
				<?php endif; ?>
				<?php if ( gat_home( 'hero_btn2_label' ) ) : ?>
					<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( gat_url( gat_home( 'hero_btn2_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'hero_btn2_label' ) ); ?></span><?php echo gat_icon( 'arrow', 17 ); // phpcs:ignore ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $gat_hero_badge ) : ?>
			<p class="hero__badge" aria-hidden="true"><?php echo implode( '<br>', array_map( 'esc_html', $gat_hero_badge ) ); // phpcs:ignore ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( gat_home( 'ticker_show' ) && $gat_news_posts ) : ?>
<section class="ticker" aria-label="<?php echo esc_attr( gat_home( 'ticker_label' ) ); ?>">
	<div class="wrap ticker__inner">
		<a class="ticker__label" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>"><?php echo gat_icon( 'megaphone', 18 ); // phpcs:ignore ?><span><?php echo esc_html( gat_home( 'ticker_label' ) ); ?></span></a>
		<div class="ticker__viewport" data-ticker>
			<ul class="ticker__track">
				<?php foreach ( array_slice( $gat_news_posts, 0, 6 ) as $gat_p ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $gat_p ) ); ?>"><?php echo esc_html( get_the_title( $gat_p ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="ticker__controls">
			<button class="round-btn" type="button" data-ticker-prev aria-label="<?php esc_attr_e( 'Previous', 'gat' ); ?>"><?php echo gat_icon( 'chevron-l', 16 ); // phpcs:ignore ?></button>
			<button class="round-btn" type="button" data-ticker-pause aria-label="<?php esc_attr_e( 'Pause', 'gat' ); ?>" aria-pressed="false"><?php echo gat_icon( 'pause', 16 ); // phpcs:ignore ?></button>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section--about">
	<div class="wrap about-grid<?php echo gat_advertisers() ? '' : ' no-ads'; ?>">
		<div class="about-card" style="--about-img:url('<?php echo esc_url( gat_home_image( 'about_image', 'about.jpg' ) ); ?>')">
			<div class="about-card__content">
				<p class="eyebrow"><?php echo esc_html( gat_home( 'about_eyebrow' ) ); ?></p>
				<h2 class="display"><?php echo esc_html( gat_home( 'about_title' ) ); ?></h2>
				<p><?php echo esc_html( gat_home( 'about_text' ) ); ?></p>
				<?php if ( gat_home( 'about_btn_label' ) ) : ?>
					<a class="btn btn--navy" href="<?php echo esc_url( gat_url( gat_home( 'about_btn_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'about_btn_label' ) ); ?></span><?php echo gat_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php $gat_ads = gat_advertisers(); ?>
		<?php if ( $gat_ads ) : ?>
		<aside class="ads-card" aria-label="<?php echo esc_attr( gat_home( 'ads_title' ) ); ?>">
			<div class="ads-card__head">
				<h2><?php echo esc_html( gat_home( 'ads_title' ) ); ?></h2>
				<a href="<?php echo esc_url( home_url( '/advertising/' ) ); ?>"><?php esc_html_e( 'View All', 'gat' ); ?></a>
			</div>
			<div class="ads-carousel" data-carousel>
				<ul class="ads-carousel__track">
					<?php foreach ( $gat_ads as $gat_ad ) : ?>
						<li class="ad-tile">
							<?php if ( $gat_ad['url'] ) : ?><a href="<?php echo esc_url( $gat_ad['url'] ); ?>" target="_blank" rel="noopener sponsored"><?php else : ?><span><?php endif; ?>
								<?php if ( $gat_ad['logo'] ) : ?>
									<img src="<?php echo esc_url( $gat_ad['logo'] ); ?>" alt="<?php echo esc_attr( $gat_ad['name'] ); ?>" loading="lazy">
								<?php else : ?>
									<strong><?php echo esc_html( $gat_ad['name'] ); ?></strong>
								<?php endif; ?>
							<?php echo $gat_ad['url'] ? '</a>' : '</span>'; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="ads-card__controls">
				<button class="round-btn" type="button" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous advertisers', 'gat' ); ?>"><?php echo gat_icon( 'chevron-l', 16 ); // phpcs:ignore ?></button>
				<span class="dots" data-carousel-dots aria-hidden="true"></span>
				<button class="round-btn" type="button" data-carousel-next aria-label="<?php esc_attr_e( 'Next advertisers', 'gat' ); ?>"><?php echo gat_icon( 'chevron-r', 16 ); // phpcs:ignore ?></button>
			</div>
		</aside>
		<?php endif; ?>
	</div>
</section>

<section class="section section--services">
	<div class="wrap">
		<h2 class="section-title"><?php echo esc_html( gat_home( 'services_title' ) ); ?></h2>
		<div class="service-grid">
			<?php
			$gat_cards = array(
				array( 'svc1', 'blue', 'book' ),
				array( 'svc2', 'green', 'wellbeing' ),
				array( 'svc3', 'teal', 'community' ),
			);
			foreach ( $gat_cards as list( $gat_k, $gat_color, $gat_icon ) ) :
				?>
				<article class="service-card service-card--<?php echo esc_attr( $gat_color ); ?>">
					<span class="service-card__icon"><?php echo gat_icon( $gat_icon, 32 ); // phpcs:ignore ?></span>
					<div class="service-card__body">
						<h3><?php echo esc_html( gat_home( $gat_k . '_title' ) ); ?></h3>
						<p><?php echo esc_html( gat_home( $gat_k . '_text' ) ); ?></p>
						<a class="pill-link" href="<?php echo esc_url( gat_url( gat_home( $gat_k . '_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'svc_more_label' ) ); ?></span><span class="screen-reader-text">: <?php echo esc_html( gat_home( $gat_k . '_title' ) ); ?></span><?php echo gat_icon( 'arrow', 15 ); // phpcs:ignore ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( gat_home( 'services_btn_label' ) ) : ?>
			<p class="section-more"><a class="btn btn--soft" href="<?php echo esc_url( gat_url( gat_home( 'services_btn_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'services_btn_label' ) ); ?></span><?php echo gat_icon( 'arrow', 15 ); // phpcs:ignore ?></a></p>
		<?php endif; ?>
	</div>
</section>

<section class="section section--academy">
	<div class="wrap academy-grid">
		<div class="academy-card">
			<div class="academy-card__content">
				<p class="eyebrow"><?php echo esc_html( gat_home( 'academy_eyebrow' ) ); ?></p>
				<h2 class="display academy-title">
					<?php
					$gat_parts = array_map( 'trim', explode( '|', (string) gat_home( 'academy_title' ) ) );
					echo implode( '<span class="bar" aria-hidden="true">|</span>', array_map( 'esc_html', $gat_parts ) ); // phpcs:ignore
					?>
				</h2>
				<p><?php echo esc_html( gat_home( 'academy_text' ) ); ?></p>
				<?php $gat_points = array_filter( array_map( 'trim', explode( "\n", (string) gat_home( 'academy_points' ) ) ) ); ?>
				<?php if ( $gat_points ) : ?>
					<ul class="checklist">
						<?php foreach ( $gat_points as $gat_pt ) : ?>
							<li><?php echo gat_icon( 'check', 14 ); // phpcs:ignore ?><?php echo esc_html( $gat_pt ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( gat_home( 'academy_btn_label' ) ) : ?>
					<a class="btn btn--navy" href="<?php echo esc_url( gat_url( gat_home( 'academy_btn_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'academy_btn_label' ) ); ?></span><?php echo gat_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
				<?php endif; ?>
			</div>
			<div class="academy-card__image" style="background-image:url('<?php echo esc_url( gat_home_image( 'academy_image', 'academy.jpg' ) ); ?>')" role="img" aria-label="<?php esc_attr_e( 'A child reading at the Sunnah Academy', 'gat' ); ?>"></div>
		</div>
		<aside class="side-card">
			<span class="side-card__icon"><?php echo gat_icon( 'people', 30 ); // phpcs:ignore ?></span>
			<h2><?php echo esc_html( gat_home( 'academy_card_title' ) ); ?></h2>
			<p><?php echo esc_html( gat_home( 'academy_card_text' ) ); ?></p>
			<?php if ( gat_home( 'academy_card_btn' ) ) : ?>
				<a class="btn btn--outline" href="<?php echo esc_url( gat_url( gat_home( 'academy_card_url' ) ) ); ?>"><span><?php echo esc_html( gat_home( 'academy_card_btn' ) ); ?></span><?php echo gat_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
			<?php endif; ?>
		</aside>
	</div>
</section>

<?php if ( $gat_news_posts ) : ?>
<section class="section section--news">
	<div class="wrap">
		<div class="section-head">
			<h2 class="section-title section-title--sm"><?php echo esc_html( gat_home( 'news_title' ) ); ?></h2>
			<?php if ( get_option( 'page_for_posts' ) ) : ?>
				<a class="text-link" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'View all', 'gat' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="news-strip">
			<?php foreach ( array_slice( $gat_news_posts, 0, max( 1, (int) gat_home( 'news_count' ) ) ) as $gat_p ) : ?>
				<?php get_template_part( 'template-parts/news-card', 'mini', array( 'post' => $gat_p ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php
get_footer();
