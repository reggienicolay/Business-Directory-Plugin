<?php
/**
 * Leaderboard Shortcode
 *
 * Displays top contributors leaderboard.
 * Usage: [bd_leaderboard], [bd_leaderboard period="month" limit="5"]
 *
 * @package BusinessDirectory
 */
namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LeaderboardShortcode {

	/**
	 * Rank definitions matching the gamification system.
	 */
	private static $ranks = array(
		array(
			'name'  => 'Newcomer',
			'min'   => 0,
			'color' => '#94a3b8',
			'bg'    => 'rgba(148,163,184,.1)',
		),
		array(
			'name'  => 'Neighbor',
			'min'   => 50,
			'color' => '#3b82f6',
			'bg'    => 'rgba(59,130,246,.1)',
		),
		array(
			'name'  => 'Explorer',
			'min'   => 200,
			'color' => '#2CB1BC',
			'bg'    => 'rgba(44,177,188,.1)',
		),
		array(
			'name'  => 'Insider',
			'min'   => 500,
			'color' => '#f59e0b',
			'bg'    => 'rgba(245,158,11,.1)',
		),
		array(
			'name'  => 'Local Legend',
			'min'   => 1000,
			'color' => '#8b5cf6',
			'bg'    => 'rgba(139,92,246,.1)',
		),
	);

	public static function init() {
		add_shortcode( 'bd_leaderboard', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'period' => 'all_time',
				'limit'  => '10',
			),
			$atts,
			'bd_leaderboard'
		);

		$limit  = absint( $atts['limit'] ) ?: 10;
		$period = sanitize_text_field( $atts['period'] );

		$leaders = self::get_leaderboard( $period, $limit );

		if ( empty( $leaders ) ) {
			return '<!-- BD Leaderboard: No leaders found -->';
		}

		$html = '<div class="bd-leaderboard ltv-rank-ladder">';

		foreach ( $leaders as $i => $leader ) {
			$rank         = self::get_rank( (int) $leader['total_points'] );
			$display_name = esc_html( $leader['display_name'] );
			$points       = absint( $leader['total_points'] );
			$initials     = self::get_initials( $leader['display_name'] );
			$position     = $i + 1;

			// Progress bar: percentage toward next rank tier.
			$next_rank = self::get_next_rank( $points );
			$progress  = $next_rank ? min( 100, round( ( $points / $next_rank['min'] ) * 100 ) ) : 100;

			$html .= '<div class="ltv-rank-item">';
			$html .= '<div class="ltv-rank-icon" style="background:' . esc_attr( $rank['bg'] ) . ';color:' . esc_attr( $rank['color'] ) . '">';
			$html .= '<span style="font-weight:700;font-size:14px">' . $position . '</span>';
			$html .= '</div>';
			$html .= '<div class="ltv-rank-info">';
			$html .= '<div class="ltv-rank-name">' . $display_name . '</div>';
			$html .= '<div class="ltv-rank-points">' . number_format( $points ) . ' points &middot; ' . esc_html( $rank['name'] ) . '</div>';
			$html .= '<div class="ltv-rank-bar"><div class="ltv-rank-fill" style="width:' . $progress . '%;background:' . esc_attr( $rank['color'] ) . '"></div></div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get leaderboard data. Uses ActivityTracker if available, else queries directly.
	 */
	private static function get_leaderboard( $period, $limit ) {
		// Use existing ActivityTracker method if available.
		if ( class_exists( 'BD\\Gamification\\ActivityTracker' ) && method_exists( 'BD\\Gamification\\ActivityTracker', 'get_leaderboard' ) ) {
			return \BD\Gamification\ActivityTracker::get_leaderboard( $period, $limit );
		}

		// Fallback: direct query.
		global $wpdb;
		$reputation_table = $wpdb->prefix . 'bd_user_reputation';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reputation_table )
		);
		if ( ! $table_exists ) {
			return array();
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$leaders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.user_id, r.total_points, u.display_name
                 FROM {$reputation_table} r
                 JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.total_points > 0
                 ORDER BY r.total_points DESC
                 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $leaders ?: array();
	}

	/**
	 * Get current rank for a given point total.
	 */
	private static function get_rank( $points ) {
		$current = self::$ranks[0];
		foreach ( self::$ranks as $rank ) {
			if ( $points >= $rank['min'] ) {
				$current = $rank;
			}
		}
		return $current;
	}

	/**
	 * Get next rank tier for progress bar calculation.
	 */
	private static function get_next_rank( $points ) {
		foreach ( self::$ranks as $rank ) {
			if ( $points < $rank['min'] ) {
				return $rank;
			}
		}
		return null; // Already at max.
	}

	/**
	 * Get initials from a display name.
	 */
	private static function get_initials( $name ) {
		$parts    = explode( ' ', trim( $name ) );
		$initials = '';
		foreach ( $parts as $part ) {
			$initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) );
			if ( strlen( $initials ) >= 2 ) {
				break;
			}
		}
		return $initials ?: '?';
	}
}

LeaderboardShortcode::init();
