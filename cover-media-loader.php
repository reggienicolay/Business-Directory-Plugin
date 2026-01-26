<?php
/**
 * Cover Media Feature Loader
 *
 * Initializes all cover media components.
 * Include this file from your main plugin loader.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\CoverMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize cover media feature
 */
function init() {
	$base_path = __DIR__;

	// Load components FIRST (before trying to use them)
	require_once $base_path . '/includes/class-cover-media-migration.php';
	require_once $base_path . '/src/Lists/CoverManager.php';
	require_once $base_path . '/src/API/CoverEndpoint.php';
	require_once $base_path . '/src/Frontend/NetworkLists.php';
	require_once $base_path . '/src/Frontend/CoverEditorAssets.php';
	require_once $base_path . '/src/Frontend/ListSocialMeta.php';

	// Admin components
	if ( is_admin() ) {
		require_once $base_path . '/src/Admin/CoverModeration.php';
	}

	// Run database migration AFTER class is loaded
	\BD\Includes\CoverMediaMigration::maybe_migrate();

	// Register video lightbox assets
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_video_lightbox' );

	// Add Content-Security-Policy for video embeds
	add_action( 'send_headers', __NAMESPACE__ . '\add_csp_headers' );
}

/**
 * Register video lightbox assets
 */
function register_video_lightbox() {
	$version = defined( 'BD_VERSION' ) ? BD_VERSION : '1.2.0';

	wp_register_style(
		'bd-video-lightbox',
		plugins_url( 'assets/css/video-lightbox.css', __FILE__ ),
		array(),
		$version
	);

	wp_register_script(
		'bd-video-lightbox',
		plugins_url( 'assets/js/video-lightbox.js', __FILE__ ),
		array( 'jquery' ),
		$version,
		true
	);

	// Enqueue on list pages
	if ( is_singular() || get_query_var( 'bd_list' ) ) {
		wp_enqueue_style( 'bd-video-lightbox' );
		wp_enqueue_script( 'bd-video-lightbox' );
	}
}

/**
 * Add Content-Security-Policy headers for video embeds
 *
 * This provides defense-in-depth against XSS attacks that might
 * try to inject malicious iframe sources.
 */
function add_csp_headers() {
	// Only add on list pages where video covers might be displayed
	if ( ! is_singular() && ! get_query_var( 'bd_list' ) ) {
		return;
	}

	// Don't override if CSP is already set
	$headers = headers_list();
	foreach ( $headers as $header ) {
		if ( stripos( $header, 'Content-Security-Policy' ) !== false ) {
			return;
		}
	}

	// Allow video embeds from YouTube and Vimeo only
	$frame_src = implode(
		' ',
		array(
			"'self'",
			'https://www.youtube-nocookie.com',
			'https://www.youtube.com',
			'https://player.vimeo.com',
		)
	);

	header( "Content-Security-Policy: frame-src {$frame_src};" );
}

// Hook initialization - use priority 5 to run before other things at default priority
add_action( 'plugins_loaded', __NAMESPACE__ . '\init', 5 );

/**
 * Activation hook - run migration
 */
function activate() {
	require_once __DIR__ . '/includes/class-cover-media-migration.php';
	\BD\Includes\CoverMediaMigration::migrate();
}

/**
 * Get feature status for admin display
 *
 * @return array Feature status.
 */
function get_status() {
	require_once __DIR__ . '/includes/class-cover-media-migration.php';

	return array(
		'migration' => \BD\Includes\CoverMediaMigration::get_status(),
		'version'   => '1.2.0',
		'features'  => array(
			'image_upload'     => true,
			'image_crop'       => true,
			'video_covers'     => true,
			'video_lightbox'   => true,
			'network_lists'    => true,
			'city_filter_api'  => true,
			'social_meta'      => true,
			'admin_moderation' => true,
			'csp_headers'      => true,
		),
	);
}
