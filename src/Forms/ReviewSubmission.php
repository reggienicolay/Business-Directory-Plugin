<?php
namespace BD\Forms;

class ReviewSubmission {
    
    public function __construct() {
        add_shortcode('bd_submit_review', [$this, 'render_form']);
        add_filter('the_content', [$this, 'wrap_business_content'], 5);
        add_filter('the_content', [$this, 'add_reviews_to_content'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function wrap_business_content($content) {
        if (is_singular('bd_business') && is_main_query()) {
            return '<div class="bd-business-detail-wrapper">' . $content;
        }
        return $content;
    }
    
    public function enqueue_assets() {
        if (is_singular('bd_business')) {
            wp_enqueue_style('bd-review-form', BD_PLUGIN_URL . 'assets/css/review-form.css', [], BD_VERSION);
            wp_enqueue_script('bd-review-form', BD_PLUGIN_URL . 'assets/js/review-form.js', ['jquery'], BD_VERSION, true);
            
            wp_localize_script('bd-review-form', 'bdReview', [
                'restUrl' => rest_url('bd/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'helpfulNonce' => wp_create_nonce('bd_helpful_vote'),
                'turnstileSiteKey' => get_option('bd_turnstile_site_key', ''),
                'businessId' => get_the_ID(),
            ]);
            
            $site_key = get_option('bd_turnstile_site_key');
            if (!empty($site_key)) {
                wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
            }
            
            // Force reviews section visible immediately - override theme lazy load
            add_action('wp_footer', [$this, 'force_reviews_visible'], 1);
        }
    }
    
    /**
     * Force reviews section visible immediately - override theme lazy load
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
    
    public function add_reviews_to_content($content) {
        if (is_singular('bd_business') && is_main_query()) {
            return $content . $this->render_reviews_section() . '</div><!-- .bd-business-detail-wrapper -->';
        }
        return $content;
    }
    
    private function render_reviews_section() {
        $business_id = get_the_ID();
        $reviews = \BD\DB\ReviewsTable::get_by_business($business_id);
        
        $avg_rating = get_post_meta($business_id, 'bd_avg_rating', true);
        $review_count = get_post_meta($business_id, 'bd_review_count', true);
        $turnstile_enabled = !empty(get_option('bd_turnstile_site_key'));
        
        ob_start();
        ?>
        <div class="bd-reviews-section">
            <h2><?php _e('Reviews', 'business-directory'); ?></h2>
            
            <?php if ($review_count > 0): ?>
                <div class="bd-rating-summary">
                    <?php echo self::render_stars($avg_rating); ?>
                    <span><?php echo number_format($avg_rating, 1); ?> (<?php echo $review_count; ?> reviews)</span>
                </div>
            <?php endif; ?>
            
            <div class="bd-reviews-list">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="bd-review-card" data-review-id="<?php echo $review['id']; ?>">
                            <div class="bd-review-header">
                                <div class="bd-review-author-info">
                                    <?php if ($review['user_id']): ?>
                                        <?php echo get_avatar($review['user_id'], 48); ?>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo esc_html($review['author_name'] ?? 'Anonymous'); ?></strong>
                                        <div class="bd-review-date">
                                            <?php echo human_time_diff(strtotime($review['created_at']), current_time('timestamp')) . ' ago'; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php echo self::render_stars($review['rating']); ?>
                            </div>
                            
                            <?php if (!empty($review['title'])): ?>
                                <h4 class="bd-review-title"><?php echo esc_html($review['title']); ?></h4>
                            <?php endif; ?>
                            
                            <p class="bd-review-content"><?php echo esc_html($review['content']); ?></p>
                            
                            <?php if (!empty($review['photo_ids'])): ?>
                                <div class="bd-review-photos">
                                    <?php
                                    $photo_ids = explode(',', $review['photo_ids']);
                                    foreach ($photo_ids as $photo_id) {
                                        echo wp_get_attachment_image($photo_id, 'thumbnail');
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- HELPFUL VOTE BUTTON -->
                            <div class="bd-review-actions">
                                <button class="bd-helpful-btn" 
                                        data-review-id="<?php echo $review['id']; ?>"
                                        data-review-author-id="<?php echo $review['user_id']; ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                    </svg>
                                    <span class="bd-helpful-text">Helpful</span>
                                    <span class="bd-helpful-count"><?php echo $review['helpful_count'] ?? 0; ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="bd-no-reviews"><?php _e('Be the first to review!', 'business-directory'); ?></p>
                <?php endif; ?>
            </div>
            
            <h3 class="bd-write-review-title"><?php _e('Write a Review', 'business-directory'); ?></h3>
            
            <form id="bd-submit-review-form" class="bd-form" enctype="multipart/form-data">
                <input type="hidden" name="business_id" value="<?php echo $business_id; ?>" />
                
                <div class="bd-form-row">
                    <label><?php _e('Rating', 'business-directory'); ?> <span class="required">*</span></label>
                    <div class="bd-star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required />
                            <label for="star-<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="bd-form-row">
                    <label for="author_name"><?php _e('Your Name', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="text" id="author_name" name="author_name" required />
                </div>
                
                <div class="bd-form-row">
                    <label for="author_email"><?php _e('Email (not published)', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="email" id="author_email" name="author_email" required />
                </div>
                
                <div class="bd-form-row">
                    <label for="title"><?php _e('Review Title', 'business-directory'); ?></label>
                    <input type="text" id="title" name="title" />
                </div>
                
                <div class="bd-form-row">
                    <label for="content"><?php _e('Your Review', 'business-directory'); ?> <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="6" required></textarea>
                </div>
                
                <div class="bd-form-row">
                    <label for="photos"><?php _e('Add Photos', 'business-directory'); ?></label>
                    <input type="file" name="photos[]" accept="image/*" multiple />
                    <p class="description"><?php _e('Up to 3 photos, 5MB each', 'business-directory'); ?></p>
                </div>
                
                <?php if ($turnstile_enabled): ?>
                <div class="bd-form-row">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(get_option('bd_turnstile_site_key')); ?>"></div>
                </div>
                <?php endif; ?>
                
                <div class="bd-form-row">
                    <button type="submit" class="bd-btn bd-btn-primary"><?php _e('Submit Review', 'business-directory'); ?></button>
                </div>
                
                <div id="bd-review-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private static function render_stars($rating) {
        $output = '';
        for ($i = 1; $i <= 5; $i++) {
            $output .= $i <= $rating ? '★' : '☆';
        }
        return '<span class="bd-stars">' . $output . '</span>';
    }
}