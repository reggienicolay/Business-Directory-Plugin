<?php
/**
 * Unified Profile System
 *
 * Single profile class that handles both public viewing and private dashboard.
 * Premium design with cover photos, Guide features, gamification, and editing.
 *
 * Replaces: PublicProfile.php, ProfileShortcode.php
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 * @version 3.0.0
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;
use BD\Lists\ListManager;

/**
 * Class Profile
 */
class Profile {

	/**
	 * Available cover photos
	 */
	const COVER_OPTIONS = array(
		'vineyard-hills'     => 'Vineyard Hills',
		'downtown-livermore' => 'Downtown Livermore',
		'wine-barrels'       => 'Wine Barrels',
		'pleasanton-main'    => 'Pleasanton Main Street',
		'sunset-vines'       => 'Sunset Over Vines',
		'rolling-hills'      => 'Rolling Hills',
	);

	/**
	 * Initialize
	 */
	public static function init() {
		// Register shortcodes (keep both for backward compatibility).
		add_shortcode( 'bd_profile', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'bd_public_profile', array( __CLASS__, 'render_shortcode' ) );

		// Add rewrite rules for /profile/username/.
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );

		// Handle profile routing.
		add_action( 'template_redirect', array( __CLASS__, 'handle_profile_routing' ) );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Initialize profile editor AJAX handlers.
		ProfileEditor::init();

		// Handle email verification from URL.
		add_action( 'template_redirect', array( 'BD\Frontend\ProfileEditor', 'maybe_verify_email' ) );
	}

	/**
	 * Add rewrite rules for profile URLs
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule(
			'^profile/([^/]+)/?$',
			'index.php?bd_profile_user=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars.
	 * @return array Modified vars.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'bd_profile_user';
		return $vars;
	}

	/**
	 * Handle profile routing
	 * - /profile/username/ shows that user's profile
	 * - /my-profile/ redirects to /profile/current-user/ or shows login
	 */
	public static function handle_profile_routing() {
		// Handle /my-profile/ redirect (but NOT subpages like /my-profile/my-lists/).
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = parse_url( $request_uri, PHP_URL_PATH );
		$request_path = rtrim( $request_path, '/' ); // Normalize trailing slash
		
		// Only redirect exact /my-profile path, not subpages.
		if ( $request_path === '/my-profile' ) {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				wp_safe_redirect( home_url( '/profile/' . $user->user_nicename . '/' ) );
				exit;
			}
			// Not logged in - let the page render with login prompt.
			return;
		}

		// Handle /profile/username/.
		$profile_user = get_query_var( 'bd_profile_user' );

		if ( empty( $profile_user ) ) {
			return;
		}

		// Get user by login/nicename.
		$user = get_user_by( 'slug', $profile_user );
		if ( ! $user ) {
			$user = get_user_by( 'login', $profile_user );
		}

		if ( ! $user ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Set up global for template.
		set_query_var( 'bd_profile_user_id', $user->ID );

		// Load template.
		$template = locate_template( 'bd-profile.php' );
		if ( ! $template ) {
			$template = BD_PLUGIN_DIR . 'templates/profile.php';
		}

		if ( file_exists( $template ) ) {
			include $template;
			exit;
		}
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets() {
		$profile_user = get_query_var( 'bd_profile_user' );
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		
		// Check if we're on a profile page.
		$is_profile_page = ! empty( $profile_user ) 
			|| strpos( $request_uri, '/my-profile' ) !== false
			|| strpos( $request_uri, '/profile' ) !== false
			|| is_page( array( 'my-profile', 'profile' ) );

		if ( ! $is_profile_page ) {
			return;
		}

		// Design tokens.
		wp_enqueue_style(
			'bd-design-tokens',
			BD_PLUGIN_URL . 'assets/css/design-tokens.css',
			array(),
			BD_VERSION
		);

		// Font Awesome.
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

		// Unified profile styles.
		wp_enqueue_style(
			'bd-profile',
			BD_PLUGIN_URL . 'assets/css/profile.css',
			array( 'bd-design-tokens', 'font-awesome', 'bd-badge-fonts' ),
			BD_VERSION
		);

		// Profile JavaScript.
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
					'saving'       => __( 'Saving...', 'business-directory' ),
					'saved'        => __( 'Profile updated!', 'business-directory' ),
					'error'        => __( 'An error occurred. Please try again.', 'business-directory' ),
					'copied'       => __( 'Link copied!', 'business-directory' ),
					'copyError'    => __( 'Could not copy link', 'business-directory' ),
					'showMore'     => __( 'Show More', 'business-directory' ),
					'showLess'     => __( 'Show Less', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Render shortcode (backward compatible)
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'user'    => '',
				'user_id' => 0,
			),
			$atts
		);

		// If on my-profile page and not logged in, show login prompt.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $request_uri, '/my-profile' ) !== false && ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		// Get user.
		$user = null;
		if ( ! empty( $atts['user'] ) ) {
			$user = get_user_by( 'slug', $atts['user'] );
			if ( ! $user ) {
				$user = get_user_by( 'login', $atts['user'] );
			}
		} elseif ( ! empty( $atts['user_id'] ) ) {
			$user = get_userdata( absint( $atts['user_id'] ) );
		} elseif ( is_user_logged_in() ) {
			$user = wp_get_current_user();
		}

		if ( ! $user ) {
			return self::render_login_prompt();
		}

		return self::render_profile( $user->ID );
	}

	/**
	 * Render unified profile
	 *
	 * @param int $user_id User ID.
	 * @return string HTML output.
	 */
	public static function render_profile( $user_id ) {
		$user_id = absint( $user_id ); // Ensure integer
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return '<p>' . esc_html__( 'User not found.', 'business-directory' ) . '</p>';
		}

		$current_user   = get_current_user_id();
		$is_own_profile = ( $current_user === $user_id ); // Both are now integers

		// Check profile visibility.
		$visibility = get_user_meta( $user_id, 'bd_profile_visibility', true );

		// Privacy check (skip for own profile).
		if ( ! $is_own_profile ) {
			if ( 'private' === $visibility ) {
				return self::render_private_notice();
			}
			if ( 'members' === $visibility && ! is_user_logged_in() ) {
				return self::render_members_only_notice();
			}
		}

		// Get all profile data - pass $user to avoid duplicate get_userdata call.
		$data = self::get_profile_data( $user_id, $user );

		// Check class existence once.
		$has_badge_system     = class_exists( 'BD\Gamification\BadgeSystem' );
		$has_activity_tracker = class_exists( 'BD\Gamification\ActivityTracker' );

		// Get gamification data for progress section.
		$rank_data       = $has_badge_system ? BadgeSystem::get_user_rank( $user_id ) : null;
		$rank_position   = $has_activity_tracker ? ActivityTracker::get_user_rank_position( $user_id ) : 0;
		$recent_activity = $has_activity_tracker ? ActivityTracker::get_user_activity( $user_id, 10 ) : array();

		// Section visibility settings.
		$show_badges  = self::should_show_section( $user_id, 'badges', $is_own_profile );
		$show_reviews = self::should_show_section( $user_id, 'reviews', $is_own_profile );
		$show_lists   = self::should_show_section( $user_id, 'lists', $is_own_profile );
		$show_stats   = self::should_show_section( $user_id, 'stats', $is_own_profile );

		// Allowed HTML for icons.
		$allowed_icon_html = array(
			'i' => array(
				'class'       => array(),
				'aria-hidden' => array(),
			),
		);

		ob_start();
		?>
		<div class="bd-public-profile bd-profile <?php echo esc_attr( $data['is_guide'] ? 'is-guide' : '' ); ?> <?php echo esc_attr( $is_own_profile ? 'is-own-profile' : '' ); ?>">

			<?php // Visibility notice for profile owner. ?>
			<?php if ( $is_own_profile && ! empty( $visibility ) && 'public' !== $visibility ) : ?>
				<div class="bd-profile-visibility-notice">
					<i class="fas fa-eye-slash"></i>
					<?php if ( 'private' === $visibility ) : ?>
						<?php esc_html_e( 'Your profile is private. Only you can see this page.', 'business-directory' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Your profile is visible to members only.', 'business-directory' ); ?>
					<?php endif; ?>
					<button type="button" class="bd-edit-profile-btn bd-btn-link">
						<?php esc_html_e( 'Change settings', 'business-directory' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Hero Section with Cover Photo -->
			<div class="bd-profile-hero">
				<?php echo self::render_cover_photo( $data ); ?>
				
				<div class="bd-profile-hero-content">
					<div class="bd-profile-header">
						<!-- Avatar -->
						<div class="bd-profile-avatar">
							<div class="bd-profile-avatar-wrapper">
								<?php echo get_avatar( $user_id, 140 ); ?>
							</div>
							<?php if ( $data['is_guide'] ) : ?>
								<span class="bd-guide-badge" title="<?php esc_attr_e( 'Community Guide', 'business-directory' ); ?>">
									<i class="fas fa-user-shield"></i>
								</span>
							<?php endif; ?>
						</div>

						<!-- Profile Info -->
						<div class="bd-profile-info">
							<h1 class="bd-profile-name">
								<?php echo esc_html( $user->display_name ); ?>
								<?php if ( $data['is_guide'] ) : ?>
									<span class="bd-verified-badge" title="<?php esc_attr_e( 'Verified Community Guide', 'business-directory' ); ?>">
										<i class="fas fa-circle-check"></i>
									</span>
								<?php endif; ?>
							</h1>

							<?php if ( $data['guide_title'] ) : ?>
								<p class="bd-profile-title">
									<i class="fas fa-award"></i>
									<?php echo esc_html( $data['guide_title'] ); ?>
								</p>
							<?php endif; ?>

							<div class="bd-profile-meta">
								<?php if ( ! empty( $data['guide_cities'] ) && is_array( $data['guide_cities'] ) ) : ?>
									<span class="bd-profile-meta-item">
										<i class="fas fa-map-marker-alt"></i>
										<?php echo esc_html( implode( ' · ', $data['guide_cities'] ) ); ?>
									</span>
								<?php elseif ( $data['city'] ) : ?>
									<span class="bd-profile-meta-item">
										<i class="fas fa-map-marker-alt"></i>
										<?php echo esc_html( $data['city'] ); ?>
									</span>
								<?php endif; ?>

								<span class="bd-profile-meta-item">
									<i class="fas fa-calendar-alt"></i>
									<?php if ( $data['is_guide'] ) : ?>
										<?php
										printf(
											/* translators: %s: date */
											esc_html__( 'Trusted Guide since %s', 'business-directory' ),
											esc_html( date_i18n( 'F Y', strtotime( $user->user_registered ) ) )
										);
										?>
									<?php else : ?>
										<?php
										printf(
											/* translators: %s: date */
											esc_html__( 'Joined %s', 'business-directory' ),
											esc_html( date_i18n( 'F Y', strtotime( $user->user_registered ) ) )
										);
										?>
									<?php endif; ?>
								</span>

								<?php if ( $rank_data ) : ?>
									<span class="bd-profile-meta-item bd-profile-rank">
										<span class="bd-rank-icon"><?php echo wp_kses( $rank_data['icon'], $allowed_icon_html ); ?></span>
										<?php echo esc_html( $rank_data['name'] ); ?>
										<?php if ( $rank_position > 0 ) : ?>
											<span class="bd-rank-position">#<?php echo esc_html( $rank_position ); ?></span>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</div>

							<!-- Social Links -->
							<?php if ( ! empty( array_filter( $data['social_links'] ) ) ) : ?>
								<div class="bd-profile-social">
									<?php foreach ( $data['social_links'] as $network => $url ) : ?>
										<?php if ( ! empty( $url ) ) : ?>
											<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="bd-social-link" title="<?php echo esc_attr( ucfirst( $network ) ); ?>">
												<i class="fa-brands fa-<?php echo esc_attr( $network ); ?>"></i>
											</a>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<!-- Header Actions (own profile only) -->
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
			</div>

			<!-- Profile Content -->
			<div class="bd-profile-content">

				<?php // Stats Row (always visible). ?>
				<?php if ( $show_stats ) : ?>
				<div class="bd-profile-stats-row">
					<section class="bd-profile-stats">
						<div class="bd-stat-item">
							<span class="bd-stat-value"><?php echo esc_html( number_format( $data['stats']['reviews'] ) ); ?></span>
							<span class="bd-stat-label"><?php esc_html_e( 'Reviews', 'business-directory' ); ?></span>
						</div>
						<div class="bd-stat-item">
							<span class="bd-stat-value"><?php echo esc_html( number_format( $data['stats']['lists'] ) ); ?></span>
							<span class="bd-stat-label"><?php esc_html_e( 'Lists', 'business-directory' ); ?></span>
						</div>
						<div class="bd-stat-item">
							<span class="bd-stat-value"><?php echo esc_html( number_format( $data['stats']['points'] ) ); ?></span>
							<span class="bd-stat-label"><?php esc_html_e( 'Points', 'business-directory' ); ?></span>
						</div>
						<div class="bd-stat-item">
							<span class="bd-stat-value"><?php echo esc_html( number_format( $data['stats']['helpful'] ) ); ?></span>
							<span class="bd-stat-label"><?php esc_html_e( 'Helpful', 'business-directory' ); ?></span>
						</div>
					</section>

					<?php 
					// Guide quote.
					$quote = isset( $data['guide_quote'] ) ? trim( $data['guide_quote'] ) : '';
					$is_real_quote = ! empty( $quote ) 
						&& strtolower( $quote ) !== '*quote*' 
						&& strtolower( $quote ) !== 'quote' 
						&& strlen( $quote ) > 10;
					?>
					<?php if ( $data['is_guide'] && $is_real_quote ) : ?>
						<section class="bd-profile-tagline">
							<blockquote>
								<span class="bd-quote-icon"><i class="fas fa-quote-left"></i></span>
								<?php echo esc_html( $quote ); ?>
							</blockquote>
						</section>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<!-- Guide Impact Banner -->
				<?php if ( $data['is_guide'] ) : ?>
					<div class="bd-guide-impact-banner">
						<div class="bd-guide-impact-icon">
							<i class="fas fa-shield-halved"></i>
						</div>
						<div class="bd-guide-impact-content">
							<span class="bd-guide-impact-label"><?php esc_html_e( 'Community Guide', 'business-directory' ); ?></span>
							<span class="bd-guide-impact-text">
								<?php
								printf(
									/* translators: %1$s: reviews count, %2$s: helpful votes */
									esc_html__( 'Trusted local expert with %1$s reviews and %2$s helpful votes', 'business-directory' ),
									'<strong>' . esc_html( number_format( $data['stats']['reviews'] ) ) . '</strong>',
									'<strong>' . esc_html( number_format( $data['stats']['helpful'] ) ) . '</strong>'
								);
								?>
							</span>
						</div>
						<div class="bd-guide-impact-badge">
							<i class="fas fa-check-circle"></i>
							<?php esc_html_e( 'Verified', 'business-directory' ); ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Main Content Grid -->
				<div class="bd-profile-grid">

					<!-- Left Column: Main Content -->
					<div class="bd-profile-main">

						<?php // Edit Form (own profile only). ?>
						<?php if ( $is_own_profile ) : ?>
							<?php echo ProfileEditor::render_edit_form( $user_id ); ?>
						<?php endif; ?>

						<!-- About Section -->
						<?php $bio = ! empty( $data['public_bio'] ) ? $data['public_bio'] : $data['bio']; ?>
						<?php if ( ! empty( $bio ) ) : ?>
							<section class="bd-profile-section">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-user"></i></div>
									<h2 class="bd-profile-section-title"><?php esc_html_e( 'About', 'business-directory' ); ?></h2>
								</div>
								<div class="bd-profile-bio">
									<?php echo wp_kses_post( wpautop( $bio ) ); ?>
								</div>

								<?php if ( ! empty( $data['expertise'] ) ) : ?>
									<div class="bd-profile-expertise">
										<?php foreach ( $data['expertise'] as $category ) : ?>
											<span class="bd-expertise-tag">
												<i class="<?php echo esc_attr( $category['icon'] ); ?>"></i>
												<?php echo esc_html( $category['name'] ); ?>
											</span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</section>
						<?php endif; ?>

						<!-- Points & Progress (own profile only) -->
						<?php if ( $is_own_profile && class_exists( 'BD\Gamification\BadgeSystem' ) ) : ?>
							<section class="bd-profile-section bd-profile-progress">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-chart-line"></i></div>
									<h2 class="bd-profile-section-title"><?php esc_html_e( 'Points & Progress', 'business-directory' ); ?></h2>
								</div>

								<div class="bd-points-summary">
									<div class="bd-points-total">
										<span class="bd-points-value"><?php echo esc_html( number_format( $data['stats']['points'] ) ); ?></span>
										<span class="bd-points-label"><?php esc_html_e( 'Total Points', 'business-directory' ); ?></span>
									</div>

									<?php
									// Calculate next rank progress.
									$current_points    = $data['stats']['points'];
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
										$progress      = ( $next_rank['threshold'] - $current_threshold ) > 0 
											? ( ( $current_points - $current_threshold ) / ( $next_rank['threshold'] - $current_threshold ) ) * 100 
											: 0;
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
							</section>
						<?php endif; ?>

						<!-- Badges Section -->
						<?php if ( $show_badges && ! empty( $data['badges'] ) ) : ?>
							<section class="bd-profile-section bd-profile-badges">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-trophy"></i></div>
									<h2 class="bd-profile-section-title"><?php esc_html_e( 'Badges Earned', 'business-directory' ); ?></h2>
									<span class="bd-badge-count"><?php echo count( $data['badges'] ); ?></span>
								</div>
								<div class="bd-badge-grid">
									<?php foreach ( $data['badges'] as $badge ) : ?>
										<?php
										$rarity = $badge['rarity'] ?? 'common';
										$color  = $badge['color'] ?? '#7a9eb8';
										?>
										<div class="bd-badge-card bd-badge-card-earned" data-rarity="<?php echo esc_attr( $rarity ); ?>" style="border-color: <?php echo esc_attr( $color ); ?>;">
											<div class="bd-badge-check"><i class="fa-solid fa-check-circle"></i></div>
											<div class="bd-badge-rarity bd-rarity-<?php echo esc_attr( $rarity ); ?>">
												<?php echo esc_html( ucfirst( $rarity ) ); ?>
											</div>
											<div class="bd-badge-card-icon" style="color: <?php echo esc_attr( $color ); ?>;">
												<?php echo $badge['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</div>
											<div class="bd-badge-card-name"><?php echo esc_html( $badge['name'] ); ?></div>
											<div class="bd-badge-card-desc"><?php echo esc_html( $badge['description'] ); ?></div>
											<?php if ( ! empty( $badge['points'] ) ) : ?>
												<div class="bd-badge-card-points"><i class="fa-solid fa-plus"></i> <?php echo esc_html( $badge['points'] ); ?> pts</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="bd-section-footer">
									<a href="<?php echo esc_url( home_url( '/badges/' ) ); ?>" class="bd-btn bd-btn-secondary">
										<i class="fa-solid fa-grid-2"></i> <?php esc_html_e( 'View All Badges', 'business-directory' ); ?>
									</a>
								</p>
							</section>
						<?php endif; ?>

						<!-- Curated Lists -->
						<?php if ( $show_lists && ! empty( $data['lists'] ) ) : ?>
							<section class="bd-profile-section">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-list"></i></div>
									<h2 class="bd-profile-section-title">
										<?php echo $data['is_guide'] ? esc_html__( "Guide's Top Picks", 'business-directory' ) : esc_html__( 'Curated Lists', 'business-directory' ); ?>
									</h2>
									<a href="<?php echo esc_url( home_url( '/lists/?user=' . $user->user_nicename ) ); ?>" class="bd-profile-section-action">
										<?php esc_html_e( 'View All', 'business-directory' ); ?>
										<i class="fas fa-arrow-right"></i>
									</a>
								</div>
								<div class="bd-lists-grid">
									<?php foreach ( array_slice( $data['lists'], 0, 3 ) as $list ) : ?>
										<?php
										if ( ! is_array( $list ) || ! isset( $list['id'] ) ) {
											continue;
										}
										$list_url   = home_url( '/lists/?list=' . ( isset( $list['slug'] ) ? $list['slug'] : $list['id'] ) );
										$item_count = isset( $list['item_count'] ) ? $list['item_count'] : 0;
										?>
										<a href="<?php echo esc_url( $list_url ); ?>" class="bd-list-card">
											<?php if ( ! empty( $list['cover_image'] ) ) : ?>
												<div class="bd-list-cover" style="background-image: url('<?php echo esc_url( $list['cover_image'] ); ?>');">
													<span class="bd-list-cover-count"><?php echo esc_html( $item_count ); ?> places</span>
												</div>
											<?php else : ?>
												<div class="bd-list-cover bd-list-cover-default">
													<i class="fas fa-list"></i>
													<span class="bd-list-cover-count"><?php echo esc_html( $item_count ); ?> places</span>
												</div>
											<?php endif; ?>
											<div class="bd-list-info">
												<h3><?php echo esc_html( isset( $list['title'] ) ? $list['title'] : '' ); ?></h3>
											</div>
										</a>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endif; ?>

						<!-- Recent Reviews -->
						<?php if ( $show_reviews && ! empty( $data['recent_reviews'] ) && is_array( $data['recent_reviews'] ) ) : ?>
							<section class="bd-profile-section">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-star"></i></div>
									<h2 class="bd-profile-section-title"><?php esc_html_e( 'Recent Reviews', 'business-directory' ); ?></h2>
								</div>
								<div class="bd-reviews-list bd-collapsed">
									<?php foreach ( $data['recent_reviews'] as $review ) : ?>
										<?php if ( ! is_array( $review ) ) { continue; } ?>
										<div class="bd-review-card">
											<div class="bd-review-header">
												<?php if ( ! empty( $review['business_image'] ) ) : ?>
													<img src="<?php echo esc_url( $review['business_image'] ); ?>" alt="" class="bd-review-business-img">
												<?php else : ?>
													<div class="bd-review-business-img bd-review-business-img-placeholder">
														<i class="fas fa-store"></i>
													</div>
												<?php endif; ?>
												<div class="bd-review-business-info">
													<a href="<?php echo esc_url( isset( $review['business_url'] ) ? $review['business_url'] : '#' ); ?>" class="bd-review-business-name">
														<?php echo esc_html( isset( $review['business_name'] ) ? $review['business_name'] : '' ); ?>
													</a>
													<div class="bd-review-rating">
														<?php echo self::render_stars( isset( $review['rating'] ) ? $review['rating'] : 0 ); ?>
													</div>
												</div>
											</div>
											<?php if ( ! empty( $review['content'] ) ) : ?>
												<p class="bd-review-text"><?php echo esc_html( wp_trim_words( $review['content'], 30 ) ); ?></p>
											<?php endif; ?>
											<div class="bd-review-footer">
												<span class="bd-review-date">
													<?php
													if ( isset( $review['created_at'] ) ) {
														echo esc_html( human_time_diff( strtotime( $review['created_at'] ) ) ) . ' ago';
													}
													?>
												</span>
												<?php if ( ! empty( $review['helpful_count'] ) && $review['helpful_count'] > 0 ) : ?>
													<span class="bd-review-helpful">
														<i class="fas fa-thumbs-up"></i>
														<?php echo esc_html( $review['helpful_count'] ); ?> helpful
													</span>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<?php if ( count( $data['recent_reviews'] ) > 3 ) : ?>
									<button type="button" class="bd-reviews-toggle">
										<i class="fas fa-chevron-down"></i>
										<span>
											<?php
											printf(
												/* translators: %d: number of hidden reviews */
												esc_html__( 'Show %d More Reviews', 'business-directory' ),
												count( $data['recent_reviews'] ) - 3
											);
											?>
										</span>
									</button>
								<?php endif; ?>
							</section>
						<?php endif; ?>

						<!-- Recent Activity (own profile only) -->
						<?php if ( $is_own_profile && ! empty( $recent_activity ) ) : ?>
							<section class="bd-profile-section">
								<div class="bd-profile-section-header">
									<div class="bd-profile-section-icon"><i class="fas fa-clock-rotate-left"></i></div>
									<h2 class="bd-profile-section-title"><?php esc_html_e( 'Recent Activity', 'business-directory' ); ?></h2>
								</div>
								<div class="bd-activity-timeline">
									<?php foreach ( $recent_activity as $activity ) : ?>
										<div class="bd-activity-item">
											<div class="bd-activity-icon">
												<?php echo esc_html( self::get_activity_icon( $activity['activity_type'] ) ); ?>
											</div>
											<div class="bd-activity-content">
												<div class="bd-activity-text">
													<?php echo esc_html( self::get_activity_text( $activity ) ); ?>
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
							</section>
						<?php endif; ?>

					</div><!-- .bd-profile-main -->

					<!-- Right Column: Sidebar (own profile only) -->
					<?php if ( $is_own_profile ) : ?>
					<div class="bd-profile-sidebar">
						<!-- Leaderboard Widget -->
						<section class="bd-profile-section">
							<div class="bd-profile-section-header">
								<div class="bd-profile-section-icon"><i class="fas fa-trophy"></i></div>
								<h2 class="bd-profile-section-title"><?php esc_html_e( 'Top Contributors', 'business-directory' ); ?></h2>
							</div>
							<?php 
							if ( class_exists( 'BD\Frontend\BadgeDisplay' ) ) {
								echo \BD\Frontend\BadgeDisplay::render_leaderboard( 'all_time', 5, false );
							}
							?>
						</section>
					</div>
					<?php endif; ?>

				</div><!-- .bd-profile-grid -->

				<!-- Profile Footer -->
				<footer class="bd-profile-footer">
					<i class="fas fa-heart"></i>
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__( 'Member of %s', 'business-directory' ),
						esc_html( get_bloginfo( 'name' ) )
					);
					?>
				</footer>

			</div><!-- .bd-profile-content -->

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if section should be shown
	 *
	 * @param int    $user_id        User ID.
	 * @param string $section        Section name (badges, reviews, lists, stats).
	 * @param bool   $is_own_profile Whether viewing own profile.
	 * @return bool
	 */
	private static function should_show_section( $user_id, $section, $is_own_profile ) {
		// Always show all sections on own profile.
		if ( $is_own_profile ) {
			return true;
		}

		// Check user preference.
		$meta_key = 'bd_profile_show_' . $section;
		$value    = get_user_meta( $user_id, $meta_key, true );

		// Default to true if not set.
		return '' === $value || (bool) $value;
	}

	/**
	 * Get profile data
	 *
	 * @param int          $user_id User ID.
	 * @param WP_User|null $user    Optional user object to avoid duplicate query.
	 * @return array Profile data.
	 */
	public static function get_profile_data( $user_id, $user = null ) {
		if ( ! $user ) {
			$user = get_userdata( $user_id );
		}

		$data = array(
			'user_id'      => $user_id,
			'display_name' => $user->display_name,
			'bio'          => get_user_meta( $user_id, 'description', true ),
			'public_bio'   => get_user_meta( $user_id, 'bd_public_bio', true ),
			'city'         => get_user_meta( $user_id, 'bd_city', true ),

			// Guide fields.
			'is_guide'     => (bool) get_user_meta( $user_id, 'bd_is_guide', true ),
			'guide_title'  => get_user_meta( $user_id, 'bd_guide_title', true ),
			'guide_quote'  => get_user_meta( $user_id, 'bd_guide_quote', true ),
			'guide_cities' => get_user_meta( $user_id, 'bd_guide_cities', true ) ?: array(),
			'cover_photo'  => get_user_meta( $user_id, 'bd_cover_photo', true ),

			// Social links.
			'social_links' => array(
				'instagram' => self::get_social_url( 'instagram', get_user_meta( $user_id, 'bd_instagram', true ) ),
				'facebook'  => get_user_meta( $user_id, 'bd_facebook', true ),
				'twitter'   => self::get_social_url( 'twitter', get_user_meta( $user_id, 'bd_twitter', true ) ),
				'linkedin'  => get_user_meta( $user_id, 'bd_linkedin', true ),
			),

			// Data arrays.
			'stats'          => array(),
			'badges'         => array(),
			'lists'          => array(),
			'recent_reviews' => array(),
			'expertise'      => array(),
		);

		// Get stats.
		$data['stats'] = self::get_user_stats( $user_id );

		// Get badges.
		$data['badges'] = self::get_user_badges_with_data( $user_id );

		// Get public lists.
		if ( class_exists( 'BD\Lists\ListManager' ) ) {
			$lists = ListManager::get_user_lists( $user_id );
			if ( is_array( $lists ) ) {
				$data['lists'] = array();
				foreach ( $lists as $list ) {
					if ( is_array( $list ) && isset( $list['visibility'] ) && 'public' === $list['visibility'] ) {
						if ( ! empty( $list['cover_image_id'] ) ) {
							$list['cover_image'] = wp_get_attachment_image_url( $list['cover_image_id'], 'medium' );
						}
						$data['lists'][] = $list;
					}
				}
			}
		}

		// Get recent reviews.
		$data['recent_reviews'] = self::get_user_reviews( $user_id, 10 );

		// Get expertise.
		$data['expertise'] = self::get_user_expertise( $user_id );

		return $data;
	}

	/**
	 * Cache for table existence checks
	 *
	 * @var array
	 */
	private static $table_cache = array();

	/**
	 * Check if a database table exists (cached)
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if table exists.
	 */
	private static function table_exists( $table_name ) {
		if ( isset( self::$table_cache[ $table_name ] ) ) {
			return self::$table_cache[ $table_name ];
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		self::$table_cache[ $table_name ] = (bool) $exists;
		return self::$table_cache[ $table_name ];
	}

	/**
	 * Get user stats - optimized single query
	 *
	 * @param int $user_id User ID.
	 * @return array Stats.
	 */
	private static function get_user_stats( $user_id ) {
		global $wpdb;

		$stats = array(
			'reviews' => 0,
			'photos'  => 0,
			'lists'   => 0,
			'points'  => 0,
			'helpful' => 0,
		);

		$reviews_table = $wpdb->prefix . 'bd_reviews';

		if ( self::table_exists( $reviews_table ) ) {
			// Single query for review stats.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$review_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						COUNT(*) as reviews,
						COALESCE(SUM(helpful_count), 0) as helpful,
						SUM(CASE WHEN photo_ids IS NOT NULL AND photo_ids != '' THEN 1 ELSE 0 END) as photos
					FROM {$reviews_table} 
					WHERE user_id = %d AND status = 'approved'",
					$user_id
				),
				ARRAY_A
			);

			if ( $review_stats ) {
				$stats['reviews'] = (int) $review_stats['reviews'];
				$stats['helpful'] = (int) $review_stats['helpful'];
				$stats['photos']  = (int) $review_stats['photos'];
			}
		}

		// Lists count.
		$lists_table = $wpdb->prefix . 'bd_lists';

		if ( self::table_exists( $lists_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['lists'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$lists_table} WHERE user_id = %d AND visibility = 'public'",
					$user_id
				)
			);
		}

		// Points.
		$reputation_table = $wpdb->prefix . 'bd_user_reputation';

		if ( self::table_exists( $reputation_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['points'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT total_points FROM {$reputation_table} WHERE user_id = %d",
					$user_id
				)
			);
		}

		return $stats;
	}

	/**
	 * Get user badges with full data
	 *
	 * @param int $user_id User ID.
	 * @return array Array of badge data.
	 */
	private static function get_user_badges_with_data( $user_id ) {
		if ( ! class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			return array();
		}

		$badge_keys = BadgeSystem::get_user_badges( $user_id );

		if ( empty( $badge_keys ) || ! is_array( $badge_keys ) ) {
			return array();
		}

		$all_badges       = BadgeSystem::BADGES;
		$badges_with_data = array();

		foreach ( $badge_keys as $badge_key ) {
			if ( is_array( $badge_key ) ) {
				$badges_with_data[] = $badge_key;
				continue;
			}

			if ( isset( $all_badges[ $badge_key ] ) ) {
				$badges_with_data[] = array_merge(
					array( 'key' => $badge_key ),
					$all_badges[ $badge_key ]
				);
			}
		}

		return $badges_with_data;
	}

	/**
	 * Get user reviews
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of reviews.
	 * @return array Reviews.
	 */
	private static function get_user_reviews( $user_id, $limit = 10 ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		if ( ! self::table_exists( $reviews_table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, p.post_title as business_name
				FROM {$reviews_table} r
				LEFT JOIN {$wpdb->posts} p ON r.business_id = p.ID
				WHERE r.user_id = %d AND r.status = 'approved'
				ORDER BY r.created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $reviews ) ) {
			return array();
		}

		// Batch fetch business IDs for priming caches.
		$business_ids = array_unique( array_filter( array_column( $reviews, 'business_id' ) ) );
		
		if ( ! empty( $business_ids ) ) {
			// Prime post cache to reduce individual queries.
			_prime_post_caches( $business_ids, true, true );
		}

		// Now get_permalink and get_the_post_thumbnail_url will use cached data.
		foreach ( $reviews as &$review ) {
			$review['business_url']   = get_permalink( $review['business_id'] );
			$review['business_image'] = get_the_post_thumbnail_url( $review['business_id'], 'thumbnail' );
		}

		return $reviews;
	}

	/**
	 * Get user expertise (top reviewed categories)
	 *
	 * @param int $user_id User ID.
	 * @return array Expertise categories.
	 */
	private static function get_user_expertise( $user_id ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		if ( ! self::table_exists( $reviews_table ) ) {
			return array();
		}

		// Single query to get category counts directly from term_relationships.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$category_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, t.slug, COUNT(*) as count
				FROM {$reviews_table} r
				INNER JOIN {$wpdb->term_relationships} tr ON r.business_id = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE r.user_id = %d 
				AND r.status = 'approved'
				AND tt.taxonomy = 'bd_category'
				GROUP BY t.term_id
				ORDER BY count DESC
				LIMIT 5",
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $category_data ) ) {
			return array();
		}

		$category_icons = array(
			'restaurants'   => 'fas fa-utensils',
			'dining'        => 'fas fa-utensils',
			'wineries'      => 'fas fa-wine-glass-alt',
			'wine'          => 'fas fa-wine-glass-alt',
			'shopping'      => 'fas fa-shopping-bag',
			'entertainment' => 'fas fa-ticket-alt',
			'services'      => 'fas fa-concierge-bell',
			'outdoors'      => 'fas fa-hiking',
			'family'        => 'fas fa-child',
			'cafes'         => 'fas fa-coffee',
			'bars'          => 'fas fa-glass-martini-alt',
			'breweries'     => 'fas fa-beer',
		);

		$expertise = array();
		foreach ( $category_data as $cat ) {
			$icon = 'fas fa-tag';
			foreach ( $category_icons as $key => $icon_class ) {
				if ( stripos( $cat['slug'], $key ) !== false ) {
					$icon = $icon_class;
					break;
				}
			}
			$expertise[] = array(
				'name' => $cat['name'],
				'slug' => $cat['slug'],
				'icon' => $icon,
			);
		}

		return $expertise;
	}

	/**
	 * Render cover photo
	 *
	 * @param array $data Profile data.
	 * @return string HTML output.
	 */
	private static function render_cover_photo( $data ) {
		$cover_key = isset( $data['cover_photo'] ) ? $data['cover_photo'] : '';

		if ( ! $data['is_guide'] || empty( $cover_key ) ) {
			return '<div class="bd-profile-cover bd-cover-default"></div>';
		}

		$cover_url = BD_PLUGIN_URL . 'assets/images/covers/' . $cover_key . '.jpg';

		return sprintf(
			'<div class="bd-profile-cover" style="background-image: url(\'%s\');"></div>',
			esc_url( $cover_url )
		);
	}

	/**
	 * Render star rating
	 *
	 * @param float $rating Rating value.
	 * @return string HTML stars.
	 */
	private static function render_stars( $rating ) {
		$output     = '<span class="bd-review-rating">';
		$full_stars = floor( $rating );
		$half_star  = ( $rating - $full_stars ) >= 0.5;

		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $i <= $full_stars ) {
				$output .= '<i class="fas fa-star"></i>';
			} elseif ( $half_star && $i === $full_stars + 1 ) {
				$output .= '<i class="fas fa-star-half-alt"></i>';
			} else {
				$output .= '<i class="far fa-star empty"></i>';
			}
		}

		$output .= '</span>';
		return $output;
	}

	/**
	 * Get social URL
	 *
	 * @param string $network Network name.
	 * @param string $value   Handle or URL.
	 * @return string Full URL.
	 */
	private static function get_social_url( $network, $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( strpos( $value, 'http' ) === 0 ) {
			return $value;
		}

		switch ( $network ) {
			case 'instagram':
				return 'https://instagram.com/' . ltrim( $value, '@' );
			case 'twitter':
				return 'https://x.com/' . ltrim( $value, '@' );
			default:
				return $value;
		}
	}

	/**
	 * Get activity icon
	 *
	 * @param string $type Activity type.
	 * @return string Emoji.
	 */
	private static function get_activity_icon( $type ) {
		$icons = array(
			'review_created'        => '📝',
			'review_with_photo'     => '📸',
			'review_detailed'       => '✍️',
			'helpful_vote_received' => '👍',
			'badge_bonus'           => '🏆',
			'list_created'          => '📋',
			'business_claimed'      => '🏢',
			'first_login'           => '👋',
			'first_review_day'      => '🌟',
			'profile_completed'     => '✅',
		);

		return $icons[ $type ] ?? '⭐';
	}

	/**
	 * Get activity text
	 *
	 * @param array $activity Activity data.
	 * @return string Activity description.
	 */
	private static function get_activity_text( $activity ) {
		$texts = array(
			'review_created'        => __( 'Wrote a review', 'business-directory' ),
			'review_with_photo'     => __( 'Added photos to review', 'business-directory' ),
			'review_detailed'       => __( 'Wrote a detailed review', 'business-directory' ),
			'helpful_vote_received' => __( 'Received a helpful vote', 'business-directory' ),
			'badge_bonus'           => __( 'Earned a new badge', 'business-directory' ),
			'list_created'          => __( 'Created a new list', 'business-directory' ),
			'business_claimed'      => __( 'Claimed a business', 'business-directory' ),
			'first_login'           => __( 'First login bonus', 'business-directory' ),
			'first_review_day'      => __( 'First review of the day', 'business-directory' ),
			'profile_completed'     => __( 'Completed profile', 'business-directory' ),
		);

		return $texts[ $activity['activity_type'] ] ?? __( 'Activity', 'business-directory' );
	}

	/**
	 * Render login prompt
	 *
	 * @return string HTML output.
	 */
	private static function render_login_prompt() {
		$login_url    = home_url( '/login/' );
		$register_url = home_url( '/login/?tab=register' );

		ob_start();
		?>
		<div class="bd-login-prompt">
			<div class="bd-login-card">
				<div class="bd-login-icon">
					<i class="fas fa-user-circle"></i>
				</div>
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
	 * Render private profile notice
	 *
	 * @return string HTML notice.
	 */
	private static function render_private_notice() {
		ob_start();
		?>
		<div class="bd-profile-restricted">
			<div class="bd-restricted-icon">
				<i class="fas fa-lock"></i>
			</div>
			<h2><?php esc_html_e( 'Private Profile', 'business-directory' ); ?></h2>
			<p><?php esc_html_e( 'This profile is private and cannot be viewed.', 'business-directory' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bd-btn bd-btn-primary">
				<?php esc_html_e( 'Back to Home', 'business-directory' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render members-only profile notice
	 *
	 * @return string HTML notice.
	 */
	private static function render_members_only_notice() {
		ob_start();
		?>
		<div class="bd-profile-restricted">
			<div class="bd-restricted-icon">
				<i class="fas fa-users"></i>
			</div>
			<h2><?php esc_html_e( 'Members Only', 'business-directory' ); ?></h2>
			<p><?php esc_html_e( 'You must be logged in to view this profile.', 'business-directory' ); ?></p>
			<div class="bd-restricted-actions">
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="bd-btn bd-btn-primary">
					<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/login/?tab=register' ) ); ?>" class="bd-btn bd-btn-secondary">
					<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get available cover options
	 *
	 * @return array Cover options.
	 */
	public static function get_cover_options() {
		return self::COVER_OPTIONS;
	}
}
