<?php
/**
 * Social Sharing System Loader
 *
 * Loads all social sharing related classes for viral growth features.
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once.
if ( defined( 'BD_SOCIAL_SHARING_LOADED' ) ) {
	return;
}
define( 'BD_SOCIAL_SHARING_LOADED', true );

// Define base path for this module.
$bd_social_path = plugin_dir_path( __FILE__ ) . '../src/Social/';

// Check if files exist before loading.
$social_files = array(
	'ShareButtons.php',
	'ShareTracker.php',
	'OpenGraph.php',
	'ImageGenerator.php',
);

foreach ( $social_files as $file ) {
	$file_path = $bd_social_path . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// Initialize components only if classes exist.
if ( class_exists( 'BD\Social\ShareButtons' ) ) {
	new BD\Social\ShareButtons();
}
if ( class_exists( 'BD\Social\ShareTracker' ) ) {
	new BD\Social\ShareTracker();
}
if ( class_exists( 'BD\Social\OpenGraph' ) ) {
	new BD\Social\OpenGraph();
}

// Enqueue social sharing assets.
add_action(
	'wp_enqueue_scripts',
	function () {
		$plugin_url = plugin_dir_url( __DIR__ );

		// Check if we're on a page that needs share buttons.
		$needs_sharing = false;

		// Business detail pages.
		if ( is_singular( 'bd_business' ) || is_singular( 'business' ) ) {
			$needs_sharing = true;
		}

		// User profile pages.
		global $post;
		if ( is_a( $post, 'WP_Post' ) ) {
			if ( has_shortcode( $post->post_content, 'bd_profile' ) ||
				has_shortcode( $post->post_content, 'bd_user_profile' ) ||
				has_shortcode( $post->post_content, 'user_profile' ) ) {
				$needs_sharing = true;
			}
		}

		// Directory pages (for sharing individual listings).
		if ( is_a( $post, 'WP_Post' ) ) {
			if ( has_shortcode( $post->post_content, 'bd_directory' ) ||
				has_shortcode( $post->post_content, 'business_directory_complete' ) ) {
				$needs_sharing = true;
			}
		}

		if ( $needs_sharing ) {
			// Social sharing CSS.
			wp_enqueue_style(
				'bd-social-sharing',
				$plugin_url . 'assets/css/social-sharing.css',
				array(),
				'1.0.0'
			);

			// Social sharing JS.
			wp_enqueue_script(
				'bd-social-sharing',
				$plugin_url . 'assets/js/social-sharing.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);

			// Localize script.
			wp_localize_script(
				'bd-social-sharing',
				'bdShare',
				array(
					'restUrl'  => rest_url( 'bd/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'siteUrl'  => home_url(),
					'siteName' => get_bloginfo( 'name' ),
					'userId'   => get_current_user_id(),
					'i18n'     => array(
						'copied'       => __( 'Link copied!', 'business-directory' ),
						'shareFailed'  => __( 'Share failed. Please try again.', 'business-directory' ),
						'shareSuccess' => __( 'Thanks for sharing!', 'business-directory' ),
					),
				)
			);
		}
	},
	25
);
