<?php
/**
 * Explore Pages Query Builder
 *
 * Builds and executes WP_Query for explore pages with batch
 * cache priming to eliminate N+1 queries. Mirrors the data
 * format from BusinessEndpoint for consistent card rendering.
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreQuery
 */
class ExploreQuery {

	/**
	 * Businesses per page on explore pages.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * Minimum businesses required for an intersection page to exist.
	 *
	 * @var int
	 */
	const MIN_BUSINESSES = 2;

	/**
	 * Cache group for explore data.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'bd_explore';

	/**
	 * Allowed sort options.
	 *
	 * Matches the directory homepage Quick Filters sort dropdown.
	 * 'rating' is the default for explore pages (SEO intent = "best").
	 *
	 * @var array
	 */
	const SORT_OPTIONS = array(
		'rating'   => 'Top Rated',
		'featured' => 'Featured',
		'reviews'  => 'Most Reviewed',
		'newest'   => 'Newest',
		'name'     => 'A–Z',
	);

	/**
	 * Validate and sanitize a sort parameter.
	 *
	 * @param string $sort Raw sort value from URL.
	 * @return string Validated sort key, defaults to 'rating'.
	 */
	public static function validate_sort( $sort ) {
		$sort = sanitize_key( $sort );
		return array_key_exists( $sort, self::SORT_OPTIONS ) ? $sort : 'rating';
	}

	/**
	 * Build WP_Query orderby + meta_query args for a given sort.
	 *
	 * Featured sort uses 'title' ordering at the DB level, then
	 * PHP-reorders featured businesses to the top (same pattern
	 * as Quick Filters JS sortFeaturedFirst).
	 *
	 * @param string $sort Validated sort key.
	 * @return array Partial WP_Query args (meta_query + orderby).
	 */
	private static function build_sort_args( $sort ) {
		switch ( $sort ) {
			case 'newest':
				return array(
					'meta_query' => array(),
					'orderby'    => 'date',
					'order'      => 'DESC',
				);

			case 'name':
				return array(
					'meta_query' => array(),
					'orderby'    => 'title',
					'order'      => 'ASC',
				);

			case 'featured':
				// Featured sort is handled via posts_orderby filter in
				// apply_featured_orderby() — MySQL FIELD() ensures correct
				// pagination. Fallback here is title ASC (used if no
				// featured IDs exist and the filter short-circuits).
				return array(
					'meta_query' => array(),
					'orderby'    => 'title',
					'order'      => 'ASC',
				);

			case 'reviews':
				return array(
					'meta_query' => array(
						'relation'       => 'OR',
						'reviews_clause' => array(
							'key'     => 'bd_review_count',
							'compare' => 'EXISTS',
							'type'    => 'NUMERIC',
						),
						'no_reviews'     => array(
							'key'     => 'bd_review_count',
							'compare' => 'NOT EXISTS',
						),
					),
					'orderby'    => array(
						'reviews_clause' => 'DESC',
						'title'          => 'ASC',
					),
				);

			case 'rating':
			default:
				return array(
					'meta_query' => array(
						'relation'      => 'OR',
						'rating_clause' => array(
							'key'     => 'bd_avg_rating',
							'compare' => 'EXISTS',
							'type'    => 'NUMERIC',
						),
						'no_rating'     => array(
							'key'     => 'bd_avg_rating',
							'compare' => 'NOT EXISTS',
						),
					),
					'orderby'    => array(
						'rating_clause' => 'DESC',
						'title'         => 'ASC',
					),
				);
		}
	}

	/**
	 * Apply MySQL FIELD() ordering for featured sort.
	 *
	 * Must be called BEFORE WP_Query and removed AFTER.
	 * Uses FIELD() so featured businesses sort correctly across
	 * paginated results — not just within the current page.
	 *
	 * SQL produced:
	 *   ORDER BY FIELD(ID, 5, 12, 3) = 0,
	 *            FIELD(ID, 5, 12, 3),
	 *            post_title ASC
	 *
	 * This puts featured IDs first (in admin drag order),
	 * then everything else alphabetically.
	 *
	 * @return bool True if filter was applied, false if no featured IDs.
	 */
	private static function apply_featured_orderby() {
		if ( ! class_exists( '\\BD\\Admin\\FeaturedAdmin' ) ) {
			return false;
		}

		$featured_ids = \BD\Admin\FeaturedAdmin::get_featured_ids();
		if ( empty( $featured_ids ) ) {
			return false;
		}

		// Store IDs for the filter closure.
		self::$featured_orderby_ids = $featured_ids;

		add_filter( 'posts_orderby', array( __CLASS__, 'filter_featured_orderby' ), 10, 2 );
		return true;
	}

	/**
	 * Remove the featured orderby filter after query execution.
	 */
	private static function remove_featured_orderby() {
		remove_filter( 'posts_orderby', array( __CLASS__, 'filter_featured_orderby' ), 10 );
		self::$featured_orderby_ids = null;
	}

	/**
	 * Featured IDs for the posts_orderby filter (temporary).
	 *
	 * @var array|null
	 */
	private static $featured_orderby_ids = null;

	/**
	 * Filter callback for posts_orderby during featured sort.
	 *
	 * @param string    $orderby Existing ORDER BY clause.
	 * @param \WP_Query $query   Current query object.
	 * @return string Modified ORDER BY clause.
	 */
	public static function filter_featured_orderby( $orderby, $query ) {
		if ( empty( self::$featured_orderby_ids ) ) {
			return $orderby;
		}

		// Only modify bd_business queries.
		if ( 'bd_business' !== $query->get( 'post_type' ) ) {
			return $orderby;
		}

		global $wpdb;

		// Sanitize IDs (already absint'd by FeaturedAdmin, but belt-and-suspenders).
		$ids_csv = implode( ',', array_map( 'absint', self::$featured_orderby_ids ) );

		// FIELD(ID, 5,12,3) = 0 → 0 for featured (sort first), 1 for rest.
		// FIELD(ID, 5,12,3)     → preserves admin drag order within featured.
		// post_title ASC        → alphabetical within non-featured.
		return sprintf(
			'FIELD(%1$s.ID, %2$s) = 0, FIELD(%1$s.ID, %2$s), %1$s.post_title ASC',
			$wpdb->posts,
			$ids_csv
		);
	}

	/**
	 * Get businesses for a tag × city intersection page.
	 *
	 * @param string $area_slug Area (city) slug.
	 * @param string $tag_slug  Tag slug.
	 * @param int    $paged     Page number.
	 * @param string $sort      Sort key (validated via validate_sort).
	 * @return array {
	 *     @type array    $businesses Formatted business data.
	 *     @type int      $total      Total matching businesses.
	 *     @type int      $pages      Total pages.
	 *     @type int      $page       Current page.
	 *     @type \WP_Term $area       Area term object.
	 *     @type \WP_Term $tag        Tag term object.
	 *     @type string   $sort       Applied sort key.
	 * }
	 */
	public static function get_intersection( $area_slug, $tag_slug, $paged = 1, $sort = 'rating' ) {
		$area = ExploreRouter::get_area_term( $area_slug );
		$tag  = ExploreRouter::get_tag_term( $tag_slug );

		if ( ! $area || ! $tag ) {
			return self::empty_result();
		}

		$sort_args = self::build_sort_args( $sort );

		$query_args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'tax_query'      => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'bd_area',
					'field'    => 'slug',
					'terms'    => $area_slug,
				),
				array(
					'taxonomy' => 'bd_tag',
					'field'    => 'slug',
					'terms'    => $tag_slug,
				),
			),
		);

		// Merge sort-specific args (meta_query, orderby, order).
		if ( ! empty( $sort_args['meta_query'] ) ) {
			$query_args['meta_query'] = $sort_args['meta_query'];
		}
		$query_args['orderby'] = $sort_args['orderby'];
		if ( isset( $sort_args['order'] ) ) {
			$query_args['order'] = $sort_args['order'];
		}

		// Featured sort: apply MySQL FIELD() ordering before query
		// so pagination works correctly across all pages.
		$has_featured_filter = false;
		if ( 'featured' === $sort ) {
			$has_featured_filter = self::apply_featured_orderby();
		}

		$query = new \WP_Query( $query_args );

		// Clean up featured filter immediately after query.
		if ( $has_featured_filter ) {
			self::remove_featured_orderby();
		}

		$businesses = self::format_query_results( $query );

		return array(
			'businesses' => $businesses,
			'total'      => $query->found_posts,
			'pages'      => $query->max_num_pages,
			'page'       => $paged,
			'area'       => $area,
			'tag'        => $tag,
			'sort'       => $sort,
		);
	}

	/**
	 * Get businesses for a city landing page.
	 *
	 * @param string $area_slug Area (city) slug.
	 * @param int    $paged     Page number.
	 * @param string $sort      Sort key (validated via validate_sort).
	 * @return array Same shape as get_intersection() minus 'tag'.
	 */
	public static function get_city( $area_slug, $paged = 1, $sort = 'rating' ) {
		$area = ExploreRouter::get_area_term( $area_slug );

		if ( ! $area ) {
			return self::empty_result();
		}

		$sort_args = self::build_sort_args( $sort );

		$query_args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'tax_query'      => array(
				array(
					'taxonomy' => 'bd_area',
					'field'    => 'slug',
					'terms'    => $area_slug,
				),
			),
		);

		// Merge sort-specific args (meta_query, orderby, order).
		if ( ! empty( $sort_args['meta_query'] ) ) {
			$query_args['meta_query'] = $sort_args['meta_query'];
		}
		$query_args['orderby'] = $sort_args['orderby'];
		if ( isset( $sort_args['order'] ) ) {
			$query_args['order'] = $sort_args['order'];
		}

		// Featured sort: apply MySQL FIELD() ordering before query
		// so pagination works correctly across all pages.
		$has_featured_filter = false;
		if ( 'featured' === $sort ) {
			$has_featured_filter = self::apply_featured_orderby();
		}

		$query = new \WP_Query( $query_args );

		// Clean up featured filter immediately after query.
		if ( $has_featured_filter ) {
			self::remove_featured_orderby();
		}

		$businesses = self::format_query_results( $query );

		return array(
			'businesses' => $businesses,
			'total'      => $query->found_posts,
			'pages'      => $query->max_num_pages,
			'page'       => $paged,
			'area'       => $area,
			'sort'       => $sort,
		);
	}

	/**
	 * Get all cities with business counts for the hub page.
	 *
	 * @return array Array of area terms with 'count' populated.
	 */
	public static function get_hub_data() {
		$cache_key = 'hub_data';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $areas ) ) {
			return array();
		}

		$hub_data = array();
		foreach ( $areas as $area ) {
			$hub_data[] = array(
				'term'     => $area,
				'count'    => $area->count,
				'url'      => ExploreRouter::get_explore_url( $area->slug ),
				'top_tags' => self::get_tags_for_area( $area->slug, 8 ),
			);
		}

		wp_cache_set( $cache_key, $hub_data, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $hub_data;
	}

	/**
	 * Get tags with business counts for a specific area.
	 *
	 * Only returns tags with 2+ businesses in this area.
	 *
	 * @param string $area_slug Area slug.
	 * @param int    $limit     Max tags to return (0 = all).
	 * @return array Array of tag data with counts.
	 */
	public static function get_tags_for_area( $area_slug, $limit = 0 ) {
		$cache_key = 'area_tags_' . $area_slug;

		// Layer 1: Per-request object cache.
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $limit > 0 ? array_slice( $cached, 0, $limit ) : $cached;
		}

		// Layer 2: Persistent transient (survives without Redis/Memcached).
		$transient_key = 'bd_ex_atags_' . substr( md5( $area_slug ), 0, 12 );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $limit > 0 ? array_slice( $cached, 0, $limit ) : $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT p.ID) AS business_count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON t.term_id = tt.term_id AND tt.taxonomy = 'bd_tag'
				INNER JOIN {$wpdb->term_relationships} tr_tag
					ON tt.term_taxonomy_id = tr_tag.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p
					ON tr_tag.object_id = p.ID
					AND p.post_type = 'bd_business'
					AND p.post_status = 'publish'
				INNER JOIN {$wpdb->term_relationships} tr_area
					ON p.ID = tr_area.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt_area
					ON tr_area.term_taxonomy_id = tt_area.term_taxonomy_id
					AND tt_area.taxonomy = 'bd_area'
				INNER JOIN {$wpdb->terms} t_area
					ON tt_area.term_id = t_area.term_id
					AND t_area.slug = %s
				GROUP BY t.term_id
				HAVING business_count >= %d
				ORDER BY business_count DESC",
				$area_slug,
				self::MIN_BUSINESSES
			)
		);

		$tags = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$tags[] = array(
					'term_id' => absint( $row->term_id ),
					'name'    => $row->name,
					'slug'    => $row->slug,
					'count'   => absint( $row->business_count ),
					'url'     => ExploreRouter::get_explore_url( $area_slug, $row->slug ),
				);
			}
		}

		wp_cache_set( $cache_key, $tags, self::CACHE_GROUP, HOUR_IN_SECONDS );
		set_transient( $transient_key, $tags, HOUR_IN_SECONDS );

		return $limit > 0 ? array_slice( $tags, 0, $limit ) : $tags;
	}

	/**
	 * Get related tags for cross-linking on intersection pages.
	 *
	 * Returns other tags in the same area (excluding the current tag).
	 *
	 * @param string $area_slug    Area slug.
	 * @param string $exclude_slug Tag slug to exclude.
	 * @param int    $limit        Max tags.
	 * @return array Array of tag data.
	 */
	public static function get_related_tags( $area_slug, $exclude_slug, $limit = 6 ) {
		$all_tags = self::get_tags_for_area( $area_slug );

		$filtered = array_filter(
			$all_tags,
			function ( $tag ) use ( $exclude_slug ) {
				return $tag['slug'] !== $exclude_slug;
			}
		);

		return array_slice( array_values( $filtered ), 0, $limit );
	}

	/**
	 * Get other cities that have the same tag.
	 *
	 * @param string $tag_slug          Tag slug.
	 * @param string $exclude_area_slug Area slug to exclude (current city).
	 * @return array Array of area data with counts.
	 */
	public static function get_tag_in_other_cities( $tag_slug, $exclude_area_slug ) {
		$cache_key = 'tag_cities_' . $tag_slug;

		// Layer 1: Per-request object cache.
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return self::filter_cities( $cached, $exclude_area_slug );
		}

		// Layer 2: Persistent transient (survives without Redis/Memcached).
		$transient_key = 'bd_ex_tcit_' . substr( md5( $tag_slug ), 0, 12 );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return self::filter_cities( $cached, $exclude_area_slug );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t_area.term_id, t_area.name, t_area.slug, COUNT(DISTINCT p.ID) AS business_count
				FROM {$wpdb->terms} t_area
				INNER JOIN {$wpdb->term_taxonomy} tt_area
					ON t_area.term_id = tt_area.term_id AND tt_area.taxonomy = 'bd_area'
				INNER JOIN {$wpdb->term_relationships} tr_area
					ON tt_area.term_taxonomy_id = tr_area.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p
					ON tr_area.object_id = p.ID
					AND p.post_type = 'bd_business'
					AND p.post_status = 'publish'
				INNER JOIN {$wpdb->term_relationships} tr_tag
					ON p.ID = tr_tag.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt_tag
					ON tr_tag.term_taxonomy_id = tt_tag.term_taxonomy_id
					AND tt_tag.taxonomy = 'bd_tag'
				INNER JOIN {$wpdb->terms} t_tag
					ON tt_tag.term_id = t_tag.term_id
					AND t_tag.slug = %s
				GROUP BY t_area.term_id
				HAVING business_count >= %d
				ORDER BY t_area.name ASC",
				$tag_slug,
				self::MIN_BUSINESSES
			)
		);

		$cities = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$cities[] = array(
					'term_id' => absint( $row->term_id ),
					'name'    => $row->name,
					'slug'    => $row->slug,
					'count'   => absint( $row->business_count ),
					'url'     => ExploreRouter::get_explore_url( $row->slug, $tag_slug ),
				);
			}
		}

		wp_cache_set( $cache_key, $cities, self::CACHE_GROUP, HOUR_IN_SECONDS );
		set_transient( $transient_key, $cities, HOUR_IN_SECONDS );

		return self::filter_cities( $cities, $exclude_area_slug );
	}

	/**
	 * Filter cities array to exclude a specific area slug.
	 *
	 * @param array  $cities             Array of city data.
	 * @param string $exclude_area_slug  Slug to exclude.
	 * @return array Filtered cities.
	 */
	private static function filter_cities( $cities, $exclude_area_slug ) {
		$filtered = array_filter(
			$cities,
			function ( $city ) use ( $exclude_area_slug ) {
				return $city['slug'] !== $exclude_area_slug;
			}
		);
		return array_values( $filtered );
	}

	/**
	 * Get total count of businesses for an intersection (used for 404 check).
	 *
	 * @param string $area_slug Area slug.
	 * @param string $tag_slug  Tag slug.
	 * @return int Business count.
	 */
	public static function get_intersection_count( $area_slug, $tag_slug ) {
		$cache_key = 'intersection_count_' . $area_slug . '_' . $tag_slug;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Direct COUNT is faster than WP_Query — no post objects, no meta loading.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr_area
					ON p.ID = tr_area.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt_area
					ON tr_area.term_taxonomy_id = tt_area.term_taxonomy_id
					AND tt_area.taxonomy = 'bd_area'
				INNER JOIN {$wpdb->terms} t_area
					ON tt_area.term_id = t_area.term_id
					AND t_area.slug = %s
				INNER JOIN {$wpdb->term_relationships} tr_tag
					ON p.ID = tr_tag.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt_tag
					ON tr_tag.term_taxonomy_id = tt_tag.term_taxonomy_id
					AND tt_tag.taxonomy = 'bd_tag'
				INNER JOIN {$wpdb->terms} t_tag
					ON tt_tag.term_id = t_tag.term_id
					AND t_tag.slug = %s
				WHERE p.post_type = 'bd_business'
					AND p.post_status = 'publish'",
				$area_slug,
				$tag_slug
			)
		);

		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Format WP_Query results into the same data shape as BusinessEndpoint.
	 *
	 * Uses batch cache priming to eliminate N+1 queries.
	 *
	 * @param \WP_Query $query Query object.
	 * @return array Array of formatted business data.
	 */
	private static function format_query_results( $query ) {
		if ( ! $query->have_posts() ) {
			return array();
		}

		$post_ids = wp_list_pluck( $query->posts, 'ID' );

		// Batch cache priming — eliminates N+1 queries.
		self::prime_caches( $post_ids );

		$businesses = array();
		foreach ( $query->posts as $post ) {
			$businesses[] = self::format_business( $post );
		}

		return $businesses;
	}

	/**
	 * Prime WordPress caches for a batch of post IDs.
	 *
	 * Same pattern as BusinessEndpoint::prepare_business_cache().
	 *
	 * @param array $post_ids Array of post IDs.
	 */
	private static function prime_caches( $post_ids ) {
		if ( empty( $post_ids ) ) {
			return;
		}

		// Prime post meta cache (1 query for all meta for all posts).
		update_meta_cache( 'post', $post_ids );

		// Prime term cache for all BD taxonomies (1 query per taxonomy).
		update_object_term_cache( $post_ids, 'bd_business' );
	}

	/**
	 * Format a single business post into the standard data array.
	 *
	 * After cache priming, all calls here hit cache (0 database queries).
	 * Matches the shape returned by BusinessEndpoint::format_business().
	 *
	 * @param \WP_Post $post Business post object.
	 * @return array Formatted business data.
	 */
	private static function format_business( $post ) {
		$business_id = $post->ID;

		// Featured image — real URLs only.
		// Data URIs (from PlaceholderImage) cannot pass through esc_url(),
		// so we only store http/https URLs here. The card renderer calls
		// PlaceholderImage::generate() directly when featured_image is empty.
		$featured_image = get_the_post_thumbnail_url( $business_id, 'medium' );
		if ( empty( $featured_image ) && function_exists( 'bd_get_business_image' ) ) {
			$image_data = bd_get_business_image( $business_id, 'medium' );
			if ( ! empty( $image_data['url'] ) && empty( $image_data['is_placeholder'] ) ) {
				$featured_image = $image_data['url'];
			}
		}

		// Location meta.
		$location      = null;
		$location_meta = get_post_meta( $business_id, 'bd_location', true );
		if ( is_array( $location_meta ) ) {
			$location = array(
				'lat'     => floatval( isset( $location_meta['lat'] ) ? $location_meta['lat'] : 0 ),
				'lng'     => floatval( isset( $location_meta['lng'] ) ? $location_meta['lng'] : 0 ),
				'address' => isset( $location_meta['address'] ) ? $location_meta['address'] : '',
				'city'    => isset( $location_meta['city'] ) ? $location_meta['city'] : '',
				'state'   => isset( $location_meta['state'] ) ? $location_meta['state'] : '',
				'zip'     => isset( $location_meta['zip'] ) ? $location_meta['zip'] : '',
			);
		}

		// Contact meta.
		$contact = get_post_meta( $business_id, 'bd_contact', true );
		if ( ! is_array( $contact ) ) {
			$contact = array();
		}

		// Tags.
		$tags      = array();
		$tag_terms = wp_get_post_terms( $business_id, 'bd_tag', array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		// Categories (names only, matching REST endpoint).
		$categories = array();
		$cat_terms  = wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cat_terms ) ) {
			$categories = $cat_terms;
		}

		// Areas (names only, matching REST endpoint).
		$areas      = array();
		$area_terms = wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $area_terms ) ) {
			$areas = $area_terms;
		}

		return array(
			'id'             => $business_id,
			'title'          => get_the_title( $business_id ),
			'slug'           => $post->post_name,
			'excerpt'        => get_the_excerpt( $business_id ),
			'permalink'      => get_permalink( $business_id ),
			'featured_image' => $featured_image ?: '',
			'rating'         => floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) ),
			'review_count'   => intval( get_post_meta( $business_id, 'bd_review_count', true ) ),
			'price_level'    => get_post_meta( $business_id, 'bd_price_level', true ) ?: '',
			'categories'     => $categories,
			'areas'          => $areas,
			'tags'           => $tags,
			'location'       => $location,
			'phone'          => isset( $contact['phone'] ) ? $contact['phone'] : '',
		);
	}

	/**
	 * Return empty result set.
	 *
	 * @return array Empty result array.
	 */
	private static function empty_result() {
		return array(
			'businesses' => array(),
			'total'      => 0,
			'pages'      => 0,
			'page'       => 1,
		);
	}
}
