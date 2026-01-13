<?php
/**
 * Profile Editor
 *
 * Handles AJAX profile updates for frontend users.
 * Updated with public profile visibility settings.
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 * @version 1.1.1
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProfileEditor
 */
class ProfileEditor {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'wp_ajax_bd_update_profile', array( __CLASS__, 'handle_update' ) );
		add_action( 'wp_ajax_bd_request_email_change', array( __CLASS__, 'handle_email_change_request' ) );
	}

	/**
	 * Handle profile update AJAX request
	 */
	public static function handle_update() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_profile_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page.', 'business-directory' ),
				)
			);
			return; // Explicit return for clarity
		}

		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to update your profile.', 'business-directory' ),
				)
			);
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			wp_send_json_error(
				array(
					'message' => __( 'User not found.', 'business-directory' ),
				)
			);
			return;
		}

		$errors  = array();
		$updated = array();

		// First Name.
		if ( isset( $_POST['first_name'] ) ) {
			$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
			update_user_meta( $user_id, 'first_name', $first_name );
			$updated[] = 'first_name';
		}

		// Last Name.
		if ( isset( $_POST['last_name'] ) ) {
			$last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
			update_user_meta( $user_id, 'last_name', $last_name );
			$updated[] = 'last_name';
		}

		// Display Name.
		if ( isset( $_POST['display_name'] ) ) {
			$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ) );
			if ( ! empty( $display_name ) ) {
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => $display_name,
					)
				);
				$updated[] = 'display_name';
			}
		}

		// Bio/About.
		if ( isset( $_POST['bio'] ) ) {
			$bio = sanitize_textarea_field( wp_unslash( $_POST['bio'] ) );
			update_user_meta( $user_id, 'description', $bio );
			$updated[] = 'bio';
		}

		// Phone.
		if ( isset( $_POST['phone'] ) ) {
			$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ) );
			// Basic phone validation - allow numbers, spaces, dashes, parentheses, plus.
			$phone = preg_replace( '/[^0-9\s\-\(\)\+]/', '', $phone );
			update_user_meta( $user_id, 'bd_phone', $phone );
			$updated[] = 'phone';
		}

		// City (same field as registration).
		if ( isset( $_POST['city'] ) ) {
			$city = sanitize_text_field( wp_unslash( $_POST['city'] ) );
			update_user_meta( $user_id, 'bd_city', $city );
			$updated[] = 'city';
		}

		// Website.
		if ( isset( $_POST['website'] ) ) {
			$website = esc_url_raw( wp_unslash( $_POST['website'] ) );
			wp_update_user(
				array(
					'ID'       => $user_id,
					'user_url' => $website,
				)
			);
			$updated[] = 'website';
		}

		// Social Media - Instagram.
		if ( isset( $_POST['instagram'] ) ) {
			$instagram = sanitize_text_field( wp_unslash( $_POST['instagram'] ) );
			$instagram = self::clean_social_handle( $instagram, 'instagram' );
			update_user_meta( $user_id, 'bd_instagram', $instagram );
			$updated[] = 'instagram';
		}

		// Social Media - Facebook.
		if ( isset( $_POST['facebook'] ) ) {
			$facebook = esc_url_raw( wp_unslash( $_POST['facebook'] ) );
			update_user_meta( $user_id, 'bd_facebook', $facebook );
			$updated[] = 'facebook';
		}

		// Social Media - Twitter/X.
		if ( isset( $_POST['twitter'] ) ) {
			$twitter = sanitize_text_field( wp_unslash( $_POST['twitter'] ) );
			$twitter = self::clean_social_handle( $twitter, 'twitter' );
			update_user_meta( $user_id, 'bd_twitter', $twitter );
			$updated[] = 'twitter';
		}

		// Social Media - LinkedIn.
		if ( isset( $_POST['linkedin'] ) ) {
			$linkedin = esc_url_raw( wp_unslash( $_POST['linkedin'] ) );
			update_user_meta( $user_id, 'bd_linkedin', $linkedin );
			$updated[] = 'linkedin';
		}

		// Public Profile Visibility.
		if ( isset( $_POST['profile_visibility'] ) ) {
			$visibility = sanitize_text_field( wp_unslash( $_POST['profile_visibility'] ) );
			// Validate allowed values.
			if ( in_array( $visibility, array( 'public', 'members', 'private' ), true ) ) {
				update_user_meta( $user_id, 'bd_profile_visibility', $visibility );
				$updated[] = 'profile_visibility';
			}
		}

		// FIX: Only process section toggles if the public profile section was included
		// Check for hidden field that indicates this section was submitted
		if ( isset( $_POST['public_profile_section_submitted'] ) ) {
			// Process checkboxes - unchecked means not present in POST
			$show_badges  = isset( $_POST['profile_show_badges'] ) ? 1 : 0;
			$show_reviews = isset( $_POST['profile_show_reviews'] ) ? 1 : 0;
			$show_lists   = isset( $_POST['profile_show_lists'] ) ? 1 : 0;
			$show_stats   = isset( $_POST['profile_show_stats'] ) ? 1 : 0;

			update_user_meta( $user_id, 'bd_profile_show_badges', $show_badges );
			update_user_meta( $user_id, 'bd_profile_show_reviews', $show_reviews );
			update_user_meta( $user_id, 'bd_profile_show_lists', $show_lists );
			update_user_meta( $user_id, 'bd_profile_show_stats', $show_stats );

			$updated[] = 'section_visibility';
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( '<br>', $errors ),
				)
			);
			return;
		}

		wp_send_json_success(
			array(
				'message' => __( 'Profile updated successfully!', 'business-directory' ),
				'updated' => $updated,
			)
		);
	}

	/**
	 * Handle email change request
	 *
	 * Email changes require verification for security.
	 */
	public static function handle_email_change_request() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_profile_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page.', 'business-directory' ),
				)
			);
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in.', 'business-directory' ),
				)
			);
			return;
		}

		$user_id   = get_current_user_id();
		$user      = get_userdata( $user_id );
		$new_email = isset( $_POST['new_email'] ) ? sanitize_email( wp_unslash( $_POST['new_email'] ) ) : '';

		// Validate email.
		if ( empty( $new_email ) || ! is_email( $new_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address.', 'business-directory' ),
				)
			);
			return;
		}

		// Check if same as current.
		if ( $new_email === $user->user_email ) {
			wp_send_json_error(
				array(
					'message' => __( 'This is already your email address.', 'business-directory' ),
				)
			);
			return;
		}

		// Check if email exists.
		if ( email_exists( $new_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This email address is already in use.', 'business-directory' ),
				)
			);
			return;
		}

		// Generate verification key.
		$key = wp_generate_password( 20, false );
		update_user_meta( $user_id, 'bd_pending_email', $new_email );
		update_user_meta( $user_id, 'bd_email_change_key', $key );
		update_user_meta( $user_id, 'bd_email_change_expires', time() + DAY_IN_SECONDS );

		// Build verification URL.
		$verify_url = add_query_arg(
			array(
				'action'  => 'bd_verify_email',
				'user_id' => $user_id,
				'key'     => $key,
			),
			home_url( '/my-profile/' )
		);

		// Send verification email.
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Verify Your Email Change', 'business-directory' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: display name, 2: site name, 3: new email, 4: verify URL */
			__(
				"Hi %1\$s,\n\n" .
				"You requested to change your email address on %2\$s.\n\n" .
				"New email: %3\$s\n\n" .
				"Click the link below to verify this change:\n%4\$s\n\n" .
				"This link expires in 24 hours.\n\n" .
				"If you didn't request this change, please ignore this email.\n\n" .
				"Thanks,\n%2\$s Team",
				'business-directory'
			),
			$user->display_name,
			get_bloginfo( 'name' ),
			$new_email,
			$verify_url
		);

		$sent = wp_mail( $new_email, $subject, $message );

		if ( ! $sent ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to send verification email. Please try again.', 'business-directory' ),
				)
			);
			return;
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: email address */
					__( 'Verification email sent to %s. Please check your inbox.', 'business-directory' ),
					$new_email
				),
			)
		);
	}

	/**
	 * Handle email verification from URL
	 */
	public static function maybe_verify_email() {
		if ( ! isset( $_GET['action'] ) || 'bd_verify_email' !== $_GET['action'] ) {
			return;
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( ! $user_id || ! $key ) {
			return;
		}

		// Verify key and expiration.
		$stored_key = get_user_meta( $user_id, 'bd_email_change_key', true );
		$expires    = get_user_meta( $user_id, 'bd_email_change_expires', true );
		$new_email  = get_user_meta( $user_id, 'bd_pending_email', true );

		if ( $key !== $stored_key ) {
			wp_die( esc_html__( 'Invalid verification link.', 'business-directory' ) );
		}

		if ( time() > (int) $expires ) {
			// Clean up expired data.
			delete_user_meta( $user_id, 'bd_email_change_key' );
			delete_user_meta( $user_id, 'bd_email_change_expires' );
			delete_user_meta( $user_id, 'bd_pending_email' );
			wp_die( esc_html__( 'This verification link has expired. Please request a new one.', 'business-directory' ) );
		}

		// Update the email.
		$result = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $new_email,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html__( 'Failed to update email address.', 'business-directory' ) );
		}

		// Clean up.
		delete_user_meta( $user_id, 'bd_email_change_key' );
		delete_user_meta( $user_id, 'bd_email_change_expires' );
		delete_user_meta( $user_id, 'bd_pending_email' );

		// Redirect back to profile with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'email_updated' => '1',
				),
				home_url( '/my-profile/' )
			)
		);
		exit;
	}

	/**
	 * Clean social media handle
	 *
	 * @param string $value Raw input.
	 * @param string $type Social network type.
	 * @return string Cleaned handle.
	 */
	private static function clean_social_handle( $value, $type ) {
		if ( empty( $value ) ) {
			return '';
		}

		switch ( $type ) {
			case 'instagram':
				// Remove @ if present.
				$value = ltrim( $value, '@' );
				// Extract from URL if needed.
				if ( strpos( $value, 'instagram.com' ) !== false ) {
					$value = preg_replace( '#https?://(www\.)?instagram\.com/#i', '', $value );
					$value = trim( $value, '/' );
				}
				break;

			case 'twitter':
				// Remove @ if present.
				$value = ltrim( $value, '@' );
				// Extract from URL if needed.
				if ( strpos( $value, 'twitter.com' ) !== false || strpos( $value, 'x.com' ) !== false ) {
					$value = preg_replace( '#https?://(www\.)?(twitter|x)\.com/#i', '', $value );
					$value = trim( $value, '/' );
				}
				break;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Get user profile data for form
	 *
	 * @param int $user_id User ID.
	 * @return array Profile data.
	 */
	public static function get_profile_data( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		// Get visibility settings with defaults.
		$visibility = get_user_meta( $user_id, 'bd_profile_visibility', true );
		if ( empty( $visibility ) ) {
			$visibility = 'public';
		}

		// Section visibility - default all to true (1) if not set.
		$show_badges  = get_user_meta( $user_id, 'bd_profile_show_badges', true );
		$show_reviews = get_user_meta( $user_id, 'bd_profile_show_reviews', true );
		$show_lists   = get_user_meta( $user_id, 'bd_profile_show_lists', true );
		$show_stats   = get_user_meta( $user_id, 'bd_profile_show_stats', true );

		return array(
			'first_name'         => $user->first_name,
			'last_name'          => $user->last_name,
			'display_name'       => $user->display_name,
			'email'              => $user->user_email,
			'bio'                => get_user_meta( $user_id, 'description', true ),
			'phone'              => get_user_meta( $user_id, 'bd_phone', true ),
			'city'               => get_user_meta( $user_id, 'bd_city', true ),
			'website'            => $user->user_url,
			'instagram'          => get_user_meta( $user_id, 'bd_instagram', true ),
			'facebook'           => get_user_meta( $user_id, 'bd_facebook', true ),
			'twitter'            => get_user_meta( $user_id, 'bd_twitter', true ),
			'linkedin'           => get_user_meta( $user_id, 'bd_linkedin', true ),
			'profile_visibility' => $visibility,
			'show_badges'        => '' === $show_badges ? true : (bool) $show_badges,
			'show_reviews'       => '' === $show_reviews ? true : (bool) $show_reviews,
			'show_lists'         => '' === $show_lists ? true : (bool) $show_lists,
			'show_stats'         => '' === $show_stats ? true : (bool) $show_stats,
		);
	}

	/**
	 * Render edit profile form
	 *
	 * @param int $user_id User ID.
	 * @return string HTML.
	 */
	public static function render_edit_form( $user_id ) {
		$data = self::get_profile_data( $user_id );
		$user = get_userdata( $user_id );

		// Get public profile URL.
		$public_profile_url = home_url( '/profile/' . $user->user_nicename . '/' );

		// Get areas for city dropdown (same as registration).
		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		ob_start();
		?>
		<div class="bd-profile-card bd-edit-profile-card" id="bd-edit-profile-section" style="display: none;">
			<div class="bd-edit-profile-header">
				<h2><i class="fa-solid fa-user-pen"></i> <?php esc_html_e( 'Edit Profile', 'business-directory' ); ?></h2>
				<button type="button" class="bd-btn bd-btn-secondary bd-btn-sm bd-cancel-edit">
					<i class="fa-solid fa-times"></i> <?php esc_html_e( 'Cancel', 'business-directory' ); ?>
				</button>
			</div>

			<div class="bd-edit-profile-messages"></div>

			<form id="bd-edit-profile-form" class="bd-edit-profile-form">
				<input type="hidden" name="action" value="bd_update_profile">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_profile_nonce' ) ); ?>">
				<!-- FIX: Hidden field to indicate public profile section was submitted -->
				<input type="hidden" name="public_profile_section_submitted" value="1">

				<!-- Public Profile Settings -->
				<div class="bd-form-section bd-public-profile-settings">
					<h3><i class="fa-solid fa-globe"></i> <?php esc_html_e( 'Public Profile', 'business-directory' ); ?></h3>

					<div class="bd-public-profile-url-box">
						<label><?php esc_html_e( 'Your Profile Link', 'business-directory' ); ?></label>
						<div class="bd-url-copy-wrapper">
							<input type="text" 
								id="bd-public-profile-url" 
								value="<?php echo esc_url( $public_profile_url ); ?>" 
								readonly 
								class="bd-input-readonly">
							<button type="button" class="bd-btn bd-btn-secondary bd-btn-sm bd-copy-url-btn" data-url="<?php echo esc_url( $public_profile_url ); ?>">
								<i class="fa-solid fa-copy"></i>
								<span><?php esc_html_e( 'Copy', 'business-directory' ); ?></span>
							</button>
							<a href="<?php echo esc_url( $public_profile_url ); ?>" target="_blank" class="bd-btn bd-btn-secondary bd-btn-sm" title="<?php esc_attr_e( 'Preview', 'business-directory' ); ?>">
								<i class="fa-solid fa-external-link-alt"></i>
							</a>
						</div>
					</div>

					<div class="bd-form-group bd-visibility-options">
						<label><?php esc_html_e( 'Profile Visibility', 'business-directory' ); ?></label>
						<div class="bd-radio-group">
							<label class="bd-radio-option">
								<input type="radio" name="profile_visibility" value="public" <?php checked( $data['profile_visibility'], 'public' ); ?>>
								<span class="bd-radio-icon"><i class="fa-solid fa-globe"></i></span>
								<span class="bd-radio-content">
									<span class="bd-radio-label"><?php esc_html_e( 'Public', 'business-directory' ); ?></span>
									<span class="bd-radio-desc"><?php esc_html_e( 'Anyone can view your profile', 'business-directory' ); ?></span>
								</span>
							</label>
							<label class="bd-radio-option">
								<input type="radio" name="profile_visibility" value="members" <?php checked( $data['profile_visibility'], 'members' ); ?>>
								<span class="bd-radio-icon"><i class="fa-solid fa-users"></i></span>
								<span class="bd-radio-content">
									<span class="bd-radio-label"><?php esc_html_e( 'Members Only', 'business-directory' ); ?></span>
									<span class="bd-radio-desc"><?php esc_html_e( 'Only logged-in members can view', 'business-directory' ); ?></span>
								</span>
							</label>
							<label class="bd-radio-option">
								<input type="radio" name="profile_visibility" value="private" <?php checked( $data['profile_visibility'], 'private' ); ?>>
								<span class="bd-radio-icon"><i class="fa-solid fa-lock"></i></span>
								<span class="bd-radio-content">
									<span class="bd-radio-label"><?php esc_html_e( 'Private', 'business-directory' ); ?></span>
									<span class="bd-radio-desc"><?php esc_html_e( 'Only you can view your profile', 'business-directory' ); ?></span>
								</span>
							</label>
						</div>
					</div>

					<div class="bd-form-group bd-section-toggles">
						<label><?php esc_html_e( 'Show on Public Profile', 'business-directory' ); ?></label>
						<div class="bd-checkbox-group">
							<label class="bd-checkbox-option">
								<input type="checkbox" name="profile_show_badges" value="1" <?php checked( $data['show_badges'] ); ?>>
								<span class="bd-checkbox-label"><i class="fa-solid fa-medal"></i> <?php esc_html_e( 'Badges', 'business-directory' ); ?></span>
							</label>
							<label class="bd-checkbox-option">
								<input type="checkbox" name="profile_show_reviews" value="1" <?php checked( $data['show_reviews'] ); ?>>
								<span class="bd-checkbox-label"><i class="fa-solid fa-star"></i> <?php esc_html_e( 'Reviews', 'business-directory' ); ?></span>
							</label>
							<label class="bd-checkbox-option">
								<input type="checkbox" name="profile_show_lists" value="1" <?php checked( $data['show_lists'] ); ?>>
								<span class="bd-checkbox-label"><i class="fa-solid fa-list"></i> <?php esc_html_e( 'Lists', 'business-directory' ); ?></span>
							</label>
							<label class="bd-checkbox-option">
								<input type="checkbox" name="profile_show_stats" value="1" <?php checked( $data['show_stats'] ); ?>>
								<span class="bd-checkbox-label"><i class="fa-solid fa-chart-simple"></i> <?php esc_html_e( 'Stats', 'business-directory' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Personal Information -->
				<div class="bd-form-section">
					<h3><?php esc_html_e( 'Personal Information', 'business-directory' ); ?></h3>

					<div class="bd-form-row bd-form-row-2col">
						<div class="bd-form-group">
							<label for="bd-first-name"><?php esc_html_e( 'First Name', 'business-directory' ); ?></label>
							<input type="text" id="bd-first-name" name="first_name" 
								value="<?php echo esc_attr( $data['first_name'] ); ?>"
								placeholder="<?php esc_attr_e( 'Your first name', 'business-directory' ); ?>">
						</div>
						<div class="bd-form-group">
							<label for="bd-last-name"><?php esc_html_e( 'Last Name', 'business-directory' ); ?></label>
							<input type="text" id="bd-last-name" name="last_name" 
								value="<?php echo esc_attr( $data['last_name'] ); ?>"
								placeholder="<?php esc_attr_e( 'Your last name', 'business-directory' ); ?>">
						</div>
					</div>

					<div class="bd-form-group">
						<label for="bd-display-name"><?php esc_html_e( 'Display Name', 'business-directory' ); ?> <span class="bd-required">*</span></label>
						<input type="text" id="bd-display-name" name="display_name" required
							value="<?php echo esc_attr( $data['display_name'] ); ?>"
							placeholder="<?php esc_attr_e( 'How your name appears publicly', 'business-directory' ); ?>">
						<p class="bd-field-help"><?php esc_html_e( 'This is how your name will appear on reviews and the leaderboard.', 'business-directory' ); ?></p>
					</div>

					<div class="bd-form-group">
						<label for="bd-bio"><?php esc_html_e( 'About Me', 'business-directory' ); ?></label>
						<textarea id="bd-bio" name="bio" rows="3"
							placeholder="<?php esc_attr_e( 'Tell us a little about yourself...', 'business-directory' ); ?>"><?php echo esc_textarea( $data['bio'] ); ?></textarea>
					</div>

					<div class="bd-form-group">
						<label for="bd-city"><?php esc_html_e( 'Your City', 'business-directory' ); ?></label>
						<select id="bd-city" name="city" class="bd-form-select">
							<option value=""><?php esc_html_e( 'Select your city', 'business-directory' ); ?></option>
							<?php if ( ! is_wp_error( $areas ) && ! empty( $areas ) ) : ?>
								<?php foreach ( $areas as $area ) : ?>
									<option value="<?php echo esc_attr( $area->slug ); ?>" <?php selected( $data['city'], $area->slug ); ?>>
										<?php echo esc_html( $area->name ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
							<option value="other" <?php selected( $data['city'], 'other' ); ?>><?php esc_html_e( 'Other', 'business-directory' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Contact Information -->
				<div class="bd-form-section">
					<h3><?php esc_html_e( 'Contact Information', 'business-directory' ); ?></h3>

					<div class="bd-form-group">
						<label for="bd-email-display"><?php esc_html_e( 'Email Address', 'business-directory' ); ?></label>
						<div class="bd-email-field-wrapper">
							<input type="email" id="bd-email-display" 
								value="<?php echo esc_attr( $data['email'] ); ?>" 
								disabled 
								class="bd-input-disabled">
							<button type="button" class="bd-btn bd-btn-secondary bd-btn-sm bd-change-email-btn">
								<?php esc_html_e( 'Change', 'business-directory' ); ?>
							</button>
						</div>
						<p class="bd-field-help"><?php esc_html_e( 'Email changes require verification for security.', 'business-directory' ); ?></p>
					</div>

					<!-- Hidden email change form -->
					<div class="bd-email-change-form" style="display: none;">
						<div class="bd-form-group">
							<label for="bd-new-email"><?php esc_html_e( 'New Email Address', 'business-directory' ); ?></label>
							<input type="email" id="bd-new-email" name="new_email" 
								placeholder="<?php esc_attr_e( 'Enter new email address', 'business-directory' ); ?>">
						</div>
						<div class="bd-form-actions-inline">
							<button type="button" class="bd-btn bd-btn-primary bd-btn-sm bd-send-verification">
								<?php esc_html_e( 'Send Verification', 'business-directory' ); ?>
							</button>
							<button type="button" class="bd-btn bd-btn-secondary bd-btn-sm bd-cancel-email-change">
								<?php esc_html_e( 'Cancel', 'business-directory' ); ?>
							</button>
						</div>
					</div>

					<div class="bd-form-group">
						<label for="bd-phone"><?php esc_html_e( 'Phone Number', 'business-directory' ); ?></label>
						<input type="tel" id="bd-phone" name="phone" 
							value="<?php echo esc_attr( $data['phone'] ); ?>"
							placeholder="<?php esc_attr_e( '(555) 123-4567', 'business-directory' ); ?>">
					</div>

					<div class="bd-form-group">
						<label for="bd-website"><?php esc_html_e( 'Website', 'business-directory' ); ?></label>
						<input type="url" id="bd-website" name="website" 
							value="<?php echo esc_attr( $data['website'] ); ?>"
							placeholder="https://yourwebsite.com">
					</div>
				</div>

				<!-- Social Media -->
				<div class="bd-form-section">
					<h3><?php esc_html_e( 'Social Media', 'business-directory' ); ?></h3>

					<div class="bd-form-row bd-form-row-2col">
						<div class="bd-form-group">
							<label for="bd-instagram">
								<i class="fa-brands fa-instagram"></i> <?php esc_html_e( 'Instagram', 'business-directory' ); ?>
							</label>
							<div class="bd-input-with-prefix">
								<span class="bd-input-prefix">@</span>
								<input type="text" id="bd-instagram" name="instagram" 
									value="<?php echo esc_attr( $data['instagram'] ); ?>"
									placeholder="username">
							</div>
						</div>
						<div class="bd-form-group">
							<label for="bd-twitter">
								<i class="fa-brands fa-x-twitter"></i> <?php esc_html_e( 'X (Twitter)', 'business-directory' ); ?>
							</label>
							<div class="bd-input-with-prefix">
								<span class="bd-input-prefix">@</span>
								<input type="text" id="bd-twitter" name="twitter" 
									value="<?php echo esc_attr( $data['twitter'] ); ?>"
									placeholder="username">
							</div>
						</div>
					</div>

					<div class="bd-form-group">
						<label for="bd-facebook">
							<i class="fa-brands fa-facebook"></i> <?php esc_html_e( 'Facebook', 'business-directory' ); ?>
						</label>
						<input type="url" id="bd-facebook" name="facebook" 
							value="<?php echo esc_attr( $data['facebook'] ); ?>"
							placeholder="https://facebook.com/yourprofile">
					</div>

					<div class="bd-form-group">
						<label for="bd-linkedin">
							<i class="fa-brands fa-linkedin"></i> <?php esc_html_e( 'LinkedIn', 'business-directory' ); ?>
						</label>
						<input type="url" id="bd-linkedin" name="linkedin" 
							value="<?php echo esc_attr( $data['linkedin'] ); ?>"
							placeholder="https://linkedin.com/in/yourprofile">
					</div>
				</div>

				<!-- Submit -->
				<div class="bd-form-actions">
					<button type="submit" class="bd-btn bd-btn-primary">
						<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Save Changes', 'business-directory' ); ?>
					</button>
					<button type="button" class="bd-btn bd-btn-secondary bd-cancel-edit">
						<?php esc_html_e( 'Cancel', 'business-directory' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
