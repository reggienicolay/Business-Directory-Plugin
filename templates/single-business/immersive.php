<?php

/**
 * Immersive Single Business Template (V2)
 *
 * Features:
 * - Photo hero with parallax background
 * - Floating save/share buttons
 * - Sticky quick navigation bar
 * - Modern card-based layout
 * - Verified badge for claimed businesses
 * - Weather integration (via external plugin)
 * - Featured event banner in hero
 *
 * @package BusinessDirectory
 * @since 0.1.7
 */

if (! defined('ABSPATH')) {
	exit;
}

get_header();

while (have_posts()) :
	the_post();

	// =========================================================================
	// DATA SETUP - Get all business metadata
	// =========================================================================
	$business_id  = get_the_ID();
	$business_url = get_permalink($business_id);

	// Core meta.
	$location     = get_post_meta($business_id, 'bd_location', true);
	$contact      = get_post_meta($business_id, 'bd_contact', true);
	$hours        = get_post_meta($business_id, 'bd_hours', true);
	$price_level  = get_post_meta($business_id, 'bd_price_level', true);
	$avg_rating   = get_post_meta($business_id, 'bd_avg_rating', true);
	$review_count = get_post_meta($business_id, 'bd_review_count', true);
	$social       = get_post_meta($business_id, 'bd_social', true);

	// Claim status.
	$claimed_by   = get_post_meta($business_id, 'bd_claimed_by', true);
	$claim_status = get_post_meta($business_id, 'bd_claim_status', true);
	$is_verified  = ($claim_status === 'approved');

	// Is the current user the owner?
	$current_user_id   = get_current_user_id();
	$is_owner          = ($current_user_id && intval($claimed_by) === $current_user_id);

	// Taxonomies.
	$categories = wp_get_post_terms($business_id, 'bd_category');
	$areas      = wp_get_post_terms($business_id, 'bd_area');
	$tags       = wp_get_post_terms($business_id, 'bd_tag');

	// Photos - Gallery + Featured Image.
	// Note: Photos stored in bd_photos, featured image is post thumbnail
	$gallery_ids    = get_post_meta($business_id, 'bd_photos', true);
	$gallery_ids    = is_array($gallery_ids) ? $gallery_ids : array();
	$featured_id    = get_post_thumbnail_id($business_id);

	// Combine: featured first, then gallery photos
	$all_photo_ids  = array();
	if ($featured_id) {
		$all_photo_ids[] = $featured_id;
	}
	foreach ($gallery_ids as $photo_id) {
		if ($photo_id && ! in_array($photo_id, $all_photo_ids, true)) {
			$all_photo_ids[] = $photo_id;
		}
	}
	$photo_count    = count($all_photo_ids);
	$has_photos     = $photo_count > 0;

	// Hero background image (with placeholder fallback).
	$hero_image      = bd_get_business_image($business_id, 'full');
	$hero_image_url  = $hero_image['url'];
	$is_placeholder  = $hero_image['is_placeholder'];

	// Open/Closed status.
	$is_open        = false;
	$closes_at      = '';
	$today_key      = strtolower(date('l'));

	if ($hours && isset($hours[$today_key])) {
		$today_hours = $hours[$today_key];
		if (! empty($today_hours) && empty($today_hours['closed']) && ! empty($today_hours['open']) && ! empty($today_hours['close'])) {
			$now         = current_time('U'); // Unix timestamp in site timezone
			$open_time   = strtotime($today_hours['open']);
			$close_time  = strtotime($today_hours['close']);

			if ($now >= $open_time && $now < $close_time) {
				$is_open   = true;
				$closes_at = date('g:i A', $close_time);
			}
		}
	}

	// =========================================================================
	// FEATURED EVENT (from TEC integration)
	// =========================================================================
	$upcoming_event = null;

	// Method 1: Use existing EventsCalendarIntegration if available
	if (class_exists('\BD\Integrations\EventsCalendar\EventsCalendarIntegration')) {
		$events = \BD\Integrations\EventsCalendar\EventsCalendarIntegration::get_business_events($business_id, 1);
		if (! empty($events)) {
			$upcoming_event = $events[0];
		}
	}
	// Method 2: Fallback to direct query
	elseif (function_exists('tribe_get_events')) {
		// Get venue linked to this business
		$venue_id = get_post_meta($business_id, '_bd_synced_venue_id', true);
		$has_venue_link = ! empty($venue_id);

		if ($has_venue_link) {
			// Build meta query for events at this venue OR linked to business
			$meta_query = array('relation' => 'OR');

			// Events directly linked to business
			$meta_query[] = array(
				'key'     => 'bd_linked_business',
				'value'   => $business_id,
				'compare' => '=',
			);

			// Events at the synced venue
			$meta_query[] = array(
				'key'     => '_EventVenueID',
				'value'   => $venue_id,
				'compare' => '=',
			);

			$events = tribe_get_events(
				array(
					'posts_per_page' => 1,
					'start_date'     => 'now',
					'meta_query'     => $meta_query,
				)
			);
		} else {
			// No venue link - try direct business link only
			$events = tribe_get_events(
				array(
					'posts_per_page' => 1,
					'start_date'     => 'now',
					'meta_query'     => array(
						array(
							'key'     => 'bd_linked_business',
							'value'   => $business_id,
							'compare' => '=',
						),
					),
				)
			);
		}

		if (! empty($events)) {
			$upcoming_event = $events[0];
		}
	}

	// =========================================================================
	// REVIEWS
	// =========================================================================
	global $wpdb;
	$reviews_table = $wpdb->prefix . 'bd_reviews';
	$reviews       = array();

	if ($wpdb->get_var("SHOW TABLES LIKE '$reviews_table'") === $reviews_table) {
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $reviews_table WHERE business_id = %d AND status = 'approved' ORDER BY created_at DESC LIMIT 10",
				$business_id
			),
			ARRAY_A
		);
	}
?>

	<!-- =====================================================================
	     HERO SECTION - Photo Background with Parallax
	     ===================================================================== -->
	<section class="bd-business-hero bd-immersive-hero">
		<!-- Background Layer -->
		<div class="bd-hero-bg <?php echo $has_photos ? 'bd-hero-bg--photo' : 'bd-hero-bg--placeholder'; ?>"
			style="background-image: url('<?php echo esc_attr($hero_image_url); ?>');"></div>

		<!-- Photo Count Badge (only if real photos exist) -->
		<?php if ($photo_count > 0) : ?>
			<button class="bd-photo-count-badge" data-action="open-gallery">
				<i class="fas fa-camera"></i>
				<?php echo esc_html($photo_count); ?> <?php echo esc_html(_n('photo', 'photos', $photo_count, 'business-directory')); ?>
			</button>
		<?php endif; ?>

		<!-- Hero Content -->
		<div class="bd-hero-content">
			<div class="bd-hero-top-row">
				<div class="bd-hero-info">
					<!-- Title + Verified -->
					<div class="bd-hero-title-row">
						<h1><?php the_title(); ?></h1>
						<?php if ($is_verified) : ?>
							<span class="bd-verified-badge">
								<i class="fas fa-circle-check"></i>
								<span><?php esc_html_e('Verified', 'business-directory'); ?></span>
							</span>
						<?php endif; ?>
					</div>

					<!-- Meta Badges -->
					<div class="bd-hero-meta">
						<?php if ($avg_rating) : ?>
							<span class="bd-badge bd-badge-rating">
								<span class="rating-num"><?php echo number_format($avg_rating, 1); ?></span>
								<span class="stars"><?php echo str_repeat('★', round($avg_rating)); ?></span>
								<span class="count">(<?php echo intval($review_count); ?>)</span>
							</span>
						<?php endif; ?>

						<?php if ($price_level) : ?>
							<span class="bd-badge"><?php echo esc_html($price_level); ?></span>
						<?php endif; ?>

						<?php if (! empty($categories)) : ?>
							<span class="bd-badge"><?php echo esc_html($categories[0]->name); ?></span>
						<?php endif; ?>

						<!-- Open/Closed Status -->
						<?php if ($hours) : ?>
							<?php if ($is_open) : ?>
								<span class="bd-badge bd-badge-open">
									<span class="bd-status-dot"></span>
									<?php
									printf(
										/* translators: %s: closing time */
										esc_html__('Open · Closes %s', 'business-directory'),
										esc_html($closes_at)
									);
									?>
								</span>
							<?php else : ?>
								<span class="bd-badge bd-badge-closed">
									<?php esc_html_e('Closed', 'business-directory'); ?>
								</span>
							<?php endif; ?>
						<?php endif; ?>

						<!-- Weather (via external plugin hook) -->
						<?php do_action('bd_hero_weather_badge', $business_id, $location); ?>
					</div>

					<!-- Address -->
					<?php if ($location) : ?>
						<div class="bd-hero-address">
							<i class="fas fa-location-dot"></i>
							<span>
								<?php echo esc_html($location['address']); ?>,
								<?php echo esc_html($location['city']); ?>,
								<?php echo esc_html($location['state']); ?>
								<?php echo esc_html($location['zip']); ?>
							</span>
						</div>
					<?php endif; ?>

					<!-- Social Proof - Only show saved count (reviews/photos shown elsewhere) -->
					<?php
					// Saved count from lists.
					$saved_count = apply_filters('bd_business_saved_count', 0, $business_id);
					if ($saved_count > 0) :
					?>
					<div class="bd-social-proof">
						<span><i class="fas fa-bookmark"></i> <?php esc_html_e('Saved', 'business-directory'); ?> <?php echo intval($saved_count); ?> <?php esc_html_e('times', 'business-directory'); ?></span>
					</div>
					<?php endif; ?>

					<!-- Thumbnail Strip -->
					<?php if ($photo_count > 1) : ?>
						<div class="bd-hero-thumbs">
							<?php
							$thumb_photos = array_slice($all_photo_ids, 0, 4);
							$index        = 0;
							foreach ($thumb_photos as $photo_id) :
								$index++;
								$is_last = ($index === 4 && $photo_count > 4);
							?>
								<div class="bd-hero-thumb" data-index="<?php echo esc_attr($index - 1); ?>">
									<?php echo wp_get_attachment_image($photo_id, 'thumbnail', false, array('loading' => 'lazy')); ?>
									<?php if ($is_last) : ?>
										<div class="more-overlay">+<?php echo intval($photo_count - 4); ?></div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- Upcoming Event IN Hero (like mockup) -->
					<?php if ($upcoming_event) : ?>
						<a href="<?php echo esc_url(get_permalink($upcoming_event->ID)); ?>" class="bd-hero-event-chip">
							<div class="bd-hero-event-date">
								<span class="month"><?php echo esc_html(strtoupper(date('M', strtotime(tribe_get_start_date($upcoming_event, false, 'Y-m-d'))))); ?></span>
								<span class="day"><?php echo esc_html(date('j', strtotime(tribe_get_start_date($upcoming_event, false, 'Y-m-d')))); ?></span>
							</div>
							<div class="bd-hero-event-info">
								<span class="bd-hero-event-icon"><i class="fas fa-music"></i></span>
								<span class="bd-hero-event-name"><?php echo esc_html($upcoming_event->post_title); ?></span>
								<span class="bd-hero-event-time"><?php echo esc_html(tribe_get_start_date($upcoming_event, false, 'l g:i A')); ?> – <?php echo esc_html(tribe_get_end_date($upcoming_event, false, 'g:i A')); ?></span>
							</div>
							<span class="bd-hero-event-arrow">→</span>
						</a>
					<?php endif; ?>
				</div>

				<!-- Hero Action Buttons -->
				<div class="bd-hero-actions">
					<?php if (! empty($contact['website'])) : ?>
						<a href="<?php echo esc_url($contact['website']); ?>" class="bd-btn bd-btn-primary" target="_blank" rel="noopener">
							<i class="fas fa-globe"></i>
							<?php esc_html_e('Visit Website', 'business-directory'); ?>
						</a>
					<?php endif; ?>

					<?php if (! empty($contact['phone'])) : ?>
						<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $contact['phone'])); ?>" class="bd-btn bd-btn-secondary">
							<i class="fas fa-phone"></i>
							<?php echo esc_html($contact['phone']); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- =====================================================================
	     EVENT TEASER BANNER (if upcoming event exists)
	     ===================================================================== -->
	<?php if ($upcoming_event) : ?>
		<div class="bd-event-teaser">
			<div class="bd-event-teaser-inner">
				<div class="bd-event-teaser-left">
					<div class="bd-event-date-chip">
						<span class="month"><?php echo esc_html(date('M', strtotime(tribe_get_start_date($upcoming_event, false, 'Y-m-d')))); ?></span>
						<span class="day"><?php echo esc_html(date('j', strtotime(tribe_get_start_date($upcoming_event, false, 'Y-m-d')))); ?></span>
					</div>
					<div class="bd-event-teaser-info">
						<span class="event-name"><?php echo esc_html($upcoming_event->post_title); ?></span>
						<span class="event-time"><?php echo esc_html(tribe_get_start_date($upcoming_event, false, 'g:i A')); ?></span>
					</div>
				</div>
				<a href="<?php echo esc_url(get_permalink($upcoming_event->ID)); ?>" class="bd-event-teaser-link">
					<?php esc_html_e('View Event', 'business-directory'); ?> →
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- =====================================================================
	     STICKY QUICK BAR
	     ===================================================================== -->
	<nav class="bd-quick-bar">
		<div class="bd-quick-bar-inner">
			<div class="bd-quick-left">
				<?php if ($hours) : ?>
					<span class="bd-open-status <?php echo $is_open ? 'bd-is-open' : 'bd-is-closed'; ?>">
						<?php if ($is_open) : ?>
							<span class="bd-open-dot"></span>
							<?php
							printf(
								/* translators: %s: closing time */
								esc_html__('Open now · Closes %s', 'business-directory'),
								esc_html($closes_at)
							);
							?>
						<?php else : ?>
							<?php esc_html_e('Closed', 'business-directory'); ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>

				<?php if ($location) : ?>
					<span class="bd-quick-address">
						<i class="fas fa-location-dot"></i>
						<?php echo esc_html($location['address']); ?>, <?php echo esc_html($location['city']); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="bd-quick-right">
				<?php
				// Use the existing save button shortcode
				if (class_exists('\BD\Frontend\ListDisplay')) {
					echo \BD\Frontend\ListDisplay::render_save_button(array(
						'business_id' => $business_id,
						'style'       => 'button'
					));
				} elseif (is_user_logged_in()) {
				?>
					<button class="bd-btn-pill bd-save-btn" data-business-id="<?php echo esc_attr($business_id); ?>">
						<i class="far fa-heart"></i>
						<?php esc_html_e('Save', 'business-directory'); ?>
					</button>
				<?php
				} else {
				?>
					<button class="bd-btn-pill bd-save-btn" data-login-required="true">
						<i class="far fa-heart"></i>
						<?php esc_html_e('Save', 'business-directory'); ?>
					</button>
				<?php
				}
				?>
				<button class="bd-btn-pill bd-share-quick">
					<i class="fas fa-arrow-up-from-bracket"></i>
					<?php esc_html_e('Share', 'business-directory'); ?>
				</button>
				<?php if ($location && ! empty($location['lat']) && ! empty($location['lng'])) : ?>
					<a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($location['lat']); ?>,<?php echo esc_attr($location['lng']); ?>" class="bd-btn-pill directions" target="_blank" rel="noopener">
						<i class="fas fa-diamond-turn-right"></i>
						<?php esc_html_e('Directions', 'business-directory'); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</nav>

	<!-- =====================================================================
	     ENGAGEMENT STRIP - "Been here?"
	     ===================================================================== -->
	<div class="bd-engagement-strip">
		<div class="bd-engagement-strip-inner">
			<span class="bd-engagement-label"><?php esc_html_e('Been here?', 'business-directory'); ?></span>
			<a href="#write-review" class="bd-engagement-cta">
				<i class="fas fa-star"></i>
				<?php esc_html_e('Write a Review', 'business-directory'); ?>
			</a>
			<a href="#write-review" class="bd-engagement-cta bd-add-photo-btn" data-focus="photos">
				<i class="fas fa-camera"></i>
				<?php esc_html_e('Add a Photo', 'business-directory'); ?>
			</a>

			<?php
			// Wine Trail badge (or other special badges).
			$experience_tags = wp_get_post_terms($business_id, 'bd_experience');
			foreach ($experience_tags as $exp_tag) :
				if (stripos($exp_tag->name, 'wine') !== false) :
			?>
					<span class="bd-trail-badge">
						<i class="fas fa-wine-glass"></i>
						<?php echo esc_html(strtoupper($exp_tag->name)); ?>
					</span>
			<?php
					break;
				endif;
			endforeach;
			?>
		</div>
	</div>

	<!-- =====================================================================
	     MAIN CONTENT - Two Column Grid
	     ===================================================================== -->
	<div class="bd-content-wrapper">
		<!-- LEFT COLUMN - Main Content -->
		<main class="bd-main-content">

			<!-- About Card -->
			<article class="bd-card bd-card-padded">
				<div class="bd-section-label">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
					</svg>
					<h2><?php esc_html_e('About', 'business-directory'); ?> <?php the_title(); ?></h2>
					<span class="label-line"></span>
				</div>

				<div class="bd-about-text">
					<?php
					$content = get_the_content();
					if (! empty($content)) {
						echo wp_kses_post(wpautop($content));
					} else {
						echo '<p>' . esc_html__('No description available yet.', 'business-directory') . '</p>';
					}
					?>
				</div>

				<!-- Tags -->
				<?php if (! empty($tags)) : ?>
					<div class="bd-tags-row">
						<?php foreach ($tags as $tag) : ?>
							<a href="<?php echo esc_url(get_term_link($tag)); ?>" class="bd-tag">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
									<circle cx="12" cy="12" r="4" />
								</svg>
								<?php echo esc_html($tag->name); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<!-- Featured in Lists -->
				<?php
				$featured_lists = apply_filters('bd_business_featured_lists', array(), $business_id);
				if (! empty($featured_lists)) :
				?>
					<div class="bd-lists-strip">
						<?php foreach ($featured_lists as $list) : ?>
							<a href="<?php echo esc_url($list['url']); ?>" class="bd-list-chip">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
									<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
								</svg>
								<div>
									<div><?php echo esc_html($list['name']); ?></div>
									<div class="bd-list-chip-meta">
										<?php esc_html_e('by', 'business-directory'); ?> <?php echo esc_html($list['author']); ?> · <?php echo intval($list['count']); ?> <?php esc_html_e('places', 'business-directory'); ?>
									</div>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</article>

			<?php
			/**
			 * Hook: bd_after_about_section
			 * Used by: BD Outdoor Activities (trail stats, map, elevation)
			 */
			do_action('bd_after_about_section', $business_id);
			?>

			<!-- Reviews Card -->
			<article class="bd-card bd-card-padded" id="reviews">
				<div class="bd-section-label">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
						<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
					</svg>
					<h2><?php esc_html_e('Reviews', 'business-directory'); ?></h2>
					<span class="label-line"></span>
				</div>

				<!-- Rating Summary -->
				<div class="bd-reviews-header">
					<div class="bd-rating-big">
						<span class="bd-rating-score"><?php echo $avg_rating ? number_format($avg_rating, 1) : '0.0'; ?></span>
						<div class="bd-rating-detail">
							<span class="bd-rating-stars"><?php echo str_repeat('★', $avg_rating ? round($avg_rating) : 0); ?><?php echo str_repeat('☆', 5 - ($avg_rating ? round($avg_rating) : 0)); ?></span>
							<span class="bd-rating-count"><?php printf(esc_html__('Based on %d reviews', 'business-directory'), intval($review_count)); ?></span>
						</div>
					</div>
					<a href="#write-review" class="bd-btn-write-review">
						<i class="fas fa-pencil"></i>
						<?php esc_html_e('Write a Review', 'business-directory'); ?>
					</a>
				</div>

				<!-- Review List -->
				<?php if (! empty($reviews)) : ?>
					<div class="bd-reviews-list">
						<?php foreach ($reviews as $review) : ?>
							<?php
							$reviewer_user = get_userdata($review['user_id']);
							$reviewer_name = $review['reviewer_name'];
							$initials      = strtoupper(substr($reviewer_name, 0, 1));
							$avatar_url    = $reviewer_user ? get_avatar_url($reviewer_user->ID, array('size' => 88)) : '';
							?>
							<div class="bd-review-card">
								<div class="bd-review-top">
									<div class="bd-reviewer">
										<?php if ($avatar_url) : ?>
											<img src="<?php echo esc_url($avatar_url); ?>" alt="" class="bd-reviewer-avatar">
										<?php else : ?>
											<div class="bd-reviewer-avatar"><?php echo esc_html($initials); ?></div>
										<?php endif; ?>
										<div>
											<div class="bd-reviewer-name"><?php echo esc_html($reviewer_name); ?></div>
											<div class="bd-reviewer-date"><?php echo esc_html(date_i18n('F Y', strtotime($review['created_at']))); ?></div>
										</div>
									</div>
									<span class="bd-review-stars"><?php echo str_repeat('★', intval($review['rating'])); ?></span>
								</div>

								<?php if (! empty($review['title'])) : ?>
									<h4 class="bd-review-title"><?php echo esc_html($review['title']); ?></h4>
								<?php endif; ?>

								<p class="bd-review-body"><?php echo esc_html($review['content']); ?></p>

								<?php
								// Review photos.
								$review_photos = maybe_unserialize($review['photos'] ?? array());
								if (! empty($review_photos)) :
								?>
									<div class="bd-review-photos">
										<?php foreach ($review_photos as $photo_url) : ?>
											<img src="<?php echo esc_url($photo_url); ?>" alt="" class="bd-review-photo" loading="lazy">
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="bd-no-reviews"><?php esc_html_e('Be the first to review this business!', 'business-directory'); ?></p>
				<?php endif; ?>

				<!-- Write Review Form -->
				<div class="bd-review-form-section" id="write-review">
					<h3><?php esc_html_e('Write a Review', 'business-directory'); ?></h3>

					<?php if (is_user_logged_in()) : ?>
						<?php
						$current_user = wp_get_current_user();
						$display_name = get_user_meta($current_user->ID, 'bd_display_name', true);
						if (empty($display_name)) {
							$display_name = $current_user->display_name;
						}
						$user_initial = strtoupper(substr($display_name, 0, 1));
						?>
						<form class="bd-review-form bd-form" id="bd-submit-review-form" method="post">
							<input type="hidden" name="business_id" value="<?php echo esc_attr($business_id); ?>">
							<?php wp_nonce_field('bd_submit_review', 'bd_review_nonce'); ?>

							<!-- Message area for review-form.js -->
							<div id="bd-review-message"></div>

							<div class="bd-form-row">
								<label><?php esc_html_e('Rating', 'business-directory'); ?> <span class="required">*</span></label>
								<div class="bd-star-rating">
									<?php for ($i = 5; $i >= 1; $i--) : ?>
										<input type="radio" id="star-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>">
										<label for="star-<?php echo $i; ?>" title="<?php echo $i; ?> stars">★</label>
									<?php endfor; ?>
								</div>
							</div>

							<div class="bd-form-row">
								<label><?php esc_html_e('Posting as', 'business-directory'); ?></label>
								<div class="bd-posting-as-bar">
									<div class="bd-posting-avatar"><?php echo esc_html($user_initial); ?></div>
									<span class="bd-posting-name"><?php echo esc_html($display_name); ?></span>
									<button type="button" class="bd-posting-change"><?php esc_html_e('Change', 'business-directory'); ?></button>
								</div>
							</div>

							<div class="bd-form-row">
								<label for="review-title"><?php esc_html_e('Review Title', 'business-directory'); ?></label>
								<input type="text" id="review-title" name="title" placeholder="<?php esc_attr_e('Summarize your experience', 'business-directory'); ?>">
							</div>

							<div class="bd-form-row">
								<label for="review-content"><?php esc_html_e('Your Review', 'business-directory'); ?> <span class="required">*</span></label>
								<textarea id="review-content" name="content" rows="5" placeholder="<?php esc_attr_e('Tell others about your experience...', 'business-directory'); ?>"></textarea>
							</div>

							<div class="bd-form-row">
								<label><?php esc_html_e('Add Photos', 'business-directory'); ?></label>
								<input type="file" name="review_photos[]" multiple accept="image/*" class="bd-photo-input">
								<p class="bd-field-hint description"><?php esc_html_e('Up to 3 photos, 5MB each', 'business-directory'); ?></p>
							</div>

							<button type="submit" class="bd-btn bd-btn-primary">
								<?php esc_html_e('Submit Review', 'business-directory'); ?>
							</button>
						</form>
					<?php else : ?>
						<p class="bd-login-prompt">
							<?php
							printf(
								/* translators: %1$s: login URL, %2$s: register URL */
								wp_kses_post(__('Please <a href="%1$s">log in</a> or <a href="%2$s">create an account</a> to write a review.', 'business-directory')),
								esc_url(wp_login_url($business_url . '#write-review')),
								esc_url(wp_registration_url())
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<!-- Share Bar -->
				<div class="bd-share-bar">
					<span><?php esc_html_e('Share', 'business-directory'); ?></span>
					<button class="bd-share-btn" data-platform="facebook" aria-label="Share on Facebook">
						<i class="fab fa-facebook-f"></i>
					</button>
					<button class="bd-share-btn" data-platform="twitter" aria-label="Share on Twitter">
						<i class="fab fa-twitter"></i>
					</button>
					<button class="bd-share-btn" data-platform="copy" aria-label="Copy link">
						<i class="fas fa-link"></i>
					</button>
					<span class="bd-share-points">
						<i class="fas fa-star"></i> +5 <?php esc_html_e('points', 'business-directory'); ?>
					</span>
				</div>
			</article>

		</main>

		<!-- RIGHT SIDEBAR -->
		<aside class="bd-sidebar">
			<!-- Hours Card -->
			<?php if ($hours) : ?>
				<div class="bd-card bd-card-padded">
					<div class="bd-hours-header">
						<h3><?php esc_html_e('Hours', 'business-directory'); ?></h3>
						<span class="bd-hours-status-badge <?php echo $is_open ? 'bd-open' : 'bd-closed'; ?>">
							<?php echo $is_open ? esc_html__('Open Now', 'business-directory') : esc_html__('Closed', 'business-directory'); ?>
						</span>
					</div>
					<?php
					$days       = array(
						'monday'    => __('Monday', 'business-directory'),
						'tuesday'   => __('Tuesday', 'business-directory'),
						'wednesday' => __('Wednesday', 'business-directory'),
						'thursday'  => __('Thursday', 'business-directory'),
						'friday'    => __('Friday', 'business-directory'),
						'saturday'  => __('Saturday', 'business-directory'),
						'sunday'    => __('Sunday', 'business-directory'),
					);
					$today_name = strtolower(date('l'));

					foreach ($days as $key => $label) :
						$day_hours = $hours[$key] ?? null;
						$is_today  = ($key === $today_name);
						$is_closed = empty($day_hours) || ! empty($day_hours['closed']) || empty($day_hours['open']) || empty($day_hours['close']);
					?>
						<div class="bd-hours-row <?php echo $is_today ? 'bd-today' : ''; ?> <?php echo $is_closed ? 'bd-closed' : ''; ?>">
							<span class="day"><?php echo esc_html($label); ?></span>
							<span class="time">
								<?php
								if ($is_closed) {
									esc_html_e('Closed', 'business-directory');
								} else {
									echo esc_html(date('g:i A', strtotime($day_hours['open'])) . ' – ' . date('g:i A', strtotime($day_hours['close'])));
								}
								?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Contact Card -->
			<div class="bd-card bd-card-padded">
				<?php if ($location) : ?>
					<div class="bd-contact-item">
						<div class="bd-contact-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
							</svg>
						</div>
						<div>
							<div class="bd-contact-label"><?php esc_html_e('Address', 'business-directory'); ?></div>
							<div class="bd-contact-value">
								<?php echo esc_html($location['address']); ?><br>
								<?php echo esc_html($location['city']); ?>, <?php echo esc_html($location['state']); ?> <?php echo esc_html($location['zip']); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if (! empty($contact['phone'])) : ?>
					<div class="bd-contact-item">
						<div class="bd-contact-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
							</svg>
						</div>
						<div>
							<div class="bd-contact-label"><?php esc_html_e('Phone', 'business-directory'); ?></div>
							<div class="bd-contact-value">
								<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $contact['phone'])); ?>">
									<?php echo esc_html($contact['phone']); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if (! empty($contact['website'])) : ?>
					<div class="bd-contact-item">
						<div class="bd-contact-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
							</svg>
						</div>
						<div>
							<div class="bd-contact-label"><?php esc_html_e('Website', 'business-directory'); ?></div>
							<div class="bd-contact-value">
								<a href="<?php echo esc_url($contact['website']); ?>" target="_blank" rel="noopener">
									<?php echo esc_html(preg_replace('#^https?://#', '', rtrim($contact['website'], '/'))); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Map Card -->
			<?php if ($location && ! empty($location['lat']) && ! empty($location['lng'])) : ?>
				<div class="bd-card" style="overflow: hidden;">
					<div id="bd-sidebar-map" class="bd-map-placeholder"
						data-lat="<?php echo esc_attr($location['lat']); ?>"
						data-lng="<?php echo esc_attr($location['lng']); ?>">
						<span>🗺 <?php esc_html_e('Loading map...', 'business-directory'); ?></span>
					</div>
					<a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr($location['lat']); ?>,<?php echo esc_attr($location['lng']); ?>" class="bd-map-directions-btn" target="_blank" rel="noopener">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
							<path d="M21.71 11.29l-9-9a.996.996 0 00-1.41 0l-9 9a.996.996 0 000 1.41l9 9c.39.39 1.02.39 1.41 0l9-9a.996.996 0 000-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5l3.5 3.5-3.5 3.5z" />
						</svg>
						<?php esc_html_e('Get Directions', 'business-directory'); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php
			/**
			 * Hook: bd_sidebar_after_save
			 * Used by: BD Outdoor Activities (activities, features, weather)
			 */
			do_action('bd_sidebar_after_save', $business_id);
			?>

			<!-- Upcoming Events Card -->
			<?php
			// Get events for this business ONLY - use same method as hero event.
			$sidebar_events = array();
			
			// Method 1: Use EventsCalendarIntegration if available (same as hero)
			if (class_exists('\BD\Integrations\EventsCalendar\EventsCalendarIntegration')) {
				$sidebar_events = \BD\Integrations\EventsCalendar\EventsCalendarIntegration::get_business_events($business_id, 4);
			}
			// Method 2: Fallback to direct query with strict filtering
			elseif (function_exists('tribe_get_events')) {
				// Only show events explicitly linked to THIS business
				$sidebar_events = tribe_get_events(
					array(
						'posts_per_page' => 4,
						'start_date'     => 'now',
						'meta_query'     => array(
							array(
								'key'     => 'bd_linked_business',
								'value'   => $business_id,
								'compare' => '=',
							),
						),
					)
				);
			}

			if (! empty($sidebar_events)) :
			?>
				<div class="bd-card bd-card-padded bd-events-card">
					<h3><?php esc_html_e('Upcoming Events', 'business-directory'); ?></h3>
					<?php foreach ($sidebar_events as $event) : ?>
						<a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="bd-event-item">
							<div class="bd-event-date-badge-sm">
								<span class="month"><?php echo esc_html(strtoupper(date('M', strtotime(tribe_get_start_date($event, false, 'Y-m-d'))))); ?></span>
								<span class="day"><?php echo esc_html(date('j', strtotime(tribe_get_start_date($event, false, 'Y-m-d')))); ?></span>
							</div>
							<div>
								<div class="bd-event-name"><?php echo esc_html($event->post_title); ?></div>
								<div class="bd-event-time"><?php echo esc_html(tribe_get_start_date($event, false, 'g:i A')); ?></div>
							</div>
						</a>
					<?php endforeach; ?>
					<a href="<?php echo esc_url(home_url('/events/')); ?>" class="bd-btn-all-events">
						<?php esc_html_e('View All Events', 'business-directory'); ?> →
					</a>
				</div>
			<?php endif; ?>

			<!-- Claim Card (only if not claimed) -->
			<?php if (! $claimed_by) : ?>
				<?php
				// Check for pending claim
				$claim_status = '';
				if (class_exists('\BD\DB\ClaimRequestsTable')) {
					$pending_claim = \BD\DB\ClaimRequestsTable::get_by_business($business_id, 'pending');
					if (! empty($pending_claim)) {
						$claim_status = 'pending';
					}
				}
				?>
				<div class="bd-claim-card">
					<div class="bd-claim-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
							<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z" />
						</svg>
					</div>
					<h4><?php esc_html_e('Own this business?', 'business-directory'); ?></h4>
					<p><?php esc_html_e('Claim your listing to update info, add photos, and respond to reviews.', 'business-directory'); ?></p>

					<?php if ($claim_status === 'pending') : ?>
						<button class="bd-btn-claim" disabled style="opacity: 0.6; cursor: not-allowed;">
							<?php esc_html_e('Claim Pending', 'business-directory'); ?>
						</button>
					<?php else : ?>
						<!-- Claim Button - handled by claim-form.js which listens for .bd-claim-btn -->
						<button type="button" class="bd-btn-claim bd-claim-btn" data-business-id="<?php echo esc_attr($business_id); ?>">
							<?php esc_html_e('Claim This Listing', 'business-directory'); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Owner Card (if this is the owner) -->
			<?php if ($is_owner) : ?>
				<div class="bd-card bd-card-padded bd-owner-card">
					<div class="bd-claim-icon" style="background: rgba(16,185,129,0.15); color: #10b981;">
						<svg width="24" height="24" viewBox="0 0 32 32" fill="currentColor">
							<path d="M16 2C8.3 2 2 8.3 2 16s6.3 14 14 14 14-6.3 14-14S23.7 2 16 2zm-2 20l-6-6 1.4-1.4L14 19.2l8.6-8.6L24 12l-10 10z" />
						</svg>
					</div>
					<h4 style="color: #059669;"><?php esc_html_e('✓ Your Listing', 'business-directory'); ?></h4>
					<p><?php esc_html_e('Manage your business information and respond to reviews.', 'business-directory'); ?></p>

					<?php
					$tools_url = \BD\Admin\Settings::get_business_tools_url();
					$edit_url  = \BD\Admin\Settings::get_edit_listing_url($business_id);
					?>

					<?php if ($edit_url) : ?>
						<a href="<?php echo esc_url($edit_url); ?>" class="bd-btn-claim" style="background: var(--bd-primary-600);">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px; vertical-align: -2px;">
								<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
							</svg>
							<?php esc_html_e('Edit Listing', 'business-directory'); ?>
						</a>
					<?php endif; ?>

					<?php if ($tools_url) : ?>
						<a href="<?php echo esc_url($tools_url); ?>" class="bd-btn-all-events" style="margin-top: 8px;">
							<?php esc_html_e('Business Tools', 'business-directory'); ?> →
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Sidebar hook for integrations -->
			<?php do_action('bd_after_business_sidebar', $business_id); ?>
		</aside>
	</div>

	<!-- =====================================================================
	     SIMILAR BUSINESSES
	     ===================================================================== -->
	<?php
	$similar_args = array(
		'post_type'      => 'bd_business',
		'posts_per_page' => 3,
		'post__not_in'   => array($business_id),
		'post_status'    => 'publish',
	);

	if (! empty($categories)) {
		$similar_args['tax_query'] = array(
			array(
				'taxonomy' => 'bd_category',
				'field'    => 'term_id',
				'terms'    => $categories[0]->term_id,
			),
		);
	}

	$similar_businesses = new WP_Query($similar_args);

	if ($similar_businesses->have_posts()) :
	?>
		<section class="bd-similar-section">
			<div class="bd-section-label">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
				</svg>
				<h2><?php esc_html_e('Similar Businesses', 'business-directory'); ?></h2>
				<span class="label-line"></span>
			</div>
			<div class="bd-similar-grid">
				<?php
				while ($similar_businesses->have_posts()) :
					$similar_businesses->the_post();
					$sim_id       = get_the_ID();
					$sim_rating   = get_post_meta($sim_id, 'bd_avg_rating', true);
					$sim_reviews  = get_post_meta($sim_id, 'bd_review_count', true);
					$sim_location = get_post_meta($sim_id, 'bd_location', true);
				?>
					<a href="<?php the_permalink(); ?>" class="bd-similar-card">
						<div class="bd-similar-img">
							<?php
							$sim_image = bd_get_business_image($sim_id, 'medium');
							if ($sim_image['url']) :
							?>
								<img src="<?php echo esc_attr($sim_image['url']); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
							<?php else : ?>
								<span><?php esc_html_e('No Image', 'business-directory'); ?></span>
							<?php endif; ?>
						</div>
						<div class="bd-similar-body">
							<h3><?php the_title(); ?></h3>
							<?php if ($sim_rating) : ?>
								<div class="sim-rating">
									<span class="stars"><?php echo str_repeat('★', round($sim_rating)); ?></span>
									<span class="count">(<?php echo intval($sim_reviews); ?>)</span>
								</div>
							<?php endif; ?>
							<?php if ($sim_location) : ?>
								<p class="sim-location">
									<svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
										<path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5s2.5 1.1 2.5 2.5S9.4 7.5 8 7.5z" />
									</svg>
									<?php echo esc_html($sim_location['city']); ?>
								</p>
							<?php endif; ?>
						</div>
					</a>
				<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- =====================================================================
	     PHOTO LIGHTBOX MODAL
	     ===================================================================== -->
	<div id="bd-lightbox" class="bd-lightbox" style="display: none;">
		<button type="button" class="bd-lightbox-close" aria-label="<?php esc_attr_e('Close', 'business-directory'); ?>">&times;</button>
		<button type="button" class="bd-lightbox-prev" aria-label="<?php esc_attr_e('Previous', 'business-directory'); ?>">
			<svg width="32" height="32" viewBox="0 0 32 32" fill="white">
				<path d="M20 4L8 16l12 12V4z" />
			</svg>
		</button>
		<button type="button" class="bd-lightbox-next" aria-label="<?php esc_attr_e('Next', 'business-directory'); ?>">
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


	<!-- Lightbox body class handler + move to body -->
	<script>
	(function() {
		document.addEventListener('DOMContentLoaded', function() {
			const lightbox = document.getElementById('bd-lightbox');
			if (!lightbox) return;
			
			// CRITICAL: Move lightbox to body to escape any stacking contexts
			document.body.appendChild(lightbox);
			
			// Watch for display changes via MutationObserver
			const observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
						const style = window.getComputedStyle(lightbox);
						const isVisible = style.display === 'flex' || style.display === 'block' || lightbox.classList.contains('active');
						
						if (isVisible) {
							document.body.classList.add('bd-lightbox-open');
							document.documentElement.style.overflow = 'hidden';
						} else {
							document.body.classList.remove('bd-lightbox-open');
							document.documentElement.style.overflow = '';
						}
					}
				});
			});
			
			observer.observe(lightbox, { attributes: true, attributeFilter: ['style', 'class'] });
			
			// Close button removes body class
			const closeBtn = lightbox.querySelector('.bd-lightbox-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function() {
					document.body.classList.remove('bd-lightbox-open');
					document.documentElement.style.overflow = '';
				});
			}
			
			// ESC key support
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && document.body.classList.contains('bd-lightbox-open')) {
					document.body.classList.remove('bd-lightbox-open');
					document.documentElement.style.overflow = '';
				}
			});
		});
	})();
	</script>


	<?php if (! empty($all_photo_ids)) : ?>
		<script>
			window.bdBusinessPhotos = <?php
										echo wp_json_encode(
											array_map(
												function ($id) {
													return array(
														'url' => wp_get_attachment_image_url($id, 'full'),
														'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: get_the_title(),
													);
												},
												$all_photo_ids
											)
										);
										?>;
		</script>
	<?php endif; ?>

<?php
endwhile;

get_footer();
