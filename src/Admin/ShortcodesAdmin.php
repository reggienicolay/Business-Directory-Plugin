<?php
/**
 * Shortcodes Admin - Reference Page
 *
 * Displays all available shortcodes with their configurations.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */


namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Class ShortcodesAdmin
 * Provides admin reference page for all shortcodes
 */
class ShortcodesAdmin {

	/**
	 * Initialize the admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Shortcodes', 'business-directory' ),
			__( 'Shortcodes', 'business-directory' ),
			'manage_options',
			'bd-shortcodes',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-shortcodes' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bd-shortcodes-admin',
			plugins_url( 'assets/css/admin-shortcodes.css', dirname( __DIR__ ) ),
			array(),
			'1.2.0'
		);

		wp_enqueue_script(
			'bd-shortcodes-admin',
			plugins_url( 'assets/js/admin-shortcodes.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.2.0',
			true
		);
	}

	/**
	 * Get all shortcodes with their configurations
	 *
	 * @return array Shortcodes data.
	 */
	public static function get_shortcodes() {
		return array(
			// =============================================
			// CORE DIRECTORY
			// =============================================
			'directory'           => array(
				'name'        => 'Business Directory',
				'shortcode'   => 'business_directory',
				'description' => 'Displays the main business directory with map, filters, and listings.',
				'attributes'  => array(
					array(
						'name'        => 'city',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Filter by city name',
					),
					array(
						'name'        => 'category',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Filter by category slug',
					),
					array(
						'name'        => 'per_page',
						'type'        => 'integer',
						'default'     => '12',
						'description' => 'Number of businesses per page',
					),
					array(
						'name'        => 'show_map',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show/hide the map',
					),
					array(
						'name'        => 'show_filters',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show/hide filter sidebar',
					),
				),
				'examples'    => array(
					'[business_directory]',
					'[business_directory city="Livermore" per_page="24"]',
					'[business_directory category="restaurants" show_map="no"]',
				),
			),
			'search_box'          => array(
				'name'        => 'Search Box',
				'shortcode'   => 'bd_search',
				'description' => 'Displays a standalone business search box.',
				'attributes'  => array(
					array(
						'name'        => 'placeholder',
						'type'        => 'string',
						'default'     => 'Search businesses...',
						'description' => 'Placeholder text',
					),
					array(
						'name'        => 'redirect',
						'type'        => 'string',
						'default'     => '/local/',
						'description' => 'Search results page URL',
					),
				),
				'examples'    => array(
					'[bd_search]',
					'[bd_search placeholder="Find a local business..."]',
				),
			),
			'category_list'       => array(
				'name'        => 'Category List',
				'shortcode'   => 'bd_categories',
				'description' => 'Displays a list or grid of business categories.',
				'attributes'  => array(
					array(
						'name'        => 'layout',
						'type'        => 'string',
						'default'     => 'grid',
						'description' => 'Layout: grid, list, icons',
					),
					array(
						'name'        => 'show_count',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show business count per category',
					),
					array(
						'name'        => 'hide_empty',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Hide categories with no businesses',
					),
				),
				'examples'    => array(
					'[bd_categories]',
					'[bd_categories layout="list" show_count="no"]',
				),
			),
			'featured_businesses' => array(
				'name'        => 'Featured Businesses',
				'shortcode'   => 'bd_featured',
				'description' => 'Displays a grid of featured/promoted businesses.',
				'attributes'  => array(
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '4',
						'description' => 'Number of businesses to show',
					),
					array(
						'name'        => 'category',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Filter by category slug',
					),
					array(
						'name'        => 'columns',
						'type'        => 'integer',
						'default'     => '4',
						'description' => 'Number of columns',
					),
				),
				'examples'    => array(
					'[bd_featured]',
					'[bd_featured limit="6" columns="3"]',
					'[bd_featured category="restaurants"]',
				),
			),

			// =============================================
			// USER LISTS
			// =============================================
			'my_lists'            => array(
				'name'        => 'My Lists',
				'shortcode'   => 'bd_my_lists',
				'description' => 'Displays the current user\'s saved business lists with management options.',
				'attributes'  => array(
					array(
						'name'        => 'per_page',
						'type'        => 'integer',
						'default'     => '12',
						'description' => 'Number of lists per page',
					),
					array(
						'name'        => 'show_create',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show create new list button',
					),
				),
				'examples'    => array(
					'[bd_my_lists]',
					'[bd_my_lists per_page="6"]',
					'[bd_my_lists show_create="no"]',
				),
			),
			'public_lists'        => array(
				'name'        => 'Community Lists',
				'shortcode'   => 'bd_public_lists',
				'description' => 'Displays public lists from all users. Great for a "Community Lists" or "Discover" page.',
				'attributes'  => array(
					array(
						'name'        => 'per_page',
						'type'        => 'integer',
						'default'     => '12',
						'description' => 'Number of lists per page',
					),
					array(
						'name'        => 'show_featured',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show featured lists section at top',
					),
					array(
						'name'        => 'orderby',
						'type'        => 'string',
						'default'     => 'updated_at',
						'description' => 'Order by: updated_at, created_at, view_count, title',
					),
				),
				'examples'    => array(
					'[bd_public_lists]',
					'[bd_public_lists per_page="9" orderby="view_count"]',
					'[bd_public_lists show_featured="no"]',
				),
			),
			'single_list'         => array(
				'name'        => 'Single List View',
				'shortcode'   => 'bd_list',
				'description' => 'Displays a single list with all its businesses. Used for the list detail page.',
				'attributes'  => array(
					array(
						'name'        => 'id',
						'type'        => 'integer',
						'default'     => '',
						'description' => 'List ID (or uses ?list= URL param)',
					),
					array(
						'name'        => 'slug',
						'type'        => 'string',
						'default'     => '',
						'description' => 'List slug (alternative to ID)',
					),
				),
				'examples'    => array(
					'[bd_list]',
					'[bd_list id="5"]',
					'[bd_list slug="best-coffee-shops"]',
				),
			),
			'save_button'         => array(
				'name'        => 'Save to List Button',
				'shortcode'   => 'bd_save_button',
				'description' => 'Displays a "Save" button that opens the list selector modal. Use on business pages.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID to save',
					),
					array(
						'name'        => 'style',
						'type'        => 'string',
						'default'     => 'button',
						'description' => 'Style: button, icon, text',
					),
				),
				'examples'    => array(
					'[bd_save_button]',
					'[bd_save_button style="icon"]',
					'[bd_save_button business_id="123" style="button"]',
				),
			),

			// =============================================
			// EVENTS CALENDAR
			// =============================================
			'city_events'         => array(
				'name'        => 'City Events',
				'shortcode'   => 'bd_city_events',
				'description' => 'Displays upcoming events filtered by city. Use the source attribute on network sub-sites to fetch events from the main site via REST API.',
				'attributes'  => array(
					array(
						'name'        => 'city',
						'type'        => 'string',
						'default'     => '',
						'description' => 'City name to filter events (required)',
					),
					array(
						'name'        => 'source',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Source site domain for remote fetch (e.g., lovetrivalley.com)',
					),
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '10',
						'description' => 'Number of events to display',
					),
					array(
						'name'        => 'layout',
						'type'        => 'string',
						'default'     => 'grid',
						'description' => 'Layout: grid, list, compact',
					),
					array(
						'name'        => 'columns',
						'type'        => 'integer',
						'default'     => '3',
						'description' => 'Number of columns (grid layout)',
					),
					array(
						'name'        => 'show_business',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show linked business name',
					),
					array(
						'name'        => 'show_image',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show event thumbnail',
					),
					array(
						'name'        => 'show_venue',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show venue name',
					),
					array(
						'name'        => 'show_time',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show event time',
					),
					array(
						'name'        => 'title',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Optional section title',
					),
					array(
						'name'        => 'view_all_url',
						'type'        => 'string',
						'default'     => '',
						'description' => 'URL for "View All" link',
					),
					array(
						'name'        => 'cache',
						'type'        => 'integer',
						'default'     => '15',
						'description' => 'Cache duration in minutes (0 to disable)',
					),
				),
				'examples'    => array(
					'[bd_city_events city="Livermore"]',
					'[bd_city_events city="Livermore" source="lovetrivalley.com"]',
					'[bd_city_events city="Pleasanton" source="lovetrivalley.com" layout="list"]',
					'[bd_city_events city="Dublin" layout="compact" columns="2"]',
					'[bd_city_events city="Livermore" title="What\'s Happening" view_all_url="/events/"]',
				),
			),
			'business_events'     => array(
				'name'        => 'Business Events',
				'shortcode'   => 'bd_business_events',
				'description' => 'Displays upcoming events for a specific business. Use the source attribute on network sub-sites to fetch events from the main site via REST API.',
				'attributes'  => array(
					array(
						'name'        => 'id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'source',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Source site domain for remote fetch (e.g., lovetrivalley.com)',
					),
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '5',
						'description' => 'Number of events to show',
					),
				),
				'examples'    => array(
					'[bd_business_events]',
					'[bd_business_events id="2336" source="lovetrivalley.com"]',
					'[bd_business_events id="2336" source="lovetrivalley.com" limit="3"]',
				),
			),

			// =============================================
			// GAMIFICATION
			// =============================================
			'user_profile'        => array(
				'name'        => 'User Profile',
				'shortcode'   => 'bd_user_profile',
				'description' => 'Displays a user profile with stats, badges, reviews, and activity.',
				'attributes'  => array(
					array(
						'name'        => 'user_id',
						'type'        => 'integer',
						'default'     => '0 (current user)',
						'description' => 'Specific user ID to display',
					),
					array(
						'name'        => 'show_reviews',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show/hide reviews section',
					),
					array(
						'name'        => 'show_activity',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show/hide activity feed',
					),
					array(
						'name'        => 'reviews_limit',
						'type'        => 'integer',
						'default'     => '5',
						'description' => 'Number of reviews to display',
					),
				),
				'examples'    => array(
					'[bd_user_profile]',
					'[bd_user_profile user_id="5"]',
					'[bd_user_profile show_reviews="no" show_activity="no"]',
				),
			),
			'badge_gallery'       => array(
				'name'        => 'Badge Gallery',
				'shortcode'   => 'bd_badge_gallery',
				'description' => 'Displays the complete badge collection with user progress.',
				'attributes'  => array(
					array(
						'name'        => 'show_ranks',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show/hide rank progression section',
					),
				),
				'examples'    => array(
					'[bd_badge_gallery]',
					'[bd_badge_gallery show_ranks="no"]',
				),
			),
			'leaderboard'         => array(
				'name'        => 'Leaderboard',
				'shortcode'   => 'bd_leaderboard',
				'description' => 'Displays top contributors leaderboard.',
				'attributes'  => array(
					array(
						'name'        => 'period',
						'type'        => 'string',
						'default'     => 'all_time',
						'description' => 'Time period: all_time, month, week',
					),
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '10',
						'description' => 'Number of users to display',
					),
				),
				'examples'    => array(
					'[bd_leaderboard]',
					'[bd_leaderboard period="month" limit="5"]',
					'[bd_leaderboard period="week"]',
				),
			),

			// =============================================
			// BUSINESS FEATURES
			// =============================================
			'business_embed'      => array(
				'name'        => 'Business Embed',
				'shortcode'   => 'bd_business_embed',
				'description' => 'Embed a business card from the directory on any page/site.',
				'attributes'  => array(
					array(
						'name'        => 'id',
						'type'        => 'integer',
						'default'     => '',
						'description' => 'Business post ID (required)',
					),
					array(
						'name'        => 'layout',
						'type'        => 'string',
						'default'     => 'card',
						'description' => 'Layout style: card, compact, full, minimal',
					),
					array(
						'name'        => 'show_map',
						'type'        => 'yes/no',
						'default'     => 'no',
						'description' => 'Show mini map',
					),
				),
				'examples'    => array(
					'[bd_business_embed id="123"]',
					'[bd_business_embed id="123" layout="compact"]',
					'[bd_business_embed id="123" layout="full" show_map="yes"]',
				),
			),
			'business_hours'      => array(
				'name'        => 'Business Hours',
				'shortcode'   => 'bd_business_hours',
				'description' => 'Displays business hours with open/closed status.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'show_status',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show open/closed status',
					),
					array(
						'name'        => 'highlight_today',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Highlight current day',
					),
				),
				'examples'    => array(
					'[bd_business_hours]',
					'[bd_business_hours business_id="123"]',
					'[bd_business_hours show_status="no"]',
				),
			),
			'social_share'        => array(
				'name'        => 'Social Share Buttons',
				'shortcode'   => 'bd_social_share',
				'description' => 'Displays social sharing buttons for the current business.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'networks',
						'type'        => 'string',
						'default'     => 'facebook,twitter,linkedin,email',
						'description' => 'Comma-separated list of networks',
					),
					array(
						'name'        => 'style',
						'type'        => 'string',
						'default'     => 'buttons',
						'description' => 'Style: buttons, icons, minimal',
					),
				),
				'examples'    => array(
					'[bd_social_share]',
					'[bd_social_share networks="facebook,twitter"]',
					'[bd_social_share style="icons"]',
				),
			),
			'qr_code'             => array(
				'name'        => 'QR Code',
				'shortcode'   => 'bd_qr_code',
				'description' => 'Displays a QR code linking to the business page.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'size',
						'type'        => 'integer',
						'default'     => '150',
						'description' => 'QR code size in pixels',
					),
				),
				'examples'    => array(
					'[bd_qr_code]',
					'[bd_qr_code business_id="123" size="200"]',
				),
			),

			// =============================================
			// REVIEWS & RATINGS
			// =============================================
			'business_reviews'    => array(
				'name'        => 'Business Reviews',
				'shortcode'   => 'bd_business_reviews',
				'description' => 'Displays reviews for a specific business.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '10',
						'description' => 'Number of reviews to show',
					),
					array(
						'name'        => 'show_form',
						'type'        => 'yes/no',
						'default'     => 'yes',
						'description' => 'Show review submission form',
					),
				),
				'examples'    => array(
					'[bd_business_reviews]',
					'[bd_business_reviews business_id="123"]',
					'[bd_business_reviews limit="5" show_form="no"]',
				),
			),
			'review_form'         => array(
				'name'        => 'Review Form',
				'shortcode'   => 'bd_review_form',
				'description' => 'Displays a standalone review submission form.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
					array(
						'name'        => 'redirect',
						'type'        => 'string',
						'default'     => '',
						'description' => 'URL to redirect after submission',
					),
				),
				'examples'    => array(
					'[bd_review_form]',
					'[bd_review_form business_id="123"]',
				),
			),
			'recent_reviews'      => array(
				'name'        => 'Recent Reviews',
				'shortcode'   => 'bd_recent_reviews',
				'description' => 'Displays recent reviews across all businesses.',
				'attributes'  => array(
					array(
						'name'        => 'limit',
						'type'        => 'integer',
						'default'     => '5',
						'description' => 'Number of reviews to show',
					),
					array(
						'name'        => 'category',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Filter by business category slug',
					),
				),
				'examples'    => array(
					'[bd_recent_reviews]',
					'[bd_recent_reviews limit="10"]',
					'[bd_recent_reviews category="restaurants"]',
				),
			),

			// =============================================
			// FORMS
			// =============================================
			'submit_business'     => array(
				'name'        => 'Submit Business Form',
				'shortcode'   => 'bd_submit_business',
				'description' => 'Displays a form for users to submit new business listings.',
				'attributes'  => array(
					array(
						'name'        => 'category',
						'type'        => 'string',
						'default'     => '',
						'description' => 'Pre-select category slug',
					),
					array(
						'name'        => 'redirect',
						'type'        => 'string',
						'default'     => '',
						'description' => 'URL to redirect after submission',
					),
				),
				'examples'    => array(
					'[bd_submit_business]',
					'[bd_submit_business category="restaurants"]',
				),
			),
			'claim_business'      => array(
				'name'        => 'Claim Business Form',
				'shortcode'   => 'bd_claim_business',
				'description' => 'Displays a form for business owners to claim their listing.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (current)',
						'description' => 'Business post ID',
					),
				),
				'examples'    => array(
					'[bd_claim_business]',
					'[bd_claim_business business_id="123"]',
				),
			),
			'business_tools'      => array(
				'name'        => 'Business Tools',
				'shortcode'   => 'bd_business_tools',
				'description' => 'Dashboard for claimed business owners to manage their listing, view analytics, and access tools.',
				'attributes'  => array(),
				'examples'    => array(
					'[bd_business_tools]',
				),
			),
			'edit_listing'        => array(
				'name'        => 'Edit Listing',
				'shortcode'   => 'bd_edit_listing',
				'description' => 'Allows claimed business owners to edit their listing information.',
				'attributes'  => array(
					array(
						'name'        => 'business_id',
						'type'        => 'integer',
						'default'     => '0 (from URL)',
						'description' => 'Business post ID (uses ?business_id= param)',
					),
				),
				'examples'    => array(
					'[bd_edit_listing]',
				),
			),
		);
	}

	/**
	 * Render the admin page
	 */
	public static function render_page() {
		$shortcodes = self::get_shortcodes();
		$categories = array(
			'core'         => array(
				'name'  => 'Core Directory',
				'icon'  => 'dashicons-grid-view',
				'desc'  => 'Main directory display and navigation shortcodes.',
				'codes' => array( 'directory', 'search_box', 'category_list', 'featured_businesses' ),
			),
			'lists'        => array(
				'name'  => 'User Lists',
				'icon'  => 'dashicons-list-view',
				'desc'  => 'Pinterest-style user-curated business lists.',
				'codes' => array( 'my_lists', 'public_lists', 'single_list', 'save_button' ),
			),
			'events'       => array(
				'name'  => 'Events Calendar',
				'icon'  => 'dashicons-calendar-alt',
				'desc'  => 'Display events from The Events Calendar, filtered by city or linked to businesses.',
				'codes' => array( 'city_events', 'business_events' ),
			),
			'gamification' => array(
				'name'  => 'Gamification',
				'icon'  => 'dashicons-awards',
				'desc'  => 'User engagement features: badges, points, and leaderboards.',
				'codes' => array( 'user_profile', 'badge_gallery', 'leaderboard' ),
			),
			'business'     => array(
				'name'  => 'Business Features',
				'icon'  => 'dashicons-store',
				'desc'  => 'Individual business display and information shortcodes.',
				'codes' => array( 'business_embed', 'business_hours', 'social_share', 'qr_code' ),
			),
			'reviews'      => array(
				'name'  => 'Reviews & Ratings',
				'icon'  => 'dashicons-star-filled',
				'desc'  => 'Display and collect business reviews.',
				'codes' => array( 'business_reviews', 'recent_reviews', 'review_form' ),
			),
			'forms'        => array(
				'name'  => 'Forms & Management',
				'icon'  => 'dashicons-feedback',
				'desc'  => 'User submission, claim forms, and business owner tools.',
				'codes' => array( 'submit_business', 'claim_business', 'business_tools', 'edit_listing' ),
			),
		);
		?>
		<div class="wrap bd-shortcodes-admin">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-shortcode"></span>
				<?php esc_html_e( 'Shortcodes Reference', 'business-directory' ); ?>
			</h1>
			<p class="bd-page-description">
				<?php esc_html_e( 'Copy and paste these shortcodes into any page or post to display directory features.', 'business-directory' ); ?>
			</p>
			<hr class="wp-header-end">

			<!-- Quick Navigation -->
			<div class="bd-shortcode-nav">
				<?php foreach ( $categories as $cat_key => $category ) : ?>
					<a href="#<?php echo esc_attr( $cat_key ); ?>" class="bd-nav-item">
						<span class="dashicons <?php echo esc_attr( $category['icon'] ); ?>"></span>
						<?php echo esc_html( $category['name'] ); ?>
						<span class="bd-nav-count"><?php echo count( $category['codes'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<!-- Page Setup Guide -->
			<div class="bd-setup-guide">
				<h2><span class="dashicons dashicons-welcome-learn-more"></span> Recommended Page Setup</h2>
				<p>Create these pages for a complete directory experience:</p>
				<table class="bd-setup-table">
					<thead>
						<tr>
							<th>Page Name</th>
							<th>Shortcode</th>
							<th>Purpose</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Business Directory</strong></td>
							<td><code>[business_directory]</code></td>
							<td>Main directory with map and filters</td>
						</tr>
						<tr>
							<td><strong>My Lists</strong></td>
							<td><code>[bd_my_lists]</code></td>
							<td>User's saved business lists (requires login)</td>
						</tr>
						<tr>
							<td><strong>Community Lists</strong></td>
							<td><code>[bd_public_lists]</code></td>
							<td>Browse all public lists from users</td>
						</tr>
						<tr>
							<td><strong>View List</strong></td>
							<td><code>[bd_list]</code></td>
							<td>Single list detail page (auto-detects from URL)</td>
						</tr>
						<tr>
							<td><strong>City Events</strong></td>
							<td><code>[bd_city_events city="Livermore" source="lovetrivalley.com"]</code></td>
							<td>Events in a city (use source on sub-sites)</td>
						</tr>
						<tr>
							<td><strong>My Profile</strong></td>
							<td><code>[bd_user_profile]</code></td>
							<td>User stats, badges, and activity</td>
						</tr>
						<tr>
							<td><strong>Badges</strong></td>
							<td><code>[bd_badge_gallery]</code></td>
							<td>All available badges and user progress</td>
						</tr>
						<tr>
							<td><strong>Leaderboard</strong></td>
							<td><code>[bd_leaderboard]</code></td>
							<td>Top community contributors</td>
						</tr>
						<tr>
							<td><strong>Add a Business</strong></td>
							<td><code>[bd_submit_business]</code></td>
							<td>Public business submission form</td>
						</tr>
						<tr>
							<td><strong>Business Tools</strong></td>
							<td><code>[bd_business_tools]</code></td>
							<td>Dashboard for claimed business owners</td>
						</tr>
						<tr>
							<td><strong>Edit Listing</strong></td>
							<td><code>[bd_edit_listing]</code></td>
							<td>Form for owners to edit their listing</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Shortcode Sections -->
			<?php foreach ( $categories as $cat_key => $category ) : ?>
				<div id="<?php echo esc_attr( $cat_key ); ?>" class="bd-shortcode-section">
					<h2>
						<span class="dashicons <?php echo esc_attr( $category['icon'] ); ?>"></span>
						<?php echo esc_html( $category['name'] ); ?>
					</h2>
					<?php if ( ! empty( $category['desc'] ) ) : ?>
						<p class="bd-section-desc"><?php echo esc_html( $category['desc'] ); ?></p>
					<?php endif; ?>

					<div class="bd-shortcode-grid">
						<?php foreach ( $category['codes'] as $code_key ) : ?>
							<?php
							$shortcode = $shortcodes[ $code_key ] ?? null;
							if ( ! $shortcode ) {
								continue;
							}
							?>
							<div class="bd-shortcode-card">
								<div class="bd-shortcode-header">
									<h3><?php echo esc_html( $shortcode['name'] ); ?></h3>
									<code class="bd-shortcode-tag" data-copy="[<?php echo esc_attr( $shortcode['shortcode'] ); ?>]">
										[<?php echo esc_html( $shortcode['shortcode'] ); ?>]
									</code>
								</div>

								<p class="bd-shortcode-desc">
									<?php echo esc_html( $shortcode['description'] ); ?>
								</p>

								<?php if ( ! empty( $shortcode['attributes'] ) ) : ?>
									<div class="bd-shortcode-attributes">
										<h4><?php esc_html_e( 'Attributes', 'business-directory' ); ?></h4>
										<table class="bd-attributes-table">
											<thead>
												<tr>
													<th><?php esc_html_e( 'Name', 'business-directory' ); ?></th>
													<th><?php esc_html_e( 'Type', 'business-directory' ); ?></th>
													<th><?php esc_html_e( 'Default', 'business-directory' ); ?></th>
													<th><?php esc_html_e( 'Description', 'business-directory' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $shortcode['attributes'] as $attr ) : ?>
													<tr>
														<td><code><?php echo esc_html( $attr['name'] ); ?></code></td>
														<td><span class="bd-attr-type"><?php echo esc_html( $attr['type'] ); ?></span></td>
														<td><code><?php echo esc_html( $attr['default'] ); ?></code></td>
														<td><?php echo esc_html( $attr['description'] ); ?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $shortcode['examples'] ) ) : ?>
									<div class="bd-shortcode-examples">
										<h4><?php esc_html_e( 'Examples', 'business-directory' ); ?></h4>
										<?php foreach ( $shortcode['examples'] as $example ) : ?>
											<div class="bd-example-row">
												<code class="bd-example-code" data-copy="<?php echo esc_attr( $example ); ?>">
													<?php echo esc_html( $example ); ?>
												</code>
												<button type="button" class="bd-copy-btn" title="Copy to clipboard">
													<span class="dashicons dashicons-clipboard"></span>
												</button>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<!-- Tips Section -->
			<div class="bd-tips-section">
				<h2><span class="dashicons dashicons-lightbulb"></span> Tips</h2>
				<div class="bd-tips-grid">
					<div class="bd-tip-card">
						<h4>Using in Pages</h4>
						<p>Simply paste the shortcode into your page content using the WordPress editor. Works with both Classic and Block editors.</p>
					</div>
					<div class="bd-tip-card">
						<h4>Using in Widgets</h4>
						<p>Add a "Shortcode" or "Custom HTML" widget and paste your shortcode. Great for sidebars and footers.</p>
					</div>
					<div class="bd-tip-card">
						<h4>Using in Templates</h4>
						<p>Use <code>do_shortcode('[shortcode]')</code> in your theme templates to output shortcodes programmatically.</p>
					</div>
					<div class="bd-tip-card">
						<h4>Combining Attributes</h4>
						<p>You can combine multiple attributes in a single shortcode. Order doesn't matter.</p>
					</div>
					<div class="bd-tip-card">
						<h4>City Events on Network Sites</h4>
						<p>On sub-sites like LoveLivermore, use the <code>source</code> attribute to fetch events from the main site: <code>[bd_city_events city="Livermore" source="lovetrivalley.com"]</code></p>
					</div>
					<div class="bd-tip-card">
						<h4>Dynamic List Pages</h4>
						<p>The <code>[bd_list]</code> shortcode automatically reads the <code>?list=</code> URL parameter to display the correct list.</p>
					</div>
				</div>
			</div>

		</div>
		<?php
	}
}
