<?php
/**
 * Explore Pages Router
 *
 * Registers rewrite rules for /explore/ URL structure and
 * routes requests to the appropriate template.
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
 * Class ExploreRouter
 *
 * Maps /explore/{area}/{tag}/ URLs to custom query vars
 * and loads the correct template via template_include.
 */
class ExploreRouter {

	/**
	 * Query var for explore page type: hub|city|intersection.
	 *
	 * @var string
	 */
	const QV_EXPLORE = 'bd_explore';

	/**
	 * Query var for area slug.
	 *
	 * @var string
	 */
	const QV_AREA = 'bd_explore_area';

	/**
	 * Query var for tag slug.
	 *
	 * @var string
	 */
	const QV_TAG = 'bd_explore_tag';

	/**
	 * Cached term objects to avoid redundant DB lookups.
	 *
	 * @var array
	 */
	private static $term_cache = array();

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'route_template' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_document_title' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_canonical' ), 1 );
	}

	/**
	 * Register rewrite rules for explore pages.
	 *
	 * Order matters — most specific rules first.
	 */
	public static function register_rewrite_rules() {
		// Intersection — paginated.
		add_rewrite_rule(
			'^explore/([^/]+)/(?!page/)([^/]+)/page/([0-9]+)/?$',
			'index.php?' . self::QV_EXPLORE . '=intersection&' . self::QV_AREA . '=$matches[1]&' . self::QV_TAG . '=$matches[2]&paged=$matches[3]',
			'top'
		);

		// Intersection.
		add_rewrite_rule(
			'^explore/([^/]+)/(?!page/)([^/]+)/?$',
			'index.php?' . self::QV_EXPLORE . '=intersection&' . self::QV_AREA . '=$matches[1]&' . self::QV_TAG . '=$matches[2]',
			'top'
		);

		// City landing — paginated.
		add_rewrite_rule(
			'^explore/([^/]+)/page/([0-9]+)/?$',
			'index.php?' . self::QV_EXPLORE . '=city&' . self::QV_AREA . '=$matches[1]&paged=$matches[2]',
			'top'
		);

		// City landing.
		add_rewrite_rule(
			'^explore/([^/]+)/?$',
			'index.php?' . self::QV_EXPLORE . '=city&' . self::QV_AREA . '=$matches[1]',
			'top'
		);

		// Hub page.
		add_rewrite_rule(
			'^explore/?$',
			'index.php?' . self::QV_EXPLORE . '=hub',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = self::QV_EXPLORE;
		$vars[] = self::QV_AREA;
		$vars[] = self::QV_TAG;
		return $vars;
	}

	/**
	 * Route to the correct template based on query vars.
	 *
	 * @param string $template Default template path.
	 * @return string Template path to load.
	 */
	public static function route_template( $template ) {
		$explore_type = get_query_var( self::QV_EXPLORE );

		if ( empty( $explore_type ) ) {
			return $template;
		}

		switch ( $explore_type ) {
			case 'hub':
				return self::get_template( 'explore-hub.php' );

			case 'city':
				$area_slug = sanitize_title( get_query_var( self::QV_AREA ) );
				if ( empty( $area_slug ) || ! self::area_exists( $area_slug ) ) {
					return self::set_404( $template );
				}
				return self::get_template( 'explore-city.php' );

			case 'intersection':
				$area_slug = sanitize_title( get_query_var( self::QV_AREA ) );
				$tag_slug  = sanitize_title( get_query_var( self::QV_TAG ) );

				if ( empty( $area_slug ) || empty( $tag_slug ) ) {
					return self::set_404( $template );
				}
				if ( ! self::area_exists( $area_slug ) || ! self::tag_exists( $tag_slug ) ) {
					return self::set_404( $template );
				}
				// Only render if 2+ businesses exist for this combination.
				if ( ExploreQuery::get_intersection_count( $area_slug, $tag_slug ) < 2 ) {
					return self::set_404( $template );
				}
				return self::get_template( 'explore-intersection.php' );

			default:
				return self::set_404( $template );
		}
	}

	/**
	 * Resolve template path.
	 *
	 * Allows themes to override templates by placing them in
	 * business-directory/templates/ directory.
	 *
	 * @param string $filename Template filename.
	 * @return string Full template path.
	 */
	private static function get_template( $filename ) {
		// Check theme override first.
		$theme_template = locate_template( 'business-directory/templates/' . $filename );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Fall back to plugin template.
		$plugin_template = BD_PLUGIN_DIR . 'templates/' . $filename;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		// Final fallback — should never reach here.
		return $plugin_template;
	}

	/**
	 * Set 404 status and return default template.
	 *
	 * @param string $template Default template.
	 * @return string 404 template.
	 */
	private static function set_404( $template ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return get_404_template();
	}

	/**
	 * Check if a bd_area term exists by slug.
	 *
	 * @param string $slug Area slug.
	 * @return bool True if exists.
	 */
	public static function area_exists( $slug ) {
		return (bool) self::get_area_term( $slug );
	}

	/**
	 * Check if a bd_tag term exists by slug.
	 *
	 * @param string $slug Tag slug.
	 * @return bool True if exists.
	 */
	public static function tag_exists( $slug ) {
		return (bool) self::get_tag_term( $slug );
	}

	/**
	 * Get area term object by slug with per-request caching.
	 *
	 * @param string $slug Area slug.
	 * @return \WP_Term|false Area term or false.
	 */
	public static function get_area_term( $slug ) {
		$cache_key = 'area_' . $slug;
		if ( isset( self::$term_cache[ $cache_key ] ) ) {
			return self::$term_cache[ $cache_key ];
		}
		$term                           = get_term_by( 'slug', $slug, 'bd_area' );
		$result                         = ( $term && ! is_wp_error( $term ) ) ? $term : false;
		self::$term_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Get tag term object by slug with per-request caching.
	 *
	 * @param string $slug Tag slug.
	 * @return \WP_Term|false Tag term or false.
	 */
	public static function get_tag_term( $slug ) {
		$cache_key = 'tag_' . $slug;
		if ( isset( self::$term_cache[ $cache_key ] ) ) {
			return self::$term_cache[ $cache_key ];
		}
		$term                           = get_term_by( 'slug', $slug, 'bd_tag' );
		$result                         = ( $term && ! is_wp_error( $term ) ) ? $term : false;
		self::$term_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Get area term object from current request.
	 *
	 * @return \WP_Term|false Area term or false.
	 */
	public static function get_current_area() {
		$slug = sanitize_title( get_query_var( self::QV_AREA ) );
		if ( empty( $slug ) ) {
			return false;
		}
		return self::get_area_term( $slug );
	}

	/**
	 * Get tag term object from current request.
	 *
	 * @return \WP_Term|false Tag term or false.
	 */
	public static function get_current_tag() {
		$slug = sanitize_title( get_query_var( self::QV_TAG ) );
		if ( empty( $slug ) ) {
			return false;
		}
		return self::get_tag_term( $slug );
	}

	/**
	 * Get the current page number.
	 *
	 * @return int Page number (1-indexed).
	 */
	public static function get_current_page() {
		$paged = absint( get_query_var( 'paged' ) );
		return max( 1, $paged );
	}

	/**
	 * Filter the document title for explore pages.
	 *
	 * @param array $title_parts Title parts array.
	 * @return array Modified title parts.
	 */
	public static function filter_document_title( $title_parts ) {
		$explore_type = get_query_var( self::QV_EXPLORE );

		if ( empty( $explore_type ) ) {
			return $title_parts;
		}

		// BD-SEO handles explore titles when active — avoid double-filtering.
		if ( class_exists( 'BusinessDirectorySEO\\Plugin' ) ) {
			return $title_parts;
		}

		switch ( $explore_type ) {
			case 'hub':
				$title_parts['title'] = __( 'Discover the Tri-Valley — Local Businesses & Experiences', 'business-directory' );
				break;

			case 'city':
				$area = self::get_current_area();
				if ( $area ) {
					$title_parts['title'] = sprintf(
						/* translators: %s: City name */
						__( 'Explore %s — Local Businesses & Experiences', 'business-directory' ),
						$area->name
					);
				}
				break;

			case 'intersection':
				$area = self::get_current_area();
				$tag  = self::get_current_tag();
				if ( $area && $tag ) {
					$title_parts['title'] = sprintf(
						/* translators: 1: Tag name, 2: City name */
						__( '%1$s in %2$s, CA — Discover Local', 'business-directory' ),
						$tag->name,
						$area->name
					);
				}
				break;
		}

		return $title_parts;
	}

	/**
	 * Generate URL for an explore page.
	 *
	 * @param string      $area_slug Area slug.
	 * @param string|null $tag_slug  Optional tag slug.
	 * @param int         $page      Page number (0 or 1 = no pagination suffix).
	 * @return string URL.
	 */
	public static function get_explore_url( $area_slug = '', $tag_slug = null, $page = 1 ) {
		$base = home_url( '/explore/' );

		if ( empty( $area_slug ) ) {
			return $base;
		}

		$url = trailingslashit( $base . sanitize_title( $area_slug ) );

		if ( ! empty( $tag_slug ) ) {
			$url = trailingslashit( $url . sanitize_title( $tag_slug ) );
		}

		if ( $page > 1 ) {
			$url = trailingslashit( $url . 'page/' . absint( $page ) );
		}

		return $url;
	}

	/**
	 * Check if current request is an explore page.
	 *
	 * @return bool True if on an explore page.
	 */
	public static function is_explore_page() {
		$explore_type = get_query_var( self::QV_EXPLORE );
		return ! empty( $explore_type );
	}

	/**
	 * Output canonical URL and robots meta for explore pages.
	 *
	 * Critical for SEO: without this, WordPress outputs no canonical
	 * for custom rewrite rule pages, causing duplicate content issues.
	 *
	 * @since 2.2.0
	 */
	public static function output_canonical() {
		if ( ! self::is_explore_page() ) {
			return;
		}

		// Prevent WordPress from outputting its own (empty) canonical.
		remove_action( 'wp_head', 'rel_canonical' );

		$explore_type = get_query_var( self::QV_EXPLORE );
		$area_slug    = sanitize_title( get_query_var( self::QV_AREA ) );
		$tag_slug     = sanitize_title( get_query_var( self::QV_TAG ) );
		$paged        = self::get_current_page();

		// Build canonical URL (page 1 = no /page/N/ suffix).
		switch ( $explore_type ) {
			case 'hub':
				$canonical = self::get_explore_url();
				break;
			case 'city':
				$canonical = self::get_explore_url( $area_slug, null, $paged );
				break;
			case 'intersection':
				$canonical = self::get_explore_url( $area_slug, $tag_slug, $paged );
				break;
			default:
				return;
		}

		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
	}

	/**
	 * Flush rewrite rules (called on activation).
	 */
	public static function flush_rules() {
		self::register_rewrite_rules();
		flush_rewrite_rules();
	}
}
