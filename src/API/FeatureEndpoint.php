<?php
/**
 * Feature Endpoint - REST API for cross-site business embeds
 */
namespace BD\API;

if ( ! defined( 'ABSPATH' ) ) exit;

class FeatureEndpoint {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'bd/v1', '/feature', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_featured_businesses' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'ids' => array( 'required' => true, 'type' => 'string' ) ),
		));

		register_rest_route( 'bd/v1', '/feature/search', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'search_businesses' ),
			'permission_callback' => '__return_true', // Public - only returns published business info
			'args'                => array(
				'q'        => array( 'type' => 'string', 'default' => '' ),
				'category' => array( 'type' => 'integer' ),
				'per_page' => array( 'type' => 'integer', 'default' => 20 ),
			),
		));
	}

	public static function can_edit_posts() { return current_user_can( 'edit_posts' ); }

	public static function get_featured_businesses( $request ) {
		$ids = array_filter( array_map( 'absint', explode( ',', $request->get_param( 'ids' ) ) ) );
		if ( empty( $ids ) ) return new \WP_Error( 'no_ids', 'No valid IDs', array( 'status' => 400 ) );

		$businesses = array();
		foreach ( $ids as $id ) {
			$b = self::format_business( $id );
			if ( $b ) $businesses[] = $b;
		}

		$response = rest_ensure_response( array( 'businesses' => $businesses, 'count' => count( $businesses ), 'source' => home_url() ) );
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';
		if ( self::is_allowed_origin( $origin ) ) {
			$response->header( 'Access-Control-Allow-Origin', $origin );
			$response->header( 'Access-Control-Allow-Methods', 'GET' );
		}
		return $response;
	}

	public static function search_businesses( $request ) {
		$args = array( 'post_type' => 'bd_business', 'post_status' => 'publish', 'posts_per_page' => min( $request->get_param( 'per_page' ), 50 ), 'orderby' => 'title', 'order' => 'ASC' );
		if ( $request->get_param( 'q' ) ) $args['s'] = $request->get_param( 'q' );
		if ( $request->get_param( 'category' ) ) $args['tax_query'] = array( array( 'taxonomy' => 'bd_category', 'field' => 'term_id', 'terms' => $request->get_param( 'category' ) ) );

		$query = new \WP_Query( $args );
		$businesses = array();
		foreach ( $query->posts as $post ) {
			$cats = wp_get_post_terms( $post->ID, 'bd_category', array( 'fields' => 'names' ) );
			$businesses[] = array( 'id' => $post->ID, 'title' => $post->post_title, 'category' => ! empty( $cats ) ? $cats[0] : '', 'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ), 'rating' => floatval( get_post_meta( $post->ID, 'bd_avg_rating', true ) ) );
		}

		$all_cats = get_terms( array( 'taxonomy' => 'bd_category', 'hide_empty' => true ) );
		$cats_list = array();
		if ( ! is_wp_error( $all_cats ) ) foreach ( $all_cats as $cat ) $cats_list[] = array( 'id' => $cat->term_id, 'name' => $cat->name );

		$response = rest_ensure_response( array( 'businesses' => $businesses, 'categories' => $cats_list, 'total' => $query->found_posts ) );
		
		// Add CORS headers for cross-site requests
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';
		if ( self::is_allowed_origin( $origin ) ) {
			$response->header( 'Access-Control-Allow-Origin', $origin );
			$response->header( 'Access-Control-Allow-Methods', 'GET' );
		}
		
		return $response;
	}

	private static function format_business( $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_status !== 'publish' || $post->post_type !== 'bd_business' ) return null;

		$location = get_post_meta( $id, 'bd_location', true );
		$city = is_array( $location ) && ! empty( $location['city'] ) ? $location['city'] : '';
		$cats = wp_get_post_terms( $id, 'bd_category', array( 'fields' => 'names' ) );
		$contact = get_post_meta( $id, 'bd_contact', true );
		$excerpt = $post->post_excerpt ?: wp_trim_words( strip_shortcodes( $post->post_content ), 20, '...' );

		return array(
			'id' => $id, 'title' => $post->post_title, 'slug' => $post->post_name, 'excerpt' => $excerpt, 'permalink' => get_permalink( $id ),
			'featured_image' => array( 'thumbnail' => get_the_post_thumbnail_url( $id, 'thumbnail' ), 'medium' => get_the_post_thumbnail_url( $id, 'medium' ) ),
			'rating' => floatval( get_post_meta( $id, 'bd_avg_rating', true ) ), 'review_count' => intval( get_post_meta( $id, 'bd_review_count', true ) ),
			'price_level' => get_post_meta( $id, 'bd_price_level', true ), 'categories' => $cats, 'city' => $city,
			'phone' => isset( $contact['phone'] ) ? $contact['phone'] : '', 'website' => isset( $contact['website'] ) ? $contact['website'] : '',
		);
	}

	private static function is_allowed_origin( $origin ) {
		$domains = array( 'lovetrivalley.com', 'www.lovetrivalley.com', 'lovelivermore.com', 'www.lovelivermore.com', 'lovepleasanton.com', 'www.lovepleasanton.com', 'lovedublin.com', 'www.lovedublin.com', 'lovesanramon.com', 'www.lovesanramon.com', 'lovedanville.com', 'www.lovedanville.com' );
		$host = wp_parse_url( $origin, PHP_URL_HOST );
		if ( in_array( $host, $domains ) ) return true;
		if ( is_multisite() ) {
			foreach ( get_sites( array( 'number' => 100 ) ) as $site ) {
				if ( wp_parse_url( get_site_url( $site->blog_id ), PHP_URL_HOST ) === $host ) return true;
			}
		}
		return false;
	}
}

FeatureEndpoint::init();
