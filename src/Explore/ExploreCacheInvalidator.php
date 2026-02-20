<?php
/**
 * Explore Cache Invalidation
 *
 * Hooks into WordPress post and term lifecycle events to
 * invalidate explore page caches when underlying data changes.
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
 * Class ExploreCacheInvalidator
 */
class ExploreCacheInvalidator {

	/**
	 * Initialize cache invalidation hooks.
	 */
	public static function init() {
		// Business post changes.
		add_action( 'save_post_bd_business', array( __CLASS__, 'on_business_saved' ), 20, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_business_trashed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_business_untrashed' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_business_deleted' ), 10, 2 );

		// Taxonomy term changes.
		add_action( 'edited_bd_area', array( __CLASS__, 'invalidate_all' ) );
		add_action( 'edited_bd_tag', array( __CLASS__, 'invalidate_all' ) );
		add_action( 'created_bd_area', array( __CLASS__, 'invalidate_all' ) );
		add_action( 'created_bd_tag', array( __CLASS__, 'invalidate_all' ) );
		add_action( 'delete_bd_area', array( __CLASS__, 'invalidate_all' ) );
		add_action( 'delete_bd_tag', array( __CLASS__, 'invalidate_all' ) );

		// Term relationship changes (business assigned/removed from area/tag).
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_set' ), 10, 4 );
	}

	/**
	 * When a business is saved (created or updated).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function on_business_saved( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::invalidate_for_business( $post_id );
	}

	/**
	 * When a business is trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_business_trashed( $post_id ) {
		if ( 'bd_business' !== get_post_type( $post_id ) ) {
			return;
		}
		self::invalidate_for_business( $post_id );
	}

	/**
	 * When a business is untrashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_business_untrashed( $post_id ) {
		if ( 'bd_business' !== get_post_type( $post_id ) ) {
			return;
		}
		self::invalidate_for_business( $post_id );
	}

	/**
	 * When a business is permanently deleted.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function on_business_deleted( $post_id, $post ) {
		if ( 'bd_business' !== $post->post_type ) {
			return;
		}
		ExploreLoader::invalidate_caches();
	}

	/**
	 * When terms are set on an object.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Array of term IDs.
	 * @param array  $tt_ids     Array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 */
	public static function on_terms_set( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( ! in_array( $taxonomy, array( 'bd_area', 'bd_tag', 'bd_category' ), true ) ) {
			return;
		}

		if ( 'bd_business' !== get_post_type( $object_id ) ) {
			return;
		}

		self::invalidate_for_business( $object_id );
	}

	/**
	 * Invalidate caches related to a specific business.
	 *
	 * Targeted invalidation: clears only the caches for the
	 * areas and tags this business belongs to.
	 *
	 * @param int $business_id Business post ID.
	 */
	private static function invalidate_for_business( $business_id ) {
		// Get this business's areas and tags.
		$area_terms = wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'slugs' ) );
		$tag_terms  = wp_get_post_terms( $business_id, 'bd_tag', array( 'fields' => 'slugs' ) );

		// Invalidate area-specific caches.
		if ( ! is_wp_error( $area_terms ) ) {
			foreach ( $area_terms as $slug ) {
				wp_cache_delete( 'area_tags_' . $slug, ExploreQuery::CACHE_GROUP );
				wp_cache_delete( 'city_reviews_' . $slug, ExploreQuery::CACHE_GROUP );
				delete_transient( 'bd_ex_atags_' . substr( md5( $slug ), 0, 12 ) );
			}
		}

		// Invalidate tag-specific caches.
		if ( ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $slug ) {
				wp_cache_delete( 'tag_cities_' . $slug, ExploreQuery::CACHE_GROUP );
				delete_transient( 'bd_ex_tcit_' . substr( md5( $slug ), 0, 12 ) );
			}
		}

		// Invalidate intersection count caches for all area × tag combos.
		if ( ! is_wp_error( $area_terms ) && ! is_wp_error( $tag_terms ) ) {
			foreach ( $area_terms as $area_slug ) {
				foreach ( $tag_terms as $tag_slug ) {
					wp_cache_delete( 'intersection_count_' . $area_slug . '_' . $tag_slug, ExploreQuery::CACHE_GROUP );
				}
			}
		}

		// Always invalidate hub and sitemap caches.
		wp_cache_delete( 'hub_data', ExploreQuery::CACHE_GROUP );
		wp_cache_delete( 'bd_explore_sitemap_urls', ExploreQuery::CACHE_GROUP );
		delete_transient( 'bd_geositemap_xml' );
		delete_transient( 'bd_ex_sitemap_urls' );
	}

	/**
	 * Full cache invalidation — used for taxonomy-level changes.
	 */
	public static function invalidate_all() {
		ExploreLoader::invalidate_caches();
	}
}
