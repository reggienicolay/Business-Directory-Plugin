<?php

/**
 * Explore Pages Sitemap Integration
 *
 * Registers a custom WP Sitemaps provider that adds all valid
 * explore intersection pages to the XML sitemap. Also provides
 * a GeoSitemap endpoint with lat/lng for all businesses.
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
 * Class ExploreSitemap
 */
class ExploreSitemap {


	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'register_provider' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register_geositemap_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_geositemap_request' ) );
	}

	/**
	 * Register our custom sitemap provider with WP core sitemaps.
	 *
	 * @param \WP_Sitemaps_Provider $provider Provider being added.
	 * @param string                $name     Provider name.
	 * @return \WP_Sitemaps_Provider Possibly modified provider.
	 */
	public static function register_provider( $provider, $name ) {
		// Hook in after core providers are registered.
		// We use the filter to add our own provider once.
		static $added = false;
		if ( ! $added ) {
			$added = true;
			add_filter(
				'wp_sitemaps_get_providers',
				function ( $providers ) {
					$providers['explore'] = new ExploreSitemapProvider();
					return $providers;
				}
			);
		}
		return $provider;
	}

	/**
	 * Register the geositemap rewrite endpoint.
	 */
	public static function register_geositemap_endpoint() {
		add_rewrite_rule(
			'^geo-sitemap\.xml$',
			'index.php?bd_geositemap=1',
			'top'
		);
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'bd_geositemap';
				return $vars;
			}
		);
	}

	/**
	 * Handle geositemap request and output XML.
	 */
	public static function handle_geositemap_request() {
		if ( ! get_query_var( 'bd_geositemap' ) ) {
			return;
		}

		$cache_key = 'bd_geositemap_xml';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$xml = self::generate_geositemap();

		// Cache for 1 hour.
		set_transient( $cache_key, $xml, HOUR_IN_SECONDS );

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate the GeoSitemap XML.
	 *
	 * @return string XML content.
	 */
	private static function generate_geositemap() {
		global $wpdb;

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:geo="http://www.google.com/geo/schemas/sitemap/1.0">' . "\n";

		$batch_size = 500;
		$offset     = 0;

		do {
			// Paginate to avoid memory exhaustion with large datasets.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$businesses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_modified_gmt, pm.meta_value AS location
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm
						ON p.ID = pm.post_id AND pm.meta_key = 'bd_location'
					WHERE p.post_type = 'bd_business'
						AND p.post_status = 'publish'
					ORDER BY p.ID ASC
					LIMIT %d OFFSET %d",
					$batch_size,
					$offset
				)
			);

			if ( empty( $businesses ) ) {
				break;
			}

			// Prime the post cache for this batch so get_permalink() doesn't
			// trigger a separate get_post() query for each business.
			$batch_ids = wp_list_pluck( $businesses, 'ID' );
			_prime_post_caches( $batch_ids, false, false );

			foreach ( $businesses as $business ) {
				$location = maybe_unserialize( $business->location );

				if ( ! is_array( $location ) ) {
					continue;
				}

				$lat = isset( $location['lat'] ) ? floatval( $location['lat'] ) : 0;
				$lng = isset( $location['lng'] ) ? floatval( $location['lng'] ) : 0;

				if ( empty( $lat ) || empty( $lng ) ) {
					continue;
				}

				$permalink = get_permalink( $business->ID );
				$lastmod   = '';
				if ( ! empty( $business->post_modified_gmt ) && '0000-00-00 00:00:00' !== $business->post_modified_gmt ) {
					$ts = strtotime( $business->post_modified_gmt );
					if ( $ts ) {
						$lastmod = gmdate( DATE_W3C, $ts );
					}
				}

				$xml .= "  <url>\n";
				$xml .= '    <loc>' . esc_url( $permalink ) . "</loc>\n";
				if ( $lastmod ) {
					$xml .= '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";
				}
				$xml .= "    <geo:geo>\n";
				$xml .= "      <geo:format>kml</geo:format>\n";
				$xml .= '      <geo:lat>' . esc_html( $lat ) . "</geo:lat>\n";
				$xml .= '      <geo:long>' . esc_html( $lng ) . "</geo:long>\n";
				$xml .= "    </geo:geo>\n";
				$xml .= "  </url>\n";
			}

			$offset += $batch_size;

			$businesses_count = count( $businesses );
		} while ( $businesses_count === $batch_size );

		$xml .= "</urlset>\n";

		return $xml;
	}

	/**
	 * Get all valid explore URLs for sitemap.
	 *
	 * Only includes intersection combinations with 2+ businesses.
	 *
	 * @return array Array of URL entries with 'loc' and 'lastmod'.
	 */
	public static function get_explore_urls() {
		$cache_key = 'bd_explore_sitemap_urls';

		// Layer 1: Per-request object cache.
		$cached = wp_cache_get( $cache_key, ExploreQuery::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// Layer 2: Persistent transient.
		$transient_key = 'bd_ex_sitemap_urls';
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			wp_cache_set( $cache_key, $cached, ExploreQuery::CACHE_GROUP, HOUR_IN_SECONDS );
			return $cached;
		}

		global $wpdb;

		// Get all valid area × tag combinations with 2+ businesses.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			"SELECT
				t_area.slug AS area_slug,
				t_tag.slug AS tag_slug,
				MAX(p.post_modified_gmt) AS last_modified,
				COUNT(DISTINCT p.ID) AS business_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr_area
				ON p.ID = tr_area.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt_area
				ON tr_area.term_taxonomy_id = tt_area.term_taxonomy_id
				AND tt_area.taxonomy = 'bd_area'
			INNER JOIN {$wpdb->terms} t_area
				ON tt_area.term_id = t_area.term_id
			INNER JOIN {$wpdb->term_relationships} tr_tag
				ON p.ID = tr_tag.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt_tag
				ON tr_tag.term_taxonomy_id = tt_tag.term_taxonomy_id
				AND tt_tag.taxonomy = 'bd_tag'
			INNER JOIN {$wpdb->terms} t_tag
				ON tt_tag.term_id = t_tag.term_id
			WHERE p.post_type = 'bd_business'
				AND p.post_status = 'publish'
			GROUP BY t_area.slug, t_tag.slug
			HAVING business_count >= 2
			ORDER BY t_area.slug ASC, business_count DESC"
		);

		$urls = array();

		// Add city landing pages first — with lastmod from most recent business.
		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => true,
			)
		);

		// Build a lookup of area slug → most recent lastmod from intersections.
		$area_lastmod = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				if ( ! empty( $row->last_modified ) && '0000-00-00 00:00:00' !== $row->last_modified ) {
					$ts = strtotime( $row->last_modified );
					if ( $ts ) {
						$formatted = gmdate( DATE_W3C, $ts );
						if ( ! isset( $area_lastmod[ $row->area_slug ] ) || $formatted > $area_lastmod[ $row->area_slug ] ) {
							$area_lastmod[ $row->area_slug ] = $formatted;
						}
					}
				}
			}
		}

		if ( ! is_wp_error( $areas ) ) {
			foreach ( $areas as $area ) {
				$urls[] = array(
					'loc'     => ExploreRouter::get_explore_url( $area->slug ),
					'lastmod' => $area_lastmod[ $area->slug ] ?? '',
				);
			}
		}

		// Add hub page — lastmod from the most recent business across all areas.
		$hub_lastmod = ! empty( $area_lastmod ) ? max( $area_lastmod ) : '';
		array_unshift(
			$urls,
			array(
				'loc'     => ExploreRouter::get_explore_url(),
				'lastmod' => $hub_lastmod,
			)
		);

		// Add intersection pages.
		if ( $results ) {
			foreach ( $results as $row ) {
				$lastmod = '';
				if ( ! empty( $row->last_modified ) && '0000-00-00 00:00:00' !== $row->last_modified ) {
					$ts = strtotime( $row->last_modified );
					if ( $ts ) {
						$lastmod = gmdate( DATE_W3C, $ts );
					}
				}

				$urls[] = array(
					'loc'     => ExploreRouter::get_explore_url( $row->area_slug, $row->tag_slug ),
					'lastmod' => $lastmod,
				);
			}
		}

		wp_cache_set( $cache_key, $urls, ExploreQuery::CACHE_GROUP, HOUR_IN_SECONDS );
		set_transient( $transient_key, $urls, HOUR_IN_SECONDS );

		return $urls;
	}
}
