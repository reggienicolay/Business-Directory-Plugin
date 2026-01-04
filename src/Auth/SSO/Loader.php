<?php
/**
 * SSO Loader
 *
 * Initializes SSO components for cross-domain authentication.
 * Uses redirect chain for reliable authentication across all sites.
 *
 * @package BusinessDirectory
 * @subpackage Auth\SSO
 * @version 2.1.2
 */

namespace BD\Auth\SSO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 */
class Loader {

	/**
	 * Whether SSO has been initialized
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize SSO system
	 */
	public static function init() {
		// Prevent double initialization.
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// Only initialize on multisite.
		if ( ! is_multisite() ) {
			return;
		}

		// Check if SSO is enabled (default: true on multisite).
		if ( ! self::is_sso_enabled() ) {
			return;
		}

		// Load SSO handler (handles all SSO logic including login response filter).
		SSOHandler::init();

		// Admin settings.
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}
	}

	/**
	 * Check if SSO is enabled
	 *
	 * @return bool
	 */
	public static function is_sso_enabled() {
		// Network-wide setting.
		$enabled = get_site_option( 'bd_sso_enabled', true );

		/**
		 * Filter whether SSO is enabled
		 *
		 * @param bool $enabled Whether SSO is enabled.
		 */
		return apply_filters( 'bd_sso_enabled', (bool) $enabled );
	}

	/**
	 * Register admin settings
	 */
	public static function register_settings() {
		// Only super admins can manage SSO settings.
		if ( ! is_super_admin() ) {
			return;
		}

		register_setting(
			'bd_sso_settings',
			'bd_sso_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
	}
}

// Initialize SSO on plugins_loaded.
add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Loader', 'init' ), 20 );
