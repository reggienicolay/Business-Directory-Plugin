<?php
/**
 * Events Calendar Integration
 *
 * Main bootstrap for The Events Calendar Pro integration.
 * Links businesses to events, venues, and organizers.
 *
 * @package BusinessDirectory
 * @version 1.3.0
 */

namespace BD\Integrations\EventsCalendar;

use BD\Integrations\IntegrationsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EventsCalendarIntegration
 */
class EventsCalendarIntegration {

	/**
	 * Initialize the integration
	 */
	public static function init() {
		// Load sub-components
		require_once __DIR__ . '/BusinessLinker.php';
		require_once __DIR__ . '/CityEventsShortcode.php';

		// Initialize components
		BusinessLinker::init();
		CityEventsShortcode::init();

		// Display events on business pages
		if ( IntegrationsManager::get_setting( 'events_calendar', 'show_events_on_business', true ) ) {
			add_action( 'bd_after_business_content', array( __CLASS__, 'display_business_events' ) );
			add_shortcode( 'bd_business_events', array( __CLASS__, 'business_events_shortcode' ) );
		}

		// Display business card on event pages (replaces venue when linked)
		if ( IntegrationsManager::get_setting( 'events_calendar', 'show_business_on_events', true ) ) {
			add_filter( 'the_content', array( __CLASS__, 'add_business_card_to_event' ), 20 );
			// Note: Venue hiding is handled via inline CSS in render_business_card()
			// This works for both block-based and classic TEC templates
		}

		// Register REST API endpoints for city sites
		if ( IntegrationsManager::get_setting( 'events_calendar', 'enable_city_events_api', true ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		}

		// Enqueue assets
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public static function enqueue_assets() {
		// Only load on relevant pages
		if ( ! is_singular( 'bd_business' ) && ! is_singular( 'tribe_events' ) ) {
			return;
		}

		// Design tokens (fonts, colors)
		wp_enqueue_style(
			'bd-design-tokens',
			BD_PLUGIN_URL . 'assets/css/design-tokens.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_style(
			'bd-events-integration',
			BD_PLUGIN_URL . 'assets/css/integrations/events-calendar.css',
			array( 'bd-design-tokens' ),
			BD_VERSION
		);
	}

	/**
	 * Hide TEC venue block when a business is linked
	 * This prevents duplicate location info (venue block + business card)
	 *
	 * @param string $html     The template HTML.
	 * @param string $file     Template file path.
	 * @param array  $name     Template name.
	 * @param object $template Template object.
	 * @return string Modified HTML (empty if business linked).
	 */
	public static function maybe_hide_venue_block( $html, $file = '', $name = '', $template = null ) {
		if ( ! is_singular( 'tribe_events' ) ) {
			return $html;
		}

		$event_id    = get_the_ID();
		$business_id = self::get_business_for_event( $event_id );

		// If business is linked, hide the venue block (we'll show our business card instead)
		if ( $business_id ) {
			$business = get_post( $business_id );
			if ( $business && 'publish' === $business->post_status ) {
				return ''; // Return empty - our business card via the_content filter will show instead
			}
		}

		// No business linked - show normal TEC venue block
		return $html;
	}

	/**
	 * Hide venue HTML for older TEC filter
	 *
	 * @param string $html     The venue HTML.
	 * @param int    $event_id Event post ID.
	 * @return string Modified HTML.
	 */
	public static function maybe_hide_venue_html( $html, $event_id = 0 ) {
		if ( ! $event_id ) {
			$event_id = get_the_ID();
		}

		if ( ! is_singular( 'tribe_events' ) ) {
			return $html;
		}

		$business_id = self::get_business_for_event( $event_id );

		if ( $business_id ) {
			$business = get_post( $business_id );
			if ( $business && 'publish' === $business->post_status ) {
				return '';
			}
		}

		return $html;
	}

	/**
	 * Hide venue name when business is linked
	 *
	 * @param string $venue    The venue name/HTML.
	 * @param int    $event_id Event post ID.
	 * @return string Modified venue.
	 */
	public static function maybe_hide_venue_name( $venue, $event_id = 0 ) {
		// Only hide on single event pages, not in admin or listings
		if ( ! is_singular( 'tribe_events' ) || is_admin() ) {
			return $venue;
		}

		if ( ! $event_id ) {
			$event_id = get_the_ID();
		}

		$business_id = self::get_business_for_event( $event_id );

		if ( $business_id ) {
			$business = get_post( $business_id );
			if ( $business && 'publish' === $business->post_status ) {
				return '';
			}
		}

		return $venue;
	}

	/**
	 * Display upcoming events on a business page
	 *
	 * @param int $business_id Business post ID.
	 */
	public static function display_business_events( $business_id = null ) {
		if ( ! $business_id ) {
			$business_id = get_the_ID();
		}

		$events = self::get_business_events( $business_id, 5 );

		if ( empty( $events ) ) {
			return;
		}

		?>
		<div class="bd-business-events">
			<h3 class="bd-business-events-title">
				<?php esc_html_e( 'Upcoming Events', 'business-directory' ); ?>
			</h3>
			<ul class="bd-events-list">
				<?php foreach ( $events as $event ) : ?>
					<li class="bd-event-item">
						<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>" class="bd-event-link">
							<span class="bd-event-date">
								<?php echo esc_html( tribe_get_start_date( $event->ID, false, 'M j' ) ); ?>
							</span>
							<span class="bd-event-title">
								<?php echo esc_html( get_the_title( $event->ID ) ); ?>
							</span>
							<span class="bd-event-time">
								<?php echo esc_html( tribe_get_start_date( $event->ID, false, 'g:i A' ) ); ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url( self::get_business_events_link( $business_id ) ); ?>" class="bd-events-view-all">
				<?php esc_html_e( 'View All Events →', 'business-directory' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Shortcode for displaying business events
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function business_events_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'limit'  => 5,
				'source' => '',  // Source site URL for remote fetch (e.g., lovetrivalley.com)
			),
			$atts
		);

		$business_id = absint( $atts['id'] );
		$limit       = absint( $atts['limit'] );
		$source      = sanitize_text_field( $atts['source'] );

		if ( ! $business_id ) {
			$business_id = get_the_ID();
		}

		// If source is provided, fetch remotely
		if ( ! empty( $source ) ) {
			return self::render_remote_business_events( $business_id, $limit, $source );
		}

		// Local fetch - verify business exists
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			if ( current_user_can( 'manage_options' ) ) {
				return self::render_admin_error(
					'Business Events: Invalid business ID ' . $business_id
				);
			}
			return '';
		}

		$events = self::get_business_events( $business_id, $limit );

		if ( empty( $events ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="bd-business-events">
			<h3 class="bd-business-events-title">
				<?php esc_html_e( 'Upcoming Events', 'business-directory' ); ?>
			</h3>
			<ul class="bd-events-list">
				<?php foreach ( $events as $event ) : ?>
					<li class="bd-event-item">
						<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>" class="bd-event-link">
							<span class="bd-event-date">
								<?php echo esc_html( tribe_get_start_date( $event->ID, false, 'M j' ) ); ?>
							</span>
							<span class="bd-event-title">
								<?php echo esc_html( get_the_title( $event->ID ) ); ?>
							</span>
							<span class="bd-event-time">
								<?php echo esc_html( tribe_get_start_date( $event->ID, false, 'g:i A' ) ); ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url( self::get_business_events_link( $business_id ) ); ?>" class="bd-events-view-all">
				<?php esc_html_e( 'View All Events →', 'business-directory' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render business events from remote source
	 *
	 * @param int    $business_id Business post ID on the remote site.
	 * @param int    $limit       Number of events.
	 * @param string $source      Source site domain.
	 * @return string HTML output.
	 */
	private static function render_remote_business_events( $business_id, $limit, $source ) {
		// Clean up the source URL
		$source = preg_replace( '#^https?://#', '', $source );
		$source = rtrim( $source, '/' );

		$api_url = 'https://' . $source . '/wp-json/bd/v1/events/business/' . $business_id;
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
				return self::render_admin_error(
					'Business Events: Failed to fetch from ' . esc_html( $source )
				);
			}
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( current_user_can( 'manage_options' ) ) {
				return self::render_admin_error(
					'Business Events: Remote server returned ' . esc_html( $code )
				);
			}
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$error_msg = isset( $data['message'] ) ? $data['message'] : 'Unknown error';
				return self::render_admin_error( 'Business Events: ' . esc_html( $error_msg ) );
			}
			return '';
		}

		$events   = $data['events'] ?? array();
		$business = $data['business'] ?? array();

		if ( empty( $events ) ) {
			return '';
		}

		// Inline styles for network site compatibility (CSS files may not be available).
		$container_style  = 'margin: 30px 0; padding: 25px; background: #f9f9f9; ';
		$container_style .= 'border-radius: 8px; border: 1px solid #e0e0e0;';

		$title_style  = 'margin: 0 0 20px 0; font-size: 1.25rem; font-weight: 600; ';
		$title_style .= 'color: #1a1a1a; display: flex; align-items: center; gap: 8px;';

		$link_style  = 'display: flex; align-items: center; padding: 12px 15px; ';
		$link_style .= 'background: #fff; border-radius: 6px; border: 1px solid #e5e5e5; ';
		$link_style .= 'text-decoration: none; color: inherit;';

		$date_style  = 'min-width: 60px; padding: 4px 10px; background: #1a3a4a; ';
		$date_style .= 'color: #fff; font-size: 0.8rem; font-weight: 600; ';
		$date_style .= 'text-align: center; border-radius: 4px; margin-right: 15px;';

		$btn_style  = 'display: inline-block; margin-top: 15px; padding: 10px 20px; ';
		$btn_style .= 'background: #1a3a4a; color: #fff; text-decoration: none; ';
		$btn_style .= 'border-radius: 5px; font-size: 0.9rem; font-weight: 500;';

		ob_start();
		?>
		<div class="bd-business-events" style="<?php echo esc_attr( $container_style ); ?>">
			<h3 class="bd-business-events-title" style="<?php echo esc_attr( $title_style ); ?>">
				📅 <?php esc_html_e( 'Upcoming Events', 'business-directory' ); ?>
			</h3>
			<ul class="bd-events-list" style="list-style: none; margin: 0; padding: 0;">
				<?php foreach ( $events as $event ) : ?>
					<li class="bd-event-item" style="margin-bottom: 8px;">
						<a href="<?php echo esc_url( $event['url'] ); ?>" 
							class="bd-event-link" 
							target="_blank" 
							style="<?php echo esc_attr( $link_style ); ?>">
							<span class="bd-event-date" style="<?php echo esc_attr( $date_style ); ?>">
								<?php echo esc_html( gmdate( 'M j', strtotime( $event['start_date'] ) ) ); ?>
							</span>
							<span class="bd-event-title" style="flex: 1; font-weight: 500; color: #333;">
								<?php echo esc_html( $event['title'] ); ?>
							</span>
							<span class="bd-event-time" style="font-size: 0.85rem; color: #666; margin-left: 15px;">
								<?php
								$datetime = $event['start_date'] . ' ' . $event['start_time'];
								echo esc_html( gmdate( 'g:i A', strtotime( $datetime ) ) );
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $business['url'] ) ) : ?>
				<a href="<?php echo esc_url( $business['url'] ); ?>" 
					class="bd-events-view-all" 
					target="_blank" 
					style="<?php echo esc_attr( $btn_style ); ?>">
					<?php esc_html_e( 'View All Events →', 'business-directory' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get events for a business
	 *
	 * @param int $business_id Business post ID.
	 * @param int $limit       Number of events to return.
	 * @return array Array of event posts.
	 */
	public static function get_business_events( $business_id, $limit = 5 ) {
		// Get venue linked to this business
		$venue_id = self::get_venue_for_business( $business_id );

		// Get events directly linked to business
		$direct_event_ids = get_posts(
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
						'key'     => 'bd_linked_business',
						'value'   => $business_id,
						'compare' => '=',
					),
					array(
						'key'     => '_EventStartDate',
						'value'   => current_time( 'mysql' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'fields'         => 'ids',
			)
		);

		// Get events at the venue
		$venue_event_ids = array();
		if ( $venue_id ) {
			$venue_event_ids = get_posts(
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
							'value'   => $venue_id,
							'compare' => '=',
						),
						array(
							'key'     => '_EventStartDate',
							'value'   => current_time( 'mysql' ),
							'compare' => '>=',
							'type'    => 'DATETIME',
						),
					),
					'fields'         => 'ids',
				)
			);
		}

		// Merge and deduplicate
		$event_ids = array_unique( array_merge( $direct_event_ids, $venue_event_ids ) );

		if ( empty( $event_ids ) ) {
			return array();
		}

		// Get event posts sorted by start date
		return get_posts(
			array(
				'post_type'      => 'tribe_events',
				'post__in'       => $event_ids,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_key'       => '_EventStartDate',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Get venue ID linked to a business
	 *
	 * @param int $business_id Business post ID.
	 * @return int|null Venue post ID or null.
	 */
	public static function get_venue_for_business( $business_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$venue_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = 'bd_linked_business' 
				AND meta_value = %d
				AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tribe_venue' AND post_status = 'publish')",
				$business_id
			)
		);

		return $venue_id ? absint( $venue_id ) : null;
	}

	/**
	 * Get organizer ID linked to a business
	 *
	 * @param int $business_id Business post ID.
	 * @return int|null Organizer post ID or null.
	 */
	public static function get_organizer_for_business( $business_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$organizer_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = 'bd_linked_business' 
				AND meta_value = %d
				AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tribe_organizer' AND post_status = 'publish')",
				$business_id
			)
		);

		return $organizer_id ? absint( $organizer_id ) : null;
	}

	/**
	 * Get business ID linked to an event (directly or via venue/organizer)
	 *
	 * @param int $event_id Event post ID.
	 * @return int|null Business post ID or null.
	 */
	public static function get_business_for_event( $event_id ) {
		// Check direct link first
		$business_id = get_post_meta( $event_id, 'bd_linked_business', true );

		if ( $business_id ) {
			return absint( $business_id );
		}

		// Check venue link
		$venue_id = get_post_meta( $event_id, '_EventVenueID', true );
		if ( $venue_id ) {
			$business_id = get_post_meta( $venue_id, 'bd_linked_business', true );
			if ( $business_id ) {
				return absint( $business_id );
			}
		}

		// Check organizer link
		if ( function_exists( 'tribe_get_organizer_ids' ) ) {
			$organizer_ids = tribe_get_organizer_ids( $event_id );
			if ( ! empty( $organizer_ids ) ) {
				foreach ( $organizer_ids as $organizer_id ) {
					$business_id = get_post_meta( $organizer_id, 'bd_linked_business', true );
					if ( $business_id ) {
						return absint( $business_id );
					}
				}
			}
		}

		return null;
	}

	/**
	 * Add business card to event content
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function add_business_card_to_event( $content ) {
		if ( ! is_singular( 'tribe_events' ) ) {
			return $content;
		}

		$event_id    = get_the_ID();
		$business_id = self::get_business_for_event( $event_id );

		if ( ! $business_id ) {
			return $content;
		}

		$business = get_post( $business_id );

		if ( ! $business || 'publish' !== $business->post_status ) {
			return $content;
		}

		// Build business card HTML
		$card = self::render_business_card( $business );

		// Append card after description, JS will position it before Add to Calendar
		return $content . $card;
	}

	/**
	 * Render a business card for display on event pages
	 *
	 * @param WP_Post $business Business post object.
	 * @return string HTML.
	 */
	public static function render_business_card( $business ) {
		$location    = get_post_meta( $business->ID, 'bd_location', true );
		$address     = is_array( $location ) ? ( $location['address'] ?? '' ) : '';
		$city        = is_array( $location ) ? ( $location['city'] ?? '' ) : '';
		$rating_data = self::get_business_rating( $business->ID );
		$categories  = get_the_terms( $business->ID, 'bd_category' );
		$thumbnail   = get_the_post_thumbnail_url( $business->ID, 'medium' );

		ob_start();
		?>
		<style>
			/* Hide TEC venue block when business card is shown */
			.tribe-block__venue,
			.tribe-events-venue,
			.tribe-events-meta-group-venue,
			.tribe-events-single-event-venue {
				display: none !important;
			}
		</style>
		<script>
			/* Move business card before Add to Calendar */
			document.addEventListener('DOMContentLoaded', function() {
				var card = document.querySelector('.bd-event-business-card');
				var selectors = '.tribe-block__events-link, .tribe-events-cal-links, ';
				selectors += '.tribe-block__event-links';
				var addToCal = document.querySelector(selectors);
				if (card && addToCal && addToCal.parentNode) {
					addToCal.parentNode.insertBefore(card, addToCal);
				}
			});
		</script>
		<div class="bd-event-business-card">
			<h4 class="bd-event-business-card-title">
				<?php esc_html_e( 'Hosted At', 'business-directory' ); ?>
			</h4>
			<div class="bd-event-business-card-inner">
				<?php if ( $thumbnail ) : ?>
					<div class="bd-event-business-card-image">
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $business->post_title ); ?>">
					</div>
				<?php endif; ?>

				<div class="bd-event-business-card-content">
					<h5 class="bd-event-business-card-name">
						<a href="<?php echo esc_url( get_permalink( $business->ID ) ); ?>">
							<?php echo esc_html( $business->post_title ); ?>
						</a>
					</h5>

					<?php if ( $rating_data['count'] > 0 ) : ?>
						<div class="bd-event-business-card-rating">
							<span class="bd-stars">★ <?php echo esc_html( number_format( $rating_data['average'], 1 ) ); ?></span>
							<span class="bd-review-count">(<?php echo esc_html( $rating_data['count'] ); ?>)</span>
						</div>
					<?php endif; ?>

					<?php if ( $address || $city ) : ?>
						<div class="bd-event-business-card-location">
							<?php echo esc_html( trim( $address . ', ' . $city, ', ' ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
						<div class="bd-event-business-card-categories">
							<?php
							$cat_names = wp_list_pluck( array_slice( $categories, 0, 2 ), 'name' );
							echo esc_html( implode( ' · ', $cat_names ) );
							?>
						</div>
					<?php endif; ?>

					<a href="<?php echo esc_url( get_permalink( $business->ID ) ); ?>" class="bd-event-business-card-link">
						<?php esc_html_e( 'View Business →', 'business-directory' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get business rating data
	 *
	 * @param int $business_id Business post ID.
	 * @return array Array with 'average' and 'count'.
	 */
	private static function get_business_rating( $business_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return array(
				'average' => 0,
				'count'   => 0,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as average, COUNT(*) as count 
				FROM {$wpdb->prefix}bd_reviews 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			)
		);

		return array(
			'average' => $result ? floatval( $result->average ) : 0,
			'count'   => $result ? intval( $result->count ) : 0,
		);
	}

	/**
	 * Get link to all events for a business
	 *
	 * @param int $business_id Business post ID.
	 * @return string URL.
	 */
	private static function get_business_events_link( $business_id ) {
		$venue_id = self::get_venue_for_business( $business_id );

		if ( $venue_id ) {
			return get_permalink( $venue_id );
		}

		// Fallback to events page
		return function_exists( 'tribe_get_events_link' ) ? tribe_get_events_link() : home_url( '/events/' );
	}

	/**
	 * Register REST API routes for city sites
	 */
	public static function register_rest_routes() {
		// Events by city
		register_rest_route(
			'bd/v1',
			'/events/city/(?P<city>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get_city_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'city'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Events by business ID
		register_rest_route(
			'bd/v1',
			'/events/business/(?P<id>[0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get_business_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'limit' => array(
						'default'           => 5,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST endpoint to get events by city
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_get_city_events( $request ) {
		$city  = $request->get_param( 'city' );
		$limit = min( $request->get_param( 'limit' ), 50 );

		// Get venues in this city
		global $wpdb;

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
			return rest_ensure_response( array() );
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

		$response = array();

		foreach ( $events as $event ) {
			$venue_id    = get_post_meta( $event->ID, '_EventVenueID', true );
			$business_id = self::get_business_for_event( $event->ID );

			$response[] = array(
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
				'excerpt'     => wp_trim_words( $event->post_content, 30 ),
				'business_id' => $business_id,
				'business'    => $business_id ? array(
					'id'   => $business_id,
					'name' => get_the_title( $business_id ),
					'url'  => get_permalink( $business_id ),
				) : null,
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * REST endpoint to get events by business ID
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_get_business_events( $request ) {
		$business_id = $request->get_param( 'id' );
		$limit       = min( $request->get_param( 'limit' ), 50 );

		// Verify business exists
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return rest_ensure_response(
				array(
					'error'   => 'invalid_business',
					'message' => 'Business not found',
				)
			);
		}

		$events = self::get_business_events( $business_id, $limit );

		$response = array(
			'business' => array(
				'id'   => $business_id,
				'name' => $business->post_title,
				'url'  => get_permalink( $business_id ),
			),
			'events'   => array(),
		);

		foreach ( $events as $event ) {
			$venue_id = get_post_meta( $event->ID, '_EventVenueID', true );

			$response['events'][] = array(
				'id'         => $event->ID,
				'title'      => $event->post_title,
				'url'        => get_permalink( $event->ID ),
				'start_date' => tribe_get_start_date( $event->ID, false, 'Y-m-d' ),
				'start_time' => tribe_get_start_date( $event->ID, false, 'H:i' ),
				'end_date'   => tribe_get_end_date( $event->ID, false, 'Y-m-d' ),
				'end_time'   => tribe_get_end_date( $event->ID, false, 'H:i' ),
				'venue'      => $venue_id ? get_the_title( $venue_id ) : '',
				'thumbnail'  => get_the_post_thumbnail_url( $event->ID, 'medium' ),
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Render an admin-only error message
	 *
	 * @param string $message Error message.
	 * @return string HTML.
	 */
	private static function render_admin_error( $message ) {
		$style  = 'padding: 15px; background: #fff3cd; border: 1px solid #ffc107; ';
		$style .= 'border-radius: 6px; color: #856404;';
		return '<p style="' . esc_attr( $style ) . '">' . esc_html( $message ) . '</p>';
	}
}
