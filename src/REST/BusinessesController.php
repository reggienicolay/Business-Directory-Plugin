<?php

namespace BD\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * REST API: /bd/v1/businesses
 */
class BusinessesController {

	public static function register() {
		register_rest_route(
			'bd/v1',
			'/businesses',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_businesses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'q'        => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lat'      => array(
						'default'           => null,
						'sanitize_callback' => 'floatval',
					),
					'lng'      => array(
						'default'           => null,
						'sanitize_callback' => 'floatval',
					),
				),
			)
		);
	}

	public static function get_businesses( $request ) {
		// =====================================================================
		// SCRAPING PROTECTION (anonymous requests)
		//
		// Real users hit this endpoint through our own JS, which sends
		// X-WP-Nonce via wp_localize_script(..., 'nonce' => wp_create_nonce(
		// 'wp_rest' )). CLI scrapers (curl, wget, python-requests, scrapy)
		// don't have a valid nonce, so they get a 401. Logged-in users skip
		// this gate entirely.
		//
		// Layered:
		//   - Nonce required (binds the endpoint to first-party JS)
		//   - Rate limit 60/min/IP (matches other public endpoints)
		//   - Hard cap on `page` so even a determined scraper can't paginate
		//     the entire directory in 20 requests of 50 records
		// =====================================================================
		if ( ! is_user_logged_in() ) {
			$nonce = $request->get_header( 'x_wp_nonce' );
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new \WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Invalid or missing nonce.', 'business-directory' ),
					array( 'status' => 401 )
				);
			}

			$rate = \BD\Security\RateLimit::check(
				'businesses_get',
				\BD\Security\RateLimit::get_client_ip(),
				60,
				MINUTE_IN_SECONDS
			);
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}

			$page = (int) $request['page'];
			if ( $page > 20 ) {
				return new \WP_Error(
					'rest_pagination_limit',
					__( 'Pagination limit reached. Refine your filters to see more results.', 'business-directory' ),
					array( 'status' => 400 )
				);
			}
		}

		$args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'publish',
			'posts_per_page' => min( $request['per_page'], 50 ),
			'paged'          => $request['page'],
		);

		// Keyword search
		if ( ! empty( $request['q'] ) ) {
			$args['s'] = $request['q'];
		}

		// Category filter
		if ( ! empty( $request['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bd_category',
					'field'    => 'slug',
					'terms'    => $request['category'],
				),
			);
		}

		$query = new \WP_Query( $args );

		// Batch-prime caches to avoid N+1 queries.
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
			update_object_term_cache( $post_ids, 'bd_business' );
			\BD\DB\LocationsTable::batch_load( $post_ids );

			// Prime attachment post cache for thumbnails. update_meta_cache()
			// above cached _thumbnail_id for each business, so this loop reads
			// from cache — then one _prime_post_caches() call loads all the
			// attachment posts in a single query instead of one per business.
			$thumbnail_ids = array();
			foreach ( $post_ids as $pid ) {
				$tid = (int) get_post_meta( $pid, '_thumbnail_id', true );
				if ( $tid ) {
					$thumbnail_ids[] = $tid;
				}
			}
			if ( ! empty( $thumbnail_ids ) ) {
				_prime_post_caches( array_unique( $thumbnail_ids ), false, false );
			}
		}

		$businesses = array();
		foreach ( $query->posts as $post ) {
			$location = \BD\DB\LocationsTable::get( $post->ID );

			$business = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'excerpt'     => $post->post_excerpt,
				'permalink'   => get_permalink( $post->ID ),
				'thumbnail'   => get_the_post_thumbnail_url( $post->ID, 'medium' ),
				'categories'  => self::get_cached_term_names( $post->ID, 'bd_category' ),
				'phone'       => get_post_meta( $post->ID, 'bd_phone', true ),
				'website'     => get_post_meta( $post->ID, 'bd_website', true ),
				'price_level' => get_post_meta( $post->ID, 'bd_price_level', true ),
			);

			if ( $location ) {
				$business['location'] = array(
					'lat'     => (float) $location['lat'],
					'lng'     => (float) $location['lng'],
					'address' => $location['address'],
					'city'    => $location['city'],
				);

				// Calculate distance if user location provided
				if ( $request['lat'] !== null && $request['lng'] !== null ) {
					$business['distance_km'] = self::calculate_distance(
						$request['lat'],
						$request['lng'],
						$location['lat'],
						$location['lng']
					);
				}
			}

			$businesses[] = $business;
		}

		return rest_ensure_response(
			array(
				'data'  => $businesses,
				'total' => $query->found_posts,
				'pages' => $query->max_num_pages,
			)
		);
	}

	/**
	 * Get term names from the object term cache.
	 *
	 * Uses get_the_terms() which reads from the cache primed by
	 * update_object_term_cache(), unlike wp_get_post_terms() which
	 * always queries the database.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string[] Array of term names.
	 */
	private static function get_cached_term_names( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'name' );
	}

	private static function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371; // km

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );

		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) +
			cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			sin( $d_lng / 2 ) * sin( $d_lng / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( $earth_radius * $c, 2 );
	}
}
