<?php
/**
 * Explore Pages Renderer (Facade)
 *
 * Thin delegation layer that routes template calls to the
 * dedicated renderer classes. Templates call ExploreRenderer
 * methods as before — this facade keeps backward compatibility
 * while the actual work lives in focused SRP classes:
 *
 *   ExploreAssets              — CDN registration, SRI, enqueue
 *   ExploreCardRenderer        — Business cards, stars, placeholders
 *   ExploreMapRenderer         — Leaflet map + marker JSON
 *   ExploreNavigationRenderer  — Pagination, discovery bar, cross-links
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 * @since      2.3.0 Refactored to facade pattern.
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreRenderer
 */
class ExploreRenderer {

	// =========================================================================
	// Assets
	// =========================================================================

	/**
	 * Enqueue all CSS and JS assets for city and intersection pages.
	 */
	public static function enqueue_page_assets() {
		ExploreAssets::enqueue_map_page();
	}

	/**
	 * Enqueue lightweight assets for the hub page.
	 */
	public static function enqueue_hub_assets() {
		ExploreAssets::enqueue_hub();
	}

	// =========================================================================
	// Cards
	// =========================================================================

	/**
	 * Render a single business card.
	 *
	 * @param array $business Formatted business data from ExploreQuery.
	 * @return string Card HTML.
	 */
	public static function render_card( $business ) {
		return ExploreCardRenderer::render_card( $business );
	}

	/**
	 * Render a grid of business cards.
	 *
	 * @param array $businesses Array of formatted business data.
	 * @return string Grid HTML.
	 */
	public static function render_grid( $businesses ) {
		return ExploreCardRenderer::render_grid( $businesses );
	}

	/**
	 * Render no results message.
	 *
	 * @return string HTML.
	 */
	public static function render_no_results() {
		return ExploreCardRenderer::render_no_results();
	}

	/**
	 * Render SVG star rating.
	 *
	 * @param float $rating Rating value (0-5).
	 * @return string SVG HTML.
	 */
	public static function render_stars( $rating ) {
		return ExploreCardRenderer::render_stars( $rating );
	}

	// =========================================================================
	// Map
	// =========================================================================

	/**
	 * Render an interactive Leaflet map with markers for businesses.
	 *
	 * @param array $businesses Array of formatted business data.
	 * @return string Map HTML, or empty if no valid pins.
	 */
	public static function render_map( $businesses ) {
		return ExploreMapRenderer::render_map( $businesses );
	}

	// =========================================================================
	// Navigation
	// =========================================================================

	/**
	 * Render pagination links.
	 *
	 * @param int    $total_pages Total number of pages.
	 * @param int    $current     Current page number.
	 * @param string $base_url    Base URL for pagination links.
	 * @param string $sort        Current sort key (preserved in pagination URLs).
	 * @return string Pagination HTML.
	 */
	public static function render_pagination( $total_pages, $current, $base_url, $sort = 'rating' ) {
		return ExploreNavigationRenderer::render_pagination( $total_pages, $current, $base_url );
	}

	/**
	 * Render sort dropdown.
	 *
	 * @param string $current_sort Current sort key.
	 * @param string $base_url     Base URL for the form action.
	 * @return string Sort form HTML.
	 */
	public static function render_sort_dropdown( $current_sort, $base_url ) {
		return ExploreNavigationRenderer::render_sort_dropdown( $current_sort, $base_url );
	}

	/**
	 * Render the editorial intro section.
	 *
	 * @param string $area_slug Area slug.
	 * @param string $tag_slug  Tag slug (empty for city pages).
	 * @param int    $count     Business count.
	 * @return string Intro HTML.
	 */
	public static function render_intro( $area_slug, $tag_slug = '', $count = 0 ) {
		return ExploreNavigationRenderer::render_intro( $area_slug, $tag_slug, $count );
	}

	/**
	 * Render the "Also Explore" cross-linking section.
	 *
	 * @param string $area_slug Current area slug.
	 * @param string $tag_slug  Current tag slug.
	 * @param string $area_name Current area name.
	 * @param string $tag_name  Current tag name.
	 * @return string HTML.
	 */
	public static function render_cross_links( $area_slug, $tag_slug, $area_name, $tag_name ) {
		return ExploreNavigationRenderer::render_cross_links( $area_slug, $tag_slug, $area_name, $tag_name );
	}

	/**
	 * Render the tag cloud for city landing pages.
	 *
	 * @param array  $tags      Array of tag data.
	 * @param string $area_name City name.
	 * @return string HTML.
	 */
	public static function render_tag_cloud( $tags, $area_name ) {
		return ExploreNavigationRenderer::render_tag_cloud( $tags, $area_name );
	}

	/**
	 * Render city stats bar.
	 *
	 * @param int    $business_count Total businesses in city.
	 * @param string $area_slug      City slug.
	 * @return string HTML.
	 */
	public static function render_city_stats( $business_count, $area_slug ) {
		return ExploreNavigationRenderer::render_city_stats( $business_count, $area_slug );
	}

	/**
	 * Render other cities navigation.
	 *
	 * @param string $exclude_slug Current city slug to exclude.
	 * @return string HTML.
	 */
	public static function render_other_cities( $exclude_slug ) {
		return ExploreNavigationRenderer::render_other_cities( $exclude_slug );
	}

	/**
	 * Render the discovery bar.
	 *
	 * @param string      $area_slug Current area slug.
	 * @param string|null $tag_slug  Current tag slug.
	 * @return string HTML.
	 */
	public static function render_discovery_bar( $area_slug = '', $tag_slug = null ) {
		return ExploreNavigationRenderer::render_discovery_bar( $area_slug, $tag_slug );
	}
}
