<?php


namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Gamification\ActivityTracker;

class RegistrationHandler {


	public function __construct() {
		add_action( 'admin_post_nopriv_ll_register_user', array( $this, 'handle_registration' ) );
		add_action( 'user_register', array( $this, 'claim_past_reviews' ), 10, 1 );
		add_action( 'wp_login', array( $this, 'check_unclaimed_reviews' ), 10, 2 );
	}

	/**
	 * Handle custom registration form submission
	 */
	public function handle_registration() {
		// Verify nonce
		if ( ! isset( $_POST['ll_register_nonce'] ) || ! wp_verify_nonce( $_POST['ll_register_nonce'], 'll_register_user' ) ) {
			wp_die( 'Security check failed' );
		}

		// Sanitize inputs
		$username = sanitize_user( $_POST['username'] );
		$email    = sanitize_email( $_POST['email'] );
		$password = wp_unslash( $_POST['password'] );

		// Validate
		$errors = array();

		if ( username_exists( $username ) ) {
			$errors[] = 'Username already exists';
		}

		if ( ! is_email( $email ) ) {
			$errors[] = 'Invalid email address';
		}

		if ( email_exists( $email ) ) {
			$errors[] = 'Email already registered';
		}

		if ( strlen( $password ) < 8 ) {
			$errors[] = 'Password must be at least 8 characters';
		}

		// If errors, redirect back with error
		if ( ! empty( $errors ) ) {
			$error_msg = implode( ', ', $errors );
			wp_redirect(
				add_query_arg(
					array(
						'registration' => 'failed',
						'error'        => urlencode( $error_msg ),
					),
					wp_get_referer()
				)
			);
			exit;
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'registration' => 'failed',
						'error'        => urlencode( $user_id->get_error_message() ),
					),
					wp_get_referer()
				)
			);
			exit;
		}

		// Set user role
		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );

		// Auto login
		// Auto login
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Claim past reviews happens automatically via 'user_register' hook
		$claimed_count = get_user_meta( $user_id, 'll_claimed_reviews', true );

		// Redirect with claimed count
		if ( $claimed_count ) {
			wp_redirect( home_url( '/register?registered=success&claimed=' . $claimed_count ) );
		} else {
			wp_redirect( home_url( '/profile?registered=success' ) );
		}

		exit;
	}

	/**
	 * Claim past reviews when user registers
	 * Triggered on user_register hook
	 */
	public function claim_past_reviews( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Find reviews with matching email but no user_id
		$unclaimed_reviews = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT id, business_id, content, photo_ids, status
            FROM $reviews_table
            WHERE author_email = %s
            AND (user_id IS NULL OR user_id = 0)
        ",
				$user->user_email
			),
			ARRAY_A
		);

		if ( empty( $unclaimed_reviews ) ) {
			return;
		}

		$claimed_count = 0;

		foreach ( $unclaimed_reviews as $review ) {
			// Update review with user_id
			$wpdb->update(
				$reviews_table,
				array( 'user_id' => $user_id ),
				array( 'id' => $review['id'] ),
				array( '%d' ),
				array( '%d' )
			);

			// Award points if review was already approved
			if ( $review['status'] === 'approved' ) {
				// Base points for review
				ActivityTracker::track( $user_id, 'review_created', $review['id'] );

				// Bonus for photo
				if ( ! empty( $review['photo_ids'] ) ) {
					ActivityTracker::track( $user_id, 'review_with_photo', $review['id'] );
				}

				// Bonus for detailed review
				if ( strlen( $review['content'] ) > 100 ) {
					ActivityTracker::track( $user_id, 'review_detailed', $review['id'] );
				}

				++$claimed_count;
			}
		}

		// Store claimed count for welcome message
		if ( $claimed_count > 0 ) {
			update_user_meta( $user_id, 'll_claimed_reviews', $claimed_count );
			update_user_meta( $user_id, 'll_first_login_bonus', true );
		}
	}

	/**
	 * Check for unclaimed reviews on login
	 * Show notification if user has pending reviews to claim
	 */
	public function check_unclaimed_reviews( $user_login, $user ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Check if there are unclaimed reviews for this email
		$unclaimed_count = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*)
            FROM $reviews_table
            WHERE author_email = %s
            AND (user_id IS NULL OR user_id = 0)
        ",
				$user->user_email
			)
		);

		if ( $unclaimed_count > 0 ) {
			// Claim them now
			$this->claim_past_reviews( $user->ID );

			// Set flag to show welcome message
			update_user_meta( $user->ID, 'll_show_claim_notice', $unclaimed_count );
		}
	}

	/**
	 * Display claimed reviews notice after registration
	 */
	public static function show_claim_notice() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id       = get_current_user_id();
		$claimed_count = get_user_meta( $user_id, 'll_claimed_reviews', true );
		$show_notice   = get_user_meta( $user_id, 'll_show_claim_notice', true );

		if ( $claimed_count || $show_notice ) {
			$count = ! empty( $claimed_count ) ? $claimed_count : $show_notice;
			?>
			<div class="ll-claim-notice">
				<div class="ll-claim-notice-inner">
					<span class="ll-claim-icon">🎉</span>
					<div class="ll-claim-text">
						<strong>Welcome to Love Livermore!</strong>
						<p>We found and claimed <?php echo $count; ?> review<?php echo $count > 1 ? 's' : ''; ?> you wrote. You've earned your first <?php echo $count * 10; ?> points!</p>
					</div>
					<button class="ll-claim-dismiss" onclick="this.parentElement.parentElement.remove()">×</button>
				</div>
			</div>

			<style>
				.ll-claim-notice {
					position: fixed;
					top: 20px;
					right: 20px;
					z-index: 9999;
					animation: slideInRight 0.3s ease-out;
				}

				@keyframes slideInRight {
					from {
						transform: translateX(400px);
						opacity: 0;
					}

					to {
						transform: translateX(0);
						opacity: 1;
					}
				}

				.ll-claim-notice-inner {
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					color: white;
					padding: 20px 24px;
					border-radius: 12px;
					box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
					display: flex;
					align-items: center;
					gap: 16px;
					max-width: 400px;
				}

				.ll-claim-icon {
					font-size: 32px;
				}

				.ll-claim-text strong {
					display: block;
					font-size: 16px;
					margin-bottom: 4px;
				}

				.ll-claim-text p {
					font-size: 14px;
					margin: 0;
					opacity: 0.95;
				}

				.ll-claim-dismiss {
					background: rgba(255, 255, 255, 0.2);
					border: none;
					color: white;
					width: 28px;
					height: 28px;
					border-radius: 50%;
					cursor: pointer;
					font-size: 20px;
					line-height: 1;
					transition: all 0.2s;
				}

				.ll-claim-dismiss:hover {
					background: rgba(255, 255, 255, 0.3);
				}
			</style>

			<script>
				setTimeout(function() {
					const notice = document.querySelector('.ll-claim-notice');
					if (notice) notice.remove();
				}, 8000);
			</script>
			<?php

			// Clear the meta so it only shows once
			delete_user_meta( $user_id, 'll_claimed_reviews' );
			delete_user_meta( $user_id, 'll_show_claim_notice' );
		}
	}
}
