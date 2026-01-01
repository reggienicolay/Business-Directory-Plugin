<?php
/**
 * Badge Admin
 *
 * Admin pages for managing gamification, badges, and user rewards.
 *
 * @package BusinessDirectory
 */


namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

/**
 * Class BadgeAdmin
 */
class BadgeAdmin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_post_bd_award_badge', array( $this, 'handle_award_badge' ) );
		add_action( 'admin_post_bd_remove_badge', array( $this, 'handle_remove_badge' ) );
		add_action( 'admin_post_bd_recalculate_badges', array( $this, 'handle_recalculate_badges' ) );
		add_action( 'admin_post_bd_recalculate_all_badges', array( $this, 'handle_recalculate_all_badges' ) );
		add_action( 'wp_ajax_bd_search_users', array( $this, 'ajax_search_users' ) );
	}

	/**
	 * Get reputation table name (multisite compatible)
	 *
	 * @return string
	 */
	private function get_reputation_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_reputation';
	}

	/**
	 * Get activity table name (multisite compatible)
	 *
	 * @return string
	 */
	private function get_activity_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_user_activity';
	}

	/**
	 * Get reviews table name (multisite compatible)
	 *
	 * @return string
	 */
	private function get_reviews_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'bd_reviews';
	}

	/**
	 * Add admin menu pages
	 */
	public function add_menu_pages() {
		// Main gamification page.
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Gamification', 'business-directory' ),
			__( '🏆 Gamification', 'business-directory' ),
			'manage_options',
			'bd-gamification',
			array( $this, 'render_overview_page' )
		);

		// Badge Catalog.
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Badge Catalog', 'business-directory' ),
			__( '🎖️ Badge Catalog', 'business-directory' ),
			'manage_options',
			'bd-badge-catalog',
			array( $this, 'render_badge_catalog_page' )
		);

		// User badges management.
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Manage Badges', 'business-directory' ),
			__( 'User Badges', 'business-directory' ),
			'manage_options',
			'bd-user-badges',
			array( $this, 'render_user_badges_page' )
		);

		// Leaderboard.
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Leaderboard', 'business-directory' ),
			__( 'Leaderboard', 'business-directory' ),
			'manage_options',
			'bd-leaderboard',
			array( $this, 'render_leaderboard_page' )
		);
	}

	/**
	 * Render Badge Catalog page - shows ALL available badges
	 */
	public function render_badge_catalog_page() {
		global $wpdb;
		$reputation_table = $this->get_reputation_table();

		// Get total users with reputation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$reputation_table} WHERE total_points > 0" );
		$total_users = max( $total_users, 1 );

		// Get badge counts.
		$badge_counts = array();
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$count                = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reputation_table} WHERE badges LIKE %s",
					'%"' . $key . '"%'
				)
			);
			$badge_counts[ $key ] = (int) $count;
		}

		// Group badges by category.
		$categories = array(
			'community'  => array(
				'name'   => 'Community Status',
				'icon'   => '🏘️',
				'badges' => array( 'love_livermore_verified', 'founding_member' ),
			),
			'reviews'    => array(
				'name'   => 'Review Milestones',
				'icon'   => '✍️',
				'badges' => array( 'first_review', 'reviewer', 'super_reviewer', 'elite_reviewer', 'legend' ),
			),
			'quality'    => array(
				'name'   => 'Quality & Engagement',
				'icon'   => '⭐',
				'badges' => array( 'helpful_reviewer', 'super_helpful', 'photo_lover', 'photographer' ),
			),
			'discovery'  => array(
				'name'   => 'Discovery',
				'icon'   => '🔍',
				'badges' => array( 'explorer', 'local_expert', 'hidden_gem_hunter', 'first_reviewer' ),
			),
			'engagement' => array(
				'name'   => 'Engagement',
				'icon'   => '📅',
				'badges' => array( 'curator', 'list_master', 'early_bird', 'night_owl', 'weekend_warrior' ),
			),
			'special'    => array(
				'name'   => 'Special Recognition',
				'icon'   => '🌟',
				'badges' => array( 'nicoles_pick', 'community_champion' ),
			),
		);

		// Calculate totals.
		$total_badges        = count( BadgeSystem::BADGES );
		$total_badges_earned = array_sum( $badge_counts );
		$auto_badges         = 0;
		$manual_badges       = 0;
		foreach ( BadgeSystem::BADGES as $badge ) {
			if ( ! empty( $badge['manual'] ) ) {
				++$manual_badges;
			} else {
				++$auto_badges;
			}
		}

		$allowed_html = array(
			'i' => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
		);

		?>
		<div class="wrap">
			<h1>🎖️ Badge Catalog</h1>
			<p style="color: #6b7280; margin-bottom: 20px;">
				Complete reference of all badges. Use <code>[bd_badge_gallery]</code> on the frontend.
			</p>

			<!-- Summary Stats -->
			<div class="bd-admin-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #8b5cf6;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( $total_badges ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Total Badges</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( $auto_badges ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Auto-Awarded</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( $manual_badges ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Manual Only</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( number_format( $total_badges_earned ) ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Times Earned</div>
				</div>
			</div>

			<!-- Ranks Section -->
			<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
				<h2 style="margin-top: 0;">📊 Rank Progression</h2>
				<p style="color: #6b7280;">Users earn ranks based on total points accumulated.</p>
				<div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
					<?php foreach ( BadgeSystem::RANKS as $threshold => $rank ) : ?>
						<div style="background: #f8fafc; border: 2px solid <?php echo esc_attr( $rank['color'] ); ?>; border-radius: 12px; padding: 15px 20px; text-align: center; min-width: 120px;">
							<div style="font-size: 28px; color: <?php echo esc_attr( $rank['color'] ); ?>;">
								<?php echo wp_kses( $rank['icon'], $allowed_html ); ?>
							</div>
							<div style="font-weight: 700; color: #1f2937; margin: 8px 0 4px;">
								<?php echo esc_html( $rank['name'] ); ?>
							</div>
							<div style="font-size: 12px; color: #6b7280;">
								<?php echo esc_html( number_format( $threshold ) ); ?>+ pts
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Badge Categories -->
			<?php foreach ( $categories as $cat_key => $category ) : ?>
				<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
					<h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
						<span style="font-size: 24px;"><?php echo esc_html( $category['icon'] ); ?></span>
						<?php echo esc_html( $category['name'] ); ?>
						<span style="background: #e5e7eb; color: #6b7280; font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: normal;">
							<?php echo esc_html( count( $category['badges'] ) ); ?> badges
						</span>
					</h2>

					<table class="widefat striped" style="margin-top: 15px;">
						<thead>
							<tr>
								<th style="width: 50px;">Icon</th>
								<th>Badge</th>
								<th>Requirement</th>
								<th style="width: 80px;">Rarity</th>
								<th style="width: 70px;">Points</th>
								<th style="width: 80px;">Earned</th>
								<th style="width: 70px;">% Users</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $category['badges'] as $badge_key ) :
								if ( ! isset( BadgeSystem::BADGES[ $badge_key ] ) ) {
									continue;
								}
								$badge     = BadgeSystem::BADGES[ $badge_key ];
								$count     = $badge_counts[ $badge_key ] ?? 0;
								$percent   = round( ( $count / $total_users ) * 100, 1 );
								$is_manual = ! empty( $badge['manual'] );
								$points    = $badge['points'] ?? 0;

								$rarity_colors = array(
									'common'    => '#94a3b8',
									'rare'      => '#3b82f6',
									'epic'      => '#8b5cf6',
									'legendary' => '#f59e0b',
									'special'   => '#ec4899',
								);
								$rarity        = $badge['rarity'] ?? 'common';
								$rarity_color  = $rarity_colors[ $rarity ] ?? '#94a3b8';
								?>
								<tr>
									<td style="text-align: center;">
										<span style="font-size: 24px; color: <?php echo esc_attr( $badge['color'] ); ?>;">
											<?php echo wp_kses( $badge['icon'], $allowed_html ); ?>
										</span>
									</td>
									<td>
										<strong style="color: #1f2937;"><?php echo esc_html( $badge['name'] ); ?></strong>
										<?php if ( $is_manual ) : ?>
											<span style="background: #fef3c7; color: #92400e; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">MANUAL</span>
										<?php endif; ?>
										<br>
										<span style="color: #6b7280; font-size: 13px;"><?php echo esc_html( $badge['description'] ); ?></span>
									</td>
									<td style="color: #4b5563; font-size: 13px;">
										<?php echo esc_html( $badge['requirement'] ?? '' ); ?>
									</td>
									<td>
										<span style="background: <?php echo esc_attr( $rarity_color ); ?>; color: white; font-size: 11px; padding: 3px 8px; border-radius: 10px; text-transform: uppercase; font-weight: 600;">
											<?php echo esc_html( $rarity ); ?>
										</span>
									</td>
									<td style="text-align: center; font-weight: 600; color: #10b981;">
										<?php echo $points > 0 ? '+' . esc_html( $points ) : '—'; ?>
									</td>
									<td style="text-align: center; font-weight: 700; color: #1f2937;">
										<?php echo esc_html( number_format( $count ) ); ?>
									</td>
									<td style="text-align: center;">
										<?php if ( $count > 0 ) : ?>
											<span style="color: <?php echo $percent < 10 ? '#ef4444' : ( $percent < 30 ? '#f59e0b' : '#10b981' ); ?>; font-weight: 600;">
												<?php echo esc_html( $percent ); ?>%
											</span>
										<?php else : ?>
											<span style="color: #d1d5db;">0%</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

			<!-- Shortcode Info -->
			<div class="bd-admin-card" style="background: #f0f9ff; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd;">
				<h3 style="margin-top: 0; color: #0369a1;">📄 Frontend Display</h3>
				<p style="color: #0c4a6e;">To display the badge gallery on the frontend, use the shortcode:</p>
				<code style="background: white; padding: 10px 15px; border-radius: 6px; display: inline-block; font-size: 14px;">[bd_badge_gallery]</code>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overview page
	 */
	public function render_overview_page() {
		global $wpdb;
		$reputation_table = $this->get_reputation_table();
		$activity_table   = $this->get_activity_table();

		// Get stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$reputation_table} WHERE total_points > 0" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_points = $wpdb->get_var( "SELECT SUM(total_points) FROM {$reputation_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_reviews = $wpdb->get_var( "SELECT SUM(total_reviews) FROM {$reputation_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_badges = $wpdb->get_var( "SELECT COUNT(*) FROM {$reputation_table} WHERE badges IS NOT NULL AND badges != '' AND badges != '[]'" );

		// Recent activity.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$recent_activity = $wpdb->get_results(
			"SELECT a.*, u.display_name
			FROM {$activity_table} a
			INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
			ORDER BY a.created_at DESC
			LIMIT 20",
			ARRAY_A
		);

		// Badge distribution.
		$badge_stats = array();
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reputation_table} WHERE badges LIKE %s",
					'%"' . $key . '"%'
				)
			);

			if ( $count > 0 ) {
				$badge_stats[ $key ] = array(
					'badge' => $badge,
					'count' => $count,
				);
			}
		}

		uasort(
			$badge_stats,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		$allowed_html = array(
			'i' => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
		);

		?>
		<div class="wrap">
			<h1>🏆 Gamification Overview</h1>

			<div class="bd-admin-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #9333ea;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( number_format( $total_users ?? 0 ) ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Active Users</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( number_format( $total_points ?? 0 ) ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Total Points</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( number_format( $total_reviews ?? 0 ) ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Total Reviews</div>
				</div>
				<div class="bd-admin-stat-card" style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #fbbf24;">
					<div style="font-size: 32px; font-weight: 700; color: #1f2937;"><?php echo esc_html( number_format( $total_badges ?? 0 ) ); ?></div>
					<div style="color: #6b7280; font-size: 14px;">Users with Badges</div>
				</div>
			</div>

			<!-- Bulk Actions -->
			<div class="bd-admin-card" style="background: #fffbeb; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fcd34d;">
				<h3 style="margin-top: 0; color: #92400e;">🔧 Maintenance Tools</h3>
				<p style="color: #78350f;">Recalculate badges for all users. Use this after manual database changes or to fix badge sync issues.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
					<input type="hidden" name="action" value="bd_recalculate_all_badges">
					<?php wp_nonce_field( 'bd_recalculate_all_badges' ); ?>
					<button type="submit" class="button button-secondary" onclick="return confirm('This will recalculate badges for ALL users. Continue?');">
						🔄 Recalculate All User Badges
					</button>
				</form>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

				<!-- Badge Distribution -->
				<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px;">
					<h2>Badge Distribution</h2>
					<p style="margin-top: 0;">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bd_business&page=bd-badge-catalog' ) ); ?>">View full Badge Catalog →</a>
					</p>
					<table class="widefat" style="margin-top: 15px;">
						<thead>
							<tr>
								<th>Badge</th>
								<th>Users</th>
								<th>%</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $badge_stats, 0, 10 ) as $key => $data ) : ?>
								<tr>
									<td>
										<span style="font-size: 18px;"><?php echo wp_kses( $data['badge']['icon'], $allowed_html ); ?></span>
										<?php echo esc_html( $data['badge']['name'] ); ?>
									</td>
									<td><?php echo esc_html( $data['count'] ); ?></td>
									<td>
										<?php
										$percent = $total_users > 0 ? round( ( $data['count'] / $total_users ) * 100, 1 ) : 0;
										echo esc_html( $percent ) . '%';
										?>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $badge_stats ) ) : ?>
								<tr>
									<td colspan="3" style="text-align: center; color: #6b7280;">No badges earned yet</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Recent Activity -->
				<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px;">
					<h2>Recent Activity</h2>
					<?php if ( empty( $recent_activity ) ) : ?>
						<p style="color: #6b7280;">No activity yet.</p>
					<?php else : ?>
						<ul style="margin: 0; padding: 0; list-style: none;">
							<?php foreach ( array_slice( $recent_activity, 0, 10 ) as $activity ) : ?>
								<li style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
									<strong><?php echo esc_html( $activity['display_name'] ); ?></strong>
									<?php echo wp_kses_post( $this->format_activity( $activity ) ); ?>
									<br>
									<span style="color: #9ca3af; font-size: 12px;">
										<?php echo esc_html( human_time_diff( strtotime( $activity['created_at'] ), current_time( 'timestamp' ) ) ); ?> ago
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render user badges management page
	 */
	public function render_user_badges_page() {
		$search_user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$user           = $search_user_id ? get_userdata( $search_user_id ) : null;

		// Check for success messages.
		$badge_awarded       = isset( $_GET['badge_awarded'] );
		$badge_removed       = isset( $_GET['badge_removed'] );
		$badges_recalculated = isset( $_GET['badges_recalculated'] );
		$new_badges_count    = isset( $_GET['new_badges'] ) ? absint( $_GET['new_badges'] ) : 0;

		$allowed_html = array(
			'i' => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
		);

		?>
		<div class="wrap">
			<h1>Manage User Badges</h1>

			<?php if ( $badge_awarded ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ Badge awarded successfully!</p>
				</div>
			<?php endif; ?>

			<?php if ( $badge_removed ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ Badge removed successfully!</p>
				</div>
			<?php endif; ?>

			<?php if ( $badges_recalculated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ Badges recalculated! <?php echo $new_badges_count > 0 ? esc_html( $new_badges_count ) . ' new badge(s) awarded.' : 'No new badges earned.'; ?></p>
				</div>
			<?php endif; ?>

			<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
				<h2>Search User</h2>
				<input type="text"
					id="bd-user-search"
					class="regular-text"
					placeholder="Start typing username or email..."
					autocomplete="off">
				<div id="bd-user-search-results" style="margin-top: 10px;"></div>
			</div>

			<?php if ( $user ) : ?>
				<?php
				$stats  = ActivityTracker::get_user_stats( $user->ID );
				$badges = BadgeSystem::get_user_badges( $user->ID );
				$rank   = BadgeSystem::get_user_rank( $user->ID );
				?>

				<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
					<div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
						<div><?php echo get_avatar( $user->ID, 80 ); ?></div>
						<div style="flex: 1;">
							<h2 style="margin: 0 0 5px 0;"><?php echo esc_html( $user->display_name ); ?></h2>
							<p style="margin: 0; color: #6b7280;"><?php echo esc_html( $user->user_email ); ?></p>
							<div style="margin-top: 10px;">
								<span style="background: <?php echo esc_attr( $rank['color'] ); ?>; color: white; padding: 5px 15px; border-radius: 12px; font-weight: 600;">
									<?php echo wp_kses( $rank['icon'], $allowed_html ); ?>
									<?php echo esc_html( $rank['name'] ); ?>
								</span>
							</div>
						</div>
						<div style="text-align: right;">
							<div style="font-size: 32px; font-weight: 700;"><?php echo esc_html( number_format( $stats['total_points'] ?? 0 ) ); ?></div>
							<div style="color: #6b7280;">Total Points</div>
						</div>
					</div>

					<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; padding: 20px 0; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">
						<div style="text-align: center;">
							<div style="font-size: 24px; font-weight: 700;"><?php echo esc_html( number_format( $stats['total_reviews'] ?? 0 ) ); ?></div>
							<div style="color: #6b7280; font-size: 13px;">Reviews</div>
						</div>
						<div style="text-align: center;">
							<div style="font-size: 24px; font-weight: 700;"><?php echo esc_html( number_format( $stats['helpful_votes_received'] ?? 0 ) ); ?></div>
							<div style="color: #6b7280; font-size: 13px;">Helpful Votes</div>
						</div>
						<div style="text-align: center;">
							<div style="font-size: 24px; font-weight: 700;"><?php echo esc_html( number_format( $stats['photos_uploaded'] ?? 0 ) ); ?></div>
							<div style="color: #6b7280; font-size: 13px;">Photos</div>
						</div>
						<div style="text-align: center;">
							<div style="font-size: 24px; font-weight: 700;"><?php echo esc_html( count( $badges ) ); ?></div>
							<div style="color: #6b7280; font-size: 13px;">Badges</div>
						</div>
					</div>

					<!-- Recalculate Badges Button -->
					<div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; align-items: center; gap: 15px;">
							<input type="hidden" name="action" value="bd_recalculate_badges">
							<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
							<?php wp_nonce_field( 'bd_recalculate_badges_' . $user->ID ); ?>
							<div style="flex: 1;">
								<strong style="color: #0369a1;">🔄 Recalculate Badges</strong><br>
								<span style="color: #0c4a6e; font-size: 13px;">Re-check all badge requirements and award any earned badges.</span>
							</div>
							<button type="submit" class="button button-primary">
								Recalculate Now
							</button>
						</form>
					</div>

					<!-- Current Badges -->
					<h3 style="margin-top: 20px;">Current Badges (<?php echo esc_html( count( $badges ) ); ?>)</h3>
					<?php if ( empty( $badges ) ) : ?>
						<p style="color: #6b7280;">No badges earned yet.</p>
					<?php else : ?>
						<div style="display: flex; gap: 10px; flex-wrap: wrap;">
							<?php foreach ( $badges as $badge_key ) : ?>
								<?php
								$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
								if ( ! $badge ) {
									continue;
								}
								?>
								<div style="background: <?php echo esc_attr( $badge['color'] ); ?>20; border: 2px solid <?php echo esc_attr( $badge['color'] ); ?>; border-radius: 8px; padding: 10px 15px; display: flex; align-items: center; gap: 8px;">
									<span style="font-size: 20px; color: <?php echo esc_attr( $badge['color'] ); ?>;">
										<?php echo wp_kses( $badge['icon'], $allowed_html ); ?>
									</span>
									<span style="font-weight: 600;"><?php echo esc_html( $badge['name'] ); ?></span>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline; margin-left: 10px;">
										<input type="hidden" name="action" value="bd_remove_badge">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
										<input type="hidden" name="badge_key" value="<?php echo esc_attr( $badge_key ); ?>">
										<?php wp_nonce_field( 'bd_remove_badge_' . $user->ID ); ?>
										<button type="submit" class="button button-small" onclick="return confirm('Remove this badge?');">×</button>
									</form>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Award Manual Badges -->
				<div class="bd-admin-card" style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
					<h3 style="margin-top: 0;">Award Manual Badges</h3>
					<p style="color: #6b7280;">These badges can only be awarded manually by administrators.</p>
					<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
						<?php
						foreach ( BadgeSystem::BADGES as $key => $badge ) :
							if ( empty( $badge['manual'] ) ) {
								continue;
							}
							$has_badge = in_array( $key, $badges, true );
							?>
							<div style="background: #f8fafc; border-radius: 8px; padding: 15px; display: flex; align-items: center; gap: 15px; <?php echo $has_badge ? 'opacity: 0.6;' : ''; ?>">
								<span style="font-size: 32px; color: <?php echo esc_attr( $badge['color'] ); ?>;">
									<?php echo wp_kses( $badge['icon'], $allowed_html ); ?>
								</span>
								<div style="flex: 1;">
									<strong><?php echo esc_html( $badge['name'] ); ?></strong><br>
									<span style="color: #6b7280; font-size: 13px;">
										<?php echo esc_html( $badge['description'] ); ?>
									</span>
								</div>
								<?php if ( ! $has_badge ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
										<input type="hidden" name="action" value="bd_award_badge">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
										<input type="hidden" name="badge_key" value="<?php echo esc_attr( $key ); ?>">
										<?php wp_nonce_field( 'bd_award_badge_' . $user->ID ); ?>
										<button type="submit" class="button button-primary button-small">
											Award Badge
										</button>
									</form>
								<?php else : ?>
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
	public function render_leaderboard_page() {
		$period  = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'all_time';
		$leaders = ActivityTracker::get_leaderboard( $period, 50 );

		?>
		<div class="wrap">
			<h1>🏆 Leaderboard</h1>

			<div style="margin: 20px 0;">
				<a href="?post_type=bd_business&page=bd-leaderboard&period=all_time"
					class="button <?php echo 'all_time' === $period ? 'button-primary' : ''; ?>">All Time</a>
				<a href="?post_type=bd_business&page=bd-leaderboard&period=month"
					class="button <?php echo 'month' === $period ? 'button-primary' : ''; ?>">This Month</a>
				<a href="?post_type=bd_business&page=bd-leaderboard&period=week"
					class="button <?php echo 'week' === $period ? 'button-primary' : ''; ?>">This Week</a>
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
					<?php if ( empty( $leaders ) ) : ?>
						<tr>
							<td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
								No activity yet!
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $leaders as $index => $leader ) : ?>
							<?php $rank_display = ( $index < 3 ) ? array( '🥇', '🥈', '🥉' )[ $index ] : '#' . ( $index + 1 ); ?>
							<tr>
								<td style="text-align: center; font-size: 24px; font-weight: 700;">
									<?php echo esc_html( $rank_display ); ?>
								</td>
								<td>
									<div style="display: flex; align-items: center; gap: 10px;">
										<?php echo get_avatar( $leader['user_id'], 40 ); ?>
										<div>
											<strong><?php echo esc_html( $leader['display_name'] ); ?></strong><br>
											<a href="?post_type=bd_business&page=bd-user-badges&user_id=<?php echo esc_attr( $leader['user_id'] ); ?>"
												style="font-size: 12px;">View Profile</a>
										</div>
									</div>
								</td>
								<td><strong><?php echo esc_html( number_format( $leader['total_points'] ) ); ?></strong></td>
								<td><?php echo esc_html( number_format( $leader['total_reviews'] ) ); ?></td>
								<td><?php echo esc_html( number_format( $leader['helpful_votes_received'] ?? 0 ) ); ?></td>
								<td>
									<?php
									$badge_count = 0;
									if ( ! empty( $leader['badges'] ) ) {
										$user_badges = json_decode( $leader['badges'], true );
										$badge_count = is_array( $user_badges ) ? count( $user_badges ) : 0;
									}
									echo esc_html( $badge_count );
									?>
								</td>
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
	public function handle_award_badge() {
		$user_id   = absint( $_POST['user_id'] ?? 0 );
		$badge_key = sanitize_key( $_POST['badge_key'] ?? '' );

		check_admin_referer( 'bd_award_badge_' . $user_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		BadgeSystem::award_badge( $user_id, $badge_key, get_current_user_id() );

		wp_redirect(
			add_query_arg(
				array(
					'post_type'     => 'bd_business',
					'page'          => 'bd-user-badges',
					'user_id'       => $user_id,
					'badge_awarded' => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handle remove badge
	 */
	public function handle_remove_badge() {
		$user_id   = absint( $_POST['user_id'] ?? 0 );
		$badge_key = sanitize_key( $_POST['badge_key'] ?? '' );

		check_admin_referer( 'bd_remove_badge_' . $user_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		BadgeSystem::remove_badge( $user_id, $badge_key );

		wp_redirect(
			add_query_arg(
				array(
					'post_type'     => 'bd_business',
					'page'          => 'bd-user-badges',
					'user_id'       => $user_id,
					'badge_removed' => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handle recalculate badges for single user
	 */
	public function handle_recalculate_badges() {
		$user_id = absint( $_POST['user_id'] ?? 0 );

		check_admin_referer( 'bd_recalculate_badges_' . $user_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Recalculate reputation first.
		ActivityTracker::update_reputation( $user_id );

		// Check and award badges.
		$new_badges = BadgeSystem::check_and_award_badges( $user_id );
		$new_count  = is_array( $new_badges ) ? count( $new_badges ) : 0;

		wp_redirect(
			add_query_arg(
				array(
					'post_type'           => 'bd_business',
					'page'                => 'bd-user-badges',
					'user_id'             => $user_id,
					'badges_recalculated' => 1,
					'new_badges'          => $new_count,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handle recalculate badges for ALL users
	 */
	public function handle_recalculate_all_badges() {
		check_admin_referer( 'bd_recalculate_all_badges' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$reputation_table = $this->get_reputation_table();

		// Get all users with reputation records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$user_ids = $wpdb->get_col( "SELECT user_id FROM {$reputation_table}" );

		$total_new_badges = 0;

		foreach ( $user_ids as $user_id ) {
			// Recalculate reputation.
			ActivityTracker::update_reputation( $user_id );

			// Check and award badges.
			$new_badges = BadgeSystem::check_and_award_badges( $user_id );
			if ( is_array( $new_badges ) ) {
				$total_new_badges += count( $new_badges );
			}
		}

		wp_redirect(
			add_query_arg(
				array(
					'post_type'           => 'bd_business',
					'page'                => 'bd-gamification',
					'badges_recalculated' => 1,
					'users_processed'     => count( $user_ids ),
					'new_badges'          => $total_new_badges,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX search users
	 */
	public function ajax_search_users() {
		$query = sanitize_text_field( $_GET['query'] ?? '' );

		$users = get_users(
			array(
				'search'         => "*{$query}*",
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 10,
			)
		);

		$results = array_map(
			function ( $user ) {
				return array(
					'ID'           => $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
					'avatar'       => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
				);
			},
			$users
		);

		wp_send_json_success( $results );
	}

	/**
	 * Format activity for display
	 *
	 * @param array $activity Activity data.
	 * @return string
	 */
	private function format_activity( $activity ) {
		$type   = $activity['activity_type'] ?? '';
		$points = $activity['points'] ?? 0;

		$labels = array(
			'review_created'        => 'wrote a review',
			'review_with_photo'     => 'added a photo',
			'review_detailed'       => 'wrote a detailed review',
			'helpful_vote_received' => 'received a helpful vote',
			'list_created'          => 'created a list',
			'business_claimed'      => 'claimed a business',
			'profile_completed'     => 'completed their profile',
			'badge_bonus'           => 'earned a badge',
			'first_review_day'      => 'first review of the day',
			'first_login'           => 'logged in for the first time',
		);

		$label = $labels[ $type ] ?? $type;

		return $label . ' <span style="color: #10b981; font-weight: 600;">(+' . esc_html( $points ) . ' pts)</span>';
	}
}
