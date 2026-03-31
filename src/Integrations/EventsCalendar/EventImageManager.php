<?php
/**
 * Event Image Manager
 *
 * Auto-prunes featured images from expired events to prevent storage bloat.
 * Only deletes images flagged as imported by the BD Event Aggregator —
 * never touches user-uploaded images.
 *
 * Also hooks ImageOptimizer to skip BD-specific custom sizes on event images
 * (events only need thumbnail, medium, large — not bd-hero, bd-lightbox, etc.)
 *
 * Cron: Uses standard WordPress cron scheduled for 2 AM local time.
 * For exact timing on production, set up a real server cron via Cloudways
 * panel to hit wp-cron.php at 2 AM, and add DISABLE_WP_CRON to wp-config.php.
 *
 * @package BusinessDirectory
 * @subpackage Integrations\EventsCalendar
 * @since 0.2.0
 */

namespace BD\Integrations\EventsCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventImageManager {

	/**
	 * Cron hook name for the daily pruning job.
	 */
	const CRON_HOOK = 'bd_prune_expired_event_images';

	/**
	 * Meta key set on attachments imported by the aggregator.
	 * This is the hard safety boundary — only flagged images get pruned.
	 */
	const IMPORTED_META_KEY = '_bd_event_imported_image';

	/**
	 * Meta key set on events after their image has been pruned.
	 * Prevents re-querying already-processed events.
	 */
	const PRUNED_META_KEY = '_bd_image_pruned';

	/**
	 * Default retention period in days after event end date.
	 * Filterable via 'bd_event_image_retention_days'.
	 */
	const DEFAULT_RETENTION = 30;

	/**
	 * Events processed per cron tick. Self-reschedules if more remain.
	 */
	const PRUNE_BATCH_SIZE = 50;

	/**
	 * Initialize hooks.
	 *
	 * @since 0.2.0
	 */
	public static function init() {
		// Skip BD custom image sizes for event images (they only need WP defaults).
		add_filter( 'bd_image_optimizer_should_process', array( __CLASS__, 'skip_bd_sizes_for_events' ), 10, 2 );

		// Cron callback.
		add_action( self::CRON_HOOK, array( __CLASS__, 'prune_expired_images' ) );
	}

	/**
	 * Schedule the daily pruning cron job.
	 *
	 * Call from plugin activation hook. Uses wp_timezone_string() for
	 * DST-safe scheduling at 2 AM local time.
	 *
	 * @since 0.2.0
	 */
	public static function schedule_pruning() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// Schedule for 2 AM local time tomorrow, then daily.
		$tz        = new \DateTimeZone( wp_timezone_string() );
		$tomorrow  = new \DateTime( 'tomorrow 02:00:00', $tz );

		wp_schedule_event( $tomorrow->getTimestamp(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Clear the cron hook on plugin deactivation.
	 *
	 * @since 0.2.0
	 */
	public static function cleanup_on_deactivation() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Skip BD custom image sizes for event-imported images.
	 *
	 * Events only need thumbnail, medium, and large. Sizes like bd-hero,
	 * bd-lightbox, bd-og are never used on event pages. Returning false
	 * tells ImageOptimizer to skip WebP generation for BD sizes.
	 *
	 * @since 0.2.0
	 *
	 * @param bool $should_process Whether to process this attachment.
	 * @param int  $attachment_id  Attachment post ID.
	 * @return bool False for event images, unchanged for others.
	 */
	public static function skip_bd_sizes_for_events( $should_process, $attachment_id ) {
		if ( get_post_meta( $attachment_id, self::IMPORTED_META_KEY, true ) ) {
			return false;
		}

		return $should_process;
	}

	/**
	 * Prune featured images from expired events.
	 *
	 * Daily cron callback. Processes a batch of expired events, deletes
	 * their imported featured images, and self-reschedules if more remain.
	 *
	 * @since 0.2.0
	 */
	public static function prune_expired_images() {
		// Kill switch — filterable.
		$enabled = get_option( 'bd_event_image_pruning_enabled', true );

		/**
		 * Filter whether event image pruning is enabled.
		 *
		 * @since 0.2.0
		 *
		 * @param bool $enabled Whether pruning is enabled.
		 */
		if ( ! apply_filters( 'bd_event_image_pruning_enabled', $enabled ) ) {
			return;
		}

		/**
		 * Filter the retention period in days.
		 *
		 * @since 0.2.0
		 *
		 * @param int $days Days after event end date before image is pruned.
		 */
		$retention = (int) apply_filters( 'bd_event_image_retention_days', self::DEFAULT_RETENTION );
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

		// Query expired events with featured images that haven't been pruned yet.
		$expired_events = get_posts(
			array(
				'post_type'      => 'tribe_events',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => self::PRUNE_BATCH_SIZE,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_EventEndDate',
						'value'   => $cutoff,
						'compare' => '<',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::PRUNED_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $expired_events ) ) {
			return;
		}

		$start_time   = microtime( true );
		$pruned_count = 0;
		$bytes_freed  = 0;

		global $wpdb;

		foreach ( $expired_events as $event_id ) {
			$thumb_id = get_post_thumbnail_id( $event_id );

			if ( ! $thumb_id ) {
				// No image (race condition or already removed). Mark as processed.
				update_post_meta( $event_id, self::PRUNED_META_KEY, time() );
				continue;
			}

			// Safety: only delete images flagged as imported by the aggregator.
			if ( ! get_post_meta( $thumb_id, self::IMPORTED_META_KEY, true ) ) {
				// User-uploaded image — mark event as processed but don't touch the image.
				update_post_meta( $event_id, self::PRUNED_META_KEY, time() );
				continue;
			}

			// Safety: check if this attachment is used as featured image by other posts.
			$other_uses = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta}
					 WHERE meta_key = '_thumbnail_id'
					 AND meta_value = %d
					 AND post_id != %d",
					$thumb_id,
					$event_id
				)
			);

			if ( $other_uses > 0 ) {
				// Shared image — detach from this event but don't delete the file.
				delete_post_thumbnail( $event_id );
				update_post_meta( $event_id, self::PRUNED_META_KEY, time() );
				continue;
			}

			// Calculate file size before deletion.
			$file = get_attached_file( $thumb_id );
			if ( $file && file_exists( $file ) ) {
				$bytes_freed += (int) filesize( $file );
			}

			// Delete the attachment (force, skip trash).
			wp_delete_attachment( $thumb_id, true );
			delete_post_thumbnail( $event_id );
			update_post_meta( $event_id, self::PRUNED_META_KEY, time() );

			++$pruned_count;
		}

		$duration = round( microtime( true ) - $start_time, 2 );

		// Log summary.
		if ( $pruned_count > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[BD EventImageManager] Pruned %d images from %d expired events. Freed %s. Retention: %d days. Duration: %ss.',
					$pruned_count,
					count( $expired_events ),
					size_format( $bytes_freed ),
					$retention,
					$duration
				)
			);
		}

		// Update stats.
		$stats = get_option( 'bd_event_image_prune_stats', array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		$stats['total_pruned']      = ( $stats['total_pruned'] ?? 0 ) + $pruned_count;
		$stats['total_bytes_freed'] = ( $stats['total_bytes_freed'] ?? 0 ) + $bytes_freed;
		$stats['last_run']          = gmdate( 'c' );
		$stats['last_run_count']    = $pruned_count;
		$stats['last_run_bytes']    = $bytes_freed;

		update_option( 'bd_event_image_prune_stats', $stats, false );

		// Self-reschedule if batch was full (more events to process).
		if ( count( $expired_events ) >= self::PRUNE_BATCH_SIZE ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}
}
