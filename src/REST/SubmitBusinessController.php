<?php
namespace BD\REST;

class SubmitBusinessController {

	public static function register() {
		register_rest_route(
			'bd/v1',
			'/submit-business',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function submit( $request ) {
		$ip         = \BD\Security\RateLimit::get_client_ip();
		$rate_check = \BD\Security\RateLimit::check( 'submit_business', $ip, 3, 3600 );

		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$turnstile_token = $request->get_param( 'turnstile_token' );
		if ( ! empty( get_option( 'bd_turnstile_site_key' ) ) && ! empty( $turnstile_token ) ) {
			$captcha_check = \BD\Security\Captcha::verify_turnstile( $turnstile_token );
			if ( is_wp_error( $captcha_check ) ) {
				return $captcha_check;
			}
		}

		$business_data = array(
			'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
			'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'category'    => absint( $request->get_param( 'category' ) ),
			'address'     => sanitize_text_field( $request->get_param( 'address' ) ),
			'city'        => sanitize_text_field( $request->get_param( 'city' ) ),
			'phone'       => sanitize_text_field( $request->get_param( 'phone' ) ),
			'website'     => esc_url_raw( $request->get_param( 'website' ) ),
		);

		if ( empty( $business_data['title'] ) || empty( $business_data['description'] ) ) {
			return new \WP_Error( 'missing_required', __( 'Please fill in all required fields.', 'business-directory' ) );
		}

		$submission_id = \BD\DB\SubmissionsTable::insert(
			array(
				'business_data'   => $business_data,
				'submitter_name'  => sanitize_text_field( $request->get_param( 'submitter_name' ) ),
				'submitter_email' => sanitize_email( $request->get_param( 'submitter_email' ) ),
				'ip_address'      => $ip,
			)
		);

		if ( ! $submission_id ) {
			return new \WP_Error( 'submission_failed', __( 'Failed to save submission.', 'business-directory' ) );
		}

		\BD\Notifications\Email::notify_new_submission( $submission_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Thank you! Your submission is pending review.', 'business-directory' ),
			)
		);
	}
}
