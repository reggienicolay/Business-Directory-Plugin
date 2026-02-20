<?php
/**
 * Explore Module Loader
 *
 * Loads all Explore Pages components.
 * Include this file from business-directory.php:
 *
 *   require_once plugin_dir_path( __FILE__ ) . 'includes/explore-loader.php';
 *
 * @package BusinessDirectory
 * @subpackage Explore
 * @since 2.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once.
if ( defined( 'BD_EXPLORE_LOADED' ) ) {
	return;
}
define( 'BD_EXPLORE_LOADED', true );

// Load explore classes.
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreRouter.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreQuery.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreAssets.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreCardRenderer.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreMapRenderer.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreNavigationRenderer.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreRenderer.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreEditorial.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreSitemap.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreSitemapProvider.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreCacheInvalidator.php';
require_once BD_PLUGIN_DIR . 'src/Explore/ExploreLoader.php';

// Initialize.
\BusinessDirectory\Explore\ExploreLoader::init();
