<?php
/**
 * Review Submission Form
 *
 * Handles review display and submission forms on business pages.
 *
 * @package BusinessDirectory
 */

namespace BD\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewSubmission {

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_shortcode( 'bd_submit_review', array( $this, 'render_form' ) );
		add_filter( 'the_content', array( $this, 'wrap_business_content' ), 5 );
		add_filter( 'the_content', array( $this, 'add_reviews_to_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Wrap business content in container div.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function wrap_business_content( $content ) {
		if ( is_singular( 'bd_business' ) && is_main_query() ) {
			return '<div class="bd-business-detail-wrapper">' . $content;
		}
		return $content;
	}

	/**
	 * Enqueue assets for review forms.
	 */
	public function enqueue_assets() {
		if ( is_singular( 'bd_business' ) ) {
			wp_enqueue_style( 'bd-review-form', BD_PLUGIN_URL . 'assets/css/review-form.css', array(), BD_VERSION );
			wp_enqueue_script( 'bd-review-form', BD_PLUGIN_URL . 'assets/js/review-form.js', array( 'jquery' ), BD_VERSION, true );

			// Get current user info for JS.
			$user_id     = get_current_user_id();
			$is_logged_in = $user_id > 0;

			wp_localize_script(
				'bd-review-form',
				'bdReview',
				array(
					'restUrl'          => rest_url( 'bd/v1/' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'helpfulNonce'     => wp_create_nonce( 'bd_helpful_vote' ),
					'turnstileSiteKey' => get_option( 'bd_turnstile_site_key', '' ),
					'businessId'       => get_the_ID(),
					'isLoggedIn'       => $is_logged_in,
				)
			);

			$site_key = get_option( 'bd_turnstile_site_key' );
			if ( ! empty( $site_key ) ) {
				wp_enqueue_script( 'turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
			}

			// Force reviews section visible immediately - override theme lazy load.
			add_action( 'wp_footer', array( $this, 'force_reviews_visible' ), 1 );
		}
	}

	/**
	 * Force reviews section visible immediately - override theme lazy load.
	 */
	public function force_reviews_visible() {
		?>
		<script>
		(function() {
			// Inject CSS immediately to prevent hiding
			const style = document.createElement('style');
			style.textContent = `
				.bd-reviews-section,
				.bd-reviews-list,
				.bd-review-card {
					opacity: 1 !important;
					visibility: visible !important;
					display: block !important;
					transform: none !important;
					animation: none !important;
				}
			`;
			document.head.appendChild(style);
			
			// Watch for reviews section being added to DOM
			const observer = new MutationObserver(function() {
				const reviews = document.querySelector('.bd-reviews-section');
				if (reviews) {
					reviews.style.cssText = 'opacity: 1 !important; visibility: visible !important; display: block !important;';
					observer.disconnect();
				}
			});
			
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
			
			// Also force visible immediately if already in DOM
			const reviews = document.querySelector('.bd-reviews-section');
			if (reviews) {
				reviews.style.cssText = 'opacity: 1 !important; visibility: visible !important; display: block !important;';
			}
		})();
		</script>
		<?php
	}

	/**
	 * Add reviews section to business content.
	 *
	 * @param string $content Post content.
	 * @return string Modified content with reviews.
	 */
	public function add_reviews_to_content( $content ) {
		if ( is_singular( 'bd_business' ) && is_main_query() ) {
			return $content . $this->render_reviews_section() . '</div><!-- .bd-business-detail-wrapper -->';
		}
		return $content;
	}

	/**
	 * Render standalone review form via shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'business_id' => 0,
			),
			$atts,
			'bd_submit_review'
		);

		$business_id = absint( $atts['business_id'] );

		if ( ! $business_id ) {
			return '<p class="bd-error">' . esc_html__( 'Please specify a business_id.', 'business-directory' ) . '</p>';
		}

		// Verify business exists and is published.
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type || 'publish' !== $business->post_status ) {
			return '<p class="bd-error">' . esc_html__( 'Business not found.', 'business-directory' ) . '</p>';
		}

		// Ensure assets are enqueued when using shortcode outside business pages.
		$this->enqueue_shortcode_assets( $business_id );

		return $this->render_review_form( $business_id );
	}

	/**
	 * Enqueue assets for shortcode usage.
	 *
	 * @param int $business_id Business ID.
	 */
	private function enqueue_shortcode_assets( $business_id ) {
		// Skip if already enqueued (on business pages).
		if ( wp_script_is( 'bd-review-form', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'bd-review-form', BD_PLUGIN_URL . 'assets/css/review-form.css', array(), BD_VERSION );
		wp_enqueue_script( 'bd-review-form', BD_PLUGIN_URL . 'assets/js/review-form.js', array( 'jquery' ), BD_VERSION, true );

		$user_id      = get_current_user_id();
		$is_logged_in = $user_id > 0;

		wp_localize_script(
			'bd-review-form',
			'bdReview',
			array(
				'restUrl'          => rest_url( 'bd/v1/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'helpfulNonce'     => wp_create_nonce( 'bd_helpful_vote' ),
				'turnstileSiteKey' => get_option( 'bd_turnstile_site_key', '' ),
				'businessId'       => $business_id,
				'isLoggedIn'       => $is_logged_in,
			)
		);

		$site_key = get_option( 'bd_turnstile_site_key' );
		if ( ! empty( $site_key ) ) {
			wp_enqueue_script( 'turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
	}

	/**
	 * Render the complete reviews section.
	 *
	 * @return string Reviews section HTML.
	 */
	private function render_reviews_section() {
		$business_id = get_the_ID();
		$reviews     = \BD\DB\ReviewsTable::get_by_business( $business_id );

		$avg_rating        = get_post_meta( $business_id, 'bd_avg_rating', true );
		$review_count      = (int) get_post_meta( $business_id, 'bd_review_count', true );
		$turnstile_enabled = ! empty( get_option( 'bd_turnstile_site_key' ) );

		ob_start();
		?>
		<div class="bd-reviews-section">
			<h2><?php esc_html_e( 'Reviews', 'business-directory' ); ?></h2>
			
			<?php if ( $review_count > 0 ) : ?>
				<div class="bd-rating-summary">
					<?php echo self::render_stars( $avg_rating ); // Safe - generates only stars. ?>
					<span><?php echo esc_html( number_format( (float) $avg_rating, 1 ) ); ?> (<?php echo esc_html( $review_count ); ?> <?php echo esc_html( _n( 'review', 'reviews', $review_count, 'business-directory' ) ); ?>)</span>
				</div>
			<?php endif; ?>
			
			<div class="bd-reviews-list">
				<?php if ( ! empty( $reviews ) ) : ?>
					<?php foreach ( $reviews as $review ) : ?>
						<?php echo $this->render_single_review( $review ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="bd-no-reviews"><?php esc_html_e( 'Be the first to review!', 'business-directory' ); ?></p>
				<?php endif; ?>
			</div>
			
			<?php echo $this->render_review_form( $business_id, $turnstile_enabled ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single review card.
	 *
	 * @param array $review Review data.
	 * @return string Review card HTML.
	 */
	private function render_single_review( $review ) {
		$review_id   = absint( $review['id'] );
		$user_id     = absint( $review['user_id'] ?? 0 );
		$author_name = ! empty( $review['author_name'] ) ? $review['author_name'] : __( 'Anonymous', 'business-directory' );
		$helpful_count = absint( $review['helpful_count'] ?? 0 );

		ob_start();
		?>
		<div class="bd-review-card" data-review-id="<?php echo esc_attr( $review_id ); ?>">
			<div class="bd-review-header">
				<div class="bd-review-author-info">
					<?php if ( $user_id ) : ?>
						<?php echo get_avatar( $user_id, 48 ); ?>
					<?php else : ?>
						<div class="bd-avatar-placeholder"></div>
					<?php endif; ?>
					<div>
						<strong><?php echo esc_html( $author_name ); ?></strong>
						<div class="bd-review-date">
							<?php
							$created_time = strtotime( $review['created_at'] );
							if ( $created_time ) {
								echo esc_html( human_time_diff( $created_time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'business-directory' ) );
							}
							?>
						</div>
					</div>
				</div>
				<?php echo self::render_stars( $review['rating'] ); ?>
			</div>
			
			<?php if ( ! empty( $review['title'] ) ) : ?>
				<h4 class="bd-review-title"><?php echo esc_html( $review['title'] ); ?></h4>
			<?php endif; ?>
			
			<p class="bd-review-content"><?php echo esc_html( $review['content'] ); ?></p>
			
			<?php if ( ! empty( $review['photo_ids'] ) ) : ?>
				<div class="bd-review-photos">
					<?php
					$photo_ids = array_map( 'absint', explode( ',', $review['photo_ids'] ) );
					foreach ( $photo_ids as $photo_id ) {
						if ( $photo_id ) {
							echo wp_get_attachment_image( $photo_id, 'thumbnail', false, array( 'class' => 'bd-review-photo' ) );
						}
					}
					?>
				</div>
			<?php endif; ?>
			
			<!-- HELPFUL VOTE BUTTON -->
			<div class="bd-review-actions">
				<button class="bd-helpful-btn" 
						data-review-id="<?php echo esc_attr( $review_id ); ?>"
						data-review-author-id="<?php echo esc_attr( $user_id ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
					</svg>
					<span class="bd-helpful-text"><?php esc_html_e( 'Helpful', 'business-directory' ); ?></span>
					<span class="bd-helpful-count"><?php echo esc_html( $helpful_count ); ?></span>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the review submission form.
	 *
	 * @param int  $business_id       Business ID.
	 * @param bool $turnstile_enabled Whether Turnstile CAPTCHA is enabled.
	 * @return string Form HTML.
	 */
	private function render_review_form( $business_id, $turnstile_enabled = null ) {
		if ( null === $turnstile_enabled ) {
			$turnstile_enabled = ! empty( get_option( 'bd_turnstile_site_key' ) );
		}

		$user_id      = get_current_user_id();
		$is_logged_in = $user_id > 0;
		$display_name = '';

		if ( $is_logged_in ) {
			$user = get_userdata( $user_id );

			// Safety check - user could be deleted mid-session.
			if ( ! $user ) {
				$is_logged_in = false;
			} else {
				// Get display name: BD nickname → WP display_name → user_login.
				$bd_nickname  = get_user_meta( $user_id, 'bd_display_name', true );
				$display_name = ! empty( $bd_nickname ) ? $bd_nickname : ( $user->display_name ?: $user->user_login );
			}
		}

		ob_start();
		?>
		<h3 class="bd-write-review-title"><?php esc_html_e( 'Write a Review', 'business-directory' ); ?></h3>
		
		<form id="bd-submit-review-form" class="bd-form" enctype="multipart/form-data">
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
						<?php echo get_avatar( $user_id, 32 ); ?>
						<span class="bd-user-display-name"><?php echo esc_html( $display_name ); ?></span>
						<button type="button" class="bd-change-nickname-btn" id="bd-change-nickname-btn">
							<?php esc_html_e( 'Change display name', 'business-directory' ); ?>
						</button>
					</div>
				</div>
				
				<!-- Hidden nickname editor (shown via JS) -->
				<div class="bd-form-row bd-nickname-editor" id="bd-nickname-editor" style="display: none;">
					<label for="bd_display_name"><?php esc_html_e( 'Display Name', 'business-directory' ); ?></label>
					<input type="text" 
						   id="bd_display_name" 
						   name="bd_display_name" 
						   value="<?php echo esc_attr( $display_name ); ?>" 
						   maxlength="100"
						   placeholder="<?php esc_attr_e( 'Enter your display name', 'business-directory' ); ?>" />
					<p class="description"><?php esc_html_e( 'This name will be shown with your reviews.', 'business-directory' ); ?></p>
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
						/* translators: %s: login URL */
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
			
			<?php if ( $turnstile_enabled ) : ?>
			<div class="bd-form-row">
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( get_option( 'bd_turnstile_site_key' ) ); ?>"></div>
			</div>
			<?php endif; ?>
			
			<div class="bd-form-row">
				<button type="submit" class="bd-btn bd-btn-primary"><?php esc_html_e( 'Submit Review', 'business-directory' ); ?></button>
			</div>
			
			<div id="bd-review-message"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render star rating display.
	 *
	 * @param float $rating Rating value (1-5).
	 * @return string HTML stars.
	 */
	private static function render_stars( $rating ) {
		$rating = (float) $rating;
		$output = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$output .= $i <= $rating ? '★' : '☆';
		}
		return '<span class="bd-stars">' . $output . '</span>';
	}
}
