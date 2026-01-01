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
	exit; }

/**
 * Class GamificationHooks
 */
class GamificationHooks {

	/**
	 * Constructor - register all hooks
	 */
	public function __construct() {
		// Review actions.
		add_action( 'bd_review_approved', array( $this, 'on_review_approved' ), 10, 1 );
		add_action( 'bd_review_created', array( $this, 'on_review_created' ), 10, 2 );

		// Helpful votes.
		add_action( 'wp_ajax_bd_mark_helpful', array( $this, 'handle_helpful_vote' ) );
		add_action( 'wp_ajax_nopriv_bd_mark_helpful', array( $this, 'handle_helpful_vote' ) );

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
	 * Get the reviews table name (uses base_prefix for multisite compatibility)
	 *
	 * @return string
	 */
	private function get_reviews_table() {
		global $wpdb;
		// Use base_prefix for shared network tables.
		return $wpdb->base_prefix . 'bd_reviews';
	}

	/**
	 * Get the reputation table name
	 *
	 * @return string
	 */
	private function get_reputation_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_reputation';
	}

	/**
	 * Get the activity table name
	 *
	 * @return string
	 */
	private function get_activity_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_activity';
	}

	/**
	 * Get the helpful table name
	 *
	 * @return string
	 */
	private function get_helpful_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_review_helpful';
	}

	/**
	 * When a review is approved
	 *
	 * @param int $review_id Review ID.
	 */
	public function on_review_approved( $review_id ) {
		global $wpdb;
		$reviews_table = $this->get_reviews_table();

		// Get review data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$reviews_table} WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review || empty( $review['user_id'] ) ) {
			// No user associated with this review.
			return;
		}

		$user_id = absint( $review['user_id'] );

		// Check if we already awarded points for this review.
		$activity_table = $this->get_activity_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$already_tracked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$activity_table} 
				WHERE user_id = %d AND activity_type = 'review_created' AND object_id = %d",
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

		// Bonus for photo - awards 5 points per photo.
		if ( ! empty( $review['photo_ids'] ) ) {
			$photo_ids   = explode( ',', $review['photo_ids'] );
			$photo_count = count( array_filter( $photo_ids ) );
			for ( $i = 0; $i < $photo_count; $i++ ) {
				ActivityTracker::track( $user_id, 'review_with_photo', $review_id );
			}
		}

		// Bonus for detailed review (>100 chars) - awards 5 points.
		if ( ! empty( $review['content'] ) && strlen( $review['content'] ) > 100 ) {
			ActivityTracker::track( $user_id, 'review_detailed', $review_id );
		}

		// Check if this is their first review today - awards 20 points.
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
	 * When a review is first created (before approval)
	 *
	 * @param int   $review_id   Review ID.
	 * @param array $review_data Review data.
	 */
	public function on_review_created( $review_id, $review_data ) {
		// Could track pending reviews for analytics.
		// Points are awarded on approval, not creation.
	}

	/**
	 * Handle helpful vote AJAX
	 */
	public function handle_helpful_vote() {
		check_ajax_referer( 'bd_helpful_vote', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to vote' ) );
		}

		$review_id = absint( $_POST['review_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $review_id ) {
			wp_send_json_error( array( 'message' => 'Invalid review' ) );
		}

		global $wpdb;
		$helpful_table = $this->get_helpful_table();
		$reviews_table = $this->get_reviews_table();

		// Check if already voted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$already_voted = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$helpful_table} WHERE review_id = %d AND user_id = %d",
				$review_id,
				$user_id
			)
		);

		if ( $already_voted ) {
			wp_send_json_error( array( 'message' => 'You already marked this helpful' ) );
		}

		// Get review author.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, helpful_count FROM {$reviews_table} WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			wp_send_json_error( array( 'message' => 'Review not found' ) );
		}

		// Can't vote for your own review.
		if ( ! empty( $review['user_id'] ) && (int) $review['user_id'] === $user_id ) {
			wp_send_json_error( array( 'message' => 'You cannot vote for your own review' ) );
		}

		// Record vote.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$helpful_table,
			array(
				'review_id' => $review_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%d' )
		);

		// Update helpful count.
		$new_count = ( $review['helpful_count'] ?? 0 ) + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$reviews_table,
			array( 'helpful_count' => $new_count ),
			array( 'id' => $review_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Track activity for review author (if they're a registered user).
		if ( ! empty( $review['user_id'] ) ) {
			ActivityTracker::track(
				$review['user_id'],
				'helpful_vote_received',
				$review_id
			);
		}

		wp_send_json_success(
			array(
				'message' => 'Marked as helpful!',
				'count'   => $new_count,
			)
		);
	}

	/**
	 * When a list is created
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 */
	public function on_list_created( $list_id, $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		ActivityTracker::track( $user_id, 'list_created', $list_id );
	}

	/**
	 * When a business is claimed
	 *
	 * @param int $business_id Business ID.
	 * @param int $user_id     User ID.
	 */
	public function on_business_claimed( $business_id, $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		ActivityTracker::track( $user_id, 'business_claimed', $business_id );
	}

	/**
	 * Check profile completion
	 *
	 * @param int $user_id User ID.
	 */
	public function check_profile_completion( $user_id ) {
		$user = get_userdata( $user_id );
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
					WHERE user_id = %d AND activity_type = 'profile_completed'",
					$user_id
				)
			);

			if ( ! $already_awarded ) {
				ActivityTracker::track( $user_id, 'profile_completed', $user_id );
			}
		}
	}

	/**
	 * Track new user registration
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_register( $user_id ) {
		global $wpdb;
		$reputation_table = $this->get_reputation_table();

		// Create reputation record for new user.
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
					'user_id'      => $user_id,
					'total_points' => 0,
					'current_rank' => 'newcomer',
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Notify user when badges are earned
	 *
	 * @param int   $user_id    User ID.
	 * @param array $new_badges Array of badge keys.
	 */
	public function notify_badges_earned( $user_id, $new_badges ) {
		if ( empty( $new_badges ) ) {
			return;
		}

		// Store in user meta for frontend notification.
		$pending = get_user_meta( $user_id, 'bd_pending_badge_notifications', true );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		$pending = array_merge( $pending, $new_badges );
		update_user_meta( $user_id, 'bd_pending_badge_notifications', array_unique( $pending ) );
	}
}
