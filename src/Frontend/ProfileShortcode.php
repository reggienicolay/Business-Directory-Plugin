<?php

namespace BD\Frontend;

use BD\Gamification\ActivityTracker;
use BD\Gamification\BadgeSystem;

class ProfileShortcode {


	public function __construct() {
		add_shortcode( 'bd_profile', array( $this, 'render_profile' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue profile styles
	 */
	public function enqueue_styles() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bd_profile' ) ) {
			wp_enqueue_style(
				'bd-profile',
				BD_PLUGIN_URL . 'assets/css/profile.css',
				array(),
				BD_VERSION
			);
		}
	}

	/**
	 * Render user profile
	 */
	public function render_profile( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
			),
			$atts
		);

		$user_id = absint( $atts['user_id'] );

		// If no user_id and not logged in, show login prompt
		if ( ! $user_id ) {
			return $this->render_login_prompt();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '<p>User not found.</p>';
		}

		// ==================================================================
		// GET REAL STATS FROM DATABASE - FIXED!
		// ==================================================================
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Count APPROVED reviews by this user
		$reviews_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $reviews_table WHERE user_id = %d AND status = 'approved'",
				$user_id
			)
		);

		// Count helpful votes received
		$helpful_votes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(helpful_count) FROM $reviews_table WHERE user_id = %d AND status = 'approved'",
				$user_id
			)
		);
		$helpful_votes = $helpful_votes ? (int) $helpful_votes : 0;

		// Count photos (count reviews with photos)
		$photos_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $reviews_table WHERE user_id = %d AND status = 'approved' AND photo_ids IS NOT NULL AND photo_ids != ''",
				$user_id
			)
		);

		// Get total points from reputation table
		$reputation_table = $wpdb->prefix . 'bd_user_reputation';
		$reputation_data  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $reputation_table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		$total_points = $reputation_data ? (int) $reputation_data['total_points'] : 0;
		$user_badges  = $reputation_data && ! empty( $reputation_data['badges'] ) ? json_decode( $reputation_data['badges'], true ) : array();

		// Build stats array with REAL data
		$stats = array(
			'total_points'    => $total_points,
			'total_reviews'   => (int) $reviews_count,
			'helpful_votes'   => $helpful_votes,
			'photos_uploaded' => (int) $photos_count,
			'badges'          => json_encode( $user_badges ),
		);
		// ==================================================================

		$rank_data     = BadgeSystem::get_user_rank( $user_id );
		$rank_position = ActivityTracker::get_user_rank_position( $user_id );

		// Get badges - using BADGES constant from BadgeSystem
		$all_badges = BadgeSystem::BADGES;

		// Get recent activity
		$recent_activity = ActivityTracker::get_user_activity( $user_id, 10 );

		ob_start();
		?>

		<div class="bd-profile-page">

			<!-- Profile Header -->
			<div class="bd-profile-header">
				<div class="bd-profile-hero">
					<div class="bd-profile-avatar">
						<?php echo get_avatar( $user_id, 120 ); ?>
					</div>
					<div class="bd-profile-info">
						<h1><?php echo esc_html( $user->display_name ); ?></h1>
						<?php if ( $rank_data ) : ?>
							<div class="bd-rank-badge bd-rank-<?php echo esc_attr( strtolower( $rank_data['name'] ) ); ?>">
								<span class="bd-rank-icon"><?php echo $rank_data['icon']; ?></span>
								<span class="bd-rank-name"><?php echo esc_html( $rank_data['name'] ); ?></span>
							</div>
						<?php endif; ?>
						<p class="bd-profile-stats-line">
							<strong><?php echo number_format( $stats['total_points'] ); ?></strong> points •
							<strong><?php echo number_format( $stats['total_reviews'] ); ?></strong> reviews •
							Ranked <strong>#<?php echo number_format( $rank_position ); ?></strong>
						</p>
					</div>
				</div>
			</div>

			<!-- Stats Grid -->
			<div class="bd-stats-grid">
				<div class="bd-stat-card">
					<div class="bd-stat-icon">⭐</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['total_points'] ); ?></div>
					<div class="bd-stat-label">Total Points</div>
				</div>

				<div class="bd-stat-card">
					<div class="bd-stat-icon">📝</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['total_reviews'] ); ?></div>
					<div class="bd-stat-label">Reviews Written</div>
				</div>

				<div class="bd-stat-card">
					<div class="bd-stat-icon">👍</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['helpful_votes'] ); ?></div>
					<div class="bd-stat-label">Helpful Votes</div>
				</div>

				<div class="bd-stat-card">
					<div class="bd-stat-icon">📸</div>
					<div class="bd-stat-value"><?php echo number_format( $stats['photos_uploaded'] ); ?></div>
					<div class="bd-stat-label">Photos Shared</div>
				</div>
			</div>

			<!-- Badges Section -->
			<div class="bd-profile-section">
				<h2>🏆 Badges Earned (<?php echo count( $user_badges ); ?>)</h2>

				<?php if ( ! empty( $user_badges ) ) : ?>
					<div class="bd-badges-grid">
						<?php foreach ( $user_badges as $badge_key ) : ?>
							<?php
							if ( isset( $all_badges[ $badge_key ] ) ) :
								$badge = $all_badges[ $badge_key ];
								?>
								<div class="bd-badge-card bd-badge-card-earned" data-rarity="<?php echo esc_attr( $badge['rarity'] ); ?>">
									<div class="bd-badge-icon-large">
									<?php
																		$allowed_html = array(
																			'i' => array(
																				'class'       => array(),
																				'aria-hidden' => array(),
																			),
																		);
																		echo wp_kses( $badge['icon'], $allowed_html );
																		?>
																		</div>
									<div class="bd-badge-name"><?php echo esc_html( $badge['name'] ); ?></div>
									<div class="bd-badge-description"><?php echo esc_html( $badge['description'] ); ?></div>
									<div class="bd-badge-rarity bd-rarity-<?php echo esc_attr( $badge['rarity'] ); ?>">
										<?php echo ucfirst( $badge['rarity'] ); ?>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="bd-empty-state">
						<p>No badges earned yet. Write reviews to start earning!</p>
					</div>
				<?php endif; ?>

				<!-- Locked Badges -->
				<h3 style="margin-top: 40px;">🔒 Badges to Earn</h3>
				<div class="bd-badges-grid">
					<?php
					$locked_count = 0;
					foreach ( $all_badges as $badge_key => $badge ) :
						if ( ! in_array( $badge_key, $user_badges, true ) && $locked_count < 6 ) :
							++$locked_count;
							?>
							<div class="bd-badge-card bd-badge-locked">
								<div class="bd-badge-icon-large">🔒</div>
								<div class="bd-badge-name"><?php echo esc_html( $badge['name'] ); ?></div>
								<div class="bd-badge-description"><?php echo esc_html( $badge['description'] ); ?></div>
								<div class="bd-badge-requirement">
									<?php echo esc_html( $badge['description'] ); ?>
								</div>
							</div>
							<?php
						endif;
					endforeach;
					?>
				</div>
			</div>

			<!-- Recent Activity -->
			<div class="bd-profile-section">
				<h2>📊 Recent Activity</h2>

				<?php if ( ! empty( $recent_activity ) ) : ?>
					<div class="bd-activity-timeline">
						<?php foreach ( $recent_activity as $activity ) : ?>
							<div class="bd-activity-item">
								<div class="bd-activity-icon">
									<?php echo $this->get_activity_icon( $activity['activity_type'] ); ?>
								</div>
								<div class="bd-activity-content">
									<div class="bd-activity-text">
										<?php echo $this->get_activity_text( $activity ); ?>
									</div>
									<div class="bd-activity-meta">
										<span class="bd-activity-points">+<?php echo $activity['points']; ?> points</span>
										<span class="bd-activity-time"><?php echo human_time_diff( strtotime( $activity['created_at'] ), current_time( 'timestamp' ) ) . ' ago'; ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="bd-empty-state">
						<p>No activity yet. Start writing reviews!</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Next Rank Progress -->
			<?php
			// Calculate next rank manually using RANKS constant
			$current_points = $stats['total_points'];
			$next_rank      = null;

			foreach ( BadgeSystem::RANKS as $threshold => $rank_data ) {
				if ( $current_points < $threshold ) {
					$next_rank = array_merge( $rank_data, array( 'threshold' => $threshold ) );
					break;
				}
			}

			if ( $next_rank ) :
				$next_threshold   = $next_rank['threshold'];
				$progress_percent = min( 100, ( $current_points / $next_threshold ) * 100 );
				?>
				<div class="bd-profile-section">
					<h2>🎯 Next Rank: <?php echo esc_html( $next_rank['name'] ); ?></h2>
					<div class="bd-rank-progress">
						<div class="bd-progress-bar">
							<div class="bd-progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
						</div>
						<p class="bd-progress-text">
							<?php echo number_format( $current_points ); ?> / <?php echo number_format( $next_threshold ); ?> points
							(<?php echo number_format( $next_threshold - $current_points ); ?> more to go!)
						</p>
					</div>
				</div>
			<?php endif; ?>

		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render login prompt
	 */
	private function render_login_prompt() {
		ob_start();
		?>
		<div class="bd-login-prompt">
			<div class="bd-login-card">
				<h2>Sign In to View Your Profile</h2>
				<p>Create an account or sign in to track your reviews, earn badges, and climb the leaderboard!</p>
				<div class="bd-login-buttons">
					<a href="<?php echo home_url( '/register' ); ?>" class="bd-btn bd-btn-primary">Create Account</a>
					<a href="<?php echo wp_login_url( get_permalink() ); ?>" class="bd-btn bd-btn-secondary">Sign In</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get activity icon
	 */
	private function get_activity_icon( $type ) {
		$icons = array(
			'review_created'        => '📝',
			'review_with_photo'     => '📸',
			'review_detailed'       => '✍️',
			'helpful_vote_received' => '👍',
			'badge_bonus'           => '🏆',
			'list_created'          => '📋',
			'business_claimed'      => '🏢',
		);

		return $icons[ $type ] ?? '⭐';
	}

	/**
	 * Get activity text
	 */
	private function get_activity_text( $activity ) {
		$texts = array(
			'review_created'        => 'Wrote a review',
			'review_with_photo'     => 'Added photos to review',
			'review_detailed'       => 'Wrote a detailed review',
			'helpful_vote_received' => 'Received a helpful vote',
			'badge_bonus'           => 'Earned a new badge',
			'list_created'          => 'Created a new list',
			'business_claimed'      => 'Claimed a business',
		);

		return $texts[ $activity['activity_type'] ] ?? 'Activity';
	}
}
