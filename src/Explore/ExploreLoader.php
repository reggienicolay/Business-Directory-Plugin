<?php
/**
 * Explore Module Loader
 *
 * Bootstraps all Explore Pages components. Include this file
 * from the main BD Pro plugin's initialization sequence.
 *
 * Usage in the main plugin:
 *   require_once BD_PLUGIN_DIR . 'src/Explore/ExploreLoader.php';
 *
 * Or with PSR-4 autoloading already in place, just call:
 *   \BusinessDirectory\Explore\ExploreLoader::init();
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreLoader
 */
class ExploreLoader {

	/**
	 * Initialize all explore components.
	 */
	public static function init() {
		// Explore pages only exist on the hub — subsites have no local
		// bd_business posts and subsite-domain explore URLs would create
		// duplicate content in Google's index (5 sitemaps, 5 URL sets).
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		// Core routing and URL handling.
		ExploreRouter::init();

		// Editorial content management (admin fields + auto-generation).
		ExploreEditorial::init();

		// Sitemap integration.
		ExploreSitemap::init();

		// Cache invalidation on data changes.
		ExploreCacheInvalidator::init();

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rewrite_rules' ) );
		}
	}

	/**
	 * Flush rewrite rules once after plugin update.
	 *
	 * Runs on admin_init (not admin_notices) to avoid
	 * unnecessary DB writes on every admin page load.
	 */
	public static function maybe_flush_rewrite_rules() {
		$version_key = 'bd_explore_rewrite_version';
		$stored      = get_option( $version_key, '' );
		$current     = defined( 'BD_VERSION' ) ? BD_VERSION : '2.2.0';

		if ( $stored !== $current ) {
			flush_rewrite_rules();
			update_option( $version_key, $current, true );
		}
	}

	/**
	 * Get the total number of valid explore pages.
	 *
	 * Useful for admin dashboard stats.
	 *
	 * @return int Count of explore pages (hub + cities + intersections).
	 */
	public static function get_total_explore_pages() {
		$urls = ExploreSitemap::get_explore_urls();
		return count( $urls );
	}

	/**
	 * Invalidate explore caches.
	 *
	 * Call when businesses are created, updated, or deleted,
	 * or when taxonomy terms change.
	 */
	public static function invalidate_caches() {
		wp_cache_delete( 'hub_data', ExploreQuery::CACHE_GROUP );
		wp_cache_delete( 'bd_explore_sitemap_urls', ExploreQuery::CACHE_GROUP );
		delete_transient( 'bd_geositemap_xml' );
		delete_transient( 'bd_ex_sitemap_urls' );

		// Flush area-specific tag caches.
		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		if ( ! is_wp_error( $areas ) ) {
			foreach ( $areas as $slug ) {
				wp_cache_delete( 'area_tags_' . $slug, ExploreQuery::CACHE_GROUP );
				wp_cache_delete( 'city_reviews_' . $slug, ExploreQuery::CACHE_GROUP );
				delete_transient( 'bd_ex_atags_' . substr( md5( $slug ), 0, 12 ) );
			}
		}

		// Flush tag-specific city caches.
		$tags = get_terms(
			array(
				'taxonomy'   => 'bd_tag',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		if ( ! is_wp_error( $tags ) ) {
			foreach ( $tags as $slug ) {
				wp_cache_delete( 'tag_cities_' . $slug, ExploreQuery::CACHE_GROUP );
				delete_transient( 'bd_ex_tcit_' . substr( md5( $slug ), 0, 12 ) );
			}
		}
	}
}
