<?php
namespace BD\Gamification;

class ActivityTracker {
    
    // Points awarded for each activity type
    const ACTIVITY_POINTS = [
        'review_created' => 10,
        'review_with_photo' => 5, // Bonus on top of review_created
        'review_detailed' => 5, // Reviews > 100 chars
        'helpful_vote_received' => 2,
        'list_created' => 5,
        'list_made_public' => 10,
        'business_claimed' => 25,
        'profile_completed' => 15,
        'first_review_day' => 20, // Bonus for first review
    ];
    
    /**
     * Track a user activity
     */
    public static function track($user_id, $activity_type, $related_id = null, $metadata = null) {
        if (!$user_id) return false;
        
        global $wpdb;
        $activity_table = $wpdb->prefix . 'bd_user_activity';
        
        // Get points for this activity
        $points = self::ACTIVITY_POINTS[$activity_type] ?? 0;
        
        // Insert activity
        $result = $wpdb->insert(
            $activity_table,
            [
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'points' => $points,
                'related_id' => $related_id,
                'metadata' => is_array($metadata) ? wp_json_encode($metadata) : $metadata,
            ],
            ['%d', '%s', '%d', '%d', '%s']
        );
        
        if ($result) {
            // Update reputation summary
            self::update_reputation($user_id);
            
            // Check for new badges
            BadgeSystem::check_and_award_badges($user_id, $activity_type);
        }
        
        return $result;
    }
    
    /**
     * Update user reputation summary (cached stats)
     */
    public static function update_reputation($user_id) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'bd_user_activity';
        $reviews_table = $wpdb->prefix . 'bd_reviews';
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        
        // Calculate totals from activity
        $activity_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(points) as total_points,
                SUM(CASE WHEN activity_type = 'review_created' THEN 1 ELSE 0 END) as total_reviews,
                SUM(CASE WHEN activity_type = 'helpful_vote_received' THEN 1 ELSE 0 END) as helpful_votes,
                SUM(CASE WHEN activity_type IN ('list_created', 'list_made_public') THEN 1 ELSE 0 END) as lists_created,
                SUM(CASE WHEN activity_type IN ('review_with_photo') THEN 1 ELSE 0 END) as photos_uploaded
            FROM $activity_table
            WHERE user_id = %d
        ", $user_id));
        
        // Get actual review count from reviews table
        $review_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reviews_table WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));
        
        // Get categories reviewed
        $categories = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT tt.term_id
            FROM $reviews_table r
            INNER JOIN {$wpdb->term_relationships} tr ON r.business_id = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE r.user_id = %d
            AND r.status = 'approved'
            AND tt.taxonomy = 'business_category'
        ", $user_id));
        
        // Calculate rank
        $points = $activity_stats->total_points ?? 0;
        $rank = self::calculate_rank($points);
        
        // Prepare data
        $reputation_data = [
            'user_id' => $user_id,
            'total_points' => $points,
            'total_reviews' => $review_count,
            'helpful_votes' => $activity_stats->helpful_votes ?? 0,
            'lists_created' => $activity_stats->lists_created ?? 0,
            'photos_uploaded' => $activity_stats->photos_uploaded ?? 0,
            'categories_reviewed' => wp_json_encode($categories),
            'rank' => $rank,
            'updated_at' => current_time('mysql'),
        ];
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $reputation_table WHERE user_id = %d",
            $user_id
        ));
        
        if ($exists) {
            // Update existing record (preserve badges)
            unset($reputation_data['user_id']);
            $wpdb->update(
                $reputation_table,
                $reputation_data,
                ['user_id' => $user_id],
                ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $reputation_data['badges'] = '[]';
            $reputation_data['badge_count'] = 0;
            $reputation_data['created_at'] = current_time('mysql');
            
            $wpdb->insert(
                $reputation_table,
                $reputation_data,
                ['%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s']
            );
        }
        
        return true;
    }
    
    /**
     * Calculate rank based on points
     */
    private static function calculate_rank($points) {
        $rank = 'newcomer';
        
        foreach (BadgeSystem::RANKS as $threshold => $rank_data) {
            if ($points >= $threshold) {
                $rank = strtolower($rank_data['name']);
            }
        }
        
        return $rank;
    }
    
    /**
     * Get user activity history
     */
    public static function get_user_activity($user_id, $limit = 50) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'bd_user_activity';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $activity_table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Get user stats
     */
    public static function get_user_stats($user_id) {
        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reputation_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$stats) {
            // Return default stats
            return [
                'user_id' => $user_id,
                'total_points' => 0,
                'total_reviews' => 0,
                'helpful_votes' => 0,
                'lists_created' => 0,
                'photos_uploaded' => 0,
                'categories_reviewed' => '[]',
                'rank' => 'newcomer',
                'badges' => '[]',
                'badge_count' => 0,
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get leaderboard
     */
    public static function get_leaderboard($period = 'all_time', $limit = 10) {
        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        
        $where = '1=1';
        
        if ($period === 'month') {
            $where = "updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        } elseif ($period === 'week') {
            $where = "updated_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, u.display_name, u.user_email
            FROM $reputation_table r
            INNER JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE $where
            ORDER BY r.total_points DESC
            LIMIT %d
        ", $limit), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get user rank position
     */
    public static function get_user_rank_position($user_id) {
        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        
        $position = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) + 1
            FROM $reputation_table r1
            WHERE r1.total_points > (
                SELECT total_points 
                FROM $reputation_table 
                WHERE user_id = %d
            )
        ", $user_id));
        
        return $position ?? 0;
    }
    
    /**
     * Get recent achievements (new badges, rank ups)
     */
    public static function get_recent_achievements($user_id, $days = 7) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'bd_user_activity';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $activity_table
            WHERE user_id = %d
            AND activity_type = 'badge_bonus'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
        ", $user_id, $days), ARRAY_A);
    }
}