<?php
/**
 * Query Builder for Business Search
 *
 * Builds and executes optimized queries for business search with location filtering.
 * Uses batch loading to avoid N+1 query problems.
 *
 * @package BusinessDirectory
 * @subpackage Search
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
			'posts_per_page' => $filters['per_page'] ?? 20,
			'paged'          => $filters['page'] ?? 1,
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
		$sort = $this->filters['sort'] ?? 'distance';

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
				'terms'    => $this->filters['categories'],
				'operator' => 'IN',
			);
		}

		// Areas.
		if ( ! empty( $this->filters['areas'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'bd_area',
				'field'    => 'term_id',
				'terms'    => $this->filters['areas'],
				'operator' => 'IN',
			);
		}

		// Tags.
		if ( ! empty( $this->filters['tags'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'bd_tag',
				'field'    => 'term_id',
				'terms'    => $this->filters['tags'],
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
				'value'   => $this->filters['price_level'],
				'compare' => 'IN',
			);
		}

		// Minimum rating.
		if ( ! empty( $this->filters['min_rating'] ) ) {
			$meta_query[] = array(
				'key'     => 'bd_avg_rating',
				'value'   => $this->filters['min_rating'],
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
		// First, get businesses matching other filters.
		$args                   = $this->build();
		$args['posts_per_page'] = -1; // Get all for distance filtering.
		$args['fields']         = 'ids';

		$business_ids = get_posts( $args );

		// Safety cap to prevent memory exhaustion with very large datasets.
		// Can be filtered: add_filter( 'bd_max_location_results', function() { return 10000; } );
		$max_results = apply_filters( 'bd_max_location_results', 5000 );
		if ( count( $business_ids ) > $max_results ) {
			$business_ids = array_slice( $business_ids, 0, $max_results );
		}

		if ( empty( $business_ids ) ) {
			return array(
				'businesses' => array(),
				'total'      => 0,
				'pages'      => 1,
				'locations'  => array(),
				'hours'      => array(),
			);
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
			return array(
				'businesses' => array(),
				'total'      => 0,
				'pages'      => 1,
				'locations'  => array(),
				'hours'      => array(),
			);
		}

		// If no user location provided, return businesses without distance data.
		if ( empty( $this->filters['lat'] ) || empty( $this->filters['lng'] ) ) {
			$businesses = array();
			foreach ( $business_ids_with_location as $id ) {
				$businesses[] = array( 'id' => $id );
			}

			// Apply "open now" filter even without location data.
			if ( ! empty( $this->filters['open_now'] ) ) {
				$hours_cache = $this->hours_cache;
				$businesses  = array_filter(
					$businesses,
					function ( $b ) use ( $hours_cache ) {
						return $this->is_open_now_from_cache( $b['id'], $hours_cache );
					}
				);
				$businesses  = array_values( $businesses ); // Re-index array.
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
			$radius_km                = $this->filters['radius_km'];
			$businesses_with_distance = array_filter(
				$businesses_with_distance,
				function ( $b ) use ( $radius_km ) {
					return $b['distance_km'] <= $radius_km;
				}
			);
		}

		// Sort by distance if requested.
		$sort = $this->filters['sort'] ?? 'distance';
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
			$hours_cache              = $this->hours_cache;
			$businesses_with_distance = array_filter(
				$businesses_with_distance,
				function ( $b ) use ( $hours_cache ) {
					return $this->is_open_now_from_cache( $b['id'], $hours_cache );
				}
			);
		}

		$result              = $this->paginate_array( array_values( $businesses_with_distance ) );
		$result['locations'] = $this->location_cache;
		$result['hours']     = $this->hours_cache;

		return $result;
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

		if ( empty( $business_ids ) ) {
			return;
		}

		// Sanitize IDs for SQL.
		$ids_array  = array_map( 'intval', $business_ids );
		$ids_string = implode( ',', $ids_array );

		// 1. Get locations from custom table (for migrated businesses).
		$table_name = $wpdb->prefix . 'bd_locations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading, IDs are sanitized via intval.
		$table_locations = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string is sanitized above with intval.
			"SELECT business_id, lat, lng, address, city, state, postal_code 
			FROM {$table_name} 
			WHERE business_id IN ({$ids_string})",
			ARRAY_A
		);

		if ( $table_locations ) {
			foreach ( $table_locations as $loc ) {
				$this->location_cache[ (int) $loc['business_id'] ] = array(
					'lat'     => floatval( $loc['lat'] ),
					'lng'     => floatval( $loc['lng'] ),
					'address' => $loc['address'] ?? '',
					'city'    => $loc['city'] ?? '',
					'state'   => $loc['state'] ?? '',
					'zip'     => $loc['postal_code'] ?? '',
				);
			}
		}

		// 2. Find IDs not in custom table (need meta fallback).
		$ids_needing_meta = array_diff( $ids_array, array_keys( $this->location_cache ) );

		if ( empty( $ids_needing_meta ) ) {
			return;
		}

		// Batch fetch bd_location meta for remaining IDs.
		$meta_ids_string = implode( ',', $ids_needing_meta );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading, IDs are sanitized via intval.
		$meta_locations = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $meta_ids_string is sanitized above with intval.
			"SELECT post_id, meta_value 
			FROM {$wpdb->postmeta} 
			WHERE post_id IN ({$meta_ids_string}) 
			AND meta_key = 'bd_location'",
			ARRAY_A
		);

		if ( $meta_locations ) {
			foreach ( $meta_locations as $row ) {
				$location_meta = maybe_unserialize( $row['meta_value'] );

				if ( $location_meta && is_array( $location_meta ) && ! empty( $location_meta['lat'] ) && ! empty( $location_meta['lng'] ) ) {
					$this->location_cache[ (int) $row['post_id'] ] = array(
						'lat'     => floatval( $location_meta['lat'] ),
						'lng'     => floatval( $location_meta['lng'] ),
						'address' => $location_meta['address'] ?? '',
						'city'    => $location_meta['city'] ?? '',
						'state'   => $location_meta['state'] ?? '',
						'zip'     => $location_meta['zip'] ?? '',
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

		if ( empty( $business_ids ) ) {
			return;
		}

		$ids_array  = array_map( 'intval', $business_ids );
		$ids_string = implode( ',', $ids_array );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch loading, IDs are sanitized via intval.
		$hours_data = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string is sanitized above with intval.
			"SELECT post_id, meta_value 
			FROM {$wpdb->postmeta} 
			WHERE post_id IN ({$ids_string}) 
			AND meta_key = 'bd_hours'",
			ARRAY_A
		);

		if ( $hours_data ) {
			foreach ( $hours_data as $row ) {
				$hours = maybe_unserialize( $row['meta_value'] );
				if ( $hours && is_array( $hours ) ) {
					$this->hours_cache[ (int) $row['post_id'] ] = $hours;
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

	/**
	 * Calculate distances using cached location data.
	 *
	 * @return array Array of businesses with distance info.
	 */
	private function calculate_distances_from_cache() {
		$user_lat = $this->filters['lat'];
		$user_lng = $this->filters['lng'];

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
		$per_page = $this->filters['per_page'] ?? 20;
		$page     = $this->filters['page'] ?? 1;

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
