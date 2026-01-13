<?php
/**
 * Gamification System Loader
 *
 * Loads all gamification-related classes and assets.
 *
 * @package BusinessDirectory
 * @subpackage Gamification
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading.
if ( defined( 'BD_GAMIFICATION_LOADED' ) ) {
	return;
}
define( 'BD_GAMIFICATION_LOADED', true );

// Load Gamification core classes (may already be loaded early, require_once handles this).
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/BadgeSystem.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/ActivityTracker.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Gamification/GamificationHooks.php';

// Load Frontend display classes.
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/BadgeDisplay.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/ProfileEditor.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/Profile.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/RegistrationShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/RegistrationHandler.php';

// Load Admin classes.
require_once plugin_dir_path( __FILE__ ) . '../src/Admin/BadgeAdmin.php';

// Initialize components.
new BD\Gamification\GamificationHooks();
new BD\Admin\BadgeAdmin();

// Initialize unified Profile class (handles [bd_profile], [bd_public_profile], and /profile/username/ routing).
BD\Frontend\Profile::init();

new BD\Frontend\RegistrationShortcode();
new BD\Frontend\RegistrationHandler();

/**
 * Enqueue Badge Styles - ADMIN
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only load on gamification admin pages.
		if ( strpos( $hook, 'bd-gamification' ) === false &&
			strpos( $hook, 'bd-user-badges' ) === false &&
			strpos( $hook, 'bd-leaderboard' ) === false &&
			strpos( $hook, 'bd-badge-catalog' ) === false ) {
			return;
		}

		bd_gamification_enqueue_badge_styles();
	}
);

/**
 * Enqueue Badge Styles - FRONTEND
 *
 * Consolidated hook for all frontend badge/profile style loading.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		global $post;

		$should_load = false;

		// Single business pages (for reviewer badges).
		if ( is_singular( 'bd_business' ) ) {
			$should_load = true;
		}

		// Author archives (user profile pages).
		if ( is_author() ) {
			$should_load = true;
		}

		// My-profile page.
		if ( is_page( 'my-profile' ) ) {
			$should_load = true;
		}

		// Check for /profile/ URL pattern.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $request_uri, '/profile/' ) !== false ) {
			$should_load = true;
		}

		// Check post content for profile-related shortcodes.
		if ( ! $should_load && is_a( $post, 'WP_Post' ) ) {
			// Use single regex for efficiency instead of multiple has_shortcode() calls.
			$shortcode_pattern = '/\[(user_profile|bd_profile|bd_public_profile|bd_user_profile|bd_badge_gallery)/';
			if ( preg_match( $shortcode_pattern, $post->post_content ) ) {
				$should_load = true;
			}
		}

		if ( $should_load ) {
			bd_gamification_enqueue_badge_styles();
		}
	},
	20
);

/**
 * Enqueue badge styles helper function.
 *
 * Ensures design tokens load first, then badge styles.
 * Checks if already enqueued to prevent duplicates.
 */
function bd_gamification_enqueue_badge_styles() {
	$plugin_url = plugin_dir_url( __DIR__ );

	// Design Tokens (fonts, colors, variables) - LOAD FIRST.
	if ( ! wp_style_is( 'bd-design-tokens', 'enqueued' ) ) {
		wp_enqueue_style(
			'bd-design-tokens',
			$plugin_url . 'assets/css/design-tokens.css',
			array(),
			BD_VERSION
		);
	}

	// Badges CSS.
	if ( ! wp_style_is( 'bd-badges', 'enqueued' ) ) {
		wp_enqueue_style(
			'bd-badges',
			$plugin_url . 'assets/css/badges.css',
			array( 'bd-design-tokens' ),
			BD_VERSION
		);
	}
}
