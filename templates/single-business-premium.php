<?php

/**
 * Premium Single Business Template - WITH SIDEBAR CLAIM BLOCK
 * Features: Photo Gallery, Video Gallery, Lightbox, Similar Businesses, Social Sharing, Featured in Lists
 */

get_header();

// Add styles for the review form posting-as section
?>
<style>
/* Posting As Section */
.bd-posting-as {
	background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
	border-radius: 12px;
	padding: 16px 20px;
	border: 1px solid #dee2e6;
}

.bd-posting-as > label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #6c757d;
	margin-bottom: 10px;
}

.bd-user-identity {
	display: flex;
	align-items: center;
	gap: 12px;
}

.bd-user-identity img.avatar {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	border: 2px solid #fff;
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
	object-fit: cover;
}

.bd-user-display-name {
	flex: 1;
	font-size: 16px;
	font-weight: 600;
	color: #212529;
}

.bd-change-nickname-btn {
	background: transparent;
	border: 1px solid #0d6efd;
	color: #0d6efd;
	padding: 6px 14px;
	border-radius: 20px;
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.2s ease;
}

.bd-change-nickname-btn:hover {
	background: #0d6efd;
	color: #fff;
}

/* Nickname Editor */
.bd-nickname-editor {
	background: #fff;
	border-radius: 8px;
	padding: 16px;
	border: 1px solid #dee2e6;
	margin-top: -8px;
}

.bd-nickname-editor label {
	font-weight: 600;
	color: #495057;
	margin-bottom: 6px;
	display: block;
}

.bd-nickname-editor input[type="text"] {
	width: 100%;
	padding: 10px 14px;
	border: 2px solid #dee2e6;
	border-radius: 8px;
	font-size: 15px;
	transition: border-color 0.2s ease;
}

.bd-nickname-editor input[type="text"]:focus {
	outline: none;
	border-color: #0d6efd;
}

.bd-nickname-editor .description {
	font-size: 13px;
	color: #6c757d;
	margin-top: 6px;
}

/* Login Prompt */
.bd-login-prompt {
	background: #e7f1ff;
	border-radius: 8px;
	padding: 12px 16px;
	font-size: 14px;
	color: #0a58ca;
	margin-top: 8px;
}

.bd-login-prompt a {
	font-weight: 600;
	color: #0d6efd;
	text-decoration: underline;
}

.bd-login-prompt a:hover {
	color: #0a58ca;
}

/* Star Rating Interaction */
.bd-star-rating {
	display: flex;
	flex-direction: row-reverse;
	justify-content: flex-end;
	gap: 4px;
}

.bd-star-rating input {
	display: none;
}

.bd-star-rating label {
	font-size: 32px;
	color: #d1d5db;
	cursor: pointer;
	transition: color 0.15s ease, transform 0.15s ease;
	line-height: 1;
}

.bd-star-rating label:hover,
.bd-star-rating label:hover ~ label {
	color: #fbbf24;
	transform: scale(1.1);
}

.bd-star-rating input:checked ~ label {
	color: #f59e0b;
}

.bd-star-rating label.selected {
	color: #f59e0b;
}

/* Field Error States */
.bd-field-error {
	border-color: #dc3545 !important;
	box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15) !important;
}

.bd-star-rating.bd-field-error {
	background: rgba(220, 53, 69, 0.08);
	border-radius: 8px;
	padding: 8px;
	margin: -8px;
}

.bd-inline-error {
	color: #dc3545;
	font-size: 13px;
	font-weight: 500;
	margin-top: 6px;
	margin-bottom: 0;
	display: flex;
	align-items: center;
	gap: 6px;
}

.bd-inline-error::before {
	content: "⚠";
	font-size: 14px;
}

/* Shake Animation */
@keyframes bd-shake {
	0%, 100% { transform: translateX(0); }
	10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
	20%, 40%, 60%, 80% { transform: translateX(4px); }
}

.bd-shake {
	animation: bd-shake 0.5s ease-in-out;
}

/* Photo Preview */
.bd-photo-preview {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin-top: 10px;
}

.bd-photo-thumbnail {
	width: 80px;
	height: 80px;
	object-fit: cover;
	border-radius: 8px;
	border: 2px solid #dee2e6;
}

/* Review Photos - Clickable */
.bd-review-photos img {
	cursor: pointer;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.bd-review-photos img:hover {
	transform: scale(1.05);
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>
<?php

while ( have_posts() ) :
	the_post();
	$business_id  = get_the_ID();
	$location     = get_post_meta( $business_id, 'bd_location', true );
	$contact      = get_post_meta( $business_id, 'bd_contact', true );
	$hours        = get_post_meta( $business_id, 'bd_hours', true );
	$price_level  = get_post_meta( $business_id, 'bd_price_level', true );
	$avg_rating   = get_post_meta( $business_id, 'bd_avg_rating', true );
	$review_count = get_post_meta( $business_id, 'bd_review_count', true );
	$categories   = wp_get_post_terms( $business_id, 'bd_category' );
	$areas        = wp_get_post_terms( $business_id, 'bd_area' );
	$social       = get_post_meta( $business_id, 'bd_social', true );
	$claimed_by   = get_post_meta( $business_id, 'bd_claimed_by', true );
	$claim_status = get_post_meta( $business_id, 'bd_claim_status', true );
	?>

	<!-- Back to Directory Button -->
	<a href="<?php echo home_url( '/local/' ); ?>" class="bd-back-link">
		<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
			<path d="M8 0L0 8l8 8V0z" />
		</svg>
		Back to Directory
	</a>

	<!-- Hero Section -->
	<div class="bd-business-hero">
		<div class="bd-business-hero-content">
			<div class="bd-business-hero-left">
				<h1><?php the_title(); ?></h1>

				<div class="bd-business-meta">
					<?php if ( $avg_rating ) : ?>
						<div class="bd-rating-badge">
							<span class="bd-rating-number"><?php echo number_format( $avg_rating, 1 ); ?></span>
							<div class="bd-rating-stars">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="<?php echo $i <= $avg_rating ? 'filled' : 'empty'; ?>">★</span>
								<?php endfor; ?>
							</div>
							<span class="bd-review-count">(<?php echo $review_count; ?> reviews)</span>
						</div>
					<?php endif; ?>

					<?php if ( $price_level ) : ?>
						<span class="bd-price-badge"><?php echo esc_html( $price_level ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $categories ) ) : ?>
						<span class="bd-category-badge"><?php echo esc_html( $categories[0]->name ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $location ) : ?>
					<div class="bd-location-quick">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
							<path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5s2.5 1.1 2.5 2.5S9.4 7.5 8 7.5z" />
						</svg>
						<?php echo esc_html( $location['address'] ); ?>, <?php echo esc_html( $location['city'] ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Hero Action Buttons -->
			<div class="bd-business-hero-actions">
				<?php if ( ! empty( $contact['website'] ) ) : ?>
					<a href="<?php echo esc_url( $contact['website'] ); ?>" target="_blank" rel="noopener" class="bd-btn bd-btn-primary">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="10" cy="10" r="8" />
							<path d="M2 10h16M10 2a15.3 15.3 0 0 1 4 8 15.3 15.3 0 0 1-4 8 15.3 15.3 0 0 1-4-8 15.3 15.3 0 0 1 4-8z" />
						</svg>
						Visit Website
					</a>
				<?php endif; ?>

				<?php if ( ! empty( $contact['phone'] ) ) : ?>
					<a href="tel:<?php echo esc_attr( $contact['phone'] ); ?>" class="bd-btn bd-btn-secondary">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
							<path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
						</svg>
						<?php echo esc_html( $contact['phone'] ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="bd-business-content-wrapper">

		<!-- PHOTO GALLERY SECTION -->
		<?php
		$photo_ids         = get_post_meta( $business_id, 'bd_photos', true );
		$featured_image_id = get_post_thumbnail_id( $business_id );

		$all_photo_ids = array();
		if ( $photo_ids && is_array( $photo_ids ) ) {
			$all_photo_ids = $photo_ids;
		}
		if ( $featured_image_id && ! in_array( $featured_image_id, $all_photo_ids ) ) {
			array_unshift( $all_photo_ids, $featured_image_id );
		}
		$all_photo_ids = array_values( array_unique( $all_photo_ids ) );
		?>

		<?php if ( ! empty( $all_photo_ids ) && count( $all_photo_ids ) > 0 ) : ?>
			<section class="bd-photo-gallery">
				<div class="bd-gallery-main bd-gallery-clickable" data-index="0">
					<?php
					$main_photo_url = wp_get_attachment_image_url( $all_photo_ids[0], 'large' );
					$main_photo_alt = get_post_meta( $all_photo_ids[0], '_wp_attachment_image_alt', true ) ?: get_the_title() . ' - Photo 1';
					?>
					<img src="<?php echo esc_url( $main_photo_url ); ?>"
						alt="<?php echo esc_attr( $main_photo_alt ); ?>"
						loading="eager">
					<div class="bd-gallery-overlay">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
							<circle cx="11" cy="11" r="8" />
							<path d="M21 21l-4.35-4.35" />
							<line x1="11" y1="8" x2="11" y2="14" />
							<line x1="8" y1="11" x2="14" y2="11" />
						</svg>
					</div>
				</div>

				<?php if ( count( $all_photo_ids ) > 1 ) : ?>
					<div class="bd-gallery-grid">
						<?php
						$grid_photo_ids  = array_slice( $all_photo_ids, 1, 4 );
						$remaining_count = count( $all_photo_ids ) - 5;

						foreach ( $grid_photo_ids as $index => $photo_id ) :
							$photo_url         = wp_get_attachment_image_url( $photo_id, 'medium' );
							$photo_alt         = get_post_meta( $photo_id, '_wp_attachment_image_alt', true ) ?: get_the_title() . ' - Photo ' . ( $index + 2 );
							$is_last_grid_item = ( $index === 3 );
							$show_more_overlay = ( $is_last_grid_item && $remaining_count > 0 );
							$actual_index      = $index + 1;
							?>
							<div class="bd-gallery-item bd-gallery-clickable <?php echo $show_more_overlay ? 'bd-gallery-more' : ''; ?>"
								data-index="<?php echo $actual_index; ?>">
								<?php if ( $show_more_overlay ) : ?>
									<div class="bd-gallery-more-overlay">
										<span>+<?php echo $remaining_count; ?> more</span>
									</div>
								<?php endif; ?>
								<img src="<?php echo esc_url( $photo_url ); ?>"
									alt="<?php echo esc_attr( $photo_alt ); ?>"
									loading="lazy">
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<!-- VIDEO GALLERY SECTION -->
		<?php
		$video_ids = get_post_meta( $business_id, 'bd_videos', true );
		if ( $video_ids && is_array( $video_ids ) && count( $video_ids ) > 0 ) :
			?>
			<section class="bd-video-gallery">
				<h2 class="bd-section-title">Videos</h2>
				<div class="bd-video-grid">
					<?php
					foreach ( $video_ids as $video_id ) :
						$video_url   = wp_get_attachment_url( $video_id );
						$video_thumb = wp_get_attachment_image_url( $video_id, 'medium' );
						?>
						<div class="bd-video-item">
							<video controls poster="<?php echo esc_url( $video_thumb ); ?>">
								<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
								Your browser does not support video playback.
							</video>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>



		<!-- Main Content Grid -->
		<div class="bd-business-grid">

			<!-- Left Column: About & Features -->
			<div class="bd-business-main">

				<!-- About Section (Description Only) -->
				<div class="bd-info-card bd-about-section">
					<h2><?php /* translators: %s: business name */ echo esc_html( sprintf( __( 'About %s', 'business-directory' ), get_the_title() ) ); ?></h2>
					<div class="bd-description">
						<?php
						// Get raw post content WITHOUT BD Pro's the_content filters
						$raw_content = get_post_field( 'post_content', $business_id );
						// Use WordPress native function to strip shortcodes (more reliable than regex)
						$raw_content = strip_shortcodes( $raw_content );
						// Apply basic formatting
						echo wp_kses_post( wpautop( $raw_content ) );
						?>
					</div>
				</div>

				<?php
				// Trail Route & Stats
				if ( class_exists( 'BDOutdoor\Frontend\TrailDisplay' ) ) {
					\BDOutdoor\Frontend\TrailDisplay::render_main_content( $business_id );
				}
				?>

				<!-- Reviews Section -->
				<div class="bd-info-card bd-reviews-section">
					<?php
					// Fetch reviews from BD Pro's database
					$reviews = class_exists( 'BD\DB\ReviewsTable' )
						? \BD\DB\ReviewsTable::get_by_business( $business_id )
						: array();

					// Use cached meta from top of template (already fetched as $avg_rating, $review_count)
					$turnstile_site_key = get_option( 'bd_turnstile_site_key', '' );
					?>
					
					<h2><?php esc_html_e( 'Reviews', 'business-directory' ); ?></h2>
					
					<?php if ( $review_count > 0 && $avg_rating ) : ?>
						<?php $rating_rounded = (int) round( (float) $avg_rating ); ?>
						<div class="bd-rating-summary">
							<span class="bd-stars"><?php echo esc_html( str_repeat( '★', $rating_rounded ) . str_repeat( '☆', 5 - $rating_rounded ) ); ?></span>
							<span><?php echo esc_html( number_format( (float) $avg_rating, 1 ) ); ?> (<?php echo esc_html( $review_count ); ?> reviews)</span>
						</div>
					<?php endif; ?>
					
					<div class="bd-reviews-list">
						<?php if ( ! empty( $reviews ) ) : ?>
							<?php foreach ( $reviews as $review ) : ?>
								<?php $review_rating = isset( $review['rating'] ) ? (int) $review['rating'] : 0; ?>
								<div class="bd-review-card" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
									<div class="bd-review-header">
										<div class="bd-review-author-info">
											<?php if ( ! empty( $review['user_id'] ) ) : ?>
												<?php echo get_avatar( $review['user_id'], 48 ); ?>
											<?php endif; ?>
											<div>
												<strong><?php echo esc_html( $review['author_name'] ?? 'Anonymous' ); ?></strong>
												<div class="bd-review-date">
													<?php
													$created = strtotime( $review['created_at'] ?? '' );
													if ( $created ) {
														echo esc_html( human_time_diff( $created, current_time( 'timestamp' ) ) . ' ago' );
													}
													?>
												</div>
											</div>
										</div>
										<span class="bd-stars"><?php echo esc_html( str_repeat( '★', $review_rating ) . str_repeat( '☆', 5 - $review_rating ) ); ?></span>
									</div>
									
									<?php if ( ! empty( $review['title'] ) ) : ?>
										<h4 class="bd-review-title"><?php echo esc_html( $review['title'] ); ?></h4>
									<?php endif; ?>
									
									<p class="bd-review-content"><?php echo esc_html( $review['content'] ?? '' ); ?></p>
									
									<?php if ( ! empty( $review['photo_ids'] ) ) : ?>
										<div class="bd-review-photos">
											<?php
											$photo_ids = array_filter( array_map( 'absint', explode( ',', $review['photo_ids'] ) ) );
											foreach ( $photo_ids as $photo_id ) {
												echo wp_get_attachment_image( $photo_id, 'thumbnail' );
											}
											?>
										</div>
									<?php endif; ?>
									
									<div class="bd-review-actions">
										<button class="bd-helpful-btn" 
												data-review-id="<?php echo esc_attr( $review['id'] ); ?>"
												data-review-author-id="<?php echo esc_attr( $review['user_id'] ?? 0 ); ?>">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
											</svg>
											<span class="bd-helpful-text"><?php esc_html_e( 'Helpful', 'business-directory' ); ?></span>
											<span class="bd-helpful-count"><?php echo esc_html( $review['helpful_count'] ?? 0 ); ?></span>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="bd-no-reviews"><?php esc_html_e( 'Be the first to review!', 'business-directory' ); ?></p>
						<?php endif; ?>
					</div>
					
					<h3 class="bd-write-review-title"><?php esc_html_e( 'Write a Review', 'business-directory' ); ?></h3>
					
					<?php
					// Get current user info for logged-in detection
					$current_user_id = get_current_user_id();
					$is_logged_in    = $current_user_id > 0;
					$display_name    = '';
					$needs_nickname  = false;

					if ( $is_logged_in ) {
						$reviewer_user = get_userdata( $current_user_id );
						if ( $reviewer_user ) {
							// Get display name: BD nickname → WP display_name → user_login
							$bd_nickname  = get_user_meta( $current_user_id, 'bd_display_name', true );
							$display_name = ! empty( $bd_nickname ) ? $bd_nickname : ( $reviewer_user->display_name ?: $reviewer_user->user_login );

							// Check if name looks "ugly" (email-like or matches username)
							$needs_nickname = (
								empty( $bd_nickname ) && (
									strpos( $display_name, '@' ) !== false ||
									$display_name === $reviewer_user->user_login ||
									empty( $reviewer_user->display_name ) ||
									$reviewer_user->display_name === $reviewer_user->user_login
								)
							);
						} else {
							$is_logged_in = false;
						}
					}
					?>
					
					<form id="bd-submit-review-form" class="bd-form" enctype="multipart/form-data" novalidate>
						<input type="hidden" name="business_id" value="<?php echo esc_attr( $business_id ); ?>" />
						
						<div class="bd-form-row">
							<label><?php esc_html_e( 'Rating', 'business-directory' ); ?> <span class="required">*</span></label>
							<div class="bd-star-rating">
								<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
									<input type="radio" id="star-<?php echo esc_attr( $i ); ?>" name="rating" value="<?php echo esc_attr( $i ); ?>" required />
									<label for="star-<?php echo esc_attr( $i ); ?>">★</label>
								<?php endfor; ?>
							</div>
						</div>
						
						<?php if ( $is_logged_in ) : ?>
							<!-- Logged-in user: Show display name with option to change -->
							<div class="bd-form-row bd-posting-as">
								<label><?php esc_html_e( 'Posting as', 'business-directory' ); ?></label>
								<div class="bd-user-identity">
									<?php echo get_avatar( $current_user_id, 32 ); ?>
									<span class="bd-user-display-name"><?php echo esc_html( $display_name ); ?></span>
									<button type="button" class="bd-change-nickname-btn" id="bd-change-nickname-btn">
										<?php echo $needs_nickname ? esc_html__( 'Set display name', 'business-directory' ) : esc_html__( 'Change', 'business-directory' ); ?>
									</button>
								</div>
							</div>
							
							<!-- Nickname editor (auto-expanded if name is ugly) -->
							<div class="bd-form-row bd-nickname-editor" id="bd-nickname-editor" style="<?php echo $needs_nickname ? '' : 'display: none;'; ?>">
								<label for="bd_display_name"><?php esc_html_e( 'Display Name', 'business-directory' ); ?></label>
								<input type="text" 
										id="bd_display_name" 
										name="bd_display_name" 
										value="<?php echo $needs_nickname ? '' : esc_attr( $display_name ); ?>" 
										maxlength="100"
										placeholder="<?php esc_attr_e( 'Enter a friendly name for your reviews', 'business-directory' ); ?>" />
								<p class="description"><?php esc_html_e( 'This name will be shown publicly with your reviews.', 'business-directory' ); ?></p>
							</div>
						<?php else : ?>
							<!-- Anonymous user: Require name and email -->
							<div class="bd-form-row">
								<label for="author_name"><?php esc_html_e( 'Your Name', 'business-directory' ); ?> <span class="required">*</span></label>
								<input type="text" id="author_name" name="author_name" required maxlength="100" />
							</div>
							
							<div class="bd-form-row">
								<label for="author_email"><?php esc_html_e( 'Email (not published)', 'business-directory' ); ?> <span class="required">*</span></label>
								<input type="email" id="author_email" name="author_email" required />
							</div>
							
							<p class="bd-login-prompt">
								<?php
								printf(
									/* translators: %s: login link */
									esc_html__( 'Already have an account? %s to earn points for your review!', 'business-directory' ),
									'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'business-directory' ) . '</a>'
								);
								?>
							</p>
						<?php endif; ?>
						
						<div class="bd-form-row">
							<label for="title"><?php esc_html_e( 'Review Title', 'business-directory' ); ?></label>
							<input type="text" id="title" name="title" maxlength="200" />
						</div>
						
						<div class="bd-form-row">
							<label for="content"><?php esc_html_e( 'Your Review', 'business-directory' ); ?> <span class="required">*</span></label>
							<textarea id="content" name="content" rows="6" required minlength="10" maxlength="5000"></textarea>
							<p class="description"><?php esc_html_e( 'Minimum 10 characters', 'business-directory' ); ?></p>
						</div>
						
						<div class="bd-form-row">
							<label for="photos"><?php esc_html_e( 'Add Photos', 'business-directory' ); ?></label>
							<input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple />
							<p class="description"><?php esc_html_e( 'Up to 3 photos, 5MB each (JPEG, PNG, GIF, WebP)', 'business-directory' ); ?></p>
						</div>
						
						<?php if ( ! empty( $turnstile_site_key ) ) : ?>
						<div class="bd-form-row">
							<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
						</div>
						<?php endif; ?>
						
						<div class="bd-form-row">
							<button type="submit" class="bd-btn bd-btn-primary"><?php esc_html_e( 'Submit Review', 'business-directory' ); ?></button>
						</div>
						
						<div id="bd-review-message"></div>
					</form>
				</div>

				<!-- Share Buttons -->
				<div class="bd-share-wrapper">
					<?php
					if ( shortcode_exists( 'bd_share_buttons' ) ) {
						echo do_shortcode( '[bd_share_buttons]' );
					}
					?>
				</div>
				
				<!-- Amenities/Features -->
				<?php $features = get_post_meta( $business_id, 'bd_features', true ); ?>
				<?php if ( $features && is_array( $features ) ) : ?>
					<div class="bd-info-card bd-amenities">
						<h3>Amenities & Features</h3>
						<div class="bd-amenities-grid">
							<?php foreach ( $features as $feature ) : ?>
								<div class="bd-amenity-item">
									<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
										<path d="M10 0l3 7h7l-5.5 4.5L17 20l-7-5-7 5 2.5-8.5L0 7h7z" />
									</svg>
									<?php echo esc_html( $feature ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

			</div>

			<!-- Right Sidebar: Claim Block, Hours, Contact, Map -->
			<aside class="bd-business-sidebar">

				<!-- Save to List Button -->
				<div class="bd-info-card bd-save-card">
					<?php
					if ( shortcode_exists( 'bd_save_button' ) ) {
						echo do_shortcode( '[bd_save_button style="button"]' );
					}
					?>
				</div>

				<?php
				// Activities, Plan Your Visit - render directly after Save button
				if ( class_exists( 'BDOutdoor\Frontend\TrailDisplay' ) ) {
					\BDOutdoor\Frontend\TrailDisplay::render_sidebar_content( $business_id );
				}
				?>

				<!-- FEATURED IN LISTS SECTION -->
				<?php
				if ( class_exists( 'BD\Lists\ListManager' ) ) :
					$lists_count    = \BD\Lists\ListManager::count_public_lists_for_business( $business_id );
					$featured_lists = $lists_count > 0 ? \BD\Lists\ListManager::get_public_lists_for_business( $business_id, 3 ) : array();

					if ( $lists_count > 0 ) :
						?>
						<div class="bd-info-card bd-featured-lists-card">
							<div class="bd-featured-lists-header">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
									<path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
								</svg>
								<h4>Featured in <?php echo esc_html( $lists_count ); ?> <?php echo $lists_count === 1 ? 'List' : 'Lists'; ?></h4>
							</div>
							
							<div class="bd-featured-lists-items">
								<?php foreach ( $featured_lists as $list ) : ?>
									<a href="<?php echo esc_url( $list['url'] ); ?>" class="bd-featured-list-item">
										<span class="bd-featured-list-title"><?php echo esc_html( $list['title'] ); ?></span>
										<span class="bd-featured-list-meta">
											by <?php echo esc_html( $list['author_name'] ); ?> · <?php echo esc_html( $list['item_count'] ); ?> places
										</span>
									</a>
								<?php endforeach; ?>
							</div>

							<?php if ( $lists_count > 3 ) : ?>
								<a href="<?php echo esc_url( home_url( '/lists/?business=' . $business_id ) ); ?>" class="bd-featured-lists-more">
									View all <?php echo esc_html( $lists_count ); ?> lists →
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<!-- CLAIM BLOCK (SIDEBAR VERSION) -->
				<?php if ( ! $claimed_by ) : ?>
					<div class="bd-info-card bd-claim-card">
						<div class="bd-claim-icon-small">
							<svg width="32" height="32" viewBox="0 0 32 32" fill="currentColor">
								<path d="M16 2C8.3 2 2 8.3 2 16s6.3 14 14 14 14-6.3 14-14S23.7 2 16 2zm7 15h-6v6h-2v-6H9v-2h6V9h2v6h6v2z" />
							</svg>
						</div>
						<h4>Own this business?</h4>
						<p class="bd-claim-text">Claim your listing to update info, add photos, and respond to reviews.</p>

						<?php if ( $claim_status === 'pending' ) : ?>
							<button class="bd-btn bd-btn-secondary bd-btn-block" disabled>
								Claim Pending
							</button>
						<?php else : ?>
							<button type="button" class="bd-btn bd-btn-primary bd-btn-block bd-claim-btn">
								Claim This Listing
							</button>
						<?php endif; ?>
					</div>
				<?php elseif ( $claimed_by && current_user_can( 'edit_post', $business_id ) ) : ?>
					<div class="bd-info-card bd-claim-card bd-owned">
						<div class="bd-claim-icon-small bd-owned-icon">
							<svg width="32" height="32" viewBox="0 0 32 32" fill="white">
								<path d="M16 2C8.3 2 2 8.3 2 16s6.3 14 14 14 14-6.3 14-14S23.7 2 16 2zm-2 20l-6-6 1.4-1.4L14 19.2l8.6-8.6L24 12l-10 10z" />
							</svg>
						</div>
						<h4 style="color: #2E7D32;">✓ Your Listing</h4>
						<p class="bd-claim-text">Manage your business information and respond to reviews.</p>

						<?php
						// Get page URLs from settings.
						$tools_url = \BD\Admin\Settings::get_business_tools_url();
						$edit_url  = \BD\Admin\Settings::get_edit_listing_url( $business_id );
						?>
						<?php if ( $edit_url ) : ?>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="bd-btn bd-btn-primary bd-btn-block">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px; vertical-align: -2px;">
									<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
								</svg>
								<?php esc_html_e( 'Edit Listing', 'business-directory' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo get_edit_post_link( $business_id ); ?>" class="bd-btn bd-btn-primary bd-btn-block">
								<?php esc_html_e( 'Manage Listing', 'business-directory' ); ?>
							</a>
						<?php endif; ?>

						<?php if ( $tools_url ) : ?>
							<a href="<?php echo esc_url( $tools_url ); ?>" class="bd-btn bd-btn-secondary bd-btn-block" style="margin-top: 8px;">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px; vertical-align: -2px;">
									<path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z" />
								</svg>
								<?php esc_html_e( 'Business Tools', 'business-directory' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Hours Card -->
				<?php if ( $hours ) : ?>
					<div class="bd-info-card bd-hours-card">
						<h3>Hours</h3>
						<div class="bd-hours-list">
							<?php
							$days  = array(
								'monday'    => 'Monday',
								'tuesday'   => 'Tuesday',
								'wednesday' => 'Wednesday',
								'thursday'  => 'Thursday',
								'friday'    => 'Friday',
								'saturday'  => 'Saturday',
								'sunday'    => 'Sunday',
							);
							$today = strtolower( date( 'l' ) );

							foreach ( $days as $key => $label ) :
								$day_hours = $hours[ $key ] ?? null;
								$is_today  = ( $key === $today );
								?>
								<div class="bd-hours-row <?php echo $is_today ? 'bd-today' : ''; ?>">
									<span class="bd-day"><?php echo $label; ?></span>
									<span class="bd-time">
										<?php
										// Check if day is closed or has no valid hours set
										$is_closed = empty( $day_hours )
											|| ! empty( $day_hours['closed'] )
											|| empty( $day_hours['open'] )
											|| empty( $day_hours['close'] )
											|| strtotime( $day_hours['open'] ) === false
											|| strtotime( $day_hours['close'] ) === false;
										
										if ( $is_closed ) {
											echo 'Closed';
										} else {
											$open_formatted  = date( 'g:i A', strtotime( $day_hours['open'] ) );
											$close_formatted = date( 'g:i A', strtotime( $day_hours['close'] ) );
											echo esc_html( $open_formatted ) . ' - ' . esc_html( $close_formatted );
										}
										?>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Contact Card -->
				<div class="bd-info-card bd-contact-card">
					<h3>Contact</h3>
					<div class="bd-contact-list">
						<?php if ( $location ) : ?>
							<div class="bd-contact-item">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
									<path d="M10 0C6.5 0 3.75 2.75 3.75 6.25c0 4.375 6.25 13.75 6.25 13.75s6.25-9.375 6.25-13.75C16.25 2.75 13.5 0 10 0zm0 9.375c-1.75 0-3.125-1.375-3.125-3.125S8.25 3.125 10 3.125s3.125 1.375 3.125 3.125S11.75 9.375 10 9.375z" />
								</svg>
								<div>
									<strong>Address</strong>
									<p><?php echo esc_html( $location['address'] ); ?><br>
										<?php echo esc_html( $location['city'] ); ?>, <?php echo esc_html( $location['state'] ); ?> <?php echo esc_html( $location['zip'] ); ?></p>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $contact['phone'] ) ) : ?>
							<div class="bd-contact-item">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
									<path d="M3.75 2.5a1.25 1.25 0 011.25-1.25h10a1.25 1.25 0 011.25 1.25v15a1.25 1.25 0 01-1.25 1.25H5a1.25 1.25 0 01-1.25-1.25v-15z" />
								</svg>
								<div>
									<strong>Phone</strong>
									<p><a href="tel:<?php echo esc_attr( $contact['phone'] ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a></p>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $contact['email'] ) ) : ?>
							<div class="bd-contact-item">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
									<path d="M2.5 5.625A1.875 1.875 0 014.375 3.75h11.25A1.875 1.875 0 0117.5 5.625v8.75a1.875 1.875 0 01-1.875 1.875H4.375A1.875 1.875 0 012.5 14.375v-8.75zM10 11.25l-7.5-5v1.25l7.5 5 7.5-5V6.25l-7.5 5z" />
								</svg>
								<div>
									<strong>Email</strong>
									<p><a href="mailto:<?php echo esc_attr( $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a></p>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Map Preview -->
				<?php if ( $location && ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) : ?>
					<div class="bd-info-card bd-map-preview">
						<div id="bd-location-map"
							data-lat="<?php echo esc_attr( $location['lat'] ); ?>"
							data-lng="<?php echo esc_attr( $location['lng'] ); ?>"></div>
						<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode( $location['address'] . ', ' . $location['city'] . ', ' . $location['state'] ); ?>"
							target="_blank" rel="noopener" class="bd-map-link">
							Get Directions →
						</a>
					</div>
				<?php endif; ?>

				<?php
				// Events Calendar Integration - Display upcoming events
				do_action( 'bd_after_business_content', $business_id );
				?>

			</aside>

		</div>

		<!-- SIMILAR BUSINESSES SECTION -->
		<?php
		$similar_args = array(
			'post_type'      => 'bd_business',
			'posts_per_page' => 3,
			'post__not_in'   => array( $business_id ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $categories ) ) {
			$similar_args['tax_query'] = array(
				array(
					'taxonomy' => 'bd_category',
					'field'    => 'term_id',
					'terms'    => $categories[0]->term_id,
				),
			);
		}

		$similar_businesses = new WP_Query( $similar_args );

		if ( $similar_businesses->have_posts() ) :
			?>
			<section class="bd-similar-businesses">
				<h2 class="bd-section-title">Similar Businesses</h2>
				<div class="bd-similar-grid">
					<?php
					while ( $similar_businesses->have_posts() ) :
						$similar_businesses->the_post();
						$sim_id       = get_the_ID();
						$sim_rating   = get_post_meta( $sim_id, 'bd_avg_rating', true );
						$sim_reviews  = get_post_meta( $sim_id, 'bd_review_count', true );
						$sim_location = get_post_meta( $sim_id, 'bd_location', true );
						?>
						<article class="bd-similar-card">
							<?php if ( has_post_thumbnail() ) : ?>
								<div class="bd-similar-image">
									<a href="<?php the_permalink(); ?>">
										<?php the_post_thumbnail( 'medium' ); ?>
									</a>
								</div>
							<?php endif; ?>
							<div class="bd-similar-content">
								<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

								<?php if ( $sim_rating ) : ?>
									<div class="bd-similar-rating">
										<span class="bd-rating-num"><?php echo number_format( $sim_rating, 1 ); ?></span>
										<div class="bd-stars">
											<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
												<span class="<?php echo $i <= $sim_rating ? 'filled' : 'empty'; ?>">★</span>
											<?php endfor; ?>
										</div>
										<span class="bd-review-text">(<?php echo $sim_reviews; ?>)</span>
									</div>
								<?php endif; ?>

								<?php if ( $sim_location ) : ?>
									<p class="bd-similar-location">
										<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
											<path d="M7 0C4.2 0 2 2.2 2 5c0 3.5 5 9 5 9s5-5.5 5-9c0-2.8-2.2-5-5-5zm0 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z" />
										</svg>
										<?php echo esc_html( $sim_location['city'] ); ?>
									</p>
								<?php endif; ?>

								<a href="<?php the_permalink(); ?>" class="bd-similar-link">View Details →</a>
							</div>
						</article>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

	</div>

	<!-- PHOTO LIGHTBOX MODAL -->
	<div id="bd-lightbox" class="bd-lightbox" style="display: none;">
		<button type="button" class="bd-lightbox-close" aria-label="Close">&times;</button>
		<button type="button" class="bd-lightbox-prev" aria-label="Previous">
			<svg width="32" height="32" viewBox="0 0 32 32" fill="white">
				<path d="M20 4L8 16l12 12V4z" />
			</svg>
		</button>
		<button type="button" class="bd-lightbox-next" aria-label="Next">
			<svg width="32" height="32" viewBox="0 0 32 32" fill="white">
				<path d="M12 4l12 12-12 12V4z" />
			</svg>
		</button>
		<div class="bd-lightbox-content">
			<img src="" alt="" id="bd-lightbox-image">
			<div class="bd-lightbox-caption">
				<span id="bd-lightbox-counter"></span>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $all_photo_ids ) ) : ?>
		<script>
			window.bdBusinessPhotos =
				<?php
				echo json_encode(
					array_map(
						function ( $id ) {
							return array(
								'url' => wp_get_attachment_image_url( $id, 'full' ),
								'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title(),
							);
						},
						$all_photo_ids
					)
				);
				?>
				;
		</script>
	<?php endif; ?>

	<?php
endwhile;

get_footer();
?>