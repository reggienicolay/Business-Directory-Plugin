<?php
/**
 * Edit Listing Frontend Form
 *
 * Allows business owners to edit their listing from the frontend.
 *
 * @package BusinessDirectory
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\DB\ChangeRequestsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EditListing
 */
class EditListing {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'bd_edit_listing', array( $this, 'render_form' ) );
		add_action( 'wp_ajax_bd_submit_listing_changes', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_bd_upload_listing_photo', array( $this, 'handle_photo_upload' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on pages with shortcode.
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'bd_edit_listing' ) ) {
			return;
		}

		// Enqueue WordPress media uploader.
		wp_enqueue_media();

		// Enqueue editor.
		wp_enqueue_editor();

		// Design tokens (fonts, colors)
		wp_enqueue_style(
			'bd-design-tokens',
			plugins_url( 'assets/css/design-tokens.css', BD_PLUGIN_FILE ),
			array(),
			BD_VERSION
		);

		wp_enqueue_style(
			'bd-edit-listing',
			plugins_url( 'assets/css/edit-listing.css', BD_PLUGIN_FILE ),
			array( 'bd-design-tokens' ), // Ensure design tokens are loaded first.
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-edit-listing',
			plugins_url( 'assets/js/edit-listing.js', BD_PLUGIN_FILE ),
			array( 'jquery', 'wp-editor' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			'bd-edit-listing',
			'bdEditListing',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_edit_listing_nonce' ),
				'i18n'    => array(
					'saving'         => __( 'Saving...', 'business-directory' ),
					'saved'          => __( 'Changes submitted for review!', 'business-directory' ),
					'error'          => __( 'An error occurred. Please try again.', 'business-directory' ),
					'uploadError'    => __( 'Upload failed. Please try again.', 'business-directory' ),
					'confirmLeave'   => __( 'You have unsaved changes. Are you sure you want to leave?', 'business-directory' ),
					'maxPhotos'      => __( 'Maximum 10 photos allowed.', 'business-directory' ),
					'selectCategory' => __( 'Please select at least one category.', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Render the edit form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_form( $atts = array() ) {
		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		// Get business ID from URL or attribute.
		$business_id = isset( $_GET['business_id'] ) ? absint( $_GET['business_id'] ) : 0;
		if ( ! $business_id && isset( $atts['business_id'] ) ) {
			$business_id = absint( $atts['business_id'] );
		}

		// No business specified - show business selector.
		if ( ! $business_id ) {
			return $this->render_business_selector();
		}

		// Verify user can edit this business.
		if ( ! $this->user_can_edit( $business_id ) ) {
			return $this->render_access_denied();
		}

		// Check for pending changes.
		$has_pending = ChangeRequestsTable::has_pending( $business_id );

		// Get business data.
		$business = get_post( $business_id );
		if ( ! $business ) {
			return $this->render_not_found();
		}

		// Get current meta.
		$location = get_post_meta( $business_id, 'bd_location', true ) ?: array();
		$contact  = get_post_meta( $business_id, 'bd_contact', true ) ?: array();
		$hours    = get_post_meta( $business_id, 'bd_hours', true ) ?: array();
		$social   = get_post_meta( $business_id, 'bd_social', true ) ?: array();
		$features = get_post_meta( $business_id, 'bd_features', true ) ?: array();
		$photos   = get_post_meta( $business_id, 'bd_photos', true ) ?: array();

		$categories  = wp_get_object_terms( $business_id, 'bd_category', array( 'fields' => 'ids' ) );
		$tags        = wp_get_object_terms( $business_id, 'bd_tag', array( 'fields' => 'ids' ) );
		$featured_id = get_post_thumbnail_id( $business_id );

		ob_start();
		?>
		<div class="bd-edit-listing-wrapper">
			<?php if ( $has_pending ) : ?>
				<div class="bd-edit-notice bd-edit-notice-warning">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
						<path d="M10 0C4.5 0 0 4.5 0 10s4.5 10 10 10 10-4.5 10-10S15.5 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z"/>
					</svg>
					<span><?php esc_html_e( 'You have pending changes awaiting review. New submissions will replace the pending request.', 'business-directory' ); ?></span>
				</div>
			<?php endif; ?>

			<form id="bd-edit-listing-form" class="bd-edit-form" data-business-id="<?php echo esc_attr( $business_id ); ?>">
				<input type="hidden" name="business_id" value="<?php echo esc_attr( $business_id ); ?>">
				<?php wp_nonce_field( 'bd_edit_listing_nonce', 'bd_nonce' ); ?>

				<!-- Header -->
				<div class="bd-edit-header">
					<div class="bd-edit-header-content">
						<h1><?php esc_html_e( 'Edit Your Listing', 'business-directory' ); ?></h1>
						<p class="bd-edit-subtitle">
							<?php echo esc_html( $business->post_title ); ?>
							<a href="<?php echo esc_url( get_permalink( $business_id ) ); ?>" target="_blank" class="bd-view-listing-link">
								<?php esc_html_e( 'View Listing →', 'business-directory' ); ?>
							</a>
						</p>
					</div>
					<div class="bd-edit-header-actions">
						<button type="submit" class="bd-btn bd-btn-primary bd-submit-changes">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<?php esc_html_e( 'Submit for Review', 'business-directory' ); ?>
						</button>
					</div>
				</div>

				<!-- Form Sections -->
				<div class="bd-edit-sections">

					<!-- Basic Info Section -->
					<section class="bd-edit-section" id="section-basic">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
								</svg>
								<?php esc_html_e( 'Basic Information', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<div class="bd-form-row">
								<label for="bd-title"><?php esc_html_e( 'Business Name', 'business-directory' ); ?> <span class="required">*</span></label>
								<input type="text" id="bd-title" name="title" value="<?php echo esc_attr( $business->post_title ); ?>" required>
							</div>

							<div class="bd-form-row">
								<label for="bd-description"><?php esc_html_e( 'Description', 'business-directory' ); ?></label>
								<p class="bd-field-help"><?php esc_html_e( 'Describe your business. You can include YouTube video links which will be embedded automatically.', 'business-directory' ); ?></p>
								<?php
								wp_editor(
									$business->post_content,
									'bd-description',
									array(
										'textarea_name' => 'description',
										'textarea_rows' => 12,
										'media_buttons' => false,
										'teeny'         => false,
										'quicktags'     => true,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink',
											'toolbar2' => '',
										),
									)
								);
								?>
							</div>

							<div class="bd-form-row">
								<label><?php esc_html_e( 'Categories', 'business-directory' ); ?> <span class="required">*</span></label>
								<div class="bd-checkbox-grid">
									<?php
									$all_categories = get_terms(
										array(
											'taxonomy'   => 'bd_category',
											'hide_empty' => false,
										)
									);
									foreach ( $all_categories as $cat ) :
										?>
										<label class="bd-checkbox-item">
											<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>"
												<?php checked( in_array( $cat->term_id, $categories, true ) ); ?>>
											<span><?php echo esc_html( $cat->name ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="bd-form-row">
								<label><?php esc_html_e( 'Tags', 'business-directory' ); ?></label>
								<div class="bd-checkbox-grid bd-checkbox-grid-sm">
									<?php
									$all_tags = get_terms(
										array(
											'taxonomy'   => 'bd_tag',
											'hide_empty' => false,
										)
									);
									foreach ( $all_tags as $tag ) :
										?>
										<label class="bd-checkbox-item">
											<input type="checkbox" name="tags[]" value="<?php echo esc_attr( $tag->term_id ); ?>"
												<?php checked( in_array( $tag->term_id, $tags, true ) ); ?>>
											<span><?php echo esc_html( $tag->name ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</section>

					<!-- Contact Section -->
					<section class="bd-edit-section" id="section-contact">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
								</svg>
								<?php esc_html_e( 'Contact Information', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<div class="bd-form-grid bd-form-grid-2">
								<div class="bd-form-row">
									<label for="bd-phone"><?php esc_html_e( 'Phone', 'business-directory' ); ?></label>
									<input type="tel" id="bd-phone" name="contact[phone]" value="<?php echo esc_attr( $contact['phone'] ?? '' ); ?>" placeholder="(555) 123-4567">
								</div>

								<div class="bd-form-row">
									<label for="bd-email"><?php esc_html_e( 'Email', 'business-directory' ); ?></label>
									<input type="email" id="bd-email" name="contact[email]" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>" placeholder="info@yourbusiness.com">
								</div>
							</div>

							<div class="bd-form-row">
								<label for="bd-website"><?php esc_html_e( 'Website', 'business-directory' ); ?></label>
								<input type="url" id="bd-website" name="contact[website]" value="<?php echo esc_url( $contact['website'] ?? '' ); ?>" placeholder="https://www.yourbusiness.com">
							</div>
						</div>
					</section>

					<!-- Location Section -->
					<section class="bd-edit-section" id="section-location">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
								</svg>
								<?php esc_html_e( 'Location', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<div class="bd-form-row">
								<label for="bd-address"><?php esc_html_e( 'Street Address', 'business-directory' ); ?></label>
								<input type="text" id="bd-address" name="location[address]" value="<?php echo esc_attr( $location['address'] ?? '' ); ?>" placeholder="123 Main Street">
							</div>

							<div class="bd-form-grid bd-form-grid-3">
								<div class="bd-form-row">
									<label for="bd-city"><?php esc_html_e( 'City', 'business-directory' ); ?></label>
									<input type="text" id="bd-city" name="location[city]" value="<?php echo esc_attr( $location['city'] ?? '' ); ?>">
								</div>

								<div class="bd-form-row">
									<label for="bd-state"><?php esc_html_e( 'State', 'business-directory' ); ?></label>
									<input type="text" id="bd-state" name="location[state]" value="<?php echo esc_attr( $location['state'] ?? '' ); ?>">
								</div>

								<div class="bd-form-row">
									<label for="bd-zip"><?php esc_html_e( 'ZIP Code', 'business-directory' ); ?></label>
									<input type="text" id="bd-zip" name="location[zip]" value="<?php echo esc_attr( $location['zip'] ?? '' ); ?>">
								</div>
							</div>

							<!-- Hidden lat/lng fields -->
							<input type="hidden" id="bd-lat" name="location[lat]" value="<?php echo esc_attr( $location['lat'] ?? '' ); ?>">
							<input type="hidden" id="bd-lng" name="location[lng]" value="<?php echo esc_attr( $location['lng'] ?? '' ); ?>">
						</div>
					</section>

					<!-- Hours Section -->
					<section class="bd-edit-section" id="section-hours">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
								</svg>
								<?php esc_html_e( 'Business Hours', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<div class="bd-hours-editor">
								<?php
								$days = array(
									'monday'    => __( 'Monday', 'business-directory' ),
									'tuesday'   => __( 'Tuesday', 'business-directory' ),
									'wednesday' => __( 'Wednesday', 'business-directory' ),
									'thursday'  => __( 'Thursday', 'business-directory' ),
									'friday'    => __( 'Friday', 'business-directory' ),
									'saturday'  => __( 'Saturday', 'business-directory' ),
									'sunday'    => __( 'Sunday', 'business-directory' ),
								);

								foreach ( $days as $key => $label ) :
									$day_hours = $hours[ $key ] ?? array();
									$is_closed = ! empty( $day_hours['closed'] );
									?>
									<div class="bd-hours-row" data-day="<?php echo esc_attr( $key ); ?>">
										<span class="bd-hours-day"><?php echo esc_html( $label ); ?></span>
										<div class="bd-hours-inputs">
											<label class="bd-hours-closed">
												<input type="checkbox" name="hours[<?php echo esc_attr( $key ); ?>][closed]" value="1" <?php checked( $is_closed ); ?>>
												<span><?php esc_html_e( 'Closed', 'business-directory' ); ?></span>
											</label>
											<div class="bd-hours-times <?php echo $is_closed ? 'bd-hidden' : ''; ?>">
												<input type="time" name="hours[<?php echo esc_attr( $key ); ?>][open]" value="<?php echo esc_attr( $day_hours['open'] ?? '09:00' ); ?>">
												<span class="bd-hours-separator">to</span>
												<input type="time" name="hours[<?php echo esc_attr( $key ); ?>][close]" value="<?php echo esc_attr( $day_hours['close'] ?? '17:00' ); ?>">
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</section>

					<!-- Photos Section -->
					<section class="bd-edit-section" id="section-photos">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
								</svg>
								<?php esc_html_e( 'Photos', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<p class="bd-field-help"><?php esc_html_e( 'Upload up to 10 photos. Drag to reorder. The first photo will be your featured image.', 'business-directory' ); ?></p>

							<div class="bd-photo-grid" id="bd-photo-grid">
								<?php
								// Combine featured image with gallery.
								$all_photo_ids = array();
								if ( $featured_id ) {
									$all_photo_ids[] = $featured_id;
								}
								if ( is_array( $photos ) ) {
									foreach ( $photos as $photo_id ) {
										if ( $photo_id && ! in_array( $photo_id, $all_photo_ids, true ) ) {
											$all_photo_ids[] = $photo_id;
										}
									}
								}

								foreach ( $all_photo_ids as $index => $photo_id ) :
									$photo_url = wp_get_attachment_image_url( $photo_id, 'medium' );
									if ( ! $photo_url ) {
										continue;
									}
									?>
									<div class="bd-photo-item" data-id="<?php echo esc_attr( $photo_id ); ?>">
										<img src="<?php echo esc_url( $photo_url ); ?>" alt="">
										<input type="hidden" name="photos[]" value="<?php echo esc_attr( $photo_id ); ?>">
										<?php if ( 0 === $index ) : ?>
											<span class="bd-photo-badge"><?php esc_html_e( 'Featured', 'business-directory' ); ?></span>
										<?php endif; ?>
										<button type="button" class="bd-photo-remove" data-id="<?php echo esc_attr( $photo_id ); ?>">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
												<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
											</svg>
										</button>
										<div class="bd-photo-drag-handle">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
												<path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
											</svg>
										</div>
									</div>
								<?php endforeach; ?>

								<!-- Add Photo Button -->
								<div class="bd-photo-add" id="bd-photo-add">
									<svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
										<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
									</svg>
									<span><?php esc_html_e( 'Add Photo', 'business-directory' ); ?></span>
								</div>
							</div>
						</div>
					</section>

					<!-- Social Media Section -->
					<section class="bd-edit-section" id="section-social">
						<div class="bd-edit-section-header">
							<h2>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
								</svg>
								<?php esc_html_e( 'Social Media', 'business-directory' ); ?>
							</h2>
						</div>
						<div class="bd-edit-section-content">
							<div class="bd-form-grid bd-form-grid-2">
								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-facebook">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2">
											<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
										</svg>
										<?php esc_html_e( 'Facebook', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-facebook" name="social[facebook]" value="<?php echo esc_url( $social['facebook'] ?? '' ); ?>" placeholder="https://facebook.com/yourbusiness">
								</div>

								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-instagram">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#E4405F">
											<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
										</svg>
										<?php esc_html_e( 'Instagram', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-instagram" name="social[instagram]" value="<?php echo esc_url( $social['instagram'] ?? '' ); ?>" placeholder="https://instagram.com/yourbusiness">
								</div>

								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-twitter">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#000">
											<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
										</svg>
										<?php esc_html_e( 'X (Twitter)', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-twitter" name="social[twitter]" value="<?php echo esc_url( $social['twitter'] ?? '' ); ?>" placeholder="https://x.com/yourbusiness">
								</div>

								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-linkedin">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#0A66C2">
											<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
										</svg>
										<?php esc_html_e( 'LinkedIn', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-linkedin" name="social[linkedin]" value="<?php echo esc_url( $social['linkedin'] ?? '' ); ?>" placeholder="https://linkedin.com/company/yourbusiness">
								</div>

								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-yelp">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#FF1A1A">
											<path d="M20.16 12.594l-4.995 1.433c-.96.276-1.74-.8-1.176-1.63l2.905-4.308a1.072 1.072 0 011.596-.206 9.194 9.194 0 012.364 3.252 1.073 1.073 0 01-.694 1.459zm-3.397 5.906a1.072 1.072 0 01.147-1.58 9.238 9.238 0 013.347-1.614 1.073 1.073 0 011.39.883c.097.715.11 1.45.02 2.2a1.076 1.076 0 01-1.264.93l-3.64-.82zm-5.632 1.12c-.156-.956.8-1.74 1.63-1.176l4.308 2.905a1.072 1.072 0 01.206 1.596 9.194 9.194 0 01-3.252 2.364 1.073 1.073 0 01-1.459-.694l-1.433-4.995zM5.07 16.652a1.073 1.073 0 01-.884-1.39 9.238 9.238 0 011.614-3.347 1.072 1.072 0 011.58.147l.82 3.64a1.076 1.076 0 01-.93 1.264c-.715.097-1.45.11-2.2.02v-.334zM12.24.015a1.073 1.073 0 011.158.985l.45 7.553c.056.934-1.053 1.465-1.746.835L5.42 2.86a1.073 1.073 0 01.19-1.731A9.218 9.218 0 0112.24.015z"/>
										</svg>
										<?php esc_html_e( 'Yelp', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-yelp" name="social[yelp]" value="<?php echo esc_url( $social['yelp'] ?? '' ); ?>" placeholder="https://yelp.com/biz/yourbusiness">
								</div>

								<div class="bd-form-row bd-form-row-icon">
									<label for="bd-youtube">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="#FF0000">
											<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
										</svg>
										<?php esc_html_e( 'YouTube', 'business-directory' ); ?>
									</label>
									<input type="url" id="bd-youtube" name="social[youtube]" value="<?php echo esc_url( $social['youtube'] ?? '' ); ?>" placeholder="https://youtube.com/@yourbusiness">
								</div>
							</div>
						</div>
					</section>

				</div>

				<!-- Sticky Footer -->
				<div class="bd-edit-footer">
					<div class="bd-edit-footer-content">
						<p class="bd-edit-note">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
							</svg>
							<?php esc_html_e( 'Changes will be reviewed by our team before publishing.', 'business-directory' ); ?>
						</p>
						<button type="submit" class="bd-btn bd-btn-primary bd-submit-changes">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
							</svg>
							<?php esc_html_e( 'Submit for Review', 'business-directory' ); ?>
						</button>
					</div>
				</div>

			</form>

			<!-- Success/Error Messages -->
			<div id="bd-edit-messages"></div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Check if user can edit this business.
	 *
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	private function user_can_edit( $business_id ) {
		$user_id = get_current_user_id();

		// Admin can edit anything.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check if user is the claimed owner.
		$claimed_by = get_post_meta( $business_id, 'bd_claimed_by', true );
		if ( $claimed_by && (int) $claimed_by === $user_id ) {
			return true;
		}

		// Check claims table for approved claim.
		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claim = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$claims_table} WHERE business_id = %d AND user_id = %d AND status = 'approved'",
				$business_id,
				$user_id
			)
		);

		return ! empty( $claim );
	}

	/**
	 * Render login required message.
	 */
	private function render_login_required() {
		ob_start();
		?>
		<div class="bd-edit-message bd-edit-login">
			<div class="bd-edit-message-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Login Required', 'business-directory' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to edit your business listing.', 'business-directory' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bd-btn bd-btn-primary">
				<?php esc_html_e( 'Log In', 'business-directory' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render business selector.
	 */
	private function render_business_selector() {
		$user_id    = get_current_user_id();
		$businesses = $this->get_user_businesses( $user_id );

		ob_start();
		?>
		<div class="bd-edit-message bd-edit-select">
			<div class="bd-edit-message-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Select a Business', 'business-directory' ); ?></h2>

			<?php if ( empty( $businesses ) ) : ?>
				<p><?php esc_html_e( "You don't have any claimed businesses yet.", 'business-directory' ); ?></p>
				<a href="<?php echo home_url( '/local/' ); ?>" class="bd-btn bd-btn-primary">
					<?php esc_html_e( 'Browse Directory', 'business-directory' ); ?>
				</a>
			<?php else : ?>
				<p><?php esc_html_e( 'Choose which business you want to edit:', 'business-directory' ); ?></p>
				<div class="bd-business-select-list">
					<?php foreach ( $businesses as $business ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'business_id', $business->ID, get_permalink() ) ); ?>" class="bd-business-select-item">
							<?php if ( has_post_thumbnail( $business->ID ) ) : ?>
								<div class="bd-business-select-image">
									<?php echo get_the_post_thumbnail( $business->ID, 'thumbnail' ); ?>
								</div>
							<?php endif; ?>
							<div class="bd-business-select-info">
								<h3><?php echo esc_html( $business->post_title ); ?></h3>
								<?php
								$location = get_post_meta( $business->ID, 'bd_location', true );
								if ( $location && ! empty( $location['city'] ) ) :
									?>
									<p><?php echo esc_html( $location['city'] ); ?></p>
								<?php endif; ?>
							</div>
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
								<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
							</svg>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render access denied message.
	 */
	private function render_access_denied() {
		ob_start();
		?>
		<div class="bd-edit-message bd-edit-denied">
			<div class="bd-edit-message-icon bd-edit-icon-error">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Access Denied', 'business-directory' ); ?></h2>
			<p><?php esc_html_e( "You don't have permission to edit this business. Only claimed owners can make changes.", 'business-directory' ); ?></p>
			<a href="<?php echo home_url( '/local/' ); ?>" class="bd-btn bd-btn-primary">
				<?php esc_html_e( 'Back to Directory', 'business-directory' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render not found message.
	 */
	private function render_not_found() {
		ob_start();
		?>
		<div class="bd-edit-message bd-edit-notfound">
			<div class="bd-edit-message-icon bd-edit-icon-error">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Business Not Found', 'business-directory' ); ?></h2>
			<p><?php esc_html_e( 'The business you are looking for does not exist or has been removed.', 'business-directory' ); ?></p>
			<a href="<?php echo home_url( '/local/' ); ?>" class="bd-btn bd-btn-primary">
				<?php esc_html_e( 'Back to Directory', 'business-directory' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get businesses for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of WP_Post objects.
	 */
	private function get_user_businesses( $user_id ) {
		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// Get approved claims.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claims = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT business_id FROM {$claims_table} WHERE user_id = %d AND status = 'approved'",
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $claims ) ) {
			return array();
		}

		$business_ids = wp_list_pluck( $claims, 'business_id' );

		return get_posts(
			array(
				'post_type'      => array( 'bd_business', 'business' ),
				'post__in'       => $business_ids,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Handle form submission via AJAX.
	 */
	public function handle_submission() {
		check_ajax_referer( 'bd_edit_listing_nonce', 'bd_nonce' );

		$business_id = isset( $_POST['business_id'] ) ? absint( $_POST['business_id'] ) : 0;

		if ( ! $business_id || ! $this->user_can_edit( $business_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this business.', 'business-directory' ) ) );
		}

		$business = get_post( $business_id );
		if ( ! $business ) {
			wp_send_json_error( array( 'message' => __( 'Business not found.', 'business-directory' ) ) );
		}

		// Collect changes.
		$changes  = array();
		$original = array();
		$summary  = array();

		// Title.
		if ( isset( $_POST['title'] ) ) {
			$new_title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
			if ( $new_title !== $business->post_title ) {
				$changes['title']  = $new_title;
				$original['title'] = $business->post_title;
				$summary[]         = __( 'Business name', 'business-directory' );
			}
		}

		// Description.
		if ( isset( $_POST['description'] ) ) {
			$new_desc = wp_kses_post( wp_unslash( $_POST['description'] ) );
			if ( $new_desc !== $business->post_content ) {
				$changes['description']  = $new_desc;
				$original['description'] = $business->post_content;
				$summary[]               = __( 'Description', 'business-directory' );
			}
		}

		// Contact.
		if ( isset( $_POST['contact'] ) && is_array( $_POST['contact'] ) ) {
			$new_contact = array_map( 'sanitize_text_field', wp_unslash( $_POST['contact'] ) );
			$old_contact = get_post_meta( $business_id, 'bd_contact', true ) ?: array();
			if ( $new_contact !== $old_contact ) {
				$changes['contact']  = $new_contact;
				$original['contact'] = $old_contact;
				$summary[]           = __( 'Contact info', 'business-directory' );
			}
		}

		// Location.
		if ( isset( $_POST['location'] ) && is_array( $_POST['location'] ) ) {
			$new_location = array_map( 'sanitize_text_field', wp_unslash( $_POST['location'] ) );
			$old_location = get_post_meta( $business_id, 'bd_location', true ) ?: array();
			if ( $new_location !== $old_location ) {
				$changes['location']  = $new_location;
				$original['location'] = $old_location;
				$summary[]            = __( 'Location', 'business-directory' );
			}
		}

		// Hours.
		if ( isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ) {
			$new_hours = array();
			foreach ( $_POST['hours'] as $day => $times ) {
				$day               = sanitize_key( $day );
				$new_hours[ $day ] = array(
					'closed' => ! empty( $times['closed'] ),
					'open'   => sanitize_text_field( $times['open'] ?? '' ),
					'close'  => sanitize_text_field( $times['close'] ?? '' ),
				);
			}
			$old_hours = get_post_meta( $business_id, 'bd_hours', true ) ?: array();
			if ( $new_hours !== $old_hours ) {
				$changes['hours']  = $new_hours;
				$original['hours'] = $old_hours;
				$summary[]         = __( 'Business hours', 'business-directory' );
			}
		}

		// Social.
		if ( isset( $_POST['social'] ) && is_array( $_POST['social'] ) ) {
			$new_social = array_map( 'esc_url_raw', wp_unslash( $_POST['social'] ) );
			$old_social = get_post_meta( $business_id, 'bd_social', true ) ?: array();
			if ( $new_social !== $old_social ) {
				$changes['social']  = $new_social;
				$original['social'] = $old_social;
				$summary[]          = __( 'Social media', 'business-directory' );
			}
		}

		// Categories.
		if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
			$new_cats = array_map( 'absint', $_POST['categories'] );
			$old_cats = wp_get_object_terms( $business_id, 'bd_category', array( 'fields' => 'ids' ) );
			sort( $new_cats );
			sort( $old_cats );
			if ( $new_cats !== $old_cats ) {
				$changes['categories']  = $new_cats;
				$original['categories'] = $old_cats;
				$summary[]              = __( 'Categories', 'business-directory' );
			}
		}

		// Tags.
		if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
			$new_tags = array_map( 'absint', $_POST['tags'] );
			$old_tags = wp_get_object_terms( $business_id, 'bd_tag', array( 'fields' => 'ids' ) );
			sort( $new_tags );
			sort( $old_tags );
			if ( $new_tags !== $old_tags ) {
				$changes['tags']  = $new_tags;
				$original['tags'] = $old_tags;
				$summary[]        = __( 'Tags', 'business-directory' );
			}
		}

		// Photos.
		if ( isset( $_POST['photos'] ) && is_array( $_POST['photos'] ) ) {
			$new_photos = array_map( 'absint', $_POST['photos'] );
			$old_photos = get_post_meta( $business_id, 'bd_photos', true ) ?: array();
			$featured   = get_post_thumbnail_id( $business_id );

			// Compare photos.
			if ( $new_photos !== $old_photos || ( ! empty( $new_photos ) && $new_photos[0] !== $featured ) ) {
				$changes['photos'] = $new_photos;
				if ( ! empty( $new_photos ) ) {
					$changes['featured_image'] = $new_photos[0];
				}
				$original['photos']         = $old_photos;
				$original['featured_image'] = $featured;
				$summary[]                  = __( 'Photos', 'business-directory' );
			}
		}

		// No changes detected.
		if ( empty( $changes ) ) {
			wp_send_json_error( array( 'message' => __( 'No changes detected.', 'business-directory' ) ) );
		}

		// Create summary string.
		$summary_text = sprintf(
			// translators: %s is a comma-separated list of updated fields.
			__( 'Updated: %s', 'business-directory' ),
			implode( ', ', $summary )
		);

		// Insert change request.
		$request_id = ChangeRequestsTable::insert(
			$business_id,
			get_current_user_id(),
			$changes,
			$original,
			$summary_text
		);

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save changes. Please try again.', 'business-directory' ) ) );
		}

		// Send notification to admin.
		$this->notify_admin_new_request( $request_id, $business_id, $summary_text );

		wp_send_json_success(
			array(
				'message'    => __( 'Your changes have been submitted for review. You will be notified once approved.', 'business-directory' ),
				'request_id' => $request_id,
			)
		);
	}

	/**
	 * Handle photo upload via AJAX.
	 */
	public function handle_photo_upload() {
		check_ajax_referer( 'bd_edit_listing_nonce', 'nonce' );

		if ( ! isset( $_FILES['photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'business-directory' ) ) );
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( $_POST['business_id'] ) : 0;

		if ( ! $business_id || ! $this->user_can_edit( $business_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		// Handle upload.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', $business_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$photo_url = wp_get_attachment_image_url( $attachment_id, 'medium' );

		wp_send_json_success(
			array(
				'id'  => $attachment_id,
				'url' => $photo_url,
			)
		);
	}

	/**
	 * Notify admin of new change request.
	 *
	 * @param int    $request_id   Request ID.
	 * @param int    $business_id  Business ID.
	 * @param string $summary      Change summary.
	 */
	private function notify_admin_new_request( $request_id, $business_id, $summary ) {
		$admin_email   = get_option( 'bd_notification_emails', get_option( 'admin_email' ) );
		$business_name = get_the_title( $business_id );
		$user          = wp_get_current_user();

		$subject = sprintf(
			'[%s] Listing Change Request: %s',
			get_bloginfo( 'name' ),
			$business_name
		);

		$message = sprintf(
			"A business owner has submitted changes for review.\n\n" .
			"Business: %s\n" .
			"Submitted by: %s (%s)\n" .
			"Changes: %s\n\n" .
			"Review and approve: %s\n",
			$business_name,
			$user->display_name,
			$user->user_email,
			$summary,
			admin_url( 'admin.php?page=bd-pending-changes' )
		);

		wp_mail( $admin_email, $subject, $message );
	}
}
