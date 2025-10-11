<?php
namespace BD\Admin;

/**
 * Business metaboxes for custom fields
 */
class MetaBoxes {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post_bd_business', [$this, 'save_metabox'], 10, 2);
    }
    
    /**
     * Register metaboxes
     */
    public function add_metaboxes() {
        add_meta_box(
            'bd_business_details',
            __('Business Details', 'business-directory'),
            [$this, 'render_details_metabox'],
            'bd_business',
            'normal',
            'high'
        );
        
        add_meta_box(
            'bd_business_location',
            __('Location', 'business-directory'),
            [$this, 'render_location_metabox'],
            'bd_business',
            'side',
            'default'
        );
    }
    
    /**
     * Render business details metabox
     */
    public function render_details_metabox($post) {
        wp_nonce_field('bd_business_details', 'bd_business_details_nonce');
        
        $phone = get_post_meta($post->ID, 'bd_phone', true);
        $website = get_post_meta($post->ID, 'bd_website', true);
        $email = get_post_meta($post->ID, 'bd_email', true);
        $price_level = get_post_meta($post->ID, 'bd_price_level', true);
        $hours = get_post_meta($post->ID, 'bd_hours', true);
        $social = get_post_meta($post->ID, 'bd_social', true);
        
        if (!is_array($hours)) {
            $hours = [];
        }
        if (!is_array($social)) {
            $social = [];
        }
        
        ?>
        <style>
            .bd-metabox-field { margin-bottom: 20px; }
            .bd-metabox-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .bd-metabox-field input[type="text"],
            .bd-metabox-field input[type="url"],
            .bd-metabox-field input[type="email"] { width: 100%; }
            .bd-hours-grid { display: grid; grid-template-columns: 120px 1fr 1fr; gap: 10px; margin-top: 10px; }
            .bd-hours-grid label { font-weight: normal; }
            .bd-social-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        </style>
        
        <div class="bd-metabox-field">
            <label><?php _e('Phone Number', 'business-directory'); ?></label>
            <input type="text" name="bd_phone" value="<?php echo esc_attr($phone); ?>" placeholder="(555) 123-4567" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Website', 'business-directory'); ?></label>
            <input type="url" name="bd_website" value="<?php echo esc_url($website); ?>" placeholder="https://example.com" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Email', 'business-directory'); ?></label>
            <input type="email" name="bd_email" value="<?php echo esc_attr($email); ?>" placeholder="contact@example.com" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Price Level', 'business-directory'); ?></label>
            <select name="bd_price_level">
                <option value="">Select...</option>
                <option value="$" <?php selected($price_level, '$'); ?>>$ (Budget)</option>
                <option value="$$" <?php selected($price_level, '$$'); ?>>$$ (Moderate)</option>
                <option value="$$$" <?php selected($price_level, '$$$'); ?>>$$$ (Expensive)</option>
                <option value="$$$$" <?php selected($price_level, '$$$$'); ?>>$$$$ (Luxury)</option>
            </select>
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Hours of Operation', 'business-directory'); ?></label>
            <div class="bd-hours-grid">
                <strong>Day</strong><strong>Open</strong><strong>Close</strong>
                <?php
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($days as $day) {
                    $open = isset($hours[$day]['open']) ? $hours[$day]['open'] : '';
                    $close = isset($hours[$day]['close']) ? $hours[$day]['close'] : '';
                    ?>
                    <label><?php echo ucfirst($day); ?></label>
                    <input type="time" name="bd_hours[<?php echo $day; ?>][open]" value="<?php echo esc_attr($open); ?>" />
                    <input type="time" name="bd_hours[<?php echo $day; ?>][close]" value="<?php echo esc_attr($close); ?>" />
                    <?php
                }
                ?>
            </div>
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Social Media', 'business-directory'); ?></label>
            <div class="bd-social-grid">
                <div>
                    <label>Facebook</label>
                    <input type="url" name="bd_social[facebook]" value="<?php echo esc_url($social['facebook'] ?? ''); ?>" placeholder="https://facebook.com/..." />
                </div>
                <div>
                    <label>Instagram</label>
                    <input type="url" name="bd_social[instagram]" value="<?php echo esc_url($social['instagram'] ?? ''); ?>" placeholder="https://instagram.com/..." />
                </div>
                <div>
                    <label>Twitter/X</label>
                    <input type="url" name="bd_social[twitter]" value="<?php echo esc_url($social['twitter'] ?? ''); ?>" placeholder="https://x.com/..." />
                </div>
                <div>
                    <label>LinkedIn</label>
                    <input type="url" name="bd_social[linkedin]" value="<?php echo esc_url($social['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/..." />
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render location metabox
     */
    public function render_location_metabox($post) {
        $location = \BD\DB\LocationsTable::get($post->ID);
        
        $lat = $location['lat'] ?? '';
        $lng = $location['lng'] ?? '';
        $address = $location['address'] ?? '';
        $city = $location['city'] ?? '';
        $state = $location['state'] ?? '';
        $postal_code = $location['postal_code'] ?? '';
        
        ?>
        <div class="bd-metabox-field">
            <label><?php _e('Address', 'business-directory'); ?></label>
            <input type="text" name="bd_address" id="bd_address" value="<?php echo esc_attr($address); ?>" style="width:100%;" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('City', 'business-directory'); ?></label>
            <input type="text" name="bd_city" id="bd_city" value="<?php echo esc_attr($city); ?>" style="width:100%;" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('State', 'business-directory'); ?></label>
            <input type="text" name="bd_state" id="bd_state" value="<?php echo esc_attr($state); ?>" style="width:100%;" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Postal Code', 'business-directory'); ?></label>
            <input type="text" name="bd_postal_code" id="bd_postal_code" value="<?php echo esc_attr($postal_code); ?>" style="width:100%;" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Latitude', 'business-directory'); ?></label>
            <input type="text" name="bd_lat" id="bd_lat" value="<?php echo esc_attr($lat); ?>" style="width:100%;" />
        </div>
        
        <div class="bd-metabox-field">
            <label><?php _e('Longitude', 'business-directory'); ?></label>
            <input type="text" name="bd_lng" id="bd_lng" value="<?php echo esc_attr($lng); ?>" style="width:100%;" />
        </div>
        
        <div id="bd-map" style="height: 300px; margin-top: 10px; border: 1px solid #ddd;"></div>
        <p class="description"><?php _e('Click on the map to set location, or enter coordinates manually.', 'business-directory'); ?></p>
        <?php
    }
    
    /**
     * Save metabox data
     */
    public function save_metabox($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['bd_business_details_nonce']) || !wp_verify_nonce($_POST['bd_business_details_nonce'], 'bd_business_details')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save business details
        if (isset($_POST['bd_phone'])) {
            update_post_meta($post_id, 'bd_phone', sanitize_text_field($_POST['bd_phone']));
        }
        if (isset($_POST['bd_website'])) {
            update_post_meta($post_id, 'bd_website', esc_url_raw($_POST['bd_website']));
        }
        if (isset($_POST['bd_email'])) {
            update_post_meta($post_id, 'bd_email', sanitize_email($_POST['bd_email']));
        }
        if (isset($_POST['bd_price_level'])) {
            update_post_meta($post_id, 'bd_price_level', sanitize_text_field($_POST['bd_price_level']));
        }
        if (isset($_POST['bd_hours'])) {
            update_post_meta($post_id, 'bd_hours', array_map('sanitize_text_field', $_POST['bd_hours']));
        }
        if (isset($_POST['bd_social'])) {
            $social = array_map('esc_url_raw', $_POST['bd_social']);
            update_post_meta($post_id, 'bd_social', $social);
        }
        
        // Save location data
        if (isset($_POST['bd_lat']) && isset($_POST['bd_lng'])) {
            $lat = floatval($_POST['bd_lat']);
            $lng = floatval($_POST['bd_lng']);
            
            if (\BD\Utils\Validation::is_valid_latitude($lat) && \BD\Utils\Validation::is_valid_longitude($lng)) {
                $location_data = [
                    'business_id' => $post_id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'address' => isset($_POST['bd_address']) ? sanitize_text_field($_POST['bd_address']) : '',
                    'city' => isset($_POST['bd_city']) ? sanitize_text_field($_POST['bd_city']) : '',
                    'state' => isset($_POST['bd_state']) ? sanitize_text_field($_POST['bd_state']) : '',
                    'postal_code' => isset($_POST['bd_postal_code']) ? sanitize_text_field($_POST['bd_postal_code']) : '',
                ];
                
                // Check if location exists
                $existing = \BD\DB\LocationsTable::get($post_id);
                
                if ($existing) {
                    \BD\DB\LocationsTable::update($post_id, $location_data);
                } else {
                    \BD\DB\LocationsTable::insert($location_data);
                }
            }
        }
    }
}
