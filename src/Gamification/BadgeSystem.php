<?php

namespace BD\Gamification;

class BadgeSystem
{

    // Badge definitions
    const BADGES = [
        // ============================================
        // COMMUNITY STATUS BADGES
        // ============================================
        'love_livermore_verified' => [
            'name' => 'Love Livermore Verified',
            'icon' => '<i class="fas fa-check-circle"></i>',
            'color' => '#6B2C3E',
            'description' => 'Verified member of the Love Livermore community',
            'requirement' => 'Member of Love Livermore Facebook group',
            'manual' => true,
            'rarity' => 'special',
        ],
        'founding_member' => [
            'name' => 'Founding Member',
            'icon' => '<i class="fas fa-star"></i>',
            'color' => '#fbbf24',
            'description' => 'One of the first to join the directory',
            'requirement' => 'One of first 100 registered users',
            'auto' => true,
            'rarity' => 'legendary',
        ],

        // ============================================
        // REVIEW QUANTITY BADGES
        // ============================================
        'first_review' => [
            'name' => 'First Review',
            'icon' => '<i class="fas fa-pen"></i>',
            'color' => '#3b82f6',
            'description' => 'Written your first review',
            'requirement' => 'Write 1 review',
            'check' => 'review_count',
            'threshold' => 1,
            'points' => 10,
            'rarity' => 'common',
        ],
        'reviewer' => [
            'name' => 'Reviewer',
            'icon' => '<i class="fas fa-edit"></i>',
            'color' => '#3b82f6',
            'description' => 'Active reviewer in the community',
            'requirement' => 'Write 5 reviews',
            'check' => 'review_count',
            'threshold' => 5,
            'points' => 50,
            'rarity' => 'common',
        ],
        'super_reviewer' => [
            'name' => 'Super Reviewer',
            'icon' => '<i class="fas fa-star-half-alt"></i>',
            'color' => '#8b5cf6',
            'description' => 'Prolific reviewer',
            'requirement' => 'Write 25 reviews',
            'check' => 'review_count',
            'threshold' => 25,
            'points' => 100,
            'rarity' => 'rare',
        ],
        'elite_reviewer' => [
            'name' => 'Elite Reviewer',
            'icon' => '<i class="fas fa-crown"></i>',
            'color' => '#C9A86A',
            'description' => 'Elite status reviewer',
            'requirement' => 'Write 50 reviews',
            'check' => 'review_count',
            'threshold' => 50,
            'points' => 250,
            'rarity' => 'epic',
        ],
        'legend' => [
            'name' => 'Review Legend',
            'icon' => '<i class="fas fa-trophy"></i>',
            'color' => '#f59e0b',
            'description' => 'Legendary reviewer',
            'requirement' => 'Write 100 reviews',
            'check' => 'review_count',
            'threshold' => 100,
            'points' => 500,
            'rarity' => 'legendary',
        ],

        // ============================================
        // QUALITY & ENGAGEMENT BADGES
        // ============================================
        'helpful_reviewer' => [
            'name' => 'Helpful Reviewer',
            'icon' => '<i class="fas fa-thumbs-up"></i>',
            'color' => '#10b981',
            'description' => 'Your reviews help others',
            'requirement' => 'Get 25 helpful votes',
            'check' => 'helpful_votes',
            'threshold' => 25,
            'points' => 100,
            'rarity' => 'rare',
        ],
        'super_helpful' => [
            'name' => 'Super Helpful',
            'icon' => '<i class="fas fa-hands"></i>',
            'color' => '#10b981',
            'description' => 'Extremely helpful to the community',
            'requirement' => 'Get 100 helpful votes',
            'check' => 'helpful_votes',
            'threshold' => 100,
            'points' => 250,
            'rarity' => 'epic',
        ],
        'photo_lover' => [
            'name' => 'Photo Lover',
            'icon' => '<i class="fas fa-camera"></i>',
            'color' => '#ec4899',
            'description' => 'Sharing visual experiences',
            'requirement' => 'Upload 20 photos',
            'check' => 'photo_count',
            'threshold' => 20,
            'points' => 75,
            'rarity' => 'common',
        ],
        'photographer' => [
            'name' => 'Photographer',
            'icon' => '<i class="fas fa-camera-retro"></i>',
            'color' => '#ec4899',
            'description' => 'Visual storyteller',
            'requirement' => 'Upload 50 photos',
            'check' => 'photo_count',
            'threshold' => 50,
            'points' => 150,
            'rarity' => 'rare',
        ],

        // ============================================
        // DISCOVERY BADGES
        // ============================================
        'explorer' => [
            'name' => 'Livermore Explorer',
            'icon' => '<i class="fas fa-map-marked-alt"></i>',
            'color' => '#8b5cf6',
            'description' => 'Exploring different types of businesses',
            'requirement' => 'Review businesses in 5+ categories',
            'check' => 'category_diversity',
            'threshold' => 5,
            'points' => 75,
            'rarity' => 'rare',
        ],
        'local_expert' => [
            'name' => 'Category Expert',
            'icon' => '<i class="fas fa-graduation-cap"></i>',
            'color' => '#fbbf24',
            'description' => 'Expert in a specific category',
            'requirement' => 'Review 10+ businesses in one category',
            'check' => 'category_specialist',
            'threshold' => 10,
            'points' => 100,
            'rarity' => 'rare',
        ],
        'hidden_gem_hunter' => [
            'name' => 'Hidden Gem Hunter',
            'icon' => '<i class="fas fa-gem"></i>',
            'color' => '#14b8a6',
            'description' => 'Finds undiscovered spots',
            'requirement' => 'Review 5 businesses with <10 reviews',
            'check' => 'hidden_gems',
            'threshold' => 5,
            'points' => 75,
            'rarity' => 'epic',
        ],
        'first_reviewer' => [
            'name' => 'First!',
            'icon' => '<i class="fas fa-medal"></i>',
            'color' => '#fbbf24',
            'description' => 'First to review a business',
            'requirement' => 'Be first to review 3 businesses',
            'check' => 'first_reviews',
            'threshold' => 3,
            'points' => 100,
            'rarity' => 'epic',
        ],

        // ============================================
        // ENGAGEMENT BADGES
        // ============================================
        'curator' => [
            'name' => 'List Curator',
            'icon' => '<i class="fas fa-clipboard-list"></i>',
            'color' => '#06b6d4',
            'description' => 'Creating helpful lists',
            'requirement' => 'Create 3 public lists',
            'check' => 'list_count',
            'threshold' => 3,
            'points' => 50,
            'rarity' => 'common',
        ],
        'list_master' => [
            'name' => 'List Master',
            'icon' => '<i class="fas fa-book"></i>',
            'color' => '#06b6d4',
            'description' => 'Master list curator',
            'requirement' => 'Create 10 public lists',
            'check' => 'list_count',
            'threshold' => 10,
            'points' => 150,
            'rarity' => 'rare',
        ],
        'early_bird' => [
            'name' => 'Early Bird',
            'icon' => '<i class="fas fa-sun"></i>',
            'color' => '#f59e0b',
            'description' => 'Active in the morning',
            'requirement' => 'Write 10 reviews before 9am',
            'check' => 'early_reviews',
            'threshold' => 10,
            'points' => 50,
            'rarity' => 'rare',
        ],
        'night_owl' => [
            'name' => 'Night Owl',
            'icon' => '<i class="fas fa-moon"></i>',
            'color' => '#6366f1',
            'description' => 'Active at night',
            'requirement' => 'Write 10 reviews after 9pm',
            'check' => 'late_reviews',
            'threshold' => 10,
            'points' => 50,
            'rarity' => 'rare',
        ],
        'weekend_warrior' => [
            'name' => 'Weekend Warrior',
            'icon' => '<i class="fas fa-calendar-alt"></i>',
            'color' => '#f59e0b',
            'description' => 'Active on weekends',
            'requirement' => 'Write reviews on 10 different weekends',
            'check' => 'weekend_reviews',
            'threshold' => 10,
            'points' => 50,
            'rarity' => 'rare',
        ],

        // ============================================
        // SPECIAL BADGES
        // ============================================
        'nicoles_pick' => [
            'name' => "Nicole's Pick",
            'icon' => '<i class="fas fa-award"></i>',
            'color' => '#9333ea',
            'description' => 'Personally recognized by Nicole',
            'requirement' => 'Awarded by Nicole for exceptional contributions',
            'manual' => true,
            'rarity' => 'legendary',
        ],
        'community_champion' => [
            'name' => 'Community Champion',
            'icon' => '<i class="fas fa-heart"></i>',
            'color' => '#ef4444',
            'description' => 'Champion of local businesses',
            'requirement' => 'Awarded for outstanding community support',
            'manual' => true,
            'rarity' => 'legendary',
        ],
    ];

    // Rank levels based on total points
    const RANKS = [
        0 => ['name' => 'Newcomer', 'icon' => '<i class="fas fa-seedling"></i>', 'color' => '#94a3b8'],
        50 => ['name' => 'Local', 'icon' => '<i class="fas fa-home"></i>', 'color' => '#3b82f6'],
        150 => ['name' => 'Regular', 'icon' => '<i class="fas fa-star"></i>', 'color' => '#8b5cf6'],
        300 => ['name' => 'Insider', 'icon' => '<i class="fas fa-user-tie"></i>', 'color' => '#9333ea'],
        600 => ['name' => 'VIP', 'icon' => '<i class="fas fa-crown"></i>', 'color' => '#fbbf24'],
        1000 => ['name' => 'Legend', 'icon' => '<i class="fas fa-trophy"></i>', 'color' => '#f59e0b'],
    ];

    /**
     * Check and award badges after user activity
     */
    public static function check_and_award_badges($user_id, $activity_type = null)
    {
        if (!$user_id) return;

        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';

        // Get current user stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reputation_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$stats) return;

        // Get current badges
        $current_badges = !empty($stats['badges']) ? json_decode($stats['badges'], true) : [];
        $new_badges = [];

        // Check each badge
        foreach (self::BADGES as $badge_key => $badge) {
            // Skip if already earned
            if (in_array($badge_key, $current_badges)) continue;

            // Skip manual badges
            if (!empty($badge['manual'])) continue;

            // Check if requirements met
            if (self::check_badge_requirement($user_id, $badge, $stats)) {
                $new_badges[] = $badge_key;
                $current_badges[] = $badge_key;

                // Award bonus points
                if (!empty($badge['points'])) {
                    self::award_bonus_points($user_id, $badge['points'], "Badge earned: {$badge['name']}");
                }
            }
        }

        // Update badges if any new ones earned
        if (!empty($new_badges)) {
            $wpdb->update(
                $reputation_table,
                [
                    'badges' => wp_json_encode($current_badges),
                    'badge_count' => count($current_badges),
                ],
                ['user_id' => $user_id],
                ['%s', '%d'],
                ['%d']
            );

            // Trigger notification action
            do_action('bd_badges_earned', $user_id, $new_badges);
        }

        return $new_badges;
    }

    /**
     * Check if badge requirement is met
     */
    private static function check_badge_requirement($user_id, $badge, $stats)
    {
        if (empty($badge['check'])) return false;

        switch ($badge['check']) {
            case 'review_count':
                return $stats['total_reviews'] >= $badge['threshold'];

            case 'helpful_votes':
                return $stats['helpful_votes'] >= $badge['threshold'];

            case 'photo_count':
                return $stats['photos_uploaded'] >= $badge['threshold'];

            case 'list_count':
                return $stats['lists_created'] >= $badge['threshold'];

            case 'category_diversity':
                $categories = !empty($stats['categories_reviewed']) ? json_decode($stats['categories_reviewed'], true) : [];
                return count($categories) >= $badge['threshold'];

            case 'category_specialist':
                return self::check_category_specialist($user_id, $badge['threshold']);

            case 'hidden_gems':
                return self::check_hidden_gems($user_id, $badge['threshold']);

            case 'first_reviews':
                return self::check_first_reviews($user_id, $badge['threshold']);

            case 'early_reviews':
                return self::check_time_based_reviews($user_id, $badge['threshold'], 0, 9);

            case 'late_reviews':
                return self::check_time_based_reviews($user_id, $badge['threshold'], 21, 23);

            case 'weekend_reviews':
                return self::check_weekend_reviews($user_id, $badge['threshold']);
        }

        return false;
    }

    /**
     * Check if user is specialist in any category
     */
    private static function check_category_specialist($user_id, $threshold)
    {
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'bd_reviews';

        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) as cnt
            FROM $reviews_table r
            INNER JOIN {$wpdb->term_relationships} tr ON r.business_id = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE r.user_id = %d 
            AND r.status = 'approved'
            AND tt.taxonomy = 'business_category'
            GROUP BY tt.term_id
            ORDER BY cnt DESC
            LIMIT 1
        ", $user_id));

        return $result >= $threshold;
    }

    /**
     * Check hidden gems (businesses with < 10 reviews)
     */
    private static function check_hidden_gems($user_id, $threshold)
    {
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'bd_reviews';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT r1.business_id)
            FROM $reviews_table r1
            WHERE r1.user_id = %d
            AND r1.status = 'approved'
            AND (
                SELECT COUNT(*)
                FROM $reviews_table r2
                WHERE r2.business_id = r1.business_id
                AND r2.status = 'approved'
            ) < 10
        ", $user_id));

        return $count >= $threshold;
    }

    /**
     * Check first reviews
     */
    private static function check_first_reviews($user_id, $threshold)
    {
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'bd_reviews';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $reviews_table r1
            WHERE r1.user_id = %d
            AND r1.status = 'approved'
            AND NOT EXISTS (
                SELECT 1
                FROM $reviews_table r2
                WHERE r2.business_id = r1.business_id
                AND r2.created_at < r1.created_at
                AND r2.status = 'approved'
            )
        ", $user_id));

        return $count >= $threshold;
    }

    /**
     * Check time-based reviews
     */
    private static function check_time_based_reviews($user_id, $threshold, $hour_start, $hour_end)
    {
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'bd_reviews';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $reviews_table
            WHERE user_id = %d
            AND status = 'approved'
            AND HOUR(created_at) >= %d
            AND HOUR(created_at) <= %d
        ", $user_id, $hour_start, $hour_end));

        return $count >= $threshold;
    }

    /**
     * Check weekend reviews
     */
    private static function check_weekend_reviews($user_id, $threshold)
    {
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'bd_reviews';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM $reviews_table
            WHERE user_id = %d
            AND status = 'approved'
            AND DAYOFWEEK(created_at) IN (1, 7)
        ", $user_id));

        return $count >= $threshold;
    }

    /**
     * Award bonus points
     */
    private static function award_bonus_points($user_id, $points, $reason)
    {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'bd_user_activity';

        $wpdb->insert(
            $activity_table,
            [
                'user_id' => $user_id,
                'activity_type' => 'badge_bonus',
                'points' => $points,
                'metadata' => $reason,
            ],
            ['%d', '%s', '%d', '%s']
        );

        // Update total points
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        $wpdb->query($wpdb->prepare(
            "UPDATE $reputation_table SET total_points = total_points + %d WHERE user_id = %d",
            $points,
            $user_id
        ));
    }

    /**
     * Get user's badges
     */
    public static function get_user_badges($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_user_reputation';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT badges FROM $table WHERE user_id = %d",
            $user_id
        ));

        if ($row && $row->badges) {
            return json_decode($row->badges, true);
        }

        return [];
    }

    /**
     * Get user's rank
     */
    public static function get_user_rank($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_user_reputation';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT total_points FROM $table WHERE user_id = %d",
            $user_id
        ));

        $points = $row ? $row->total_points : 0;

        // Find appropriate rank
        $rank_key = 0;
        foreach (self::RANKS as $threshold => $rank) {
            if ($points >= $threshold) {
                $rank_key = $threshold;
            }
        }

        return self::RANKS[$rank_key];
    }

    /**
     * Manually award a badge (for Nicole)
     */
    public static function award_manual_badge($user_id, $badge_key)
    {
        if (!isset(self::BADGES[$badge_key])) return false;

        $badge = self::BADGES[$badge_key];
        if (empty($badge['manual'])) return false;

        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';

        // Get current badges
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT badges FROM $reputation_table WHERE user_id = %d",
            $user_id
        ));

        $badges = $row && $row->badges ? json_decode($row->badges, true) : [];

        if (in_array($badge_key, $badges)) {
            return false; // Already has it
        }

        $badges[] = $badge_key;

        $wpdb->update(
            $reputation_table,
            [
                'badges' => wp_json_encode($badges),
                'badge_count' => count($badges),
            ],
            ['user_id' => $user_id],
            ['%s', '%d'],
            ['%d']
        );

        do_action('bd_manual_badge_awarded', $user_id, $badge_key);

        return true;
    }

    /**
     * Remove a badge
     */
    public static function remove_badge($user_id, $badge_key)
    {
        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT badges FROM $reputation_table WHERE user_id = %d",
            $user_id
        ));

        $badges = $row && $row->badges ? json_decode($row->badges, true) : [];
        $badges = array_filter($badges, function ($b) use ($badge_key) {
            return $b !== $badge_key;
        });

        $wpdb->update(
            $reputation_table,
            [
                'badges' => wp_json_encode(array_values($badges)),
                'badge_count' => count($badges),
            ],
            ['user_id' => $user_id],
            ['%s', '%d'],
            ['%d']
        );

        return true;
    }
}
