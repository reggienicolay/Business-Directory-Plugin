<?php
/**
 * City Events Shortcode
 *
 * Displays events filtered by city. Works locally or fetches from
 * remote site (for network city sites like LoveLivermore).
 *
 * Usage:
 *   [bd_city_events city="Livermore"]
 *   [bd_city_events city="Pleasanton" limit="10" layout="list"]
 *   [bd_city_events city="Dublin" source="lovetrivalley.com"]
 *
 * @package BusinessDirectory
 * @version 2.0.0
 */

namespace BD\Integrations\EventsCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Admin\FeatureSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CityEventsShortcode
 */
class CityEventsShortcode {

	/**
	 * Initialize shortcodes
	 */
	public static function init() {
		add_shortcode( 'bd_city_events', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city'          => '',
				'limit'         => 10,
				'layout'        => 'grid',      // grid, list, compact
				'columns'       => 3,
				'show_business' => 'true',
				'show_image'    => 'true',
				'show_venue'    => 'true',
				'show_time'     => 'true',
				'title'         => '',          // Optional section title
				'view_all_url'  => '',          // Optional "View All" link
				'source'        => '',          // Source site URL (e.g., lovetrivalley.com)
				'cache'         => 15,          // Cache minutes (0 to disable)
			),
			$atts,
			'bd_city_events'
		);

		// City is required
		if ( empty( $atts['city'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404;">City Events: Please specify a city. Example: [bd_city_events city="Livermore"]</p>';
			}
			return '';
		}

		// Get events (from cache or fresh)
		$cache_key  = 'bd_city_events_' . sanitize_key( $atts['city'] ) . '_' . sanitize_key( $atts['source'] ) . '_' . $atts['limit'];
		$cache_time = absint( $atts['cache'] ) * MINUTE_IN_SECONDS;
		$events     = false;

		if ( $cache_time > 0 ) {
			$events = get_transient( $cache_key );
		}

		if ( false === $events ) {
			$events = self::fetch_events( $atts['city'], absint( $atts['limit'] ), $atts['source'] );

			if ( $cache_time > 0 && ! empty( $events ) ) {
				set_transient( $cache_key, $events, $cache_time );
			}
		}

		if ( empty( $events ) ) {
			return '<p style="padding: 30px; text-align: center; color: #666; background: #f9f9f9; border-radius: 8px;">' . sprintf(
				/* translators: %s: city name */
				esc_html__( 'No upcoming events in %s.', 'business-directory' ),
				esc_html( ucfirst( $atts['city'] ) )
			) . '</p>';
		}

		// Render based on layout
		return self::render_events( $events, $atts );
	}

	/**
	 * Fetch events for a city
	 *
	 * @param string $city   City name.
	 * @param int    $limit  Number of events.
	 * @param string $source Source site URL (optional).
	 * @return array Events data.
	 */
	private static function fetch_events( $city, $limit, $source = '' ) {
		// Determine source URL
		$source_url = $source;

		// If no source specified in shortcode, check settings
		if ( empty( $source_url ) && class_exists( 'BD\\Admin\\FeatureSettings' ) ) {
			$source_url = FeatureSettings::get_source_url();
		}

		// If we have a source URL, fetch remotely
		if ( ! empty( $source_url ) ) {
			return self::fetch_remote_events( $source_url, $city, $limit );
		}

		// Otherwise try local fetch
		return self::fetch_local_events( $city, $limit );
	}

	/**
	 * Fetch events from remote site
	 *
	 * @param string $source_url Source domain.
	 * @param string $city       City name.
	 * @param int    $limit      Number of events.
	 * @return array Events data.
	 */
	private static function fetch_remote_events( $source_url, $city, $limit ) {
		// Clean up the source URL
		$source_url = preg_replace( '#^https?://#', '', $source_url );
		$source_url = rtrim( $source_url, '/' );

		$api_url = 'https://' . $source_url . '/wp-json/bd/v1/events/city/' . rawurlencode( $city );
		$api_url = add_query_arg( 'limit', $limit, $api_url );

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BD City Events: Remote fetch failed - ' . $response->get_error_message() );
			}
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	/**
	 * Fetch events locally (when running on main site)
	 *
	 * @param string $city  City name.
	 * @param int    $limit Number of events.
	 * @return array Events data.
	 */
	private static function fetch_local_events( $city, $limit ) {
		// Check if TEC is active
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return array();
		}

		global $wpdb;

		// Get venues in this city
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$venue_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_VenueCity' 
				AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $city ) . '%'
			)
		);

		if ( empty( $venue_ids ) ) {
			return array();
		}

		// Get upcoming events at these venues
		$events = get_posts(
			array(
				'post_type'      => 'tribe_events',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_key'       => '_EventStartDate',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_EventVenueID',
						'value'   => $venue_ids,
						'compare' => 'IN',
					),
					array(
						'key'     => '_EventStartDate',
						'value'   => current_time( 'mysql' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		$results = array();

		foreach ( $events as $event ) {
			$venue_id    = get_post_meta( $event->ID, '_EventVenueID', true );
			$business_id = EventsCalendarIntegration::get_business_for_event( $event->ID );

			$results[] = array(
				'id'          => $event->ID,
				'title'       => $event->post_title,
				'url'         => get_permalink( $event->ID ),
				'start_date'  => tribe_get_start_date( $event->ID, false, 'Y-m-d' ),
				'start_time'  => tribe_get_start_date( $event->ID, false, 'H:i' ),
				'end_date'    => tribe_get_end_date( $event->ID, false, 'Y-m-d' ),
				'end_time'    => tribe_get_end_date( $event->ID, false, 'H:i' ),
				'venue'       => $venue_id ? get_the_title( $venue_id ) : '',
				'venue_city'  => $venue_id ? get_post_meta( $venue_id, '_VenueCity', true ) : '',
				'thumbnail'   => get_the_post_thumbnail_url( $event->ID, 'medium' ),
				'excerpt'     => wp_trim_words( $event->post_content, 20 ),
				'business_id' => $business_id,
				'business'    => $business_id ? array(
					'id'   => $business_id,
					'name' => get_the_title( $business_id ),
					'url'  => get_permalink( $business_id ),
				) : null,
			);
		}

		return $results;
	}

	/**
	 * Render events HTML with beautiful styling
	 *
	 * @param array $events Events data.
	 * @param array $atts   Shortcode attributes.
	 * @return string HTML.
	 */
	private static function render_events( $events, $atts ) {
		$layout        = sanitize_key( $atts['layout'] );
		$columns       = absint( $atts['columns'] );
		$show_business = filter_var( $atts['show_business'], FILTER_VALIDATE_BOOLEAN );
		$show_image    = filter_var( $atts['show_image'], FILTER_VALIDATE_BOOLEAN );
		$show_venue    = filter_var( $atts['show_venue'], FILTER_VALIDATE_BOOLEAN );
		$show_time     = filter_var( $atts['show_time'], FILTER_VALIDATE_BOOLEAN );
		$title         = sanitize_text_field( $atts['title'] );
		$view_all_url  = esc_url( $atts['view_all_url'] );

		// Generate unique ID for this instance
		$instance_id = 'bd-events-' . wp_rand( 1000, 9999 );

		ob_start();
		?>
		<style>
			/* Font Awesome */
			@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
			
			/* Google Fonts - Onest for headings */
			@import url('https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap');
			
			/* Base container - Wine Country Theme with Burgundy Accent */
			#<?php echo esc_attr( $instance_id ); ?> {
				/* Primary - Navy (from design-tokens.css) */
				--bd-primary-500: #2a7a94;
				--bd-primary-600: #133453;
				--bd-primary-700: #0f2530;
				
				/* Burgundy - Wine Country Accent */
				--bd-burgundy: #722F37;
				--bd-burgundy-light: #8b3d47;
				--bd-burgundy-dark: #5a252c;
				--bd-burgundy-shadow: rgba(114, 47, 55, 0.3);
				
				/* Gold */
				--bd-gold-400: #f5c522;
				--bd-gold-500: #C9A227;
				--bd-gold-600: #9A7B1A;
				
				/* Neutrals */
				--bd-neutral-50: #f8fafc;
				--bd-neutral-100: #f1f5f9;
				--bd-neutral-200: #e2e8f0;
				--bd-neutral-300: #cbd5e1;
				--bd-neutral-400: #94a3b8;
				--bd-neutral-500: #64748b;
				--bd-neutral-600: #475569;
				--bd-neutral-800: #1e293b;
				--bd-neutral-900: #0f172a;
				
				/* Semantic shortcuts */
				--bd-primary: var(--bd-primary-600);
				--bd-primary-light: var(--bd-primary-500);
				--bd-accent: var(--bd-burgundy);
				--bd-accent-light: var(--bd-burgundy-light);
				--bd-gold: var(--bd-gold-500);
				--bd-text: var(--bd-neutral-900);
				--bd-text-light: var(--bd-neutral-500);
				--bd-bg: var(--bd-neutral-50);
				--bd-white: #fff;
				--bd-border: var(--bd-neutral-200);
				
				/* Shadows */
				--bd-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
				--bd-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
				--bd-shadow-primary: 0 4px 14px rgba(19, 52, 83, 0.25);
				--bd-shadow-accent: 0 4px 14px var(--bd-burgundy-shadow);
				--bd-shadow-gold: 0 4px 14px rgba(201, 162, 39, 0.35);
				
				/* Sizing */
				--bd-radius: 0.75rem;
				--bd-radius-sm: 0.5rem;
				--bd-radius-full: 9999px;
				
				/* Typography */
				--bd-font-heading: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
				--bd-font-body: 'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
				
				margin: 2rem 0;
				font-family: var(--bd-font-body);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> * {
				box-sizing: border-box;
			}
			
			/* Section Header */
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 1.5rem;
				padding-bottom: 1rem;
				border-bottom: 2px solid var(--bd-border);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-title {
				margin: 0;
				font-family: var(--bd-font-heading);
				font-size: 1.75rem;
				font-weight: 700;
				color: var(--bd-text);
				display: flex;
				align-items: center;
				gap: 0.75rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-title i {
				color: var(--bd-gold);
				font-size: 0.9em;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-view-all {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				padding: 0.5rem 1rem;
				background: transparent;
				border: 2px solid var(--bd-primary);
				border-radius: 50px;
				color: var(--bd-primary);
				text-decoration: none;
				font-weight: 600;
				font-size: 0.9rem;
				transition: all 0.3s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-view-all:hover {
				background: var(--bd-primary);
				color: var(--bd-white);
				transform: translateX(3px);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-view-all i {
				transition: transform 0.3s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-view-all:hover i {
				transform: translateX(3px);
			}
			
			/* =====================================================
				GRID LAYOUT - Magazine Feature Cards
				===================================================== */
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-grid {
				display: grid;
				grid-template-columns: repeat(<?php echo esc_attr( $columns ); ?>, 1fr);
				gap: 1.5rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-card {
				position: relative;
				background: var(--bd-white);
				border-radius: var(--bd-radius);
				overflow: hidden;
				box-shadow: var(--bd-shadow);
				transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-card:hover {
				transform: translateY(-6px);
				box-shadow: var(--bd-shadow-hover);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-image {
				position: relative;
				height: 200px;
				overflow: hidden;
				background: linear-gradient(135deg, var(--bd-bg), #e8e4e0);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-card:hover .bd-grid-image img {
				transform: scale(1.08);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-image::after {
				content: '';
				position: absolute;
				bottom: 0;
				left: 0;
				right: 0;
				height: 50%;
				background: linear-gradient(to top, rgba(26, 58, 74, 0.6), transparent);
				pointer-events: none;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-date-badge {
				position: absolute;
				top: 1rem;
				left: 1rem;
				padding: 0.5rem 1rem;
				background: var(--bd-accent);
				color: var(--bd-white);
				font-family: var(--bd-font-heading);
				font-size: 0.85rem;
				font-weight: 700;
				border-radius: 6px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				box-shadow: var(--bd-shadow-accent);
				z-index: 2;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-time-badge {
				position: absolute;
				bottom: 1rem;
				right: 1rem;
				padding: 0.4rem 0.8rem;
				background: rgba(255, 255, 255, 0.95);
				color: var(--bd-primary);
				font-size: 0.8rem;
				font-weight: 600;
				border-radius: 20px;
				display: flex;
				align-items: center;
				gap: 0.4rem;
				z-index: 2;
				backdrop-filter: blur(4px);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-content {
				padding: 1.25rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-title {
				margin: 0 0 0.75rem 0;
				font-family: var(--bd-font-heading);
				font-size: 1.15rem;
				font-weight: 600;
				line-height: 1.35;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-title a {
				color: var(--bd-text);
				text-decoration: none;
				transition: color 0.2s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-title a:hover {
				color: var(--bd-accent);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-meta {
				display: flex;
				flex-direction: column;
				gap: 0.5rem;
				font-size: 0.9rem;
				color: var(--bd-text-light);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-meta span {
				display: flex;
				align-items: center;
				gap: 0.5rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-meta i {
				width: 16px;
				color: var(--bd-primary);
				opacity: 0.7;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-business {
				margin-top: 1rem;
				padding-top: 1rem;
				border-top: 1px solid var(--bd-border);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-business a {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				padding: 0.4rem 0.75rem;
				background: linear-gradient(135deg, var(--bd-bg), #f0ece8);
				border-radius: 20px;
				color: var(--bd-primary);
				text-decoration: none;
				font-size: 0.85rem;
				font-weight: 600;
				transition: all 0.2s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-grid-business a:hover {
				background: var(--bd-primary);
				color: var(--bd-white);
			}
			
			/* =====================================================
				LIST LAYOUT - Timeline Style
				===================================================== */
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-list {
				display: flex;
				flex-direction: column;
				gap: 1rem;
				position: relative;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-list::before {
				content: '';
				position: absolute;
				left: 42px;
				top: 0;
				bottom: 0;
				width: 2px;
				background: linear-gradient(to bottom, var(--bd-accent), var(--bd-primary));
				border-radius: 2px;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-card {
				display: flex;
				align-items: stretch;
				gap: 1.25rem;
				padding: 1.25rem;
				background: var(--bd-white);
				border-radius: var(--bd-radius);
				box-shadow: var(--bd-shadow);
				position: relative;
				transition: all 0.3s ease;
				margin-left: 20px;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-card:hover {
				transform: translateX(8px);
				box-shadow: var(--bd-shadow-hover);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-date {
				flex-shrink: 0;
				width: 65px;
				text-align: center;
				padding: 0.75rem 0.5rem;
				background: linear-gradient(135deg, var(--bd-accent), var(--bd-burgundy-dark));
				border-radius: var(--bd-radius-sm);
				color: var(--bd-white);
				position: relative;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-date::before {
				content: '';
				position: absolute;
				left: -32px;
				top: 50%;
				transform: translateY(-50%);
				width: 12px;
				height: 12px;
				background: var(--bd-white);
				border: 3px solid var(--bd-accent);
				border-radius: 50%;
				box-shadow: 0 0 0 4px var(--bd-burgundy-shadow);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-date .bd-month {
				display: block;
				font-size: 0.7rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 1px;
				opacity: 0.9;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-date .bd-day {
				display: block;
				font-family: var(--bd-font-heading);
				font-size: 1.75rem;
				font-weight: 700;
				line-height: 1;
				margin-top: 2px;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-image {
				flex-shrink: 0;
				width: 100px;
				height: 100px;
				border-radius: var(--bd-radius-sm);
				overflow: hidden;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.4s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-card:hover .bd-list-image img {
				transform: scale(1.1);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-content {
				flex: 1;
				min-width: 0;
				display: flex;
				flex-direction: column;
				justify-content: center;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-title {
				margin: 0 0 0.5rem 0;
				font-family: var(--bd-font-heading);
				font-size: 1.1rem;
				font-weight: 600;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-title a {
				color: var(--bd-text);
				text-decoration: none;
				transition: color 0.2s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-title a:hover {
				color: var(--bd-accent);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta {
				display: flex;
				flex-wrap: wrap;
				gap: 0.5rem 1.25rem;
				font-size: 0.875rem;
				color: var(--bd-text-light);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta span,
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta a {
				display: inline-flex;
				align-items: center;
				gap: 0.4rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta i {
				color: var(--bd-primary);
				opacity: 0.7;
				font-size: 0.9em;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta a {
				color: var(--bd-primary);
				text-decoration: none;
				font-weight: 600;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-meta a:hover {
				color: var(--bd-accent);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-arrow {
				flex-shrink: 0;
				display: flex;
				align-items: center;
				justify-content: center;
				width: 44px;
				height: 44px;
				background: var(--bd-bg);
				border-radius: 50%;
				color: var(--bd-primary);
				font-size: 0.9rem;
				transition: all 0.3s ease;
				align-self: center;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-list-card:hover .bd-list-arrow {
				background: var(--bd-primary);
				color: var(--bd-white);
				transform: translateX(4px);
			}
			
			/* =====================================================
				COMPACT LAYOUT - Minimal Timeline
				===================================================== */
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-compact {
				background: var(--bd-white);
				border-radius: var(--bd-radius);
				box-shadow: var(--bd-shadow);
				overflow: hidden;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-item {
				display: flex;
				align-items: center;
				padding: 1rem 1.25rem;
				border-bottom: 1px solid var(--bd-border);
				transition: all 0.2s ease;
				text-decoration: none;
				color: inherit;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-item:last-child {
				border-bottom: none;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-item:hover {
				background: linear-gradient(90deg, rgba(114, 47, 55, 0.08), transparent);
				padding-left: 1.5rem;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-date {
				flex-shrink: 0;
				display: flex;
				align-items: center;
				gap: 0.75rem;
				min-width: 100px;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-date-icon {
				width: 36px;
				height: 36px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				background: var(--bd-accent);
				border-radius: 6px;
				color: var(--bd-white);
				font-size: 0.65rem;
				font-weight: 700;
				text-transform: uppercase;
				line-height: 1.1;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-date-icon .bd-day {
				font-size: 1rem;
				font-family: var(--bd-font-heading);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-title {
				flex: 1;
				font-weight: 600;
				color: var(--bd-text);
				margin: 0 1rem;
				font-family: var(--bd-font-heading);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-time {
				flex-shrink: 0;
				display: flex;
				align-items: center;
				gap: 0.4rem;
				font-size: 0.85rem;
				color: var(--bd-text-light);
				padding: 0.3rem 0.75rem;
				background: var(--bd-bg);
				border-radius: 20px;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-time i {
				color: var(--bd-primary);
				opacity: 0.7;
				font-size: 0.8em;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-arrow {
				flex-shrink: 0;
				margin-left: 0.75rem;
				color: var(--bd-border);
				transition: all 0.2s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-compact-item:hover .bd-compact-arrow {
				color: var(--bd-accent);
				transform: translateX(3px);
			}
			
			/* Footer CTA */
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-footer {
				margin-top: 1.5rem;
				text-align: center;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-footer-btn {
				display: inline-flex;
				align-items: center;
				gap: 0.5rem;
				padding: 0.875rem 2rem;
				background: linear-gradient(135deg, var(--bd-primary), var(--bd-primary-light));
				color: var(--bd-white);
				text-decoration: none;
				border-radius: 50px;
				font-weight: 600;
				font-size: 1rem;
				transition: all 0.3s ease;
				box-shadow: 0 4px 15px rgba(26, 58, 74, 0.25);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-footer-btn:hover {
				transform: translateY(-2px);
				box-shadow: 0 6px 20px rgba(26, 58, 74, 0.35);
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-footer-btn i {
				transition: transform 0.3s ease;
			}
			
			#<?php echo esc_attr( $instance_id ); ?> .bd-events-footer-btn:hover i {
				transform: translateX(3px);
			}
			
			/* Responsive */
			@media (max-width: 992px) {
				#<?php echo esc_attr( $instance_id ); ?> .bd-events-grid {
					grid-template-columns: repeat(2, 1fr);
				}
			}
			
			@media (max-width: 768px) {
				#<?php echo esc_attr( $instance_id ); ?> .bd-events-header {
					flex-direction: column;
					align-items: flex-start;
					gap: 1rem;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-events-grid {
					grid-template-columns: 1fr;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-events-list::before {
					display: none;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-list-card {
					margin-left: 0;
					flex-wrap: wrap;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-list-date::before {
					display: none;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-list-image {
					width: 100%;
					height: 150px;
					order: -1;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-list-content {
					width: 100%;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-list-arrow {
					display: none;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-compact-item {
					flex-wrap: wrap;
					gap: 0.5rem;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-compact-title {
					width: 100%;
					order: 1;
					margin: 0.5rem 0 0 0;
				}
				
				#<?php echo esc_attr( $instance_id ); ?> .bd-compact-time {
					order: 0;
				}
			}
		</style>
		
		<div id="<?php echo esc_attr( $instance_id ); ?>">
			<?php if ( $title || $view_all_url ) : ?>
				<div class="bd-events-header">
					<?php if ( $title ) : ?>
						<h2 class="bd-events-title">
							<i class="fas fa-calendar-star"></i>
							<?php echo esc_html( $title ); ?>
						</h2>
					<?php endif; ?>
					<?php if ( $view_all_url ) : ?>
						<a href="<?php echo esc_url( $view_all_url ); ?>" class="bd-events-view-all">
							<?php esc_html_e( 'View All', 'business-directory' ); ?>
							<i class="fas fa-arrow-right"></i>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( 'grid' === $layout ) : ?>
				<?php echo self::render_grid_v2( $events, $show_image, $show_venue, $show_time, $show_business ); // phpcs:ignore ?>
			<?php elseif ( 'list' === $layout ) : ?>
				<?php echo self::render_list_v2( $events, $show_image, $show_venue, $show_time, $show_business ); // phpcs:ignore ?>
			<?php elseif ( 'compact' === $layout ) : ?>
				<?php echo self::render_compact_v2( $events, $show_time ); // phpcs:ignore ?>
			<?php endif; ?>

			<?php if ( $view_all_url && ! $title ) : ?>
				<div class="bd-events-footer">
					<a href="<?php echo esc_url( $view_all_url ); ?>" class="bd-events-footer-btn">
						<?php esc_html_e( 'View All Events', 'business-directory' ); ?>
						<i class="fas fa-arrow-right"></i>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render grid layout v2
	 */
	private static function render_grid_v2( $events, $show_image, $show_venue, $show_time, $show_business ) {
		ob_start();
		?>
		<div class="bd-events-grid">
			<?php foreach ( $events as $event ) : ?>
				<article class="bd-grid-card">
					<?php if ( $show_image ) : ?>
						<div class="bd-grid-image">
							<?php if ( ! empty( $event['thumbnail'] ) ) : ?>
								<a href="<?php echo esc_url( $event['url'] ); ?>">
									<img src="<?php echo esc_url( $event['thumbnail'] ); ?>" 
										alt="<?php echo esc_attr( $event['title'] ); ?>" 
										loading="lazy">
								</a>
							<?php endif; ?>
							<span class="bd-grid-date-badge">
								<?php echo esc_html( gmdate( 'M j', strtotime( $event['start_date'] ) ) ); ?>
							</span>
							<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
								<span class="bd-grid-time-badge">
									<i class="far fa-clock"></i>
									<?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					
					<div class="bd-grid-content">
						<?php if ( ! $show_image ) : ?>
							<span style="display: block; font-size: 0.85rem; color: var(--bd-primary); font-weight: 600; margin-bottom: 0.5rem;">
								<i class="far fa-calendar" style="margin-right: 0.4rem;"></i>
								<?php echo esc_html( gmdate( 'D, M j', strtotime( $event['start_date'] ) ) ); ?>
								<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
									@ <?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
								<?php endif; ?>
							</span>
						<?php endif; ?>
						
						<h3 class="bd-grid-title">
							<a href="<?php echo esc_url( $event['url'] ); ?>">
								<?php echo esc_html( $event['title'] ); ?>
							</a>
						</h3>
						
						<div class="bd-grid-meta">
							<?php
							// Only show venue if no business is linked (avoid redundancy)
							$has_business = $show_business && ! empty( $event['business'] );
							if ( $show_venue && ! empty( $event['venue'] ) && ! $has_business ) :
								?>
								<span>
									<i class="fas fa-map-marker-alt"></i>
									<?php echo esc_html( $event['venue'] ); ?>
								</span>
							<?php endif; ?>
						</div>
						
						<?php if ( $has_business ) : ?>
							<div class="bd-grid-business">
								<a href="<?php echo esc_url( $event['business']['url'] ); ?>">
									<i class="fas fa-store"></i>
									<?php echo esc_html( $event['business']['name'] ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render list layout v2 - Timeline style
	 */
	private static function render_list_v2( $events, $show_image, $show_venue, $show_time, $show_business ) {
		ob_start();
		?>
		<div class="bd-events-list">
			<?php foreach ( $events as $event ) : ?>
				<article class="bd-list-card">
					<div class="bd-list-date">
						<span class="bd-month"><?php echo esc_html( gmdate( 'M', strtotime( $event['start_date'] ) ) ); ?></span>
						<span class="bd-day"><?php echo esc_html( gmdate( 'j', strtotime( $event['start_date'] ) ) ); ?></span>
					</div>
					
					<?php if ( $show_image && ! empty( $event['thumbnail'] ) ) : ?>
						<div class="bd-list-image">
							<a href="<?php echo esc_url( $event['url'] ); ?>">
								<img src="<?php echo esc_url( $event['thumbnail'] ); ?>" 
									alt="<?php echo esc_attr( $event['title'] ); ?>" 
									loading="lazy">
							</a>
						</div>
					<?php endif; ?>
					
					<div class="bd-list-content">
						<h3 class="bd-list-title">
							<a href="<?php echo esc_url( $event['url'] ); ?>">
								<?php echo esc_html( $event['title'] ); ?>
							</a>
						</h3>
						
						<div class="bd-list-meta">
							<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
								<span>
									<i class="far fa-clock"></i>
									<?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
								</span>
							<?php endif; ?>
							
							<?php
							// Only show venue if no business is linked (avoid redundancy)
							$has_business = $show_business && ! empty( $event['business'] );
							if ( $show_venue && ! empty( $event['venue'] ) && ! $has_business ) :
								?>
								<span>
									<i class="fas fa-map-marker-alt"></i>
									<?php echo esc_html( $event['venue'] ); ?>
								</span>
							<?php endif; ?>
							
							<?php if ( $has_business ) : ?>
								<a href="<?php echo esc_url( $event['business']['url'] ); ?>">
									<i class="fas fa-store"></i>
									<?php echo esc_html( $event['business']['name'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
					
					<a href="<?php echo esc_url( $event['url'] ); ?>" class="bd-list-arrow">
						<i class="fas fa-chevron-right"></i>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render compact layout v2 - Minimal with calendar icons
	 */
	private static function render_compact_v2( $events, $show_time ) {
		ob_start();
		?>
		<div class="bd-events-compact">
			<?php foreach ( $events as $event ) : ?>
				<a href="<?php echo esc_url( $event['url'] ); ?>" class="bd-compact-item">
					<div class="bd-compact-date">
						<div class="bd-compact-date-icon">
							<span><?php echo esc_html( gmdate( 'M', strtotime( $event['start_date'] ) ) ); ?></span>
							<span class="bd-day"><?php echo esc_html( gmdate( 'j', strtotime( $event['start_date'] ) ) ); ?></span>
						</div>
					</div>
					
					<span class="bd-compact-title">
						<?php echo esc_html( $event['title'] ); ?>
					</span>
					
					<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
						<span class="bd-compact-time">
							<i class="far fa-clock"></i>
							<?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
						</span>
					<?php endif; ?>
					
					<span class="bd-compact-arrow">
						<i class="fas fa-chevron-right"></i>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
