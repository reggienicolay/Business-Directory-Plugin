<?php
/**
 * Plugin Name: Business Directory Pro
 * Plugin URI: https://github.com/reggienicolay/Business-Directory-Plugin
 * Description: Modern, map-first local business directory with geolocation, reviews, and multi-city support.
 * Version: 0.1.6
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

// Define plugin constants.
define( 'BD_VERSION', '0.1.6' );
define( 'BD_PLUGIN_FILE', __FILE__ );
define( 'BD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoload.
if ( file_exists( BD_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once BD_PLUGIN_DIR . 'vendor/autoload.php';
}

// Manual autoload fallback for BD namespace.
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

// Explicitly load gamification files early (they have constants accessed statically).
require_once BD_PLUGIN_DIR . 'src/Gamification/BadgeSystem.php';
require_once BD_PLUGIN_DIR . 'src/Gamification/ActivityTracker.php';
require_once BD_PLUGIN_DIR . 'src/Plugin.php';

// Load Lists system files.
require_once BD_PLUGIN_DIR . 'src/Lists/ListManager.php';
require_once BD_PLUGIN_DIR . 'src/Lists/ListCollaborators.php';

// Load API endpoints.
require_once BD_PLUGIN_DIR . 'src/API/ListsEndpoint.php';
require_once BD_PLUGIN_DIR . 'src/API/CollaboratorsEndpoint.php';
require_once BD_PLUGIN_DIR . 'src/API/BadgeEndpoint.php';
require_once BD_PLUGIN_DIR . 'src/API/SubmissionEndpoint.php';

// Load Admin classes.
require_once BD_PLUGIN_DIR . 'src/Admin/ListsAdmin.php';
require_once BD_PLUGIN_DIR . 'src/Admin/FeaturedAdmin.php';
require_once BD_PLUGIN_DIR . 'src/Admin/DuplicatesAdmin.php';
require_once BD_PLUGIN_DIR . 'src/Admin/ExporterPage.php';

// Load DB classes for duplicate management.
require_once BD_PLUGIN_DIR . 'src/DB/DuplicateFinder.php';
require_once BD_PLUGIN_DIR . 'src/DB/DuplicateMerger.php';

// Load Exporter classes.
require_once BD_PLUGIN_DIR . 'src/Exporter/CSV.php';

// Load Frontend classes.
require_once BD_PLUGIN_DIR . 'src/Frontend/ListDisplay.php';
require_once BD_PLUGIN_DIR . 'src/Frontend/ViewTracker.php';

// Activation hook.
register_activation_hook(
	__FILE__,
	function () {
		DB\Installer::activate();
		\BD\Install\FrontendEditorInstaller::install();
	}
);

// Deactivation hook.
register_deactivation_hook( __FILE__, array( DB\Installer::class, 'deactivate' ) );

// Initialize database migration checks (must run before plugins_loaded).
\BD\DB\Installer::init();

// Initialize plugin on plugins_loaded.
add_action(
	'plugins_loaded',
	function () {
		try {
			Plugin::instance();

			// Initialize SSO for multisite.
			if ( is_multisite() ) {
				\BD\Auth\SSO\Loader::init();
			}

			// Frontend edit form.
			new \BD\Frontend\EditListing();

			// Admin change requests queue.
			new \BD\Admin\ChangeRequestsQueue();

			// Initialize Duplicates Admin.
			new \BD\Admin\DuplicatesAdmin();

			// Initialize Export Admin.
			new \BD\Admin\ExporterPage();

			// Initialize components (preserve original order - ListDisplay must come AFTER ListsAdmin).
			\BD\Frontend\BadgeDisplay::init();
			\BD\Admin\ReviewsAdmin::init();
			\BD\Admin\ShortcodesAdmin::init();
			\BD\Admin\ListsAdmin::init();
			\BD\Frontend\ListDisplay::init();
			\BD\Admin\MenuOrganizer::init();
			\BD\Admin\GuideProfileFields::init();

		} catch ( \Exception $e ) {
			// Log the error for debugging.
			error_log( 'BD Plugin Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_die( 'Business Directory Plugin Error: ' . esc_html( $e->getMessage() ) );
			}
		}
	}
);

// Load Directory Assets (search, filters, maps).
if ( file_exists( BD_PLUGIN_DIR . 'includes/directory-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/directory-loader.php';
}

// Load Geolocation & Performance.
if ( file_exists( BD_PLUGIN_DIR . 'includes/geolocation-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/geolocation-loader.php';
}

/**
 * Custom template for single business pages.
 *
 * @param string $single_template The template path.
 * @return string Modified template path.
 */
function bd_custom_business_template( $single_template ) {
	global $post;

	if ( isset( $post->post_type ) && $post->post_type === 'bd_business' ) {
		$plugin_template = BD_PLUGIN_DIR . 'templates/single-business-premium.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}

	return $single_template;
}
add_filter( 'single_template', __NAMESPACE__ . '\bd_custom_business_template' );

// Load Gamification System (has load-once guard, safe even with early loads above).
if ( file_exists( BD_PLUGIN_DIR . 'includes/gamification-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/gamification-loader.php';
}

// Load Feature Embed System.
if ( file_exists( BD_PLUGIN_DIR . 'includes/feature-embed-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/feature-embed-loader.php';
}

// Load Social Sharing System.
if ( file_exists( BD_PLUGIN_DIR . 'includes/social-sharing-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/social-sharing-loader.php';
}

// Load Cover Media System.
if ( file_exists( BD_PLUGIN_DIR . 'cover-media-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'cover-media-loader.php';
}


// Load Business Owner Tools.
if ( file_exists( BD_PLUGIN_DIR . 'includes/business-tools-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/business-tools-loader.php';
}

// Load Integrations System.
if ( file_exists( BD_PLUGIN_DIR . 'includes/integrations-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/integrations-loader.php';
}

// Load Auth System.
if ( file_exists( BD_PLUGIN_DIR . 'includes/auth-loader.php' ) ) {
	require_once BD_PLUGIN_DIR . 'includes/auth-loader.php';
}

// Load Guides System.
require_once BD_PLUGIN_DIR . 'includes/guides-loader.php';
