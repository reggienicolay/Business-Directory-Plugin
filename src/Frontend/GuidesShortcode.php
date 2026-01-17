<?php
/**
 * Guides Shortcode
 *
 * Displays Community Guides in a beautiful grid layout.
 * Used on the main Guides page to showcase team members.
 *
 * ENHANCEMENTS (v1.3.0):
 * - Fixed avatar overlap effect - badge icon now inside avatar container
 * - Added quote validation to hide placeholder text like "Quote"
 * - Added verified checkmark next to guide names
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 * @version 1.3.0
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Admin\GuidesAdmin;
use BD\Gamification\BadgeSystem;

/**
 * Class GuidesShortcode
 */
class GuidesShortcode {

	/**
	 * Initialize
	 */
	public static function init() {
		add_shortcode( 'bd_guides', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets() {
		global $post;

		// Check if shortcode is used.
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'bd_guides' ) ) {
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

		// Guides styles.
		wp_enqueue_style(
			'bd-guides',
			BD_PLUGIN_URL . 'assets/css/guides.css',
			array( 'bd-design-tokens', 'font-awesome' ),
			BD_VERSION
		);
	}

	/**
	 * Check if a quote is valid (not empty or placeholder)
	 *
	 * @param string $quote The quote text.
	 * @return bool True if valid quote, false otherwise.
	 */
	private static function is_valid_quote( $quote ) {
		if ( empty( $quote ) ) {
			return false;
		}

		$quote       = trim( $quote );
		$quote_lower = strtolower( $quote );

		// List of invalid placeholder values.
		$invalid_values = array(
			'quote',
			'*quote*',
			'**quote**',
			'"quote"',
			'""quote""',
			'your quote here',
			'add your quote',
			'enter quote',
			'[quote]',
			'(quote)',
		);

		if ( in_array( $quote_lower, $invalid_values, true ) ) {
			return false;
		}

		// Must be at least 10 characters to be meaningful.
		if ( strlen( $quote ) < 10 ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'   => -1,
				'city'    => '',
				'columns' => 3,
				'layout'  => 'cards', // cards, compact, featured.
			),
			$atts
		);

		// Get guides.
		$guides = GuidesAdmin::get_all_guides(
			array(
				'limit' => intval( $atts['limit'] ),
				'city'  => sanitize_text_field( $atts['city'] ),
			)
		);

		if ( empty( $guides ) ) {
			return '<p class="bd-no-guides">' . esc_html__( 'No guides found.', 'business-directory' ) . '</p>';
		}

		$layout  = sanitize_key( $atts['layout'] );
		$columns = absint( $atts['columns'] );

		ob_start();
		?>
		<div class="bd-guides-section">

			<?php if ( 'featured' === $layout ) : ?>
				<!-- Featured Layout: Hero-style for homepage -->
				<div class="bd-guides-featured">
					<?php foreach ( $guides as $guide ) : ?>
						<?php echo self::render_featured_card( $guide ); ?>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'compact' === $layout ) : ?>
				<!-- Compact Layout: Smaller cards for sidebars -->
				<div class="bd-guides-compact">
					<?php foreach ( $guides as $guide ) : ?>
						<?php echo self::render_compact_card( $guide ); ?>
					<?php endforeach; ?>
				</div>

			<?php else : ?>
				<!-- Default Cards Layout -->
				<div class="bd-guides-grid bd-guides-cols-<?php echo esc_attr( $columns ); ?>">
					<?php foreach ( $guides as $guide ) : ?>
						<?php echo self::render_guide_card( $guide ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a guide card
	 *
	 * @param array $guide Guide data.
	 * @return string HTML output.
	 */
	private static function render_guide_card( $guide ) {
		$user        = get_userdata( $guide['user_id'] );
		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$quote       = get_user_meta( $guide['user_id'], 'bd_guide_quote', true );
		$cities      = get_user_meta( $guide['user_id'], 'bd_guide_cities', true ) ?: array();
		$bio         = get_user_meta( $guide['user_id'], 'bd_public_bio', true );
		$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		// Get social links.
		$instagram = get_user_meta( $guide['user_id'], 'bd_instagram', true );
		$facebook  = get_user_meta( $guide['user_id'], 'bd_facebook', true );
		$linkedin  = get_user_meta( $guide['user_id'], 'bd_linkedin', true );

		// Get stats.
		$stats = self::get_guide_stats( $guide['user_id'] );

		// Get top badges (max 3) - with safety check.
		$badges = array();
		if ( class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			$raw_badges = BadgeSystem::get_user_badges( $guide['user_id'] );
			if ( is_array( $raw_badges ) ) {
				$badges = array_slice( $raw_badges, 0, 3 );
			}
		}

		// ENHANCEMENT: Validate quote.
		$has_valid_quote = self::is_valid_quote( $quote );

		ob_start();
		?>
		<article class="bd-guide-card">
			<a href="<?php echo esc_url( $profile_url ); ?>" class="bd-guide-card-link">
				<!-- Card Header with Avatar -->
				<div class="bd-guide-card-header">
					<div class="bd-guide-avatar">
						<?php echo get_avatar( $guide['user_id'], 130 ); ?>
						<!-- FIX: Badge icon INSIDE avatar for proper positioning -->
						<span class="bd-guide-badge-icon">
							<i class="fas fa-user-shield"></i>
						</span>
					</div>
				</div>

				<!-- Card Body -->
				<div class="bd-guide-card-body">
					<!-- ENHANCEMENT: Added verified checkmark -->
					<h3 class="bd-guide-name">
						<?php echo esc_html( $user->display_name ); ?>
						<span class="bd-guide-verified" title="<?php esc_attr_e( 'Verified Guide', 'business-directory' ); ?>">
							<i class="fas fa-circle-check"></i>
						</span>
					</h3>

					<?php if ( $title ) : ?>
						<p class="bd-guide-title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $cities ) && is_array( $cities ) ) : ?>
						<p class="bd-guide-cities">
							<i class="fas fa-map-marker-alt"></i>
							<?php echo esc_html( implode( ' · ', $cities ) ); ?>
						</p>
					<?php endif; ?>

					<?php // ENHANCEMENT: Only show quote if valid. ?>
					<?php if ( $has_valid_quote ) : ?>
						<blockquote class="bd-guide-quote">
							"<?php echo esc_html( wp_trim_words( $quote, 15 ) ); ?>"
						</blockquote>
					<?php elseif ( $bio ) : ?>
						<p class="bd-guide-bio"><?php echo esc_html( wp_trim_words( $bio, 20 ) ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Stats Row -->
				<div class="bd-guide-stats">
					<span class="bd-guide-stat" title="<?php esc_attr_e( 'Reviews', 'business-directory' ); ?>">
						<i class="fas fa-star"></i>
						<?php echo esc_html( $stats['reviews'] ); ?>
					</span>
					<span class="bd-guide-stat" title="<?php esc_attr_e( 'Lists', 'business-directory' ); ?>">
						<i class="fas fa-list"></i>
						<?php echo esc_html( $stats['lists'] ); ?>
					</span>
					<span class="bd-guide-stat" title="<?php esc_attr_e( 'Badges', 'business-directory' ); ?>">
						<i class="fas fa-award"></i>
						<?php echo esc_html( $stats['badges'] ); ?>
					</span>
				</div>

				<!-- Badges Row -->
				<?php if ( ! empty( $badges ) && is_array( $badges ) ) : ?>
					<div class="bd-guide-badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php
							// Safety check: handle both array and object formats.
							$badge_name = '';
							$badge_icon = '';

							if ( is_array( $badge ) ) {
								$badge_name = isset( $badge['name'] ) ? $badge['name'] : '';
								$badge_icon = isset( $badge['icon'] ) ? $badge['icon'] : '<i class="fas fa-award"></i>';
							} elseif ( is_object( $badge ) ) {
								$badge_name = isset( $badge->name ) ? $badge->name : '';
								$badge_icon = isset( $badge->icon ) ? $badge->icon : '<i class="fas fa-award"></i>';
							} elseif ( is_string( $badge ) ) {
								// If badge is just a string (name), use it directly.
								$badge_name = $badge;
								$badge_icon = '<i class="fas fa-award"></i>';
							}

							if ( empty( $badge_name ) ) {
								continue;
							}
							?>
							<span class="bd-guide-badge-mini" title="<?php echo esc_attr( $badge_name ); ?>">
								<?php echo $badge_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon HTML from badge system. ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</a>

			<!-- Social Links (outside main link) -->
			<?php if ( $instagram || $facebook || $linkedin ) : ?>
				<div class="bd-guide-social">
					<?php if ( $instagram ) : ?>
						<a href="<?php echo esc_url( 'https://instagram.com/' . ltrim( $instagram, '@' ) ); ?>" target="_blank" rel="noopener" title="Instagram">
							<i class="fa-brands fa-instagram"></i>
						</a>
					<?php endif; ?>
					<?php if ( $facebook ) : ?>
						<a href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener" title="Facebook">
							<i class="fa-brands fa-facebook"></i>
						</a>
					<?php endif; ?>
					<?php if ( $linkedin ) : ?>
						<a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener" title="LinkedIn">
							<i class="fa-brands fa-linkedin"></i>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- View Profile CTA -->
			<a href="<?php echo esc_url( $profile_url ); ?>" class="bd-guide-cta">
				<?php esc_html_e( 'View Profile', 'business-directory' ); ?>
				<i class="fas fa-arrow-right"></i>
			</a>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a featured guide card (larger, for homepage)
	 *
	 * @param array $guide Guide data.
	 * @return string HTML output.
	 */
	private static function render_featured_card( $guide ) {
		$user        = get_userdata( $guide['user_id'] );
		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$quote       = get_user_meta( $guide['user_id'], 'bd_guide_quote', true );
		$bio         = get_user_meta( $guide['user_id'], 'bd_public_bio', true );
		$cities      = get_user_meta( $guide['user_id'], 'bd_guide_cities', true ) ?: array();
		$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		// ENHANCEMENT: Validate quote.
		$has_valid_quote = self::is_valid_quote( $quote );

		ob_start();
		?>
		<article class="bd-guide-featured">
			<div class="bd-guide-featured-avatar">
				<?php echo get_avatar( $guide['user_id'], 200 ); ?>
				<span class="bd-guide-badge-lg">
					<i class="fas fa-user-shield"></i>
					<?php esc_html_e( 'Guide', 'business-directory' ); ?>
				</span>
			</div>

			<div class="bd-guide-featured-content">
				<!-- ENHANCEMENT: Added verified checkmark -->
				<h3 class="bd-guide-name">
					<?php echo esc_html( $user->display_name ); ?>
					<span class="bd-guide-verified" title="<?php esc_attr_e( 'Verified Guide', 'business-directory' ); ?>">
						<i class="fas fa-circle-check"></i>
					</span>
				</h3>

				<?php if ( $title ) : ?>
					<p class="bd-guide-title"><?php echo esc_html( $title ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $cities ) && is_array( $cities ) ) : ?>
					<p class="bd-guide-cities">
						<i class="fas fa-map-marker-alt"></i>
						<?php echo esc_html( implode( ' · ', $cities ) ); ?>
					</p>
				<?php endif; ?>

				<?php // ENHANCEMENT: Only show quote if valid. ?>
				<?php if ( $has_valid_quote ) : ?>
					<blockquote class="bd-guide-quote-lg">
						<i class="fas fa-quote-left"></i>
						<?php echo esc_html( $quote ); ?>
					</blockquote>
				<?php endif; ?>

				<?php if ( $bio ) : ?>
					<p class="bd-guide-bio-lg"><?php echo esc_html( wp_trim_words( $bio, 40 ) ); ?></p>
				<?php endif; ?>

				<a href="<?php echo esc_url( $profile_url ); ?>" class="bd-guide-cta-lg">
					<?php esc_html_e( 'Meet', 'business-directory' ); ?> <?php echo esc_html( $user->display_name ); ?>
					<i class="fas fa-arrow-right"></i>
				</a>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a compact guide card (for sidebars)
	 *
	 * @param array $guide Guide data.
	 * @return string HTML output.
	 */
	private static function render_compact_card( $guide ) {
		$user        = get_userdata( $guide['user_id'] );
		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		ob_start();
		?>
		<a href="<?php echo esc_url( $profile_url ); ?>" class="bd-guide-compact">
			<div class="bd-guide-compact-avatar">
				<?php echo get_avatar( $guide['user_id'], 50 ); ?>
			</div>
			<div class="bd-guide-compact-info">
				<span class="bd-guide-compact-name"><?php echo esc_html( $user->display_name ); ?></span>
				<?php if ( $title ) : ?>
					<span class="bd-guide-compact-title"><?php echo esc_html( $title ); ?></span>
				<?php endif; ?>
			</div>
			<i class="fas fa-chevron-right"></i>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get guide stats
	 *
	 * @param int $user_id User ID.
	 * @return array Stats.
	 */
	private static function get_guide_stats( $user_id ) {
		global $wpdb;

		$stats = array(
			'reviews' => 0,
			'lists'   => 0,
			'badges'  => 0,
		);

		// Reviews.
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);

		if ( $table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['reviews'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reviews_table} WHERE user_id = %d AND status = 'approved'",
					$user_id
				)
			);
		}

		// Lists.
		$lists_table = $wpdb->prefix . 'bd_lists';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$lists_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $lists_table )
		);

		if ( $lists_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['lists'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$lists_table} WHERE user_id = %d",
					$user_id
				)
			);
		}

		// Badges.
		if ( class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			$badges          = BadgeSystem::get_user_badges( $user_id );
			$stats['badges'] = is_array( $badges ) ? count( $badges ) : 0;
		}

		return $stats;
	}
}