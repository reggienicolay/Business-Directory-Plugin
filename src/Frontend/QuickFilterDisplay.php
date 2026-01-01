<?php
/**
 * Quick Filter Directory Display
 *
 * Renders the modern quick filter directory layout with
 * experiences bar, dynamic tags, and featured businesses.
 *
 * @package BusinessDirectory
 */

namespace BD\Frontend;

use BD\Admin\FeaturedAdmin;
use BD\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QuickFilterDisplay {

	/**
	 * Tag icon mapping (slug => Font Awesome class).
	 * Fallback: fa-tag for unmapped tags.
	 */
	const TAG_ICONS = array(
		// Food & Drink
		'bar'             => 'fa-beer',
		'wine-tasting'    => 'fa-wine-glass-alt',
		'wine'            => 'fa-wine-glass-alt',
		'brewery'         => 'fa-beer',
		'coffee'          => 'fa-coffee',
		'cafe'            => 'fa-coffee',
		'restaurant'      => 'fa-utensils',
		'fine-dining'     => 'fa-utensils',
		'casual-dining'   => 'fa-utensils',
		'fast-food'       => 'fa-hamburger',
		'pizza'           => 'fa-pizza-slice',
		'bakery'          => 'fa-bread-slice',
		'dessert'         => 'fa-ice-cream',
		'vegan'           => 'fa-leaf',
		'vegetarian'      => 'fa-seedling',
		'organic'         => 'fa-leaf',

		// Location/Vibe
		'downtown'        => 'fa-city',
		'outdoor-seating' => 'fa-umbrella-beach',
		'patio'           => 'fa-umbrella-beach',
		'rooftop'         => 'fa-building',
		'waterfront'      => 'fa-water',
		'scenic'          => 'fa-mountain',

		// Family/Pet
		'family-friendly' => 'fa-child',
		'kid-friendly'    => 'fa-child',
		'dog-friendly'    => 'fa-dog',
		'pet-friendly'    => 'fa-paw',

		// Services
		'delivery'        => 'fa-truck',
		'takeout'         => 'fa-shopping-bag',
		'catering'        => 'fa-concierge-bell',
		'reservations'    => 'fa-calendar-check',
		'wifi'            => 'fa-wifi',
		'parking'         => 'fa-parking',
		'free-parking'    => 'fa-parking',

		// Activities
		'live-music'      => 'fa-music',
		'entertainment'   => 'fa-theater-masks',
		'sports-bar'      => 'fa-football-ball',
		'trivia'          => 'fa-question-circle',
		'happy-hour'      => 'fa-cocktail',
		'brunch'          => 'fa-sun',
		'late-night'      => 'fa-moon',

		// Shopping
		'boutique'        => 'fa-store',
		'gifts'           => 'fa-gift',
		'local-products'  => 'fa-map-marker-alt',
		'handmade'        => 'fa-hand-paper',
		'antiques'        => 'fa-clock',

		// Services
		'spa'             => 'fa-spa',
		'salon'           => 'fa-cut',
		'fitness'         => 'fa-dumbbell',
		'yoga'            => 'fa-pray',
		'massage'         => 'fa-hands',

		// Health
		'medical'         => 'fa-stethoscope',
		'dental'          => 'fa-tooth',
		'pharmacy'        => 'fa-pills',
		'wellness'        => 'fa-heart',

		// Professional
		'legal'           => 'fa-balance-scale',
		'financial'       => 'fa-chart-line',
		'real-estate'     => 'fa-home',
		'insurance'       => 'fa-shield-alt',
	);

	/**
	 * Initialize the display.
	 */
	public static function init() {
		// Assets are enqueued conditionally when the shortcode renders
	}

	/**
	 * Check if quick filter layout is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Settings::get_directory_layout() === 'quick_filter';
	}

	/**
	 * Render the quick filter directory.
	 *
	 * @param array $metadata Filter metadata from FilterHandler.
	 * @return string HTML output.
	 */
	public static function render( $metadata ) {
		self::enqueue_assets();

		$categories   = $metadata['categories'] ?? array();
		$areas        = $metadata['areas'] ?? array();
		$tags         = $metadata['tags'] ?? array();
		$featured_ids = FeaturedAdmin::get_featured_ids();

		ob_start();
		?>
		<div class="bd-qf-wrapper">

			<!-- Experiences Bar -->
			<div class="bd-qf-experiences">
				<span class="bd-qf-experiences-label">Explore:</span>
				<div class="bd-qf-experiences-list">
					<?php foreach ( $categories as $category ) : ?>
						<button type="button" 
							class="bd-qf-experience-btn" 
							data-category-id="<?php echo esc_attr( $category['id'] ); ?>"
							data-category-slug="<?php echo esc_attr( $category['slug'] ); ?>">
							<?php echo esc_html( $category['name'] ); ?>
						</button>
					<?php endforeach; ?>
					<button type="button" class="bd-qf-experiences-expand" id="bd-qf-experiences-expand">
						See All <i class="fas fa-chevron-down"></i>
					</button>
				</div>
			</div>

			<!-- Search Row -->
			<div class="bd-qf-search-row">
				<div class="bd-qf-search-input-wrap">
					<i class="fas fa-search"></i>
					<input type="text" 
						id="bd-qf-search" 
						class="bd-qf-search-input" 
						placeholder="Search businesses..." 
						autocomplete="off">
				</div>

				<div class="bd-qf-area-dropdown">
					<select id="bd-qf-area" class="bd-qf-area-select">
						<option value="">Tri-Valley (All Cities)</option>
						<?php foreach ( $areas as $area ) : ?>
							<option value="<?php echo esc_attr( $area['id'] ); ?>" data-slug="<?php echo esc_attr( $area['slug'] ); ?>">
								<?php echo esc_html( $area['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="button" id="bd-qf-more-filters" class="bd-qf-more-filters-btn">
					<i class="fas fa-sliders-h"></i>
					<span>Filters</span>
				</button>
			</div>

			<!-- Tags Row -->
			<div class="bd-qf-tags-row">
				<button type="button" class="bd-qf-tags-toggle" id="bd-qf-tags-toggle" title="Toggle tags">
					<i class="fas fa-chevron-down"></i>
				</button>
				<span class="bd-qf-tags-label">Refine:</span>
				<div class="bd-qf-tags-scroll">
					<div class="bd-qf-tags-list" id="bd-qf-tags-list">
						<?php
						$visible_count = 0;
						foreach ( $tags as $tag ) :
							$icon_class = self::get_tag_icon( $tag['slug'] );
							$hidden     = $visible_count >= 8 ? 'bd-qf-tag-hidden' : '';
							?>
							<button type="button" 
								class="bd-qf-tag-btn <?php echo esc_attr( $hidden ); ?>" 
								data-tag-id="<?php echo esc_attr( $tag['id'] ); ?>"
								data-tag-slug="<?php echo esc_attr( $tag['slug'] ); ?>">
								<i class="fas <?php echo esc_attr( $icon_class ); ?>"></i>
								<?php echo esc_html( $tag['name'] ); ?>
							</button>
							<?php
							++$visible_count;
						endforeach;
						?>
					</div>
					<?php if ( count( $tags ) > 8 ) : ?>
						<button type="button" class="bd-qf-tags-more" id="bd-qf-tags-more">
							+<?php echo count( $tags ) - 8; ?> more
						</button>
					<?php endif; ?>
				</div>
			</div>

			<!-- Results Bar -->
			<div class="bd-qf-results-bar">
				<div class="bd-qf-results-left">
					<span class="bd-qf-results-count" id="bd-qf-results-count">Loading...</span>
					<button type="button" class="bd-qf-clear-all" id="bd-qf-clear-all" style="display: none;">
						Clear All <span class="bd-qf-filter-count"></span>
					</button>
				</div>
				<div class="bd-qf-results-right">
					<div class="bd-qf-active-filters" id="bd-qf-active-filters">
						<!-- Active filter pills inserted by JS -->
					</div>
					<div class="bd-qf-sort-wrap">
						<select id="bd-qf-sort" class="bd-qf-sort-select">
							<option value="featured">Featured</option>
							<option value="distance">Distance</option>
							<option value="rating">Rating</option>
							<option value="newest">Newest</option>
							<option value="name">Name (A-Z)</option>
						</select>
					</div>
					<div class="bd-qf-view-toggle">
						<button type="button" class="bd-qf-view-btn bd-qf-view-split-btn" data-view="split" title="Map + List View">
							<i class="fas fa-columns"></i>
						</button>
						<button type="button" class="bd-qf-view-btn" data-view="grid" title="Grid View">
							<i class="fas fa-th"></i>
						</button>
						<button type="button" class="bd-qf-view-btn" data-view="list" title="List View">
							<i class="fas fa-list"></i>
						</button>
					</div>
				</div>
			</div>

			<!-- Main Content Area (supports split view) -->
			<div id="bd-qf-main-content" class="bd-qf-main-content">
				<!-- Map -->
				<div id="bd-qf-map" class="bd-qf-map"></div>

				<!-- Business Grid/List -->
				<div id="bd-qf-businesses" class="bd-qf-businesses bd-qf-view-list">
					<p class="bd-qf-loading">Loading businesses...</p>
				</div>
			</div>

			<!-- Add Business CTA -->
			<div class="bd-qf-add-business">
				<a href="<?php echo esc_url( home_url( '/add-your-business/' ) ); ?>" class="bd-qf-add-business-btn">
					<i class="fas fa-plus"></i>
					Add Your Business
				</a>
			</div>

		</div>

		<!-- More Filters Modal -->
		<div id="bd-qf-filters-modal" class="bd-qf-filters-modal" style="display: none;">
			<div class="bd-qf-filters-modal-content">
				<div class="bd-qf-filters-modal-header">
					<h3>More Filters</h3>
					<button type="button" class="bd-qf-filters-modal-close">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="bd-qf-filters-modal-body">
					<!-- Price Level -->
					<div class="bd-qf-filter-group">
						<label class="bd-qf-filter-label">Price Level</label>
						<div class="bd-qf-price-buttons">
							<button type="button" class="bd-qf-price-btn" data-price="$">$</button>
							<button type="button" class="bd-qf-price-btn" data-price="$$">$$</button>
							<button type="button" class="bd-qf-price-btn" data-price="$$$">$$$</button>
							<button type="button" class="bd-qf-price-btn" data-price="$$$$">$$$$</button>
						</div>
					</div>

					<!-- Rating -->
					<div class="bd-qf-filter-group">
						<label class="bd-qf-filter-label">Minimum Rating</label>
						<div class="bd-qf-rating-buttons">
							<button type="button" class="bd-qf-rating-btn" data-rating="4">4+ Stars</button>
							<button type="button" class="bd-qf-rating-btn" data-rating="3">3+ Stars</button>
							<button type="button" class="bd-qf-rating-btn" data-rating="2">2+ Stars</button>
						</div>
					</div>

					<!-- Open Now -->
					<div class="bd-qf-filter-group">
						<label class="bd-qf-toggle-label">
							<input type="checkbox" id="bd-qf-open-now">
							<span>Open Now</span>
						</label>
					</div>
				</div>
				<div class="bd-qf-filters-modal-footer">
					<button type="button" class="bd-qf-filters-clear">Clear</button>
					<button type="button" class="bd-qf-filters-apply">Apply Filters</button>
				</div>
			</div>
		</div>

		<!-- Hidden data for JS -->
		<script type="application/json" id="bd-qf-data">
			<?php
			echo wp_json_encode(
				array(
					'categories'  => $categories,
					'areas'       => $areas,
					'tags'        => $tags,
					'featuredIds' => $featured_ids,
					'tagIcons'    => self::TAG_ICONS,
				)
			);
			?>
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the icon class for a tag slug.
	 *
	 * @param string $slug Tag slug.
	 * @return string Font Awesome class.
	 */
	public static function get_tag_icon( $slug ) {
		return self::TAG_ICONS[ $slug ] ?? 'fa-tag';
	}

	/**
	 * Enqueue Quick Filter assets.
	 */
	private static function enqueue_assets() {
		// CSS
		wp_enqueue_style(
			'bd-quick-filters',
			BD_PLUGIN_URL . 'assets/css/quick-filters.css',
			array(),
			BD_VERSION
		);

		// Map markers CSS (heart icons)
		wp_enqueue_style(
			'bd-map-markers',
			BD_PLUGIN_URL . 'assets/css/map-markers.css',
			array(),
			BD_VERSION
		);

		// Leaflet MarkerCluster CSS (if not already loaded)
		if ( ! wp_style_is( 'leaflet-markercluster', 'enqueued' ) ) {
			wp_enqueue_style(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css',
				array(),
				'1.4.1'
			);
			wp_enqueue_style(
				'leaflet-markercluster-default',
				'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css',
				array(),
				'1.4.1'
			);
		}

		// Font Awesome (if not already loaded)
		if ( ! wp_style_is( 'font-awesome', 'enqueued' ) && ! wp_style_is( 'fontawesome', 'enqueued' ) ) {
			wp_enqueue_style(
				'font-awesome-5',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				array(),
				'5.15.4'
			);
		}

		// Leaflet MarkerCluster JS (if not already loaded)
		if ( ! wp_script_is( 'leaflet-markercluster', 'enqueued' ) ) {
			wp_enqueue_script(
				'leaflet-markercluster',
				'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js',
				array(),
				'1.4.1',
				true
			);
		}

		// JS
		wp_enqueue_script(
			'bd-quick-filters',
			BD_PLUGIN_URL . 'assets/js/quick-filters.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		// Localize
		wp_localize_script(
			'bd-quick-filters',
			'bdQuickFilters',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'bd/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'noResults'    => __( 'No businesses found matching your criteria.', 'business-directory' ),
					'loading'      => __( 'Loading...', 'business-directory' ),
					/* translators: %d: number of businesses found */
					'resultsCount' => __( '%d results', 'business-directory' ),
					'resultCount'  => __( '1 result', 'business-directory' ),
				),
			)
		);

		// Also enqueue lists.js for Save to List functionality
		// These are registered by ListManager class
		if ( wp_script_is( 'bd-lists', 'registered' ) && ! wp_script_is( 'bd-lists', 'enqueued' ) ) {
			wp_enqueue_script( 'bd-lists' );
		}
		if ( wp_style_is( 'bd-lists', 'registered' ) && ! wp_style_is( 'bd-lists', 'enqueued' ) ) {
			wp_enqueue_style( 'bd-lists' );
		}
	}

	/**
	 * Get tags associated with a category (derived from business data).
	 * Cached for 1 hour.
	 *
	 * @param int $category_id Category term ID.
	 * @return array Tag data.
	 */
	public static function get_tags_for_category( $category_id ) {
		$cache_key = 'bd_category_tags_' . $category_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get all businesses in this category
		$business_ids = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'bd_category',
						'field'    => 'term_id',
						'terms'    => $category_id,
					),
				),
			)
		);

		if ( empty( $business_ids ) ) {
			set_transient( $cache_key, array(), HOUR_IN_SECONDS );
			return array();
		}

		// Get tags used by these businesses
		$tags = wp_get_object_terms(
			$business_ids,
			'bd_tag',
			array(
				'orderby' => 'count',
				'order'   => 'DESC',
			)
		);

		if ( is_wp_error( $tags ) ) {
			return array();
		}

		$result = array();
		foreach ( $tags as $tag ) {
			$result[] = array(
				'id'    => $tag->term_id,
				'name'  => $tag->name,
				'slug'  => $tag->slug,
				'count' => $tag->count,
				'icon'  => self::get_tag_icon( $tag->slug ),
			);
		}

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}
}
