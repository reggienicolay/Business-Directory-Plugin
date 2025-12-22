<?php
/**
 * Events Calendar Integration
 *
 * Main bootstrap for The Events Calendar Pro integration.
 * Links businesses to events, venues, and organizers.
 *
 * @package BusinessDirectory
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

		// Initialize components
		BusinessLinker::init();

		// Display events on business pages
		if ( IntegrationsManager::get_setting( 'events_calendar', 'show_events_on_business', true ) ) {
			add_action( 'bd_after_business_content', array( __CLASS__, 'display_business_events' ) );
			add_shortcode( 'bd_business_events', array( __CLASS__, 'business_events_shortcode' ) );
		}

		// Display business card on event pages
		if ( IntegrationsManager::get_setting( 'events_calendar', 'show_business_on_events', true ) ) {
			add_filter( 'the_content', array( __CLASS__, 'add_business_card_to_event' ), 20 );
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

		wp_enqueue_style(
			'bd-events-integration',
			BD_PLUGIN_URL . 'assets/css/integrations/events-calendar.css',
			array(),
			BD_VERSION
		);
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
				'id'    => 0,
				'limit' => 5,
			),
			$atts
		);

		$business_id = absint( $atts['id'] );

		if ( ! $business_id ) {
			$business_id = get_the_ID();
		}

		ob_start();
		self::display_business_events( $business_id );
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

		$venue_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = 'bd_linked_business' 
				AND meta_value = %d
				AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tribe_venue')",
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

		$organizer_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = 'bd_linked_business' 
				AND meta_value = %d
				AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tribe_organizer')",
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
		$organizer_ids = tribe_get_organizer_ids( $event_id );
		if ( ! empty( $organizer_ids ) ) {
			foreach ( $organizer_ids as $organizer_id ) {
				$business_id = get_post_meta( $organizer_id, 'bd_linked_business', true );
				if ( $business_id ) {
					return absint( $business_id );
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
		$categories  = get_the_terms( $business->ID, 'business_category' );
		$thumbnail   = get_the_post_thumbnail_url( $business->ID, 'medium' );

		ob_start();
		?>
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

		$table = $wpdb->base_prefix . 'bd_reviews';

		// Check if table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return array(
				'average' => 0,
				'count'   => 0,
			);
		}

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as average, COUNT(*) as count 
				FROM {$table} 
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

		// Fallback to events page with organizer filter
		return tribe_get_events_link();
	}

	/**
	 * Register REST API routes for city sites
	 */
	public static function register_rest_routes() {
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
}
