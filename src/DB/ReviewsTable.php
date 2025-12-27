<?php
/**
 * Reviews Database Table
 *
 * Handles storage and retrieval of business reviews.
 *
 * @package BusinessDirectory
 */

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewsTable
 */
class ReviewsTable {

	/**
	 * Cached table name.
	 *
	 * @var string|null
	 */
	private static $table_name = null;

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		if ( self::$table_name === null ) {
			self::$table_name = $wpdb->prefix . 'bd_reviews';
		}
		return self::$table_name;
	}

	/**
	 * Insert a new review.
	 *
	 * @param array $data Review data.
	 * @return int|false|\WP_Error Insert ID, false on failure, or WP_Error.
	 */
	public static function insert( $data ) {
		global $wpdb;

		if ( empty( $data['business_id'] ) || empty( $data['rating'] ) ) {
			return new \WP_Error( 'missing_fields', 'Business ID and rating are required' );
		}

		$rating = absint( $data['rating'] );
		if ( $rating < 1 || $rating > 5 ) {
			return new \WP_Error( 'invalid_rating', 'Rating must be between 1 and 5' );
		}

		// Build data array dynamically, only including non-null values.
		$clean_data = array(
			'business_id' => absint( $data['business_id'] ),
			'rating'      => $rating,
			'status'      => 'pending',
		);

		// Build format array dynamically.
		$formats = array( '%d', '%d', '%s' );

		// Add optional fields only if they exist.
		if ( ! empty( $data['user_id'] ) ) {
			$clean_data['user_id'] = absint( $data['user_id'] );
			$formats[]             = '%d';
		}

		if ( ! empty( $data['author_name'] ) ) {
			$clean_data['author_name'] = sanitize_text_field( $data['author_name'] );
			$formats[]                 = '%s';
		}

		if ( ! empty( $data['author_email'] ) ) {
			$clean_data['author_email'] = sanitize_email( $data['author_email'] );
			$formats[]                  = '%s';
		}

		if ( ! empty( $data['title'] ) ) {
			$clean_data['title'] = sanitize_text_field( $data['title'] );
			$formats[]           = '%s';
		}

		if ( ! empty( $data['content'] ) ) {
			$clean_data['content'] = wp_kses_post( $data['content'] );
			$formats[]             = '%s';
		}

		if ( ! empty( $data['photo_ids'] ) ) {
			$clean_data['photo_ids'] = sanitize_text_field( $data['photo_ids'] );
			$formats[]               = '%s';
		}

		if ( ! empty( $data['ip_address'] ) ) {
			$clean_data['ip_address'] = sanitize_text_field( $data['ip_address'] );
			$formats[]                = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::table(),
			$clean_data,
			$formats
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a single review by ID.
	 *
	 * @param int $review_id Review ID.
	 * @return array|null Review data or null.
	 */
	public static function get( $review_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d',
				absint( $review_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Get approved reviews for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return array Array of reviews.
	 */
	public static function get_by_business( $business_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " 
				WHERE business_id = %d AND status = 'approved' 
				ORDER BY created_at DESC",
				absint( $business_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Get pending reviews.
	 *
	 * @param int $limit Optional limit.
	 * @return array Array of pending reviews.
	 */
	public static function get_pending( $limit = 50 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " 
				WHERE status = 'pending' 
				ORDER BY created_at ASC 
				LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);
	}

	/**
	 * Count pending reviews.
	 *
	 * @return int Number of pending reviews.
	 */
	public static function count_pending() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'pending'"
		);
	}

	/**
	 * Approve a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool Success.
	 */
	public static function approve( $review_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			array( 'status' => 'approved' ),
			array( 'id' => absint( $review_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Reject a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool Success.
	 */
	public static function reject( $review_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			array( 'status' => 'rejected' ),
			array( 'id' => absint( $review_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool Success.
	 */
	public static function delete( $review_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			self::table(),
			array( 'id' => absint( $review_id ) ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get reviews by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of reviews.
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' 
				WHERE user_id = %d 
				ORDER BY created_at DESC',
				absint( $user_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Get average rating for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return float Average rating (0 if no reviews).
	 */
	public static function get_average_rating( $business_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT AVG(rating) FROM ' . self::table() . " 
				WHERE business_id = %d AND status = 'approved'",
				absint( $business_id )
			)
		);

		return $avg ? round( (float) $avg, 1 ) : 0;
	}

	/**
	 * Get review count for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return int Number of approved reviews.
	 */
	public static function get_review_count( $business_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . " 
				WHERE business_id = %d AND status = 'approved'",
				absint( $business_id )
			)
		);
	}
}
