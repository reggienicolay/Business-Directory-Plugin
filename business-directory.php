<?php

/**
 * Plugin Name: Business Directory Pro
 * Plugin URI: https://github.com/reggienicolay/Business-Directory-Plugin
 * Description: Modern, map-first local business directory with geolocation, reviews, and multi-city support.
 * Version: 0.1.4
 * Author: Reggie Nicolay
 * Author URI: https://narrpr.com
 * Text Domain: business-directory
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace BD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'BD_VERSION', '0.1.4' );
define( 'BD_PLUGIN_FILE', __FILE__ );
define( 'BD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoload
if ( file_exists( BD_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once BD_PLUGIN_DIR . 'vendor/autoload.php';
}

// Manual autoload fallback
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'BD\\';
		$base_dir = BD_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Explicitly load gamification files early (they have constants accessed statically)
require_once BD_PLUGIN_DIR . 'src/Gamification/BadgeSystem.php';
require_once BD_PLUGIN_DIR . 'src/Gamification/ActivityTracker.php';
require_once BD_PLUGIN_DIR . 'src/Plugin.php';

// Load Lists files
require_once plugin_dir_path( __FILE__ ) . 'src/Lists/ListManager.php';
require_once plugin_dir_path( __FILE__ ) . 'src/API/ListsEndpoint.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Admin/ListsAdmin.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Admin/FeaturedAdmin.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Frontend/ListDisplay.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Frontend/ViewTracker.php';
require_once plugin_dir_path( __FILE__ ) . 'src/Lists/ListCollaborators.php';
require_once plugin_dir_path( __FILE__ ) . 'src/API/CollaboratorsEndpoint.php';
require_once plugin_dir_path( __FILE__ ) . 'src/API/BadgeEndpoint.php';

// Activation/Deactivation hooks
register_activation_hook(
	__FILE__,
	function () {
		DB\Installer::activate();
		\BD\Install\FrontendEditorInstaller::install();
	}
);

// Deactivation hook (separate, not nested)
register_deactivation_hook( __FILE__, array( DB\Installer::class, 'deactivate' ) );

// Initialize database migration checks (must be before plugins_loaded fires)
\BD\DB\Installer::init();

// Initialize plugin
add_action(
	'plugins_loaded',
	function () {
		Plugin::instance();
		// Frontend edit form
		new \BD\Frontend\EditListing();

		// Admin change requests queue
		new \BD\Admin\ChangeRequestsQueue();
		\BD\Frontend\BadgeDisplay::init();
		\BD\Admin\ReviewsAdmin::init();
		\BD\Admin\ShortcodesAdmin::init();
		\BD\Admin\ListsAdmin::init();
		\BD\Frontend\ListDisplay::init();
		\BD\Admin\MenuOrganizer::init();
	}
);

// Load Directory Assets (search, filters, maps)
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/directory-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/directory-loader.php';
}

// Load Geolocation & Performance
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/geolocation-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/geolocation-loader.php';
}

/**
 * Custom template for single business pages
 */
add_filter( 'single_template', 'BD\bd_custom_business_template' );
function bd_custom_business_template( $single_template ) {
	global $post;

	if ( isset( $post->post_type ) && ( $post->post_type == 'bd_business' || $post->post_type == 'business' ) ) {
		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-business-premium.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}

	return $single_template;
}

// Load submission endpoint
require_once plugin_dir_path( __FILE__ ) . 'src/API/SubmissionEndpoint.php';

// Load Gamification System
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/gamification-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/gamification-loader.php';
}

// Load Feature Embed System
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/feature-embed-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/feature-embed-loader.php';
}

// Load Social Sharing System
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/social-sharing-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/social-sharing-loader.php';
}

// Load Business Owner Tools
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/business-tools-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/business-tools-loader.php';
}

// Load Integrations System
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/integrations-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/integrations-loader.php';
}

// Load Auth System
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/auth-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/auth-loader.php';
}
