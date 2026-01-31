<?php
/**
 * Duplicate Business Merger
 *
 * Handles merging duplicate businesses and cleaning up resolved duplicates.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DuplicateMerger
 */
class DuplicateMerger {

	/**
	 * Action constants for duplicate resolution.
	 */
	const ACTION_DELETE   = 'delete';
	const ACTION_TRASH    = 'trash';
	const ACTION_REDIRECT = 'redirect';

	/**
	 * Get allowed action values.
	 *
	 * @return array Allowed actions.
	 */
	public static function get_allowed_actions() {
		return array( self::ACTION_DELETE, self::ACTION_TRASH, self::ACTION_REDIRECT );
	}

	/**
	 * Merge duplicate businesses into a primary record.
	 *
	 * @param int    $primary_id    The business ID to keep as primary.
	 * @param array  $duplicate_ids Array of business IDs to merge into primary.
	 * @param string $action        Action to take with duplicates: delete, trash, or redirect.
	 * @param array  $options       Additional merge options.
	 * @return array|\WP_Error Result with merged data or error.
	 */
	public static function merge( $primary_id, $duplicate_ids, $action = self::ACTION_TRASH, $options = array() ) {
		// Sanitize primary ID.
		$primary_id = absint( $primary_id );

		// Validate action.
		if ( ! in_array( $action, self::get_allowed_actions(), true ) ) {
			$action = self::ACTION_TRASH;
		}

		$primary = get_post( $primary_id );
		if ( ! $primary || 'bd_business' !== $primary->post_type ) {
			return new \WP_Error( 'invalid_primary', __( 'Primary business not found.', 'business-directory' ) );
		}

		// Validate duplicate IDs.
		$valid_duplicates = array();
		foreach ( $duplicate_ids as $dup_id ) {
			$dup_id = absint( $dup_id );
			$dup    = get_post( $dup_id );
			if ( $dup && 'bd_business' === $dup->post_type && $dup_id !== $primary_id ) {
				$valid_duplicates[] = $dup_id;
			}
		}

		if ( empty( $valid_duplicates ) ) {
			return new \WP_Error( 'no_duplicates', __( 'No valid duplicate businesses to merge.', 'business-directory' ) );
		}

		$defaults = array(
			'merge_reviews'    => true,
			'merge_photos'     => true,
			'merge_meta'       => true,
			'merge_categories' => true,
			'merge_tags'       => true,
			'keep_best_data'   => true,
		);

		$options = wp_parse_args( $options, $defaults );
		$log     = array();

		do_action( 'bd_before_duplicate_merge', $primary_id, $valid_duplicates, $options );

		try {
			// Merge reviews.
			if ( $options['merge_reviews'] ) {
				$reviews_merged = self::merge_reviews( $primary_id, $valid_duplicates );
				$log['reviews'] = $reviews_merged;
			}

			// Merge photos/gallery.
			if ( $options['merge_photos'] ) {
				$photos_merged  = self::merge_photos( $primary_id, $valid_duplicates );
				$log['photos']  = $photos_merged;
			}

			// Merge meta data (phone, website, etc.).
			if ( $options['merge_meta'] && $options['keep_best_data'] ) {
				$meta_merged = self::merge_meta( $primary_id, $valid_duplicates );
				$log['meta'] = $meta_merged;
			}

			// Merge categories.
			if ( $options['merge_categories'] ) {
				$cats_merged       = self::merge_terms( $primary_id, $valid_duplicates, 'bd_category' );
				$log['categories'] = $cats_merged;
			}

			// Merge tags.
			if ( $options['merge_tags'] ) {
				$tags_merged = self::merge_terms( $primary_id, $valid_duplicates, 'bd_tag' );
				$log['tags'] = $tags_merged;
			}

			// Transfer claims to primary.
			$claims_transferred = self::transfer_claims( $primary_id, $valid_duplicates );
			$log['claims']      = $claims_transferred;

			// Handle the duplicate records based on action.
			foreach ( $valid_duplicates as $dup_id ) {
				switch ( $action ) {
					case self::ACTION_DELETE:
						$result = self::delete_business( $dup_id );
						break;

					case self::ACTION_REDIRECT:
						$result = self::setup_redirect( $dup_id, $primary_id );
						break;

					case self::ACTION_TRASH:
					default:
						$result = wp_trash_post( $dup_id );
						break;
				}

				if ( ! $result ) {
					$log['errors'][] = sprintf(
						/* translators: %d: business ID */
						__( 'Failed to process duplicate ID %d', 'business-directory' ),
						$dup_id
					);
				}
			}

			// Log the merge action.
			self::log_merge( $primary_id, $valid_duplicates, $action, $log );

			// Clear duplicate caches.
			DuplicateFinder::clear_cache();

			do_action( 'bd_after_duplicate_merge', $primary_id, $valid_duplicates, $log );

			return array(
				'success'    => true,
				'primary_id' => $primary_id,
				'merged'     => $valid_duplicates,
				'action'     => $action,
				'log'        => $log,
			);

		} catch ( \Exception $e ) {
			return new \WP_Error( 'merge_failed', $e->getMessage() );
		}
	}

	/**
	 * Merge reviews from duplicates into primary using batch update.
	 *
	 * @param int   $primary_id    Primary business ID.
	 * @param array $duplicate_ids Duplicate business IDs.
	 * @return int Number of reviews merged.
	 */
	private static function merge_reviews( $primary_id, $duplicate_ids ) {
		global $wpdb;

		// Defensive check - should already be validated by caller.
		if ( empty( $duplicate_ids ) ) {
			return 0;
		}

		$table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists.
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		// Use single batch update instead of loop.
		$ids_placeholder = implode( ',', array_fill( 0, count( $duplicate_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$merged = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET business_id = %d WHERE business_id IN ({$ids_placeholder})",
				$primary_id,
				...$duplicate_ids
			)
		);

		// Clear review caches.
		delete_transient( 'bd_reviews_' . $primary_id );
		delete_post_meta( $primary_id, 'bd_review_count_cache' );
		delete_post_meta( $primary_id, 'bd_rating_cache' );

		return absint( $merged );
	}

	/**
	 * Merge photos from duplicates into primary.
	 *
	 * @param int   $primary_id    Primary business ID.
	 * @param array $duplicate_ids Duplicate business IDs.
	 * @return int Number of photos merged.
	 */
	private static function merge_photos( $primary_id, $duplicate_ids ) {
		$merged = 0;

		// Get primary's existing gallery.
		$primary_gallery = get_post_meta( $primary_id, 'bd_gallery', true );
		if ( ! is_array( $primary_gallery ) ) {
			$primary_gallery = array();
		}

		foreach ( $duplicate_ids as $dup_id ) {
			// Get duplicate's gallery.
			$dup_gallery = get_post_meta( $dup_id, 'bd_gallery', true );
			if ( is_array( $dup_gallery ) && ! empty( $dup_gallery ) ) {
				$primary_gallery = array_merge( $primary_gallery, $dup_gallery );
				$merged         += count( $dup_gallery );
			}

			// Check for featured image.
			$dup_thumbnail = get_post_thumbnail_id( $dup_id );
			if ( $dup_thumbnail && ! has_post_thumbnail( $primary_id ) ) {
				set_post_thumbnail( $primary_id, $dup_thumbnail );
				++$merged;
			}
		}

		// Remove duplicates and update.
		$primary_gallery = array_unique( array_filter( $primary_gallery ) );
		if ( ! empty( $primary_gallery ) ) {
			update_post_meta( $primary_id, 'bd_gallery', $primary_gallery );
		}

		return $merged;
	}

	/**
	 * Merge meta data from duplicates into primary (fills in blanks).
	 *
	 * @param int   $primary_id    Primary business ID.
	 * @param array $duplicate_ids Duplicate business IDs.
	 * @return array Meta fields that were filled in.
	 */
	private static function merge_meta( $primary_id, $duplicate_ids ) {
		$meta_keys = array(
			'bd_phone',
			'bd_website',
			'bd_email',
			'bd_hours',
			'bd_price_level',
			'bd_google_place_id',
			'bd_social_facebook',
			'bd_social_instagram',
			'bd_social_twitter',
			'bd_social_yelp',
		);

		$filled = array();

		foreach ( $meta_keys as $key ) {
			$primary_value = get_post_meta( $primary_id, $key, true );

			// Skip if primary already has this value.
			if ( ! empty( $primary_value ) ) {
				continue;
			}

			// Look for value in duplicates.
			foreach ( $duplicate_ids as $dup_id ) {
				$dup_value = get_post_meta( $dup_id, $key, true );
				if ( ! empty( $dup_value ) ) {
					update_post_meta( $primary_id, $key, $dup_value );
					$filled[] = $key;
					break;
				}
			}
		}

		// Special handling for location data.
		if ( class_exists( '\BD\DB\LocationsTable' ) ) {
			$primary_location = LocationsTable::get( $primary_id );
			if ( ! $primary_location || empty( $primary_location['address'] ) ) {
				foreach ( $duplicate_ids as $dup_id ) {
					$dup_location = LocationsTable::get( $dup_id );
					if ( $dup_location && ! empty( $dup_location['address'] ) ) {
						LocationsTable::delete( $primary_id );
						$dup_location['business_id'] = $primary_id;
						LocationsTable::insert( $dup_location );
						$filled[] = 'location';
						break;
					}
				}
			}
		}

		return $filled;
	}

	/**
	 * Merge taxonomy terms from duplicates into primary.
	 *
	 * @param int    $primary_id    Primary business ID.
	 * @param array  $duplicate_ids Duplicate business IDs.
	 * @param string $taxonomy      Taxonomy name.
	 * @return int Number of terms added.
	 */
	private static function merge_terms( $primary_id, $duplicate_ids, $taxonomy ) {
		$primary_terms = wp_get_object_terms( $primary_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $primary_terms ) ) {
			$primary_terms = array();
		}

		$added = 0;
		foreach ( $duplicate_ids as $dup_id ) {
			$dup_terms = wp_get_object_terms( $dup_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $dup_terms ) ) {
				continue;
			}

			foreach ( $dup_terms as $term_id ) {
				if ( ! in_array( $term_id, $primary_terms, true ) ) {
					$primary_terms[] = $term_id;
					++$added;
				}
			}
		}

		if ( $added > 0 ) {
			wp_set_object_terms( $primary_id, $primary_terms, $taxonomy );
		}

		return $added;
	}

	/**
	 * Transfer claim ownership from duplicates to primary.
	 *
	 * @param int   $primary_id    Primary business ID.
	 * @param array $duplicate_ids Duplicate business IDs.
	 * @return int Number of claims transferred.
	 */
	private static function transfer_claims( $primary_id, $duplicate_ids ) {
		$transferred = 0;

		// Check if primary is already claimed.
		$primary_claimed_by = get_post_meta( $primary_id, 'bd_claimed_by', true );

		foreach ( $duplicate_ids as $dup_id ) {
			$dup_claimed_by = get_post_meta( $dup_id, 'bd_claimed_by', true );

			if ( ! empty( $dup_claimed_by ) ) {
				if ( empty( $primary_claimed_by ) ) {
					// Transfer claim to primary.
					update_post_meta( $primary_id, 'bd_claimed_by', $dup_claimed_by );
					update_post_meta( $primary_id, 'bd_claimed_at', get_post_meta( $dup_id, 'bd_claimed_at', true ) );
					$primary_claimed_by = $dup_claimed_by;
					++$transferred;
				}

				// Clear duplicate's claim.
				delete_post_meta( $dup_id, 'bd_claimed_by' );
				delete_post_meta( $dup_id, 'bd_claimed_at' );
			}
		}

		return $transferred;
	}

	/**
	 * Check if a database table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if table exists.
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;

		static $cache = array();

		if ( isset( $cache[ $table_name ] ) ) {
			return $cache[ $table_name ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		) === $table_name;

		$cache[ $table_name ] = $exists;

		return $exists;
	}

	/**
	 * Permanently delete a business and its associated data.
	 *
	 * @param int $business_id Business post ID.
	 * @return bool True on success.
	 */
	private static function delete_business( $business_id ) {
		global $wpdb;

		// Delete from locations table if available.
		if ( class_exists( '\BD\DB\LocationsTable' ) ) {
			LocationsTable::delete( $business_id );
		}

		// Delete reviews if table exists.
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		if ( self::table_exists( $reviews_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $reviews_table, array( 'business_id' => $business_id ), array( '%d' ) );
		}

		// Delete the post permanently.
		$result = wp_delete_post( $business_id, true );

		return false !== $result;
	}

	/**
	 * Set up a redirect from duplicate to primary.
	 *
	 * @param int $duplicate_id Duplicate business ID.
	 * @param int $primary_id   Primary business ID.
	 * @return bool True on success.
	 */
	private static function setup_redirect( $duplicate_id, $primary_id ) {
		// Store redirect metadata.
		update_post_meta( $duplicate_id, 'bd_redirect_to', $primary_id );
		update_post_meta( $duplicate_id, 'bd_merged_at', current_time( 'mysql' ) );

		// Change status to draft.
		wp_update_post(
			array(
				'ID'          => $duplicate_id,
				'post_status' => 'draft',
			)
		);

		return true;
	}

	/**
	 * Log a merge action for audit purposes.
	 *
	 * @param int    $primary_id    Primary business ID.
	 * @param array  $duplicate_ids Merged duplicate IDs.
	 * @param string $action        Action taken.
	 * @param array  $log           Merge log details.
	 */
	private static function log_merge( $primary_id, $duplicate_ids, $action, $log ) {
		$merge_log = array(
			'primary_id' => $primary_id,
			'duplicates' => $duplicate_ids,
			'action'     => $action,
			'details'    => $log,
			'merged_at'  => current_time( 'mysql' ),
			'merged_by'  => get_current_user_id(),
		);

		// Add to post meta for history.
		$history = get_post_meta( $primary_id, 'bd_merge_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$history[] = $merge_log;
		update_post_meta( $primary_id, 'bd_merge_history', $history );

		// Also log to debug log if enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[Business Directory] Merged duplicates %s into %d using action: %s',
					implode( ', ', $duplicate_ids ),
					$primary_id,
					$action
				)
			);
		}
	}

	/**
	 * Preview what will happen during a merge without making changes.
	 *
	 * @param int   $primary_id    Primary business ID.
	 * @param array $duplicate_ids Duplicate business IDs.
	 * @return array Preview data.
	 */
	public static function preview_merge( $primary_id, $duplicate_ids ) {
		$primary_id = absint( $primary_id );
		$primary    = get_post( $primary_id );
		if ( ! $primary ) {
			return array( 'error' => __( 'Primary business not found.', 'business-directory' ) );
		}

		// Sanitize duplicate IDs.
		$duplicate_ids = array_map( 'absint', $duplicate_ids );
		$duplicate_ids = array_filter( $duplicate_ids );

		$preview = array(
			'primary'    => DuplicateFinder::get_group_details( array( $primary_id ) )[0] ?? null,
			'duplicates' => DuplicateFinder::get_group_details( $duplicate_ids ),
			'changes'    => array(),
		);

		// Preview reviews merge.
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		if ( self::table_exists( $reviews_table ) && ! empty( $duplicate_ids ) ) {
			$ids_placeholder = implode( ',', array_fill( 0, count( $duplicate_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$dup_reviews = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reviews_table} WHERE business_id IN ({$ids_placeholder})",
					...$duplicate_ids
				)
			);

			if ( $dup_reviews > 0 ) {
				$preview['changes'][] = sprintf(
					/* translators: %d: number of reviews */
					__( '%d reviews will be moved to primary business', 'business-directory' ),
					$dup_reviews
				);
			}
		}

		// Preview meta merge.
		$meta_keys = array( 'bd_phone', 'bd_website', 'bd_email' );
		foreach ( $meta_keys as $key ) {
			$primary_value = get_post_meta( $primary_id, $key, true );
			if ( empty( $primary_value ) ) {
				foreach ( $duplicate_ids as $dup_id ) {
					$dup_value = get_post_meta( $dup_id, $key, true );
					if ( ! empty( $dup_value ) ) {
						$preview['changes'][] = sprintf(
							/* translators: 1: field name, 2: field value */
							__( '%1$s will be set to "%2$s" from duplicate', 'business-directory' ),
							str_replace( 'bd_', '', $key ),
							$dup_value
						);
						break;
					}
				}
			}
		}

		// Preview claim transfer.
		$primary_claimed = get_post_meta( $primary_id, 'bd_claimed_by', true );
		if ( empty( $primary_claimed ) ) {
			foreach ( $duplicate_ids as $dup_id ) {
				$dup_claimed = get_post_meta( $dup_id, 'bd_claimed_by', true );
				if ( ! empty( $dup_claimed ) ) {
					$user = get_user_by( 'id', $dup_claimed );
					$preview['changes'][] = sprintf(
						/* translators: %s: username */
						__( 'Claim ownership will be transferred from %s', 'business-directory' ),
						$user ? $user->display_name : 'Unknown'
					);
					break;
				}
			}
		}

		return $preview;
	}

	/**
	 * Undo a merge by restoring trashed duplicates.
	 * Only works if duplicates were trashed (not deleted).
	 *
	 * Note: This only restores the posts from trash. It does NOT reverse
	 * the data transfer (reviews, meta, etc.) back to the original duplicates.
	 *
	 * @param int $primary_id Primary business ID.
	 * @return array|\WP_Error Result or error.
	 */
	public static function undo_merge( $primary_id ) {
		$primary_id = absint( $primary_id );
		$history    = get_post_meta( $primary_id, 'bd_merge_history', true );

		if ( empty( $history ) || ! is_array( $history ) ) {
			return new \WP_Error( 'no_history', __( 'No merge history found for this business.', 'business-directory' ) );
		}

		// Get the most recent merge.
		$last_merge = end( $history );

		if ( ! isset( $last_merge['action'] ) || 'trash' !== $last_merge['action'] ) {
			return new \WP_Error(
				'cannot_undo',
				__( 'Cannot undo this merge. Duplicates were permanently deleted or redirected.', 'business-directory' )
			);
		}

		if ( empty( $last_merge['duplicates'] ) || ! is_array( $last_merge['duplicates'] ) ) {
			return new \WP_Error( 'invalid_history', __( 'Merge history is corrupted.', 'business-directory' ) );
		}

		$restored = array();
		foreach ( $last_merge['duplicates'] as $dup_id ) {
			$dup_id = absint( $dup_id );
			$result = wp_untrash_post( $dup_id );
			if ( $result ) {
				$restored[] = $dup_id;
			}
		}

		// Remove the last merge from history.
		array_pop( $history );
		update_post_meta( $primary_id, 'bd_merge_history', $history );

		// Clear duplicate caches.
		DuplicateFinder::clear_cache();

		return array(
			'success'  => true,
			'restored' => $restored,
			'note'     => __( 'Posts restored from trash. Note: Reviews and meta data were not moved back.', 'business-directory' ),
		);
	}
}
