<?php
/**
 * Business Tools Loader
 *
 * Loads all business owner marketing tools.
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once.
if ( defined( 'BD_BUSINESS_TOOLS_LOADED' ) ) {
	return;
}
define( 'BD_BUSINESS_TOOLS_LOADED', true );

// Define base path for this module.
$bd_tools_path = plugin_dir_path( __FILE__ ) . '../src/BusinessTools/';

// Check if files exist before loading.
$required_files = array(
	'ToolsDashboard.php',
	'WidgetGenerator.php',
	'WidgetEndpoint.php',
	'QRGenerator.php',
	'BadgeGenerator.php',
	'StatsEmail.php',
);

foreach ( $required_files as $file ) {
	$file_path = $bd_tools_path . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// Initialize components only if classes exist.
if ( class_exists( '\BD\BusinessTools\ToolsDashboard' ) ) {
	new \BD\BusinessTools\ToolsDashboard();
}
if ( class_exists( '\BD\BusinessTools\WidgetEndpoint' ) ) {
	new \BD\BusinessTools\WidgetEndpoint();
}
if ( class_exists( '\BD\BusinessTools\QRGenerator' ) ) {
	new \BD\BusinessTools\QRGenerator();
}
if ( class_exists( '\BD\BusinessTools\BadgeGenerator' ) ) {
	new \BD\BusinessTools\BadgeGenerator();
}
if ( class_exists( '\BD\BusinessTools\StatsEmail' ) ) {
	new \BD\BusinessTools\StatsEmail();
}

// Create database tables on activation - only if constant is defined.
if ( defined( 'BD_PLUGIN_FILE' ) ) {
	register_activation_hook( BD_PLUGIN_FILE, 'bd_business_tools_create_tables' );
}

/**
 * Check if this site should have local database tables.
 * Mirrors the logic in BD\DB\Installer::should_create_tables().
 *
 * @return bool
 */
function bd_business_tools_should_create_tables() {
	// Single site always gets tables.
	if ( ! is_multisite() ) {
		return true;
	}

	// Use the setting from FeatureSettings if available.
	if ( class_exists( '\BD\Admin\FeatureSettings' ) ) {
		return \BD\Admin\FeatureSettings::is_local_features_enabled();
	}

	// Fallback: main site gets tables, subsites don't.
	return is_main_site();
}

/**
 * Create business tools database tables.
 */
function bd_business_tools_create_tables() {
	// Skip table creation on subsites that don't need local features.
	if ( ! bd_business_tools_should_create_tables() ) {
		return;
	}

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Widget domains table.
	$domains_table = $wpdb->prefix . 'bd_widget_domains';
	$sql_domains   = "CREATE TABLE IF NOT EXISTS {$domains_table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		business_id BIGINT UNSIGNED NOT NULL,
		domain VARCHAR(255) NOT NULL,
		status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		approved_at DATETIME DEFAULT NULL,
		UNIQUE KEY unique_domain (business_id, domain),
		INDEX idx_business (business_id),
		INDEX idx_status (status)
	) {$charset_collate};";

	// QR scans table.
	$scans_table = $wpdb->prefix . 'bd_qr_scans';
	$sql_scans   = "CREATE TABLE IF NOT EXISTS {$scans_table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		business_id BIGINT UNSIGNED NOT NULL,
		qr_type VARCHAR(50) NOT NULL,
		ip_address VARCHAR(45) DEFAULT NULL,
		user_agent TEXT DEFAULT NULL,
		referrer VARCHAR(255) DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_business (business_id),
		INDEX idx_date (created_at)
	) {$charset_collate};";

	// Widget clicks table.
	$clicks_table = $wpdb->prefix . 'bd_widget_clicks';
	$sql_clicks   = "CREATE TABLE IF NOT EXISTS {$clicks_table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		business_id BIGINT UNSIGNED NOT NULL,
		action VARCHAR(50) NOT NULL,
		domain VARCHAR(255) DEFAULT NULL,
		ip_address VARCHAR(45) DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_business (business_id),
		INDEX idx_date (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_domains );
	dbDelta( $sql_scans );
	dbDelta( $sql_clicks );
}

// Also create tables if they don't exist (for existing installations).
add_action(
	'admin_init',
	function () {
		// Skip on subsites that don't need local features.
		if ( ! bd_business_tools_should_create_tables() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bd_widget_domains';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			bd_business_tools_create_tables();
		}
	}
);

/**
 * Get the asset version for cache busting.
 *
 * @return string Version string.
 */
function bd_business_tools_get_version() {
	if ( defined( 'BD_VERSION' ) ) {
		return BD_VERSION;
	}
	return '1.0.0';
}

// Enqueue assets for business tools pages.
add_action(
	'wp_enqueue_scripts',
	function () {
		// Only load on relevant pages.
		if ( ! is_page() ) {
			return;
		}

		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check for business tools shortcodes.
		$has_tools = has_shortcode( $post->post_content, 'bd_business_tools' ) ||
					has_shortcode( $post->post_content, 'bd_owner_dashboard' );

		if ( ! $has_tools ) {
			return;
		}

		$plugin_url = plugin_dir_url( __DIR__ );
		$version    = bd_business_tools_get_version();

		wp_enqueue_style(
			'bd-business-tools',
			$plugin_url . 'assets/css/business-tools.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'bd-business-tools',
			$plugin_url . 'assets/js/business-tools.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'bd-business-tools',
			'bdTools',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'bd/v1/' ),
				'nonce'   => wp_create_nonce( 'bd_tools_nonce' ),
				'i18n'    => array(
					'copied'        => __( 'Copied to clipboard!', 'business-directory' ),
					'generating'    => __( 'Generating...', 'business-directory' ),
					'downloadReady' => __( 'Download ready!', 'business-directory' ),
					'error'         => __( 'An error occurred. Please try again.', 'business-directory' ),
				),
			)
		);
	},
	20
);

// Admin assets.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( strpos( $hook, 'bd-business-tools' ) === false ) {
			return;
		}

		$plugin_url = plugin_dir_url( __DIR__ );
		$version    = bd_business_tools_get_version();

		wp_enqueue_style(
			'bd-business-tools-admin',
			$plugin_url . 'assets/css/business-tools.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'bd-business-tools-admin',
			$plugin_url . 'assets/js/business-tools.js',
			array( 'jquery' ),
			$version,
			true
		);
	}
);
