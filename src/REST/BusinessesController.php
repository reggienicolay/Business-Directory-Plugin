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

		$businesses = array();
		foreach ( $query->posts as $post ) {
			$location = \BD\DB\LocationsTable::get( $post->ID );

			$business = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'excerpt'     => $post->post_excerpt,
				'permalink'   => get_permalink( $post->ID ),
				'thumbnail'   => get_the_post_thumbnail_url( $post->ID, 'medium' ),
				'categories'  => wp_get_post_terms( $post->ID, 'bd_category', array( 'fields' => 'names' ) ),
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
				if ( $request['lat'] && $request['lng'] ) {
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
