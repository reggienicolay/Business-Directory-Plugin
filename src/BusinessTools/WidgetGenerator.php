<?php
/**
 * Widget Generator
 *
 * Generates embeddable review widget code for business owners.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WidgetGenerator
 */
class WidgetGenerator {

	/**
	 * Widget styles.
	 */
	const STYLES = array(
		'compact'  => 'Compact Badge',
		'carousel' => 'Review Carousel',
		'list'     => 'Full Reviews List',
	);

	/**
	 * Generate embed code for a business.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $style       Widget style.
	 * @param string $theme       Theme (light/dark).
	 * @param int    $reviews     Number of reviews to show.
	 * @return string Embed code.
	 */
	public static function generate_embed_code( $business_id, $style = 'compact', $theme = 'light', $reviews = 5 ) {
		$widget_url = rest_url( 'bd/v1/widget/embed.js' );
		$slug       = get_post_field( 'post_name', $business_id );
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Embed code for external sites.
		$code = sprintf(
			'<!-- LoveTriValley Review Widget -->
<div id="ltv-widget-%1$d"></div>
<script 
  src="%2$s"
  data-business="%1$d"
  data-slug="%3$s"
  data-style="%4$s"
  data-theme="%5$s"
  data-reviews="%6$d"
  async>
</script>',
			$business_id,
			esc_url( $widget_url ),
			esc_attr( $slug ),
			esc_attr( $style ),
			esc_attr( $theme ),
			$reviews
		);

		return $code;
	}

	/**
	 * Get allowed domains for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return array Array of allowed domains.
	 */
	public static function get_allowed_domains( $business_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_widget_domains';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$domains = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT domain FROM {$table} WHERE business_id = %d AND status = 'approved'",
				$business_id
			)
		);
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		return $domains ?: array();
	}

	/**
	 * Save allowed domains for a business.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $domains_text Domains (newline separated).
	 * @return bool Success.
	 */
	public static function save_allowed_domains( $business_id, $domains_text ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_widget_domains';

		// Parse domains.
		$domains = array_filter(
			array_map(
				function ( $domain ) {
					$domain = trim( strtolower( $domain ) );
					// Remove protocol if present.
					$domain = preg_replace( '#^https?://#', '', $domain );
					// Remove trailing slash.
					$domain = rtrim( $domain, '/' );
					// Remove path if present.
					$domain = explode( '/', $domain )[0];
					return $domain;
				},
				explode( "\n", $domains_text )
			)
		);

		// Delete existing domains.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'business_id' => $business_id ), array( '%d' ) );

		// Insert new domains.
		foreach ( $domains as $domain ) {
			if ( empty( $domain ) ) {
				continue;
			}

			// Validate domain format.
			if ( ! self::is_valid_domain( $domain ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'business_id' => $business_id,
					'domain'      => $domain,
					'status'      => 'approved', // Auto-approve for now.
					'approved_at' => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Check if a domain is allowed for a business.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $domain      Domain to check.
	 * @return bool Is allowed.
	 */
	public static function is_domain_allowed( $business_id, $domain ) {
		$allowed = self::get_allowed_domains( $business_id );

		if ( empty( $allowed ) ) {
			// If no domains configured, allow all (for testing).
			return true;
		}

		// Normalize domain.
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = rtrim( $domain, '/' );

		// Check exact match.
		if ( in_array( $domain, $allowed, true ) ) {
			return true;
		}

		// Check without www.
		$without_www = preg_replace( '#^www\.#', '', $domain );
		if ( in_array( $without_www, $allowed, true ) ) {
			return true;
		}

		// Check with www.
		$with_www = 'www.' . $without_www;
		if ( in_array( $with_www, $allowed, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate domain format.
	 *
	 * @param string $domain Domain to validate.
	 * @return bool Is valid.
	 */
	private static function is_valid_domain( $domain ) {
		// Basic domain validation.
		return (bool) preg_match( '/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $domain );
	}

	/**
	 * Get recent reviews for widget.
	 *
	 * @param int $business_id Business ID.
	 * @param int $limit       Number of reviews.
	 * @return array Reviews.
	 */
	public static function get_widget_reviews( $business_id, $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, rating, content, author_name, created_at 
				FROM {$table} 
				WHERE business_id = %d AND status = 'approved' 
				ORDER BY created_at DESC 
				LIMIT %d",
				$business_id,
				$limit
			),
			ARRAY_A
		);

		// Format reviews.
		return array_map(
			function ( $review ) {
				return array(
					'id'       => $review['id'],
					'rating'   => (int) $review['rating'],
					'content'  => wp_trim_words( $review['content'], 30 ),
					'author'   => $review['author_name'] ?: __( 'Anonymous', 'business-directory' ),
					'date'     => human_time_diff( strtotime( $review['created_at'] ) ) . ' ' . __( 'ago', 'business-directory' ),
					'date_iso' => gmdate( 'c', strtotime( $review['created_at'] ) ),
				);
			},
			$reviews ?: array()
		);
	}

	/**
	 * Get widget data for a business.
	 *
	 * @param int $business_id Business ID.
	 * @param int $review_count Number of reviews to include.
	 * @return array Widget data.
	 */
	public static function get_widget_data( $business_id, $review_count = 5 ) {
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return array( 'error' => 'Business not found' );
		}

		// Get rating info.
		global $wpdb;
		$table = $wpdb->prefix . 'bd_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as avg 
				FROM {$table} 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			),
			ARRAY_A
		);

		return array(
			'business'   => array(
				'id'   => $business_id,
				'name' => $business->post_title,
				'slug' => $business->post_name,
				'url'  => get_permalink( $business_id ),
			),
			'rating'     => array(
				'average' => $stats['avg'] ? round( (float) $stats['avg'], 1 ) : 0,
				'count'   => (int) $stats['count'],
			),
			'reviews'    => self::get_widget_reviews( $business_id, $review_count ),
			'review_url' => get_permalink( $business_id ) . '#write-review',
			'site_name'  => get_bloginfo( 'name' ),
			'site_url'   => home_url(),
		);
	}

	/**
	 * Generate widget preview HTML (for dashboard).
	 *
	 * @param int    $business_id Business ID.
	 * @param string $style       Widget style.
	 * @param string $theme       Theme.
	 * @return string HTML.
	 */
	public static function generate_preview( $business_id, $style = 'compact', $theme = 'light' ) {
		$data = self::get_widget_data( $business_id, 3 );

		if ( isset( $data['error'] ) ) {
			return '<p class="error">' . esc_html( $data['error'] ) . '</p>';
		}

		$theme_class = 'ltv-theme-' . $theme;

		ob_start();

		switch ( $style ) {
			case 'compact':
				?>
				<div class="ltv-widget ltv-compact <?php echo esc_attr( $theme_class ); ?>">
					<div class="ltv-rating">
						<span class="ltv-stars"><?php echo esc_html( str_repeat( '★', (int) round( $data['rating']['average'] ) ) ); ?></span>
						<span class="ltv-rating-text"><?php echo esc_html( $data['rating']['average'] ); ?> (<?php echo esc_html( $data['rating']['count'] ); ?>)</span>
					</div>
					<a href="<?php echo esc_url( $data['review_url'] ); ?>" class="ltv-review-btn">Write a Review</a>
					<div class="ltv-branding">📍 <?php echo esc_html( $data['site_name'] ); ?></div>
				</div>
				<?php
				break;

			case 'carousel':
				?>
				<div class="ltv-widget ltv-carousel <?php echo esc_attr( $theme_class ); ?>">
					<?php if ( ! empty( $data['reviews'] ) ) : ?>
						<div class="ltv-review-slide">
							<div class="ltv-review-content">"<?php echo esc_html( $data['reviews'][0]['content'] ); ?>"</div>
							<div class="ltv-review-meta">
								<span class="ltv-stars"><?php echo esc_html( str_repeat( '★', $data['reviews'][0]['rating'] ) ); ?></span>
								— <?php echo esc_html( $data['reviews'][0]['author'] ); ?>
							</div>
						</div>
					<?php endif; ?>
					<div class="ltv-carousel-footer">
						<span class="ltv-carousel-dots">● ○ ○</span>
						<a href="<?php echo esc_url( $data['review_url'] ); ?>" class="ltv-review-btn">Write a Review</a>
					</div>
				</div>
				<?php
				break;

			case 'list':
				?>
				<div class="ltv-widget ltv-list <?php echo esc_attr( $theme_class ); ?>">
					<div class="ltv-header">
						<strong><?php echo esc_html( $data['business']['name'] ); ?></strong>
						<span class="ltv-rating">
							<?php echo esc_html( str_repeat( '★', (int) round( $data['rating']['average'] ) ) ); ?>
							<?php echo esc_html( $data['rating']['average'] ); ?> · <?php echo esc_html( $data['rating']['count'] ); ?> reviews
						</span>
					</div>
					<div class="ltv-reviews">
						<?php foreach ( $data['reviews'] as $review ) : ?>
							<div class="ltv-review">
								<span class="ltv-stars"><?php echo esc_html( str_repeat( '★', $review['rating'] ) ); ?></span>
								"<?php echo esc_html( $review['content'] ); ?>"
								<span class="ltv-author"><?php echo esc_html( $review['author'] ); ?> · <?php echo esc_html( $review['date'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="ltv-footer">
						<a href="<?php echo esc_url( $data['review_url'] ); ?>" class="ltv-review-btn">Write a Review</a>
						<a href="<?php echo esc_url( $data['business']['url'] ); ?>" class="ltv-all-reviews">See All Reviews</a>
					</div>
				</div>
				<?php
				break;
		}

		return ob_get_clean();
	}
}
