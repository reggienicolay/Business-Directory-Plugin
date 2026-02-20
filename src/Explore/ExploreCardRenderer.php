<?php
/**
 * Explore Card Renderer
 *
 * Renders business cards, the card grid, star ratings, and the
 * topographic contour placeholder SVG for cards without images.
 *
 * Server-side HTML that produces identical markup to the
 * Quick Filters JS renderCard() function, ensuring visual
 * consistency and seamless JS hydration after page load.
 *
 * @package    BD
 * @subpackage Explore
 * @since      2.3.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreCardRenderer
 */
class ExploreCardRenderer {

	/**
	 * SVG star path — shared across full, half, and empty states.
	 *
	 * SYNC: Also used in quick-filters.js renderStars().
	 * If this path changes, update both locations.
	 *
	 * @var string
	 */
	const STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';

	/**
	 * Featured business IDs (loaded once per request).
	 *
	 * @var array|null
	 */
	private static $featured_ids = null;

	/**
	 * Render a single business card.
	 *
	 * Produces the same HTML as quick-filters.js renderCard().
	 * Uses bd-qf-card CSS classes for visual consistency.
	 *
	 * @param array $business Formatted business data from ExploreQuery.
	 * @return string Card HTML.
	 */
	public static function render_card( $business ) {
		if ( null === self::$featured_ids ) {
			self::$featured_ids = array();
			if ( class_exists( '\\BD\\Admin\\FeaturedAdmin' ) ) {
				self::$featured_ids = \BD\Admin\FeaturedAdmin::get_featured_ids();
			}
		}

		$is_featured = in_array( $business['id'], self::$featured_ids, true );
		$classes     = 'bd-qf-card';
		if ( $is_featured ) {
			$classes .= ' bd-qf-featured';
		}

		// ExploreQuery::format_business() guarantees featured_image
		// is always a string (URL or empty string '').
		$image_url = $business['featured_image'] ?? '';

		// Location may be null if no bd_location meta exists.
		$location = is_array( $business['location'] ?? null ) ? $business['location'] : array();

		ob_start();
		?>
		<article class="<?php echo esc_attr( $classes ); ?>" data-business-id="<?php echo esc_attr( $business['id'] ); ?>">
			<?php // Image section. ?>
			<div class="bd-qf-card-image">
				<?php if ( ! empty( $image_url ) ) : ?>
					<?php
					$alt_text = $business['title'];
					if ( ! empty( $business['areas'] ) ) {
						$alt_text .= ' in ' . $business['areas'][0] . ', California';
					}
					?>
					<img src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $alt_text ); ?>"
						loading="lazy" width="400" height="200">
				<?php else : ?>
					<?php
					// Try the category-aware PlaceholderImage (wine glass for wineries,
					// utensils for restaurants, etc.) before the generic contour rings.
					$placeholder_svg = '';

					// Use PlaceholderImage class directly if available.
					if ( class_exists( '\\BusinessDirectory\\Utils\\PlaceholderImage' ) ) {
						$placeholder_svg = \BusinessDirectory\Utils\PlaceholderImage::generate( $business['id'] );
					}

					if ( ! empty( $placeholder_svg ) ) {
						echo $placeholder_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from PlaceholderImage.
					} else {
						echo self::render_placeholder_svg( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup.
					}
					?>
				<?php endif; ?>
				<div class="bd-qf-card-save">
					<div class="bd-save-wrapper" data-business-id="<?php echo esc_attr( $business['id'] ); ?>">
						<button type="button" class="bd-save-btn" title="<?php esc_attr_e( 'Save to List', 'business-directory' ); ?>">
							<i class="far fa-heart"></i>
						</button>
					</div>
				</div>
			</div>

			<?php // Content section. ?>
			<div class="bd-qf-card-content">
				<h3 class="bd-qf-card-title">
					<a href="<?php echo esc_url( $business['permalink'] ); ?>">
						<?php echo esc_html( $business['title'] ); ?>
					</a>
				</h3>

				<div class="bd-qf-card-rating">
					<?php if ( $business['rating'] > 0 ) : ?>
						<div class="bd-qf-card-stars"><?php echo self::render_stars( $business['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup. ?></div>
						<span class="bd-qf-card-rating-value"><?php echo esc_html( number_format( $business['rating'], 1 ) ); ?></span>
						<span class="bd-qf-card-review-count">(<?php echo intval( $business['review_count'] ); ?>)</span>
					<?php else : ?>
						<span class="bd-qf-card-no-reviews"><?php esc_html_e( 'No reviews yet', 'business-directory' ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $business['categories'] ) ) : ?>
					<span class="bd-qf-card-category"><?php echo esc_html( $business['categories'][0] ); ?></span>
				<?php endif; ?>

				<?php if ( ! empty( $business['areas'] ) ) : ?>
					<div class="bd-qf-card-location">
						<i class="fas fa-map-marker-alt"></i>
						<?php echo esc_html( $business['areas'][0] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $business['excerpt'] ) ) : ?>
					<p class="bd-qf-card-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $business['excerpt'] ), 25 ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $business['tags'] ) ) : ?>
					<div class="bd-qf-card-tags">
						<?php foreach ( array_slice( $business['tags'], 0, 3 ) as $tag ) : ?>
							<span class="bd-qf-card-tag"><?php echo esc_html( $tag['name'] ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php // CTA — outside content div for grid layout. ?>
			<a href="<?php echo esc_url( $business['permalink'] ); ?>" class="bd-qf-card-cta">
				<?php esc_html_e( 'View Details', 'business-directory' ); ?> <i class="fas fa-arrow-right"></i>
			</a>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a grid of business cards.
	 *
	 * @param array $businesses Array of formatted business data.
	 * @return string Grid HTML.
	 */
	public static function render_grid( $businesses ) {
		if ( empty( $businesses ) ) {
			return self::render_no_results();
		}

		ob_start();
		?>
		<div id="bd-qf-businesses" class="bd-qf-businesses bd-qf-view-grid">
			<?php foreach ( $businesses as $business ) : ?>
				<?php echo self::render_card( $business ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render no results message.
	 *
	 * @return string HTML.
	 */
	public static function render_no_results() {
		ob_start();
		?>
		<div class="bd-qf-no-results">
			<i class="fas fa-search"></i>
			<p><?php esc_html_e( 'No businesses found matching your criteria.', 'business-directory' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render SVG star rating matching Quick Filter JS.
	 *
	 * SYNC: quick-filters.js renderStars() produces identical output.
	 * If the star design changes, update both locations.
	 *
	 * @param float $rating Rating value (0-5).
	 * @return string SVG HTML.
	 */
	public static function render_stars( $rating ) {
		$rating     = floatval( $rating );
		$full_stars = (int) floor( $rating );
		$has_half   = ( $rating - $full_stars ) >= 0.5;
		$html       = '';
		$path       = self::STAR_PATH;

		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $i <= $full_stars ) {
				$html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="#2CB1BC"><path d="' . $path . '"/></svg>';
			} elseif ( $i === $full_stars + 1 && $has_half ) {
				$half_id = 'half-' . wp_unique_id( 'star-' );
				$html   .= '<svg width="14" height="14" viewBox="0 0 24 24"><defs><linearGradient id="' . esc_attr( $half_id ) . '"><stop offset="50%" stop-color="#2CB1BC"/><stop offset="50%" stop-color="#d1d5db"/></linearGradient></defs><path fill="url(#' . esc_attr( $half_id ) . ')" d="' . $path . '"/></svg>';
			} else {
				$html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="#d1d5db"><path d="' . $path . '"/></svg>';
			}
		}

		return $html;
	}

	/**
	 * Render the topographic contour placeholder SVG.
	 *
	 * Branded wine-country aesthetic for cards without a featured image:
	 * concentric gold rings, centered pin icon, coordinate text.
	 *
	 * SYNC: quick-filters.js renderPlaceholderSVG() produces identical
	 * visual output. If the design changes, update both locations.
	 *
	 * @param array $location Location array with 'lat' and 'lng' keys, or empty.
	 * @return string Placeholder HTML.
	 */
	public static function render_placeholder_svg( $location = array() ) {
		$lat = floatval( $location['lat'] ?? 0 );
		$lng = floatval( $location['lng'] ?? 0 );
		$has_coords = ( 0.0 !== $lat || 0.0 !== $lng );
		$coord_text = $has_coords
			? sprintf( '%.2fN %.2fW', abs( $lat ), abs( $lng ) )
			: '';

		ob_start();
		?>
		<div class="bd-qf-card-placeholder" aria-hidden="true">
			<svg viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
				<?php // Concentric contour ellipses — gold accent rings. ?>
				<ellipse cx="200" cy="100" rx="180" ry="90" fill="none" stroke="rgba(201,162,39,0.08)" stroke-width="1"/>
				<ellipse cx="200" cy="100" rx="150" ry="75" fill="none" stroke="rgba(201,162,39,0.10)" stroke-width="1"/>
				<ellipse cx="200" cy="100" rx="120" ry="60" fill="none" stroke="rgba(201,162,39,0.13)" stroke-width="1"/>
				<ellipse cx="200" cy="100" rx="90" ry="45" fill="none" stroke="rgba(201,162,39,0.16)" stroke-width="1"/>
				<ellipse cx="200" cy="100" rx="65" ry="33" fill="none" stroke="rgba(201,162,39,0.20)" stroke-width="1.5"/>
				<ellipse cx="200" cy="100" rx="40" ry="20" fill="none" stroke="rgba(201,162,39,0.25)" stroke-width="1.5"/>
				<?php // Semi-transparent contour lines (lighter, offset). ?>
				<ellipse cx="195" cy="105" rx="160" ry="80" fill="none" stroke="rgba(168,196,212,0.06)" stroke-width="0.8"/>
				<ellipse cx="205" cy="95" rx="130" ry="65" fill="none" stroke="rgba(168,196,212,0.08)" stroke-width="0.8"/>
				<ellipse cx="198" cy="102" rx="100" ry="50" fill="none" stroke="rgba(168,196,212,0.10)" stroke-width="0.8"/>
				<?php // Center pin icon circle. ?>
				<circle cx="200" cy="90" r="22" fill="rgba(44,177,188,0.12)"/>
				<?php // Location pin icon. ?>
				<g transform="translate(188,74)" fill="rgba(255,255,255,0.7)">
					<path d="M12 0C8.13 0 5 3.13 5 7c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
				</g>
				<?php if ( $coord_text ) : ?>
					<text x="200" y="185" text-anchor="middle" fill="rgba(168,196,212,0.35)" font-family="'Inter',system-ui,sans-serif" font-size="11" letter-spacing="1.5"><?php echo esc_html( $coord_text ); ?></text>
				<?php endif; ?>
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}
}
