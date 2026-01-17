<?php
/**
 * Filter Handler for Business Search
 *
 * Sanitizes filter inputs and provides filter metadata with counts.
 * Optimized to use aggregate queries instead of N+1 patterns.
 *
 * @package BusinessDirectory
 * @subpackage Search
 */

namespace BusinessDirectory\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FilterHandler
 *
 * Handles filter input sanitization and metadata retrieval.
 */
class FilterHandler {

	/**
	 * Sanitize and validate filter inputs.
	 *
	 * @param array $filters Raw filter parameters from request.
	 * @return array Sanitized filter parameters.
	 */
	public static function sanitize_filters( $filters ) {
		$sanitized = array();

		// Categories (array of term IDs).
		if ( ! empty( $filters['categories'] ) ) {
			$cats                    = is_array( $filters['categories'] ) ? $filters['categories'] : explode( ',', $filters['categories'] );
			$sanitized['categories'] = array_map( 'intval', array_filter( $cats ) );
		}

		// Areas (array of term IDs).
		if ( ! empty( $filters['areas'] ) ) {
			$areas              = is_array( $filters['areas'] ) ? $filters['areas'] : explode( ',', $filters['areas'] );
			$sanitized['areas'] = array_map( 'intval', array_filter( $areas ) );
		}

		// Tags (array of term IDs).
		if ( ! empty( $filters['tags'] ) ) {
			$tags              = is_array( $filters['tags'] ) ? $filters['tags'] : explode( ',', $filters['tags'] );
			$sanitized['tags'] = array_map( 'intval', array_filter( $tags ) );
		}

		// Price levels (array of strings).
		if ( ! empty( $filters['price_level'] ) ) {
			$valid_prices             = array( '$', '$$', '$$$', '$$$$' );
			$prices                   = is_array( $filters['price_level'] ) ? $filters['price_level'] : explode( ',', $filters['price_level'] );
			$sanitized['price_level'] = array_intersect( $prices, $valid_prices );
		}

		// Minimum rating (1-5).
		if ( isset( $filters['min_rating'] ) ) {
			$rating                  = floatval( $filters['min_rating'] );
			$sanitized['min_rating'] = max( 0, min( 5, $rating ) );
		}

		// Open now (boolean).
		if ( isset( $filters['open_now'] ) ) {
			$sanitized['open_now'] = filter_var( $filters['open_now'], FILTER_VALIDATE_BOOLEAN );
		}

		// Keyword search.
		if ( ! empty( $filters['q'] ) ) {
			$sanitized['q'] = sanitize_text_field( $filters['q'] );
		}

		// Location & radius.
		if ( isset( $filters['lat'] ) ) {
			$sanitized['lat'] = floatval( $filters['lat'] );
		}
		if ( isset( $filters['lng'] ) ) {
			$sanitized['lng'] = floatval( $filters['lng'] );
		}
		if ( isset( $filters['radius_km'] ) ) {
			$sanitized['radius_km'] = max( 1, min( 80, floatval( $filters['radius_km'] ) ) ); // 1-80km.
		}

		// Sorting.
		$valid_sorts = array( 'distance', 'rating', 'newest', 'name' );
		if ( ! empty( $filters['sort'] ) && in_array( $filters['sort'], $valid_sorts, true ) ) {
			$sanitized['sort'] = $filters['sort'];
		} else {
			$sanitized['sort'] = 'distance';
		}

		// Pagination.
		$sanitized['page']     = ! empty( $filters['page'] ) ? max( 1, intval( $filters['page'] ) ) : 1;
		$sanitized['per_page'] = ! empty( $filters['per_page'] ) ? max( 1, min( 100, intval( $filters['per_page'] ) ) ) : 20;

		return $sanitized;
	}

	/**
	 * Get filter metadata (available options with counts).
	 *
	 * Cached for 15 minutes to reduce database load.
	 *
	 * @return array Filter metadata including categories, areas, tags, price levels, and ratings.
	 */
	public static function get_filter_metadata() {
		$cache_key = 'bd_filter_metadata';
		$metadata  = get_transient( $cache_key );

		if ( false !== $metadata ) {
			return $metadata;
		}

		$metadata = array(
			'categories'    => self::get_category_counts(),
			'areas'         => self::get_area_counts(),
			'tags'          => self::get_tag_counts(),
			'price_levels'  => self::get_price_level_counts(),
			'rating_ranges' => self::get_rating_counts(),
		);

		// Cache for 15 minutes.
		set_transient( $cache_key, $metadata, 15 * MINUTE_IN_SECONDS );

		return $metadata;
	}

	/**
	 * Get category counts.
	 *
	 * @return array Array of categories with id, name, slug, and count.
	 */
	private static function get_category_counts() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'bd_category',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$counts = array();
		foreach ( $terms as $term ) {
			$counts[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $counts;
	}

	/**
	 * Get area counts.
	 *
	 * @return array Array of areas with id, name, slug, and count.
	 */
	private static function get_area_counts() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$counts = array();
		foreach ( $terms as $term ) {
			$counts[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $counts;
	}

	/**
	 * Get tag counts.
	 *
	 * @return array Array of tags with id, name, slug, and count, ordered by count.
	 */
	private static function get_tag_counts() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'bd_tag',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$counts = array();
		foreach ( $terms as $term ) {
			$counts[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $counts;
	}

	/**
	 * Get price level counts.
	 *
	 * Uses single aggregate query instead of N+1 pattern.
	 *
	 * @return array Associative array of price level => count.
	 */
	private static function get_price_level_counts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query for filter metadata, cached at caller level.
		$counts = $wpdb->get_results(
			"SELECT meta_value as price_level, COUNT(*) as count
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = 'bd_price_level'
			AND pm.meta_value != ''
			AND p.post_type = 'bd_business'
			AND p.post_status = 'publish'
			GROUP BY pm.meta_value",
			ARRAY_A
		);

		$result = array();
		if ( $counts ) {
			foreach ( $counts as $row ) {
				$result[ $row['price_level'] ] = intval( $row['count'] );
			}
		}

		return $result;
	}

	/**
	 * Get rating range counts.
	 *
	 * OPTIMIZED: Uses single aggregate query with CASE statements
	 * instead of N+1 pattern (fetching all IDs then looping).
	 *
	 * @return array Associative array of rating ranges (4+, 3+, 2+, 1+) => count.
	 */
	private static function get_rating_counts() {
		global $wpdb;

		// Single aggregate query calculates all ranges at once.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate query for filter metadata, cached at caller level.
		$result = $wpdb->get_row(
			"SELECT 
				SUM(CASE WHEN CAST(pm.meta_value AS DECIMAL(3,2)) >= 4 THEN 1 ELSE 0 END) as rating_4plus,
				SUM(CASE WHEN CAST(pm.meta_value AS DECIMAL(3,2)) >= 3 THEN 1 ELSE 0 END) as rating_3plus,
				SUM(CASE WHEN CAST(pm.meta_value AS DECIMAL(3,2)) >= 2 THEN 1 ELSE 0 END) as rating_2plus,
				SUM(CASE WHEN CAST(pm.meta_value AS DECIMAL(3,2)) >= 1 THEN 1 ELSE 0 END) as rating_1plus
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = 'bd_avg_rating'
			AND pm.meta_value != ''
			AND pm.meta_value != '0'
			AND p.post_type = 'bd_business'
			AND p.post_status = 'publish'",
			ARRAY_A
		);

		if ( ! $result ) {
			return array(
				'4+' => 0,
				'3+' => 0,
				'2+' => 0,
				'1+' => 0,
			);
		}

		return array(
			'4+' => intval( $result['rating_4plus'] ?? 0 ),
			'3+' => intval( $result['rating_3plus'] ?? 0 ),
			'2+' => intval( $result['rating_2plus'] ?? 0 ),
			'1+' => intval( $result['rating_1plus'] ?? 0 ),
		);
	}

	/**
	 * Check if business is open now.
	 *
	 * Uses WordPress timezone settings (wp_date) instead of PHP date()
	 * to respect site-configured timezone.
	 *
	 * @param int        $business_id Business post ID.
	 * @param array|null $hours       Optional pre-loaded hours data. If null, fetches from meta.
	 * @return bool True if business is currently open, false otherwise.
	 */
	public static function is_open_now( $business_id, $hours = null ) {
		// Allow passing pre-loaded hours to avoid N+1 queries.
		if ( null === $hours ) {
			$hours = get_post_meta( $business_id, 'bd_hours', true );
		}

		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return false;
		}

		// Use WordPress timezone settings instead of server timezone.
		$current_day  = strtolower( wp_date( 'l' ) );  // 'monday', 'tuesday', etc.
		$current_time = wp_date( 'H:i' );              // '14:30'.

		if ( ! isset( $hours[ $current_day ] ) ) {
			return false;
		}

		$today = $hours[ $current_day ];

		// Check if closed.
		if ( empty( $today['open'] ) || empty( $today['close'] ) ) {
			return false;
		}

		return ( $current_time >= $today['open'] && $current_time <= $today['close'] );
	}
}
