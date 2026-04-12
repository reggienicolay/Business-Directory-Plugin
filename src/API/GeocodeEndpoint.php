<?php

namespace BusinessDirectory\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BusinessDirectory\Search\Geocoder;

class GeocodeEndpoint {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		// Geocode address to coordinates
		register_rest_route(
			'bd/v1',
			'/geocode',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'geocode' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'address' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Reverse geocode coordinates to address
		register_rest_route(
			'bd/v1',
			'/geocode/reverse',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'reverse_geocode' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lat' => array(
						'required' => true,
						'type'     => 'number',
					),
					'lng' => array(
						'required' => true,
						'type'     => 'number',
					),
				),
			)
		);
	}

	/**
	 * Geocode address
	 */
	public static function geocode( $request ) {
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$address = $request->get_param( 'address' );
		$result  = Geocoder::geocode( $address );

		if ( ! $result ) {
			return new \WP_Error( 'geocode_failed', 'Could not geocode address', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Reverse geocode
	 */
	public static function reverse_geocode( $request ) {
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$lat = $request->get_param( 'lat' );
		$lng = $request->get_param( 'lng' );

		$result = Geocoder::reverse_geocode( $lat, $lng );

		if ( ! $result ) {
			return new \WP_Error( 'reverse_geocode_failed', 'Could not reverse geocode coordinates', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Rate-limit geocode requests to prevent abuse of the Nominatim proxy.
	 *
	 * Public endpoint → per-IP limiting. Nominatim's own policy is 1 req/sec;
	 * we allow 10 per minute per IP which is generous for legitimate use but
	 * blocks bulk scraping.
	 *
	 * @return true|\WP_Error
	 */
	private static function check_rate_limit() {
		if ( ! class_exists( '\BD\Security\RateLimit' ) ) {
			return true;
		}
		$ip = \BD\Security\RateLimit::get_client_ip();
		return \BD\Security\RateLimit::check( 'geocode', $ip, 10, 60 );
	}
}
