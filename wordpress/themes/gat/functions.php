<?php
/**
 * Global Alliance Trust theme.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

define( 'GAT_THEME_VERSION', '1.0.1' );

require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Theme setup.
 */
function gat_setup() {
	load_theme_textdomain( 'gat', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 128,
			'width'       => 128,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( array( 'assets/css/editor.css' ) );

	register_nav_menus(
		array(
			'primary' => __( 'Main menu (header)', 'gat' ),
			'footer'  => __( 'Quick links (footer)', 'gat' ),
			'legal'   => __( 'Legal links (footer)', 'gat' ),
		)
	);

	add_image_size( 'gat-card', 640, 420, true );
	add_image_size( 'gat-thumb', 240, 200, true );

	// Brand colours in the editor's colour picker.
	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => __( 'Navy', 'gat' ), 'slug' => 'navy', 'color' => '#0b3a75' ),
			array( 'name' => __( 'Blue', 'gat' ), 'slug' => 'blue', 'color' => '#1d7ce0' ),
			array( 'name' => __( 'Sky', 'gat' ), 'slug' => 'sky', 'color' => '#e9f3fd' ),
			array( 'name' => __( 'Green', 'gat' ), 'slug' => 'green', 'color' => '#2e8b3e' ),
			array( 'name' => __( 'Teal', 'gat' ), 'slug' => 'teal', 'color' => '#0f8a97' ),
			array( 'name' => __( 'Ink', 'gat' ), 'slug' => 'ink', 'color' => '#122540' ),
			array( 'name' => __( 'White', 'gat' ), 'slug' => 'white', 'color' => '#ffffff' ),
		)
	);
	add_theme_support( 'disable-custom-colors' );
	add_theme_support( 'disable-custom-gradients' );
	add_theme_support( 'editor-gradient-presets', array() );
}
add_action( 'after_setup_theme', 'gat_setup' );

/**
 * Front-end styles and scripts.
 */
function gat_assets() {
	wp_enqueue_style(
		'gat-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Montserrat:wght@600;700;800&family=Lora:wght@600;700&family=Dancing+Script:wght@600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'gat-theme', get_theme_file_uri( 'assets/css/theme.css' ), array( 'gat-fonts' ), GAT_THEME_VERSION );
	wp_enqueue_script( 'gat-theme', get_theme_file_uri( 'assets/js/theme.js' ), array(), GAT_THEME_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
}
add_action( 'wp_enqueue_scripts', 'gat_assets' );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function gat_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		$urls[] = 'https://fonts.googleapis.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'gat_resource_hints', 10, 2 );

/**
 * Shorter excerpts for news cards.
 */
add_filter( 'excerpt_length', fn() => 22 );
add_filter( 'excerpt_more', fn() => '…' );

/**
 * Add a caret marker and keyboard-friendly toggle to menu items with children.
 *
 * @param string   $output Item output.
 * @param WP_Post  $item   Menu item.
 * @param int      $depth  Depth.
 * @param stdClass $args   Args.
 * @return string
 */
function gat_menu_caret( $output, $item, $depth, $args ) {
	if ( isset( $args->theme_location ) && 'primary' === $args->theme_location && in_array( 'menu-item-has-children', (array) $item->classes, true ) && 0 === $depth ) {
		$output .= '<button class="submenu-toggle" type="button" aria-expanded="false" aria-label="' . esc_attr( sprintf( /* translators: %s menu item */ __( 'Show %s submenu', 'gat' ), wp_strip_all_tags( $item->title ) ) ) . '"><span aria-hidden="true">▾</span></button>';
	}
	return $output;
}
add_filter( 'walker_nav_menu_start_el', 'gat_menu_caret', 10, 4 );

/**
 * Body classes.
 *
 * @param array $classes Classes.
 * @return array
 */
function gat_body_class( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'gat-home';
	}
	return $classes;
}
add_filter( 'body_class', 'gat_body_class' );

/**
 * Block styles offered in the editor's "Styles" panel, so editors can make
 * on-brand panels, cards, tick lists and numbered steps without custom CSS.
 */
function gat_block_styles() {
	register_block_style( 'core/group', array( 'name' => 'gat-panel', 'label' => __( 'White panel', 'gat' ) ) );
	register_block_style( 'core/group', array( 'name' => 'gat-highlight', 'label' => __( 'Blue highlight', 'gat' ) ) );
	register_block_style( 'core/paragraph', array( 'name' => 'gat-note', 'label' => __( 'Yellow note', 'gat' ) ) );
	register_block_style( 'core/columns', array( 'name' => 'gat-cards', 'label' => __( 'Cards', 'gat' ) ) );
	register_block_style( 'core/list', array( 'name' => 'gat-checks', 'label' => __( 'Tick list', 'gat' ) ) );
	register_block_style( 'core/list', array( 'name' => 'gat-steps', 'label' => __( 'Numbered steps', 'gat' ) ) );
}
add_action( 'init', 'gat_block_styles' );

/**
 * Load brand fonts inside the block editor too.
 */
function gat_editor_fonts() {
	wp_enqueue_style(
		'gat-editor-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Montserrat:wght@600;700;800&family=Lora:wght@600;700&display=swap',
		array(),
		null
	);
}
add_action( 'enqueue_block_editor_assets', 'gat_editor_fonts' );
