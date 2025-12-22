<?php
/**
 * Share Tracker
 *
 * REST API endpoints for tracking shares and awarding points.
 *
 * @package BusinessDirectory
 */

namespace BD\Social;

use BD\Gamification\ActivityTracker;

class ShareTracker {

	/**
	 * Daily share limits per user.
	 *
	 * @var array
	 */
	const DAILY_LIMITS = array(
		'business' => 3,
		'review'   => 1,
		'badge'    => 1,
		'profile'  => 1,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bd/v1',
			'/share/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_share' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type'      => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'business', 'review', 'badge', 'profile' ),
					),
					'object_id' => array(
						'required' => true,
						'type'     => 'mixed',
					),
					'platform'  => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'facebook', 'linkedin', 'nextdoor', 'email', 'copy_link' ),
					),
				),
			)
		);

		register_rest_route(
			'bd/v1',
			'/share/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_share_stats' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type'      => array(
						'required' => true,
						'type'     => 'string',
					),
					'object_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'bd/v1',
			'/share/user-stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_share_stats' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * Track a share action.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function track_share( $request ) {
		global $wpdb;

		$type      = sanitize_text_field( $request->get_param( 'type' ) );
		$object_id = $request->get_param( 'object_id' );
		$platform  = sanitize_text_field( $request->get_param( 'platform' ) );
		$user_id   = get_current_user_id();

		// Get visitor info for anonymous shares.
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Check daily limit for logged-in users.
		$points_awarded   = 0;
		$limit_reached    = false;
		$remaining_shares = 0;

		if ( $user_id ) {
			$daily_count      = $this->get_user_daily_share_count( $user_id, $type );
			$limit            = self::DAILY_LIMITS[ $type ] ?? 3;
			$remaining_shares = max( 0, $limit - $daily_count - 1 );

			if ( $daily_count >= $limit ) {
				$limit_reached = true;
			} else {
				// Award points.
				$points        = ShareButtons::SHARE_POINTS[ 'share_' . $type ] ?? 5;
				$points_awarded = $points;

				// Track activity if ActivityTracker is available.
				if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
					ActivityTracker::track(
						$user_id,
						'share',
						$points,
						sprintf(
							// translators: %1$s is share type, %2$s is platform.
							__( 'Shared %1$s on %2$s', 'business-directory' ),
							$type,
							$platform
						),
						array(
							'type'      => $type,
							'object_id' => $object_id,
							'platform'  => $platform,
						)
					);
				}
			}
		}

		// Record the share.
		$table = $wpdb->prefix . 'bd_share_tracking';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'share_type'     => $type,
				'object_id'      => is_numeric( $object_id ) ? (int) $object_id : 0,
				'object_key'     => is_string( $object_id ) && ! is_numeric( $object_id ) ? $object_id : '',
				'platform'       => $platform,
				'user_id'        => $user_id,
				'ip_address'     => $ip_address,
				'user_agent'     => $user_agent,
				'points_awarded' => $points_awarded,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return rest_ensure_response(
			array(
				'success'         => true,
				'points_awarded'  => $points_awarded,
				'limit_reached'   => $limit_reached,
				'remaining_today' => $remaining_shares,
				'message'         => $limit_reached
					? __( 'Daily share limit reached. Thanks for sharing!', 'business-directory' )
					: ( $points_awarded > 0
						? sprintf(
							// translators: %d is number of points.
							__( '+%d points! Thanks for sharing!', 'business-directory' ),
							$points_awarded
						)
						: __( 'Thanks for sharing!', 'business-directory' )
					),
			)
		);
	}

	/**
	 * Get share stats for an object.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function get_share_stats( $request ) {
		global $wpdb;

		$type      = sanitize_text_field( $request->get_param( 'type' ) );
		$object_id = (int) $request->get_param( 'object_id' );

		$table = $wpdb->prefix . 'bd_share_tracking';

		// Get total shares.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE share_type = %s AND object_id = %d",
				$type,
				$object_id
			)
		);

		// Get shares by platform.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_platform = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT platform, COUNT(*) as count 
				FROM $table 
				WHERE share_type = %s AND object_id = %d 
				GROUP BY platform",
				$type,
				$object_id
			),
			ARRAY_A
		);

		$platforms = array();
		foreach ( $by_platform as $row ) {
			$platforms[ $row['platform'] ] = (int) $row['count'];
		}

		return rest_ensure_response(
			array(
				'total'     => (int) $total,
				'platforms' => $platforms,
			)
		);
	}

	/**
	 * Get current user's share stats.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function get_user_share_stats( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'bd_share_tracking';

		// Get total shares by user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d",
				$user_id
			)
		);

		// Get total points earned from shares.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(points_awarded) FROM $table WHERE user_id = %d",
				$user_id
			)
		);

		// Get today's shares by type.
		$today_start = gmdate( 'Y-m-d 00:00:00' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_by_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT share_type, COUNT(*) as count 
				FROM $table 
				WHERE user_id = %d AND created_at >= %s 
				GROUP BY share_type",
				$user_id,
				$today_start
			),
			ARRAY_A
		);

		$remaining = array();
		foreach ( self::DAILY_LIMITS as $type => $limit ) {
			$used              = 0;
			foreach ( $today_by_type as $row ) {
				if ( $row['share_type'] === $type ) {
					$used = (int) $row['count'];
					break;
				}
			}
			$remaining[ $type ] = max( 0, $limit - $used );
		}

		return rest_ensure_response(
			array(
				'total_shares'    => (int) $total,
				'total_points'    => (int) $points,
				'remaining_today' => $remaining,
			)
		);
	}

	/**
	 * Get user's daily share count for a type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Share type.
	 * @return int Count.
	 */
	private function get_user_daily_share_count( $user_id, $type ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bd_share_tracking';
		$today_start = gmdate( 'Y-m-d 00:00:00' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d AND share_type = %s AND created_at >= %s",
				$user_id,
				$type,
				$today_start
			)
		);

		return (int) $count;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip = explode( ',', $ip )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Create share tracking table if not exists.
	 */
	public function maybe_create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'bd_share_tracking';
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $exists ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			share_type varchar(50) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_key varchar(100) DEFAULT '',
			platform varchar(50) NOT NULL,
			user_id bigint(20) unsigned DEFAULT 0,
			ip_address varchar(45) DEFAULT '',
			user_agent varchar(500) DEFAULT '',
			points_awarded int(11) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY share_type_object (share_type, object_id),
			KEY user_id (user_id),
			KEY platform (platform),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
