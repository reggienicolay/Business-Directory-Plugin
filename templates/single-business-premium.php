<?php
/**
 * Single Business Template Router
 *
 * Routes to the appropriate detail page layout based on admin settings.
 * Supports: classic (original) and immersive (new V2 design)
 *
 * @package BusinessDirectory
 * @since 0.1.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the layout setting.
$bd_detail_layout = \BD\Admin\Settings::get_detail_layout();

// Add body class for layout-specific styling.
add_filter(
	'body_class',
	function ( $classes ) use ( $bd_detail_layout ) {
		$classes[] = 'bd-detail-layout-' . $bd_detail_layout;
		return $classes;
	}
);

// Route to the appropriate template.
switch ( $bd_detail_layout ) {
	case 'immersive':
		$template_file = BD_PLUGIN_DIR . 'templates/single-business/immersive.php';
		break;

	case 'classic':
	default:
		$template_file = BD_PLUGIN_DIR . 'templates/single-business/classic.php';
		break;
}

// Load the selected template.
if ( file_exists( $template_file ) ) {
	include $template_file;
} else {
	// Fallback: if the template file doesn't exist, load classic.
	$fallback = BD_PLUGIN_DIR . 'templates/single-business/classic.php';
	if ( file_exists( $fallback ) ) {
		include $fallback;
	} else {
		// Ultimate fallback: use theme's single template.
		get_header();
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		get_footer();
	}
}
