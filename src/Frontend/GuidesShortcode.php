<?php
/**
 * Guides Shortcode
 *
 * Displays Community Guides in a beautiful grid layout.
 * Used on the main Guides page to showcase team members.
 *
 * ENHANCEMENTS (v1.5.0):
 * - Fixed avatar overlap effect - badge icon now inside avatar container
 * - Added quote validation to hide placeholder text like "Quote"
 * - Added verified checkmark next to guide names
 * - SECURITY: Added wp_kses() for badge icon output to prevent XSS
 * - BUGFIX: Added null checks for get_userdata() to handle deleted users
 * - SECURITY: Fixed SQL table name interpolation
 * - Added filterable profile URL
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 * @version 1.5.0
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
	 * Allowed HTML for badge icons (security whitelist)
	 *
	 * @var array
	 */
	const ALLOWED_ICON_HTML = array(
		'i'    => array(
			'class'       => array(),
			'title'       => array(),
			'aria-hidden' => array(),
		),
		'span' => array(
			'class'       => array(),
			'title'       => array(),
			'aria-hidden' => array(),
		),
		'svg'  => array(
			'class'   => array(),
			'width'   => array(),
			'height'  => array(),
			'viewbox' => array(),
			'fill'    => array(),
			'xmlns'   => array(),
		),
		'path' => array(
			'd'    => array(),
			'fill' => array(),
		),
	);

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
	 * Get profile URL for a user
	 *
	 * @param \WP_User $user User object.
	 * @return string Profile URL.
	 */
	private static function get_profile_url( $user ) {
		$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		/**
		 * Filter the guide profile URL.
		 *
		 * @param string   $profile_url The profile URL.
		 * @param \WP_User $user        The user object.
		 */
		return apply_filters( 'bd_guide_profile_url', $profile_url, $user );
	}

	/**
	 * Sanitize badge icon HTML
	 *
	 * @param string $icon_html Raw icon HTML.
	 * @return string Sanitized icon HTML.
	 */
	private static function sanitize_badge_icon( $icon_html ) {
		if ( empty( $icon_html ) ) {
			return '<i class="fas fa-award"></i>';
		}

		// Use wp_kses with our allowed HTML whitelist.
		$sanitized = wp_kses( $icon_html, self::ALLOWED_ICON_HTML );

		// If sanitization removed everything, return default icon.
		if ( empty( trim( $sanitized ) ) ) {
			return '<i class="fas fa-award"></i>';
		}

		return $sanitized;
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
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method returns escaped HTML.
						echo self::render_featured_card( $guide );
						?>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'compact' === $layout ) : ?>
				<!-- Compact Layout: Smaller cards for sidebars -->
				<div class="bd-guides-compact">
					<?php foreach ( $guides as $guide ) : ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method returns escaped HTML.
						echo self::render_compact_card( $guide );
						?>
					<?php endforeach; ?>
				</div>

			<?php else : ?>
				<!-- Default Cards Layout -->
				<div class="bd-guides-grid bd-guides-cols-<?php echo esc_attr( $columns ); ?>">
					<?php foreach ( $guides as $guide ) : ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method returns escaped HTML.
						echo self::render_guide_card( $guide );
						?>
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
		$user = get_userdata( $guide['user_id'] );

		// Safety check: user may have been deleted.
		if ( ! $user ) {
			return '';
		}

		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$quote       = get_user_meta( $guide['user_id'], 'bd_guide_quote', true );
		$cities      = get_user_meta( $guide['user_id'], 'bd_guide_cities', true );
		$bio         = get_user_meta( $guide['user_id'], 'bd_public_bio', true );
		$profile_url = self::get_profile_url( $user );

		// Ensure cities is an array.
		$cities = is_array( $cities ) ? $cities : array();

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

		// Validate quote.
		$has_valid_quote = self::is_valid_quote( $quote );

		ob_start();
		?>
		<article class="bd-guide-card">
			<a href="<?php echo esc_url( $profile_url ); ?>" class="bd-guide-card-link">
				<!-- Card Header with Avatar -->
				<div class="bd-guide-card-header">
					<div class="bd-guide-avatar">
						<?php echo get_avatar( $guide['user_id'], 130 ); ?>
						<!-- Badge icon INSIDE avatar for proper positioning -->
						<span class="bd-guide-badge-icon">
							<i class="fas fa-user-shield"></i>
						</span>
					</div>
				</div>

				<!-- Card Body -->
				<div class="bd-guide-card-body">
					<h3 class="bd-guide-name">
						<?php echo esc_html( $user->display_name ); ?>
						<span class="bd-guide-verified" title="<?php esc_attr_e( 'Verified Guide', 'business-directory' ); ?>">
							<i class="fas fa-circle-check"></i>
						</span>
					</h3>

					<?php if ( $title ) : ?>
						<p class="bd-guide-title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $cities ) ) : ?>
						<p class="bd-guide-cities">
							<i class="fas fa-map-marker-alt"></i>
							<?php echo esc_html( implode( ' · ', $cities ) ); ?>
						</p>
					<?php endif; ?>

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
				<?php if ( ! empty( $badges ) ) : ?>
					<div class="bd-guide-badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php
							// Safety check: handle both array and object formats.
							$badge_name = '';
							$badge_icon = '<i class="fas fa-award"></i>';

							if ( is_array( $badge ) ) {
								$badge_name = isset( $badge['name'] ) ? $badge['name'] : '';
								$badge_icon = isset( $badge['icon'] ) ? $badge['icon'] : '<i class="fas fa-award"></i>';
							} elseif ( is_object( $badge ) ) {
								$badge_name = isset( $badge->name ) ? $badge->name : '';
								$badge_icon = isset( $badge->icon ) ? $badge->icon : '<i class="fas fa-award"></i>';
							} elseif ( is_string( $badge ) ) {
								$badge_name = $badge;
							}

							if ( empty( $badge_name ) ) {
								continue;
							}

							// SECURITY FIX: Sanitize badge icon HTML to prevent XSS.
							$safe_icon = self::sanitize_badge_icon( $badge_icon );
							?>
							<span class="bd-guide-badge-mini" title="<?php echo esc_attr( $badge_name ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses in sanitize_badge_icon().
								echo $safe_icon;
								?>
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
		$user = get_userdata( $guide['user_id'] );

		// Safety check: user may have been deleted.
		if ( ! $user ) {
			return '';
		}

		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$quote       = get_user_meta( $guide['user_id'], 'bd_guide_quote', true );
		$bio         = get_user_meta( $guide['user_id'], 'bd_public_bio', true );
		$cities      = get_user_meta( $guide['user_id'], 'bd_guide_cities', true );
		$profile_url = self::get_profile_url( $user );

		// Ensure cities is an array.
		$cities = is_array( $cities ) ? $cities : array();

		// Validate quote.
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
				<h3 class="bd-guide-name">
					<?php echo esc_html( $user->display_name ); ?>
					<span class="bd-guide-verified" title="<?php esc_attr_e( 'Verified Guide', 'business-directory' ); ?>">
						<i class="fas fa-circle-check"></i>
					</span>
				</h3>

				<?php if ( $title ) : ?>
					<p class="bd-guide-title"><?php echo esc_html( $title ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $cities ) ) : ?>
					<p class="bd-guide-cities">
						<i class="fas fa-map-marker-alt"></i>
						<?php echo esc_html( implode( ' · ', $cities ) ); ?>
					</p>
				<?php endif; ?>

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
		$user = get_userdata( $guide['user_id'] );

		// Safety check: user may have been deleted.
		if ( ! $user ) {
			return '';
		}

		$title       = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
		$profile_url = self::get_profile_url( $user );

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

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return $stats;
		}

		// Reviews - check table exists first.
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time table check.
		$reviews_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);

		if ( $reviews_table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query, caching handled at page level.
			$stats['reviews'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed from $wpdb->prefix.
					"SELECT COUNT(*) FROM `{$reviews_table}` WHERE user_id = %d AND status = 'approved'",
					$user_id
				)
			);
		}

		// Lists - check table exists first.
		$lists_table = $wpdb->prefix . 'bd_lists';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time table check.
		$lists_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $lists_table )
		);

		if ( $lists_table_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query, caching handled at page level.
			$stats['lists'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed from $wpdb->prefix.
					"SELECT COUNT(*) FROM `{$lists_table}` WHERE user_id = %d",
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
