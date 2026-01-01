<?php

namespace BD\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Claim Request REST API Controller
 */
class ClaimController {

	public static function register() {
		// Submit a claim request (public)
		register_rest_route(
			'bd/v1',
			'/claim',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_claim' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get claims (admin only)
		register_rest_route(
			'bd/v1',
			'/claims',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_claims' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		// Approve claim (admin only)
		register_rest_route(
			'bd/v1',
			'/claims/(?P<id>\d+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'approve_claim' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		// Reject claim (admin only)
		register_rest_route(
			'bd/v1',
			'/claims/(?P<id>\d+)/reject',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reject_claim' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);
	}

	/**
	 * Submit a claim request
	 */
	public static function submit_claim( $request ) {
		// Rate limiting
		$ip         = \BD\Security\RateLimit::get_client_ip();
		$rate_check = \BD\Security\RateLimit::check( 'claim_business', $ip, 3, 3600 );

		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Verify Turnstile if enabled
		$turnstile_token = $request->get_param( 'turnstile_token' );
		if ( ! empty( get_option( 'bd_turnstile_site_key' ) ) && ! empty( $turnstile_token ) ) {
			$captcha_check = \BD\Security\Captcha::verify_turnstile( $turnstile_token );
			if ( is_wp_error( $captcha_check ) ) {
				return $captcha_check;
			}
		}

		$business_id = absint( $request->get_param( 'business_id' ) );

		if ( ! $business_id || get_post_status( $business_id ) !== 'publish' ) {
			return new \WP_Error( 'invalid_business', __( 'Invalid business.', 'business-directory' ) );
		}

		// Check if business is already claimed
		$claimed_by = get_post_meta( $business_id, 'bd_claimed_by', true );
		if ( $claimed_by ) {
			return new \WP_Error( 'already_claimed', __( 'This business has already been claimed.', 'business-directory' ) );
		}

		// Check for existing pending claim for this business
		$existing_claim = \BD\DB\ClaimRequestsTable::get_by_business( $business_id, 'pending' );
		if ( ! empty( $existing_claim ) ) {
			return new \WP_Error( 'pending_claim', __( 'There is already a pending claim for this business.', 'business-directory' ) );
		}

		// Handle file uploads
		$proof_files = array();
		if ( ! empty( $_FILES['proof_files'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$files      = $_FILES['proof_files'];
			$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

			for ( $i = 0; $i < min( $file_count, 5 ); $i++ ) { // Max 5 files
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
							$proof_files[] = $attachment_id;
						}
					}
				}
			}
		}

		// Insert claim request
		$claim_data = array(
			'business_id'    => $business_id,
			'user_id'        => get_current_user_id() ?: null,
			'claimant_name'  => sanitize_text_field( $request->get_param( 'claimant_name' ) ),
			'claimant_email' => sanitize_email( $request->get_param( 'claimant_email' ) ),
			'claimant_phone' => sanitize_text_field( $request->get_param( 'claimant_phone' ) ),
			'relationship'   => sanitize_text_field( $request->get_param( 'relationship' ) ),
			'proof_files'    => $proof_files,
			'message'        => sanitize_textarea_field( $request->get_param( 'message' ) ),
		);

		$claim_id = \BD\DB\ClaimRequestsTable::insert( $claim_data );

		if ( ! $claim_id ) {
			return new \WP_Error( 'claim_failed', __( 'Failed to submit claim request.', 'business-directory' ) );
		}

		// Send notification email to admin
		self::notify_admin_new_claim( $claim_id, $business_id, $claim_data );

		// Send confirmation email to claimant
		self::notify_claimant_submitted( $claim_data );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'Thank you! Your claim request has been submitted. We\'ll review it within 24-48 hours.', 'business-directory' ),
				'claim_id' => $claim_id,
			)
		);
	}

	/**
	 * Get claims (admin)
	 */
	public static function get_claims( $request ) {
		$status = $request->get_param( 'status' ) ?: 'pending';

		if ( 'pending' === $status ) {
			$claims = \BD\DB\ClaimRequestsTable::get_pending( 100 );
		} else {
			// Get all claims with specific status
			$claims = array(); // TODO: Add get_by_status method if needed
		}

		// Enhance with business info
		foreach ( $claims as &$claim ) {
			$claim['business'] = array(
				'title'     => get_the_title( $claim['business_id'] ),
				'permalink' => get_permalink( $claim['business_id'] ),
				'edit_link' => get_edit_post_link( $claim['business_id'], 'raw' ),
			);
		}

		return rest_ensure_response( $claims );
	}

	/**
	 * Approve a claim
	 */
	public static function approve_claim( $request ) {
		$claim_id = absint( $request['id'] );
		$notes    = sanitize_textarea_field( $request->get_param( 'notes' ) );
		$admin_id = get_current_user_id();

		$claim = \BD\DB\ClaimRequestsTable::get( $claim_id );

		if ( ! $claim ) {
			return new \WP_Error( 'invalid_claim', __( 'Claim not found.', 'business-directory' ) );
		}

		if ( $claim['status'] !== 'pending' ) {
			return new \WP_Error( 'already_processed', __( 'This claim has already been processed.', 'business-directory' ) );
		}

		// Approve the claim
		\BD\DB\ClaimRequestsTable::approve( $claim_id, $admin_id, $notes );

		// Create or get user account
		$user_id = $claim['user_id'];

		if ( ! $user_id ) {
			// Check if user exists with this email
			$user = get_user_by( 'email', $claim['claimant_email'] );

			if ( $user ) {
				$user_id = $user->ID;
			} else {
				// Create new user
				$username = sanitize_user( $claim['claimant_email'] );
				$password = wp_generate_password( 12, true );

				$user_id = wp_create_user( $username, $password, $claim['claimant_email'] );

				if ( is_wp_error( $user_id ) ) {
					return $user_id;
				}

				// Update user meta
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => $claim['claimant_name'],
						'first_name'   => $claim['claimant_name'],
					)
				);

				// Set role
				$user = new \WP_User( $user_id );
				$user->set_role( 'business_owner' );

				// Send welcome email with login info
				self::notify_claimant_approved( $claim, $password );
			}
		}

		// Link business to user
		update_post_meta( $claim['business_id'], 'bd_claimed_by', $user_id );
		update_post_meta( $claim['business_id'], 'bd_claim_status', 'claimed' );
		update_post_meta( $claim['business_id'], 'bd_claimed_date', current_time( 'mysql' ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Claim approved successfully.', 'business-directory' ),
			)
		);
	}

	/**
	 * Reject a claim
	 */
	public static function reject_claim( $request ) {
		$claim_id = absint( $request['id'] );
		$notes    = sanitize_textarea_field( $request->get_param( 'notes' ) );
		$admin_id = get_current_user_id();

		$claim = \BD\DB\ClaimRequestsTable::get( $claim_id );

		if ( ! $claim ) {
			return new \WP_Error( 'invalid_claim', __( 'Claim not found.', 'business-directory' ) );
		}

		if ( $claim['status'] !== 'pending' ) {
			return new \WP_Error( 'already_processed', __( 'This claim has already been processed.', 'business-directory' ) );
		}

		\BD\DB\ClaimRequestsTable::reject( $claim_id, $admin_id, $notes );

		// Notify claimant of rejection
		self::notify_claimant_rejected( $claim, $notes );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Claim rejected.', 'business-directory' ),
			)
		);
	}

	/**
	 * Admin permission check
	 */
	public static function admin_permission() {
		return current_user_can( 'bd_manage_claims' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Email Notifications
	 */
	private static function notify_admin_new_claim( $claim_id, $business_id, $claim_data ) {
		$admin_email   = get_option( 'bd_notification_emails', get_option( 'admin_email' ) );
		$business_name = get_the_title( $business_id );

		$subject = sprintf( '[%s] New Business Claim: %s', get_bloginfo( 'name' ), $business_name );

		$message = sprintf(
			"A new claim request requires your attention.\n\n" .
			"Business: %s\n" .
			"Claimant: %s\n" .
			"Email: %s\n" .
			"Phone: %s\n\n" .
			"Review and approve: %s\n",
			$business_name,
			$claim_data['claimant_name'],
			$claim_data['claimant_email'],
			$claim_data['claimant_phone'] ?: 'N/A',
			admin_url( 'admin.php?page=bd-pending-claims' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	private static function notify_claimant_submitted( $claim_data ) {
		$subject = sprintf( '[%s] Your Claim Request', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Hi %s,\n\n" .
			"Thank you for claiming your business listing on %s!\n\n" .
			"We've received your claim request and will review it within 24-48 hours.\n\n" .
			"What happens next:\n" .
			"1. Our team will verify your proof of ownership\n" .
			"2. You'll receive an email once approved\n" .
			"3. You can then log in to manage your listing\n\n" .
			"Best regards,\n%s Team",
			$claim_data['claimant_name'],
			get_bloginfo( 'name' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $claim_data['claimant_email'], $subject, $message );
	}

	private static function notify_claimant_approved( $claim, $password ) {
		$subject = sprintf( '[%s] Claim Approved - Welcome!', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Hi %s,\n\n" .
			"Great news! Your claim for %s has been approved.\n\n" .
			"Login Details:\n" .
			"Email: %s\n" .
			"Password: %s\n" .
			"Login URL: %s\n\n" .
			"Next steps:\n" .
			"1. Log in using the link above\n" .
			"2. Change your password\n" .
			"3. Complete your business profile\n" .
			"4. Upload photos and update hours\n\n" .
			"Welcome aboard!\n%s Team",
			$claim['claimant_name'],
			get_the_title( $claim['business_id'] ),
			$claim['claimant_email'],
			$password,
			wp_login_url(),
			get_bloginfo( 'name' )
		);

		wp_mail( $claim['claimant_email'], $subject, $message );
	}

	private static function notify_claimant_rejected( $claim, $notes ) {
		$subject = sprintf( '[%s] Claim Request Update', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Hi %s,\n\n" .
			"Thank you for your claim request for %s.\n\n" .
			"Unfortunately, we were unable to verify your claim at this time.\n\n" .
			"%s\n\n" .
			"If you believe this is an error, please contact us at %s.\n\n" .
			"Best regards,\n%s Team",
			$claim['claimant_name'],
			get_the_title( $claim['business_id'] ),
			! empty( $notes ) ? $notes : 'Additional verification is required.',
			get_option( 'admin_email' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $claim['claimant_email'], $subject, $message );
	}
}
