<?php

/**
 * Business REST API Endpoint
 *
 * Handles business search and filter API requests.
 * Optimized to use batch cache priming to eliminate N+1 queries.
 *
 * @package BusinessDirectory
 * @subpackage API
 * @version 1.1.2
 */

namespace BusinessDirectory\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BusinessDirectory\Search\FilterHandler;
use BusinessDirectory\Search\QueryBuilder;

/**
 * Class BusinessEndpoint
 *
 * REST API endpoint for business search and filtering.
 */
class BusinessEndpoint {

	/**
	 * Pre-loaded location data from QueryBuilder.
	 *
	 * @var array
	 */
	private static $location_cache = array();

	/**
	 * Pre-loaded hours data from QueryBuilder.
	 *
	 * @var array
	 */
	private static $hours_cache = array();

	/**
	 * Initialize the endpoint.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'bd/v1',
			'/businesses',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_businesses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lat'         => array( 'type' => 'number' ),
					'lng'         => array( 'type' => 'number' ),
					'radius_km'   => array( 'type' => 'number' ),
					'categories'  => array( 'type' => 'string' ),
					'areas'       => array( 'type' => 'string' ),
					'tags'        => array( 'type' => 'string' ),
					'price_level' => array( 'type' => 'string' ),
					'min_rating'  => array( 'type' => 'number' ),
					'open_now'    => array( 'type' => 'boolean' ),
					'q'           => array( 'type' => 'string' ),
					'sort'        => array( 'type' => 'string' ),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			'bd/v1',
			'/filters',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_filter_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get businesses endpoint handler.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_businesses( $request ) {
		$filters = FilterHandler::sanitize_filters( $request->get_params() );

		// Check cache first.
		$cache_key = 'bd_businesses_' . md5( wp_json_encode( $filters ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		// Execute query with batch-loaded location/hours data.
		$query_builder = new QueryBuilder( $filters );
		$result        = $query_builder->get_businesses_with_location();

		// Store location/hours caches for use in format_business().
		self::$location_cache = isset( $result['locations'] ) ? $result['locations'] : array();
		self::$hours_cache    = isset( $result['hours'] ) ? $result['hours'] : array();

		// Extract business IDs for batch cache priming.
		$business_ids = array_column( $result['businesses'], 'id' );

		// Prime WordPress caches BEFORE formatting (eliminates N+1 queries).
		if ( ! empty( $business_ids ) ) {
			self::prepare_business_cache( $business_ids );
		}

		// Format businesses (now uses primed caches).
		$businesses = array();
		foreach ( $result['businesses'] as $b ) {
			$business = self::format_business( $b['id'] );

			if ( isset( $b['distance_km'] ) ) {
				$business['distance'] = array(
					'km'      => round( $b['distance_km'], 2 ),
					'mi'      => round( $b['distance_mi'], 2 ),
					'display' => round( $b['distance_mi'], 1 ) . ' mi away',
				);
			}

			$businesses[] = $business;
		}

		$bounds = self::calculate_bounds( $businesses );

		// Collect unique tags from results for dynamic tag bar.
		$unique_tags = self::collect_unique_tags( $businesses );

		$response = array(
			'businesses'      => $businesses,
			'total'           => $result['total'],
			'pages'           => isset( $result['pages'] ) ? $result['pages'] : 1,
			'page'            => $filters['page'],
			'per_page'        => $filters['per_page'],
			'bounds'          => $bounds,
			'available_tags'  => $unique_tags,
			'filters_applied' => self::get_applied_filters( $filters ),
		);

		// Cache response for 5 minutes.
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response );
	}

	/**
	 * Prime WordPress caches for batch of business IDs.
	 *
	 * This is the KEY optimization - by priming the meta and term caches
	 * BEFORE the formatting loop, all subsequent get_post_meta() and
	 * wp_get_post_terms() calls hit the cache instead of the database.
	 *
	 * Reduces ~15 queries per business down to 0 additional queries.
	 *
	 * @param array $business_ids Array of business post IDs.
	 */
	private static function prepare_business_cache( $business_ids ) {
		if ( empty( $business_ids ) ) {
			return;
		}

		// Prime the post cache (1 query for all posts).
		if ( function_exists( '_prime_post_caches' ) ) {
			\_prime_post_caches( $business_ids, true, true );
		}

		// Prime the post meta cache (1 query for ALL meta for ALL posts).
		if ( function_exists( 'update_meta_cache' ) ) {
			\update_meta_cache( 'post', $business_ids );
		}

		// Prime the term cache for all relevant taxonomies (1 query per taxonomy).
		if ( function_exists( 'update_object_term_cache' ) ) {
			\update_object_term_cache( $business_ids, 'bd_business' );
		}
	}

	/**
	 * Format single business for API response.
	 *
	 * Uses pre-primed WordPress caches and pre-loaded location/hours data.
	 * After cache priming, all calls here hit cache (0 database queries).
	 *
	 * @param int $business_id Business post ID.
	 * @return array Formatted business data.
	 */
	private static function format_business( $business_id ) {
		$post = get_post( $business_id );

		// Get featured image, with placeholder fallback.
		$featured_image = get_the_post_thumbnail_url( $business_id, 'medium' );
		if ( empty( $featured_image ) && function_exists( 'bd_get_business_image' ) ) {
			$image_data     = bd_get_business_image( $business_id, 'medium' );
			$featured_image = $image_data['url'];
		}

		// Use pre-loaded location data if available.
		$location = null;
		if ( isset( self::$location_cache[ $business_id ] ) ) {
			$loc      = self::$location_cache[ $business_id ];
			$location = array(
				'lat'     => floatval( isset( $loc['lat'] ) ? $loc['lat'] : 0 ),
				'lng'     => floatval( isset( $loc['lng'] ) ? $loc['lng'] : 0 ),
				'address' => isset( $loc['address'] ) ? $loc['address'] : '',
				'city'    => isset( $loc['city'] ) ? $loc['city'] : '',
				'state'   => isset( $loc['state'] ) ? $loc['state'] : '',
				'zip'     => isset( $loc['zip'] ) ? $loc['zip'] : '',
			);
		} else {
			// Fallback to meta (hits primed cache).
			$location_meta = get_post_meta( $business_id, 'bd_location', true );

			if ( $location_meta && is_array( $location_meta ) ) {
				$location = array(
					'lat'     => floatval( isset( $location_meta['lat'] ) ? $location_meta['lat'] : 0 ),
					'lng'     => floatval( isset( $location_meta['lng'] ) ? $location_meta['lng'] : 0 ),
					'address' => isset( $location_meta['address'] ) ? $location_meta['address'] : '',
					'city'    => isset( $location_meta['city'] ) ? $location_meta['city'] : '',
					'state'   => isset( $location_meta['state'] ) ? $location_meta['state'] : '',
					'zip'     => isset( $location_meta['zip'] ) ? $location_meta['zip'] : '',
				);
			}
		}

		// Get contact from meta (hits primed cache).
		$contact = get_post_meta( $business_id, 'bd_contact', true );
		if ( ! is_array( $contact ) ) {
			$contact = array();
		}

		// Get tags with full term data (hits primed cache).
		$tags      = array();
		$tag_terms = wp_get_post_terms( $business_id, 'bd_tag', array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $tag_terms ) && ! empty( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		// Get categories (hits primed cache, with error check).
		$categories = array();
		$cat_terms  = wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cat_terms ) ) {
			$categories = $cat_terms;
		}

		// Get areas (hits primed cache, with error check).
		$areas      = array();
		$area_terms = wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $area_terms ) ) {
			$areas = $area_terms;
		}

		// Determine if open now using pre-loaded hours data.
		$hours   = isset( self::$hours_cache[ $business_id ] ) ? self::$hours_cache[ $business_id ] : null;
		$is_open = FilterHandler::is_open_now( $business_id, $hours );

		return array(
			'id'             => $business_id,
			'title'          => get_the_title( $business_id ),
			'slug'           => $post ? $post->post_name : '',
			'excerpt'        => get_the_excerpt( $business_id ),
			'permalink'      => get_permalink( $business_id ),
			'featured_image' => $featured_image,
			'rating'         => floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) ),
			'review_count'   => intval( get_post_meta( $business_id, 'bd_review_count', true ) ),
			'price_level'    => get_post_meta( $business_id, 'bd_price_level', true ),
			'categories'     => $categories,
			'areas'          => $areas,
			'tags'           => $tags,
			'location'       => $location,
			'phone'          => isset( $contact['phone'] ) ? $contact['phone'] : '',
			'is_open_now'    => $is_open,
		);
	}

	/**
	 * Collect unique tags from business results for dynamic tag bar.
	 *
	 * @param array $businesses Array of formatted businesses.
	 * @return array Array of unique tags sorted by count.
	 */
	private static function collect_unique_tags( $businesses ) {
		$tags_map = array();

		foreach ( $businesses as $business ) {
			if ( ! empty( $business['tags'] ) ) {
				foreach ( $business['tags'] as $tag ) {
					$tag_id = $tag['id'];
					if ( ! isset( $tags_map[ $tag_id ] ) ) {
						$tags_map[ $tag_id ] = array(
							'id'    => $tag['id'],
							'name'  => $tag['name'],
							'slug'  => $tag['slug'],
							'count' => 0,
						);
					}
					++$tags_map[ $tag_id ]['count'];
				}
			}
		}

		// Sort by count descending.
		usort(
			$tags_map,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_values( $tags_map );
	}

	/**
	 * Calculate map bounds from businesses.
	 *
	 * @param array $businesses Array of formatted businesses.
	 * @return array|null Bounds array or null if no valid locations.
	 */
	private static function calculate_bounds( $businesses ) {
		if ( empty( $businesses ) ) {
			return null;
		}

		$lats = array();
		$lngs = array();

		foreach ( $businesses as $business ) {
			if ( ! empty( $business['location']['lat'] ) && ! empty( $business['location']['lng'] ) ) {
				$lats[] = $business['location']['lat'];
				$lngs[] = $business['location']['lng'];
			}
		}

		if ( empty( $lats ) ) {
			return null;
		}

		return array(
			'north' => max( $lats ),
			'south' => min( $lats ),
			'east'  => max( $lngs ),
			'west'  => min( $lngs ),
		);
	}

	/**
	 * Get summary of applied filters.
	 *
	 * @param array $filters Sanitized filter array.
	 * @return array Summary of applied filters.
	 */
	private static function get_applied_filters( $filters ) {
		$applied = array();

		if ( ! empty( $filters['categories'] ) ) {
			$applied['categories'] = count( $filters['categories'] );
		}
		if ( ! empty( $filters['areas'] ) ) {
			$applied['areas'] = count( $filters['areas'] );
		}
		if ( ! empty( $filters['tags'] ) ) {
			$applied['tags'] = count( $filters['tags'] );
		}
		if ( ! empty( $filters['price_level'] ) ) {
			$applied['price_level'] = $filters['price_level'];
		}
		if ( ! empty( $filters['min_rating'] ) ) {
			$applied['min_rating'] = $filters['min_rating'];
		}
		if ( ! empty( $filters['open_now'] ) ) {
			$applied['open_now'] = true;
		}
		if ( ! empty( $filters['q'] ) ) {
			$applied['search'] = $filters['q'];
		}
		if ( ! empty( $filters['radius_km'] ) ) {
			$applied['radius_km'] = $filters['radius_km'];
		}

		return $applied;
	}

	/**
	 * Get filter metadata endpoint handler.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_filter_metadata() {
		$metadata = FilterHandler::get_filter_metadata();
		return rest_ensure_response( $metadata );
	}
}

BusinessEndpoint::init();
