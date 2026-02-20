<?php
/**
 * Explore Pages Asset Manager
 *
 * Single source of truth for all assets used by explore pages.
 * Centralizes CDN resource registration with SRI hashes, handles
 * dependency ordering, and provides page-type-specific enqueue
 * methods called from templates.
 *
 * Templates call ONE method instead of managing 40+ lines of
 * enqueue boilerplate each:
 *
 *   ExploreAssets::enqueue_hub();
 *   ExploreAssets::enqueue_city();
 *   ExploreAssets::enqueue_intersection();
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
 * Class ExploreAssets
 */
class ExploreAssets {

	/**
	 * CDN library definitions.
	 *
	 * Centralized so that version bumps and SRI hash updates
	 * happen in exactly one place.
	 *
	 * Generate SRI hashes locally with:
	 *   curl -sL <URL> | openssl dgst -sha256 -binary | openssl base64
	 *
	 * Or use https://www.srihash.org/
	 *
	 * @var array
	 */
	private static $cdn = array(
		'leaflet-css'                       => array(
			'handle'    => 'leaflet',
			'src'       => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			'version'   => '1.9.4',
			'integrity' => 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=',
		),
		'leaflet-js'                        => array(
			'handle'    => 'leaflet',
			'src'       => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			'version'   => '1.9.4',
			'integrity' => 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=',
		),
		'leaflet-markercluster-css'         => array(
			'handle'    => 'leaflet-markercluster',
			'src'       => 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
			'version'   => '1.5.3',
			'integrity' => '', // Generate: curl -sL <src> | openssl dgst -sha256 -binary | openssl base64
		),
		'leaflet-markercluster-default-css' => array(
			'handle'    => 'leaflet-markercluster-default',
			'src'       => 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
			'version'   => '1.5.3',
			'integrity' => '', // Generate: curl -sL <src> | openssl dgst -sha256 -binary | openssl base64
		),
		'leaflet-markercluster-js'          => array(
			'handle'    => 'leaflet-markercluster',
			'src'       => 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
			'version'   => '1.5.3',
			'integrity' => '', // Generate: curl -sL <src> | openssl dgst -sha256 -binary | openssl base64
		),
		'font-awesome-css'                  => array(
			'handle'    => 'font-awesome',
			'src'       => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
			'version'   => '5.15.4',
			'integrity' => 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==',
		),
	);

	/**
	 * Whether the SRI filter has been registered.
	 *
	 * @var bool
	 */
	private static $sri_filter_added = false;

	/**
	 * Map of handle → integrity hash for the tag filter.
	 *
	 * @var array
	 */
	private static $sri_hashes = array();

	/**
	 * Enqueue assets for the hub page (/explore/).
	 *
	 * Lightweight — no map, no cards, just layout + icons.
	 */
	public static function enqueue_hub() {
		self::enqueue_font_awesome();

		$suffix = self::asset_suffix();

		wp_enqueue_style(
			'bd-explore',
			BD_PLUGIN_URL . "assets/css/explore{$suffix}.css",
			array(),
			BD_VERSION
		);
	}

	/**
	 * Get asset file suffix based on SCRIPT_DEBUG.
	 *
	 * @return string '.min' for production, '' for debug mode.
	 */
	private static function asset_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Enqueue assets for city and intersection pages.
	 *
	 * Full stack: cards, map, markers, save-to-list, icons.
	 */
	public static function enqueue_map_page() {
		$suffix = self::asset_suffix();

		// 1. Card styles (registered by the main QF module or fallback).
		self::ensure_quick_filters_style();
		wp_enqueue_style( 'bd-quick-filters' );

		// 2. Map marker styles (heart pins, clusters).
		self::ensure_map_markers_style();
		wp_enqueue_style( 'bd-map-markers' );

		// 3. Explore page layout (loads AFTER QF + markers for cascade).
		wp_enqueue_style(
			'bd-explore',
			BD_PLUGIN_URL . "assets/css/explore{$suffix}.css",
			array( 'bd-quick-filters', 'bd-map-markers' ),
			BD_VERSION
		);

		// 4. Leaflet map library.
		self::enqueue_leaflet();

		// 5. Leaflet MarkerCluster.
		self::enqueue_markercluster();

		// 6. Font Awesome icons.
		self::enqueue_font_awesome();

		// 7. Explore map script (heart pins + cluster rollups).
		wp_enqueue_script(
			'bd-explore-map',
			BD_PLUGIN_URL . "assets/js/explore-map{$suffix}.js",
			array( 'leaflet', 'leaflet-markercluster' ),
			BD_VERSION,
			true
		);

		// 8. Discovery bar navigation (CSP-safe external script).
		wp_enqueue_script(
			'bd-explore-discovery',
			BD_PLUGIN_URL . 'assets/js/explore-discovery.js',
			array(),
			BD_VERSION,
			true
		);

		// 9. Save-to-list (hearts on cards).
		self::enqueue_lists();
	}

	/**
	 * Register + enqueue Leaflet CSS and JS with SRI.
	 */
	private static function enqueue_leaflet() {
		$css = self::$cdn['leaflet-css'];
		$js  = self::$cdn['leaflet-js'];

		if ( ! wp_style_is( $css['handle'], 'registered' ) ) {
			wp_register_style( $css['handle'], $css['src'], array(), $css['version'] );
			self::add_sri( $css['handle'], $css['integrity'], 'style' );
		}
		wp_enqueue_style( $css['handle'] );

		if ( ! wp_script_is( $js['handle'], 'registered' ) ) {
			wp_register_script( $js['handle'], $js['src'], array(), $js['version'], true );
			self::add_sri( $js['handle'], $js['integrity'], 'script' );
		}
		wp_enqueue_script( $js['handle'] );
	}

	/**
	 * Register + enqueue Leaflet MarkerCluster CSS and JS with SRI.
	 */
	private static function enqueue_markercluster() {
		$css     = self::$cdn['leaflet-markercluster-css'];
		$css_def = self::$cdn['leaflet-markercluster-default-css'];
		$js      = self::$cdn['leaflet-markercluster-js'];

		if ( ! wp_style_is( $css['handle'], 'enqueued' ) ) {
			wp_enqueue_style( $css['handle'], $css['src'], array( 'leaflet' ), $css['version'] );
			self::add_sri( $css['handle'], $css['integrity'], 'style' );

			wp_enqueue_style( $css_def['handle'], $css_def['src'], array( $css['handle'] ), $css_def['version'] );
			self::add_sri( $css_def['handle'], $css_def['integrity'], 'style' );
		}

		if ( ! wp_script_is( $js['handle'], 'enqueued' ) ) {
			wp_enqueue_script( $js['handle'], $js['src'], array( 'leaflet' ), $js['version'], true );
			self::add_sri( $js['handle'], $js['integrity'], 'script' );
		}
	}

	/**
	 * Enqueue Font Awesome CSS with SRI.
	 *
	 * Checks both 'font-awesome' and 'font-awesome-5' handles
	 * to avoid double-loading if the theme already provides it.
	 */
	private static function enqueue_font_awesome() {
		if ( wp_style_is( 'font-awesome', 'enqueued' ) || wp_style_is( 'font-awesome-5', 'enqueued' ) ) {
			return;
		}

		$css = self::$cdn['font-awesome-css'];

		if ( ! wp_style_is( $css['handle'], 'registered' ) ) {
			wp_register_style( $css['handle'], $css['src'], array(), $css['version'] );
			self::add_sri( $css['handle'], $css['integrity'], 'style' );
		}
		wp_enqueue_style( $css['handle'] );
	}

	/**
	 * Ensure the Quick Filters card styles are registered.
	 *
	 * The main QF module registers this handle during normal directory
	 * page loads. If it hasn't been registered yet (e.g. explore page
	 * reached without visiting directory first), register it here as
	 * a fallback pointing to the same file.
	 */
	private static function ensure_quick_filters_style() {
		if ( ! wp_style_is( 'bd-quick-filters', 'registered' ) ) {
			$suffix = self::asset_suffix();
			wp_register_style(
				'bd-quick-filters',
				BD_PLUGIN_URL . "assets/css/quick-filters{$suffix}.css",
				array(),
				BD_VERSION
			);
		}
	}

	/**
	 * Ensure the map markers CSS is registered.
	 *
	 * Same fallback pattern as Quick Filters — the directory-loader
	 * normally registers this, but we ensure it exists for explore.
	 */
	private static function ensure_map_markers_style() {
		if ( ! wp_style_is( 'bd-map-markers', 'registered' ) ) {
			wp_register_style(
				'bd-map-markers',
				BD_PLUGIN_URL . 'assets/css/map-markers.css',
				array(),
				BD_VERSION
			);
		}
	}

	/**
	 * Enqueue the Lists module assets for save-to-list hearts.
	 *
	 * The Lists module registers 'bd-lists' during its own init.
	 * If it hasn't registered yet (load order variance), we register
	 * a fallback. If the Lists module isn't active at all, the
	 * handle simply won't exist and the hearts won't render — which
	 * is the correct graceful degradation.
	 */
	private static function enqueue_lists() {
		// Fallback registration if Lists module hasn't registered yet.
		// ListDisplay::enqueue_assets() only fires on singular business
		// pages and lists pages — not on explore pages. So we handle it.
		if ( ! wp_script_is( 'bd-lists', 'registered' ) ) {
			$lists_js = BD_PLUGIN_DIR . 'assets/js/lists.js';
			if ( file_exists( $lists_js ) ) {
				wp_register_script(
					'bd-lists',
					BD_PLUGIN_URL . 'assets/js/lists.js',
					array( 'jquery' ),
					BD_VERSION,
					true
				);
			}
		}

		if ( ! wp_style_is( 'bd-lists', 'registered' ) ) {
			$lists_css = BD_PLUGIN_DIR . 'assets/css/lists.css';
			if ( file_exists( $lists_css ) ) {
				wp_register_style(
					'bd-lists',
					BD_PLUGIN_URL . 'assets/css/lists.css',
					array(),
					BD_VERSION
				);
			}
		}

		// Now enqueue if available.
		if ( wp_script_is( 'bd-lists', 'registered' ) ) {
			wp_enqueue_script( 'bd-lists' );

			// Lists JS reads from the global 'bdLists' object.
			// ListDisplay normally provides this, but its enqueue_assets()
			// doesn't fire on explore pages. Provide the config here if
			// it hasn't been localized yet (check for existing data).
			if ( ! wp_scripts()->get_data( 'bd-lists', 'data' ) ) {
				wp_localize_script(
					'bd-lists',
					'bdLists',
					array(
						'restUrl'     => esc_url_raw( rest_url( 'bd/v1/' ) ),
						'nonce'       => wp_create_nonce( 'wp_rest' ),
						'isLoggedIn'  => is_user_logged_in(),
						'loginUrl'    => wp_login_url(),
						'registerUrl' => wp_registration_url(),
						'myListsUrl'  => home_url( '/my-profile/my-lists/' ),
						'strings'     => array(
							'saved'         => __( 'Saved!', 'business-directory' ),
							'removed'       => __( 'Removed', 'business-directory' ),
							'error'         => __( 'Something went wrong', 'business-directory' ),
							'loginRequired' => __( 'Please log in to save places', 'business-directory' ),
							'createList'    => __( 'Create New List', 'business-directory' ),
							'copied'        => __( 'Copied to clipboard!', 'business-directory' ),
							'shareTitle'    => __( 'Share This List', 'business-directory' ),
							'following'     => __( 'Following', 'business-directory' ),
							'follow'        => __( 'Follow', 'business-directory' ),
						),
					)
				);
			}
		}
		if ( wp_style_is( 'bd-lists', 'registered' ) ) {
			wp_enqueue_style( 'bd-lists' );
		}

		// Explore save-to-list bridge — creates a shared modal for explore
		// pages where ListDisplay doesn't render server-side modals.
		wp_enqueue_script(
			'bd-explore-save',
			BD_PLUGIN_URL . 'assets/js/explore-save.js',
			array( 'jquery', 'bd-lists' ),
			BD_VERSION,
			true
		);
	}

	/**
	 * Add SRI integrity hash for a registered asset.
	 *
	 * Uses the `script_loader_tag` and `style_loader_tag` filters
	 * to inject `integrity` and `crossorigin` attributes. The filter
	 * is registered once and serves all handles.
	 *
	 * @param string $handle    Asset handle.
	 * @param string $integrity SRI hash (e.g. 'sha256-...').
	 * @param string $type      'script' or 'style'.
	 */
	private static function add_sri( $handle, $integrity, $type ) {
		if ( empty( $integrity ) ) {
			return;
		}

		self::$sri_hashes[ $type . ':' . $handle ] = $integrity;

		if ( ! self::$sri_filter_added ) {
			self::$sri_filter_added = true;

			add_filter( 'script_loader_tag', array( __CLASS__, 'inject_sri_attribute' ), 10, 2 );
			add_filter( 'style_loader_tag', array( __CLASS__, 'inject_sri_attribute' ), 10, 2 );
		}
	}

	/**
	 * Filter callback: inject SRI attributes into script/style tags.
	 *
	 * @param string $tag    The HTML tag.
	 * @param string $handle The asset handle.
	 * @return string Modified tag.
	 */
	public static function inject_sri_attribute( $tag, $handle ) {
		// Determine type from which filter called us.
		$type = current_filter() === 'script_loader_tag' ? 'script' : 'style';
		$key  = $type . ':' . $handle;

		if ( ! isset( self::$sri_hashes[ $key ] ) ) {
			return $tag;
		}

		// Don't add if already present.
		if ( false !== strpos( $tag, 'integrity=' ) ) {
			return $tag;
		}

		$integrity = esc_attr( self::$sri_hashes[ $key ] );
		$attrs     = sprintf( 'integrity="%s" crossorigin="anonymous"', $integrity );

		// Insert before the closing > of the tag.
		if ( 'script' === $type ) {
			$tag = str_replace( '></script>', ' ' . $attrs . '></script>', $tag );
		} else {
			// Style <link> tags: insert before self-closing />.
			$tag = str_replace( '/>', $attrs . ' />', $tag );
		}

		return $tag;
	}
}
