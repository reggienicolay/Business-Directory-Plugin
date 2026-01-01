<?php
/**
 * Badge System
 *
 * Defines badges, ranks, and award logic for the gamification system.
 * Premium Wine Country Edition with collectible, shareable designs.
 *
 * @package BusinessDirectory
 * @subpackage Gamification
 * @version 2.3.0
 */


namespace BD\Gamification;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class BadgeSystem {

	/**
	 * Badge Definitions
	 *
	 * Each badge includes:
	 * - name: Display name
	 * - icon: Font Awesome 6 icon HTML
	 * - color: Primary badge color (hex)
	 * - description: Shown when earned
	 * - requirement: Shown when locked (how to earn)
	 * - rarity: common|rare|epic|legendary|special
	 * - points: Bonus points awarded when badge is earned
	 * - check: Field to check in user stats (for automatic badges)
	 * - threshold: Value needed to earn (for automatic badges)
	 * - manual: true if admin-awarded only
	 */
	const BADGES = array(

		// =====================================================================
		// COMMUNITY STATUS BADGES (Special/Manual)
		// =====================================================================
		'love_livermore_verified' => array(
			'name'        => 'Love Livermore Verified',
			'icon'        => '<i class="fa-solid fa-certificate"></i>',
			'color'       => '#1a3a4a',
			'description' => 'Verified member of the Love Livermore community',
			'requirement' => 'Join the Love Livermore Facebook group',
			'manual'      => true,
			'rarity'      => 'special',
			'points'      => 25,
		),
		'founding_member'         => array(
			'name'        => 'Founding Member',
			'icon'        => '<i class="fa-solid fa-gem"></i>',
			'color'       => '#C9A227',
			'description' => 'Pioneer of the TriValley community',
			'requirement' => 'Be one of the first 100 registered members',
			'auto'        => true,
			'rarity'      => 'legendary',
			'points'      => 100,
		),
		'nicoles_pick'            => array(
			'name'        => "Nicole's Pick",
			'icon'        => '<i class="fa-solid fa-heart-circle-check"></i>',
			'color'       => '#9333ea',
			'description' => 'Personally recognized by Nicole for exceptional contributions',
			'requirement' => 'Receive personal recognition from Nicole',
			'manual'      => true,
			'rarity'      => 'legendary',
			'points'      => 150,
		),
		'community_champion'      => array(
			'name'        => 'Community Champion',
			'icon'        => '<i class="fa-solid fa-hand-holding-heart"></i>',
			'color'       => '#ef4444',
			'description' => 'True champion of local businesses and community',
			'requirement' => 'Demonstrate outstanding community support',
			'manual'      => true,
			'rarity'      => 'legendary',
			'points'      => 150,
		),

		// =====================================================================
		// REVIEW MILESTONE BADGES
		// =====================================================================
		'first_review'            => array(
			'name'        => 'First Steps',
			'icon'        => '<i class="fa-solid fa-feather"></i>',
			'color'       => '#3b82f6',
			'description' => 'You\'ve written your first review!',
			'requirement' => 'Write your first review',
			'check'       => 'review_count',
			'threshold'   => 1,
			'points'      => 10,
			'rarity'      => 'common',
		),
		'reviewer'                => array(
			'name'        => 'Rising Voice',
			'icon'        => '<i class="fa-solid fa-pen-fancy"></i>',
			'color'       => '#3b82f6',
			'description' => 'Your voice is being heard in the community',
			'requirement' => 'Write 5 reviews',
			'check'       => 'review_count',
			'threshold'   => 5,
			'points'      => 25,
			'rarity'      => 'common',
		),
		'super_reviewer'          => array(
			'name'        => 'Trusted Reviewer',
			'icon'        => '<i class="fa-solid fa-star-half-stroke"></i>',
			'color'       => '#8b5cf6',
			'description' => 'A trusted voice in the community',
			'requirement' => 'Write 25 reviews',
			'check'       => 'review_count',
			'threshold'   => 25,
			'points'      => 75,
			'rarity'      => 'rare',
		),
		'elite_reviewer'          => array(
			'name'        => 'Elite Reviewer',
			'icon'        => '<i class="fa-solid fa-crown"></i>',
			'color'       => '#C9A227',
			'description' => 'Elite status achieved through dedication',
			'requirement' => 'Write 50 reviews',
			'check'       => 'review_count',
			'threshold'   => 50,
			'points'      => 150,
			'rarity'      => 'epic',
		),
		'legend'                  => array(
			'name'        => 'Review Legend',
			'icon'        => '<i class="fa-solid fa-trophy"></i>',
			'color'       => '#f59e0b',
			'description' => 'A legendary contributor to the community',
			'requirement' => 'Write 100 reviews',
			'check'       => 'review_count',
			'threshold'   => 100,
			'points'      => 300,
			'rarity'      => 'legendary',
		),

		// =====================================================================
		// QUALITY & ENGAGEMENT BADGES
		// =====================================================================
		'helpful_reviewer'        => array(
			'name'        => 'Helpful Hand',
			'icon'        => '<i class="fa-solid fa-thumbs-up"></i>',
			'color'       => '#10b981',
			'description' => 'Your reviews genuinely help others',
			'requirement' => 'Receive 25 helpful votes on your reviews',
			'check'       => 'helpful_votes',
			'threshold'   => 25,
			'points'      => 75,
			'rarity'      => 'rare',
		),
		'super_helpful'           => array(
			'name'        => 'Community Guide',
			'icon'        => '<i class="fa-solid fa-compass"></i>',
			'color'       => '#10b981',
			'description' => 'An essential guide for the community',
			'requirement' => 'Receive 100 helpful votes on your reviews',
			'check'       => 'helpful_votes',
			'threshold'   => 100,
			'points'      => 150,
			'rarity'      => 'epic',
		),
		'photo_lover'             => array(
			'name'        => 'Shutterbug',
			'icon'        => '<i class="fa-solid fa-camera-retro"></i>',
			'color'       => '#ec4899',
			'description' => 'Capturing the essence of local businesses',
			'requirement' => 'Upload 25 photos with your reviews',
			'check'       => 'photos_uploaded',
			'threshold'   => 25,
			'points'      => 75,
			'rarity'      => 'rare',
		),
		'photographer'            => array(
			'name'        => 'Master Photographer',
			'icon'        => '<i class="fa-solid fa-aperture"></i>',
			'color'       => '#ec4899',
			'description' => 'Your photos bring businesses to life',
			'requirement' => 'Upload 100 photos with your reviews',
			'check'       => 'photos_uploaded',
			'threshold'   => 100,
			'points'      => 150,
			'rarity'      => 'epic',
		),
		'wordsmith'               => array(
			'name'        => 'Wordsmith',
			'icon'        => '<i class="fa-solid fa-book-open"></i>',
			'color'       => '#6366f1',
			'description' => 'Your detailed reviews tell the full story',
			'requirement' => 'Write 10 reviews with 200+ characters',
			'check'       => 'detailed_reviews',
			'threshold'   => 10,
			'points'      => 50,
			'rarity'      => 'rare',
		),

		// =====================================================================
		// EXPLORER BADGES (Category Diversity)
		// =====================================================================
		'explorer'                => array(
			'name'        => 'Explorer',
			'icon'        => '<i class="fa-solid fa-map-location-dot"></i>',
			'color'       => '#06b6d4',
			'description' => 'Discovering the diversity of TriValley',
			'requirement' => 'Review businesses in 5 different categories',
			'check'       => 'categories_reviewed',
			'threshold'   => 5,
			'points'      => 50,
			'rarity'      => 'common',
		),
		'adventurer'              => array(
			'name'        => 'Adventurer',
			'icon'        => '<i class="fa-solid fa-mountain-sun"></i>',
			'color'       => '#06b6d4',
			'description' => 'No corner of TriValley left unexplored',
			'requirement' => 'Review businesses in 10 different categories',
			'check'       => 'categories_reviewed',
			'threshold'   => 10,
			'points'      => 100,
			'rarity'      => 'rare',
		),
		'trailblazer'             => array(
			'name'        => 'Trailblazer',
			'icon'        => '<i class="fa-solid fa-medal"></i>',
			'color'       => '#fbbf24',
			'description' => 'First to discover hidden gems',
			'requirement' => 'Be the first to review 3 businesses',
			'check'       => 'first_reviews',
			'threshold'   => 3,
			'points'      => 100,
			'rarity'      => 'epic',
		),

		// =====================================================================
		// SPECIALTY CATEGORY BADGES
		// =====================================================================
		'foodie'                  => array(
			'name'        => 'Foodie',
			'icon'        => '<i class="fa-solid fa-utensils"></i>',
			'color'       => '#f97316',
			'description' => 'Connoisseur of the local food scene',
			'requirement' => 'Review 10 restaurants or food businesses',
			'check'       => 'food_reviews',
			'threshold'   => 10,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'wine_enthusiast'         => array(
			'name'        => 'Wine Enthusiast',
			'icon'        => '<i class="fa-solid fa-wine-glass"></i>',
			'color'       => '#1a3a4a',
			'description' => 'True appreciator of TriValley wines',
			'requirement' => 'Review 5 wineries',
			'check'       => 'winery_reviews',
			'threshold'   => 5,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'shop_local'              => array(
			'name'        => 'Shop Local Champion',
			'icon'        => '<i class="fa-solid fa-bag-shopping"></i>',
			'color'       => '#059669',
			'description' => 'Supporting local retail businesses',
			'requirement' => 'Review 10 retail shops',
			'check'       => 'retail_reviews',
			'threshold'   => 10,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'wellness_advocate'       => array(
			'name'        => 'Wellness Advocate',
			'icon'        => '<i class="fa-solid fa-spa"></i>',
			'color'       => '#14b8a6',
			'description' => 'Champion of health and wellness',
			'requirement' => 'Review 5 health or wellness businesses',
			'check'       => 'wellness_reviews',
			'threshold'   => 5,
			'points'      => 50,
			'rarity'      => 'rare',
		),

		// =====================================================================
		// TIMING & CONSISTENCY BADGES
		// =====================================================================
		'early_bird'              => array(
			'name'        => 'Early Bird',
			'icon'        => '<i class="fa-solid fa-sun"></i>',
			'color'       => '#f59e0b',
			'description' => 'Catching the morning vibes in TriValley',
			'requirement' => 'Write 5 reviews before 9 AM',
			'check'       => 'morning_reviews',
			'threshold'   => 5,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'night_owl'               => array(
			'name'        => 'Night Owl',
			'icon'        => '<i class="fa-solid fa-moon"></i>',
			'color'       => '#4338ca',
			'description' => 'Exploring the nightlife and late-night spots',
			'requirement' => 'Write 5 reviews after 9 PM',
			'check'       => 'evening_reviews',
			'threshold'   => 5,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'weekend_warrior'         => array(
			'name'        => 'Weekend Warrior',
			'icon'        => '<i class="fa-solid fa-calendar-check"></i>',
			'color'       => '#f59e0b',
			'description' => 'Making the most of weekends in TriValley',
			'requirement' => 'Write reviews on 10 different weekends',
			'check'       => 'weekend_reviews',
			'threshold'   => 10,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'consistent_contributor'  => array(
			'name'        => 'Consistent Contributor',
			'icon'        => '<i class="fa-solid fa-fire-flame-curved"></i>',
			'color'       => '#ef4444',
			'description' => 'Your dedication keeps the community thriving',
			'requirement' => 'Write reviews in 4 consecutive weeks',
			'check'       => 'streak_weeks',
			'threshold'   => 4,
			'points'      => 75,
			'rarity'      => 'epic',
		),

		// =====================================================================
		// LIST CURATION BADGES
		// =====================================================================
		'curator'                 => array(
			'name'        => 'List Curator',
			'icon'        => '<i class="fa-solid fa-list-check"></i>',
			'color'       => '#3b82f6',
			'description' => 'Curating the best of TriValley',
			'requirement' => 'Create your first public list',
			'check'       => 'list_count',
			'threshold'   => 1,
			'points'      => 15,
			'rarity'      => 'common',
		),
		'list_master'             => array(
			'name'        => 'List Master',
			'icon'        => '<i class="fa-solid fa-layer-group"></i>',
			'color'       => '#8b5cf6',
			'description' => 'Master curator of TriValley collections',
			'requirement' => 'Create 5 lists with 5+ businesses each',
			'check'       => 'qualifying_lists',
			'threshold'   => 5,
			'points'      => 75,
			'rarity'      => 'epic',
		),
		'tastemaker'              => array(
			'name'        => 'Tastemaker',
			'icon'        => '<i class="fa-solid fa-wand-magic-sparkles"></i>',
			'color'       => '#ec4899',
			'description' => 'Your lists inspire the community',
			'requirement' => 'Have your lists saved 50 times by others',
			'check'       => 'list_saves',
			'threshold'   => 50,
			'points'      => 100,
			'rarity'      => 'epic',
		),
		'team_player'             => array(
			'name'        => 'Team Player',
			'icon'        => '<i class="fa-solid fa-people-group"></i>',
			'color'       => '#06b6d4',
			'description' => 'Collaboration makes lists better',
			'requirement' => 'Collaborate on 3 different lists',
			'check'       => 'collaborative_lists',
			'threshold'   => 3,
			'points'      => 25,
			'rarity'      => 'common',
		),
		'list_leader'             => array(
			'name'        => 'List Leader',
			'icon'        => '<i class="fa-solid fa-users-gear"></i>',
			'color'       => '#8b5cf6',
			'description' => 'Building a community of curators',
			'requirement' => 'Have 5 or more collaborators across your lists',
			'check'       => 'total_collaborators',
			'threshold'   => 5,
			'points'      => 75,
			'rarity'      => 'rare',
		),

		// =====================================================================
		// SOCIAL ENGAGEMENT BADGES
		// =====================================================================
		'social_butterfly'        => array(
			'name'        => 'Social Butterfly',
			'icon'        => '<i class="fa-solid fa-share-nodes"></i>',
			'color'       => '#06b6d4',
			'description' => 'Spreading the word about TriValley businesses',
			'requirement' => 'Share 10 businesses on social media',
			'check'       => 'social_shares',
			'threshold'   => 10,
			'points'      => 50,
			'rarity'      => 'rare',
		),
		'influencer'              => array(
			'name'        => 'Local Influencer',
			'icon'        => '<i class="fa-solid fa-bullhorn"></i>',
			'color'       => '#8b5cf6',
			'description' => 'Your voice shapes the community',
			'requirement' => 'Have 25 followers on your profile',
			'check'       => 'follower_count',
			'threshold'   => 25,
			'points'      => 100,
			'rarity'      => 'epic',
		),

		// =====================================================================
		// SEASONAL/EVENT BADGES (Manual)
		// =====================================================================
		'holiday_spirit'          => array(
			'name'        => 'Holiday Spirit',
			'icon'        => '<i class="fa-solid fa-gifts"></i>',
			'color'       => '#dc2626',
			'description' => 'Celebrating the holidays with TriValley',
			'requirement' => 'Write reviews during the holiday season',
			'manual'      => true,
			'rarity'      => 'rare',
			'points'      => 50,
		),
		'harvest_festival'        => array(
			'name'        => 'Harvest Festival',
			'icon'        => '<i class="fa-solid fa-wheat-awn"></i>',
			'color'       => '#d97706',
			'description' => 'Celebrating the TriValley harvest season',
			'requirement' => 'Participate in harvest season activities',
			'manual'      => true,
			'rarity'      => 'rare',
			'points'      => 50,
		),
	);

	/**
	 * Rank Definitions
	 *
	 * Points thresholds and rank details.
	 * Users progress through ranks as they earn points.
	 */
	const RANKS = array(
		0    => array(
			'name'  => 'Newcomer',
			'icon'  => '<i class="fa-solid fa-seedling"></i>',
			'color' => '#94a3b8',
			'desc'  => 'Just getting started',
		),
		50   => array(
			'name'  => 'Local',
			'icon'  => '<i class="fa-solid fa-house"></i>',
			'color' => '#3b82f6',
			'desc'  => 'Finding your way around',
		),
		150  => array(
			'name'  => 'Regular',
			'icon'  => '<i class="fa-solid fa-star"></i>',
			'color' => '#8b5cf6',
			'desc'  => 'A familiar face in the community',
		),
		300  => array(
			'name'  => 'Insider',
			'icon'  => '<i class="fa-solid fa-user-tie"></i>',
			'color' => '#9333ea',
			'desc'  => 'You know all the best spots',
		),
		600  => array(
			'name'  => 'VIP',
			'icon'  => '<i class="fa-solid fa-crown"></i>',
			'color' => '#C9A227',
			'desc'  => 'Elite community member',
		),
		1000 => array(
			'name'  => 'Legend',
			'icon'  => '<i class="fa-solid fa-trophy"></i>',
			'color' => '#f59e0b',
			'desc'  => 'A true TriValley legend',
		),
	);

	/**
	 * Badge Categories for Display
	 */
	const BADGE_CATEGORIES = array(
		'community' => array(
			'name'   => 'Community Status',
			'icon'   => '<i class="fa-solid fa-certificate"></i>',
			'desc'   => 'Special recognition badges awarded by the community',
			'badges' => array( 'love_livermore_verified', 'founding_member', 'nicoles_pick', 'community_champion' ),
		),
		'reviews'   => array(
			'name'   => 'Review Milestones',
			'icon'   => '<i class="fa-solid fa-pen-fancy"></i>',
			'desc'   => 'Earned by writing reviews and sharing your experiences',
			'badges' => array( 'first_review', 'reviewer', 'super_reviewer', 'elite_reviewer', 'legend' ),
		),
		'quality'   => array(
			'name'   => 'Quality & Engagement',
			'icon'   => '<i class="fa-solid fa-thumbs-up"></i>',
			'desc'   => 'Recognition for helpful, detailed, and photo-rich reviews',
			'badges' => array( 'helpful_reviewer', 'super_helpful', 'photo_lover', 'photographer', 'wordsmith' ),
		),
		'explorer'  => array(
			'name'   => 'Explorer',
			'icon'   => '<i class="fa-solid fa-compass"></i>',
			'desc'   => 'Discover the full diversity of TriValley',
			'badges' => array( 'explorer', 'adventurer', 'trailblazer' ),
		),
		'specialty' => array(
			'name'   => 'Specialty',
			'icon'   => '<i class="fa-solid fa-wine-glass"></i>',
			'desc'   => 'Expertise in specific business categories',
			'badges' => array( 'foodie', 'wine_enthusiast', 'shop_local', 'wellness_advocate' ),
		),
		'timing'    => array(
			'name'   => 'Timing & Consistency',
			'icon'   => '<i class="fa-solid fa-clock"></i>',
			'desc'   => 'When you explore matters too',
			'badges' => array( 'early_bird', 'night_owl', 'weekend_warrior', 'consistent_contributor' ),
		),
		'curator'   => array(
			'name'   => 'Curator',
			'icon'   => '<i class="fa-solid fa-layer-group"></i>',
			'desc'   => 'Create and share collections of your favorites',
			'badges' => array( 'curator', 'list_master', 'tastemaker', 'team_player', 'list_leader' ),
		),
		'social'    => array(
			'name'   => 'Social',
			'icon'   => '<i class="fa-solid fa-share-nodes"></i>',
			'desc'   => 'Spread the word and build your following',
			'badges' => array( 'social_butterfly', 'influencer' ),
		),
		'seasonal'  => array(
			'name'   => 'Seasonal & Events',
			'icon'   => '<i class="fa-solid fa-calendar-star"></i>',
			'desc'   => 'Limited-time badges for special occasions',
			'badges' => array( 'holiday_spirit', 'harvest_festival' ),
		),
	);

	/**
	 * Check and award badges after user activity
	 *
	 * @param int         $user_id       User ID.
	 * @param string|null $activity_type Activity type for optimization.
	 */
	public static function check_and_award_badges( $user_id, $activity_type = null ) {
		if ( ! $user_id ) {
			return;
		}

		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// Get current user stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $reputation_table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return;
		}

		// Get current badges.
		$current_badges = ! empty( $stats['badges'] ) ? json_decode( $stats['badges'], true ) : array();
		$new_badges     = array();

		// Check each badge.
		foreach ( self::BADGES as $badge_key => $badge ) {
			// Skip if already earned.
			if ( in_array( $badge_key, $current_badges, true ) ) {
				continue;
			}

			// Skip manual badges.
			if ( ! empty( $badge['manual'] ) ) {
				continue;
			}

			// Check if requirements met.
			if ( self::check_badge_requirement( $user_id, $badge, $stats ) ) {
				$new_badges[]     = $badge_key;
				$current_badges[] = $badge_key;

				// Award bonus points.
				if ( ! empty( $badge['points'] ) ) {
					self::award_bonus_points( $user_id, $badge['points'], "Badge earned: {$badge['name']}" );
				}
			}
		}

		// Update badges if any new ones earned.
		if ( ! empty( $new_badges ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$reputation_table,
				array(
					'badges'      => wp_json_encode( $current_badges ),
					'badge_count' => count( $current_badges ),
				),
				array( 'user_id' => $user_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			// Record badge awards.
			$awards_table = $wpdb->base_prefix . 'bd_badge_awards';
			foreach ( $new_badges as $badge_key ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$awards_table,
					array(
						'user_id'   => $user_id,
						'badge_key' => $badge_key,
					),
					array( '%d', '%s' )
				);
			}

			// Update rank based on new point total.
			self::update_user_rank( $user_id );

			// Trigger action for notifications.
			do_action( 'bd_badges_earned', $user_id, $new_badges );
		}

		return $new_badges;
	}

	/**
	 * Check if a specific badge requirement is met
	 *
	 * @param int   $user_id User ID.
	 * @param array $badge   Badge definition.
	 * @param array $stats   User stats array.
	 * @return bool
	 */
	private static function check_badge_requirement( $user_id, $badge, $stats ) {
		if ( empty( $badge['check'] ) || empty( $badge['threshold'] ) ) {
			return false;
		}

		$check     = $badge['check'];
		$threshold = $badge['threshold'];

		// Direct stat checks.
		$direct_stats = array(
			'review_count',
			'helpful_votes',
			'photos_uploaded',
			'categories_reviewed',
			'list_count',
		);

		if ( in_array( $check, $direct_stats, true ) ) {
			$value = isset( $stats[ $check ] ) ? (int) $stats[ $check ] : 0;
			return $value >= $threshold;
		}

		// Custom checks requiring queries.
		global $wpdb;

		switch ( $check ) {
			case 'qualifying_lists':
				// Lists with 5+ items.
				$lists_table = $wpdb->prefix . 'bd_lists';
				$items_table = $wpdb->prefix . 'bd_list_items';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $lists_table l
						WHERE l.user_id = %d AND l.visibility = 'public'
						AND (SELECT COUNT(*) FROM $items_table WHERE list_id = l.id) >= 5",
						$user_id
					)
				);
				return $count >= $threshold;

			case 'list_saves':
				// How many times user's lists have been followed.
				$lists_table   = $wpdb->prefix . 'bd_lists';
				$follows_table = $wpdb->prefix . 'bd_list_follows';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $follows_table f
						INNER JOIN $lists_table l ON f.list_id = l.id
						WHERE l.user_id = %d",
						$user_id
					)
				);
				return $count >= $threshold;

			case 'collaborative_lists':
				// Count lists user collaborates on (not owns).
				$collab_table = $wpdb->prefix . 'bd_list_collaborators';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT list_id) FROM $collab_table
						WHERE user_id = %d AND status = 'active'",
						$user_id
					)
				);
				return $count >= $threshold;

			case 'total_collaborators':
				// Count total collaborators across all lists user owns.
				$lists_table  = $wpdb->prefix . 'bd_lists';
				$collab_table = $wpdb->prefix . 'bd_list_collaborators';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $collab_table c
						INNER JOIN $lists_table l ON c.list_id = l.id
						WHERE l.user_id = %d AND c.status = 'active'",
						$user_id
					)
				);
				return $count >= $threshold;

			case 'first_reviews':
				// Be first to review businesses.
				$reviews_table = $wpdb->prefix . 'bd_reviews';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT r.business_id) FROM $reviews_table r
						WHERE r.user_id = %d AND r.status = 'approved'
						AND NOT EXISTS (
							SELECT 1 FROM $reviews_table r2
							WHERE r2.business_id = r.business_id
							AND r2.status = 'approved'
							AND r2.created_at < r.created_at
						)",
						$user_id
					)
				);
				return $count >= $threshold;

			default:
				return false;
		}
	}

	/**
	 * Award bonus points for badge
	 *
	 * @param int    $user_id User ID.
	 * @param int    $points  Points to award.
	 * @param string $reason  Reason for points.
	 */
	private static function award_bonus_points( $user_id, $points, $reason = '' ) {
		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// Update total points.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $reputation_table SET total_points = total_points + %d WHERE user_id = %d",
				$points,
				$user_id
			)
		);

		// Log activity.
		$activity_table = $wpdb->base_prefix . 'bd_user_activity';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$activity_table,
			array(
				'user_id'       => $user_id,
				'activity_type' => 'badge_bonus',
				'points'        => $points,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/**
	 * Update user rank based on current points
	 *
	 * @param int $user_id User ID.
	 * @return string New rank.
	 */
	public static function update_user_rank( $user_id ) {
		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_points = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_points FROM $reputation_table WHERE user_id = %d",
				$user_id
			)
		);

		$new_rank = 'newcomer';
		foreach ( self::RANKS as $threshold => $rank_data ) {
			if ( $total_points >= $threshold ) {
				$new_rank = strtolower( $rank_data['name'] );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$reputation_table,
			array( 'current_rank' => $new_rank ),
			array( 'user_id' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $new_rank;
	}

	/**
	 * Get user's earned badges
	 *
	 * @param int $user_id User ID.
	 * @return array Badge keys.
	 */
	public static function get_user_badges( $user_id ) {
		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$badges_json = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT badges FROM $reputation_table WHERE user_id = %d",
				$user_id
			)
		);

		if ( empty( $badges_json ) ) {
			return array();
		}

		return json_decode( $badges_json, true ) ?: array();
	}

	/**
	 * Get user's current rank
	 *
	 * @param int $user_id User ID.
	 * @return array Rank data.
	 */
	public static function get_user_rank( $user_id ) {
		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT current_rank, total_points FROM $reputation_table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $stats ) ) {
			return self::RANKS[0];
		}

		// Find matching rank.
		$current_rank = self::RANKS[0];
		foreach ( self::RANKS as $threshold => $rank_data ) {
			if ( $stats['total_points'] >= $threshold ) {
				$current_rank = $rank_data;
			}
		}

		return $current_rank;
	}

	/**
	 * Manually award a badge to a user
	 *
	 * @param int      $user_id    User ID.
	 * @param string   $badge_key  Badge key.
	 * @param int|null $awarded_by Admin user ID who awarded.
	 * @return bool Success.
	 */
	public static function award_badge( $user_id, $badge_key, $awarded_by = null ) {
		if ( ! isset( self::BADGES[ $badge_key ] ) ) {
			return false;
		}

		$badge = self::BADGES[ $badge_key ];

		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// Get current badges.
		$current_badges = self::get_user_badges( $user_id );

		// Check if already has badge.
		if ( in_array( $badge_key, $current_badges, true ) ) {
			return false;
		}

		// Add badge.
		$current_badges[] = $badge_key;

		// Update reputation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$reputation_table,
			array(
				'badges'      => wp_json_encode( $current_badges ),
				'badge_count' => count( $current_badges ),
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		// Record award.
		$awards_table = $wpdb->base_prefix . 'bd_badge_awards';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$awards_table,
			array(
				'user_id'    => $user_id,
				'badge_key'  => $badge_key,
				'awarded_by' => $awarded_by,
			),
			array( '%d', '%s', '%d' )
		);

		// Award points.
		if ( ! empty( $badge['points'] ) ) {
			self::award_bonus_points( $user_id, $badge['points'], "Badge awarded: {$badge['name']}" );
		}

		// Trigger action.
		do_action( 'bd_badge_awarded', $user_id, $badge_key, $awarded_by );

		return true;
	}

	/**
	 * Remove a badge from a user
	 *
	 * @param int    $user_id   User ID.
	 * @param string $badge_key Badge key.
	 * @return bool Success.
	 */
	public static function remove_badge( $user_id, $badge_key ) {
		global $wpdb;
		$reputation_table = $wpdb->base_prefix . 'bd_user_reputation';

		// Get current badges.
		$current_badges = self::get_user_badges( $user_id );

		// Check if has badge.
		$index = array_search( $badge_key, $current_badges, true );
		if ( false === $index ) {
			return false;
		}

		// Remove badge.
		unset( $current_badges[ $index ] );
		$current_badges = array_values( $current_badges );

		// Update reputation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$reputation_table,
			array(
				'badges'      => wp_json_encode( $current_badges ),
				'badge_count' => count( $current_badges ),
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		// Remove from awards table.
		$awards_table = $wpdb->base_prefix . 'bd_badge_awards';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete(
			$awards_table,
			array(
				'user_id'   => $user_id,
				'badge_key' => $badge_key,
			),
			array( '%d', '%s' )
		);

		return true;
	}
}
