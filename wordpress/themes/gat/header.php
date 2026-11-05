<?php
/**
 * Site header.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'gat' ); ?></a>

<header class="site-header">
	<div class="wrap site-header__bar">
		<?php gat_brand( 'header' ); ?>

		<nav class="main-nav" id="main-nav" aria-label="<?php esc_attr_e( 'Main', 'gat' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'main-nav__menu',
					'depth'          => 2,
					'fallback_cb'    => function () {
						echo '<ul class="main-nav__menu">';
						wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
						echo '</ul>';
					},
				)
			);
			?>
		</nav>

		<div class="site-header__actions">
			<a class="btn btn--donate" href="<?php echo esc_url( home_url( '/donate/' ) ); ?>"><?php echo gat_icon( 'heart', 15 ); // phpcs:ignore ?><span><?php esc_html_e( 'Donate', 'gat' ); ?></span></a>
			<button class="icon-btn search-toggle" type="button" aria-expanded="false" aria-controls="site-search" aria-label="<?php esc_attr_e( 'Search', 'gat' ); ?>"><?php echo gat_icon( 'search', 20 ); // phpcs:ignore ?></button>
			<button class="icon-btn menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="<?php esc_attr_e( 'Menu', 'gat' ); ?>"><?php echo gat_icon( 'menu', 22 ); // phpcs:ignore ?></button>
		</div>
	</div>
	<div class="site-search" id="site-search" hidden>
		<div class="wrap"><?php get_search_form(); ?></div>
	</div>
</header>

<main id="main" class="site-main">
