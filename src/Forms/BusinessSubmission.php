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
                wp_enqueue_style('bd-submission-form', BD_PLUGIN_URL . 'assets/css/submission-form-premium.css', [], BD_VERSION);
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
        <div class="bd-submission-wrapper">
            
            <!-- Hero Section -->
            <div class="bd-submission-hero">
                <h1>Add Your Business</h1>
                <p class="bd-submission-subtitle">Join our premium business directory and reach more customers</p>
                <div class="bd-submission-benefits">
                    <div class="bd-benefit">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Premium Listing</span>
                    </div>
                    <div class="bd-benefit">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Photo & Video Gallery</span>
                    </div>
                    <div class="bd-benefit">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <span>Customer Reviews</span>
                    </div>
                </div>
            </div>
            
            <form id="bd-submit-business-form" class="bd-submission-form">
                
                <!-- Basic Information -->
                <div class="bd-form-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">1</span>
                        Basic Information
                    </h2>
                    
                    <div class="bd-form-grid">
                        <div class="bd-form-field bd-field-full">
                            <label>Business Name <span class="required">*</span></label>
                            <input type="text" name="title" required placeholder="e.g., Sunset Cafe & Wine Bar" />
                        </div>
                        
                        <div class="bd-form-field bd-field-full">
                            <label>Description <span class="required">*</span></label>
                            <textarea name="description" rows="6" required placeholder="Tell customers what makes your business special..."></textarea>
                            <p class="bd-field-hint">Include key details about your products, services, and what sets you apart</p>
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Category <span class="required">*</span></label>
                            <select name="category" required>
                                <option value="">Select a category...</option>
                                <?php
                                $categories = get_terms(['taxonomy' => 'bd_category', 'hide_empty' => false]);
                                foreach ($categories as $cat) {
                                    echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Area</label>
                            <select name="area">
                                <option value="">Select an area...</option>
                                <?php
                                $areas = get_terms(['taxonomy' => 'bd_area', 'hide_empty' => false]);
                                foreach ($areas as $area) {
                                    echo '<option value="' . $area->term_id . '">' . esc_html($area->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Price Level</label>
                            <select name="price_level">
                                <option value="">Select...</option>
                                <option value="$">$ - Budget Friendly</option>
                                <option value="$$">$$ - Moderate</option>
                                <option value="$$$">$$$ - Upscale</option>
                                <option value="$$$$">$$$$ - Fine Dining / Premium</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Location -->
                <div class="bd-form-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">2</span>
                        Location
                    </h2>
                    
                    <div class="bd-form-grid">
                        <div class="bd-form-field bd-field-full">
                            <label>Street Address <span class="required">*</span></label>
                            <input type="text" name="address" required placeholder="123 Main Street" />
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>City <span class="required">*</span></label>
                            <input type="text" name="city" required placeholder="Livermore" />
                        </div>
                        
                        <div class="bd-form-field bd-field-quarter">
                            <label>State</label>
                            <input type="text" name="state" placeholder="CA" maxlength="2" />
                        </div>
                        
                        <div class="bd-form-field bd-field-quarter">
                            <label>ZIP Code</label>
                            <input type="text" name="zip" placeholder="94550" />
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="bd-form-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">3</span>
                        Contact Information
                    </h2>
                    
                    <div class="bd-form-grid">
                        <div class="bd-form-field bd-field-half">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" placeholder="(925) 555-1234" />
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Website</label>
                            <input type="url" name="website" placeholder="https://yourbusiness.com" />
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="info@yourbusiness.com" />
                        </div>
                    </div>
                </div>
                
                <!-- Photos & Videos -->
                <div class="bd-form-section bd-media-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">4</span>
                        Photos & Videos
                    </h2>
                    <p class="bd-section-description">Showcase your business with high-quality images and videos</p>
                    
                    <div class="bd-media-upload-area">
                        <div class="bd-upload-box">
                            <svg width="48" height="48" viewBox="0 0 48 48" fill="currentColor">
                                <path d="M38 16h-8l-4-4H14c-2.2 0-4 1.8-4 4v20c0 2.2 1.8 4 4 4h24c2.2 0 4-1.8 4-4V20c0-2.2-1.8-4-4-4zm-14 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8z"/>
                            </svg>
                            <h3>Upload Photos</h3>
                            <p>Drag and drop or click to browse</p>
                            <input type="file" name="photos[]" accept="image/*" multiple id="bd-photo-upload" />
                            <button type="button" class="bd-upload-btn" onclick="document.getElementById('bd-photo-upload').click()">
                                Choose Photos
                            </button>
                            <p class="bd-upload-hint">Up to 10 photos, 5MB each (JPG, PNG)</p>
                        </div>
                        
                        <div class="bd-upload-box">
                            <svg width="48" height="48" viewBox="0 0 48 48" fill="currentColor">
                                <path d="M36 8H12c-2.2 0-4 1.8-4 4v24c0 2.2 1.8 4 4 4h24c2.2 0 4-1.8 4-4V12c0-2.2-1.8-4-4-4zM18 32l-6-8 8-10 6 8 8-10 8 12H18z"/>
                            </svg>
                            <h3>Upload Videos</h3>
                            <p>Show your business in action</p>
                            <input type="file" name="videos[]" accept="video/*" multiple id="bd-video-upload" />
                            <button type="button" class="bd-upload-btn" onclick="document.getElementById('bd-video-upload').click()">
                                Choose Videos
                            </button>
                            <p class="bd-upload-hint">Up to 3 videos, 50MB each (MP4, MOV)</p>
                        </div>
                    </div>
                    
                    <div id="bd-media-preview" class="bd-media-preview"></div>
                </div>
                
                <!-- Hours of Operation -->
                <div class="bd-form-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">5</span>
                        Hours of Operation
                    </h2>
                    
                    <div class="bd-hours-grid">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day):
                            $day_lower = strtolower($day);
                        ?>
                            <div class="bd-hours-row">
                                <label class="bd-day-label">
                                    <input type="checkbox" name="hours[<?php echo $day_lower; ?>][enabled]" checked />
                                    <?php echo $day; ?>
                                </label>
                                <div class="bd-hours-inputs">
                                    <input type="time" name="hours[<?php echo $day_lower; ?>][open]" value="09:00" />
                                    <span>to</span>
                                    <input type="time" name="hours[<?php echo $day_lower; ?>][close]" value="17:00" />
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Your Information -->
                <div class="bd-form-section">
                    <h2 class="bd-section-title">
                        <span class="bd-section-number">6</span>
                        Your Information
                    </h2>
                    
                    <div class="bd-form-grid">
                        <div class="bd-form-field bd-field-half">
                            <label>Your Name <span class="required">*</span></label>
                            <input type="text" name="submitter_name" required />
                        </div>
                        
                        <div class="bd-form-field bd-field-half">
                            <label>Your Email <span class="required">*</span></label>
                            <input type="email" name="submitter_email" required />
                        </div>
                    </div>
                </div>
                
                <?php if ($turnstile_enabled): ?>
                <div class="bd-form-section">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(get_option('bd_turnstile_site_key')); ?>"></div>
                </div>
                <?php endif; ?>
                
                <div class="bd-form-actions">
                    <button type="submit" class="bd-btn bd-btn-primary bd-btn-large">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                        </svg>
                        Submit Your Business
                    </button>
                </div>
                
                <div id="bd-submission-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}