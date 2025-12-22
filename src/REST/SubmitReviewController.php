<?php

namespace BD\REST;

error_log( 'SubmitReviewController class loaded!' ); // ADD THIS

class SubmitReviewController {


	public static function register() {
		error_log( 'SubmitReviewController::register() called!' ); // ADD THIS
		register_rest_route(
			'bd/v1',
			'/submit-review',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function submit( $request ) {
		$ip = \BD\Security\RateLimit::get_client_ip();
		// Handle comma-separated IPs (from proxies) - just take the first one
		if ( strpos( $ip, ',' ) !== false ) {
			$ip = trim( explode( ',', $ip )[0] );
		}
		$rate_check = \BD\Security\RateLimit::check( 'submit_review', $ip, 5, 3600 );

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

		$business_id = absint( $request->get_param( 'business_id' ) );
		$rating      = absint( $request->get_param( 'rating' ) );

		if ( ! $business_id || $rating < 1 || $rating > 5 ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid data.', 'business-directory' ) );
		}

		$photo_ids = array();
		if ( ! empty( $_FILES['photos'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$files      = $_FILES['photos'];
			$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

			for ( $i = 0; $i < min( $file_count, 3 ); $i++ ) {
				if ( isset( $files['error'][ $i ] ) && $files['error'][ $i ] === UPLOAD_ERR_OK ) {
					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);

					$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

					if ( ! isset( $upload['error'] ) ) {
						$attachment_id = wp_insert_attachment(
							array(
								'post_mime_type' => $upload['type'],
								'post_title'     => sanitize_file_name( $upload['file'] ),
								'post_status'    => 'inherit',
							),
							$upload['file']
						);

						if ( $attachment_id ) {
							wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
							$photo_ids[] = $attachment_id;
						}
					}
				}
			}
		}

		$review_data = array(
			'business_id'  => $business_id,
			'user_id'      => get_current_user_id() ?: null,
			'rating'       => $rating,
			'author_name'  => sanitize_text_field( $request->get_param( 'author_name' ) ),
			'author_email' => sanitize_email( $request->get_param( 'author_email' ) ),
			'title'        => sanitize_text_field( $request->get_param( 'title' ) ),
			'content'      => sanitize_textarea_field( $request->get_param( 'content' ) ),
			'photo_ids'    => ! empty( $photo_ids ) ? implode( ',', $photo_ids ) : null,
			'ip_address'   => $ip,
		);

		$review_id = \BD\DB\ReviewsTable::insert( $review_data );

		if ( ! $review_id ) {
			return new \WP_Error( 'submission_failed', __( 'Failed to save review.', 'business-directory' ) );
		}

		\BD\Notifications\Email::notify_new_review( $review_id );

		// If review is auto-approved, trigger gamification
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		$review        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $reviews_table WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( $review && $review['status'] === 'approved' ) {
			do_action( 'bd_review_approved', $review_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Thank you! Your review is pending approval.', 'business-directory' ),
			)
		);
	}
}
add_action( 'rest_api_init', array( 'BD\REST\SubmitReviewController', 'register' ) );
