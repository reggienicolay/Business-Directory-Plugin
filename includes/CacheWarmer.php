<?php
/**
 * Cache Warmer - Pre-populate caches for better performance
 *
 * @package BusinessDirectory
 * @subpackage Performance
 * @version 1.3.0
 */

namespace BD\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Search\FilterHandler;
use BD\Utils\Cache;

/**
 * Class CacheWarmer
 *
 * Handles cache warming and invalidation for better performance.
 */
class CacheWarmer {

	/**
	 * Whether the class has been initialized.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize hooks and scheduled events.
	 *
	 * Call this once from the main plugin file.
	 */
	public static function init() {
		// Prevent double initialization.
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// Hook into WordPress scheduled events.
		add_action( 'bd_warm_caches', array( self::class, 'warm_caches' ) );

		// Clear cache when business is saved.
		add_action( 'save_post_bd_business', array( self::class, 'on_business_save' ), 10, 3 );

		// Clear cache when business is deleted.
		add_action( 'before_delete_post', array( self::class, 'on_before_delete_post' ) );

		// Clear cache when business meta is updated (e.g., rating changes).
		add_action( 'updated_post_meta', array( self::class, 'on_updated_post_meta' ), 10, 4 );

		// Schedule initial cache warming after WordPress is fully loaded.
		// This ensures wp_next_scheduled() works correctly.
		add_action( 'wp_loaded', array( self::class, 'maybe_schedule_warming' ) );
	}

	/**
	 * Schedule cache warming if not already scheduled.
	 *
	 * Called on wp_loaded to ensure cron system is ready.
	 */
	public static function maybe_schedule_warming() {
		if ( ! wp_next_scheduled( 'bd_warm_caches' ) ) {
			// Delay initial warming by 5 minutes to avoid slowing down activation.
			wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'bd_warm_caches' );
		}
	}

	/**
	 * Warm all caches
	 */
	public static function warm_caches() {
		// Warm filter metadata.
		if ( class_exists( 'BD\Search\FilterHandler' ) ) {
			FilterHandler::get_filter_metadata();
		}

		// Warm popular queries.
		self::warm_popular_queries();

		// Schedule next warming.
		if ( ! wp_next_scheduled( 'bd_warm_caches' ) ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'bd_warm_caches' );
		}
	}

	/**
	 * Warm popular query combinations
	 *
	 * Pre-populates cache with common filter combinations.
	 * In production, this could be extended to track actual popular queries.
	 */
	private static function warm_popular_queries() {
		// Only warm if Cache class exists.
		if ( ! class_exists( 'BD\Utils\Cache' ) ) {
			return;
		}

		// Common query patterns to pre-warm.
		$popular_queries = array(
			array(
				'sort'     => 'rating',
				'per_page' => 20,
				'page'     => 1,
			),
			array(
				'sort'     => 'newest',
				'per_page' => 20,
				'page'     => 1,
			),
			array(
				'sort'     => 'distance',
				'per_page' => 20,
				'page'     => 1,
			),
		);

		// Note: Actual warming would require executing queries.
		// This is left as a hook point for future implementation.
		// Uncomment below to enable actual warming (adds load on activation):
		//
		// foreach ( $popular_queries as $filters ) {
		//     $cache_key = Cache::get_query_key( $filters );
		//     if ( false === get_transient( $cache_key ) ) {
		//         // Query would be executed here
		//     }
		// }

		/**
		 * Fires after cache warming completes.
		 *
		 * @param array $popular_queries The query patterns that were processed.
		 */
		do_action( 'bd_cache_warmer_complete', $popular_queries );
	}

	/**
	 * Clear all business-related caches
	 *
	 * Called when businesses are updated to ensure fresh data.
	 */
	public static function clear_caches() {
		if ( class_exists( 'BD\Utils\Cache' ) ) {
			Cache::invalidate_business_caches();
		}

		// Also clear filter metadata cache.
		delete_transient( 'bd_filter_metadata' );

		/**
		 * Fires after all business caches are cleared.
		 */
		do_action( 'bd_caches_cleared' );
	}

	/**
	 * Clear caches for a specific business
	 *
	 * @param int $business_id Business post ID.
	 */
	public static function clear_business_cache( $business_id ) {
		$business_id = absint( $business_id );

		if ( ! $business_id ) {
			return;
		}

		// Clear any business-specific transients.
		delete_transient( 'bd_business_' . $business_id );

		// Clear general caches since listings may have changed.
		self::clear_caches();

		/**
		 * Fires after a specific business cache is cleared.
		 *
		 * @param int $business_id The business post ID.
		 */
		do_action( 'bd_business_cache_cleared', $business_id );
	}

	/**
	 * Handle business post save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_business_save( $post_id, $post = null, $update = false ) {
		// Don't clear cache on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't clear cache on revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::clear_business_cache( $post_id );
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public static function on_before_delete_post( $post_id ) {
		$post = get_post( $post_id );

		if ( $post && 'bd_business' === $post->post_type ) {
			self::clear_business_cache( $post_id );
		}
	}

	/**
	 * Handle post meta updates.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function on_updated_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Only clear for business-related meta keys.
		$business_meta_keys = array(
			'bd_avg_rating',
			'bd_review_count',
			'bd_price_level',
			'bd_location',
			'bd_hours',
		);

		if ( ! in_array( $meta_key, $business_meta_keys, true ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( $post && 'bd_business' === $post->post_type ) {
			self::clear_business_cache( $post_id );
		}
	}
}
