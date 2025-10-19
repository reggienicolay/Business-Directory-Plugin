<?php

namespace BusinessDirectory\Frontend;

use BusinessDirectory\Search\FilterHandler;

class Filters {


	public static function init() {
		add_shortcode( 'business_filters', array( __CLASS__, 'render_filters' ) );
		add_shortcode( 'business_directory_complete', array( __CLASS__, 'render_complete_directory' ) );

		// Disable wpautop for our shortcodes to prevent <br> injection
		add_filter( 'the_content', array( __CLASS__, 'disable_wpautop_for_shortcodes' ), 9 );
	}

	/**
	 * Disable wpautop filter for our shortcodes
	 */
	public static function disable_wpautop_for_shortcodes( $content ) {
		if (
			has_shortcode( $content, 'business_directory_complete' ) ||
			has_shortcode( $content, 'business_filters' )
		) {
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_content', 'wptexturize' );
		}
		return $content;
	}

	/**
	 * Render complete directory (filters + map + list)
	 */
	public static function render_complete_directory( $atts = array() ) {
		$metadata = FilterHandler::get_filter_metadata();

		ob_start();
		?>
		<div class="bd-directory-wrapper" style="display: flex; gap: 20px; flex-wrap: wrap;">

			<!-- Filters Sidebar -->
			<aside class="bd-directory-sidebar" style="flex: 0 0 280px;">

<!-- Add Business CTA Button -->
<div class="bd-add-business-cta" style="margin-bottom: 24px;"><a href="<?php echo home_url( '/add-your-business/' ); ?>" class="bd-add-business-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> ADD YOUR BUSINESS</a></div>
				<?php echo self::render_filter_panel( $metadata ); ?>
			</aside>

			<!-- Main Content Area -->
			<main class="bd-directory-main" style="flex: 1; min-width: 300px;">

				<!-- Results Info Bar -->
				<div id="bd-results-info" class="bd-results-info" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px;">
					<div class="bd-result-count">
						<span id="bd-result-count-text">Loading...</span>
					</div>
					<div class="bd-sort-options" style="display: flex; align-items: center; gap: 8px;">
						<label for="bd-sort-select" style="font-size: 14px; color: #6b7280;">Sort by:</label>
						<select id="bd-sort-select" class="bd-select" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
							<option value="distance">Distance</option>
							<option value="rating">Rating</option>
							<option value="newest">Newest</option>
							<option value="name">Name (A-Z)</option>
						</select>
					</div>
				</div>

				<!-- Map Container -->
				<div id="bd-map" style="height: 600px; margin-bottom: 30px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f5f5f5;"></div>

				<!-- Business List Container -->
				<div id="bd-business-list" style="margin-top: 20px;">
					<p style="text-align: center; padding: 40px; color: #6b7280;">Loading businesses...</p>
				</div>

			</main>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render filter panel only (for sidebar use)
	 */
	public static function render_filters( $atts = array() ) {
		$metadata = FilterHandler::get_filter_metadata();
		return self::render_filter_panel( $metadata );
	}

	/**
	 * Render filter panel HTML
	 */
	private static function render_filter_panel( $metadata ) {
		ob_start();
		?>
		<div id="bd-filter-panel" class="bd-filter-panel">

			<!-- Mobile Toggle Button -->
			<button class="bd-filter-toggle bd-mobile-only" aria-label="Toggle Filters">
				<span>Filters</span>
				<span class="bd-filter-count" style="display: none;"></span>
			</button>

			<div class="bd-filter-content">

				<!-- Header -->
				<div class="bd-filter-header" style="margin-bottom: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
						<h3 style="margin: 0;">Filters</h3>
						<button class="bd-filter-close bd-mobile-only" style="font-size: 24px; line-height: 1; padding: 0; background: none; border: none; cursor: pointer; color: #6b7280;">×</button>
					</div>
					<button id="bd-clear-filters" style="display: inline-block; font-size: 13px; color: #9333ea; text-decoration: none; font-weight: 500; background: none; border: none; padding: 0; cursor: pointer; font-family: inherit;">
						Clear all filters
					</button>
				</div>

				<!-- Keyword Search -->
				<div class="bd-filter-group">
					<label class="bd-filter-label" data-label="SEARCH"></label> <input type="text" id="bd-keyword-search" class="bd-filter-input" placeholder="Search businesses..." />
				</div>

				<!-- Location -->
				<div class="bd-filter-group">
					<label class="bd-filter-label" data-label="LOCATION">Location</label>
					<div class="bd-location-buttons">
						<button id="bd-near-me-btn" class="bd-btn bd-btn-primary">Near Me</button>
						<button id="bd-use-city-btn" class="bd-btn bd-btn-secondary">Use City</button>
					</div>
					<div id="bd-manual-location" style="display: none; margin-top: 10px;">
						<input type="text" id="bd-city-input" class="bd-filter-input" placeholder="Enter city name" style="margin-bottom: 8px;" />
						<button id="bd-city-submit" class="bd-btn bd-btn-small bd-btn-block">Go</button>
					</div>
					<div id="bd-location-display" class="bd-location-display" style="display: none;"></div>
					<div id="bd-radius-container" style="display: none; margin-top: 12px;">
						<label class="bd-radius-label">Radius: <span id="bd-radius-value">10</span> miles</label>
						<input type="range" id="bd-radius-slider" min="1" max="50" value="10" class="bd-slider" />
					</div>
				</div>

				<!-- Categories -->
				<?php if ( ! empty( $metadata['categories'] ) ) : ?>
					<div class="bd-filter-group">
						<label class="bd-filter-label" data-label="CATEGORIES">Categories</label>
						<div class="bd-checkbox-group">
							<?php foreach ( $metadata['categories'] as $category ) : ?>
								<label class="bd-checkbox-label">
									<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category['id'] ); ?>" />
									<span><?php echo esc_html( $category['name'] ); ?> (<?php echo $category['count']; ?>)</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Areas -->
				<?php if ( ! empty( $metadata['areas'] ) ) : ?>
					<div class="bd-filter-group">
						<label class="bd-filter-label" data-label="AREAS"> Areas</label>
						<div class="bd-checkbox-group">
							<?php foreach ( $metadata['areas'] as $area ) : ?>
								<label class="bd-checkbox-label">
									<input type="checkbox" name="areas[]" value="<?php echo esc_attr( $area['id'] ); ?>" />
									<span><?php echo esc_html( $area['name'] ); ?> (<?php echo $area['count']; ?>)</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Price Level -->
				<div class="bd-filter-group">
					<label class="bd-filter-label" data-label="PRICE LEVEL">Price Level</label>
					<div class="bd-checkbox-group">
						<?php
						$price_levels = array(
							'$'    => 'Budget',
							'$$'   => 'Moderate',
							'$$$'  => 'Expensive',
							'$$$$' => 'Very Expensive',
						);
						foreach ( $price_levels as $symbol => $label ) :
							$count = $metadata['price_levels'][ $symbol ] ?? 0;
							?>
							<label class="bd-checkbox-label">
								<input type="checkbox" name="price_level[]" value="<?php echo esc_attr( $symbol ); ?>" />
								<span><?php echo esc_html( $symbol . ' ' . $label ); ?> (<?php echo $count; ?>)</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Rating -->
				<div class="bd-filter-group">
					<label class="bd-filter-label" data-label="RATING">Rating</label>
					<div class="bd-radio-group">
						<label class="bd-radio-label">
							<input type="radio" name="min_rating" value="" checked />
							<span>All ratings</span>
						</label>
						<?php foreach ( $metadata['rating_ranges'] as $range => $count ) : ?>
							<label class="bd-radio-label">
								<input type="radio" name="min_rating" value="<?php echo esc_attr( rtrim( $range, '+' ) ); ?>" />
								<span class="bd-star-option" data-stars="<?php echo esc_attr( rtrim( $range, '+' ) ); ?>">
									<?php echo esc_html( $range ); ?> stars (<?php echo $count; ?>)
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Open Now -->
				<div class="bd-filter-group">
					<label class="bd-checkbox-label bd-toggle-label">
						<input type="checkbox" id="bd-open-now" name="open_now" value="1" />
						<span>🕐 Open Now</span>
					</label>
				</div>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Initialize
Filters::init();
