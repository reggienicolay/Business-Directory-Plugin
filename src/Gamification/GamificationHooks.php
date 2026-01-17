<?php
/**
 * Gamification Hooks
 *
 * Connects gamification system to WordPress and plugin actions.
 *
 * @package BusinessDirectory
 */

namespace BD\Gamification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GamificationHooks
 */
class GamificationHooks {

	/**
	 * Constructor - register all hooks.
	 */
	public function __construct() {
		// Review actions (bd_review_approved passes 2 args: review_id, review_data).
		add_action( 'bd_review_approved', array( $this, 'on_review_approved' ), 10, 2 );
		add_action( 'bd_review_inserted', array( $this, 'on_review_inserted' ), 10, 2 );

		// Helpful votes (logged-in users only).
		add_action( 'wp_ajax_bd_mark_helpful', array( $this, 'handle_helpful_vote' ) );

		// List creation.
		add_action( 'bd_list_created', array( $this, 'on_list_created' ), 10, 2 );

		// Business claim.
		add_action( 'bd_business_claimed', array( $this, 'on_business_claimed' ), 10, 2 );

		// Profile completion.
		add_action( 'profile_update', array( $this, 'check_profile_completion' ), 10, 1 );

		// New user registration.
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );

		// Badge notifications.
		add_action( 'bd_badges_earned', array( $this, 'notify_badges_earned' ), 10, 2 );
	}

	/**
	 * Get the reviews table name.
	 *
	 * @return string
	 */
	private function get_reviews_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_reviews';
	}

	/**
	 * Get the reputation table name.
	 *
	 * @return string
	 */
	private function get_reputation_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_user_reputation';
	}

	/**
	 * Get the activity table name.
	 *
	 * @return string
	 */
	private function get_activity_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_user_activity';
	}

	/**
	 * Get the helpful votes table name.
	 *
	 * @return string
	 */
	private function get_helpful_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_review_helpful';
	}

	/**
	 * When a review is approved - award points.
	 *
	 * @param int        $review_id Review ID.
	 * @param array|null $review    Review data from ReviewsTable (optional for backwards compat).
	 */
	public function on_review_approved( $review_id, $review = null ) {
		global $wpdb;

		$review_id = absint( $review_id );

		// If review data not passed, fetch it (backwards compatibility).
		if ( ! $review ) {
			$reviews_table = $this->get_reviews_table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$review = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$reviews_table} WHERE id = %d",
					$review_id
				),
				ARRAY_A
			);
		}

		if ( ! $review || empty( $review['user_id'] ) ) {
			// No user associated with this review (anonymous review).
			return;
		}

		$user_id = absint( $review['user_id'] );

		// Check if we already awarded points for this review.
		$activity_table = $this->get_activity_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$already_tracked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$activity_table} 
				WHERE user_id = %d AND action_type = 'review_created' AND reference_id = %d",
				$user_id,
				$review_id
			)
		);

		if ( $already_tracked ) {
			// Already awarded points for this review.
			return;
		}

		// Track review creation - awards 10 points.
		ActivityTracker::track( $user_id, 'review_created', $review_id );

		// Bonus for photo - awards 5 points per photo (max 3).
		if ( ! empty( $review['photo_ids'] ) ) {
			$photo_ids   = array_filter( explode( ',', $review['photo_ids'] ) );
			$photo_count = min( count( $photo_ids ), 3 ); // Cap at 3.
			for ( $i = 0; $i < $photo_count; $i++ ) {
				ActivityTracker::track( $user_id, 'review_with_photo', $review_id );
			}
		}

		// Bonus for detailed review (>100 chars) - awards 5 points.
		// Use mb_strlen for proper UTF-8 character counting.
		if ( ! empty( $review['content'] ) && mb_strlen( $review['content'] ) > 100 ) {
			ActivityTracker::track( $user_id, 'review_detailed', $review_id );
		}

		// Check if this is their first review today - awards 20 points.
		$reviews_table = $this->get_reviews_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$reviews_today = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews_table}
				WHERE user_id = %d AND status = 'approved' AND DATE(created_at) = CURDATE()",
				$user_id
			)
		);

		if ( 1 === (int) $reviews_today ) {
			ActivityTracker::track( $user_id, 'first_review_day', $review_id );
		}
	}

	/**
	 * When a review is first inserted (before approval).
	 *
	 * @param int   $review_id   Review ID.
	 * @param array $review_data Review data.
	 */
	public function on_review_inserted( $review_id, $review_data ) {
		// Could track pending reviews for analytics.
		// Points are awarded on approval, not insertion.
	}

	/**
	 * Handle helpful vote AJAX request.
	 */
	public function handle_helpful_vote() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_helpful_vote', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'business-directory' ) ) );
		}

		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to vote.', 'business-directory' ) ) );
		}

		// Get and validate review ID.
		$review_id = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
		$user_id   = get_current_user_id();

		if ( ! $review_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid review.', 'business-directory' ) ) );
		}

		global $wpdb;
		$helpful_table = $this->get_helpful_table();
		$reviews_table = $this->get_reviews_table();

		// Get review data first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, helpful_count, status FROM {$reviews_table} WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			wp_send_json_error( array( 'message' => __( 'Review not found.', 'business-directory' ) ) );
		}

		// Only allow voting on approved reviews.
		if ( 'approved' !== $review['status'] ) {
			wp_send_json_error( array( 'message' => __( 'This review is not available.', 'business-directory' ) ) );
		}

		// Can't vote for your own review.
		if ( ! empty( $review['user_id'] ) && absint( $review['user_id'] ) === $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot vote for your own review.', 'business-directory' ) ) );
		}

		// Use INSERT IGNORE to handle race conditions atomically.
		// If duplicate key exists, insert is silently ignored and returns 0.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$helpful_table} (review_id, user_id, created_at) VALUES (%d, %d, NOW())",
				$review_id,
				$user_id
			)
		);

		// $wpdb->query returns number of affected rows. 0 means duplicate (already voted).
		if ( 0 === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'You already marked this helpful.', 'business-directory' ) ) );
		}

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Could not record your vote. Please try again.', 'business-directory' ) ) );
		}

		// Atomically increment helpful count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$reviews_table} SET helpful_count = helpful_count + 1 WHERE id = %d",
				$review_id
			)
		);

		if ( false === $updated ) {
			// Rollback the vote insert on failure.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$helpful_table,
				array(
					'review_id' => $review_id,
					'user_id'   => $user_id,
				),
				array( '%d', '%d' )
			);
			wp_send_json_error( array( 'message' => __( 'Could not update vote count. Please try again.', 'business-directory' ) ) );
		}

		// Get the actual new count from database (not stale pre-update value).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT helpful_count FROM {$reviews_table} WHERE id = %d",
				$review_id
			)
		);

		// Track activity for review author (if they're a registered user).
		if ( ! empty( $review['user_id'] ) ) {
			ActivityTracker::track(
				absint( $review['user_id'] ),
				'helpful_vote_received',
				$review_id
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Marked as helpful!', 'business-directory' ),
				'count'   => $new_count,
			)
		);
	}

	/**
	 * When a list is created.
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 */
	public function on_list_created( $list_id, $user_id ) {
		$user_id = absint( $user_id );
		$list_id = absint( $list_id );

		if ( ! $user_id || ! $list_id ) {
			return;
		}

		ActivityTracker::track( $user_id, 'list_created', $list_id );
	}

	/**
	 * When a business is claimed.
	 *
	 * @param int $business_id Business ID.
	 * @param int $user_id     User ID.
	 */
	public function on_business_claimed( $business_id, $user_id ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );

		if ( ! $user_id || ! $business_id ) {
			return;
		}

		ActivityTracker::track( $user_id, 'business_claimed', $business_id );
	}

	/**
	 * Check profile completion and award points.
	 *
	 * @param int $user_id User ID.
	 */
	public function check_profile_completion( $user_id ) {
		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		// Check if profile is complete.
		$has_description  = ! empty( $user->description );
		$has_display_name = ! empty( $user->display_name ) && $user->display_name !== $user->user_login;

		if ( $has_description && $has_display_name ) {
			global $wpdb;
			$activity_table = $this->get_activity_table();

			// Check if already awarded.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$already_awarded = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$activity_table} 
					WHERE user_id = %d AND action_type = 'profile_completed'",
					$user_id
				)
			);

			if ( ! $already_awarded ) {
				ActivityTracker::track( $user_id, 'profile_completed', $user_id );
			}
		}
	}

	/**
	 * Initialize reputation record for new user.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_register( $user_id ) {
		global $wpdb;

		$user_id          = absint( $user_id );
		$reputation_table = $this->get_reputation_table();

		if ( ! $user_id ) {
			return;
		}

		// Check if record already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reputation_table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$reputation_table,
				array(
					'user_id' => $user_id,
					'points'  => 0,
					'level'   => 'newcomer',
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Store badge notifications for frontend display.
	 *
	 * @param int   $user_id    User ID.
	 * @param array $new_badges Array of badge keys.
	 */
	public function notify_badges_earned( $user_id, $new_badges ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || empty( $new_badges ) || ! is_array( $new_badges ) ) {
			return;
		}

		// Sanitize badge keys.
		$new_badges = array_map( 'sanitize_key', $new_badges );

		// Get existing pending notifications.
		$pending = get_user_meta( $user_id, 'bd_pending_badge_notifications', true );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		// Merge and deduplicate.
		$pending = array_unique( array_merge( $pending, $new_badges ) );

		update_user_meta( $user_id, 'bd_pending_badge_notifications', $pending );
	}
}
