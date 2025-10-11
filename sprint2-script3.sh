#!/bin/bash

################################################################################
# Sprint 2 Week 1 - Part 3: Moderation & Plugin Integration
# Run this THIRD (final script)
################################################################################

set -e

echo "🚀 Sprint 2 Week 1 - Part 3/3: Moderation & Integration"
echo "========================================================"
echo ""

if [[ ! -f "business-directory.php" ]]; then
    echo "❌ Error: Run from plugin directory"
    exit 1
fi

################################################################################
# MODERATION: SUBMISSIONS QUEUE
################################################################################

echo "📝 Creating SubmissionsQueue..."

cat > src/Moderation/SubmissionsQueue.php << 'SUBMISSIONSQUEUEEOF'
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
SUBMISSIONSQUEUEEOF

################################################################################
# MODERATION: REVIEWS QUEUE
################################################################################

echo "📝 Creating ReviewsQueue..."

cat > src/Moderation/ReviewsQueue.php << 'REVIEWSQUEUEEOF'
<?php
namespace BD\Moderation;

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
        
        $wpdb->update(
            $table,
            ['status' => 'approved'],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        
        // Update aggregate rating
        $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $review_id), ARRAY_A);
        if ($review) {
            $this->update_aggregate_rating($review['business_id']);
        }
        
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
REVIEWSQUEUEEOF

################################################################################
# UPDATE PLUGIN.PHP TO LOAD NEW CLASSES
################################################################################

echo "📝 Updating Plugin.php..."

cat > src/Plugin.php << 'PLUGINEOF'
<?php
namespace BD;

class Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    private function init_components() {
        // Admin components
        if (is_admin()) {
            new Admin\MetaBoxes();
            new Admin\ImporterPage();
            new Admin\Settings();
            new Moderation\SubmissionsQueue();
            new Moderation\ReviewsQueue();
        }
        
        // Frontend components
        new Frontend\Shortcodes();
        new Forms\BusinessSubmission();
        new Forms\ReviewSubmission();
    }
    
    public function register_post_types() {
        PostTypes\Business::register();
    }
    
    public function register_taxonomies() {
        Taxonomies\Category::register();
        Taxonomies\Area::register();
        Taxonomies\Tag::register();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'business-directory',
            false,
            dirname(BD_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    public function admin_assets($hook) {
        $screen = get_current_screen();
        
        if ($screen && $screen->post_type === 'bd_business') {
            wp_enqueue_style(
                'bd-admin',
                BD_PLUGIN_URL . 'assets/css/admin.css',
                [],
                BD_VERSION
            );
            
            wp_enqueue_script(
                'bd-admin',
                BD_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                BD_VERSION,
                true
            );
            
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                wp_enqueue_script(
                    'bd-admin-map',
                    BD_PLUGIN_URL . 'assets/js/admin-map.js',
                    ['jquery'],
                    BD_VERSION,
                    true
                );
            }
        }
    }
    
    public function frontend_assets() {
        wp_register_style(
            'bd-frontend',
            BD_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            BD_VERSION
        );
        
        wp_register_script(
            'bd-frontend',
            BD_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            BD_VERSION,
            true
        );
    }
    
    public function register_rest_routes() {
        REST\BusinessesController::register();
        REST\SubmitBusinessController::register();
        REST\SubmitReviewController::register();
    }
}
PLUGINEOF

################################################################################
# UPDATE CHANGELOG
################################################################################

echo "📝 Updating CHANGELOG..."

cat >> CHANGELOG.md << 'CHANGELOGEOF'

## [0.3.0] - Sprint 2 Week 1

### Added
- Public business submission form with spam protection
- Review system with 5-star ratings
- Photo uploads for reviews (up to 3 images)
- Cloudflare Turnstile CAPTCHA integration
- Rate limiting for submissions and reviews
- Email notifications to admins
- Moderation queues for submissions and reviews
- Settings page for Turnstile keys and notification emails
- Aggregate rating calculation
- REST API endpoints for submissions and reviews

### Technical
- New database tables: bd_submissions
- Updated bd_reviews table with photo_ids and email fields
- Form validation and sanitization
- File upload handling for review photos
- Transient-based rate limiting
CHANGELOGEOF

################################################################################
# UPDATE VERSION
################################################################################

echo "📝 Updating version number..."

sed -i.bak 's/Version: 0.2.0/Version: 0.3.0/' business-directory.php
sed -i.bak "s/define('BD_VERSION', '0.2.0')/define('BD_VERSION', '0.3.0')/" business-directory.php

rm -f business-directory.php.bak

echo ""
echo "✅ ✅ ✅ ALL 3 PARTS COMPLETE! ✅ ✅ ✅"
echo ""
echo "=========================================="
echo "  SPRINT 2 WEEK 1 INSTALLATION FINISHED"
echo "=========================================="
echo ""
echo "📋 NEXT STEPS (IMPORTANT!):"
echo ""
echo "1. DEACTIVATE the plugin in WordPress admin"
echo "2. REACTIVATE the plugin"
echo "   (This creates the new bd_submissions table)"
echo ""
echo "3. Go to: Directory → Settings"
echo "   - Get FREE Turnstile keys: https://dash.cloudflare.com/sign-up/turnstile"
echo "   - Add Site Key and Secret Key"
echo "   - Set notification email(s)"
echo ""
echo "4. TEST SUBMISSION FORM:"
echo "   - Create page with: [bd_submit_business]"
echo "   - Fill out and submit"
echo "   - Check: Directory → Pending Submissions"
echo ""
echo "5. TEST REVIEWS:"
echo "   - Visit any business page"
echo "   - Scroll to bottom - see review form"
echo "   - Submit a test review with photo"
echo "   - Check: Directory → Pending Reviews"
echo ""
echo "6. COMMIT TO GITHUB:"
echo "   git add ."
echo "   git commit -m \"Sprint 2 Week 1: Submissions, Reviews, Photos, Moderation\""
echo "   git push origin main"
echo ""
echo "🎉 You now have:"
echo "  ✅ Public submission form"
echo "  ✅ Review system with photos"
echo "  ✅ Spam protection (Turnstile)"
echo "  ✅ Email notifications"
echo "  ✅ Admin moderation queues"
echo ""
