<?php
/**
 * Sprint 2 Week 2 - Search Infrastructure Loader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'BD_S2W2_LOADED' ) ) {
	return;
}
define( 'BD_S2W2_LOADED', true );

// Load Search classes
require_once plugin_dir_path( __FILE__ ) . '../src/Search/FilterHandler.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Search/QueryBuilder.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Search/Geocoder.php';

// Load Utils
require_once plugin_dir_path( __FILE__ ) . '../src/Utils/Cache.php';

// Load and initialize API
require_once plugin_dir_path( __FILE__ ) . '../src/API/BusinessEndpoint.php';

// Load and initialize Frontend
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/Filters.php';

// Enqueue scripts and styles
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		$plugin_url = plugin_dir_url( __DIR__ );

		// Check if we're on a directory page
		$has_directory = false;
		if ( is_a( $post, 'WP_Post' ) ) {
			$has_directory = has_shortcode( $post->post_content, 'business_filters' ) ||
						has_shortcode( $post->post_content, 'business_directory_complete' ) ||
						has_shortcode( $post->post_content, 'bd_directory' );
		}

		// Check if we're on a single business page
		$is_business_page = is_singular( 'bd_business' ) || is_singular( 'business' );

		// Load directory assets
		if ( $has_directory ) {
			// Enqueue Leaflet CSS
			wp_enqueue_style(
				'leaflet',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
				array(),
				'1.9.4'
			);

			// Enqueue Leaflet MarkerCluster CSS
			wp_enqueue_style(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
				array( 'leaflet' ),
				'1.5.3'
			);

			wp_enqueue_style(
				'leaflet-markercluster-default',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
				array( 'leaflet-markercluster' ),
				'1.5.3'
			);

			// Font Awesome
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				array(),
				'5.15.4'
			);

			// Enqueue filter CSS
			wp_enqueue_style(
				'bd-filters',
				$plugin_url . 'assets/css/filters-premium.css',
				array( 'font-awesome' ),
				'3.1.0'
			);

			// Enqueue Leaflet JS
			wp_enqueue_script(
				'leaflet',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
				array(),
				'1.9.4',
				true
			);

			// Enqueue Leaflet MarkerCluster JS
			wp_enqueue_script(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
				array( 'leaflet' ),
				'1.5.3',
				true
			);

			// Enqueue our unified directory JS
			wp_enqueue_script(
				'bd-directory',
				$plugin_url . 'assets/js/business-directory.js',
				array( 'jquery', 'leaflet', 'leaflet-markercluster' ),
				'2.1.0',
				true
			);

			// Pass API URL and nonce to JavaScript
			wp_localize_script(
				'bd-directory',
				'bdVars',
				array(
					'apiUrl' => rest_url( 'bd/v1/' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		// Load business detail page assets
		if ( $is_business_page ) {
			// Font Awesome (if not already loaded)
			if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
				wp_enqueue_style(
					'font-awesome',
					'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
					array(),
					'5.15.4'
				);
			}

			// Business detail premium CSS
			wp_enqueue_style(
				'bd-business-detail',
				$plugin_url . 'assets/css/business-detail-premium.css',
				array( 'font-awesome' ),
				'1.0.3'  // ← Bumped version for cache busting
			);

			// Enqueue Leaflet for the map (if not already loaded)
			if ( ! wp_script_is( 'leaflet', 'enqueued' ) ) {
				wp_enqueue_style(
					'leaflet',
					'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
					array(),
					'1.9.4'
				);

				wp_enqueue_script(
					'leaflet',
					'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
					array(),
					'1.9.4',
					true
				);
			}

			// Enqueue business detail JS
			wp_enqueue_script(
				'bd-business-detail',
				$plugin_url . 'assets/js/business-detail.js',
				array( 'jquery', 'leaflet' ),  // ← Added 'leaflet' dependency
				'1.0.1',  // ← Bumped version for cache busting
				true
			);
		}
	},
	20
);
