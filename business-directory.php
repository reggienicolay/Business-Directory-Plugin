<?php
/**
 * Plugin Name: Business Directory Pro
 * Plugin URI: https://github.com/reggienicolay/Business-Directory-Plugin
 * Description: Modern, map-first local business directory with geolocation, reviews, and multi-city support.
 * Version: 0.1.0
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
define( 'BD_VERSION', '0.1.0' );
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

// Activation/Deactivation hooks
register_activation_hook( __FILE__, array( DB\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( DB\Installer::class, 'deactivate' ) );

// Initialize plugin
add_action(
	'plugins_loaded',
	function () {
		Plugin::instance();
	}
);

// Load Sprint 2 Week 2 - Search Infrastructure
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-loader.php';
}

// Load Sprint 2 Week 2 Script 2 - Filter UI
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-script2-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-script2-loader.php';
}

// Load Sprint 2 Week 2 Script 3 - Geolocation & Performance
if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-script3-loader.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/sprint2-week2-script3-loader.php';
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
