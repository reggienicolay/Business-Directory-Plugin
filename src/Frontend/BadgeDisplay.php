<?php
/**
 * Badge Display Frontend
 *
 * Handles rendering of badges, ranks, and the public badge gallery.
 *
 * @package BusinessDirectory
 */


namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

class BadgeDisplay {

	/**
	 * Initialize shortcodes.
	 * Call this from your main plugin file or loader.
	 */
	public static function init() {
		add_shortcode( 'bd_badge_gallery', array( __CLASS__, 'render_badge_gallery' ) );
	}

	/**
	 * Display user's badges inline (for reviews, comments)
	 */
	public static function render_user_badges( $user_id, $limit = 3, $show_tooltip = true ) {
		$badge_keys = BadgeSystem::get_user_badges( $user_id );

		if ( empty( $badge_keys ) ) {
			return '';
		}

		// Sort badges by rarity (special > legendary > epic > rare > common)
		$rarity_order = array(
			'special'   => 0,
			'legendary' => 1,
			'epic'      => 2,
			'rare'      => 3,
			'common'    => 4,
		);
		usort(
			$badge_keys,
			function ( $a, $b ) use ( $rarity_order ) {
				$badge_a  = BadgeSystem::BADGES[ $a ] ?? array();
				$badge_b  = BadgeSystem::BADGES[ $b ] ?? array();
				$rarity_a = $rarity_order[ $badge_a['rarity'] ?? 'common' ] ?? 99;
				$rarity_b = $rarity_order[ $badge_b['rarity'] ?? 'common' ] ?? 99;
				return $rarity_a - $rarity_b;
			}
		);

		// Limit badges shown
		$displayed_badges = array_slice( $badge_keys, 0, $limit );
		$remaining_count  = count( $badge_keys ) - $limit;

		ob_start();
		?>
		<div class="bd-user-badges">
			<?php foreach ( $displayed_badges as $badge_key ) : ?>
				<?php
				$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
				if ( ! $badge ) {
					continue;
				}
				?>
				<span class="bd-badge bd-badge-<?php echo esc_attr( $badge['rarity'] ?? 'common' ); ?>" 
						style="background: <?php echo esc_attr( $badge['color'] ); ?>; color: white;"
						<?php if ( $show_tooltip ) : ?>
						data-tooltip="<?php echo esc_attr( $badge['name'] . ' - ' . $badge['requirement'] ); ?>"
						<?php endif; ?>>
					<span class="bd-badge-icon"><?php echo $badge['icon']; ?></span>
					<span class="bd-badge-name"><?php echo esc_html( $badge['name'] ); ?></span>
				</span>
			<?php endforeach; ?>
			
			<?php if ( $remaining_count > 0 ) : ?>
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
	public static function render_rank_badge( $user_id, $show_tooltip = true ) {
		$rank = BadgeSystem::get_user_rank( $user_id );

		if ( ! $rank ) {
			return '';
		}

		$stats  = ActivityTracker::get_user_stats( $user_id );
		$points = $stats['total_points'] ?? 0;

		ob_start();
		?>
		<span class="bd-rank-badge" 
				style="background: <?php echo esc_attr( $rank['color'] ); ?>; color: white;"
				<?php if ( $show_tooltip ) : ?>
				data-tooltip="<?php echo esc_attr( $rank['name'] . ' • ' . number_format( $points ) . ' points' ); ?>"
				<?php endif; ?>>
			<span class="bd-rank-icon"><?php echo $rank['icon']; ?></span>
			<span class="bd-rank-name"><?php echo esc_html( $rank['name'] ); ?></span>
		</span>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render public badge gallery [bd_badge_gallery]
	 *
	 * Uses existing badges.css styling with Font Awesome icons.
	 */
	public static function render_badge_gallery( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'show_ranks' => 'yes',
			),
			$atts
		);

		$user_id      = get_current_user_id();
		$user_badges  = $user_id ? BadgeSystem::get_user_badges( $user_id ) : array();
		$is_logged_in = is_user_logged_in();

		// Badge categories
		$categories = array(
			'community'  => array(
				'name'   => 'Community Status',
				'icon'   => '<i class="fas fa-users"></i>',
				'desc'   => 'Special recognition for community members',
				'badges' => array( 'love_livermore_verified', 'founding_member' ),
			),
			'reviews'    => array(
				'name'   => 'Review Milestones',
				'icon'   => '<i class="fas fa-pen"></i>',
				'desc'   => 'Earn these by writing reviews',
				'badges' => array( 'first_review', 'reviewer', 'super_reviewer', 'elite_reviewer', 'legend' ),
			),
			'quality'    => array(
				'name'   => 'Quality & Engagement',
				'icon'   => '<i class="fas fa-star"></i>',
				'desc'   => 'Recognition for helpful and quality contributions',
				'badges' => array( 'helpful_reviewer', 'super_helpful', 'photo_lover', 'photographer' ),
			),
			'discovery'  => array(
				'name'   => 'Discovery',
				'icon'   => '<i class="fas fa-compass"></i>',
				'desc'   => 'For exploring and discovering local gems',
				'badges' => array( 'explorer', 'local_expert', 'hidden_gem_hunter', 'first_reviewer' ),
			),
			'engagement' => array(
				'name'   => 'Engagement',
				'icon'   => '<i class="fas fa-calendar-check"></i>',
				'desc'   => 'For consistent community engagement',
				'badges' => array( 'curator', 'list_master', 'early_bird', 'night_owl', 'weekend_warrior' ),
			),
			'special'    => array(
				'name'   => 'Special Recognition',
				'icon'   => '<i class="fas fa-gem"></i>',
				'desc'   => 'Exclusive badges awarded by our team',
				'badges' => array( 'nicoles_pick', 'community_champion' ),
			),
		);

		// Rarity info - TriValley Vine colors
		$rarity_info = array(
			'common'    => array(
				'color' => '#94a3b8',
				'label' => 'Common',
			),
			'rare'      => array(
				'color' => '#3b82f6',
				'label' => 'Rare',
			),
			'epic'      => array(
				'color' => '#8b5cf6',
				'label' => 'Epic',
			),
			'legendary' => array(
				'color' => '#f59e0b',
				'label' => 'Legendary',
			),
			'special'   => array(
				'color' => '#C9A227',
				'label' => 'Special',
			),
		);

		ob_start();
		?>
		<div class="bd-badge-gallery">

			<!-- Header - Wine Country Theme -->
			<div class="bd-badge-gallery-header">
				<h1><i class="fas fa-award"></i> Badge Collection</h1>
				<p>Earn badges by contributing to our community. Write reviews, share photos, and help others discover local businesses!</p>

				<?php if ( $is_logged_in ) : ?>
					<div class="bd-badge-progress-summary">
						<span class="bd-badge-earned-count">
							<strong><?php echo count( $user_badges ); ?></strong> of <?php echo count( BadgeSystem::BADGES ); ?> badges earned
						</span>
						<div class="bd-progress-bar">
							<div class="bd-progress-fill" style="width: <?php echo ( count( $user_badges ) / count( BadgeSystem::BADGES ) ) * 100; ?>%;"></div>
						</div>
					</div>
				<?php else : ?>
					<p class="bd-badge-login-prompt">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to track your badge progress!
					</p>
				<?php endif; ?>
			</div>

			<!-- Ranks Section -->
			<?php if ( 'yes' === $atts['show_ranks'] ) : ?>
				<div class="bd-badges-section bd-ranks-section">
					<h3><i class="fas fa-chart-line"></i> Rank Progression</h3>
					<p class="bd-section-desc">Earn points through activities to level up your rank!</p>

					<div class="bd-ranks-grid">
						<?php
						$user_points   = 0;
						$user_rank_key = 0;
						if ( $is_logged_in ) {
							$stats       = ActivityTracker::get_user_stats( $user_id );
							$user_points = $stats['total_points'] ?? 0;
							foreach ( BadgeSystem::RANKS as $threshold => $rank ) {
								if ( $user_points >= $threshold ) {
									$user_rank_key = $threshold;
								}
							}
						}

						foreach ( BadgeSystem::RANKS as $threshold => $rank ) :
							$is_current   = ( $is_logged_in && $threshold === $user_rank_key );
							$is_achieved  = ( $is_logged_in && $user_points >= $threshold );
							$points_to_go = $threshold - $user_points;
							?>
							<div class="bd-rank-card <?php echo $is_current ? 'bd-rank-current' : ''; ?> <?php echo $is_achieved ? 'bd-rank-achieved' : 'bd-rank-locked'; ?>">
								<?php if ( $is_current ) : ?>
									<div class="bd-rank-current-label">Current</div>
								<?php endif; ?>
								<div class="bd-rank-card-icon" style="color: <?php echo esc_attr( $rank['color'] ); ?>;">
									<?php echo $rank['icon']; ?>
								</div>
								<div class="bd-rank-card-name"><?php echo esc_html( $rank['name'] ); ?></div>
								<div class="bd-rank-card-points"><?php echo number_format( $threshold ); ?>+ pts</div>
								<?php if ( $is_logged_in && ! $is_achieved ) : ?>
									<div class="bd-rank-card-togo"><?php echo number_format( $points_to_go ); ?> to go</div>
								<?php elseif ( $is_achieved && ! $is_current ) : ?>
									<div class="bd-rank-card-check"><i class="fas fa-check"></i></div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Rarity Legend -->
			<div class="bd-rarity-legend">
				<span class="bd-rarity-label"><i class="fas fa-info-circle"></i> Rarity:</span>
				<?php foreach ( $rarity_info as $key => $info ) : ?>
					<span class="bd-rarity-item bd-rarity-<?php echo esc_attr( $key ); ?>">
						<span class="bd-rarity-dot" style="background: <?php echo esc_attr( $info['color'] ); ?>;"></span>
						<?php echo esc_html( $info['label'] ); ?>
					</span>
				<?php endforeach; ?>
			</div>

			<!-- Badge Categories -->
			<?php foreach ( $categories as $cat_key => $category ) : ?>
				<div class="bd-badges-section">
					<h3>
						<span class="bd-section-icon"><?php echo $category['icon']; ?></span>
						<?php echo esc_html( $category['name'] ); ?>
					</h3>
					<p class="bd-section-desc"><?php echo esc_html( $category['desc'] ); ?></p>

					<div class="bd-badge-grid">
						<?php
						foreach ( $category['badges'] as $badge_key ) :
							if ( ! isset( BadgeSystem::BADGES[ $badge_key ] ) ) {
								continue;
							}
							$badge     = BadgeSystem::BADGES[ $badge_key ];
							$has_badge = in_array( $badge_key, $user_badges, true );
							$rarity    = $badge['rarity'] ?? 'common';
							$is_manual = ! empty( $badge['manual'] );

							// Use existing badges.css classes
							$card_class = $has_badge ? 'bd-badge-card bd-badge-card-earned' : 'bd-badge-card bd-badge-card-locked';
							?>
							<div class="<?php echo esc_attr( $card_class ); ?>" data-rarity="<?php echo esc_attr( $rarity ); ?>" style="border-color: <?php echo $has_badge ? esc_attr( $badge['color'] ) : '#d1d5db'; ?>;">

								<?php if ( $has_badge ) : ?>
									<div class="bd-badge-check"><i class="fas fa-check-circle"></i></div>
								<?php else : ?>
									<div class="bd-badge-lock"><i class="fas fa-lock"></i></div>
								<?php endif; ?>

								<div class="bd-badge-rarity bd-rarity-<?php echo esc_attr( $rarity ); ?>">
									<?php echo esc_html( ucfirst( $rarity ) ); ?>
								</div>

								<div class="bd-badge-card-icon" style="color: <?php echo $has_badge ? esc_attr( $badge['color'] ) : '#9ca3af'; ?>;">
									<?php echo $badge['icon']; ?>
								</div>

								<div class="bd-badge-card-name"><?php echo esc_html( $badge['name'] ); ?></div>

								<div class="bd-badge-card-desc">
									<?php echo esc_html( $has_badge ? $badge['description'] : $badge['requirement'] ); ?>
								</div>

								<?php if ( ! empty( $badge['points'] ) && ! $has_badge ) : ?>
									<div class="bd-badge-card-points">+<?php echo (int) $badge['points']; ?> pts</div>
								<?php endif; ?>

								<?php if ( $is_manual ) : ?>
									<div class="bd-badge-manual"><i class="fas fa-hand-holding-heart"></i> Team awarded</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<!-- Call to Action for non-logged-in users -->
			<?php if ( ! $is_logged_in ) : ?>
				<div class="bd-badge-cta">
					<h3><i class="fas fa-rocket"></i> Start Earning Badges Today!</h3>
					<p>Join our community to track your progress and earn exclusive badges.</p>
					<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="bd-btn bd-btn-primary">
						<i class="fas fa-user-plus"></i> Create Account
					</a>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bd-btn bd-btn-secondary">
						<i class="fas fa-sign-in-alt"></i> Log In
					</a>
				</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Full profile badge showcase
	 */
	public static function render_profile_badges( $user_id ) {
		$badge_keys = BadgeSystem::get_user_badges( $user_id );
		$rank       = BadgeSystem::get_user_rank( $user_id );
		$stats      = ActivityTracker::get_user_stats( $user_id );

		ob_start();
		?>
		<div class="bd-profile-badges-section">
			
			<!-- Rank Display -->
			<div class="bd-profile-rank-display">
				<div class="bd-rank-icon-large" style="color: <?php echo esc_attr( $rank['color'] ); ?>;">
					<?php echo $rank['icon']; ?>
				</div>
				<div class="bd-rank-info">
					<h3><?php echo esc_html( $rank['name'] ); ?></h3>
					<p class="bd-rank-points"><?php echo number_format( $stats['total_points'] ); ?> points</p>
					<?php
					// Show progress to next rank
					$next_rank = self::get_next_rank( $stats['total_points'] );
					if ( $next_rank ) :
						?>
						<div class="bd-rank-progress">
							<div class="bd-progress-bar">
								<?php
								$current_threshold = self::get_current_rank_threshold( $stats['total_points'] );
								$progress          = ( ( $stats['total_points'] - $current_threshold ) / ( $next_rank['threshold'] - $current_threshold ) ) * 100;
								?>
								<div class="bd-progress-fill" style="width: <?php echo min( 100, $progress ); ?>%; background: <?php echo esc_attr( $next_rank['rank']['color'] ); ?>;"></div>
							</div>
							<p class="bd-progress-text">
								<?php echo ( $next_rank['threshold'] - $stats['total_points'] ); ?> points to <?php echo esc_html( $next_rank['rank']['name'] ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Stats Grid -->
			<div class="bd-stats-grid">
				<div class="bd-stat-card">
					<div class="bd-stat-icon">✏️</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['total_reviews'] ); ?></div>
					<div class="bd-stat-label">Reviews</div>
				</div>
				<div class="bd-stat-card">
					<div class="bd-stat-icon">👍</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['helpful_votes'] ); ?></div>
					<div class="bd-stat-label">Helpful Votes</div>
				</div>
				<div class="bd-stat-card">
					<div class="bd-stat-icon">📸</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['photos_uploaded'] ); ?></div>
					<div class="bd-stat-label">Photos</div>
				</div>
				<div class="bd-stat-card">
					<div class="bd-stat-icon">🏆</div>
					<div class="bd-stat-value"><?php echo count( $badge_keys ); ?></div>
					<div class="bd-stat-label">Badges</div>
				</div>
			</div>
			
			<!-- Badges Earned -->
			<?php if ( ! empty( $badge_keys ) ) : ?>
				<div class="bd-badges-section">
					<h3>Badges Earned</h3>
					<div class="bd-badge-grid">
						<?php foreach ( $badge_keys as $badge_key ) : ?>
							<?php
							$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
							if ( ! $badge ) {
								continue;
							}
							?>
							<div class="bd-badge-card bd-badge-card-earned" style="border-color: <?php echo esc_attr( $badge['color'] ); ?>;">
								<div class="bd-badge-rarity bd-rarity-<?php echo esc_attr( $badge['rarity'] ?? 'common' ); ?>">
									<?php echo ucfirst( $badge['rarity'] ?? 'common' ); ?>
								</div>
								<div class="bd-badge-card-icon" style="color: <?php echo esc_attr( $badge['color'] ); ?>;">
									<?php echo $badge['icon']; ?>
								</div>
								<div class="bd-badge-card-name"><?php echo esc_html( $badge['name'] ); ?></div>
								<div class="bd-badge-card-desc"><?php echo esc_html( $badge['description'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			
			<!-- Badges Available -->
			<?php
			$available_badges = array_filter(
				BadgeSystem::BADGES,
				function ( $badge, $key ) use ( $badge_keys ) {
					return ! in_array( $key, $badge_keys, true ) && empty( $badge['manual'] );
				},
				ARRAY_FILTER_USE_BOTH
			);

			if ( ! empty( $available_badges ) ) :
				?>
				<div class="bd-badges-section">
					<h3>Badges to Earn</h3>
					<div class="bd-badge-grid">
						<?php foreach ( $available_badges as $badge_key => $badge ) : ?>
							<div class="bd-badge-card bd-badge-card-locked">
								<div class="bd-badge-lock">🔒</div>
								<div class="bd-badge-card-icon" style="color: #9ca3af;">
									<?php echo $badge['icon']; ?>
								</div>
								<div class="bd-badge-card-name"><?php echo esc_html( $badge['name'] ); ?></div>
								<div class="bd-badge-card-desc"><?php echo esc_html( $badge['requirement'] ); ?></div>
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
	private static function get_next_rank( $current_points ) {
		foreach ( BadgeSystem::RANKS as $threshold => $rank ) {
			if ( $current_points < $threshold ) {
				return array(
					'threshold' => $threshold,
					'rank'      => $rank,
				);
			}
		}
		return null;
	}

	/**
	 * Get current rank threshold
	 */
	private static function get_current_rank_threshold( $current_points ) {
		$current = 0;
		foreach ( BadgeSystem::RANKS as $threshold => $rank ) {
			if ( $current_points >= $threshold ) {
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
	public static function render_review_author( $review ) {
		$user_id     = $review['user_id'] ?? null;
		$author_name = $review['author_name'] ?? 'Guest';
		$created_at  = $review['created_at'] ?? '';

		ob_start();
		?>
		<div class="bd-review-author">
			<div class="bd-review-author-avatar">
				<?php if ( $user_id ) : ?>
					<?php echo get_avatar( $user_id, 48 ); ?>
				<?php else : ?>
					<div class="bd-avatar-placeholder">
						<?php echo strtoupper( substr( $author_name, 0, 1 ) ); ?>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="bd-review-author-info">
				<div class="bd-review-author-name">
					<?php echo esc_html( $author_name ); ?>
					<?php if ( $user_id ) : ?>
						<?php echo self::render_rank_badge( $user_id ); ?>
					<?php endif; ?>
				</div>
				
				<?php if ( $user_id ) : ?>
					<?php $stats = ActivityTracker::get_user_stats( $user_id ); ?>
					<div class="bd-review-author-stats">
						<?php echo number_format( $stats['total_reviews'] ); ?> reviews • 
						<?php echo number_format( $stats['helpful_votes'] ); ?> helpful votes
					</div>
					<?php echo self::render_user_badges( $user_id, 3 ); ?>
				<?php else : ?>
					<div class="bd-review-author-stats">
						Guest Reviewer
					</div>
				<?php endif; ?>
			</div>
			
			<div class="bd-review-date">
				<?php echo human_time_diff( strtotime( $created_at ), current_time( 'timestamp' ) ) . ' ago'; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render leaderboard widget
	 *
	 * @param string $period      Time period: 'all_time', 'month', 'week'.
	 * @param int    $limit       Number of users to show.
	 * @param bool   $show_header Whether to show the header (default true).
	 */
	public static function render_leaderboard( $period = 'all_time', $limit = 10, $show_header = true ) {
		$leaders = ActivityTracker::get_leaderboard( $period, $limit );

		ob_start();
		?>
		<div class="bd-leaderboard-widget">
			<?php if ( $show_header ) : ?>
			<div class="bd-leaderboard-header">
				<h3><i class="fa-solid fa-trophy"></i> Top Contributors</h3>
				<div class="bd-leaderboard-period">
					<?php
					switch ( $period ) {
						case 'week':
							echo 'This Week';
							break;
						case 'month':
							echo 'This Month';
							break;
						default:
							echo 'All Time';
					}
					?>
				</div>
			</div>
			<?php else : ?>
			<div class="bd-leaderboard-period-inline" style="text-align: right; margin-bottom: 12px;">
				<span style="font-size: 11px; font-weight: 600; color: var(--bd-navy, #1a3a4a); background: #f1f5f9; padding: 4px 10px; border-radius: 10px;">
					<?php
					switch ( $period ) {
						case 'week':
							echo 'This Week';
							break;
						case 'month':
							echo 'This Month';
							break;
						default:
							echo 'All Time';
					}
					?>
				</span>
			</div>
			<?php endif; ?>
			
			<div class="bd-leaderboard-list">
				<?php if ( empty( $leaders ) ) : ?>
					<p class="bd-leaderboard-empty">No contributors yet!</p>
				<?php else : ?>
					<?php foreach ( $leaders as $index => $leader ) : ?>
						<div class="bd-leaderboard-item">
							<div class="bd-leaderboard-rank bd-rank-<?php echo $index + 1; ?>">
								<?php
								if ( $index < 3 ) {
									echo array( '🥇', '🥈', '🥉' )[ $index ];
								} else {
									echo '#' . ( $index + 1 );
								}
								?>
							</div>
							<div class="bd-leaderboard-avatar">
								<?php echo get_avatar( $leader['user_id'], 40 ); ?>
							</div>
							<div class="bd-leaderboard-user">
								<div class="bd-leaderboard-name">
									<?php echo esc_html( $leader['display_name'] ); ?>
								</div>
								<div class="bd-leaderboard-stats">
									<?php echo number_format( $leader['total_points'] ); ?> pts • 
									<?php echo number_format( $leader['total_reviews'] ); ?> reviews
								</div>
							</div>
							<div class="bd-leaderboard-badges">
								<?php echo self::render_rank_badge( $leader['user_id'], false ); ?>
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
