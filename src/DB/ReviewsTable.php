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
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['business_id'] ) || empty( $data['rating'] ) ) {
			return false;
		}

		$rating = absint( $data['rating'] );
		if ( $rating < 1 || $rating > 5 ) {
			return false;
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
			// Use sanitize_textarea_field to strip HTML (reviews are plain text).
			$clean_data['content'] = sanitize_textarea_field( $data['content'] );
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

		if ( ! $result ) {
			return false;
		}

		$review_id = $wpdb->insert_id;

		/**
		 * Fires after a review is inserted.
		 *
		 * @param int   $review_id   The new review ID.
		 * @param array $clean_data  The sanitized review data.
		 */
		do_action( 'bd_review_inserted', $review_id, $clean_data );

		return $review_id;
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
	 * @param int $limit       Optional limit (0 = no limit).
	 * @param int $offset      Optional offset for pagination.
	 * @return array Array of reviews.
	 */
	public static function get_by_business( $business_id, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table() . " 
			WHERE business_id = %d AND status = 'approved' 
			ORDER BY created_at DESC",
			absint( $business_id )
		);

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $limit ), absint( $offset ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A );
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

		$review_id = absint( $review_id );

		// Get review data before update to check it exists.
		$review = self::get( $review_id );
		if ( ! $review ) {
			return false;
		}

		// Skip if already approved.
		if ( 'approved' === $review['status'] ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			array( 'status' => 'approved' ),
			array( 'id' => $review_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			return false;
		}

		// Update review array with new status for the hook.
		$review['status'] = 'approved';

		/**
		 * Fires after a review is approved.
		 *
		 * @param int   $review_id The review ID.
		 * @param array $review    The review data (with updated status).
		 */
		do_action( 'bd_review_approved', $review_id, $review );

		// Update business rating cache.
		self::update_business_rating_cache( $review['business_id'] );

		return true;
	}

	/**
	 * Reject a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool Success.
	 */
	public static function reject( $review_id ) {
		global $wpdb;

		$review_id = absint( $review_id );

		// Get review data before update.
		$review = self::get( $review_id );
		if ( ! $review ) {
			return false;
		}

		// Track if it was previously approved (for rating cache update).
		$was_approved = ( 'approved' === $review['status'] );

		// Skip if already rejected.
		if ( 'rejected' === $review['status'] ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			array( 'status' => 'rejected' ),
			array( 'id' => $review_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			return false;
		}

		// Update review array with new status for the hook.
		$review['status'] = 'rejected';

		/**
		 * Fires after a review is rejected.
		 *
		 * @param int   $review_id The review ID.
		 * @param array $review    The review data (with updated status).
		 */
		do_action( 'bd_review_rejected', $review_id, $review );

		// Update business rating cache if it was previously approved.
		if ( $was_approved ) {
			self::update_business_rating_cache( $review['business_id'] );
		}

		return true;
	}

	/**
	 * Delete a review and its associated photos.
	 *
	 * @param int  $review_id     Review ID.
	 * @param bool $delete_photos Whether to delete associated photo attachments.
	 * @return bool Success.
	 */
	public static function delete( $review_id, $delete_photos = true ) {
		global $wpdb;

		$review_id = absint( $review_id );

		// Get review data before delete for cleanup and hooks.
		$review = self::get( $review_id );
		if ( ! $review ) {
			return false;
		}

		// Track if it was approved (for rating cache update).
		$was_approved = ( 'approved' === $review['status'] );

		// Delete the review record first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			self::table(),
			array( 'id' => $review_id ),
			array( '%d' )
		);

		if ( $result === false ) {
			return false;
		}

		// Delete associated photos AFTER successful DB delete.
		if ( $delete_photos && ! empty( $review['photo_ids'] ) ) {
			$photo_ids = array_map( 'absint', explode( ',', $review['photo_ids'] ) );
			foreach ( $photo_ids as $photo_id ) {
				if ( $photo_id ) {
					wp_delete_attachment( $photo_id, true );
				}
			}
		}

		/**
		 * Fires after a review is deleted.
		 *
		 * @param int   $review_id The review ID.
		 * @param array $review    The review data (before deletion).
		 */
		do_action( 'bd_review_deleted', $review_id, $review );

		// Update business rating cache if it was approved.
		if ( $was_approved ) {
			self::update_business_rating_cache( $review['business_id'] );
		}

		return true;
	}

	/**
	 * Get reviews by user ID.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Optional limit (0 = no limit).
	 * @param int $offset  Optional offset for pagination.
	 * @return array Array of reviews.
	 */
	public static function get_by_user( $user_id, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' 
			WHERE user_id = %d 
			ORDER BY created_at DESC',
			absint( $user_id )
		);

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $limit ), absint( $offset ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A );
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

		return $avg ? round( (float) $avg, 1 ) : 0.0;
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

	/**
	 * Update cached rating values on business post meta.
	 *
	 * @param int $business_id Business ID.
	 * @return bool True if updated, false if business doesn't exist.
	 */
	public static function update_business_rating_cache( $business_id ) {
		$business_id = absint( $business_id );

		// Validate business exists.
		if ( ! $business_id || ! get_post( $business_id ) ) {
			return false;
		}

		$avg_rating   = self::get_average_rating( $business_id );
		$review_count = self::get_review_count( $business_id );

		update_post_meta( $business_id, 'bd_avg_rating', $avg_rating );
		update_post_meta( $business_id, 'bd_review_count', $review_count );

		/**
		 * Fires after business rating cache is updated.
		 *
		 * @param int   $business_id  The business ID.
		 * @param float $avg_rating   The new average rating.
		 * @param int   $review_count The new review count.
		 */
		do_action( 'bd_business_rating_updated', $business_id, $avg_rating, $review_count );

		return true;
	}

	/**
	 * Increment helpful count for a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool True if incremented, false if review doesn't exist or error.
	 */
	public static function increment_helpful( $review_id ) {
		global $wpdb;

		$review_id = absint( $review_id );

		// Verify review exists.
		if ( ! self::get( $review_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET helpful_count = helpful_count + 1 WHERE id = %d',
				$review_id
			)
		);

		// $wpdb->query returns number of affected rows, or false on error.
		return $result > 0;
	}
}
