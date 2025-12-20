<?php
namespace BusinessDirectory\API;

use BusinessDirectory\Search\FilterHandler;
use BusinessDirectory\Search\QueryBuilder;
use BusinessDirectory\Utils\Cache;

class BusinessEndpoint {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

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

	public static function get_businesses( $request ) {
		$filters = FilterHandler::sanitize_filters( $request->get_params() );

		$cache_key = Cache::get_query_key( $filters );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$query_builder = new QueryBuilder( $filters );
		$result        = $query_builder->get_businesses_with_location();

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

		// Collect unique tags from results for dynamic tag bar
		$unique_tags = self::collect_unique_tags( $businesses );

		$response = array(
			'businesses'      => $businesses,
			'total'           => $result['total'],
			'pages'           => $result['pages'] ?? 1,
			'page'            => $filters['page'],
			'per_page'        => $filters['per_page'],
			'bounds'          => $bounds,
			'available_tags'  => $unique_tags,
			'filters_applied' => self::get_applied_filters( $filters ),
		);

		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response );
	}

	/**
	 * Format single business - READS FROM POST META ONLY
	 */
	private static function format_business( $business_id ) {
		$post = get_post( $business_id );

		// Get location from bd_location meta field
		$location_meta = get_post_meta( $business_id, 'bd_location', true );

		$location = null;
		if ( $location_meta && is_array( $location_meta ) ) {
			$location = array(
				'lat'     => floatval( $location_meta['lat'] ?? 0 ),
				'lng'     => floatval( $location_meta['lng'] ?? 0 ),
				'address' => $location_meta['address'] ?? '',
				'city'    => $location_meta['city'] ?? '',
				'state'   => $location_meta['state'] ?? '',
				'zip'     => $location_meta['zip'] ?? '',
			);
		}

		// Get contact from bd_contact meta field
		$contact = get_post_meta( $business_id, 'bd_contact', true );
		if ( ! is_array( $contact ) ) {
			$contact = array();
		}

		// Get tags with full term data
		$tags      = array();
		$tag_terms = wp_get_post_terms( $business_id, 'bd_tag', array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		return array(
			'id'             => $business_id,
			'title'          => get_the_title( $business_id ),
			'slug'           => $post->post_name,
			'excerpt'        => get_the_excerpt( $business_id ),
			'permalink'      => get_permalink( $business_id ),
			'featured_image' => get_the_post_thumbnail_url( $business_id, 'medium' ),
			'rating'         => floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) ),
			'review_count'   => intval( get_post_meta( $business_id, 'bd_review_count', true ) ),
			'price_level'    => get_post_meta( $business_id, 'bd_price_level', true ),
			'categories'     => wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) ),
			'areas'          => wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'names' ) ),
			'tags'           => $tags,
			'location'       => $location,
			'phone'          => $contact['phone'] ?? '',
			'is_open_now'    => FilterHandler::is_open_now( $business_id ),
		);
	}

	/**
	 * Collect unique tags from business results for dynamic tag bar
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

		// Sort by count descending
		usort(
			$tags_map,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_values( $tags_map );
	}

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

	public static function get_filter_metadata() {
		$metadata = FilterHandler::get_filter_metadata();
		return rest_ensure_response( $metadata );
	}
}

BusinessEndpoint::init();
