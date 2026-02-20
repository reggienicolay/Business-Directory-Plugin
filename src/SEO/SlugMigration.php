<?php
/**
 * Slug Migration - 301 Redirects
 *
 * Handles 301 redirects from old taxonomy URLs to new URLs.
 * Old: /category/{slug}/, /area/{slug}/, /tag/{slug}/
 * New: /places/category/{slug}/, /places/area/{slug}/, /places/tag/{slug}/
 *
 * Supports:
 * - Simple terms: /category/wineries/
 * - Hierarchical terms: /category/food/italian/
 * - Pagination: /category/wineries/page/2/
 * - Feeds: /category/wineries/feed/ or /category/wineries/feed/rss/
 * - Combined: /category/wineries/page/2/feed/
 * - Query strings: /category/wineries/?orderby=name
 * - Subdirectory installs: /blog/category/wineries/
 * - Unicode slugs: /category/café/, /category/日本料理/
 *
 * @package    BusinessDirectory
 * @subpackage SEO
 * @since      0.1.8
 */

namespace BD\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SlugMigration
 *
 * Redirects old taxonomy URLs to new SEO-friendly URLs.
 * Performance-optimized: only runs on 404 pages.
 *
 * @since 0.1.8
 */
class SlugMigration {

	/**
	 * Mapping of old base paths to taxonomy names.
	 *
	 * @since 0.1.8
	 * @var array<string, string>
	 */
	private const TAXONOMY_MAP = array(
		'category' => 'bd_category',
		'area'     => 'bd_area',
		'tag'      => 'bd_tag',
	);

	/**
	 * Valid feed types that WordPress supports.
	 *
	 * @since 0.1.8
	 * @var array<string>
	 */
	private const VALID_FEED_TYPES = array( 'rss', 'rss2', 'atom', 'rdf' );

	/**
	 * Initialize hooks.
	 *
	 * @since 0.1.8
	 * @return void
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * Check if current 404 matches an old taxonomy URL and redirect.
	 *
	 * @since 0.1.8
	 * @return void
	 */
	public static function maybe_redirect(): void {
		// Performance guard: only run on 404 pages.
		if ( ! is_404() ) {
			return;
		}

		$request_uri  = self::get_request_uri();
		$redirect_url = self::parse_and_build_redirect( $request_uri );

		if ( false === $redirect_url ) {
			return;
		}

		/**
		 * Fires before a slug migration redirect.
		 *
		 * Useful for logging, analytics, or debugging.
		 *
		 * @since 0.1.8
		 *
		 * @param string $redirect_url The destination URL.
		 * @param string $request_uri  The original request URI.
		 */
		do_action( 'bd_seo_before_slug_redirect', $redirect_url, $request_uri );

		wp_safe_redirect( esc_url_raw( $redirect_url ), 301, 'Business Directory SEO' );
		exit;
	}

	/**
	 * Get the redirect URL for a given old URL (public utility method).
	 *
	 * Useful for testing, WP-CLI scripts, or batch processing.
	 *
	 * @since 0.1.8
	 *
	 * @param string $old_url Old URL or path.
	 * @return string|false New URL or false if not a matching old URL.
	 */
	public static function get_new_url( string $old_url ) {
		return self::parse_and_build_redirect( $old_url );
	}

	/**
	 * Parse a URL and build the redirect destination.
	 *
	 * This is the core logic, shared by both redirect and utility methods.
	 *
	 * @since 0.1.8
	 *
	 * @param string $url URL or path to parse.
	 * @return string|false Redirect URL or false if not a match.
	 */
	private static function parse_and_build_redirect( string $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		// Parse URL components.
		$parsed       = wp_parse_url( $url );
		$path         = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$query_string = isset( $parsed['query'] ) ? $parsed['query'] : '';

		if ( empty( $path ) ) {
			return false;
		}

		// Handle subdirectory installs by stripping home path.
		$path = self::strip_home_path( $path );

		// Decode URL-encoded characters for matching.
		$decoded_path = rawurldecode( $path );

		// Parse the path into components.
		$parsed_path = self::parse_taxonomy_path( $decoded_path );

		if ( false === $parsed_path ) {
			return false;
		}

		// Extract parsed components.
		$taxonomy  = $parsed_path['taxonomy'];
		$term_path = $parsed_path['term_path'];
		$page_num  = $parsed_path['page_num'];
		$feed_type = $parsed_path['feed_type'];

		// Get the term slug (last segment for hierarchical).
		$term_slug = self::extract_term_slug( $term_path );

		if ( empty( $term_slug ) ) {
			return false;
		}

		// Find the term in the database.
		$term = get_term_by( 'slug', $term_slug, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		// For hierarchical taxonomies, verify the full path matches.
		if ( ! self::verify_term_path( $term, $term_path, $taxonomy ) ) {
			return false;
		}

		// Build the new URL.
		$new_url = get_term_link( $term );

		if ( is_wp_error( $new_url ) ) {
			return false;
		}

		// Append pagination.
		if ( $page_num > 1 ) {
			$new_url = trailingslashit( $new_url ) . 'page/' . $page_num . '/';
		}

		// Append feed.
		if ( ! empty( $feed_type ) ) {
			$new_url = trailingslashit( $new_url ) . 'feed/';
			if ( 'feed' !== $feed_type ) {
				$new_url = trailingslashit( $new_url ) . $feed_type . '/';
			}
		}

		// Preserve query string.
		if ( ! empty( $query_string ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirecting, not processing.
			$new_url = add_query_arg( wp_parse_args( $query_string ), $new_url );
		}

		return $new_url;
	}

	/**
	 * Parse a taxonomy URL path into its components.
	 *
	 * @since 0.1.8
	 *
	 * @param string $path URL path like /category/food/italian/page/2/feed/.
	 * @return array|false Parsed components or false if not a taxonomy URL.
	 */
	private static function parse_taxonomy_path( string $path ) {
		// Normalize: ensure leading slash, remove trailing slash.
		$path = '/' . trim( $path, '/' );

		// Split into segments.
		$segments = explode( '/', trim( $path, '/' ) );

		if ( count( $segments ) < 2 ) {
			return false;
		}

		// First segment must be a known taxonomy base.
		$base = strtolower( $segments[0] );

		if ( ! isset( self::TAXONOMY_MAP[ $base ] ) ) {
			return false;
		}

		$taxonomy      = self::TAXONOMY_MAP[ $base ];
		$term_segments = array();
		$page_num      = 0;
		$feed_type     = '';

		// Parse remaining segments.
		$i     = 1;
		$count = count( $segments );

		while ( $i < $count ) {
			$segment = $segments[ $i ];

			// Check for /page/N/.
			if ( 'page' === strtolower( $segment ) && isset( $segments[ $i + 1 ] ) ) {
				$potential_page = $segments[ $i + 1 ];
				if ( ctype_digit( $potential_page ) ) {
					$page_num = absint( $potential_page );
					$i       += 2;
					continue;
				}
			}

			// Check for /feed/ or /feed/type/.
			if ( 'feed' === strtolower( $segment ) ) {
				// Check if next segment is a specific feed type.
				if ( isset( $segments[ $i + 1 ] ) && in_array( strtolower( $segments[ $i + 1 ] ), self::VALID_FEED_TYPES, true ) ) {
					$feed_type = strtolower( $segments[ $i + 1 ] );
					$i        += 2;
				} else {
					$feed_type = 'feed';
					++$i;
				}
				continue;
			}

			// Otherwise, it's part of the term path.
			$term_segments[] = $segment;
			++$i;
		}

		// Must have at least one term segment.
		if ( empty( $term_segments ) ) {
			return false;
		}

		return array(
			'taxonomy'  => $taxonomy,
			'term_path' => implode( '/', $term_segments ),
			'page_num'  => $page_num,
			'feed_type' => $feed_type,
		);
	}

	/**
	 * Get the current request URI, sanitized.
	 *
	 * @since 0.1.8
	 * @return string
	 */
	private static function get_request_uri(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized below.
		return sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Strip the home path prefix for subdirectory installs.
	 *
	 * Correctly handles path boundaries to avoid false positives.
	 * Example: /blog/category/ strips /blog, but /blogroll/category/ does not.
	 *
	 * @since 0.1.8
	 *
	 * @param string $path URL path.
	 * @return string Path with home prefix removed.
	 */
	private static function strip_home_path( string $path ): string {
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );

		// Normalize: remove trailing slash from home path for comparison.
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			$home_path = rtrim( $home_path, '/' );

			// Only strip if path matches exactly OR is followed by a slash.
			// This prevents /blog from matching /blogroll.
			if ( $path === $home_path ) {
				// Exact match: path IS the home path.
				$path = '/';
			} elseif ( 0 === strpos( $path, $home_path . '/' ) ) {
				// Path starts with home_path/ (proper segment boundary).
				$path = substr( $path, strlen( $home_path ) );
			}
			// Otherwise: no match, leave path unchanged.
		}

		// Ensure path starts with /.
		if ( empty( $path ) || '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		return $path;
	}

	/**
	 * Extract the term slug from a potentially hierarchical path.
	 *
	 * Supports Unicode slugs (café, 日本料理, etc.) as used in
	 * internationalized WordPress installations.
	 *
	 * @since 0.1.8
	 *
	 * @param string $term_path Path like "food/italian" or "wineries".
	 * @return string The final term slug, or empty string if invalid.
	 */
	private static function extract_term_slug( string $term_path ): string {
		$term_path = trim( $term_path, '/' );

		if ( empty( $term_path ) ) {
			return '';
		}

		// For hierarchical terms, slug is the last segment.
		$segments = explode( '/', $term_path );
		$slug     = end( $segments );

		// Validate slug format.
		// WordPress slugs can contain:
		// - ASCII letters and numbers (a-z, 0-9)
		// - Hyphens and underscores (-, _)
		// - Unicode letters and numbers when using non-ASCII locales.
		//
		// Using Unicode-aware regex with:
		// \p{L} = any Unicode letter
		// \p{N} = any Unicode number
		// \p{M} = any Unicode mark (combining accents)
		if ( ! preg_match( '/^[\p{L}\p{N}\p{M}_-]+$/u', $slug ) ) {
			return '';
		}

		// WordPress stores slugs in lowercase.
		// Use mb_strtolower for proper Unicode handling.
		if ( function_exists( 'mb_strtolower' ) ) {
			$slug = mb_strtolower( $slug, 'UTF-8' );
		} else {
			$slug = strtolower( $slug );
		}

		return $slug;
	}

	/**
	 * Verify that the URL path matches the term's hierarchical path.
	 *
	 * For non-hierarchical taxonomies (tags), just verifies the slug.
	 * For hierarchical taxonomies, verifies the full parent chain.
	 *
	 * @since 0.1.8
	 *
	 * @param \WP_Term $term      Term object.
	 * @param string   $term_path URL path like "food/italian".
	 * @param string   $taxonomy  Taxonomy name.
	 * @return bool True if path matches term hierarchy.
	 */
	private static function verify_term_path( \WP_Term $term, string $term_path, string $taxonomy ): bool {
		// Normalize the URL path for comparison.
		$term_path_normalized = trim( $term_path, '/' );
		if ( function_exists( 'mb_strtolower' ) ) {
			$term_path_normalized = mb_strtolower( $term_path_normalized, 'UTF-8' );
		} else {
			$term_path_normalized = strtolower( $term_path_normalized );
		}

		// Non-hierarchical taxonomies: just compare slugs.
		if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return $term_path_normalized === $term->slug;
		}

		// Hierarchical: build expected path and compare.
		$expected_path = self::build_term_hierarchy_path( $term, $taxonomy );

		return $term_path_normalized === $expected_path;
	}

	/**
	 * Build the hierarchical path for a term.
	 *
	 * @since 0.1.8
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy name.
	 * @return string Path like "food/italian" or just "wineries".
	 */
	private static function build_term_hierarchy_path( \WP_Term $term, string $taxonomy ): string {
		// Start with current term.
		$slugs = array( $term->slug );

		// Add ancestors (returns array from immediate parent to root).
		if ( $term->parent > 0 ) {
			$ancestors = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $taxonomy );

				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					// Prepend ancestor slug (building path from root to term).
					array_unshift( $slugs, $ancestor->slug );
				}
			}
		}

		return implode( '/', $slugs );
	}
}
