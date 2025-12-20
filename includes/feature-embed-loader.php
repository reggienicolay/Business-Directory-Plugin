<?php
/**
 * Feature Embed Loader
 * Loads all components for the business feature embed system
 *
 * Add this line to business-directory.php:
 * require_once plugin_dir_path( __FILE__ ) . 'includes/feature-embed-loader.php';
 *
 * @package BusinessDirectory
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once
if ( defined( 'BD_FEATURE_EMBED_LOADED' ) ) {
	return;
}
define( 'BD_FEATURE_EMBED_LOADED', true );

// Load Feature Embed classes
require_once plugin_dir_path( __FILE__ ) . '../src/API/FeatureEndpoint.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/FeatureShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/FeatureSettings.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/FeaturePicker.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/FeatureBlock.php';

// Initialize components
BD\API\FeatureEndpoint::init();
BD\Frontend\FeatureShortcode::init();
BD\Admin\FeatureSettings::init();
BD\Admin\FeaturePicker::init();
BD\Admin\FeatureBlock::init();

/**
 * Enqueue frontend styles when shortcode is used
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		// Check if page/post has our shortcode
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bd_feature' ) ) {
			wp_enqueue_style(
				'bd-feature-embed',
				BD_PLUGIN_URL . 'assets/css/feature-embed.css',
				array(),
				BD_VERSION
			);
		}

		// Also check for our block
		if ( is_a( $post, 'WP_Post' ) && has_block( 'bd/feature', $post ) ) {
			wp_enqueue_style(
				'bd-feature-embed',
				BD_PLUGIN_URL . 'assets/css/feature-embed.css',
				array(),
				BD_VERSION
			);
		}
	},
	20
);

/**
 * Also check for shortcode in widgets
 */
add_filter(
	'widget_text',
	function ( $content ) {
		if ( has_shortcode( $content, 'bd_feature' ) ) {
			wp_enqueue_style(
				'bd-feature-embed',
				BD_PLUGIN_URL . 'assets/css/feature-embed.css',
				array(),
				BD_VERSION
			);
		}
		return $content;
	}
);

/**
 * Modify shortcode to use configured source URL if not specified
 */
add_filter(
	'shortcode_atts_bd_feature',
	function ( $atts, $pairs, $original_atts ) {
		// If source not explicitly set, use configured default
		if ( empty( $original_atts['source'] ) ) {
			$configured_source = get_option( 'bd_feature_source_url', '' );
			if ( ! empty( $configured_source ) ) {
				$atts['source'] = $configured_source;
			}
		}
		return $atts;
	},
	10,
	3
);

/**
 * Clear feature cache when a business is updated
 */
add_action(
	'save_post_bd_business',
	function ( $post_id ) {
		global $wpdb;

		// Delete all feature transients (they contain the business ID in the key)
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_bd_feature_%'
			)
		);
	}
);

/**
 * Add feature embed info to admin footer on relevant pages
 */
add_action(
	'admin_footer_text',
	function ( $text ) {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'bd-feature-settings' ) !== false ) {
			return 'Business Directory Feature Embed v' . BD_VERSION;
		}
		return $text;
	}
);

/**
 * Register block category for Business Directory
 */
add_filter(
	'block_categories_all',
	function ( $categories ) {
		// Check if our category already exists
		foreach ( $categories as $cat ) {
			if ( $cat['slug'] === 'business-directory' ) {
				return $categories;
			}
		}

		// Add our category
		array_unshift(
			$categories,
			array(
				'slug'  => 'business-directory',
				'title' => 'Business Directory',
				'icon'  => 'store',
			)
		);

		return $categories;
	},
	10,
	1
);
