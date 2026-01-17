<?php
/**
 * Captcha Verification
 *
 * Provides Cloudflare Turnstile captcha verification for forms.
 *
 * IMPORTANT: When Turnstile is not configured (no secret key), verification
 * is bypassed and forms work without captcha protection. This is intentional
 * to allow the plugin to work out-of-the-box, but site owners should configure
 * Turnstile for production use.
 *
 * @package BusinessDirectory
 * @subpackage Security
 * @version 1.2.0
 */

namespace BD\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Captcha
 *
 * Handles Cloudflare Turnstile verification for form submissions.
 */
class Captcha {

	/**
	 * Turnstile verification API endpoint.
	 *
	 * @var string
	 */
	const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Verify a Turnstile captcha token.
	 *
	 * @param string $token The captcha token from the frontend.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_turnstile( $token ) {
		$secret = get_option( 'bd_turnstile_secret_key' );

		// If not configured, bypass verification.
		// This allows the plugin to work without captcha, but logs a notice.
		if ( empty( $secret ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
				error_log( '[BD Captcha] Turnstile not configured - verification bypassed. Configure bd_turnstile_secret_key for production.' );
			}

			/**
			 * Fires when captcha verification is bypassed due to missing configuration.
			 *
			 * Allows site owners to log or handle this case.
			 */
			do_action( 'bd_captcha_bypassed' );

			return true;
		}

		// Validate token is provided.
		if ( empty( $token ) ) {
			return new \WP_Error(
				'captcha_missing',
				__( 'Captcha verification required. Please complete the captcha.', 'business-directory' )
			);
		}

		// Get client IP safely (RateLimit class may not be loaded).
		$client_ip = self::get_client_ip();

		// Make verification request to Cloudflare.
		$response = wp_remote_post(
			self::TURNSTILE_VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => sanitize_text_field( $token ),
					'remoteip' => $client_ip,
				),
			)
		);

		// Handle network/HTTP errors.
		if ( is_wp_error( $response ) ) {
			self::log_error(
				sprintf(
					'[BD Captcha] Turnstile API error: %s',
					$response->get_error_message()
				)
			);

			/**
			 * Fires when Turnstile API request fails.
			 *
			 * @param \WP_Error $response The error response.
			 */
			do_action( 'bd_captcha_api_error', $response );

			// Return a user-friendly error, not the technical details.
			return new \WP_Error(
				'captcha_error',
				__( 'Verification service unavailable. Please try again.', 'business-directory' )
			);
		}

		// Parse response.
		$response_body = wp_remote_retrieve_body( $response );
		$body          = json_decode( $response_body, true );

		// Handle JSON decode failure.
		if ( null === $body ) {
			self::log_error(
				sprintf(
					'[BD Captcha] Invalid JSON response from Turnstile: %s',
					substr( $response_body, 0, 200 )
				)
			);

			return new \WP_Error(
				'captcha_error',
				__( 'Verification service returned invalid response. Please try again.', 'business-directory' )
			);
		}

		// Check for successful verification.
		if ( isset( $body['success'] ) && true === $body['success'] ) {
			return true;
		}

		// Log verification failures for debugging.
		$error_codes = 'unknown';
		if ( isset( $body['error-codes'] ) && is_array( $body['error-codes'] ) ) {
			$error_codes = implode( ', ', $body['error-codes'] );
		}

		self::log_error(
			sprintf(
				'[BD Captcha] Turnstile verification failed. Error codes: %s',
				$error_codes
			)
		);

		/**
		 * Fires when captcha verification fails.
		 *
		 * @param array $body The response body from Turnstile API.
		 */
		do_action( 'bd_captcha_verification_failed', $body );

		return new \WP_Error(
			'captcha_failed',
			__( 'Verification failed. Please try again.', 'business-directory' )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * Uses RateLimit class if available, otherwise falls back to basic detection.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip() {
		// Use RateLimit class if available (it has more sophisticated detection).
		if ( class_exists( 'BD\Security\RateLimit' ) && method_exists( 'BD\Security\RateLimit', 'get_client_ip' ) ) {
			return RateLimit::get_client_ip();
		}

		// Fallback: basic IP detection.
		// Check Cloudflare header first.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		// Fall back to REMOTE_ADDR.
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Log an error message if debug mode is enabled.
	 *
	 * @param string $message Error message to log.
	 */
	private static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
			error_log( $message );
		}
	}

	/**
	 * Check if Turnstile is configured.
	 *
	 * Useful for conditionally showing captcha in templates.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public static function is_configured() {
		$secret   = get_option( 'bd_turnstile_secret_key' );
		$site_key = get_option( 'bd_turnstile_site_key' );

		return ! empty( $secret ) && ! empty( $site_key );
	}

	/**
	 * Get the site key for frontend use.
	 *
	 * @return string|null Site key or null if not configured.
	 */
	public static function get_site_key() {
		$site_key = get_option( 'bd_turnstile_site_key' );

		return ! empty( $site_key ) ? $site_key : null;
	}
}
