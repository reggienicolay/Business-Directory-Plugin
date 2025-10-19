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

		if ( $has_filters ) {
			// Enqueue filter CSS
			wp_enqueue_style(
				'bd-filters',
				plugins_url( 'assets/css/filters.css', __DIR__ ),
				array(),
				'1.0.0'
			);

			// Enqueue filter JS
			wp_enqueue_script(
				'bd-filters',
				plugins_url( 'assets/js/directory-filters.js', __DIR__ ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

			// Localize script with API URL
			wp_localize_script(
				'bd-filters',
				'bdVars',
				array(
					'apiUrl' => home_url( '/wp-json/bd/v1/' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	},
	20
);
