<?php
/**
 * Profile Shortcode
 *
 * Displays user profile with gamification stats, badges, activity, and edit form.
 *
 * @package BusinessDirectory
 */

namespace BD\Frontend;

use BD\Gamification\ActivityTracker;
use BD\Gamification\BadgeSystem;

/**
 * Class ProfileShortcode
 */
class ProfileShortcode {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'bd_profile', array( $this, 'render_profile' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Initialize profile editor AJAX handlers.
		ProfileEditor::init();

		// Handle email verification from URL.
		add_action( 'template_redirect', array( 'BD\Frontend\ProfileEditor', 'maybe_verify_email' ) );
	}

	/**
	 * Enqueue profile assets
	 *
	 * Improved detection that works with Kadence, Elementor, and other page builders.
	 */
	public function enqueue_assets() {
		$should_load = false;

		// 1. Check by page slug.
		if ( is_page( array( 'my-profile', 'profile', 'user-profile' ) ) ) {
			$should_load = true;
		}

		// 2. Check by shortcode in content.
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bd_profile' ) ) {
			$should_load = true;
		}

		// 3. Check URL for profile indicators.
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		if ( strpos( $request_uri, '/my-profile' ) !== false
			|| strpos( $request_uri, '/profile' ) !== false ) {
			$should_load = true;
		}

		// 4. Check if this is an author archive (public profile).
		if ( is_author() ) {
			$should_load = true;
		}

		if ( $should_load ) {
			// Font Awesome 6.
			if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
				wp_enqueue_style(
					'font-awesome',
					'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
					array(),
					'6.4.0'
				);
			}

			// Google Fonts for badges.
			wp_enqueue_style(
				'bd-badge-fonts',
				'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap',
				array(),
				BD_VERSION
			);

			// Profile CSS.
			wp_enqueue_style(
				'bd-profile',
				BD_PLUGIN_URL . 'assets/css/profile.css',
				array( 'font-awesome' ),
				BD_VERSION
			);

			// Badges CSS (for badge cards).
			wp_enqueue_style(
				'bd-badges',
				BD_PLUGIN_URL . 'assets/css/badges.css',
				array( 'font-awesome', 'bd-badge-fonts' ),
				BD_VERSION
			);

			// Profile JS.
			wp_enqueue_script(
				'bd-profile',
				BD_PLUGIN_URL . 'assets/js/profile.js',
				array( 'jquery' ),
				BD_VERSION,
				true
			);

			wp_localize_script(
				'bd-profile',
				'bdProfile',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'bd_profile_nonce' ),
					'i18n'    => array(
						'saving' => __( 'Saving...', 'business-directory' ),
						'saved'  => __( 'Profile updated!', 'business-directory' ),
						'error'  => __( 'An error occurred. Please try again.', 'business-directory' ),
					),
				)
			);
		}
	}

	/**
	 * Render user profile
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_profile( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
			),
			$atts
		);

		$user_id = absint( $atts['user_id'] );

		// If no user_id and not logged in, show login prompt.
		if ( ! $user_id ) {
			return $this->render_login_prompt();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '<p>User not found.</p>';
		}

		// Check if viewing own profile.
		$is_own_profile = ( get_current_user_id() === $user_id );

		// Get real stats from database.
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Check if reviews table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);

		$reviews_count = 0;
		$helpful_votes = 0;
		$photos_count  = 0;

		if ( $table_exists ) {
			// Count APPROVED reviews by this user.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$reviews_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reviews_table} WHERE user_id = %d AND status = 'approved'",
					$user_id
				)
			);

			// Count helpful votes received.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$helpful_votes = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(helpful_count), 0) FROM {$reviews_table}
					WHERE user_id = %d AND status = 'approved'",
					$user_id
				)
			);

			// Count photos (reviews with photos).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$photos_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reviews_table}
					WHERE user_id = %d AND status = 'approved'
					AND photo_ids IS NOT NULL AND photo_ids != ''",
					$user_id
				)
			);
		}

		// Get total points from reputation table.
		$reputation_table = $wpdb->prefix . 'bd_user_reputation';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$reputation_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$reputation_table} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		$total_points = $reputation_data ? (int) $reputation_data['total_points'] : 0;
		$user_badges  = array();
		if ( $reputation_data && ! empty( $reputation_data['badges'] ) ) {
			$user_badges = json_decode( $reputation_data['badges'], true );
			if ( ! is_array( $user_badges ) ) {
				$user_badges = array();
			}
		}

		// Build stats array.
		$stats = array(
			'total_points'    => $total_points,
			'total_reviews'   => $reviews_count,
			'helpful_votes'   => $helpful_votes,
			'photos_uploaded' => $photos_count,
			'badges'          => wp_json_encode( $user_badges ),
		);

		$rank_data     = BadgeSystem::get_user_rank( $user_id );
		$rank_position = ActivityTracker::get_user_rank_position( $user_id );

		// Get badges.
		$all_badges = BadgeSystem::BADGES;

		// Get recent activity.
		$recent_activity = ActivityTracker::get_user_activity( $user_id, 10 );

		// Allowed HTML for icons.
		$allowed_icon_html = array(
			'i' => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
		);

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
						<?php if ( $is_own_profile ) : ?>
							<p class="bd-profile-email"><?php echo esc_html( $user->user_email ); ?></p>
						<?php endif; ?>
						<p class="bd-profile-member-since">
							<i class="fa-solid fa-calendar-days"></i>
							<?php
							printf(
								/* translators: %s: member since date */
								esc_html__( 'Member since %s', 'business-directory' ),
								esc_html( date_i18n( 'F Y', strtotime( $user->user_registered ) ) )
							);
							?>
						</p>
						<?php if ( $rank_data ) : ?>
							<div class="bd-profile-rank">
								<span class="bd-profile-rank-icon"><?php echo wp_kses( $rank_data['icon'], $allowed_icon_html ); ?></span>
								<span class="bd-profile-rank-name"><?php echo esc_html( $rank_data['name'] ); ?></span>
								<?php if ( $rank_position > 0 ) : ?>
									<span class="bd-profile-rank-position">#<?php echo esc_html( $rank_position ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $is_own_profile ) : ?>
						<div class="bd-profile-header-actions">
							<button type="button" class="bd-edit-profile-btn">
								<i class="fa-solid fa-pen"></i>
								<?php esc_html_e( 'Edit Profile', 'business-directory' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="bd-profile-content">
				<div class="bd-profile-main">

					<?php
					// Render edit form for own profile.
					if ( $is_own_profile ) {
						echo ProfileEditor::render_edit_form( $user_id );
					}
					?>

					<!-- Points & Progress -->
					<div class="bd-profile-card">
						<h2><i class="fa-solid fa-chart-line"></i> <?php esc_html_e( 'Points & Progress', 'business-directory' ); ?></h2>

						<div class="bd-points-summary">
							<div class="bd-points-total">
								<span class="bd-points-value"><?php echo esc_html( number_format( $stats['total_points'] ) ); ?></span>
								<span class="bd-points-label"><?php esc_html_e( 'Total Points', 'business-directory' ); ?></span>
							</div>

							<?php
							// Calculate next rank progress.
							$current_points    = $stats['total_points'];
							$next_rank         = null;
							$current_threshold = 0;

							foreach ( BadgeSystem::RANKS as $threshold => $rank_info ) {
								if ( $current_points < $threshold ) {
									$next_rank = array_merge( $rank_info, array( 'threshold' => $threshold ) );
									break;
								}
								$current_threshold = $threshold;
							}

							if ( $next_rank ) :
								$points_needed = $next_rank['threshold'] - $current_points;
								$progress      = ( ( $current_points - $current_threshold ) / ( $next_rank['threshold'] - $current_threshold ) ) * 100;
								?>
								<div class="bd-rank-progress">
									<div class="bd-progress-info">
										<span>
											<?php
											printf(
												/* translators: %s: rank name */
												esc_html__( 'Progress to %s', 'business-directory' ),
												esc_html( $next_rank['name'] )
											);
											?>
										</span>
										<span><?php echo esc_html( number_format( $points_needed ) ); ?> <?php esc_html_e( 'points to go', 'business-directory' ); ?></span>
									</div>
									<div class="bd-progress-bar">
										<div class="bd-progress-fill" style="width: <?php echo esc_attr( min( 100, $progress ) ); ?>%;"></div>
									</div>
									<p class="bd-next-rank">
										<?php esc_html_e( 'Next rank:', 'business-directory' ); ?>
										<span class="bd-next-rank-icon"><?php echo wp_kses( $next_rank['icon'], $allowed_icon_html ); ?></span>
										<?php echo esc_html( $next_rank['name'] ); ?>
									</p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Badges Section - Uses badges.css styling -->
					<div class="bd-profile-card bd-profile-badges">
						<h3><i class="fa-solid fa-medal"></i> <?php esc_html_e( 'Badges', 'business-directory' ); ?> (<?php echo esc_html( count( $user_badges ) ); ?>/<?php echo esc_html( count( $all_badges ) ); ?>)</h3>

						<?php if ( ! empty( $user_badges ) ) : ?>
							<div class="bd-badge-grid">
								<?php foreach ( $user_badges as $badge_key ) : ?>
									<?php
									if ( ! isset( $all_badges[ $badge_key ] ) ) {
										continue;
									}
									$badge  = $all_badges[ $badge_key ];
									$rarity = $badge['rarity'] ?? 'common';
									?>
									<div class="bd-badge-card bd-badge-card-earned" data-rarity="<?php echo esc_attr( $rarity ); ?>" style="border-color: <?php echo esc_attr( $badge['color'] ?? '#7a9eb8' ); ?>;">
										<div class="bd-badge-check"><i class="fa-solid fa-check-circle"></i></div>
										<div class="bd-badge-rarity bd-rarity-<?php echo esc_attr( $rarity ); ?>">
											<?php echo esc_html( ucfirst( $rarity ) ); ?>
										</div>
										<div class="bd-badge-card-icon" style="color: <?php echo esc_attr( $badge['color'] ?? '#7a9eb8' ); ?>;">
											<?php echo wp_kses( $badge['icon'], $allowed_icon_html ); ?>
										</div>
										<div class="bd-badge-card-name"><?php echo esc_html( $badge['name'] ); ?></div>
										<div class="bd-badge-card-desc"><?php echo esc_html( $badge['description'] ); ?></div>
										<?php if ( ! empty( $badge['points'] ) ) : ?>
											<div class="bd-badge-card-points"><i class="fa-solid fa-plus"></i> <?php echo esc_html( $badge['points'] ); ?> pts</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>

							<p style="margin-top: 16px; text-align: center;">
								<a href="<?php echo esc_url( home_url( '/badges/' ) ); ?>" class="bd-btn bd-btn-secondary">
									<i class="fa-solid fa-grid-2"></i> <?php esc_html_e( 'View All Badges', 'business-directory' ); ?>
								</a>
							</p>
						<?php else : ?>
							<div class="bd-empty-state">
								<div class="bd-empty-state-icon">🏆</div>
								<p class="bd-empty-state-text"><?php esc_html_e( 'No badges earned yet. Write reviews to start earning!', 'business-directory' ); ?></p>
								<p style="margin-top: 12px;">
									<a href="<?php echo esc_url( home_url( '/badges/' ) ); ?>" class="bd-btn bd-btn-secondary">
										<?php esc_html_e( 'See Available Badges', 'business-directory' ); ?>
									</a>
								</p>
							</div>
						<?php endif; ?>
					</div>

					<!-- Recent Activity -->
					<div class="bd-profile-card">
						<h2><i class="fa-solid fa-clock-rotate-left"></i> <?php esc_html_e( 'Recent Activity', 'business-directory' ); ?></h2>

						<?php if ( ! empty( $recent_activity ) ) : ?>
							<div class="bd-activity-timeline">
								<?php foreach ( $recent_activity as $activity ) : ?>
									<div class="bd-activity-item">
										<div class="bd-activity-icon">
											<?php echo esc_html( $this->get_activity_icon( $activity['activity_type'] ) ); ?>
										</div>
										<div class="bd-activity-content">
											<div class="bd-activity-text">
												<?php echo esc_html( $this->get_activity_text( $activity ) ); ?>
											</div>
											<div class="bd-activity-meta">
												<span class="bd-activity-points">+<?php echo esc_html( $activity['points'] ); ?> <?php esc_html_e( 'points', 'business-directory' ); ?></span>
												<span class="bd-activity-time">
													<?php
													echo esc_html(
														sprintf(
															/* translators: %s: human time diff */
															__( '%s ago', 'business-directory' ),
															human_time_diff( strtotime( $activity['created_at'] ), current_time( 'timestamp' ) )
														)
													);
													?>
												</span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="bd-empty-state">
								<div class="bd-empty-state-icon">📊</div>
								<p class="bd-empty-state-text"><?php esc_html_e( 'No activity yet. Start writing reviews!', 'business-directory' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

				</div>

				<!-- Sidebar -->
				<div class="bd-profile-sidebar">
					<!-- Leaderboard Widget -->
					<div class="bd-profile-card">
						<h2><i class="fa-solid fa-trophy"></i> <?php esc_html_e( 'Top Contributors', 'business-directory' ); ?></h2>
						<?php echo \BD\Frontend\BadgeDisplay::render_leaderboard( 'all_time', 5, false ); ?>
					</div>
				</div>
			</div>

		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Render login prompt
	 *
	 * @return string
	 */
	private function render_login_prompt() {
		// Use new auth system URL if available.
		$login_url    = home_url( '/login/' );
		$register_url = home_url( '/login/?tab=register' );

		ob_start();
		?>
		<div class="bd-login-prompt">
			<div class="bd-login-card">
				<h2><?php esc_html_e( 'Sign In to View Your Profile', 'business-directory' ); ?></h2>
				<p><?php esc_html_e( 'Create an account or sign in to track your reviews, earn badges, and climb the leaderboard!', 'business-directory' ); ?></p>
				<div class="bd-login-buttons">
					<a href="<?php echo esc_url( $register_url ); ?>" class="bd-btn bd-btn-primary">
						<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
					</a>
					<a href="<?php echo esc_url( $login_url ); ?>" class="bd-btn bd-btn-secondary">
						<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get activity icon
	 *
	 * @param string $type Activity type.
	 * @return string
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
			'first_login'           => '👋',
		);

		return $icons[ $type ] ?? '⭐';
	}

	/**
	 * Get activity text
	 *
	 * @param array $activity Activity data.
	 * @return string
	 */
	private function get_activity_text( $activity ) {
		$texts = array(
			'review_created'        => __( 'Wrote a review', 'business-directory' ),
			'review_with_photo'     => __( 'Added photos to review', 'business-directory' ),
			'review_detailed'       => __( 'Wrote a detailed review', 'business-directory' ),
			'helpful_vote_received' => __( 'Received a helpful vote', 'business-directory' ),
			'badge_bonus'           => __( 'Earned a new badge', 'business-directory' ),
			'list_created'          => __( 'Created a new list', 'business-directory' ),
			'business_claimed'      => __( 'Claimed a business', 'business-directory' ),
			'first_login'           => __( 'First login bonus', 'business-directory' ),
		);

		return $texts[ $activity['activity_type'] ] ?? __( 'Activity', 'business-directory' );
	}
}
