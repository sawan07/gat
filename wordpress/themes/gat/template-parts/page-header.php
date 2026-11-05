<?php
/**
 * Blue title band at the top of inner pages.
 *
 * @package gat
 */

defined( 'ABSPATH' ) || exit;

$gat_title    = $args['title'] ?? get_the_title();
$gat_subtitle = $args['subtitle'] ?? '';
$gat_eyebrow  = $args['eyebrow'] ?? '';
?>
<header class="page-hero">
	<div class="wrap">
		<?php if ( $gat_eyebrow ) : ?><p class="page-hero__eyebrow"><?php echo esc_html( $gat_eyebrow ); ?></p><?php endif; ?>
		<h1 class="page-hero__title"><?php echo esc_html( $gat_title ); ?></h1>
		<?php if ( $gat_subtitle ) : ?><p class="page-hero__subtitle"><?php echo esc_html( $gat_subtitle ); ?></p><?php endif; ?>
	</div>
</header>
