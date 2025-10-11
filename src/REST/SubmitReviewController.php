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
