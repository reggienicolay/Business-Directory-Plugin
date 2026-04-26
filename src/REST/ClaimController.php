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

		// List authorized users for a business (admin only).
		register_rest_route(
			'bd/v1',
			'/businesses/(?P<id>\d+)/access',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_business_access' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Revoke an approved claim (admin only). Distinct from reject_claim,
		// which only operates on pending rows. This acts on already-approved
		// rows and handles primary-owner cleanup.
		register_rest_route(
			'bd/v1',
			'/claims/(?P<id>\d+)/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'revoke_access' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'note' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// In-field grant access (admin only) — bypasses the public claim form.
		register_rest_route(
			'bd/v1',
			'/claims/grant',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'grant_access' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'business_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'email'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => static function ( $value ) {
							return is_email( $value ) !== false;
						},
					),
					'name'         => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'        => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'relationship' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => array( 'owner', 'manager', 'staff', 'other' ),
						'default'           => 'owner',
					),
					'note'         => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_welcome' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
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

		// Verify Turnstile CAPTCHA if configured.
		if ( ! empty( get_option( 'bd_turnstile_site_key' ) ) ) {
			$turnstile_token = $request->get_param( 'turnstile_token' );
			if ( empty( $turnstile_token ) ) {
				return new \WP_Error(
					'captcha_required',
					__( 'Please complete the CAPTCHA verification.', 'business-directory' ),
					array( 'status' => 400 )
				);
			}
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

		// Handle file uploads with server-side MIME validation.
		$proof_files    = array();
		$allowed_mimes  = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );
		$max_file_size  = 5 * 1024 * 1024; // 5MB per file.

		if ( ! empty( $_FILES['proof_files'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$files      = $_FILES['proof_files'];
			$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

			for ( $i = 0; $i < min( $file_count, 5 ); $i++ ) { // Max 5 files.
				if ( isset( $files['error'][ $i ] ) && $files['error'][ $i ] === UPLOAD_ERR_OK ) {

					// Validate file size.
					if ( $files['size'][ $i ] > $max_file_size ) {
						continue;
					}

					// Validate MIME type using actual file content (not client-provided type).
					$real_mime = \BD\Security\MimeValidator::get_real_mime_type( $files['tmp_name'][ $i ] );
					if ( ! $real_mime || ! in_array( $real_mime, $allowed_mimes, true ) ) {
						error_log( '[BD Claim] Rejected file upload with MIME: ' . ( $real_mime ?: 'unknown' ) );
						continue;
					}

					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $real_mime, // Use verified MIME, not client-provided.
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

		// Allow companion plugins to react to claim approval.
		do_action( 'bd_claim_approved', $claim_id, $claim['business_id'], $user_id );

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
	 * In-field grant access handler.
	 *
	 * Endpoint: POST /bd/v1/claims/grant
	 * Auth:     bd_manage_claims (or manage_options)
	 *
	 * Lets a directory manager authorise a known business owner (or marketing
	 * contact) to edit a listing without making them complete the public claim
	 * form. Delegates all logic to \BD\Admin\GrantAccess::grant() so this is
	 * the only endpoint — the meta box, row action, and frontend toolbar UIs
	 * (Phase 2) will hit this same route.
	 *
	 * A light rate limit is applied per admin to prevent runaway loops from a
	 * buggy client. This is NOT a security boundary — the cap check is.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function grant_access( $request ) {
		$admin_id = get_current_user_id();

		// Mild per-admin rate limit (belt-and-suspenders; cap check is the real gate).
		$rate_check = \BD\Security\RateLimit::check( 'claim_grant', 'u' . $admin_id, 30, 600 );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$result = \BD\Admin\GrantAccess::grant(
			array(
				'business_id'  => absint( $request->get_param( 'business_id' ) ),
				'email'        => sanitize_email( $request->get_param( 'email' ) ),
				'name'         => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'phone'        => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
				'relationship' => sanitize_text_field( (string) ( $request->get_param( 'relationship' ) ?: 'owner' ) ),
				'note'         => sanitize_textarea_field( (string) $request->get_param( 'note' ) ),
				'send_welcome' => (bool) $request->get_param( 'send_welcome' ),
				'granted_by'   => $admin_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * List authorized users for a business.
	 *
	 * Endpoint: GET /bd/v1/businesses/{id}/access
	 * Auth:     bd_manage_claims (or manage_options)
	 *
	 * Returns all approved claim rows for a business with minimal user
	 * info joined in (display_name, user_email). Used by the access modal
	 * and the "Business Access" meta box to render the current authorized
	 * users list.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_business_access( $request ) {
		$business_id = absint( $request['id'] );

		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return new \WP_Error( 'invalid_business', __( 'Business not found.', 'business-directory' ), array( 'status' => 404 ) );
		}

		$rows = \BD\DB\ClaimRequestsTable::get_authorized_users( $business_id );

		// Shape the payload for the UI: only the fields the modal needs, all
		// strings escaped at render time by the client. Also flag the primary
		// owner (bd_claimed_by) so the UI can badge it.
		$primary_id = (int) get_post_meta( $business_id, 'bd_claimed_by', true );

		$users = array();
		foreach ( $rows as $row ) {
			$users[] = array(
				'claim_id'     => (int) $row['id'],
				'user_id'      => (int) $row['user_id'],
				'display_name' => $row['display_name'] ?: $row['claimant_name'] ?: $row['claimant_email'],
				'email'        => $row['user_email'] ?: $row['claimant_email'],
				'phone'        => $row['claimant_phone'],
				'relationship' => $row['relationship'] ?: 'owner',
				'granted_at'   => $row['reviewed_at'],
				'granted_by'   => (int) $row['reviewed_by'],
				'admin_notes'  => $row['admin_notes'],
				'is_primary'   => ( (int) $row['user_id'] === $primary_id ),
				'user_missing' => empty( $row['user_login'] ),
			);
		}

		return rest_ensure_response(
			array(
				'business_id' => $business_id,
				'users'       => $users,
			)
		);
	}

	/**
	 * Revoke an approved claim.
	 *
	 * Endpoint: POST /bd/v1/claims/{id}/revoke
	 * Auth:     bd_manage_claims (or manage_options)
	 *
	 * Delegates to GrantAccess::revoke() which handles the DB flip and
	 * primary-owner reassignment.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function revoke_access( $request ) {
		$result = \BD\Admin\GrantAccess::revoke(
			array(
				'claim_id'   => absint( $request['id'] ),
				'note'       => sanitize_textarea_field( (string) $request->get_param( 'note' ) ),
				'revoked_by' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
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
