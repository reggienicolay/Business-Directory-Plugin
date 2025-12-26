<?php
/**
 * Sprint 2 Week 2 Script 2 - Filter UI Loader
 * This file is safe to include multiple times
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once
if ( defined( 'BD_S2W2_SCRIPT2_LOADED' ) ) {
	return;
}

define( 'BD_S2W2_SCRIPT2_LOADED', true );

// Load Frontend classes
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/Filters.php';

// Initialize
\BusinessDirectory\Frontend\Filters::init();

// Enqueue scripts and styles
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		// Check if page has our shortcodes
		$has_filters = false;
		if ( $post && has_shortcode( $post->post_content, 'business_filters' ) ) {
			$has_filters = true;
		}
		if ( $post && has_shortcode( $post->post_content, 'business_directory_complete' ) ) {
			$has_filters = true;
		}

		// Also load on specific templates
		if ( is_page_template( 'templates/directory.php' ) || is_singular( 'business' ) ) {
			$has_filters = true;
		}

		// Note: Scripts and styles are now handled by sprint2-week2-loader.php
		// This loader only initializes the Filters class
	},
	20
);
