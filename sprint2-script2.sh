#!/bin/bash

################################################################################
# Sprint 2 Week 1 - Part 2: Forms & REST API
# Run this SECOND (after Part 1)
################################################################################

set -e

echo "🚀 Sprint 2 Week 1 - Part 2/3: Forms & REST API"
echo "================================================"
echo ""

if [[ ! -f "business-directory.php" ]]; then
    echo "❌ Error: Run from plugin directory"
    exit 1
fi

################################################################################
# EMAIL NOTIFICATIONS
################################################################################

echo "📝 Creating Email notifications..."

cat > src/Notifications/Email.php << 'EMAILEOF'
<?php
namespace BD\Notifications;

class Email {
    
    public static function notify_new_submission($submission_id) {
        $submission = \BD\DB\SubmissionsTable::get($submission_id);
        
        if (!$submission) {
            return false;
        }
        
        $to = self::get_notification_emails();
        $subject = sprintf(
            __('[%s] New Business Submission', 'business-directory'),
            get_bloginfo('name')
        );
        
        $moderate_url = admin_url('admin.php?page=bd-pending-submissions');
        
        $message = sprintf(
            "New business submission:\n\nBusiness: %s\nSubmitted by: %s (%s)\n\nModerate: %s",
            $submission['business_data']['title'] ?? 'Untitled',
            $submission['submitter_name'] ?? 'Anonymous',
            $submission['submitter_email'] ?? '',
            $moderate_url
        );
        
        return wp_mail($to, $subject, $message);
    }
    
    public static function notify_new_review($review_id) {
        global $wpdb;
        
        $reviews_table = $wpdb->prefix . 'bd_reviews';
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reviews_table WHERE id = %d",
            $review_id
        ), ARRAY_A);
        
        if (!$review) {
            return false;
        }
        
        $business = get_post($review['business_id']);
        
        $to = self::get_notification_emails();
        $subject = sprintf(
            __('[%s] New Review', 'business-directory'),
            get_bloginfo('name')
        );
        
        $moderate_url = admin_url('admin.php?page=bd-pending-reviews');
        
        $message = sprintf(
            "New review submitted:\n\nBusiness: %s\nRating: %d/5\nReviewer: %s\n\nModerate: %s",
            $business->post_title,
            $review['rating'],
            $review['author_name'] ?? 'Anonymous',
            $moderate_url
        );
        
        return wp_mail($to, $subject, $message);
    }
    
    private static function get_notification_emails() {
        $emails = get_option('bd_notification_emails', '');
        
        if (empty($emails)) {
            return get_option('admin_email');
        }
        
        return array_map('trim', explode(',', $emails));
    }
}
EMAILEOF

################################################################################
# BUSINESS SUBMISSION FORM
################################################################################

echo "📝 Creating BusinessSubmission form..."

cat > src/Forms/BusinessSubmission.php << 'BUSINESSFORMEOF'
<?php
namespace BD\Forms;

class BusinessSubmission {
    
    public function __construct() {
        add_shortcode('bd_submit_business', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        if (is_singular() || is_page()) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'bd_submit_business')) {
                wp_enqueue_style('bd-forms', BD_PLUGIN_URL . 'assets/css/forms.css', [], BD_VERSION);
                wp_enqueue_script('bd-submission-form', BD_PLUGIN_URL . 'assets/js/submission-form.js', ['jquery'], BD_VERSION, true);
                
                wp_localize_script('bd-submission-form', 'bdSubmission', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'restUrl' => rest_url('bd/v1/'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'turnstileSiteKey' => get_option('bd_turnstile_site_key', ''),
                ]);
                
                $site_key = get_option('bd_turnstile_site_key');
                if (!empty($site_key)) {
                    wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
                }
            }
        }
    }
    
    public function render_form() {
        $turnstile_enabled = !empty(get_option('bd_turnstile_site_key'));
        
        ob_start();
        ?>
        <div class="bd-submission-form-wrapper">
            <h2><?php _e('Submit Your Business', 'business-directory'); ?></h2>
            
            <form id="bd-submit-business-form" class="bd-form">
                <div class="bd-form-row">
                    <label><?php _e('Business Name', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="text" name="title" required />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Description', 'business-directory'); ?> <span class="required">*</span></label>
                    <textarea name="description" rows="5" required></textarea>
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Category', 'business-directory'); ?> <span class="required">*</span></label>
                    <select name="category" required>
                        <option value="">Select...</option>
                        <?php
                        $categories = get_terms(['taxonomy' => 'bd_category', 'hide_empty' => false]);
                        foreach ($categories as $cat) {
                            echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Address', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="text" name="address" required />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('City', 'business-directory'); ?> <span class="required">*</span></label>
                    <input type="text" name="city" required />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Phone', 'business-directory'); ?></label>
                    <input type="tel" name="phone" />
                </div>
                
                <div class="bd-form-row">
                    <label><?php _e('Website', 'business-directory'); ?></label>
                    <input type="url" name="website" />
                </div>
                
                <div class="bd-form-section">
                    <h3><?php _e('Your Contact Info', 'business-directory'); ?></h3>
                    
                    <div class="bd-form-row">
                        <label><?php _e('Your Name', 'business-directory'); ?> <span class="required">*</span></label>
                        <input type="text" name="submitter_name" required />
                    </div>
                    
                    <div class="bd-form-row">
                        <label><?php _e('Your Email', 'business-directory'); ?> <span class="required">*</span></label>
                        <input type="email" name="submitter_email" required />
                    </div>
                </div>
                
                <?php if ($turnstile_enabled): ?>
                <div class="bd-form-row">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(get_option('bd_turnstile_site_key')); ?>"></div>
                </div>
                <?php endif; ?>
                
                <div class="bd-form-row">
                    <button type="submit" class="bd-btn bd-btn-primary"><?php _e('Submit Business', 'business-directory'); ?></button>
                </div>
                
                <div id="bd-submission-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
BUSINESSFORMEOF

################################################################################
# REVIEW SUBMISSION FORM
################################################################################

echo "📝 Creating ReviewSubmission form..."

cat > src/Forms/ReviewSubmission.php << 'REVIEWFORMEOF'
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
REVIEWFORMEOF

################################################################################
# REST API CONTROLLERS
################################################################################

echo "📝 Creating REST controllers..."

cat > src/REST/SubmitBusinessController.php << 'SUBMITBUSINESSEOF'
<?php
namespace BD\REST;

class SubmitBusinessController {
    
    public static function register() {
        register_rest_route('bd/v1', '/submit-business', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'submit'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public static function submit($request) {
        $ip = \BD\Security\RateLimit::get_client_ip();
        $rate_check = \BD\Security\RateLimit::check('submit_business', $ip, 3, 3600);
        
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        $turnstile_token = $request->get_param('turnstile_token');
        if (!empty(get_option('bd_turnstile_site_key')) && !empty($turnstile_token)) {
            $captcha_check = \BD\Security\Captcha::verify_turnstile($turnstile_token);
            if (is_wp_error($captcha_check)) {
                return $captcha_check;
            }
        }
        
        $business_data = [
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'category' => absint($request->get_param('category')),
            'address' => sanitize_text_field($request->get_param('address')),
            'city' => sanitize_text_field($request->get_param('city')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            'website' => esc_url_raw($request->get_param('website')),
        ];
        
        if (empty($business_data['title']) || empty($business_data['description'])) {
            return new \WP_Error('missing_required', __('Please fill in all required fields.', 'business-directory'));
        }
        
        $submission_id = \BD\DB\SubmissionsTable::insert([
            'business_data' => $business_data,
            'submitter_name' => sanitize_text_field($request->get_param('submitter_name')),
            'submitter_email' => sanitize_email($request->get_param('submitter_email')),
            'ip_address' => $ip,
        ]);
        
        if (!$submission_id) {
            return new \WP_Error('submission_failed', __('Failed to save submission.', 'business-directory'));
        }
        
        \BD\Notifications\Email::notify_new_submission($submission_id);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Thank you! Your submission is pending review.', 'business-directory'),
        ]);
    }
}
SUBMITBUSINESSEOF

cat > src/REST/SubmitReviewController.php << 'SUBMITREVIEWEOF'
<?php
namespace BD\REST;

class SubmitReviewController {
    
    public static function register() {
        register_rest_route('bd/v1', '/submit-review', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'submit'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public static function submit($request) {
        $ip = \BD\Security\RateLimit::get_client_ip();
        $rate_check = \BD\Security\RateLimit::check('submit_review', $ip, 5, 3600);
        
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        $turnstile_token = $request->get_param('turnstile_token');
        if (!empty(get_option('bd_turnstile_site_key')) && !empty($turnstile_token)) {
            $captcha_check = \BD\Security\Captcha::verify_turnstile($turnstile_token);
            if (is_wp_error($captcha_check)) {
                return $captcha_check;
            }
        }
        
        $business_id = absint($request->get_param('business_id'));
        $rating = absint($request->get_param('rating'));
        
        if (!$business_id || $rating < 1 || $rating > 5) {
            return new \WP_Error('invalid_data', __('Invalid data.', 'business-directory'));
        }
        
        $photo_ids = [];
        if (!empty($_FILES['photos'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $files = $_FILES['photos'];
            $file_count = is_array($files['name']) ? count($files['name']) : 1;
            
            for ($i = 0; $i < min($file_count, 3); $i++) {
                if (isset($files['error'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    
                    if (!isset($upload['error'])) {
                        $attachment_id = wp_insert_attachment([
                            'post_mime_type' => $upload['type'],
                            'post_title' => sanitize_file_name($upload['file']),
                            'post_status' => 'inherit',
                        ], $upload['file']);
                        
                        if ($attachment_id) {
                            wp_generate_attachment_metadata($attachment_id, $upload['file']);
                            $photo_ids[] = $attachment_id;
                        }
                    }
                }
            }
        }
        
        $review_data = [
            'business_id' => $business_id,
            'rating' => $rating,
            'author_name' => sanitize_text_field($request->get_param('author_name')),
            'author_email' => sanitize_email($request->get_param('author_email')),
            'title' => sanitize_text_field($request->get_param('title')),
            'content' => sanitize_textarea_field($request->get_param('content')),
            'photo_ids' => !empty($photo_ids) ? implode(',', $photo_ids) : null,
            'ip_address' => $ip,
        ];
        
        $review_id = \BD\DB\ReviewsTable::insert($review_data);
        
        if (!$review_id) {
            return new \WP_Error('submission_failed', __('Failed to save review.', 'business-directory'));
        }
        
        \BD\Notifications\Email::notify_new_review($review_id);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Thank you! Your review is pending approval.', 'business-directory'),
        ]);
    }
}
SUBMITREVIEWEOF

################################################################################
# JAVASCRIPT FILES
################################################################################

echo "📝 Creating JavaScript files..."

cat > assets/js/submission-form.js << 'SUBMISSIONJSEOF'
(function($) {
    'use strict';
    
    $('#bd-submit-business-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const message = $('#bd-submission-message');
        
        button.prop('disabled', true).text('Submitting...');
        message.html('');
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => data[key] = value);
        
        if (window.turnstile && bdSubmission.turnstileSiteKey) {
            data.turnstile_token = turnstile.getResponse();
        }
        
        $.ajax({
            url: bdSubmission.restUrl + 'submit-business',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bdSubmission.nonce);
            },
            success: function(response) {
                message.html('<div class="bd-success">' + response.message + '</div>');
                form[0].reset();
                if (window.turnstile) turnstile.reset();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.message || 'Submission failed. Please try again.';
                message.html('<div class="bd-error">' + error + '</div>');
                if (window.turnstile) turnstile.reset();
            },
            complete: function() {
                button.prop('disabled', false).text('Submit Business');
            }
        });
    });
    
})(jQuery);
SUBMISSIONJSEOF

cat > assets/js/review-form.js << 'REVIEWJSEOF'
(function($) {
    'use strict';
    
    $('#bd-submit-review-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const message = $('#bd-review-message');
        
        button.prop('disabled', true).text('Submitting...');
        message.html('');
        
        const formData = new FormData(this);
        
        if (window.turnstile && bdReview.turnstileSiteKey) {
            formData.append('turnstile_token', turnstile.getResponse());
        }
        
        $.ajax({
            url: bdReview.restUrl + 'submit-review',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bdReview.nonce);
            },
            success: function(response) {
                message.html('<div class="bd-success">' + response.message + '</div>');
                form[0].reset();
                if (window.turnstile) turnstile.reset();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.message || 'Submission failed. Please try again.';
                message.html('<div class="bd-error">' + error + '</div>');
                if (window.turnstile) turnstile.reset();
            },
            complete: function() {
                button.prop('disabled', false).text('Submit Review');
            }
        });
    });
    
    // Star rating interaction
    $('.bd-star-rating input').on('change', function() {
        $('.bd-star-rating label').removeClass('selected');
        $(this).parent().find('label').slice($(this).val() - 5).addClass('selected');
    });
    
})(jQuery);
REVIEWJSEOF

################################################################################
# CSS FILE
################################################################################

echo "📝 Creating CSS file..."

cat > assets/css/forms.css << 'FORMSCSSEOF'
/* Business Directory Forms */

.bd-form {
    max-width: 600px;
}

.bd-form-row {
    margin-bottom: 20px;
}

.bd-form-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.bd-form-row input[type="text"],
.bd-form-row input[type="email"],
.bd-form-row input[type="url"],
.bd-form-row input[type="tel"],
.bd-form-row textarea,
.bd-form-row select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.bd-form-row textarea {
    resize: vertical;
}

.bd-form-section {
    border-top: 2px solid #f0f0f0;
    padding-top: 20px;
    margin-top: 30px;
}

.bd-form-section h3 {
    margin-top: 0;
}

.required {
    color: #d00;
}

.bd-btn {
    display: inline-block;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.bd-btn-primary {
    background: #0073aa;
    color: white;
}

.bd-btn-primary:hover {
    background: #005a87;
}

.bd-success {
    padding: 12px;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    margin-top: 15px;
}

.bd-error {
    padding: 12px;
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    margin-top: 15px;
}

/* Star Rating */
.bd-star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    font-size: 30px;
}

.bd-star-rating input {
    display: none;
}

.bd-star-rating label {
    cursor: pointer;
    color: #ddd;
}

.bd-star-rating label:hover,
.bd-star-rating label:hover ~ label,
.bd-star-rating input:checked ~ label {
    color: #ffc107;
}

/* Reviews Display */
.bd-reviews-section {
    margin-top: 40px;
}

.bd-rating-summary {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 18px;
}

.bd-stars {
    color: #ffc107;
    font-size: 20px;
}

.bd-review-card {
    border: 1px solid #ddd;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
}

.bd-review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.bd-review-photos {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.bd-review-photos img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
}

.description {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}
FORMSCSSEOF

echo ""
echo "✅ Part 2 Complete!"
echo ""
echo "NEXT STEPS:"
echo "1. Run Part 3: bash sprint2-script3.sh"
echo ""
