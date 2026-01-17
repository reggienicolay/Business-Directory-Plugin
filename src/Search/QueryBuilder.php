<?php
/**
 * Query Builder for Business Search
 *
 * Builds and executes optimized queries for business search with location filtering.
 * Uses batch loading to avoid N+1 query problems.
 *
 * @package BusinessDirectory
 * @subpackage Search
 * @version 1.2.1
 */

namespace BusinessDirectory\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QueryBuilder
 *
 * Handles building WP_Query arguments and executing location-based searches.
 */
class QueryBuilder {

	/**
	 * Filter parameters.
	 *
	 * @var array
	 */
	private $filters = array();

	/**
	 * Base WP_Query arguments.
	 *
	 * @var array
	 */
	private $base_args = array();

	/**
	 * Cached location data keyed by business ID.
	 *
	 * @var array
	 */
	private $location_cache = array();

	/**
	 * Cached hours data keyed by business ID.
	 *
	 * @var array
	 */
	private $hours_cache = array();

	/**
	 * Constructor.
	 *
	 * @param array $filters Sanitized filter parameters.
	 */
	public function __construct( $filters = array() ) {
		$this->filters = $filters;

		$this->base_args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'publish',
			'posts_per_page' => isset( $filters['per_page'] ) ? absint( $filters['per_page'] ) : 20,
			'paged'          => isset( $filters['page'] ) ? absint( $filters['page'] ) : 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
	}

	/**
	 * Build the complete WP_Query args.
	 *
	 * @return array WP_Query arguments.
	 */
	public function build() {
		$args = $this->base_args;

		// Keyword search.
		if ( ! empty( $this->filters['q'] ) ) {
			$args['s'] = $this->filters['q'];
		}

		// Tax queries.
		$tax_query = $this->build_tax_query();
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		// Meta queries.
		$meta_query = $this->build_meta_query();
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		// Sorting (non-distance sorts).
		$sort = isset( $this->filters['sort'] ) ? $this->filters['sort'] : 'distance';

		if ( 'rating' === $sort ) {
			$args['meta_key'] = 'bd_avg_rating';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
		} elseif ( 'name' === $sort ) {
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
		} elseif ( 'newest' === $sort ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		return $args;
	}

	/**
	 * Build taxonomy query.
	 *
	 * @return array Taxonomy query array.
	 */
	private function build_tax_query() {
		$tax_query = array( 'relation' => 'AND' );

		// Categories.
		if ( ! empty( $this->filters['categories'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'bd_category',
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', (array) $this->filters['categories'] ),
				'operator' => 'IN',
			);
		}

		// Areas.
		if ( ! empty( $this->filters['areas'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'bd_area',
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', (array) $this->filters['areas'] ),
				'operator' => 'IN',
			);
		}

		// Tags.
		if ( ! empty( $this->filters['tags'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'bd_tag',
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', (array) $this->filters['tags'] ),
				'operator' => 'IN',
			);
		}

		return count( $tax_query ) > 1 ? $tax_query : array();
	}

	/**
	 * Build meta query.
	 *
	 * @return array Meta query array.
	 */
	private function build_meta_query() {
		$meta_query = array( 'relation' => 'AND' );

		// Price level.
		if ( ! empty( $this->filters['price_level'] ) ) {
			$meta_query[] = array(
				'key'     => 'bd_price_level',
				'value'   => array_map( 'sanitize_text_field', (array) $this->filters['price_level'] ),
				'compare' => 'IN',
			);
		}

		// Minimum rating.
		if ( ! empty( $this->filters['min_rating'] ) ) {
			$meta_query[] = array(
				'key'     => 'bd_avg_rating',
				'value'   => floatval( $this->filters['min_rating'] ),
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}

		return count( $meta_query ) > 1 ? $meta_query : array();
	}

	/**
	 * Get businesses with location filtering.
	 *
	 * Returns businesses along with pre-loaded location and hours data
	 * to avoid N+1 queries in the formatting phase.
	 *
	 * @return array {
	 *     @type array $businesses Array of business data with distance info.
	 *     @type int   $total      Total count of matching businesses.
	 *     @type int   $pages      Total number of pages.
	 *     @type array $locations  Location data keyed by business ID.
	 *     @type array $hours      Hours data keyed by business ID.
	 * }
	 */
	public function get_businesses_with_location() {
		$empty_result = array(
			'businesses' => array(),
			'total'      => 0,
			'pages'      => 1,
			'locations'  => array(),
			'hours'      => array(),
		);

		// First, get businesses matching other filters.
		$args                   = $this->build();
		$args['posts_per_page'] = -1; // Get all for distance filtering.
		$args['fields']         = 'ids';

		$business_ids = get_posts( $args );

		// Safety cap to prevent memory exhaustion with very large datasets.
		$max_results = apply_filters( 'bd_max_location_results', 5000 );
		if ( count( $business_ids ) > $max_results ) {
			$business_ids = array_slice( $business_ids, 0, $max_results );
		}

		if ( empty( $business_ids ) ) {
			return $empty_result;
		}

		// Batch load all location data.
		$this->batch_load_locations( $business_ids );

		// Batch load hours data if "open now" filter is active.
		if ( ! empty( $this->filters['open_now'] ) ) {
			$this->batch_load_hours( $business_ids );
		}

		// Filter to only businesses that have location data.
		$business_ids_with_location = array_keys( $this->location_cache );

		if ( empty( $business_ids_with_location ) ) {
			return $empty_result;
		}

		// If no user location provided, return businesses without distance data.
		if ( empty( $this->filters['lat'] ) || empty( $this->filters['lng'] ) ) {
			$businesses = array();
			foreach ( $business_ids_with_location as $id ) {
				$businesses[] = array( 'id' => $id );
			}

			// Apply "open now" filter even without location data.
			if ( ! empty( $this->filters['open_now'] ) ) {
				$businesses = $this->filter_open_now( $businesses );
			}

			$result              = $this->paginate_array( $businesses );
			$result['locations'] = $this->location_cache;
			$result['hours']     = $this->hours_cache;

			return $result;
		}

		// Calculate distances for businesses with location.
		$businesses_with_distance = $this->calculate_distances_from_cache();

		// Filter by radius if specified.
		if ( ! empty( $this->filters['radius_km'] ) ) {
			$radius_km                = floatval( $this->filters['radius_km'] );
			$businesses_with_distance = array_filter(
				$businesses_with_distance,
				function ( $b ) use ( $radius_km ) {
					return $b['distance_km'] <= $radius_km;
				}
			);
			$businesses_with_distance = array_values( $businesses_with_distance );
		}

		// Sort by distance if requested.
		$sort = isset( $this->filters['sort'] ) ? $this->filters['sort'] : 'distance';
		if ( 'distance' === $sort ) {
			usort(
				$businesses_with_distance,
				function ( $a, $b ) {
					return $a['distance_km'] <=> $b['distance_km'];
				}
			);
		}

		// Apply "open now" filter using cached hours data.
		if ( ! empty( $this->filters['open_now'] ) ) {
			$businesses_with_distance = $this->filter_open_now( $businesses_with_distance );
		}

		$result              = $this->paginate_array( $businesses_with_distance );
		$result['locations'] = $this->location_cache;
		$result['hours']     = $this->hours_cache;

		return $result;
	}

	/**
	 * Filter businesses to only those currently open.
	 *
	 * @param array $businesses Array of business data.
	 * @return array Filtered array of businesses that are open now.
	 */
	private function filter_open_now( $businesses ) {
		return array_values(
			array_filter(
				$businesses,
				function ( $b ) {
					return $this->is_open_now_from_cache( $b['id'], $this->hours_cache );
				}
			)
		);
	}

	/**
	 * Sanitize array of IDs for use in SQL queries.
	 *
	 * @param array $ids Array of IDs.
	 * @return array Array of sanitized integer IDs.
	 */
	private function sanitize_ids_for_query( $ids ) {
		return array_map( 'absint', array_filter( (array) $ids ) );
	}

	/**
	 * Build placeholder string for IN clause.
	 *
	 * @param array  $ids    Array of IDs.
	 * @param string $format Placeholder format (default '%d').
	 * @return string Comma-separated placeholders.
	 */
	private function build_in_placeholders( $ids, $format = '%d' ) {
		$count = count( $ids );
		if ( 0 === $count ) {
			return '';
		}
		return implode( ',', array_fill( 0, $count, $format ) );
	}

	/**
	 * Check if custom locations table exists.
	 *
	 * @return bool True if table exists.
	 */
	private function locations_table_exists() {
		global $wpdb;

		static $exists = null;

		if ( null === $exists ) {
			$table_name = $wpdb->prefix . 'bd_locations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time check.
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			) === $table_name;
		}

		return $exists;
	}

	/**
	 * Batch load location data from both custom table and meta.
	 *
	 * Loads locations in just 2 queries regardless of business count:
	 * 1. Query custom bd_locations table
	 * 2. Query postmeta for bd_location (fallback for new submissions)
	 *
	 * @param array $business_ids Array of business post IDs.
	 */
	private function batch_load_locations( $business_ids ) {
		global $wpdb;

		$this->location_cache = array();

		$ids_array = $this->sanitize_ids_for_query( $business_ids );
		if ( empty( $ids_array ) ) {
			return;
		}

		// 1. Get locations from custom table (if it exists).
		if ( $this->locations_table_exists() ) {
			$table_name   = $wpdb->prefix . 'bd_locations';
			$placeholders = $this->build_in_placeholders( $ids_array );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading for performance.
			$table_locations = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, placeholders generated safely.
					"SELECT business_id, lat, lng, address, city, state, postal_code 
					FROM `{$table_name}` 
					WHERE business_id IN ({$placeholders})",
					...$ids_array
				),
				ARRAY_A
			);

			if ( $table_locations ) {
				foreach ( $table_locations as $loc ) {
					$this->location_cache[ absint( $loc['business_id'] ) ] = array(
						'lat'     => floatval( $loc['lat'] ),
						'lng'     => floatval( $loc['lng'] ),
						'address' => isset( $loc['address'] ) ? $loc['address'] : '',
						'city'    => isset( $loc['city'] ) ? $loc['city'] : '',
						'state'   => isset( $loc['state'] ) ? $loc['state'] : '',
						'zip'     => isset( $loc['postal_code'] ) ? $loc['postal_code'] : '',
					);
				}
			}
		}

		// 2. Find IDs not in custom table (need meta fallback).
		$ids_needing_meta = array_diff( $ids_array, array_keys( $this->location_cache ) );
		$ids_needing_meta = $this->sanitize_ids_for_query( $ids_needing_meta );

		if ( empty( $ids_needing_meta ) ) {
			return;
		}

		$meta_placeholders = $this->build_in_placeholders( $ids_needing_meta );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading for performance.
		$meta_locations = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders generated safely.
				"SELECT post_id, meta_value 
				FROM {$wpdb->postmeta} 
				WHERE post_id IN ({$meta_placeholders}) 
				AND meta_key = 'bd_location'",
				...$ids_needing_meta
			),
			ARRAY_A
		);

		if ( $meta_locations ) {
			foreach ( $meta_locations as $row ) {
				$location_meta = maybe_unserialize( $row['meta_value'] );

				if ( is_array( $location_meta ) && ! empty( $location_meta['lat'] ) && ! empty( $location_meta['lng'] ) ) {
					$this->location_cache[ absint( $row['post_id'] ) ] = array(
						'lat'     => floatval( $location_meta['lat'] ),
						'lng'     => floatval( $location_meta['lng'] ),
						'address' => isset( $location_meta['address'] ) ? $location_meta['address'] : '',
						'city'    => isset( $location_meta['city'] ) ? $location_meta['city'] : '',
						'state'   => isset( $location_meta['state'] ) ? $location_meta['state'] : '',
						'zip'     => isset( $location_meta['zip'] ) ? $location_meta['zip'] : '',
					);
				}
			}
		}
	}

	/**
	 * Batch load business hours data.
	 *
	 * @param array $business_ids Array of business post IDs.
	 */
	private function batch_load_hours( $business_ids ) {
		global $wpdb;

		$this->hours_cache = array();

		$ids_array = $this->sanitize_ids_for_query( $business_ids );
		if ( empty( $ids_array ) ) {
			return;
		}

		$placeholders = $this->build_in_placeholders( $ids_array );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading for performance.
		$hours_data = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders generated safely.
				"SELECT post_id, meta_value 
				FROM {$wpdb->postmeta} 
				WHERE post_id IN ({$placeholders}) 
				AND meta_key = 'bd_hours'",
				...$ids_array
			),
			ARRAY_A
		);

		if ( $hours_data ) {
			foreach ( $hours_data as $row ) {
				$hours = maybe_unserialize( $row['meta_value'] );
				if ( is_array( $hours ) ) {
					$this->hours_cache[ absint( $row['post_id'] ) ] = $hours;
				}
			}
		}
	}

	/**
	 * Check if business is open now using cached hours data.
	 *
	 * @param int   $business_id Business post ID.
	 * @param array $hours_cache Hours data keyed by business ID.
	 * @return bool True if open now, false otherwise.
	 */
	private function is_open_now_from_cache( $business_id, $hours_cache ) {
		if ( ! isset( $hours_cache[ $business_id ] ) ) {
			return false;
		}

		$hours = $hours_cache[ $business_id ];

		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return false;
		}

		// Use WordPress timezone settings.
		$current_day  = strtolower( wp_date( 'l' ) );
		$current_time = wp_date( 'H:i' );

		if ( ! isset( $hours[ $current_day ] ) || ! is_array( $hours[ $current_day ] ) ) {
			return false;
		}

		$today = $hours[ $current_day ];

		// Check if closed.
		if ( empty( $today['open'] ) || empty( $today['close'] ) ) {
			return false;
		}

		return ( $current_time >= $today['open'] && $current_time <= $today['close'] );
	}

	/**
	 * Calculate distances using cached location data.
	 *
	 * @return array Array of businesses with distance info.
	 */
	private function calculate_distances_from_cache() {
		$user_lat = floatval( $this->filters['lat'] );
		$user_lng = floatval( $this->filters['lng'] );

		$businesses = array();

		foreach ( $this->location_cache as $business_id => $location ) {
			$distance_km = $this->haversine_distance(
				$user_lat,
				$user_lng,
				$location['lat'],
				$location['lng']
			);

			$businesses[] = array(
				'id'          => $business_id,
				'distance_km' => $distance_km,
				'distance_mi' => $distance_km * 0.621371,
			);
		}

		return $businesses;
	}

	/**
	 * Haversine formula for distance calculation.
	 *
	 * @param float $lat1 Latitude of point 1.
	 * @param float $lon1 Longitude of point 1.
	 * @param float $lat2 Latitude of point 2.
	 * @param float $lon2 Longitude of point 2.
	 * @return float Distance in kilometers.
	 */
	private function haversine_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 6371; // km.

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lon = deg2rad( $lon2 - $lon1 );

		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) +
			cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			sin( $d_lon / 2 ) * sin( $d_lon / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	/**
	 * Paginate array of businesses with distance.
	 *
	 * @param array $businesses Array of business data.
	 * @return array Paginated results with total and pages.
	 */
	private function paginate_array( $businesses ) {
		$total    = count( $businesses );
		$per_page = isset( $this->filters['per_page'] ) ? absint( $this->filters['per_page'] ) : 20;
		$page     = isset( $this->filters['page'] ) ? absint( $this->filters['page'] ) : 1;

		// Ensure valid values.
		$per_page = max( 1, $per_page );
		$page     = max( 1, $page );

		$offset    = ( $page - 1 ) * $per_page;
		$paginated = array_slice( $businesses, $offset, $per_page );

		return array(
			'businesses' => $paginated,
			'total'      => $total,
			'pages'      => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Get cached location data.
	 *
	 * @return array Location data keyed by business ID.
	 */
	public function get_location_cache() {
		return $this->location_cache;
	}

	/**
	 * Get cached hours data.
	 *
	 * @return array Hours data keyed by business ID.
	 */
	public function get_hours_cache() {
		return $this->hours_cache;
	}
}