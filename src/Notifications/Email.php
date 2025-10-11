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
