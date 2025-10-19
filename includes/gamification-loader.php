<?php
/**
 * Gamification System Loader
 * Loads all gamification-related classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once
if ( defined( 'BD_GAMIFICATION_LOADED' ) ) {
	return;
}
define( 'BD_GAMIFICATION_LOADED', true );

// Load Gamification core classes
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/BadgeSystem.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/ActivityTracker.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/GamificationHooks.php';

// Load Frontend display classes
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/BadgeDisplay.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/ProfileShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/RegistrationShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/RegistrationHandler.php';

// Load Admin classes
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/BadgeAdmin.php';

// Initialize components
new BD\Gamification\GamificationHooks();
new BD\Admin\BadgeAdmin();
new BD\Frontend\ProfileShortcode();
new BD\Frontend\RegistrationShortcode();
new BD\Frontend\RegistrationHandler();

// ⭐ Enqueue Badge Styles ⭐
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only load on gamification admin pages
		if ( strpos( $hook, 'bd-gamification' ) !== false ||
		strpos( $hook, 'bd-user-badges' ) !== false ||
		strpos( $hook, 'bd-leaderboard' ) !== false ) {

			$plugin_url = plugin_dir_url( __DIR__ );

			// Font Awesome
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				array(),
				'5.15.4'
			);

			// Badges CSS
			wp_enqueue_style(
				'bd-badges',
				$plugin_url . 'assets/css/badges.css',
				array( 'font-awesome' ),
				'1.0.0'
			);
		}
	}
);

// ⭐ ADD THIS - Enqueue Badge Styles - FRONTEND ⭐
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		$plugin_url = plugin_dir_url( __DIR__ );

		// Check if page has profile shortcode or is a user profile page
		$has_profile = false;
		if ( is_a( $post, 'WP_Post' ) ) {
			$has_profile = has_shortcode( $post->post_content, 'user_profile' ) ||
						has_shortcode( $post->post_content, 'bd_profile' ) ||
						has_shortcode( $post->post_content, 'bd_user_profile' );
		}

		// Also load on author archives (user profile pages)
		if ( is_author() ) {
			$has_profile = true;
		}

		if ( $has_profile ) {
			// Font Awesome
			if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
				wp_enqueue_style(
					'font-awesome',
					'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
					array(),
					'5.15.4'
				);
			}

			// Badges CSS
			wp_enqueue_style(
				'bd-badges',
				$plugin_url . 'assets/css/badges.css',
				array( 'font-awesome' ),
				'1.0.1'
			);
		}
	},
	20
);
