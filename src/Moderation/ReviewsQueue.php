<?php
namespace BD\Moderation;

use BD\Gamification\ActivityTracker;

class ReviewsQueue {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_post_bd_approve_review', [$this, 'approve_review']);
        add_action('admin_post_bd_reject_review', [$this, 'reject_review']);
    }
    
    public function add_menu_page() {
        $pending_count = $this->get_pending_count();
        $menu_title = $pending_count > 0 ? 
            sprintf(__('Pending Reviews %s', 'business-directory'), "<span class='awaiting-mod'>$pending_count</span>") : 
            __('Pending Reviews', 'business-directory');
        
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Pending Reviews', 'business-directory'),
            $menu_title,
            'bd_moderate_reviews',
            'bd-pending-reviews',
            [$this, 'render_page']
        );
    }
    
    private function get_pending_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_reviews';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
    }
    
    public function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_reviews';
        
        $reviews = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50",
            ARRAY_A
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Pending Reviews', 'business-directory'); ?></h1>
            
            <?php if (empty($reviews)): ?>
                <p><?php _e('No pending reviews.', 'business-directory'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Business', 'business-directory'); ?></th>
                            <th><?php _e('Rating', 'business-directory'); ?></th>
                            <th><?php _e('Reviewer', 'business-directory'); ?></th>
                            <th><?php _e('Review', 'business-directory'); ?></th>
                            <th><?php _e('Date', 'business-directory'); ?></th>
                            <th><?php _e('Actions', 'business-directory'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <?php $business = get_post($review['business_id']); ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($review['business_id']); ?>">
                                        <?php echo $business ? esc_html($business->post_title) : 'Unknown'; ?>
                                    </a>
                                </td>
                                <td><?php echo str_repeat('★', $review['rating']); ?></td>
                                <td>
                                    <?php echo esc_html($review['author_name'] ?? 'Anonymous'); ?><br>
                                    <small><?php echo esc_html($review['author_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($review['title'])): ?>
                                        <strong><?php echo esc_html($review['title']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php echo esc_html(wp_trim_words($review['content'], 15)); ?>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($review['created_at'])); ?></td>
                                <td>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                        <?php wp_nonce_field('bd_approve_review'); ?>
                                        <input type="hidden" name="action" value="bd_approve_review" />
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>" />
                                        <button type="submit" class="button button-primary"><?php _e('Approve', 'business-directory'); ?></button>
                                    </form>
                                    
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                        <?php wp_nonce_field('bd_reject_review'); ?>
                                        <input type="hidden" name="action" value="bd_reject_review" />
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>" />
                                        <button type="submit" class="button"><?php _e('Reject', 'business-directory'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function approve_review() {
        check_admin_referer('bd_approve_review');
        
        if (!current_user_can('bd_moderate_reviews')) {
            wp_die(__('Unauthorized', 'business-directory'));
        }
        
        global $wpdb;
        $review_id = absint($_POST['review_id']);
        $table = $wpdb->prefix . 'bd_reviews';
        
        // Get review data before approving
        $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $review_id), ARRAY_A);
        
        if (!$review) {
            wp_die(__('Review not found', 'business-directory'));
        }
        
        // Approve the review
        $wpdb->update(
            $table,
            ['status' => 'approved'],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        
        // Award points for the review
        if ($review['user_id']) {
            ActivityTracker::track($review['user_id'], 'review_created', $review_id);
            
            if (!empty($review['photo_ids'])) {
                ActivityTracker::track($review['user_id'], 'review_with_photo', $review_id);
            }
            
            if (strlen($review['content']) > 100) {
                ActivityTracker::track($review['user_id'], 'review_detailed', $review_id);
            }
        }
        
        // Update aggregate rating
        $this->update_aggregate_rating($review['business_id']);
        
        wp_redirect(admin_url('admin.php?page=bd-pending-reviews&approved=1'));
        exit;
    }
    
    public function reject_review() {
        check_admin_referer('bd_reject_review');
        
        if (!current_user_can('bd_moderate_reviews')) {
            wp_die(__('Unauthorized', 'business-directory'));
        }
        
        global $wpdb;
        $review_id = absint($_POST['review_id']);
        $table = $wpdb->prefix . 'bd_reviews';
        
        $wpdb->update(
            $table,
            ['status' => 'rejected'],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        
        wp_redirect(admin_url('admin.php?page=bd-pending-reviews&rejected=1'));
        exit;
    }
    
    private function update_aggregate_rating($business_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_reviews';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
             FROM $table 
             WHERE business_id = %d AND status = 'approved'",
            $business_id
        ));
        
        if ($stats) {
            update_post_meta($business_id, 'bd_avg_rating', $stats->avg_rating);
            update_post_meta($business_id, 'bd_review_count', $stats->review_count);
        }
    }
}