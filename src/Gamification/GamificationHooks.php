<?php

namespace BD\Gamification;

class GamificationHooks {


	public function __construct() {
		// Review actions
		add_action( 'bd_review_approved', array( $this, 'on_review_approved' ), 10, 1 );
		add_action( 'bd_review_created', array( $this, 'on_review_created' ), 10, 2 );

		// Helpful votes
		add_action( 'wp_ajax_bd_mark_helpful', array( $this, 'handle_helpful_vote' ) );
		add_action( 'wp_ajax_nopriv_bd_mark_helpful', array( $this, 'handle_helpful_vote' ) );

		// List creation (when you build this feature)
		add_action( 'bd_list_created', array( $this, 'on_list_created' ), 10, 2 );

		// Business claim
		add_action( 'bd_business_claimed', array( $this, 'on_business_claimed' ), 10, 2 );

		// Profile completion
		add_action( 'profile_update', array( $this, 'check_profile_completion' ), 10, 1 );

		// New user registration
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );

		// Badge notifications
		add_action( 'bd_badges_earned', array( $this, 'notify_badges_earned' ), 10, 2 );
	}

	/**
	 * When a review is approved
	 */
	public function on_review_approved( $review_id ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Get review author
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, helpful_count FROM $reviews_table WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		// DEBUG - remove after testing
		error_log( 'BD Helpful Debug - Review ID: ' . $review_id );
		error_log( 'BD Helpful Debug - Table: ' . $reviews_table );
		error_log( 'BD Helpful Debug - Query: ' . $wpdb->last_query );
		error_log( 'BD Helpful Debug - Review found: ' . print_r( $review, true ) );

		if ( ! $review || ! $review['user_id'] ) {
			wp_send_json_error( array( 'message' => 'Review not found - Table: ' . $reviews_table . ' ID: ' . $review_id ) );
		}

		// Track review creation
		ActivityTracker::track(
			$review['user_id'],
			'review_created',
			$review_id
		);

		// Bonus for photo
		if ( ! empty( $review['photo_ids'] ) ) {
			$photo_count = count( explode( ',', $review['photo_ids'] ) );
			for ( $i = 0; $i < $photo_count; $i++ ) {
				ActivityTracker::track(
					$review['user_id'],
					'review_with_photo',
					$review_id
				);
			}
		}

		// Bonus for detailed review (>100 chars)
		if ( ! empty( $review['content'] ) && strlen( $review['content'] ) > 100 ) {
			ActivityTracker::track(
				$review['user_id'],
				'review_detailed',
				$review_id
			);
		}

		// Check if this is their first review today
		$first_today = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*)
            FROM $reviews_table
            WHERE user_id = %d
            AND status = 'approved'
            AND DATE(created_at) = CURDATE()
        ",
				$review['user_id']
			)
		);

		if ( $first_today == 1 ) {
			ActivityTracker::track(
				$review['user_id'],
				'first_review_day',
				$review_id
			);
		}
	}

	/**
	 * When a review is first created (before approval)
	 */
	public function on_review_created( $review_id, $review_data ) {
		// Could track pending reviews for different purposes
		// For now, we only track after approval
	}

	/**
	 * Handle helpful vote AJAX
	 */
	public function handle_helpful_vote() {
		check_ajax_referer( 'bd_helpful_vote', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to vote' ) );
		}

		$review_id = absint( $_POST['review_id'] );
		$user_id   = get_current_user_id();

		global $wpdb;
		$helpful_table = $wpdb->base_prefix . 'bd_review_helpful';
		$reviews_table = $wpdb->base_prefix . 'bd_reviews';

		// Check if already voted
		$already_voted = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $helpful_table WHERE review_id = %d AND user_id = %d",
				$review_id,
				$user_id
			)
		);

		if ( $already_voted ) {
			wp_send_json_error( array( 'message' => 'You already marked this helpful' ) );
		}

		// Get review author
		$review = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, helpful_count FROM $reviews_table WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			wp_send_json_error( array( 'message' => 'Review not found' ) );
		}

		// Can't vote for your own review (only applies if review has a user_id)
		if ( $review['user_id'] && $review['user_id'] == $user_id ) {
			wp_send_json_error( array( 'message' => 'You cannot vote for your own review' ) );
		}

		// Record vote
		$wpdb->insert(
			$helpful_table,
			array(
				'review_id' => $review_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%d' )
		);

		// Update helpful count
		$new_count = ( $review['helpful_count'] ?? 0 ) + 1;
		$wpdb->update(
			$reviews_table,
			array( 'helpful_count' => $new_count ),
			array( 'id' => $review_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Track activity for review author
		ActivityTracker::track(
			$review['user_id'],
			'helpful_vote_received',
			$review_id
		);

		wp_send_json_success(
			array(
				'message' => 'Marked as helpful!',
				'count'   => $new_count,
			)
		);
	}

	/**
	 * When a list is created
	 */
	public function on_list_created( $list_id, $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		ActivityTracker::track(
			$user_id,
			'list_created',
			$list_id
		);
	}

	/**
	 * When a business is claimed
	 */
	public function on_business_claimed( $business_id, $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		ActivityTracker::track(
			$user_id,
			'business_claimed',
			$business_id
		);
	}

	/**
	 * Check profile completion
	 */
	public function check_profile_completion( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Check if profile is complete
		$has_description  = ! empty( $user->description );
		$has_display_name = ! empty( $user->display_name ) && $user->display_name !== $user->user_login;

		if ( $has_description && $has_display_name ) {
			// Check if already awarded
			global $wpdb;
			$activity_table = $wpdb->prefix . 'bd_user_activity';

			$already_awarded = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $activity_table WHERE user_id = %d AND activity_type = 'profile_completed'",
					$user_id
				)
			);

			if ( ! $already_awarded ) {
				ActivityTracker::track(
					$user_id,
					'profile_completed',
					$user_id
				);
			}
		}
	}

	/**
	 * Track new user registration
	 */
	public function on_user_register( $user_id ) {
		global $wpdb;
		$reputation_table = $wpdb->prefix . 'bd_user_reputation';

		// Check total user count for founding member badge
		$user_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

		if ( $user_count <= 100 ) {
			// Create reputation record with founding member badge
			$wpdb->insert(
				$reputation_table,
				array(
					'user_id'     => $user_id,
					'badges'      => wp_json_encode( array( 'founding_member' ) ),
					'badge_count' => 1,
					'rank'        => 'newcomer',
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Send notification when badges are earned
	 */
	public function notify_badges_earned( $user_id, $new_badges ) {
		if ( empty( $new_badges ) ) {
			return;
		}

		// Get user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Store in user meta for frontend display
		$pending_notifications = get_user_meta( $user_id, 'bd_pending_badge_notifications', true );
		if ( ! is_array( $pending_notifications ) ) {
			$pending_notifications = array();
		}

		foreach ( $new_badges as $badge_key ) {
			$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
			if ( ! $badge ) {
				continue;
			}

			$pending_notifications[] = array(
				'badge_key' => $badge_key,
				'badge'     => $badge,
				'earned_at' => time(),
			);
		}

		update_user_meta( $user_id, 'bd_pending_badge_notifications', $pending_notifications );

		// Optional: Send email notification
		// $this->send_badge_email($user, $new_badges);
	}

	/**
	 * Optional: Send email about new badges
	 */
	private function send_badge_email( $user, $new_badges ) {
		$badge_names = array_map(
			function ( $key ) {
				$badge = BadgeSystem::BADGES[ $key ] ?? null;
				return $badge ? $badge['icon'] . ' ' . $badge['name'] : '';
			},
			$new_badges
		);

		$subject = count( $new_badges ) == 1 ?
			'You earned a new badge!' :
			'You earned ' . count( $new_badges ) . ' new badges!';

		$message  = "Congratulations {$user->display_name}!\n\n";
		$message .= "You've earned:\n" . implode( "\n", $badge_names ) . "\n\n";
		$message .= "Keep up the great work supporting local Livermore businesses!\n\n";
		$message .= 'View your profile: ' . home_url( '/profile/' );

		wp_mail( $user->user_email, $subject, $message );
	}
}
