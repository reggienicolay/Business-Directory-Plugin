<?php
/**
 * Rate Limiting Security
 *
 * Provides rate limiting functionality to prevent abuse.
 *
 * @package BusinessDirectory
 * @subpackage Security
 * @version 1.3.0
 */

namespace BD\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RateLimit
 *
 * Handles rate limiting for various actions to prevent abuse.
 *
 * Note: This implementation uses WordPress transients which are not atomic.
 * There is a small race condition window between get_transient() and set_transient()
 * where concurrent requests could both pass. For most use cases (form submission,
 * review posting), this is acceptable. For high-security scenarios requiring
 * strict atomic operations, consider using wp_cache_incr() with a persistent
 * object cache backend (Redis/Memcached).
 */
class RateLimit {

	/**
	 * Cached fallback salt for key generation.
	 *
	 * @var string|null
	 */
	private static $fallback_salt = null;

	/**
	 * Check rate limit for an action
	 *
	 * @param string $action     Action identifier (e.g., 'review_submit', 'login_attempt').
	 * @param string $identifier Unique identifier (e.g., user ID, IP address).
	 * @param int    $max        Maximum allowed attempts within the window.
	 * @param int    $window     Time window in seconds.
	 * @return bool|\WP_Error True if within limit, WP_Error if exceeded.
	 */
	public static function check( $action, $identifier, $max = 3, $window = 3600 ) {
		// Sanitize inputs.
		$action     = sanitize_key( $action );
		$identifier = sanitize_text_field( $identifier );

		// Generate secure key with site-specific salt.
		$key = self::generate_key( $action, $identifier );

		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			set_transient( $key, 1, $window );
			return true;
		}

		if ( $attempts >= $max ) {
			// Log rate limit hit for monitoring.
			self::log_rate_limit_hit( $action, $identifier, $attempts );

			return new \WP_Error(
				'rate_limit',
				sprintf(
					/* translators: %d: minutes until rate limit resets */
					__( 'Too many attempts. Please try again in %d minutes.', 'business-directory' ),
					ceil( $window / 60 )
				),
				array(
					'status'      => 429,
					'retry_after' => $window,
				)
			);
		}

		set_transient( $key, $attempts + 1, $window );
		return true;
	}

	/**
	 * Generate a secure rate limit key
	 *
	 * Uses site-specific salt to prevent key prediction attacks.
	 *
	 * @param string $action     Action identifier.
	 * @param string $identifier Unique identifier.
	 * @return string Secure key.
	 */
	private static function generate_key( $action, $identifier ) {
		// Use WordPress NONCE_SALT for security, with cached fallback.
		if ( defined( 'NONCE_SALT' ) && NONCE_SALT ) {
			$salt = NONCE_SALT;
		} else {
			// Cache the fallback salt to avoid repeated get_site_url() calls.
			if ( null === self::$fallback_salt ) {
				self::$fallback_salt = 'bd_fallback_salt_' . get_site_url();
			}
			$salt = self::$fallback_salt;
		}

		return 'bd_rl_' . substr( md5( $action . '_' . $identifier . '_' . $salt ), 0, 32 );
	}

	/**
	 * Log rate limit hit for monitoring
	 *
	 * @param string $action     Action that was rate limited.
	 * @param string $identifier Identifier that hit the limit.
	 * @param int    $attempts   Number of attempts.
	 */
	private static function log_rate_limit_hit( $action, $identifier, $attempts ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
			error_log(
				sprintf(
					'[BD Rate Limit] Action: %s, Identifier: %s (hashed), Attempts: %d',
					$action,
					substr( md5( $identifier ), 0, 8 ), // Log hashed identifier for privacy.
					$attempts
				)
			);
		}

		/**
		 * Fires when a rate limit is hit.
		 *
		 * @param string $action     Action that was rate limited.
		 * @param string $identifier Identifier that hit the limit (could be IP or user ID).
		 * @param int    $attempts   Number of attempts made.
		 */
		do_action( 'bd_rate_limit_hit', $action, $identifier, $attempts );
	}

	/**
	 * Reset rate limit for an action/identifier
	 *
	 * Useful after successful action (e.g., after successful login).
	 *
	 * @param string $action     Action identifier.
	 * @param string $identifier Unique identifier.
	 * @return bool True on success, false on failure.
	 */
	public static function reset( $action, $identifier ) {
		$action     = sanitize_key( $action );
		$identifier = sanitize_text_field( $identifier );
		$key        = self::generate_key( $action, $identifier );

		return delete_transient( $key );
	}

	/**
	 * Get remaining attempts for an action/identifier
	 *
	 * @param string $action     Action identifier.
	 * @param string $identifier Unique identifier.
	 * @param int    $max        Maximum allowed attempts.
	 * @return int Remaining attempts.
	 */
	public static function get_remaining( $action, $identifier, $max = 3 ) {
		$action     = sanitize_key( $action );
		$identifier = sanitize_text_field( $identifier );
		$key        = self::generate_key( $action, $identifier );

		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			return $max;
		}

		return max( 0, $max - $attempts );
	}

	/**
	 * Get client IP address
	 *
	 * Handles various proxy configurations securely.
	 * Only trusts proxy headers when explicitly configured.
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip() {
		// First, check for Cloudflare (most secure - Cloudflare validates this).
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( self::is_valid_ip( $ip ) ) {
				return $ip;
			}
		}

		// Check if we should trust proxy headers.
		// Only trust X-Forwarded-For if explicitly enabled via filter.
		$trust_proxy_headers = apply_filters( 'bd_trust_proxy_headers', false );

		if ( $trust_proxy_headers && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// X-Forwarded-For can contain multiple IPs: client, proxy1, proxy2.
			// The first IP is the original client.
			$forwarded_ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$client_ip     = trim( $forwarded_ips[0] );

			if ( self::is_valid_ip( $client_ip ) && ! self::is_private_ip( $client_ip ) ) {
				return $client_ip;
			}
		}

		// Fall back to REMOTE_ADDR (always available, but may be proxy IP).
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';

		return self::is_valid_ip( $remote_addr ) ? $remote_addr : '0.0.0.0';
	}

	/**
	 * Validate IP address format
	 *
	 * @param string $ip IP address to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_valid_ip( $ip ) {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Check if IP is in private range
	 *
	 * Private IPs should not be trusted from X-Forwarded-For.
	 *
	 * @param string $ip IP address to check.
	 * @return bool True if private, false otherwise.
	 */
	private static function is_private_ip( $ip ) {
		return filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) === false;
	}

	/**
	 * Get composite identifier for rate limiting
	 *
	 * Combines user ID (if logged in) with IP for better accuracy.
	 *
	 * @return string Composite identifier.
	 */
	public static function get_identifier() {
		$parts = array();

		// Include user ID if logged in.
		if ( is_user_logged_in() ) {
			$parts[] = 'u' . get_current_user_id();
		}

		// Always include IP.
		$parts[] = 'ip' . self::get_client_ip();

		return implode( '_', $parts );
	}
}
