<?php
/**
 * Recent Reviews Shortcode
 *
 * Displays recent reviews across all businesses.
 * Usage: [bd_recent_reviews], [bd_recent_reviews limit="4"]
 *
 * @package BusinessDirectory
 */
namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecentReviewsShortcode {

	public static function init() {
		add_shortcode( 'bd_recent_reviews', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => '5',
				'category' => '',
			),
			$atts,
			'bd_recent_reviews'
		);

		$limit    = absint( $atts['limit'] ) ?: 5;
		$category = sanitize_text_field( $atts['category'] );

		$reviews = self::get_recent_reviews( $limit, $category );

		if ( empty( $reviews ) ) {
			return '<!-- BD Recent Reviews: No reviews found -->';
		}

		$html = '<div class="bd-recent-reviews ltv-reviews-strip">';

		foreach ( $reviews as $review ) {
			$initials     = self::get_initials( $review['display_name'] );
			$stars        = self::render_stars( (int) $review['rating'] );
			$time_ago     = human_time_diff( strtotime( $review['created_at'] ), current_datetime()->getTimestamp() );
			$biz_title    = esc_html( $review['business_title'] ?? '' );
			$biz_url      = get_permalink( $review['business_id'] );
			$review_text  = esc_html( wp_trim_words( $review['content'], 30, '...' ) );
			$display_name = esc_html( $review['display_name'] );
			$helpful      = absint( $review['helpful_count'] );

			$html .= '<div class="ltv-review-card">';
			$html .= '<div class="ltv-review-header">';
			$html .= '<div class="ltv-review-avatar">' . esc_html( $initials ) . '</div>';
			$html .= '<div class="ltv-review-header-info">';
			$html .= '<h4>' . $display_name . '</h4>';
			/* translators: %s: human-readable time difference */
			$html .= '<span>' . sprintf( esc_html__( '%s ago', 'business-directory' ), $time_ago ) . '</span>';
			$html .= '</div></div>';
			$html .= '<div class="ltv-review-stars">' . $stars . '</div>';
			$html .= '<div class="ltv-review-text">' . $review_text . '</div>';
			$html .= '<a href="' . esc_url( $biz_url ) . '" class="ltv-review-biz">';
			$html .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>';
			$html .= $biz_title;
			$html .= '</a>';

			if ( $helpful > 0 ) {
				$html .= '<div class="ltv-review-helpful">';
				$html .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"/></svg>';
				/* translators: %d: number of helpful votes */
				$html .= sprintf( esc_html__( '%d found helpful', 'business-directory' ), $helpful );
				$html .= '</div>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Query recent approved reviews from wp_bd_reviews table.
	 */
	private static function get_recent_reviews( $limit, $category = '' ) {
		global $wpdb;

		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Check table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);
		if ( ! $table_exists ) {
			return array();
		}

		$join  = '';
		$where = '';

		if ( ! empty( $category ) ) {
			$join  = "JOIN {$wpdb->term_relationships} tr ON r.business_id = tr.object_id ";
			$join .= "JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id ";
			$join .= "JOIN {$wpdb->terms} t ON tt.term_id = t.term_id ";
			$where = $wpdb->prepare( "AND tt.taxonomy = 'bd_category' AND t.slug = %s ", $category );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, p.post_title AS business_title,
                        COALESCE(u.display_name, r.author_name, 'Anonymous') AS display_name
                 FROM {$reviews_table} r
                 LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                 JOIN {$wpdb->posts} p ON r.business_id = p.ID
                 {$join}
                 WHERE r.status = 'approved'
                 {$where}
                 ORDER BY r.created_at DESC
                 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
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

	/**
	 * Render Unicode star rating.
	 */
	private static function render_stars( $rating ) {
		$filled = min( max( $rating, 0 ), 5 );
		$empty  = 5 - $filled;
		return str_repeat( '★', $filled ) . str_repeat( '☆', $empty );
	}
}

RecentReviewsShortcode::init();
