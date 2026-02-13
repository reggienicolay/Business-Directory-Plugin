<?php
/**
 * Directory Assets Loader
 *
 * Loads search infrastructure, filters, and directory page assets.
 * Now includes layout-aware business detail page asset loading.
 *
 * @package BusinessDirectory
 * @since 0.1.7 - Added detail layout support
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BD_DIRECTORY_LOADED' ) ) {
	return;
}

define( 'BD_DIRECTORY_LOADED', true );

// Load Search classes.
require_once plugin_dir_path( __FILE__ ) . '../src/Search/FilterHandler.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Search/QueryBuilder.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Search/Geocoder.php';

// Load Utils.
require_once plugin_dir_path( __FILE__ ) . '../src/Utils/Cache.php';

// Load and initialize API.
require_once plugin_dir_path( __FILE__ ) . '../src/API/BusinessEndpoint.php';

require_once plugin_dir_path( __FILE__ ) . 'placeholder-image-helper.php';

// Load and initialize Frontend Filters.
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/Filters.php';
\BusinessDirectory\Frontend\Filters::init();

// Enqueue scripts and styles.
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		$plugin_url = plugin_dir_url( __DIR__ );

		// Check if we're on a directory page.
		$has_directory = false;
		if ( is_a( $post, 'WP_Post' ) ) {
			$has_directory = has_shortcode( $post->post_content, 'business_filters' ) ||
						has_shortcode( $post->post_content, 'business_directory_complete' ) ||
						has_shortcode( $post->post_content, 'bd_directory' );
		}

		// Check if we're on a single business page.
		$is_business_page = is_singular( 'bd_business' ) || is_singular( 'business' );

		// Load directory assets.
		if ( $has_directory ) {
			// Enqueue Leaflet CSS.
			wp_enqueue_style(
				'leaflet',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
				array(),
				'1.9.4'
			);

			// Enqueue Leaflet MarkerCluster CSS.
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

			// Font Awesome.
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				array(),
				'5.15.4'
			);

			// Enqueue filter CSS.
			wp_enqueue_style(
				'bd-filters',
				$plugin_url . 'assets/css/filters-premium.css',
				array( 'font-awesome' ),
				'3.2.0'
			);

			// Enqueue map popup CSS.
			wp_enqueue_style(
				'bd-map-popup',
				$plugin_url . 'assets/css/map-popup.css',
				array( 'bd-filters' ),
				'1.0.0'
			);

			// Enqueue map marker CSS.
			wp_enqueue_style(
				'bd-map-markers',
				$plugin_url . 'assets/css/map-markers.css',
				array( 'bd-filters' ),
				'1.0.0'
			);

			// Add body class for detailed popup mode if enabled.
			$popup_style = apply_filters( 'bd_popup_style', 'minimal' );
			if ( 'detailed' === $popup_style ) {
				add_filter(
					'body_class',
					function ( $classes ) {
						$classes[] = 'bd-popup-detailed';
						return $classes;
					}
				);
			}

			// Enqueue Leaflet JS.
			wp_enqueue_script(
				'leaflet',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
				array(),
				'1.9.4',
				true
			);

			// Enqueue Leaflet MarkerCluster JS.
			wp_enqueue_script(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
				array( 'leaflet' ),
				'1.5.3',
				true
			);

			// Enqueue our unified directory JS.
			wp_enqueue_script(
				'bd-directory',
				$plugin_url . 'assets/js/business-directory.js',
				array( 'jquery', 'leaflet', 'leaflet-markercluster' ),
				'2.2.0',
				true
			);

			// Pass API URL, nonce, and popup style to JavaScript.
			wp_localize_script(
				'bd-directory',
				'bdVars',
				array(
					'apiUrl'     => rest_url( 'bd/v1/' ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'popupStyle' => apply_filters( 'bd_popup_style', 'minimal' ),
				)
			);
		}

		// =====================================================================
		// BUSINESS DETAIL PAGE ASSETS - Layout Aware
		// =====================================================================
		if ( $is_business_page ) {
			// Get the active detail layout.
			$detail_layout = \BD\Admin\Settings::get_detail_layout();

			// Font Awesome (if not already loaded).
			if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
				wp_enqueue_style(
					'font-awesome',
					'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
					array(),
					'5.15.4'
				);
			}

			// Design tokens (shared by all layouts).
			wp_enqueue_style(
				'bd-design-tokens',
				$plugin_url . 'assets/css/design-tokens.css',
				array(),
				BD_VERSION
			);

			// Enqueue Leaflet for the map (if not already loaded).
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

			// =====================================================================
			// LAYOUT-SPECIFIC ASSETS
			// =====================================================================
			if ( 'immersive' === $detail_layout ) {
				// Immersive Layout (V2) - Photo hero with parallax.
				wp_enqueue_style(
					'bd-business-detail',
					$plugin_url . 'assets/css/business-detail-immersive.css',
					array( 'font-awesome', 'bd-design-tokens' ),
					BD_VERSION
				);

				// Shared business detail JS (lightbox, reviews, map).
				wp_enqueue_script(
					'bd-business-detail',
					$plugin_url . 'assets/js/business-detail.js',
					array( 'jquery', 'leaflet' ),
					BD_VERSION,
					true
				);

				// Immersive-specific JS (parallax, save toggle, star rating).
				wp_enqueue_script(
					'bd-business-detail-immersive',
					$plugin_url . 'assets/js/business-detail-immersive.js',
					array( 'jquery', 'bd-business-detail' ),
					BD_VERSION,
					true
				);

				// Localize immersive script.
				wp_localize_script(
					'bd-business-detail-immersive',
					'bdImmersiveVars',
					array(
						'restUrl' => rest_url( 'bd/v1/' ),
						'nonce'   => wp_create_nonce( 'wp_rest' ),
						'strings' => array(
							'save'   => __( 'Save', 'business-directory' ),
							'saved'  => __( 'Saved', 'business-directory' ),
							'copied' => __( 'Link copied!', 'business-directory' ),
						),
					)
				);

			} else {
				// Classic Layout - Gradient hero with traditional cards.
				wp_enqueue_style(
					'bd-business-detail',
					$plugin_url . 'assets/css/business-detail-classic.css',
					array( 'font-awesome', 'bd-design-tokens' ),
					BD_VERSION
				);

				// Classic JS.
				wp_enqueue_script(
					'bd-business-detail',
					$plugin_url . 'assets/js/business-detail.js',
					array( 'jquery', 'leaflet' ),
					BD_VERSION,
					true
				);
			}

			// Localize shared business detail script.
			wp_localize_script(
				'bd-business-detail',
				'bdDetailVars',
				array(
					'restUrl'    => rest_url( 'bd/v1/' ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'businessId' => get_the_ID(),
				)
			);
		}
	},
	20
);
