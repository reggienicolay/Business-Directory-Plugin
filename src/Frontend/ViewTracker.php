<?php
/**
 * Business View Tracker
 *
 * Tracks page views for business listings to display in owner dashboard.
 *
 * @package BusinessDirectory
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ViewTracker
 */
class ViewTracker {

	/**
	 * Initialize view tracking.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'track_view' ) );
	}

	/**
	 * Track view when visiting a single business page.
	 */
	public static function track_view() {
		// Only track single business pages.
		if ( ! is_singular( 'bd_business' ) ) {
			return;
		}

		$business_id = get_the_ID();
		if ( ! $business_id ) {
			return;
		}

		// Don't count owner/admin views.
		if ( is_user_logged_in() && current_user_can( 'edit_post', $business_id ) ) {
			return;
		}

		// Simple duplicate prevention using cookie.
		$viewed_key = 'bd_viewed_' . $business_id;

		// Check if already viewed in this session.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $_COOKIE[ $viewed_key ] ) ) {
			return;
		}

		// Increment total view count.
		$current_views = (int) get_post_meta( $business_id, 'bd_view_count', true );
		update_post_meta( $business_id, 'bd_view_count', $current_views + 1 );

		// Track monthly views separately for dashboard.
		$month_key     = 'bd_views_' . gmdate( 'Y_m' );
		$monthly_views = (int) get_post_meta( $business_id, $month_key, true );
		update_post_meta( $business_id, $month_key, $monthly_views + 1 );

		// Set cookie to prevent duplicate counts (expires in 1 hour).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.cookies_setcookie
		setcookie( $viewed_key, '1', time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Get total views for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return int Total view count.
	 */
	public static function get_total_views( $business_id ) {
		return (int) get_post_meta( $business_id, 'bd_view_count', true );
	}

	/**
	 * Get monthly views for a business.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $month       Month in Y_m format (default: current month).
	 * @return int Monthly view count.
	 */
	public static function get_monthly_views( $business_id, $month = null ) {
		if ( ! $month ) {
			$month = gmdate( 'Y_m' );
		}
		$month_key = 'bd_views_' . $month;
		return (int) get_post_meta( $business_id, $month_key, true );
	}
}

ViewTracker::init();
