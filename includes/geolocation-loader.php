<?php
/**
 * Geolocation & Performance Loader
 *
 * Loads geocoding API, cache warmer, and geolocation scripts.
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BD_GEOLOCATION_LOADED' ) ) {
	return;
}

define( 'BD_GEOLOCATION_LOADED', true );

// Load Geocode API endpoint.
require_once plugin_dir_path( __FILE__ ) . '../src/API/GeocodeEndpoint.php';

// Initialize Geocode API.
\BusinessDirectory\API\GeocodeEndpoint::init();

// Load cache warmer.
require_once plugin_dir_path( __FILE__ ) . 'CacheWarmer.php';

// Enqueue geolocation scripts.
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		// Check if we're on a directory or business page.
		$should_load = is_page_template( 'templates/directory.php' ) ||
			is_singular( 'bd_business' ) ||
			is_singular( 'business' );

		// Also check for shortcodes.
		if ( ! $should_load && is_a( $post, 'WP_Post' ) ) {
			$should_load = has_shortcode( $post->post_content, 'business_filters' ) ||
				has_shortcode( $post->post_content, 'business_directory_complete' ) ||
				has_shortcode( $post->post_content, 'bd_directory' );
		}

		if ( ! $should_load ) {
			return;
		}

		$plugin_url = plugin_dir_url( __DIR__ );

		// Enqueue geolocation script (depends on directory JS).
		wp_enqueue_script(
			'bd-geolocation',
			$plugin_url . 'assets/js/geolocation.js',
			array( 'jquery', 'bd-directory' ),
			'1.0.0',
			true
		);

		// Enqueue enhanced map script (depends on Leaflet if present).
		$map_deps = array( 'jquery' );
		if ( wp_script_is( 'leaflet', 'registered' ) || wp_script_is( 'leaflet', 'enqueued' ) ) {
			$map_deps[] = 'leaflet';
		}

		wp_enqueue_script(
			'bd-map',
			$plugin_url . 'assets/js/directory-map.js',
			$map_deps,
			'1.0.0',
			true
		);
	},
	30
);
