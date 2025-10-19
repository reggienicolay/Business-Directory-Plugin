<?php

namespace BD\Admin;

use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

class BadgeAdmin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_post_bd_award_badge', [$this, 'handle_award_badge']);
        add_action('admin_post_bd_remove_badge', [$this, 'handle_remove_badge']);
        add_action('wp_ajax_bd_search_users', [$this, 'ajax_search_users']);
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages()
    {
        // Main gamification page
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Gamification', 'business-directory'),
            __('🏆 Gamification', 'business-directory'),
            'manage_options',
            'bd-gamification',
            [$this, 'render_overview_page']
        );

        // User badges management
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Manage Badges', 'business-directory'),
            __('User Badges', 'business-directory'),
            'manage_options',
            'bd-user-badges',
            [$this, 'render_user_badges_page']
        );

        // Leaderboard
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Leaderboard', 'business-directory'),
            __('Leaderboard', 'business-directory'),
            'manage_options',
            'bd-leaderboard',
            [$this, 'render_leaderboard_page']
        );
    }

    /**
     * Render overview page
     */
    public function render_overview_page()
    {
        global $wpdb;
        $reputation_table = $wpdb->prefix . 'bd_user_reputation';
        $activity_table = $wpdb->prefix . 'bd_user_activity';

        // Get stats
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $reputation_table WHERE total_points > 0");
        $total_points = $wpdb->get_var("SELECT SUM(total_points) FROM $reputation_table");
        $total_reviews = $wpdb->get_var("SELECT SUM(total_reviews) FROM $reputation_table");
        $total_badges = $wpdb->get_var("SELECT SUM(badge_count) FROM $reputation_table");

        // Recent activity
        $recent_activity = $wpdb->get_results("
            SELECT a.*, u.display_name
            FROM $activity_table a
            INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
            ORDER BY a.created_at DESC
            LIMIT 20
        ", ARRAY_A);

        // Badge distribution
        $badge_stats = [];
        foreach (BadgeSystem::BADGES as $key => $badge) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM $reputation_table
                WHERE badges LIKE %s
            ", '%"' . $key . '"%'));

            if ($count > 0) {
                $badge_stats[$key] = [
                    'badge' => $badge,
                    'count' => $count
                ];
            }
        }

        // Sort by count
        uasort($badge_stats, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

?>
        <div class="wrap">
            <h1>🏆 Gamification Overview</h1>

            <div class="bd-admin-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #9333ea;">
                    <div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo number_format($total_users); ?></div>
                    <div style="color: #6b7280; font-size: 14px;">Active Users</div>
                </div>
                <div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                    <div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo number_format($total_points); ?></div>
                    <div style="color: #6b7280; font-size: 14px;">Total Points</div>
                </div>
                <div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;">
                    <div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo number_format($total_reviews); ?></div>
                    <div style="color: #6b7280; font-size: 14px;">Total Reviews</div>
                </div>
                <div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #fbbf24;">
                    <div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo number_format($total_badges); ?></div>
                    <div style="color: #6b7280; font-size: 14px;">Badges Earned</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

                <!-- Badge Distribution -->
                <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px;">
                    <h2>Badge Distribution</h2>
                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>Badge</th>
                                <th>Users</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($badge_stats, 0, 10) as $key => $data): ?>
                                <tr>
                                    <td>
                                        <span style="font-size: 18px;"><?php
                                                                        $allowed_html = ['i' => ['class' => [], 'aria-hidden' => []]];
                                                                        echo wp_kses($data['badge']['icon'], $allowed_html);
                                                                        ?></span>
                                        <strong><?php echo esc_html($data['badge']['name']); ?></strong>
                                    </td>
                                    <td><?php echo number_format($data['count']); ?></td>
                                    <td><?php echo round(($data['count'] / max($total_users, 1)) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Activity -->
                <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px;">
                    <h2>Recent Activity</h2>
                    <div style="margin-top: 15px;">
                        <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                            <div style="padding: 10px; border-bottom: 1px solid #f3f4f6;">
                                <strong><?php echo esc_html($activity['display_name']); ?></strong>
                                <span style="color: #6b7280;">
                                    <?php echo $this->format_activity($activity); ?>
                                </span>
                                <span style="color: #9ca3af; font-size: 12px; float: right;">
                                    <?php echo human_time_diff(strtotime($activity['created_at']), current_time('timestamp')); ?> ago
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    <?php
    }

    /**
     * Render user badges management page
     */
    public function render_user_badges_page()
    {
        // Handle search
        $search_user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        $user = $search_user_id ? get_userdata($search_user_id) : null;

    ?>
        <div class="wrap">
            <h1>Manage User Badges</h1>

            <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>Search User</h2>
                <input type="text"
                    id="bd-user-search"
                    class="regular-text"
                    placeholder="Start typing username or email..."
                    autocomplete="off">
                <div id="bd-user-search-results" style="margin-top: 10px;"></div>
            </div>

            <?php if ($user): ?>
                <?php
                $stats = ActivityTracker::get_user_stats($user->ID);
                $badges = BadgeSystem::get_user_badges($user->ID);
                $rank = BadgeSystem::get_user_rank($user->ID);
                ?>

                <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <div><?php echo get_avatar($user->ID, 80); ?></div>
                        <div style="flex: 1;">
                            <h2 style="margin: 0 0 5px 0;"><?php echo esc_html($user->display_name); ?></h2>
                            <p style="margin: 0; color: #6b7280;"><?php echo esc_html($user->user_email); ?></p>
                            <div style="margin-top: 10px;">
                                <span style="background: <?php echo $rank['color']; ?>; color: white; padding: 5px 15px; border-radius: 12px; font-weight: 600;">
                                    <?php
                                    $allowed_html = ['i' => ['class' => [], 'aria-hidden' => []]];
                                    echo wp_kses($rank['icon'], $allowed_html);
                                    ?> <?php echo $rank['name']; ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($stats['total_points']); ?></div>
                            <div style="color: #6b7280;">Total Points</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($stats['total_reviews']); ?></div>
                            <div style="color: #6b7280; font-size: 14px;">Reviews</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($stats['helpful_votes']); ?></div>
                            <div style="color: #6b7280; font-size: 14px;">Helpful Votes</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo number_format($stats['photos_uploaded']); ?></div>
                            <div style="color: #6b7280; font-size: 14px;">Photos</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo count($badges); ?></div>
                            <div style="color: #6b7280; font-size: 14px;">Badges</div>
                        </div>
                    </div>
                </div>

                <!-- Current Badges -->
                <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h2>Current Badges</h2>
                    <?php if (empty($badges)): ?>
                        <p style="color: #6b7280;">No badges earned yet.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 15px;">
                            <?php foreach ($badges as $badge_key): ?>
                                <?php $badge = BadgeSystem::BADGES[$badge_key] ?? null; ?>
                                <?php if (!$badge) continue; ?>
                                <div style="border: 2px solid <?php echo $badge['color']; ?>; border-radius: 8px; padding: 15px; text-align: center;">
                                    <div style="font-size: 36px; margin-bottom: 8px;"><?php
                                                                                        // Allow only Font Awesome icon tags
                                                                                        $allowed_html = [
                                                                                            'i' => [
                                                                                                'class' => [],
                                                                                                'aria-hidden' => []
                                                                                            ]
                                                                                        ];
                                                                                        echo wp_kses($badge['icon'], $allowed_html);
                                                                                        ?></div>
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?php echo esc_html($badge['name']); ?></div>
                                    <?php if (!empty($badge['manual'])): ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 10px;">
                                            <input type="hidden" name="action" value="bd_remove_badge">
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                            <input type="hidden" name="badge_key" value="<?php echo esc_attr($badge_key); ?>">
                                            <?php wp_nonce_field('bd_remove_badge_' . $user->ID); ?>
                                            <button type="submit" class="button button-small"
                                                onclick="return confirm('Remove this badge?')">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Award Special Badges -->
                <div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h2>Award Special Badge</h2>
                    <p style="color: #6b7280;">These badges can only be awarded manually by admins.</p>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;">
                        <?php foreach (BadgeSystem::BADGES as $key => $badge): ?>
                            <?php if (empty($badge['manual'])) continue; ?>
                            <?php $has_badge = in_array($key, $badges); ?>
                            <div style="border: 2px solid <?php echo $has_badge ? '#d1d5db' : $badge['color']; ?>; 
                                        border-radius: 8px; padding: 15px; text-align: center;
                                        opacity: <?php echo $has_badge ? '0.4' : '1'; ?>;">
                                <div style="font-size: 36px; margin-bottom: 8px;"><?php
                                                                                    // Allow only Font Awesome icon tags
                                                                                    $allowed_html = [
                                                                                        'i' => [
                                                                                            'class' => [],
                                                                                            'aria-hidden' => []
                                                                                        ]
                                                                                    ];
                                                                                    echo wp_kses($badge['icon'], $allowed_html);
                                                                                    ?></div>
                                <div style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html($badge['name']); ?></div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 10px;">
                                    <?php echo esc_html($badge['description']); ?>
                                </div>
                                <?php if (!$has_badge): ?>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="bd_award_badge">
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="badge_key" value="<?php echo esc_attr($key); ?>">
                                        <?php wp_nonce_field('bd_award_badge_' . $user->ID); ?>
                                        <button type="submit" class="button button-primary button-small">
                                            Award Badge
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #10b981; font-weight: 600;">✓ Awarded</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <script>
            jQuery(document).ready(function($) {
                let searchTimeout;

                $('#bd-user-search').on('input', function() {
                    const query = $(this).val();

                    if (query.length < 2) {
                        $('#bd-user-search-results').html('');
                        return;
                    }

                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: ajaxurl,
                            data: {
                                action: 'bd_search_users',
                                query: query
                            },
                            success: function(response) {
                                if (response.success && response.data.length > 0) {
                                    let html = '<div style="border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
                                    response.data.forEach(function(user) {
                                        html += '<a href="?post_type=bd_business&page=bd-user-badges&user_id=' + user.ID + '" ';
                                        html += 'style="display: flex; align-items: center; gap: 10px; padding: 10px; text-decoration: none; color: inherit; border-bottom: 1px solid #f3f4f6;">';
                                        html += '<img src="' + user.avatar + '" width="40" height="40" style="border-radius: 50%;">';
                                        html += '<div><strong>' + user.display_name + '</strong><br><span style="color: #6b7280; font-size: 12px;">' + user.user_email + '</span></div>';
                                        html += '</a>';
                                    });
                                    html += '</div>';
                                    $('#bd-user-search-results').html(html);
                                } else {
                                    $('#bd-user-search-results').html('<p style="color: #6b7280;">No users found</p>');
                                }
                            }
                        });
                    }, 300);
                });
            });
        </script>
    <?php
    }

    /**
     * Render leaderboard page
     */
    public function render_leaderboard_page()
    {
        $period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'all_time';
        $leaders = ActivityTracker::get_leaderboard($period, 50);

    ?>
        <div class="wrap">
            <h1>🏆 Leaderboard</h1>

            <div style="margin: 20px 0;">
                <a href="?post_type=bd_business&page=bd-leaderboard&period=all_time"
                    class="button <?php echo $period === 'all_time' ? 'button-primary' : ''; ?>">All Time</a>
                <a href="?post_type=bd_business&page=bd-leaderboard&period=month"
                    class="button <?php echo $period === 'month' ? 'button-primary' : ''; ?>">This Month</a>
                <a href="?post_type=bd_business&page=bd-leaderboard&period=week"
                    class="button <?php echo $period === 'week' ? 'button-primary' : ''; ?>">This Week</a>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>User</th>
                        <th>Points</th>
                        <th>Reviews</th>
                        <th>Helpful Votes</th>
                        <th>Badges</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                                No activity yet!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaders as $index => $leader): ?>
                            <?php $rank_display = ($index < 3) ? ['🥇', '🥈', '🥉'][$index] : '#' . ($index + 1); ?>
                            <tr>
                                <td style="text-align: center; font-size: 24px; font-weight: 700;">
                                    <?php echo $rank_display; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php echo get_avatar($leader['user_id'], 40); ?>
                                        <div>
                                            <strong><?php echo esc_html($leader['display_name']); ?></strong><br>
                                            <a href="?post_type=bd_business&page=bd-user-badges&user_id=<?php echo $leader['user_id']; ?>"
                                                style="font-size: 12px;">View Profile</a>
                                        </div>
                                    </div>
                                </td>
                                <td><strong><?php echo number_format($leader['total_points']); ?></strong></td>
                                <td><?php echo number_format($leader['total_reviews']); ?></td>
                                <td><?php echo number_format($leader['helpful_votes']); ?></td>
                                <td><?php echo $leader['badge_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }

    /**
     * Handle award badge
     */
    public function handle_award_badge()
    {
        $user_id = absint($_POST['user_id']);
        $badge_key = sanitize_key($_POST['badge_key']);

        check_admin_referer('bd_award_badge_' . $user_id);

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        BadgeSystem::award_manual_badge($user_id, $badge_key);

        wp_redirect(add_query_arg([
            'post_type' => 'bd_business',
            'page' => 'bd-user-badges',
            'user_id' => $user_id,
            'badge_awarded' => 1
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * Handle remove badge
     */
    public function handle_remove_badge()
    {
        $user_id = absint($_POST['user_id']);
        $badge_key = sanitize_key($_POST['badge_key']);

        check_admin_referer('bd_remove_badge_' . $user_id);

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        BadgeSystem::remove_badge($user_id, $badge_key);

        wp_redirect(add_query_arg([
            'post_type' => 'bd_business',
            'page' => 'bd-user-badges',
            'user_id' => $user_id,
            'badge_removed' => 1
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * AJAX search users
     */
    public function ajax_search_users()
    {
        $query = sanitize_text_field($_GET['query']);

        $users = get_users([
            'search' => "*{$query}*",
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10,
        ]);

        $results = array_map(function ($user) {
            return [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 40]),
            ];
        }, $users);

        wp_send_json_success($results);
    }

    /**
     * Format activity for display
     */
    private function format_activity($activity)
    {
        $type = $activity['activity_type'];
        $points = $activity['points'];

        $labels = [
            'review_created' => 'wrote a review',
            'review_with_photo' => 'added a photo',
            'review_detailed' => 'wrote a detailed review',
            'helpful_vote_received' => 'received a helpful vote',
            'list_created' => 'created a list',
            'business_claimed' => 'claimed a business',
            'profile_completed' => 'completed their profile',
            'badge_bonus' => 'earned a badge',
        ];

        $label = $labels[$type] ?? $type;

        return $label . ' <span style="color: #10b981; font-weight: 600;">(+' . $points . ' pts)</span>';
    }
}
