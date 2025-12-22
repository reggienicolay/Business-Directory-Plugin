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
 * @version 1.2.0
 */

namespace BD\Integrations\EventsCalendar;

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
			if ( current_user_can( 'manage_options' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BD City Events: Remote fetch returned ' . $code );
			}
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Fetch events locally
	 *
	 * @param string $city  City name.
	 * @param int    $limit Number of events.
	 * @return array Events data.
	 */
	private static function fetch_local_events( $city, $limit ) {
		// Check if Events Calendar is active
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
	 * Render events HTML with inline styles
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

		ob_start();
		?>
		<div style="margin: 30px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">

			<?php if ( $title ) : ?>
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
					<h2 style="margin: 0; font-size: 1.5rem; font-weight: 600; color: #1a1a1a;"><?php echo esc_html( $title ); ?></h2>
					<?php if ( $view_all_url ) : ?>
						<a href="<?php echo esc_url( $view_all_url ); ?>" style="font-size: 0.9rem; color: #1a3a4a; text-decoration: none; font-weight: 500;">
							<?php esc_html_e( 'View All Events →', 'business-directory' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( 'grid' === $layout ) : ?>
				<?php echo self::render_grid( $events, $show_image, $show_venue, $show_time, $show_business, $columns ); ?>
			<?php elseif ( 'list' === $layout ) : ?>
				<?php echo self::render_list( $events, $show_image, $show_venue, $show_time, $show_business ); ?>
			<?php elseif ( 'compact' === $layout ) : ?>
				<?php echo self::render_compact( $events, $show_time ); ?>
			<?php endif; ?>

			<?php if ( $view_all_url && ! $title ) : ?>
				<div style="margin-top: 20px; text-align: center;">
					<a href="<?php echo esc_url( $view_all_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #1a3a4a; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500;">
						<?php esc_html_e( 'View All Events →', 'business-directory' ); ?>
					</a>
				</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render grid layout with inline styles
	 *
	 * @param array $events        Events data.
	 * @param bool  $show_image    Show image.
	 * @param bool  $show_venue    Show venue.
	 * @param bool  $show_time     Show time.
	 * @param bool  $show_business Show business.
	 * @param int   $columns       Number of columns.
	 * @return string HTML.
	 */
	private static function render_grid( $events, $show_image, $show_venue, $show_time, $show_business, $columns ) {
		// Calculate column width percentage
		$col_width = floor( 100 / $columns ) - 2;

		ob_start();
		?>
		<div style="display: flex; flex-wrap: wrap; gap: 25px; margin: 0 -5px;">
			<?php foreach ( $events as $event ) : ?>
				<div style="flex: 0 0 calc(<?php echo esc_attr( $col_width ); ?>% - 15px); min-width: 280px; max-width: 100%; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);">
					<?php if ( $show_image && ! empty( $event['thumbnail'] ) ) : ?>
						<div style="position: relative; height: 180px; overflow: hidden; background: linear-gradient(135deg, #f0f0f0, #e0e0e0);">
							<a href="<?php echo esc_url( $event['url'] ); ?>">
								<img src="<?php echo esc_url( $event['thumbnail'] ); ?>" 
									alt="<?php echo esc_attr( $event['title'] ); ?>" 
									loading="lazy"
									style="width: 100%; height: 100%; object-fit: cover;">
							</a>
							<span style="position: absolute; top: 12px; left: 12px; padding: 6px 12px; background: #1a3a4a; color: #fff; font-size: 0.8rem; font-weight: 600; border-radius: 4px; text-transform: uppercase;">
								<?php echo esc_html( gmdate( 'M j', strtotime( $event['start_date'] ) ) ); ?>
							</span>
						</div>
					<?php endif; ?>

					<div style="padding: 18px;">
						<?php if ( ! $show_image || empty( $event['thumbnail'] ) ) : ?>
							<span style="display: block; font-size: 0.85rem; color: #1a3a4a; font-weight: 600; margin-bottom: 8px;">
								<?php echo esc_html( gmdate( 'D, M j', strtotime( $event['start_date'] ) ) ); ?>
								<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
									<span style="color: #666; font-weight: 400;">
										@ <?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
									</span>
								<?php endif; ?>
							</span>
						<?php endif; ?>

						<h3 style="margin: 0 0 10px 0; font-size: 1.1rem; font-weight: 600; line-height: 1.3;">
							<a href="<?php echo esc_url( $event['url'] ); ?>" style="color: #1a1a1a; text-decoration: none;">
								<?php echo esc_html( $event['title'] ); ?>
							</a>
						</h3>

						<?php if ( $show_image && $show_time && ! empty( $event['start_time'] ) ) : ?>
							<span style="display: block; font-size: 0.85rem; color: #666; margin-bottom: 8px;">
								<?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
							</span>
						<?php endif; ?>

						<?php if ( $show_venue && ! empty( $event['venue'] ) ) : ?>
							<p style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #555; margin: 0 0 8px 0;">
								<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" style="flex-shrink: 0; opacity: 0.7;">
									<path d="M7 0C4.2 0 2 2.2 2 5c0 3.5 5 9 5 9s5-5.5 5-9c0-2.8-2.2-5-5-5zm0 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
								</svg>
								<?php echo esc_html( $event['venue'] ); ?>
							</p>
						<?php endif; ?>

						<?php if ( $show_business && ! empty( $event['business'] ) ) : ?>
							<p style="margin: 0; padding-top: 10px; border-top: 1px solid #eee; font-size: 0.85rem;">
								<a href="<?php echo esc_url( $event['business']['url'] ); ?>" style="color: #1a3a4a; text-decoration: none; font-weight: 500;">
									<?php echo esc_html( $event['business']['name'] ); ?>
								</a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render list layout with inline styles
	 *
	 * @param array $events        Events data.
	 * @param bool  $show_image    Show image.
	 * @param bool  $show_venue    Show venue.
	 * @param bool  $show_time     Show time.
	 * @param bool  $show_business Show business.
	 * @return string HTML.
	 */
	private static function render_list( $events, $show_image, $show_venue, $show_time, $show_business ) {
		ob_start();
		?>
		<div style="display: flex; flex-direction: column; gap: 15px;">
			<?php foreach ( $events as $event ) : ?>
				<div style="display: flex; align-items: center; gap: 20px; padding: 15px 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
					<?php if ( $show_image && ! empty( $event['thumbnail'] ) ) : ?>
						<div style="flex-shrink: 0; width: 80px; height: 80px; border-radius: 8px; overflow: hidden;">
							<a href="<?php echo esc_url( $event['url'] ); ?>">
								<img src="<?php echo esc_url( $event['thumbnail'] ); ?>" 
									alt="<?php echo esc_attr( $event['title'] ); ?>" 
									loading="lazy"
									style="width: 100%; height: 100%; object-fit: cover;">
							</a>
						</div>
					<?php endif; ?>

					<div style="flex-shrink: 0; width: 60px; text-align: center; padding: 10px; background: #f8f4f0; border-radius: 8px;">
						<span style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #1a3a4a; letter-spacing: 0.5px;"><?php echo esc_html( gmdate( 'M', strtotime( $event['start_date'] ) ) ); ?></span>
						<span style="display: block; font-size: 1.5rem; font-weight: 700; color: #1a1a1a; line-height: 1; margin-top: 2px;"><?php echo esc_html( gmdate( 'j', strtotime( $event['start_date'] ) ) ); ?></span>
					</div>

					<div style="flex: 1; min-width: 0;">
						<h3 style="margin: 0 0 6px 0; font-size: 1rem; font-weight: 600;">
							<a href="<?php echo esc_url( $event['url'] ); ?>" style="color: #1a1a1a; text-decoration: none;">
								<?php echo esc_html( $event['title'] ); ?>
							</a>
						</h3>

						<div style="display: flex; flex-wrap: wrap; gap: 8px 15px; font-size: 0.85rem; color: #666;">
							<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
								<span>🕐 <?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?></span>
							<?php endif; ?>

							<?php if ( $show_venue && ! empty( $event['venue'] ) ) : ?>
								<span>📍 <?php echo esc_html( $event['venue'] ); ?></span>
							<?php endif; ?>

							<?php if ( $show_business && ! empty( $event['business'] ) ) : ?>
								<a href="<?php echo esc_url( $event['business']['url'] ); ?>" style="color: #1a3a4a; text-decoration: none; font-weight: 500;">
									🏪 <?php echo esc_html( $event['business']['name'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<a href="<?php echo esc_url( $event['url'] ); ?>" style="flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #f5f5f5; border-radius: 50%; color: #666; text-decoration: none;">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
							<path d="M7 4l6 6-6 6V4z"/>
						</svg>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render compact layout with inline styles
	 *
	 * @param array $events    Events data.
	 * @param bool  $show_time Show time.
	 * @return string HTML.
	 */
	private static function render_compact( $events, $show_time ) {
		ob_start();
		?>
		<ul style="list-style: none; margin: 0; padding: 0;">
			<?php foreach ( $events as $index => $event ) : ?>
				<li style="<?php echo $index < count( $events ) - 1 ? 'border-bottom: 1px solid #eee;' : ''; ?>">
					<a href="<?php echo esc_url( $event['url'] ); ?>" style="display: flex; align-items: center; gap: 15px; padding: 12px 0; text-decoration: none; color: inherit;">
						<span style="flex-shrink: 0; min-width: 60px; font-size: 0.85rem; font-weight: 600; color: #1a3a4a;">
							<?php echo esc_html( gmdate( 'M j', strtotime( $event['start_date'] ) ) ); ?>
						</span>
						<span style="flex: 1; font-weight: 500; color: #1a1a1a;">
							<?php echo esc_html( $event['title'] ); ?>
						</span>
						<?php if ( $show_time && ! empty( $event['start_time'] ) ) : ?>
							<span style="flex-shrink: 0; font-size: 0.85rem; color: #666;">
								<?php echo esc_html( gmdate( 'g:i A', strtotime( $event['start_time'] ) ) ); ?>
							</span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}
}
