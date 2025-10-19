<?php
namespace BD\Security;

class Captcha {

	const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public static function verify_turnstile( $token ) {
		$secret = get_option( 'bd_turnstile_secret_key' );

		if ( empty( $secret ) ) {
			return true; // Skip if not configured
		}

		$response = wp_remote_post(
			self::TURNSTILE_VERIFY_URL,
			array(
				'body' => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['success'] ) && $body['success'] ) {
			return true;
		}

		return new \WP_Error( 'captcha_failed', __( 'Verification failed. Please try again.', 'business-directory' ) );
	}
}
