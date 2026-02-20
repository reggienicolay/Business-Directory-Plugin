<?php
/**
 * Explore Navigation Renderer
 *
 * Renders page-level navigation and structural elements for explore
 * pages: pagination, discovery bar, cross-links, tag clouds, city
 * statistics, other-cities navigation, and editorial intro sections.
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
 * Class ExploreNavigationRenderer
 */
class ExploreNavigationRenderer {

	/**
	 * Area terms (loaded once per request, shared by discovery bar + other cities).
	 *
	 * @var array|null
	 */
	private static $area_terms = null;

	/**
	 * Render pagination links.
	 *
	 * @param int    $total_pages Total number of pages.
	 * @param int    $current     Current page number.
	 * @param string $base_url    Base URL for pagination links.
	 * @return string Pagination HTML, or empty string if single page.
	 */
	public static function render_pagination( $total_pages, $current, $base_url ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$links = paginate_links(
			array(
				'base'      => trailingslashit( $base_url ) . 'page/%#%/',
				'format'    => '',
				'current'   => $current,
				'total'     => $total_pages,
				'prev_text' => '<i class="fas fa-chevron-left"></i> ' . __( 'Previous', 'business-directory' ),
				'next_text' => __( 'Next', 'business-directory' ) . ' <i class="fas fa-chevron-right"></i>',
				'type'      => 'list',
				'mid_size'  => 2,
			)
		);

		if ( ! $links ) {
			return '';
		}

		return '<nav class="bd-explore-pagination" aria-label="' . esc_attr__( 'Business listings pagination', 'business-directory' ) . '">' . $links . '</nav>';
	}

	/**
	 * Render the sort dropdown (rating, newest, alphabetical).
	 *
	 * Uses a noscript fallback button for non-JS users.
	 * JS auto-submits on change and strips paginated path segments.
	 *
	 * @param string $current_sort Current sort key.
	 * @param string $base_url     Base URL for the form action.
	 * @return string Sort form HTML.
	 */
	public static function render_sort_dropdown( $current_sort, $base_url ) {
		$options = ExploreQuery::SORT_OPTIONS;

		ob_start();
		?>
		<form class="bd-explore-sort-form" action="<?php echo esc_url( $base_url ); ?>" method="get">
			<label for="bd-explore-sort" class="bd-explore-sort-label">
				<?php esc_html_e( 'Sort:', 'business-directory' ); ?>
			</label>
			<select name="sort" id="bd-explore-sort" class="bd-explore-sort-select">
				<?php foreach ( $options as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $current_sort, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript>
				<button type="submit" class="bd-explore-sort-btn"><?php esc_html_e( 'Apply', 'business-directory' ); ?></button>
			</noscript>
		</form>
		<script>
		(function(){
			var sel = document.getElementById('bd-explore-sort');
			if (!sel) return;
			sel.addEventListener('change', function() {
				var val = sel.value;
				var url = new URL(window.location.href);
				if (val === 'rating') {
					url.searchParams.delete('sort');
				} else {
					url.searchParams.set('sort', val);
				}
				// Reset to page 1 on sort change — paginated URLs use
				// path segments (/page/2/), so strip them.
				url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
				window.location.href = url.toString();
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the editorial intro section.
	 *
	 * @param string $area_slug Area slug.
	 * @param string $tag_slug  Tag slug (empty for city pages).
	 * @param int    $count     Business count.
	 * @return string Intro HTML, or empty string if no editorial content.
	 */
	public static function render_intro( $area_slug, $tag_slug = '', $count = 0 ) {
		$intro = ExploreEditorial::get_intro( $area_slug, $tag_slug, $count );

		if ( empty( $intro ) ) {
			return '';
		}

		return '<p class="bd-explore-intro">' . wp_kses_post( $intro ) . '</p>';
	}

	/**
	 * Render the "Also Explore" cross-linking section.
	 *
	 * @param string $area_slug Current area slug.
	 * @param string $tag_slug  Current tag slug.
	 * @param string $area_name Current area name.
	 * @param string $tag_name  Current tag name.
	 * @return string HTML, or empty string if no related content.
	 */
	public static function render_cross_links( $area_slug, $tag_slug, $area_name, $tag_name ) {
		$related_tags = ExploreQuery::get_related_tags( $area_slug, $tag_slug, 6 );
		$other_cities = ExploreQuery::get_tag_in_other_cities( $tag_slug, $area_slug );

		if ( empty( $related_tags ) && empty( $other_cities ) ) {
			return '';
		}

		ob_start();
		?>
		<aside class="bd-explore-also" aria-label="<?php esc_attr_e( 'Related explore pages', 'business-directory' ); ?>">
			<?php if ( ! empty( $related_tags ) ) : ?>
				<div class="bd-explore-also-section">
					<h3>
						<?php
						printf(
							/* translators: %s: City name */
							esc_html__( 'Also in %s', 'business-directory' ),
							esc_html( $area_name )
						);
						?>
					</h3>
					<div class="bd-explore-also-links">
						<?php foreach ( $related_tags as $tag ) : ?>
							<a href="<?php echo esc_url( $tag['url'] ); ?>" class="bd-explore-also-link">
								<?php echo esc_html( $tag['name'] ); ?>
								<span class="bd-explore-also-count">(<?php echo intval( $tag['count'] ); ?>)</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $other_cities ) ) : ?>
				<div class="bd-explore-also-section">
					<h3>
						<?php
						printf(
							/* translators: %s: Tag name */
							esc_html__( '%s in other cities', 'business-directory' ),
							esc_html( $tag_name )
						);
						?>
					</h3>
					<div class="bd-explore-also-links">
						<?php foreach ( $other_cities as $city ) : ?>
							<a href="<?php echo esc_url( $city['url'] ); ?>" class="bd-explore-also-link">
								<?php echo esc_html( $city['name'] ); ?>
								<span class="bd-explore-also-count">(<?php echo intval( $city['count'] ); ?>)</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</aside>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the tag cloud for city landing pages.
	 *
	 * @param array  $tags      Array of tag data with counts and URLs.
	 * @param string $area_name City name.
	 * @return string HTML, or empty string if no tags.
	 */
	public static function render_tag_cloud( $tags, $area_name ) {
		if ( empty( $tags ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="bd-explore-tag-cloud">
			<h2>
				<?php
				printf(
					/* translators: %s: City name */
					esc_html__( 'What to do in %s', 'business-directory' ),
					esc_html( $area_name )
				);
				?>
			</h2>
			<div class="bd-explore-tag-cloud-list">
				<?php foreach ( $tags as $tag ) : ?>
					<a href="<?php echo esc_url( $tag['url'] ); ?>" class="bd-explore-tag-cloud-item">
						<span class="bd-explore-tag-name"><?php echo esc_html( $tag['name'] ); ?></span>
						<span class="bd-explore-tag-count"><?php echo intval( $tag['count'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render city stats bar for city landing page.
	 *
	 * @param int    $business_count Total businesses in city.
	 * @param string $area_slug      City slug.
	 * @return string HTML.
	 */
	public static function render_city_stats( $business_count, $area_slug ) {
		$review_count = self::get_city_review_count( $area_slug );

		ob_start();
		?>
		<div class="bd-explore-city-stats">
			<div class="bd-explore-stat">
				<span class="bd-explore-stat-number"><?php echo intval( $business_count ); ?></span>
				<span class="bd-explore-stat-label"><?php esc_html_e( 'businesses', 'business-directory' ); ?></span>
			</div>
			<?php if ( $review_count > 0 ) : ?>
				<div class="bd-explore-stat">
					<span class="bd-explore-stat-number"><?php echo intval( $review_count ); ?></span>
					<span class="bd-explore-stat-label"><?php esc_html_e( 'reviews', 'business-directory' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render other cities navigation for city landing page footer.
	 *
	 * @param string $exclude_slug Current city slug to exclude.
	 * @return string HTML, or empty string if no other cities.
	 */
	public static function render_other_cities( $exclude_slug ) {
		$areas = self::get_area_terms();

		if ( empty( $areas ) ) {
			return '';
		}

		$other = array_filter(
			$areas,
			function ( $term ) use ( $exclude_slug ) {
				return $term->slug !== $exclude_slug;
			}
		);

		if ( empty( $other ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="bd-explore-other-cities">
			<h3><?php esc_html_e( 'Explore other cities', 'business-directory' ); ?></h3>
			<div class="bd-explore-other-cities-list">
				<?php foreach ( $other as $area ) : ?>
					<a href="<?php echo esc_url( ExploreRouter::get_explore_url( $area->slug ) ); ?>" class="bd-explore-city-link">
						<?php echo esc_html( $area->name ); ?>
						<span class="bd-explore-city-count">(<?php echo intval( $area->count ); ?>)</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a lightweight discovery bar.
	 *
	 * NOT a duplicate of Quick Filters — this is a bridge that links users
	 * to the full interactive directory with pre-applied URL params.
	 * Quick Filters JS already has readUrlParams() that reads ?category=,
	 * ?area=, ?tags=, ?search= from the URL. So we just build the right link.
	 *
	 * Google sees static HTML. Users see a bridge to the real product.
	 *
	 * @param string      $area_slug Current area slug (pre-selected city).
	 * @param string|null $tag_slug  Current tag slug (pre-selected tag).
	 * @return string HTML.
	 */
	public static function render_discovery_bar( $area_slug = '', $tag_slug = null ) {
		/**
		 * Filter the directory page URL that the discovery bar links to.
		 *
		 * @since 2.3.0
		 *
		 * @param string $url Default directory URL.
		 */
		$directory_url = apply_filters( 'bd_directory_url', home_url( '/places/' ) );

		// Get areas for the city dropdown.
		$areas = self::get_area_terms();

		ob_start();
		?>
		<div class="bd-explore-discovery" role="search" aria-label="<?php esc_attr_e( 'Search businesses', 'business-directory' ); ?>">
			<form class="bd-explore-discovery-form"
				data-action="<?php echo esc_url( $directory_url ); ?>"
				<?php
				if ( $tag_slug ) :
					?>
					data-tags="<?php echo esc_attr( $tag_slug ); ?>"<?php endif; ?>>
				<div class="bd-explore-discovery-input-wrap">
					<i class="fas fa-search" aria-hidden="true"></i>
					<input type="text"
						class="bd-explore-discovery-input"
						placeholder="<?php esc_attr_e( 'Search places, restaurants, activities...', 'business-directory' ); ?>"
						autocomplete="off">
				</div>
				<div class="bd-explore-discovery-city">
					<select class="bd-explore-discovery-select">
						<option value=""><?php esc_html_e( 'All Cities', 'business-directory' ); ?></option>
						<?php foreach ( $areas as $area_term ) : ?>
							<option value="<?php echo esc_attr( $area_term->slug ); ?>"
								<?php selected( $area_slug, $area_term->slug ); ?>>
								<?php echo esc_html( $area_term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="bd-explore-discovery-btn">
					<i class="fas fa-search" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Explore', 'business-directory' ); ?></span>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get area terms (cached per request).
	 *
	 * Both render_discovery_bar() and render_other_cities() need the
	 * same bd_area terms. This loads them once and serves both.
	 *
	 * @return array Array of WP_Term objects, or empty array.
	 */
	private static function get_area_terms() {
		if ( null !== self::$area_terms ) {
			return self::$area_terms;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		self::$area_terms = is_wp_error( $terms ) ? array() : $terms;

		return self::$area_terms;
	}

	/**
	 * Get total review count for businesses in a city.
	 *
	 * @param string $area_slug City slug.
	 * @return int Total reviews.
	 */
	private static function get_city_review_count( $area_slug ) {
		$cache_key = 'city_reviews_' . $area_slug;
		$cached    = wp_cache_get( $cache_key, ExploreQuery::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p
					ON pm.post_id = p.ID
					AND p.post_type = 'bd_business'
					AND p.post_status = 'publish'
				INNER JOIN {$wpdb->term_relationships} tr
					ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tr.term_taxonomy_id = tt.term_taxonomy_id
					AND tt.taxonomy = 'bd_area'
				INNER JOIN {$wpdb->terms} t
					ON tt.term_id = t.term_id
					AND t.slug = %s
				WHERE pm.meta_key = 'bd_review_count'",
				$area_slug
			)
		);

		wp_cache_set( $cache_key, $count, ExploreQuery::CACHE_GROUP, HOUR_IN_SECONDS );

		return $count;
	}
}
