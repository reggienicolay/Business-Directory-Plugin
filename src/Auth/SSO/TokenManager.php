<?php
/**
 * SSO Token Manager
 *
 * Handles secure token generation and validation for cross-domain SSO.
 * Uses network-wide transients for multisite compatibility.
 *
 * @package BusinessDirectory
 * @subpackage Auth\SSO
 * @version 2.0.1
 */

namespace BD\Auth\SSO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TokenManager
 */
class TokenManager {

	/**
	 * Token expiry time in seconds
	 */
	const TOKEN_EXPIRY = 60;

	/**
	 * Token prefix for transient storage
	 */
	const TOKEN_PREFIX = 'bd_sso_token_';

	/**
	 * Generate a secure SSO token
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $origin_site Site ID where login originated.
	 * @param string $return_url  URL to return to after sync.
	 * @return string|false Token string or false on failure.
	 */
	public static function generate_token( $user_id, $origin_site, $return_url = '' ) {
		if ( ! $user_id || ! $origin_site ) {
			return false;
		}

		// Generate cryptographically secure token.
		$token = bin2hex( random_bytes( 32 ) );

		// Token data to store.
		$token_data = array(
			'user_id'     => (int) $user_id,
			'origin_site' => (int) $origin_site,
			'return_url'  => $return_url,
			'created'     => time(),
			'ip'          => self::get_client_ip(),
		);

		// Store using network-wide site transient.
		$stored = set_site_transient(
			self::TOKEN_PREFIX . $token,
			$token_data,
			self::TOKEN_EXPIRY
		);

		if ( ! $stored ) {
			return false;
		}

		return $token;
	}

	/**
	 * Validate an SSO token
	 *
	 * @param string $token Token to validate.
	 * @return array|false Token data or false if invalid.
	 */
	public static function validate_token( $token ) {
		if ( empty( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return false;
		}

		// Get token data from network-wide transient.
		$token_data = get_site_transient( self::TOKEN_PREFIX . $token );

		if ( ! $token_data || ! is_array( $token_data ) ) {
			return false;
		}

		// Verify required fields.
		if ( empty( $token_data['user_id'] ) || empty( $token_data['origin_site'] ) ) {
			self::invalidate_token( $token );
			return false;
		}

		// Verify user exists in network.
		$user = get_user_by( 'id', $token_data['user_id'] );
		if ( ! $user ) {
			self::invalidate_token( $token );
			return false;
		}

		// Invalidate token after use (single-use).
		self::invalidate_token( $token );

		return $token_data;
	}

	/**
	 * Invalidate (delete) a token
	 *
	 * @param string $token Token to invalidate.
	 * @return bool True on success.
	 */
	public static function invalidate_token( $token ) {
		return delete_site_transient( self::TOKEN_PREFIX . $token );
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip_list   = explode( ',', $forwarded );
			$ip        = trim( $ip_list[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
