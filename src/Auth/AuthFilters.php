<?php
/**
 * Authentication Filters
 *
 * Redirects wp-login.php to frontend login page.
 * Filters wp_login_url() and wp_registration_url().
 *
 * @package BusinessDirectory
 * @subpackage Auth
 * @version 1.0.0
 */

namespace BD\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AuthFilters
 */
class AuthFilters {

	/**
	 * Login page slug
	 *
	 * @var string
	 */
	const LOGIN_SLUG = 'login';

	/**
	 * Initialize filters
	 */
	public static function init() {
		// Filter login URL.
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );

		// Filter registration URL.
		add_filter( 'register_url', array( __CLASS__, 'filter_register_url' ), 10, 1 );

		// Filter logout URL to redirect to home.
		add_filter( 'logout_url', array( __CLASS__, 'filter_logout_url' ), 10, 2 );

		// Filter lostpassword URL.
		add_filter( 'lostpassword_url', array( __CLASS__, 'filter_lostpassword_url' ), 10, 2 );

		// Block wp-login.php access.
		add_action( 'login_init', array( __CLASS__, 'maybe_redirect_login' ), 1 );

		// Redirect after logout.
		add_action( 'wp_logout', array( __CLASS__, 'redirect_after_logout' ) );

		// Redirect after login if coming from frontend.
		add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 10, 3 );
	}

	/**
	 * Filter login URL to use frontend page
	 *
	 * @param string $login_url    Original login URL.
	 * @param string $redirect     Redirect URL after login.
	 * @param bool   $force_reauth Force reauth flag.
	 * @return string
	 */
	public static function filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
		$frontend_url = self::get_login_page_url();

		if ( ! $frontend_url ) {
			return $login_url;
		}

		if ( ! empty( $redirect ) ) {
			$frontend_url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $frontend_url );
		}

		return $frontend_url;
	}

	/**
	 * Filter registration URL to use frontend page
	 *
	 * @param string $register_url Original registration URL.
	 * @return string
	 */
	public static function filter_register_url( $register_url ) {
		$frontend_url = self::get_login_page_url();

		if ( ! $frontend_url ) {
			return $register_url;
		}

		return add_query_arg( 'tab', 'register', $frontend_url );
	}

	/**
	 * Filter logout URL
	 *
	 * @param string $logout_url Original logout URL.
	 * @param string $redirect   Redirect URL after logout.
	 * @return string
	 */
	public static function filter_logout_url( $logout_url, $redirect = '' ) {
		// Keep the standard logout URL but ensure redirect goes to home.
		if ( empty( $redirect ) ) {
			$redirect = home_url();
		}

		return wp_nonce_url(
			add_query_arg( 'redirect_to', rawurlencode( $redirect ), site_url( 'wp-login.php?action=logout' ) ),
			'log-out'
		);
	}

	/**
	 * Filter lost password URL
	 *
	 * @param string $lostpassword_url Original URL.
	 * @param string $redirect         Redirect URL.
	 * @return string
	 */
	public static function filter_lostpassword_url( $lostpassword_url, $redirect = '' ) {
		$frontend_url = self::get_login_page_url();

		if ( ! $frontend_url ) {
			return $lostpassword_url;
		}

		$url = add_query_arg( 'tab', 'reset', $frontend_url );

		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}

		return $url;
	}

	/**
	 * Maybe redirect wp-login.php to frontend
	 */
	public static function maybe_redirect_login() {
		// Allow logout action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'logout' === $action ) {
			return;
		}

		// Allow password reset completion (rp action).
		if ( in_array( $action, array( 'rp', 'resetpass', 'postpass' ), true ) ) {
			return;
		}

		// Allow AJAX/API requests.
		if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		// Allow if specifically requesting interim login.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['interim-login'] ) ) {
			return;
		}

		// Get frontend login URL.
		$frontend_url = self::get_login_page_url();

		if ( ! $frontend_url ) {
			return;
		}

		// Build redirect URL with any query params.
		$redirect_url = $frontend_url;

		// Preserve redirect_to.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect_url = add_query_arg(
				'redirect_to',
				rawurlencode( esc_url_raw( $_GET['redirect_to'] ) ),
				$redirect_url
			);
		}

		// Map action to tab.
		if ( 'register' === $action ) {
			$redirect_url = add_query_arg( 'tab', 'register', $redirect_url );
		} elseif ( 'lostpassword' === $action ) {
			$redirect_url = add_query_arg( 'tab', 'reset', $redirect_url );
		}

		// Redirect.
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Redirect after logout
	 */
	public static function redirect_after_logout() {
		// This is handled by the logout_url filter, but as a fallback.
		if ( ! wp_doing_ajax() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Filter login redirect
	 *
	 * @param string   $redirect_to           Redirect URL.
	 * @param string   $requested_redirect_to Requested redirect URL.
	 * @param \WP_User $user                  User object.
	 * @return string
	 */
	public static function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		// If no specific redirect requested, go to profile.
		if ( empty( $requested_redirect_to ) || admin_url() === $requested_redirect_to ) {
			// Non-admin users go to profile.
			if ( $user instanceof \WP_User && ! user_can( $user, 'manage_options' ) ) {
				return home_url( '/my-profile/' );
			}
		}

		return $redirect_to;
	}

	/**
	 * Get login page URL
	 *
	 * @return string|false
	 */
	public static function get_login_page_url() {
		// Check for page with our shortcode.
		$login_page = get_page_by_path( self::LOGIN_SLUG );

		if ( $login_page ) {
			return get_permalink( $login_page );
		}

		// Fallback: search for page with [bd_login] shortcode.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_id = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'page'
			AND post_status = 'publish'
			AND post_content LIKE '%[bd_login%'
			LIMIT 1"
		);

		if ( $page_id ) {
			return get_permalink( $page_id );
		}

		return false;
	}

	/**
	 * Check if current page is the login page
	 *
	 * @return bool
	 */
	public static function is_login_page() {
		if ( is_page( self::LOGIN_SLUG ) ) {
			return true;
		}

		global $post;
		if ( $post && has_shortcode( $post->post_content, 'bd_login' ) ) {
			return true;
		}

		return false;
	}
}
