<?php
namespace BD\Frontend;

use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

class BadgeDisplay {
    
    /**
     * Display user's badges inline (for reviews, comments)
     */
    public static function render_user_badges($user_id, $limit = 3, $show_tooltip = true) {
        $badge_keys = BadgeSystem::get_user_badges($user_id);
        
        if (empty($badge_keys)) {
            return '';
        }
        
        // Sort badges by rarity (special > legendary > epic > rare > common)
        $rarity_order = ['special' => 0, 'legendary' => 1, 'epic' => 2, 'rare' => 3, 'common' => 4];
        usort($badge_keys, function($a, $b) use ($rarity_order) {
            $badge_a = BadgeSystem::BADGES[$a] ?? [];
            $badge_b = BadgeSystem::BADGES[$b] ?? [];
            $rarity_a = $rarity_order[$badge_a['rarity'] ?? 'common'] ?? 99;
            $rarity_b = $rarity_order[$badge_b['rarity'] ?? 'common'] ?? 99;
            return $rarity_a - $rarity_b;
        });
        
        // Limit badges shown
        $displayed_badges = array_slice($badge_keys, 0, $limit);
        $remaining_count = count($badge_keys) - $limit;
        
        ob_start();
        ?>
        <div class="bd-user-badges">
            <?php foreach ($displayed_badges as $badge_key): ?>
                <?php 
                $badge = BadgeSystem::BADGES[$badge_key] ?? null;
                if (!$badge) continue;
                ?>
                <span class="bd-badge bd-badge-<?php echo esc_attr($badge['rarity'] ?? 'common'); ?>" 
                      style="background: <?php echo esc_attr($badge['color']); ?>; color: white;"
                      <?php if ($show_tooltip): ?>
                      data-tooltip="<?php echo esc_attr($badge['name'] . ' - ' . $badge['requirement']); ?>"
                      <?php endif; ?>>
                    <span class="bd-badge-icon"><?php echo $badge['icon']; ?></span>
                    <span class="bd-badge-name"><?php echo esc_html($badge['name']); ?></span>
                </span>
            <?php endforeach; ?>
            
            <?php if ($remaining_count > 0): ?>
                <span class="bd-badge bd-badge-more" data-tooltip="View all badges">
                    +<?php echo $remaining_count; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Display user rank badge
     */
    public static function render_rank_badge($user_id, $show_tooltip = true) {
        $rank = BadgeSystem::get_user_rank($user_id);
        
        if (!$rank) return '';
        
        $stats = ActivityTracker::get_user_stats($user_id);
        $points = $stats['total_points'] ?? 0;
        
        ob_start();
        ?>
        <span class="bd-rank-badge" 
              style="background: <?php echo esc_attr($rank['color']); ?>; color: white;"
              <?php if ($show_tooltip): ?>
              data-tooltip="<?php echo esc_attr($rank['name'] . ' • ' . number_format($points) . ' points'); ?>"
              <?php endif; ?>>
            <span class="bd-rank-icon"><?php echo $rank['icon']; ?></span>
            <span class="bd-rank-name"><?php echo esc_html($rank['name']); ?></span>
        </span>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Full profile badge showcase
     */
    public static function render_profile_badges($user_id) {
        $badge_keys = BadgeSystem::get_user_badges($user_id);
        $rank = BadgeSystem::get_user_rank($user_id);
        $stats = ActivityTracker::get_user_stats($user_id);
        
        ob_start();
        ?>
        <div class="bd-profile-badges-section">
            
            <!-- Rank Display -->
            <div class="bd-profile-rank-display">
                <div class="bd-rank-icon-large" style="color: <?php echo esc_attr($rank['color']); ?>;">
                    <?php echo $rank['icon']; ?>
                </div>
                <div class="bd-rank-info">
                    <h3><?php echo esc_html($rank['name']); ?></h3>
                    <p class="bd-rank-points"><?php echo number_format($stats['total_points']); ?> points</p>
                    <?php
                    // Show progress to next rank
                    $next_rank = self::get_next_rank($stats['total_points']);
                    if ($next_rank):
                    ?>
                        <div class="bd-rank-progress">
                            <div class="bd-progress-bar">
                                <?php
                                $current_threshold = self::get_current_rank_threshold($stats['total_points']);
                                $progress = (($stats['total_points'] - $current_threshold) / ($next_rank['threshold'] - $current_threshold)) * 100;
                                ?>
                                <div class="bd-progress-fill" style="width: <?php echo min(100, $progress); ?>%; background: <?php echo esc_attr($next_rank['rank']['color']); ?>;"></div>
                            </div>
                            <p class="bd-progress-text">
                                <?php echo ($next_rank['threshold'] - $stats['total_points']); ?> points to <?php echo esc_html($next_rank['rank']['name']); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="bd-stats-grid">
                <div class="bd-stat-card">
                    <div class="bd-stat-icon">✍️</div>
                    <div class="bd-stat-value"><?php echo number_format($stats['total_reviews']); ?></div>
                    <div class="bd-stat-label">Reviews</div>
                </div>
                <div class="bd-stat-card">
                    <div class="bd-stat-icon">👍</div>
                    <div class="bd-stat-value"><?php echo number_format($stats['helpful_votes']); ?></div>
                    <div class="bd-stat-label">Helpful Votes</div>
                </div>
                <div class="bd-stat-card">
                    <div class="bd-stat-icon">📸</div>
                    <div class="bd-stat-value"><?php echo number_format($stats['photos_uploaded']); ?></div>
                    <div class="bd-stat-label">Photos</div>
                </div>
                <div class="bd-stat-card">
                    <div class="bd-stat-icon">🏆</div>
                    <div class="bd-stat-value"><?php echo count($badge_keys); ?></div>
                    <div class="bd-stat-label">Badges</div>
                </div>
            </div>
            
            <!-- Badges Earned -->
            <?php if (!empty($badge_keys)): ?>
                <div class="bd-badges-section">
                    <h3>Badges Earned</h3>
                    <div class="bd-badge-grid">
                        <?php foreach ($badge_keys as $badge_key): ?>
                            <?php 
                            $badge = BadgeSystem::BADGES[$badge_key] ?? null;
                            if (!$badge) continue;
                            ?>
                            <div class="bd-badge-card bd-badge-card-earned" style="border-color: <?php echo esc_attr($badge['color']); ?>;">
                                <div class="bd-badge-rarity bd-rarity-<?php echo esc_attr($badge['rarity'] ?? 'common'); ?>">
                                    <?php echo ucfirst($badge['rarity'] ?? 'common'); ?>
                                </div>
                                <div class="bd-badge-card-icon" style="color: <?php echo esc_attr($badge['color']); ?>;">
                                    <?php echo $badge['icon']; ?>
                                </div>
                                <div class="bd-badge-card-name"><?php echo esc_html($badge['name']); ?></div>
                                <div class="bd-badge-card-desc"><?php echo esc_html($badge['description']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Badges Available -->
            <?php
            $available_badges = array_filter(BadgeSystem::BADGES, function($badge, $key) use ($badge_keys) {
                return !in_array($key, $badge_keys) && empty($badge['manual']);
            }, ARRAY_FILTER_USE_BOTH);
            
            if (!empty($available_badges)):
            ?>
                <div class="bd-badges-section">
                    <h3>Badges to Earn</h3>
                    <div class="bd-badge-grid">
                        <?php foreach ($available_badges as $badge_key => $badge): ?>
                            <div class="bd-badge-card bd-badge-card-locked">
                                <div class="bd-badge-lock">🔒</div>
                                <div class="bd-badge-card-icon" style="color: #9ca3af;">
                                    <?php echo $badge['icon']; ?>
                                </div>
                                <div class="bd-badge-card-name"><?php echo esc_html($badge['name']); ?></div>
                                <div class="bd-badge-card-desc"><?php echo esc_html($badge['requirement']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get next rank info
     */
    private static function get_next_rank($current_points) {
        foreach (BadgeSystem::RANKS as $threshold => $rank) {
            if ($current_points < $threshold) {
                return [
                    'threshold' => $threshold,
                    'rank' => $rank
                ];
            }
        }
        return null;
    }
    
    /**
     * Get current rank threshold
     */
    private static function get_current_rank_threshold($current_points) {
        $current = 0;
        foreach (BadgeSystem::RANKS as $threshold => $rank) {
            if ($current_points >= $threshold) {
                $current = $threshold;
            } else {
                break;
            }
        }
        return $current;
    }
    
    /**
     * Display review author with badges
     */
    public static function render_review_author($review) {
        $user_id = $review['user_id'] ?? null;
        $author_name = $review['author_name'] ?? 'Guest';
        $created_at = $review['created_at'] ?? '';
        
        ob_start();
        ?>
        <div class="bd-review-author">
            <div class="bd-review-author-avatar">
                <?php if ($user_id): ?>
                    <?php echo get_avatar($user_id, 48); ?>
                <?php else: ?>
                    <div class="bd-avatar-placeholder">
                        <?php echo strtoupper(substr($author_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bd-review-author-info">
                <div class="bd-review-author-name">
                    <?php echo esc_html($author_name); ?>
                    <?php if ($user_id): ?>
                        <?php echo self::render_rank_badge($user_id); ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($user_id): ?>
                    <?php $stats = ActivityTracker::get_user_stats($user_id); ?>
                    <div class="bd-review-author-stats">
                        <?php echo number_format($stats['total_reviews']); ?> reviews • 
                        <?php echo number_format($stats['helpful_votes']); ?> helpful votes
                    </div>
                    <?php echo self::render_user_badges($user_id, 3); ?>
                <?php else: ?>
                    <div class="bd-review-author-stats">
                        Guest Reviewer
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bd-review-date">
                <?php echo human_time_diff(strtotime($created_at), current_time('timestamp')) . ' ago'; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render leaderboard widget
     */
    public static function render_leaderboard($period = 'all_time', $limit = 10) {
        $leaders = ActivityTracker::get_leaderboard($period, $limit);
        
        ob_start();
        ?>
        <div class="bd-leaderboard-widget">
            <div class="bd-leaderboard-header">
                <h3>🏆 Top Contributors</h3>
                <div class="bd-leaderboard-period">
                    <?php
                    switch($period) {
                        case 'week': echo 'This Week'; break;
                        case 'month': echo 'This Month'; break;
                        default: echo 'All Time';
                    }
                    ?>
                </div>
            </div>
            
            <div class="bd-leaderboard-list">
                <?php if (empty($leaders)): ?>
                    <p class="bd-leaderboard-empty">No contributors yet!</p>
                <?php else: ?>
                    <?php foreach ($leaders as $index => $leader): ?>
                        <div class="bd-leaderboard-item">
                            <div class="bd-leaderboard-rank bd-rank-<?php echo $index + 1; ?>">
                                <?php 
                                if ($index < 3) {
                                    echo ['🥇', '🥈', '🥉'][$index];
                                } else {
                                    echo '#' . ($index + 1);
                                }
                                ?>
                            </div>
                            <div class="bd-leaderboard-avatar">
                                <?php echo get_avatar($leader['user_id'], 40); ?>
                            </div>
                            <div class="bd-leaderboard-user">
                                <div class="bd-leaderboard-name">
                                    <?php echo esc_html($leader['display_name']); ?>
                                </div>
                                <div class="bd-leaderboard-stats">
                                    <?php echo number_format($leader['total_points']); ?> pts • 
                                    <?php echo number_format($leader['total_reviews']); ?> reviews
                                </div>
                            </div>
                            <div class="bd-leaderboard-badges">
                                <?php echo self::render_rank_badge($leader['user_id'], false); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}