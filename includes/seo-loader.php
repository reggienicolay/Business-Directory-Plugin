<?php
/**
 * SEO Components Loader
 *
 * Central initialization for all SEO-related classes in the core plugin.
 * Uses defensive loading to prevent fatal errors from missing files.
 *
 * USAGE:
 * Add this line to business-directory.php (after other loaders):
 *
 *     if ( file_exists( BD_PLUGIN_DIR . 'includes/seo-loader.php' ) ) {
 *         require_once BD_PLUGIN_DIR . 'includes/seo-loader.php';
 *     }
 *
 * @package    BusinessDirectory
 * @subpackage SEO
 * @since      0.1.8
 */

namespace BD\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent multiple loads.
if ( defined( 'BD_SEO_LOADER_LOADED' ) ) {
	return;
}
define( 'BD_SEO_LOADER_LOADED', true );

/**
 * SEO class files to load.
 *
 * Format: 'ClassName' => 'relative/path/from/plugin/root.php'
 *
 * @var array<string, string>
 */
$bd_seo_classes = array(
	'SlugMigration' => 'src/SEO/SlugMigration.php',
	// Future classes (uncomment when files exist):
	// 'AutoLinker'         => 'src/SEO/AutoLinker.php',
	// 'RelatedBusinesses'  => 'src/SEO/RelatedBusinesses.php',
	// 'MultisiteCanonical' => 'src/SEO/MultisiteCanonical.php',
);

/**
 * Load SEO class files with existence checks.
 *
 * This prevents fatal errors if files are missing or during partial updates.
 */
foreach ( $bd_seo_classes as $class_name => $relative_path ) {
	$file_path = BD_PLUGIN_DIR . $relative_path;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// Log missing files in debug mode for developer awareness.
		error_log(
			sprintf(
				'[Business Directory SEO] Class file not found: %s (expected at %s)',
				$class_name,
				$file_path
			)
		);
	}
}

// Clean up.
unset( $bd_seo_classes, $class_name, $relative_path, $file_path );

/**
 * Initialize all SEO components.
 *
 * Hooked to 'init' at priority 25 to run after:
 * - Taxonomies registered (priority 10)
 * - Post types registered (priority 10)
 *
 * Uses class_exists() checks for graceful degradation.
 *
 * @since 0.1.8
 * @return void
 */
function init_seo_components(): void {
	// Slug Migration: 301 redirects for old taxonomy URLs.
	if ( class_exists( __NAMESPACE__ . '\\SlugMigration' ) ) {
		SlugMigration::init();
	}

	// Future components (uncomment when classes exist):

	// AutoLinker: Auto-link city/category mentions in descriptions.
	// if ( class_exists( __NAMESPACE__ . '\\AutoLinker' ) ) {
	// 	AutoLinker::init();
	// }

	// RelatedBusinesses: "Also in [City]" cross-links.
	// if ( class_exists( __NAMESPACE__ . '\\RelatedBusinesses' ) ) {
	// 	RelatedBusinesses::init();
	// }

	// MultisiteCanonical: REST API canonical URLs for subsites.
	// if ( is_multisite() && class_exists( __NAMESPACE__ . '\\MultisiteCanonical' ) ) {
	// 	MultisiteCanonical::init();
	// }
}

add_action( 'init', __NAMESPACE__ . '\\init_seo_components', 25 );

/**
 * Fix rewrite rule priority for taxonomy archives.
 *
 * WordPress registers bd_business post type rules before taxonomy rules,
 * causing /places/tag/winery/ to match as a business attachment instead
 * of a tag archive. This filter moves taxonomy rules to the front.
 *
 * @since 0.1.8
 *
 * @param array $rules All registered rewrite rules.
 * @return array Reordered rules with taxonomy rules first.
 */
function fix_taxonomy_rewrite_priority( array $rules ): array {
	$taxonomy_rules = array();
	$other_rules    = array();

	foreach ( $rules as $pattern => $match ) {
		// Match places/category/*, places/area/*, places/tag/*
		if ( preg_match( '#^places/(category|area|tag)/#', $pattern ) ) {
			$taxonomy_rules[ $pattern ] = $match;
		} else {
			$other_rules[ $pattern ] = $match;
		}
	}

	// Taxonomy rules first, then everything else.
	return array_merge( $taxonomy_rules, $other_rules );
}

add_filter( 'rewrite_rules_array', __NAMESPACE__ . '\\fix_taxonomy_rewrite_priority', 999 );
