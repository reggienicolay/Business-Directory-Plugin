<?php
/**
 * Submit Review REST Controller
 *
 * Handles review submissions via REST API with comprehensive security measures.
 *
 * @package BusinessDirectory
 */

namespace BD\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SubmitReviewController {

	/**
	 * Maximum file size in bytes (5MB).
	 */
	const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Allowed MIME types for photo uploads.
	 */
	const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Content length limits.
	 */
	const MIN_CONTENT_LENGTH = 10;
	const MAX_CONTENT_LENGTH = 5000;
	const MAX_TITLE_LENGTH   = 200;
	const MAX_NAME_LENGTH    = 100;

	/**
	 * Register REST routes.
	 */
	public static function register() {
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

	/**
	 * Handle review submission.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public static function submit( $request ) {
		// Get and sanitize client IP.
		$ip = self::get_sanitized_ip();

		// Check rate limit (5 reviews per hour per IP).
		$rate_check = \BD\Security\RateLimit::check( 'submit_review', $ip, 5, 3600 );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Verify CAPTCHA if enabled.
		$captcha_result = self::verify_captcha( $request );
		if ( is_wp_error( $captcha_result ) ) {
			return $captcha_result;
		}

		// Validate business ID and rating.
		$business_id = absint( $request->get_param( 'business_id' ) );
		$rating      = absint( $request->get_param( 'rating' ) );

		if ( ! $business_id || $rating < 1 || $rating > 5 ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid rating or business ID.', 'business-directory' ),
				array( 'status' => 400 )
			);
		}

		// Verify business exists and is published.
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type || 'publish' !== $business->post_status ) {
			return new \WP_Error(
				'invalid_business',
				__( 'Business not found.', 'business-directory' ),
				array( 'status' => 404 )
			);
		}

		// Check for duplicate review (same user/IP + business).
		$duplicate_check = self::check_duplicate_review( $business_id, $ip );
		if ( is_wp_error( $duplicate_check ) ) {
			return $duplicate_check;
		}

		// Get author info - logged-in users use account info, anonymous users provide their own.
		$user_id = get_current_user_id();

		if ( $user_id ) {
			// Logged-in user: Use account info for trust/gamification.
			$user = get_userdata( $user_id );

			// Safety check - user could be deleted mid-session.
			if ( ! $user ) {
				return new \WP_Error(
					'invalid_user',
					__( 'User account not found. Please log in again.', 'business-directory' ),
					array( 'status' => 401 )
				);
			}

			$author_email = $user->user_email;

			// Check if user provided a new nickname.
			$new_nickname = sanitize_text_field( $request->get_param( 'bd_display_name' ) );
			if ( ! empty( $new_nickname ) ) {
				// Validate length.
				if ( mb_strlen( $new_nickname ) > self::MAX_NAME_LENGTH ) {
					return new \WP_Error(
						'name_too_long',
						/* translators: %d: maximum character limit */
						sprintf( __( 'Display name must be %d characters or less.', 'business-directory' ), self::MAX_NAME_LENGTH ),
						array( 'status' => 400 )
					);
				}
				// Save the new nickname.
				update_user_meta( $user_id, 'bd_display_name', $new_nickname );
				$author_name = $new_nickname;
			} else {
				// Name priority: BD nickname → WP display_name → user_login
				$bd_nickname = get_user_meta( $user_id, 'bd_display_name', true );
				$author_name = ! empty( $bd_nickname ) ? $bd_nickname : $user->display_name;
			}

			// Sanitize the name.
			$author_name = sanitize_text_field( $author_name );
		} else {
			// Anonymous user: Validate provided info.
			$author_name  = sanitize_text_field( $request->get_param( 'author_name' ) );
			$author_email = sanitize_email( $request->get_param( 'author_email' ) );

			// Validate required fields for anonymous users.
			if ( empty( $author_name ) || empty( $author_email ) ) {
				return new \WP_Error(
					'missing_required',
					__( 'Please fill in your name and email.', 'business-directory' ),
					array( 'status' => 400 )
				);
			}

			// Validate email format.
			if ( ! is_email( $author_email ) ) {
				return new \WP_Error(
					'invalid_email',
					__( 'Please enter a valid email address.', 'business-directory' ),
					array( 'status' => 400 )
				);
			}

			// Validate name length.
			if ( mb_strlen( $author_name ) > self::MAX_NAME_LENGTH ) {
				return new \WP_Error(
					'name_too_long',
					sprintf(
						/* translators: %d: maximum name length */
						__( 'Name must be less than %d characters.', 'business-directory' ),
						self::MAX_NAME_LENGTH
					),
					array( 'status' => 400 )
				);
			}
		}

		// Sanitize optional title.
		$title = sanitize_text_field( $request->get_param( 'title' ) );

		// Sanitize and validate content.
		$content = sanitize_textarea_field( $request->get_param( 'content' ) );

		if ( empty( $content ) ) {
			return new \WP_Error(
				'missing_content',
				__( 'Please write your review.', 'business-directory' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $title ) && mb_strlen( $title ) > self::MAX_TITLE_LENGTH ) {
			return new \WP_Error(
				'title_too_long',
				sprintf(
					/* translators: %d: maximum title length */
					__( 'Title must be less than %d characters.', 'business-directory' ),
					self::MAX_TITLE_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		$content_length = mb_strlen( $content );
		if ( $content_length < self::MIN_CONTENT_LENGTH ) {
			return new \WP_Error(
				'content_too_short',
				sprintf(
					/* translators: %d: minimum content length */
					__( 'Review must be at least %d characters.', 'business-directory' ),
					self::MIN_CONTENT_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		if ( $content_length > self::MAX_CONTENT_LENGTH ) {
			return new \WP_Error(
				'content_too_long',
				sprintf(
					/* translators: %d: maximum content length */
					__( 'Review must be less than %d characters.', 'business-directory' ),
					self::MAX_CONTENT_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		// Handle photo uploads with validation.
		$photo_ids = self::handle_photo_uploads();
		if ( is_wp_error( $photo_ids ) ) {
			return $photo_ids;
		}

		// Build review data.
		$review_data = array(
			'business_id'  => $business_id,
			'user_id'      => $user_id ?: null,
			'rating'       => $rating,
			'author_name'  => $author_name,
			'author_email' => $author_email,
			'title'        => $title,
			'content'      => $content,
			'photo_ids'    => ! empty( $photo_ids ) ? implode( ',', $photo_ids ) : null,
			'ip_address'   => $ip,
		);

		// Insert review.
		$review_id = \BD\DB\ReviewsTable::insert( $review_data );

		if ( ! $review_id ) {
			// Clean up orphaned photo uploads on failure.
			self::cleanup_orphaned_uploads( $photo_ids );

			return new \WP_Error(
				'submission_failed',
				__( 'Failed to save review. Please try again.', 'business-directory' ),
				array( 'status' => 500 )
			);
		}

		// Send notification email.
		\BD\Notifications\Email::notify_new_review( $review_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Thank you! Your review is pending approval.', 'business-directory' ),
			)
		);
	}

	/**
	 * Get sanitized client IP address.
	 *
	 * @return string Sanitized IP address.
	 */
	private static function get_sanitized_ip() {
		$ip = \BD\Security\RateLimit::get_client_ip();

		// Handle comma-separated IPs from proxies - take the first (original client).
		if ( strpos( $ip, ',' ) !== false ) {
			$ip = trim( explode( ',', $ip )[0] );
		}

		// Validate IP format.
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );

		return $ip ?: '0.0.0.0';
	}

	/**
	 * Verify CAPTCHA if enabled.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function verify_captcha( $request ) {
		$site_key = get_option( 'bd_turnstile_site_key' );

		// If CAPTCHA is not configured, skip verification.
		if ( empty( $site_key ) ) {
			return true;
		}

		$turnstile_token = $request->get_param( 'turnstile_token' );

		// If CAPTCHA is enabled but token not provided, reject.
		if ( empty( $turnstile_token ) ) {
			return new \WP_Error(
				'captcha_required',
				__( 'Please complete the CAPTCHA verification.', 'business-directory' ),
				array( 'status' => 400 )
			);
		}

		// Verify the token.
		$captcha_check = \BD\Security\Captcha::verify_turnstile( $turnstile_token );
		if ( is_wp_error( $captcha_check ) ) {
			return $captcha_check;
		}

		return true;
	}

	/**
	 * Check for duplicate review from same user/IP for same business.
	 *
	 * Only blocks if there's an approved or pending review.
	 * Rejected reviews don't block - user can try again.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $ip          Client IP address.
	 * @return true|\WP_Error True if no duplicate, WP_Error if duplicate found.
	 */
	private static function check_duplicate_review( $business_id, $ip ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		$user_id = get_current_user_id();

		// Check by user ID if logged in, otherwise by IP.
		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $reviews_table 
					WHERE business_id = %d AND user_id = %d AND status IN ('approved', 'pending') 
					LIMIT 1",
					$business_id,
					$user_id
				)
			);
		} else {
			// For anonymous users, check by IP within last 30 days.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $reviews_table 
					WHERE business_id = %d AND ip_address = %s AND status IN ('approved', 'pending') 
					AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
					LIMIT 1",
					$business_id,
					$ip
				)
			);
		}

		if ( $existing ) {
			return new \WP_Error(
				'duplicate_review',
				__( 'You have already reviewed this business.', 'business-directory' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Handle photo uploads with security validation.
	 *
	 * @return array|\WP_Error Array of attachment IDs or WP_Error on failure.
	 */
	private static function handle_photo_uploads() {
		if ( empty( $_FILES['photos'] ) ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files      = $_FILES['photos'];
		$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;
		$photo_ids  = array();

		// Limit to 3 photos.
		$max_photos = 3;

		for ( $i = 0; $i < min( $file_count, $max_photos ); $i++ ) {
			// Skip if no file or upload error.
			if ( ! isset( $files['error'][ $i ] ) || $files['error'][ $i ] !== UPLOAD_ERR_OK ) {
				continue;
			}

			// Validate file size (5MB max).
			if ( $files['size'][ $i ] > self::MAX_FILE_SIZE ) {
				// Clean up any previously uploaded photos before returning error.
				self::cleanup_orphaned_uploads( $photo_ids );

				return new \WP_Error(
					'file_too_large',
					sprintf(
						/* translators: %d: maximum file size in MB */
						__( 'File size exceeds the maximum limit of %dMB.', 'business-directory' ),
						self::MAX_FILE_SIZE / ( 1024 * 1024 )
					),
					array( 'status' => 400 )
				);
			}

			// Validate MIME type using actual file content (not client-provided type).
			$file_path = $files['tmp_name'][ $i ];
			$mime_type = self::get_real_mime_type( $file_path );

			if ( ! $mime_type || ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
				// Clean up any previously uploaded photos before returning error.
				self::cleanup_orphaned_uploads( $photo_ids );

				return new \WP_Error(
					'invalid_file_type',
					__( 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.', 'business-directory' ),
					array( 'status' => 400 )
				);
			}

			// Prepare file array for WordPress.
			$file = array(
				'name'     => sanitize_file_name( $files['name'][ $i ] ),
				'type'     => $mime_type, // Use verified MIME type.
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);

			// Use WordPress upload handler.
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			if ( isset( $upload['error'] ) ) {
				// Log error but continue with other files.
				error_log( 'BD Review photo upload failed: ' . $upload['error'] );
				continue;
			}

			// Create attachment.
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $upload['type'],
					'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
					'post_status'    => 'inherit',
				),
				$upload['file']
			);

			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				// Generate attachment metadata.
				$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
				wp_update_attachment_metadata( $attachment_id, $metadata );

				$photo_ids[] = $attachment_id;
			}
		}

		return $photo_ids;
	}

	/**
	 * Get real MIME type of a file using multiple methods.
	 *
	 * @param string $file_path Path to the file.
	 * @return string|false MIME type or false on failure.
	 */
	private static function get_real_mime_type( $file_path ) {
		// Method 1: Use fileinfo extension (preferred).
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );

			if ( $mime_type ) {
				return $mime_type;
			}
		}

		// Method 2: Use getimagesize for images.
		$image_info = @getimagesize( $file_path );
		if ( $image_info && ! empty( $image_info['mime'] ) ) {
			return $image_info['mime'];
		}

		// Method 3: Use WordPress mime_content_type wrapper.
		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type ) {
				return $mime_type;
			}
		}

		return false;
	}

	/**
	 * Clean up orphaned attachment uploads.
	 *
	 * @param array $attachment_ids Array of attachment IDs to delete.
	 */
	private static function cleanup_orphaned_uploads( $attachment_ids ) {
		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return;
		}

		foreach ( $attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}

add_action( 'rest_api_init', array( 'BD\REST\SubmitReviewController', 'register' ) );
