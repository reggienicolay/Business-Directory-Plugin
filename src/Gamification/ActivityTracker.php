<?php
/**
 * Activity Tracker
 *
 * Tracks user activities and awards points.
 *
 * @package BusinessDirectory
 */

namespace BD\Gamification;

/**
 * Class ActivityTracker
 */
class ActivityTracker {

	/**
	 * Points awarded for each activity type
	 */
	const ACTIVITY_POINTS = array(
		'review_created'        => 10,
		'review_with_photo'     => 5,
		'review_detailed'       => 5,
		'helpful_vote_received' => 2,
		'list_created'          => 5,
		'list_made_public'      => 10,
		'business_claimed'      => 25,
		'profile_completed'     => 15,
		'first_review_day'      => 20,
		'first_login'           => 5,
	);

	/**
	 * Get activity table name (multisite compatible)
	 *
	 * @return string
	 */
	private static function get_activity_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_activity';
	}

	/**
	 * Get reviews table name (multisite compatible)
	 *
	 * @return string
	 */
	private static function get_reviews_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_reviews';
	}

	/**
	 * Get reputation table name (multisite compatible)
	 *
	 * @return string
	 */
	private static function get_reputation_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_reputation';
	}

	/**
	 * Track a user activity
	 *
	 * @param int    $user_id       User ID.
	 * @param string $activity_type Activity type.
	 * @param int    $object_id     Related object ID.
	 * @param mixed  $metadata      Additional metadata.
	 * @return bool|int
	 */
	public static function track( $user_id, $activity_type, $object_id = null, $metadata = null ) {
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$activity_table = self::get_activity_table();

		// Get points for this activity.
		$points = self::ACTIVITY_POINTS[ $activity_type ] ?? 0;

		// Insert activity.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$activity_table,
			array(
				'user_id'       => $user_id,
				'activity_type' => $activity_type,
				'points'        => $points,
				'object_id'     => $object_id,
				'description'   => is_array( $metadata ) ? wp_json_encode( $metadata ) : $metadata,
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);

		if ( $result ) {
			// Update reputation summary.
			self::update_reputation( $user_id );

			// Check for new badges.
			BadgeSystem::check_and_award_badges( $user_id, $activity_type );
		}

		return $result;
	}

	/**
	 * Update user reputation summary
	 *
	 * @param int $user_id User ID.
	 */
	public static function update_reputation( $user_id ) {
		global $wpdb;
		$activity_table   = self::get_activity_table();
		$reviews_table    = self::get_reviews_table();
		$reputation_table = self::get_reputation_table();

		// Calculate totals from activity.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$activity_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COALESCE(SUM(points), 0) as total_points,
					SUM(CASE WHEN activity_type = 'helpful_vote_received' THEN 1 ELSE 0 END) as helpful_votes,
					SUM(CASE WHEN activity_type IN ('list_created', 'list_made_public') THEN 1 ELSE 0 END) as lists_created,
					SUM(CASE WHEN activity_type = 'review_with_photo' THEN 1 ELSE 0 END) as photos_uploaded
				FROM {$activity_table}
				WHERE user_id = %d",
				$user_id
			)
		);

		// Get actual review count from reviews table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$review_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews_table} WHERE user_id = %d AND status = 'approved'",
				$user_id
			)
		);

		// Calculate rank.
		$points = (int) ( $activity_stats->total_points ?? 0 );
		$rank   = self::calculate_rank( $points );

		// Prepare data.
		$reputation_data = array(
			'user_id'                => $user_id,
			'total_points'           => $points,
			'total_reviews'          => (int) $review_count,
			'helpful_votes_received' => (int) ( $activity_stats->helpful_votes ?? 0 ),
			'current_rank'           => $rank,
		);

		// Check if record exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reputation_table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $exists ) {
			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$reputation_table,
				array(
					'total_points'           => $reputation_data['total_points'],
					'total_reviews'          => $reputation_data['total_reviews'],
					'helpful_votes_received' => $reputation_data['helpful_votes_received'],
					'current_rank'           => $reputation_data['current_rank'],
				),
				array( 'user_id' => $user_id ),
				array( '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$reputation_table,
				$reputation_data,
				array( '%d', '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Calculate rank based on points
	 *
	 * @param int $points Total points.
	 * @return string Rank name.
	 */
	private static function calculate_rank( $points ) {
		$ranks = array(
			1000 => 'legend',
			600  => 'vip',
			300  => 'insider',
			150  => 'regular',
			50   => 'local',
			0    => 'newcomer',
		);

		foreach ( $ranks as $threshold => $rank ) {
			if ( $points >= $threshold ) {
				return $rank;
			}
		}

		return 'newcomer';
	}

	/**
	 * Get user stats
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_stats( $user_id ) {
		global $wpdb;
		$reputation_table = self::get_reputation_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$reputation_table} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return array(
				'total_points'           => 0,
				'total_reviews'          => 0,
				'helpful_votes_received' => 0,
				'badges'                 => '[]',
				'current_rank'           => 'newcomer',
			);
		}

		return $stats;
	}

	/**
	 * Get user activity history
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of activities to return.
	 * @return array
	 */
	public static function get_user_activity( $user_id, $limit = 10 ) {
		global $wpdb;
		$activity_table = self::get_activity_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$activity_table} 
				WHERE user_id = %d 
				ORDER BY created_at DESC 
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		return $activities ?: array();
	}

	/**
	 * Get user's rank position on leaderboard
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_user_rank_position( $user_id ) {
		global $wpdb;
		$reputation_table = self::get_reputation_table();

		// Get user's points.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$user_points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_points FROM {$reputation_table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $user_points ) {
			return 0;
		}

		// Count users with more points.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$position = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) + 1 FROM {$reputation_table} WHERE total_points > %d",
				$user_points
			)
		);

		return (int) $position;
	}

	/**
	 * Get leaderboard
	 *
	 * @param string $period Time period (all_time, month, week).
	 * @param int    $limit  Number of users to return.
	 * @return array
	 */
	public static function get_leaderboard( $period = 'all_time', $limit = 10 ) {
		global $wpdb;
		$reputation_table = self::get_reputation_table();

		// For now, just use all-time stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$leaders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name, u.user_email
				FROM {$reputation_table} r
				JOIN {$wpdb->users} u ON r.user_id = u.ID
				WHERE r.total_points > 0
				ORDER BY r.total_points DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $leaders ?: array();
	}
}
