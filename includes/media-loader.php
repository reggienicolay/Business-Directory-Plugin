<?php
/**
 * Media Optimization Loader
 *
 * Initializes the image optimization pipeline: custom sizes, WebP
 * generation, and EXIF stripping for all uploaded images.
 *
 * IMPORTANT: ImageOptimizer::init() is called immediately (not deferred
 * to an action hook) because it registers an after_setup_theme callback.
 * That hook fires before init/plugins_loaded, so deferring would miss it.
 *
 * @package    BusinessDirectory
 * @subpackage Media
 * @since      0.2.0
 */

namespace BD\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent multiple loads.
if ( defined( 'BD_MEDIA_LOADER_LOADED' ) ) {
	return;
}
define( 'BD_MEDIA_LOADER_LOADED', true );

// Class map — defensive loading with file_exists checks.
$bd_media_classes = array(
	'ImageOptimizer' => 'src/Media/ImageOptimizer.php',
);

foreach ( $bd_media_classes as $class_name => $class_path ) {
	$full_path = BD_PLUGIN_DIR . $class_path;
	if ( file_exists( $full_path ) ) {
		require_once $full_path;
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'BD Media: Missing class file — ' . $class_path );
	}
}

// Initialize immediately — after_setup_theme must be registered before it fires.
if ( class_exists( __NAMESPACE__ . '\\ImageOptimizer' ) ) {
	ImageOptimizer::init();
}
