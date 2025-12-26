<?php
/**
 * Auth Module Loader
 *
 * Loads all authentication components.
 * Include this file from business-directory.php.
 *
 * @package BusinessDirectory
 * @subpackage Auth
 * @version 1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load once.
if ( defined( 'BD_AUTH_LOADED' ) ) {
	return;
}
define( 'BD_AUTH_LOADED', true );

// Load auth classes.
require_once BD_PLUGIN_DIR . 'src/Auth/AuthHandler.php';
require_once BD_PLUGIN_DIR . 'src/Auth/AuthFilters.php';
require_once BD_PLUGIN_DIR . 'src/Auth/LoginShortcode.php';
require_once BD_PLUGIN_DIR . 'src/Auth/LoginModal.php';
require_once BD_PLUGIN_DIR . 'src/Auth/HeaderButtons.php';

// Initialize components.
\BD\Auth\AuthHandler::init();
\BD\Auth\AuthFilters::init();
\BD\Auth\LoginShortcode::init();
\BD\Auth\LoginModal::init();
\BD\Auth\HeaderButtons::init();

/**
 * AJAX endpoint to get a fresh nonce (bypasses page cache)
 */
add_action( 'wp_ajax_bd_get_nonce', 'bd_get_fresh_nonce' );
add_action( 'wp_ajax_nopriv_bd_get_nonce', 'bd_get_fresh_nonce' );

/**
 * Return a fresh nonce for auth forms
 */
function bd_get_fresh_nonce() {
	wp_send_json_success( array(
		'nonce' => wp_create_nonce( 'bd_auth_nonce' ),
	) );
}
