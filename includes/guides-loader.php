<?php
/**
 * Guides & Public Profiles Loader
 *
 * Loads all Guide-related classes and initializes the public profile system.
 *
 * @package BusinessDirectory
 * @subpackage Guides
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once.
if ( defined( 'BD_GUIDES_LOADED' ) ) {
	return;
}
define( 'BD_GUIDES_LOADED', true );

// Load classes.
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/GuidesAdmin.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/GuidesShortcode.php';

// NOTE: PublicProfile.php is NO LONGER loaded here.
// Profile functionality is now handled by Profile.php (loaded in gamification-loader.php).
// This includes both [bd_profile] and [bd_public_profile] shortcodes,
// as well as the /profile/username/ URL routing.

// Initialize components.
\BD\Admin\GuidesAdmin::init();
\BD\Frontend\GuidesShortcode::init();

// NOTE: PublicProfile::init() is NO LONGER called here.
// Profile::init() in gamification-loader.php handles all profile functionality.

/**
 * Flush rewrite rules on plugin activation
 *
 * This is needed for the /profile/username/ URLs to work.
 * Now handled by Profile.php but we keep this for safety.
 */
function bd_guides_flush_rewrite_rules() {
	// Profile::add_rewrite_rules() is called via Profile::init()
	flush_rewrite_rules();
}

// Flush rewrite rules on activation (hook this in main plugin file).
register_activation_hook( BD_PLUGIN_FILE, 'bd_guides_flush_rewrite_rules' );

/**
 * Add "View Profile" link to admin bar for logged-in users
 */
add_action(
	'admin_bar_menu',
	function ( $wp_admin_bar ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user        = wp_get_current_user();
		$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'bd-view-profile',
				'parent' => 'user-actions',
				'title'  => __( 'View Public Profile', 'business-directory' ),
				'href'   => $profile_url,
			)
		);
	},
	100
);

/**
 * Add profile link to My Account menu items
 */
add_filter(
	'wp_nav_menu_items',
	function ( $items, $args ) {
		// Only add to specific menus (adjust as needed).
		if ( ! is_user_logged_in() ) {
			return $items;
		}

		// Check if this is an account-related menu.
		if ( isset( $args->theme_location ) && in_array( $args->theme_location, array( 'account-menu', 'user-menu' ), true ) ) {
			$user        = wp_get_current_user();
			$profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

			$items .= '<li class="menu-item"><a href="' . esc_url( $profile_url ) . '">' . esc_html__( 'My Public Profile', 'business-directory' ) . '</a></li>';
		}

		return $items;
	},
	10,
	2
);

/**
 * Register shortcode documentation
 */
add_filter(
	'bd_shortcode_documentation',
	function ( $shortcodes ) {
		$shortcodes['guides'] = array(
			'name'        => 'Community Guides',
			'shortcode'   => 'bd_guides',
			'description' => 'Displays community guides in a grid layout.',
			'attributes'  => array(
				array(
					'name'        => 'limit',
					'type'        => 'integer',
					'default'     => '-1 (all)',
					'description' => 'Maximum number of guides to display',
				),
				array(
					'name'        => 'city',
					'type'        => 'string',
					'default'     => '',
					'description' => 'Filter by city (e.g., "Livermore")',
				),
				array(
					'name'        => 'columns',
					'type'        => 'integer',
					'default'     => '3',
					'description' => 'Number of columns (2, 3, or 4)',
				),
				array(
					'name'        => 'layout',
					'type'        => 'string',
					'default'     => 'cards',
					'description' => 'Layout style: cards, featured, or compact',
				),
			),
			'examples'    => array(
				'[bd_guides]',
				'[bd_guides limit="3" columns="3"]',
				'[bd_guides city="Livermore" layout="featured"]',
			),
		);

		$shortcodes['public_profile'] = array(
			'name'        => 'Public Profile',
			'shortcode'   => 'bd_public_profile',
			'description' => 'Displays a user\'s public profile.',
			'attributes'  => array(
				array(
					'name'        => 'user',
					'type'        => 'string',
					'default'     => '',
					'description' => 'Username or user slug',
				),
				array(
					'name'        => 'user_id',
					'type'        => 'integer',
					'default'     => '',
					'description' => 'User ID (alternative to username)',
				),
			),
			'examples'    => array(
				'[bd_public_profile user="nicole"]',
				'[bd_public_profile user_id="1"]',
			),
		);

		return $shortcodes;
	}
);
