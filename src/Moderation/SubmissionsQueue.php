<?php
namespace BD\Moderation;

class SubmissionsQueue {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_post_bd_approve_submission', [$this, 'approve_submission']);
        add_action('admin_post_bd_reject_submission', [$this, 'reject_submission']);
    }
    
    public function add_menu_page() {
        $pending_count = $this->get_pending_count();
        $menu_title = $pending_count > 0 ? 
            sprintf(__('Pending Submissions %s', 'business-directory'), "<span class='awaiting-mod'>$pending_count</span>") : 
            __('Pending Submissions', 'business-directory');
        
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Pending Submissions', 'business-directory'),
            $menu_title,
            'bd_manage_businesses',
            'bd-pending-submissions',
            [$this, 'render_page']
        );
    }
    
    private function get_pending_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'bd_submissions';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
    }
    
    public function render_page() {
        $submissions = \BD\DB\SubmissionsTable::get_pending(50);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Pending Business Submissions', 'business-directory'); ?></h1>
            
            <?php if (empty($submissions)): ?>
                <p><?php _e('No pending submissions.', 'business-directory'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Business Name', 'business-directory'); ?></th>
                            <th><?php _e('Category', 'business-directory'); ?></th>
                            <th><?php _e('Submitted By', 'business-directory'); ?></th>
                            <th><?php _e('Date', 'business-directory'); ?></th>
                            <th><?php _e('Actions', 'business-directory'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <?php
                            $category_name = '';
                            if (!empty($submission['business_data']['category'])) {
                                $term = get_term($submission['business_data']['category'], 'bd_category');
                                $category_name = $term ? $term->name : '';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($submission['business_data']['title'] ?? 'Untitled'); ?></strong>
                                    <div style="margin-top:5px; font-size:13px; color:#666;">
                                        <?php echo esc_html($submission['business_data']['address'] ?? ''); ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($category_name); ?></td>
                                <td>
                                    <?php echo esc_html($submission['submitter_name'] ?? 'Unknown'); ?><br>
                                    <small><?php echo esc_html($submission['submitter_email'] ?? ''); ?></small>
                                </td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($submission['created_at'])); ?></td>
                                <td>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                        <?php wp_nonce_field('bd_approve_submission'); ?>
                                        <input type="hidden" name="action" value="bd_approve_submission" />
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>" />
                                        <button type="submit" class="button button-primary"><?php _e('Approve', 'business-directory'); ?></button>
                                    </form>
                                    
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                        <?php wp_nonce_field('bd_reject_submission'); ?>
                                        <input type="hidden" name="action" value="bd_reject_submission" />
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>" />
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
    
    public function approve_submission() {
        check_admin_referer('bd_approve_submission');
        
        if (!current_user_can('bd_manage_businesses')) {
            wp_die(__('Unauthorized', 'business-directory'));
        }
        
        $submission_id = absint($_POST['submission_id']);
        $submission = \BD\DB\SubmissionsTable::get($submission_id);
        
        if (!$submission) {
            wp_die(__('Submission not found', 'business-directory'));
        }
        
        $data = $submission['business_data'];
        
        // Create business post
        $post_id = wp_insert_post([
            'post_title' => $data['title'],
            'post_content' => $data['description'],
            'post_type' => 'bd_business',
            'post_status' => 'publish',
        ]);
        
        if ($post_id) {
            // Add category
            if (!empty($data['category'])) {
                wp_set_object_terms($post_id, (int)$data['category'], 'bd_category');
            }
            
            // Add meta
            if (!empty($data['phone'])) {
                update_post_meta($post_id, 'bd_phone', $data['phone']);
            }
            if (!empty($data['website'])) {
                update_post_meta($post_id, 'bd_website', $data['website']);
            }
            
            // Add location (simplified - no geocoding)
            if (!empty($data['address'])) {
                \BD\DB\LocationsTable::insert([
                    'business_id' => $post_id,
                    'lat' => 0, // Admin can update later
                    'lng' => 0,
                    'address' => $data['address'],
                    'city' => $data['city'] ?? '',
                ]);
            }
            
            // Mark as approved
            \BD\DB\SubmissionsTable::approve($submission_id);
        }
        
        wp_redirect(admin_url('admin.php?page=bd-pending-submissions&approved=1'));
        exit;
    }
    
    public function reject_submission() {
        check_admin_referer('bd_reject_submission');
        
        if (!current_user_can('bd_manage_businesses')) {
            wp_die(__('Unauthorized', 'business-directory'));
        }
        
        $submission_id = absint($_POST['submission_id']);
        \BD\DB\SubmissionsTable::reject($submission_id);
        
        wp_redirect(admin_url('admin.php?page=bd-pending-submissions&rejected=1'));
        exit;
    }
}
