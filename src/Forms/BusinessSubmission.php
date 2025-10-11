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
