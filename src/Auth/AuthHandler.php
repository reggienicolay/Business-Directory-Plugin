<?php
/**
 * Authentication Handler
 *
 * Handles AJAX login, registration, and password reset.
 *
 * @package BusinessDirectory
 * @subpackage Auth
 * @version 1.1.0
 */

namespace BD\Auth;

use BD\Gamification\ActivityTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AuthHandler
 */
class AuthHandler {

	/**
	 * Initialize handlers
	 */
	public static function init() {
		// AJAX login - need both for cached page handling.
		add_action( 'wp_ajax_nopriv_bd_ajax_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'wp_ajax_bd_ajax_login', array( __CLASS__, 'handle_login' ) );

		// AJAX register - need both for cached page handling.
		add_action( 'wp_ajax_nopriv_bd_ajax_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'wp_ajax_bd_ajax_register', array( __CLASS__, 'handle_register' ) );

		// AJAX password reset - need both for cached page handling.
		add_action( 'wp_ajax_nopriv_bd_ajax_reset_password', array( __CLASS__, 'handle_reset_password' ) );
		add_action( 'wp_ajax_bd_ajax_reset_password', array( __CLASS__, 'handle_reset_password' ) );

		// AJAX logout (for logged-in users).
		add_action( 'wp_ajax_bd_ajax_logout', array( __CLASS__, 'handle_logout' ) );

		// Auth status check - for header updates on cached pages.
		add_action( 'wp_ajax_bd_check_auth', array( __CLASS__, 'check_auth_status' ) );
		add_action( 'wp_ajax_nopriv_bd_check_auth', array( __CLASS__, 'check_auth_status' ) );

		// Track login for multisite.
		add_action( 'wp_login', array( __CLASS__, 'track_login' ), 10, 2 );

		// Award first login points.
		add_action( 'wp_login', array( __CLASS__, 'maybe_award_first_login' ), 20, 2 );

		// Claim past reviews on registration.
		add_action( 'user_register', array( __CLASS__, 'claim_past_reviews' ), 10, 1 );
	}

	/**
	 * Check authentication status (for frontend header on cached pages)
	 */
	public static function check_auth_status() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			// Get user display name (first name or display name).
			$display_name = $user->first_name ? $user->first_name : $user->display_name;

			// Truncate if too long.
			if ( strlen( $display_name ) > 15 ) {
				$display_name = substr( $display_name, 0, 12 ) . '...';
			}

			// Check if user has claimed businesses.
			$has_businesses = self::user_has_businesses( $user->ID );

			wp_send_json_success(
				array(
					'logged_in' => true,
					'user'      => array(
						'id'             => $user->ID,
						'display_name'   => $display_name,
						'full_name'      => $user->display_name,
						'email'          => $user->user_email,
						'avatar'         => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
						'is_admin'       => current_user_can( 'manage_options' ),
						'has_businesses' => $has_businesses,
					),
					'urls'      => array(
						'profile'        => home_url( '/my-profile/' ),
						'lists'          => home_url( '/my-lists/' ),
						'edit_listing'   => home_url( '/edit-listing/' ),
						'business_tools' => home_url( '/business-tools/' ),
						'admin'          => admin_url(),
						'logout'         => wp_logout_url( home_url() ),
					),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'logged_in' => false,
				)
			);
		}
	}

	/**
	 * Check if user has claimed businesses
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function user_has_businesses( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $claims_table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$claims_table} WHERE user_id = %d AND status = 'approved'",
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Handle AJAX login
	 */
	public static function handle_login() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_auth_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page.', 'business-directory' ),
				)
			);
		}

		$username = sanitize_user( $_POST['username'] ?? '' );
		$password = $_POST['password'] ?? '';
		$remember = ! empty( $_POST['remember'] );

		// Validate inputs.
		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your username and password.', 'business-directory' ),
				)
			);
		}

		// Attempt login.
		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => $remember,
			)
		);

		if ( is_wp_error( $user ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid username or password.', 'business-directory' ),
				)
			);
		}

		// Explicitly set auth cookie (wp_signon may not set it properly in AJAX context).
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );

		// Success.
		wp_send_json_success(
			array(
				'message'  => __( 'Login successful! Redirecting...', 'business-directory' ),
				'redirect' => self::get_redirect_url(),
				'user'     => array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
				),
			)
		);
	}

	/**
	 * Handle AJAX registration
	 */
	public static function handle_register() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_auth_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page.', 'business-directory' ),
				)
			);
		}

		// Check if registration is allowed.
		if ( ! get_option( 'users_can_register' ) && ! is_multisite() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Registration is currently disabled.', 'business-directory' ),
				)
			);
		}

		$username = sanitize_user( $_POST['username'] ?? '' );
		$email    = sanitize_email( $_POST['email'] ?? '' );
		$password = $_POST['password'] ?? '';
		$city     = sanitize_text_field( $_POST['city'] ?? '' );
		$terms    = ! empty( $_POST['terms'] );

		// Validate inputs.
		$errors = array();

		if ( empty( $username ) ) {
			$errors[] = __( 'Username is required.', 'business-directory' );
		} elseif ( strlen( $username ) < 3 ) {
			$errors[] = __( 'Username must be at least 3 characters.', 'business-directory' );
		} elseif ( username_exists( $username ) ) {
			$errors[] = __( 'Username already exists.', 'business-directory' );
		} elseif ( ! validate_username( $username ) ) {
			$errors[] = __( 'Username contains invalid characters.', 'business-directory' );
		}

		if ( empty( $email ) ) {
			$errors[] = __( 'Email is required.', 'business-directory' );
		} elseif ( ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'business-directory' );
		} elseif ( email_exists( $email ) ) {
			$errors[] = __( 'Email already registered.', 'business-directory' );
		}

		if ( empty( $password ) ) {
			$errors[] = __( 'Password is required.', 'business-directory' );
		} elseif ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters.', 'business-directory' );
		}

		if ( ! $terms ) {
			$errors[] = __( 'You must agree to the Terms of Service.', 'business-directory' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( '<br>', $errors ),
				)
			);
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array(
					'message' => $user_id->get_error_message(),
				)
			);
		}

		// Set role.
		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );

		// Store multisite meta.
		update_user_meta( $user_id, 'bd_signup_site_id', get_current_blog_id() );
		update_user_meta( $user_id, 'bd_signup_date', current_time( 'mysql' ) );

		// Store city if provided.
		if ( ! empty( $city ) ) {
			update_user_meta( $user_id, 'bd_signup_city', $city );
			update_user_meta( $user_id, 'bd_city', $city );
		}

		// Auto login.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Check for claimed reviews.
		$claimed_count = get_user_meta( $user_id, 'bd_claimed_reviews', true );

		// Build success message.
		$message = __( 'Account created successfully!', 'business-directory' );
		if ( $claimed_count ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of claimed reviews */
				__( 'We found %d review(s) you wrote and added them to your profile!', 'business-directory' ),
				$claimed_count
			);
		}

		wp_send_json_success(
			array(
				'message'  => $message,
				'redirect' => self::get_redirect_url( home_url( '/my-profile/?registered=1' ) ),
				'user'     => array(
					'id'           => $user_id,
					'display_name' => $user->display_name,
					'avatar'       => get_avatar_url( $user_id, array( 'size' => 32 ) ),
				),
			)
		);
	}

	/**
	 * Handle password reset request
	 */
	public static function handle_reset_password() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_auth_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page.', 'business-directory' ),
				)
			);
		}

		$user_login = sanitize_text_field( $_POST['user_login'] ?? '' );

		if ( empty( $user_login ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your username or email.', 'business-directory' ),
				)
			);
		}

		// Find user.
		if ( is_email( $user_login ) ) {
			$user = get_user_by( 'email', $user_login );
		} else {
			$user = get_user_by( 'login', $user_login );
		}

		// Always show success to prevent user enumeration.
		if ( ! $user ) {
			wp_send_json_success(
				array(
					'message' => __( 'If an account exists, a password reset link has been sent.', 'business-directory' ),
				)
			);
		}

		// Generate reset key.
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unable to generate reset link. Please try again.', 'business-directory' ),
				)
			);
		}

		// Build reset URL (frontend).
		$reset_url = add_query_arg(
			array(
				'tab'   => 'reset',
				'key'   => $key,
				'login' => rawurlencode( $user->user_login ),
			),
			home_url( '/login/' )
		);

		// Send email.
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Password Reset', 'business-directory' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: username, 2: site name, 3: reset URL */
			// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText
			__(
				"Hi %1\$s,\n\n" .
				"Someone requested a password reset for your account on %2\$s.\n\n" .
				"If this was you, click the link below to reset your password:\n%3\$s\n\n" .
				"If you didn't request this, you can safely ignore this email.\n\n" .
				"This link will expire in 24 hours.\n\n" .
				"Thanks,\n%2\$s Team",
				'business-directory'
			),
			// phpcs:enable WordPress.WP.I18n.NonSingularStringLiteralText
			$user->display_name,
			get_bloginfo( 'name' ),
			$reset_url
		);

		wp_mail( $user->user_email, $subject, $message );

		wp_send_json_success(
			array(
				'message' => __( 'If an account exists, a password reset link has been sent.', 'business-directory' ),
			)
		);
	}

	/**
	 * Handle AJAX logout
	 */
	public static function handle_logout() {
		wp_logout();

		wp_send_json_success(
			array(
				'message'  => __( 'You have been logged out.', 'business-directory' ),
				'redirect' => home_url(),
			)
		);
	}

	/**
	 * Track login for multisite
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public static function track_login( $user_login, $user ) {
		update_user_meta( $user->ID, 'bd_last_active_site', get_current_blog_id() );
		update_user_meta( $user->ID, 'bd_last_login', current_time( 'mysql' ) );
	}

	/**
	 * Award points for first login
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public static function maybe_award_first_login( $user_login, $user ) {
		$first_login_awarded = get_user_meta( $user->ID, 'bd_first_login_points', true );

		if ( ! $first_login_awarded && class_exists( '\BD\Gamification\ActivityTracker' ) ) {
			ActivityTracker::track( $user->ID, 'first_login', 0 );
			update_user_meta( $user->ID, 'bd_first_login_points', current_time( 'mysql' ) );
		}
	}

	/**
	 * Claim past reviews when user registers
	 *
	 * @param int $user_id User ID.
	 */
	public static function claim_past_reviews( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);

		if ( ! $table_exists ) {
			return;
		}

		// Find reviews with matching email but no user_id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$unclaimed_reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, business_id, content, photo_ids, status
				FROM {$reviews_table}
				WHERE author_email = %s
				AND (user_id IS NULL OR user_id = 0)",
				$user->user_email
			),
			ARRAY_A
		);

		if ( empty( $unclaimed_reviews ) ) {
			return;
		}

		$claimed_count = 0;

		foreach ( $unclaimed_reviews as $review ) {
			// Update review with user_id.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$reviews_table,
				array( 'user_id' => $user_id ),
				array( 'id' => $review['id'] ),
				array( '%d' ),
				array( '%d' )
			);

			// Award points if review was already approved.
			if ( 'approved' === $review['status'] && class_exists( '\BD\Gamification\ActivityTracker' ) ) {
				ActivityTracker::track( $user_id, 'review_created', $review['id'] );

				if ( ! empty( $review['photo_ids'] ) ) {
					ActivityTracker::track( $user_id, 'review_with_photo', $review['id'] );
				}

				if ( strlen( $review['content'] ) > 100 ) {
					ActivityTracker::track( $user_id, 'review_detailed', $review['id'] );
				}

				++$claimed_count;
			}
		}

		if ( $claimed_count > 0 ) {
			update_user_meta( $user_id, 'bd_claimed_reviews', $claimed_count );
		}
	}

	/**
	 * Get redirect URL after login/register
	 *
	 * @param string $default Default URL.
	 * @return string
	 */
	private static function get_redirect_url( $default = '' ) {
		// Check for redirect_to parameter.
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$redirect = esc_url_raw( $_POST['redirect_to'] );
			if ( wp_validate_redirect( $redirect ) ) {
				return $redirect;
			}
		}

		// Check referer.
		$referer = wp_get_referer();
		if ( $referer && strpos( $referer, '/login' ) === false ) {
			return $referer;
		}

		// Default.
		return $default ? $default : home_url();
	}
}
