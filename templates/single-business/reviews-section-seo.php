<?php
/**
 * SEO-Enhanced Reviews Section for immersive.php
 *
 * INSTRUCTIONS:
 * Replace lines 560-608 in templates/single-business/immersive.php
 * with this code block.
 *
 * REQUIREMENTS:
 * - $reviews array should be defined in parent scope (from DB query)
 * - Must be within The Loop (get_the_title() and get_permalink() work)
 *
 * SCHEMA.ORG STRUCTURE:
 * - Each review is a complete Review entity
 * - itemReviewed links to the LocalBusiness
 * - datePublished is a direct child of Review (not inside Person)
 * - author contains Person with name
 * - reviewRating contains Rating with ratingValue
 * - reviewBody contains the review text
 *
 * @package    BusinessDirectory
 * @subpackage Templates
 * @since      0.1.8
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// === DEFENSIVE: Ensure $reviews exists and is an array ===
if ( ! isset( $reviews ) || ! is_array( $reviews ) ) {
	$reviews = array();
}

// === GET BUSINESS INFO (with Loop validation) ===
$business_name = get_the_title();
$business_url  = get_permalink();

// Defensive: Validate we're in The Loop with valid post context.
if ( empty( $business_name ) || false === $business_url ) {
	// Not in a valid Loop context. Fall back gracefully.
	$business_name = '';
	$business_url  = '';
}

// Escape once for reuse in schema.
$business_name_attr = esc_attr( $business_name );
$business_url_esc   = esc_url( $business_url );

// CSS for visually-hidden elements (accessible to screen readers, hidden visually).
// Uses clip-path for modern browsers, with clip fallback for older browsers.
$visually_hidden_style = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap;border:0;';
?>
				<!-- Review List -->
				<?php if ( ! empty( $reviews ) ) : ?>
					<div class="bd-reviews-list">
						<?php
						$review_index = 0;

						foreach ( $reviews as $review ) :
							// === DEFENSIVE: Skip non-array entries ===
							if ( ! is_array( $review ) ) {
								continue;
							}

							$review_index++;

							// =================================================================
							// DATA EXTRACTION WITH STRICT TYPE SAFETY
							// =================================================================

							// Review ID (integer).
							$review_id = isset( $review['id'] ) ? absint( $review['id'] ) : 0;

							// User ID (integer).
							$user_id = isset( $review['user_id'] ) ? absint( $review['user_id'] ) : 0;

							// Reviewer name (string, trimmed).
							$reviewer_name = '';
							if ( isset( $review['reviewer_name'] ) && is_string( $review['reviewer_name'] ) ) {
								$reviewer_name = trim( $review['reviewer_name'] );
							}
							if ( empty( $reviewer_name ) ) {
								$reviewer_name = __( 'Anonymous', 'business-directory' );
							}

							// Rating (integer 1-5, or 0 for no rating).
							$rating_raw = isset( $review['rating'] ) ? absint( $review['rating'] ) : 0;
							$has_rating = ( $rating_raw >= 1 && $rating_raw <= 5 );
							$rating     = $has_rating ? $rating_raw : 0;

							// Review title (string, trimmed).
							$review_title = '';
							if ( isset( $review['title'] ) && is_string( $review['title'] ) ) {
								$review_title = trim( $review['title'] );
							}

							// Review body (string, trimmed).
							$review_body = '';
							if ( isset( $review['content'] ) && is_string( $review['content'] ) ) {
								$review_body = trim( $review['content'] );
							}

							// Created date (string).
							$created_at = '';
							if ( isset( $review['created_at'] ) && is_string( $review['created_at'] ) ) {
								$created_at = $review['created_at'];
							}

							// =================================================================
							// VALIDATION: Skip invalid reviews
							// =================================================================

							// A review must have content OR a valid rating to be displayed.
							if ( empty( $review_body ) && ! $has_rating ) {
								continue;
							}

							// =================================================================
							// DATE PARSING (with validation)
							// =================================================================

							$review_timestamp    = false;
							$review_date_iso     = '';
							$review_date_display = '';

							if ( ! empty( $created_at ) ) {
								$parsed_timestamp = strtotime( $created_at );

								// Validate: must be positive and reasonable (after year 2000).
								if ( false !== $parsed_timestamp && $parsed_timestamp > 946684800 ) {
									$review_timestamp    = $parsed_timestamp;
									$review_date_iso     = wp_date( 'c', $review_timestamp );
									$review_date_display = wp_date( 'F Y', $review_timestamp );
								}
							}

							// =================================================================
							// AUTHOR INFO
							// =================================================================

							// Initials - multibyte safe.
							$initials = 'A'; // Default for Anonymous.
							if ( function_exists( 'mb_substr' ) ) {
								$initials = mb_strtoupper( mb_substr( $reviewer_name, 0, 1, 'UTF-8' ), 'UTF-8' );
							} else {
								$first_char = substr( $reviewer_name, 0, 1 );
								if ( false !== $first_char ) {
									$initials = strtoupper( $first_char );
								}
							}

							// Avatar URL.
							$avatar_url = '';
							if ( $user_id > 0 ) {
								$avatar_result = get_avatar_url( $user_id, array( 'size' => 88 ) );
								if ( is_string( $avatar_result ) && ! empty( $avatar_result ) ) {
									$avatar_url = $avatar_result;
								}
							}

							// =================================================================
							// ELEMENT ID (for deep-linking)
							// =================================================================

							// Unique ID. Fallback to index if no database ID.
							$element_id = $review_id > 0
								? 'review-' . $review_id
								: 'review-item-' . $review_index;

							// =================================================================
							// STAR DISPLAY (pre-computed for consistency)
							// =================================================================

							// Stars are Unicode literals, not user input - safe to output directly.
							$filled_stars = $has_rating ? str_repeat( '★', $rating ) : '';
							$empty_stars  = $has_rating ? str_repeat( '☆', 5 - $rating ) : str_repeat( '☆', 5 );
							?>

							<!-- Review Card with Schema.org microdata -->
							<div class="bd-review-card"
								 id="<?php echo esc_attr( $element_id ); ?>"
								 itemscope
								 itemtype="https://schema.org/Review">

								<?php // Hidden Schema Elements (visually hidden, accessible to crawlers). ?>
								<span itemprop="itemReviewed" itemscope itemtype="https://schema.org/LocalBusiness"
									  style="<?php echo esc_attr( $visually_hidden_style ); ?>">
									<meta itemprop="name" content="<?php echo $business_name_attr; ?>">
									<?php if ( ! empty( $business_url_esc ) ) : ?>
										<link itemprop="url" href="<?php echo $business_url_esc; ?>">
									<?php endif; ?>
								</span>

								<?php if ( ! empty( $review_date_iso ) ) : ?>
									<meta itemprop="datePublished" content="<?php echo esc_attr( $review_date_iso ); ?>">
								<?php endif; ?>

								<div class="bd-review-top">
									<!-- Author with Person schema -->
									<div class="bd-reviewer" itemprop="author" itemscope itemtype="https://schema.org/Person">
										<?php if ( ! empty( $avatar_url ) ) : ?>
											<img src="<?php echo esc_url( $avatar_url ); ?>"
												 alt="<?php echo esc_attr( sprintf( __( '%s profile photo', 'business-directory' ), $reviewer_name ) ); ?>"
												 class="bd-reviewer-avatar"
												 width="44"
												 height="44"
												 loading="lazy">
										<?php else : ?>
											<div class="bd-reviewer-avatar"
												 role="img"
												 aria-label="<?php echo esc_attr( $reviewer_name ); ?>">
												<?php echo esc_html( $initials ); ?>
											</div>
										<?php endif; ?>

										<div class="bd-reviewer-info">
											<span class="bd-reviewer-name" itemprop="name">
												<?php echo esc_html( $reviewer_name ); ?>
											</span>
											<?php if ( ! empty( $review_date_display ) ) : ?>
												<time class="bd-reviewer-date"
													  datetime="<?php echo esc_attr( $review_date_iso ); ?>">
													<?php echo esc_html( $review_date_display ); ?>
												</time>
											<?php endif; ?>
										</div>
									</div>

									<!-- Rating -->
									<?php if ( $has_rating ) : ?>
										<span class="bd-review-stars"
											  itemprop="reviewRating"
											  itemscope
											  itemtype="https://schema.org/Rating">
											<meta itemprop="worstRating" content="1">
											<meta itemprop="bestRating" content="5">
											<meta itemprop="ratingValue" content="<?php echo esc_attr( $rating ); ?>">
											<span aria-label="<?php echo esc_attr( sprintf( __( '%d out of 5 stars', 'business-directory' ), $rating ) ); ?>">
												<?php
												// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unicode literals, no user input.
												echo $filled_stars . $empty_stars;
												?>
											</span>
										</span>
									<?php else : ?>
										<span class="bd-review-stars bd-review-stars--no-rating"
											  aria-label="<?php esc_attr_e( 'No rating provided', 'business-directory' ); ?>">
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unicode literals, no user input.
											echo '<span aria-hidden="true">' . $empty_stars . '</span>';
											?>
										</span>
									<?php endif; ?>
								</div>

								<?php if ( ! empty( $review_title ) ) : ?>
									<h4 class="bd-review-title" itemprop="name">
										<?php echo esc_html( $review_title ); ?>
									</h4>
								<?php endif; ?>

								<?php if ( ! empty( $review_body ) ) : ?>
									<p class="bd-review-body" itemprop="reviewBody">
										<?php echo esc_html( $review_body ); ?>
									</p>
								<?php endif; ?>

								<?php
								// =============================================================
								// REVIEW PHOTOS
								// =============================================================
								$review_photos = array();

								if ( isset( $review['photos'] ) && ! empty( $review['photos'] ) ) {
									$photos_raw = $review['photos'];

									// Handle serialized data safely.
									if ( is_string( $photos_raw ) ) {
										$photos_unserialized = maybe_unserialize( $photos_raw );
										if ( is_array( $photos_unserialized ) ) {
											$review_photos = $photos_unserialized;
										}
									} elseif ( is_array( $photos_raw ) ) {
										$review_photos = $photos_raw;
									}
								}

								if ( ! empty( $review_photos ) ) :
									$displayed_photo_count = 0;
									?>
									<div class="bd-review-photos">
										<?php foreach ( $review_photos as $photo_url ) : ?>
											<?php
											// === TYPE CHECK: Must be non-empty string ===
											if ( ! is_string( $photo_url ) ) {
												continue;
											}

											$photo_url = trim( $photo_url );

											if ( empty( $photo_url ) ) {
												continue;
											}

											// === URL VALIDATION ===
											// Allow: absolute URLs (http/https) and root-relative URLs.
											$is_absolute = (bool) filter_var( $photo_url, FILTER_VALIDATE_URL );
											$is_relative = (
												strlen( $photo_url ) > 1 &&
												'/' === $photo_url[0] &&
												'/' !== $photo_url[1]
											);

											if ( ! $is_absolute && ! $is_relative ) {
												continue;
											}

											// Increment only for valid, displayed photos.
											$displayed_photo_count++;
											?>
											<img src="<?php echo esc_url( $photo_url ); ?>"
												 alt="<?php echo esc_attr( sprintf( __( 'Photo %1$d from %2$s review of %3$s', 'business-directory' ), $displayed_photo_count, $reviewer_name, $business_name ) ); ?>"
												 class="bd-review-photo"
												 itemprop="image"
												 loading="lazy">
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

							</div><!-- .bd-review-card -->

						<?php endforeach; ?>
					</div><!-- .bd-reviews-list -->

				<?php else : ?>

					<p class="bd-no-reviews">
						<?php esc_html_e( 'Be the first to review this business!', 'business-directory' ); ?>
					</p>

				<?php endif; ?>
