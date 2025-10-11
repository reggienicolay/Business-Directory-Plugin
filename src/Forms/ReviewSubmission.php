<?php
namespace BD\Forms;

class ReviewSubmission {
    
    public function __construct() {
        add_filter('the_content', [$this, 'add_reviews_to_content']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        if (is_singular('bd_business')) {
            wp_enqueue_style('bd-forms', BD_PLUGIN_URL . 'assets/css/forms.css', [], BD_VERSION);
            wp_enqueue_script('bd-review-form', BD_PLUGIN_URL . 'assets/js/review-form.js', ['jquery'], BD_VERSION, true);
            
            wp_localize_script('bd-review-form', 'bdReview', [
                'restUrl' => rest_url('bd/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'turnstileSiteKey' => get_option('bd_turnstile_site_key', ''),
                'businessId' => get_the_ID(),
            ]);
            
            $site_key = get_option('bd_turnstile_site_key');
            if (!empty($site_key)) {
                wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
            }
        }
    }
    
    public function add_reviews_to_content($content) {
        if (is_singular('bd_business') && is_main_query()) {
            return $content . $this->render_reviews_section();
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
                        <div class="bd-review-card">
                            <div class="bd-review-header">
                                <strong><?php echo esc_html($review['author_name'] ?? 'Anonymous'); ?></strong>
                                <?php echo self::render_stars($review['rating']); ?>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                                <h4><?php echo esc_html($review['title']); ?></h4>
                            <?php endif; ?>
                            <p><?php echo esc_html($review['content']); ?></p>
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
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php _e('Be the first to review!', 'business-directory'); ?></p>
                <?php endif; ?>
            </div>
            
            <h3><?php _e('Write a Review', 'business-directory'); ?></h3>
            
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
                    <label><?php _e('Your Name', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="text" name="author_name" required />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Email (not published)', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="email" name="author_email" required />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Review Title', 'business-directory'); ?></label>
                    <input type="text" name="title" />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Your Review', 'business-directory'); ?> <span class="required">*</span></label>
                    <textarea name="content" rows="5" required></textarea>
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Add Photos (optional)', 'business-directory'); ?></label>
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
