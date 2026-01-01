<?php

namespace BusinessDirectory\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class FilterHandler {

	/**
	 * Sanitize and validate filter inputs
	 */
	public static function sanitize_filters( $filters ) {
		$sanitized = array();

		// Categories (array of term IDs)
		if ( ! empty( $filters['categories'] ) ) {
			$cats                    = is_array( $filters['categories'] ) ? $filters['categories'] : explode( ',', $filters['categories'] );
			$sanitized['categories'] = array_map( 'intval', array_filter( $cats ) );
		}

		// Areas (array of term IDs)
		if ( ! empty( $filters['areas'] ) ) {
			$areas              = is_array( $filters['areas'] ) ? $filters['areas'] : explode( ',', $filters['areas'] );
			$sanitized['areas'] = array_map( 'intval', array_filter( $areas ) );
		}

		// Tags (array of term IDs)
		if ( ! empty( $filters['tags'] ) ) {
			$tags              = is_array( $filters['tags'] ) ? $filters['tags'] : explode( ',', $filters['tags'] );
			$sanitized['tags'] = array_map( 'intval', array_filter( $tags ) );
		}

		// Price levels (array of strings)
		if ( ! empty( $filters['price_level'] ) ) {
			$valid_prices             = array( '$', '$$', '$$$', '$$$$' );
			$prices                   = is_array( $filters['price_level'] ) ? $filters['price_level'] : explode( ',', $filters['price_level'] );
			$sanitized['price_level'] = array_intersect( $prices, $valid_prices );
		}

		// Minimum rating (1-5)
		if ( isset( $filters['min_rating'] ) ) {
			$rating                  = floatval( $filters['min_rating'] );
			$sanitized['min_rating'] = max( 0, min( 5, $rating ) );
		}

		// Open now (boolean)
		if ( isset( $filters['open_now'] ) ) {
			$sanitized['open_now'] = filter_var( $filters['open_now'], FILTER_VALIDATE_BOOLEAN );
		}

		// Keyword search
		if ( ! empty( $filters['q'] ) ) {
			$sanitized['q'] = sanitize_text_field( $filters['q'] );
		}

		// Location & radius
		if ( isset( $filters['lat'] ) ) {
			$sanitized['lat'] = floatval( $filters['lat'] );
		}
		if ( isset( $filters['lng'] ) ) {
			$sanitized['lng'] = floatval( $filters['lng'] );
		}
		if ( isset( $filters['radius_km'] ) ) {
			$sanitized['radius_km'] = max( 1, min( 80, floatval( $filters['radius_km'] ) ) ); // 1-80km
		}

		// Sorting
		$valid_sorts = array( 'distance', 'rating', 'newest', 'name' );
		if ( ! empty( $filters['sort'] ) && in_array( $filters['sort'], $valid_sorts ) ) {
			$sanitized['sort'] = $filters['sort'];
		} else {
			$sanitized['sort'] = 'distance';
		}

		// Pagination
		$sanitized['page']     = ! empty( $filters['page'] ) ? max( 1, intval( $filters['page'] ) ) : 1;
		$sanitized['per_page'] = ! empty( $filters['per_page'] ) ? max( 1, min( 100, intval( $filters['per_page'] ) ) ) : 20;

		return $sanitized;
	}

	/**
	 * Get filter metadata (available options with counts)
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

		// Cache for 15 minutes
		set_transient( $cache_key, $metadata, 15 * MINUTE_IN_SECONDS );

		return $metadata;
	}

	/**
	 * Get category counts
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
	 * Get area counts
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
	 * Get tag counts
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
	 * Get price level counts
	 */
	private static function get_price_level_counts() {
		global $wpdb;

		$counts = $wpdb->get_results(
			"
            SELECT meta_value as price_level, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'bd_price_level'
            AND meta_value != ''
            GROUP BY meta_value
        ",
			ARRAY_A
		);

		$result = array();
		foreach ( $counts as $row ) {
			$result[ $row['price_level'] ] = intval( $row['count'] );
		}

		return $result;
	}

	/**
	 * Get rating range counts
	 */
	private static function get_rating_counts() {
		global $wpdb;

		$businesses = $wpdb->get_col(
			"
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'bd_avg_rating'
            AND meta_value != ''
        "
		);

		$ranges = array(
			'4+' => 0,
			'3+' => 0,
			'2+' => 0,
			'1+' => 0,
		);

		foreach ( $businesses as $business_id ) {
			$rating = floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) );

			if ( $rating >= 4 ) {
				++$ranges['4+'];
			}
			if ( $rating >= 3 ) {
				++$ranges['3+'];
			}
			if ( $rating >= 2 ) {
				++$ranges['2+'];
			}
			if ( $rating >= 1 ) {
				++$ranges['1+'];
			}
		}

		return $ranges;
	}

	/**
	 * Check if business is open now
	 */
	public static function is_open_now( $business_id ) {
		$hours = get_post_meta( $business_id, 'bd_hours', true );

		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return false;
		}

		$current_day  = strtolower( date( 'l' ) ); // 'monday', 'tuesday', etc.
		$current_time = date( 'H:i' ); // '14:30'

		if ( ! isset( $hours[ $current_day ] ) ) {
			return false;
		}

		$today = $hours[ $current_day ];

		// Check if closed
		if ( empty( $today['open'] ) || empty( $today['close'] ) ) {
			return false;
		}

		return ( $current_time >= $today['open'] && $current_time <= $today['close'] );
	}
}
