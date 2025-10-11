#!/bin/bash

################################################################################
# Business Directory Plugin - Sprint 1 Week 2
# Adds: Metaboxes, Location Map, CSV Importer, REST API, Frontend Shell
################################################################################

set -e

echo "🚀 Business Directory - Sprint 1 Week 2 Installer"
echo "=================================================="
echo ""

# Check if we're in the plugin directory
if [[ ! -f "business-directory.php" ]]; then
    echo "❌ Error: Please run this script from:"
    echo "   ~/Local Sites/business-directory/app/public/wp-content/plugins/business-directory/"
    echo ""
    echo "Current directory: $(pwd)"
    exit 1
fi

echo "✅ Correct directory detected"
echo ""

################################################################################
# CREATE ADDITIONAL DIRECTORIES
################################################################################

echo "📁 Creating new directories..."
mkdir -p src/{Admin,REST,Frontend,Importer}
mkdir -p assets/js/components

echo "✅ Directories created"
echo ""

################################################################################
# ADMIN METABOXES (E4-S1)
################################################################################

echo "📝 Creating Admin metaboxes..."

cat > src/Admin/MetaBoxes.php << 'METABOXFILE'
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
METABOXFILE

################################################################################
# LEAFLET MAP JS (E4-S2)
################################################################################

echo "📝 Creating Leaflet map integration..."

cat > assets/js/admin-map.js << 'ADMINMAPFILE'
/**
 * Business Directory - Admin Map (Leaflet)
 */

(function($) {
    'use strict';
    
    let map = null;
    let marker = null;
    
    function initMap() {
        const mapContainer = document.getElementById('bd-map');
        
        if (!mapContainer) {
            return;
        }
        
        // Get existing coordinates or default to Austin, TX
        const lat = parseFloat($('#bd_lat').val()) || 30.2672;
        const lng = parseFloat($('#bd_lng').val()) || -97.7431;
        
        // Initialize Leaflet map
        map = L.map('bd-map').setView([lat, lng], 13);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Add marker if coordinates exist
        if ($('#bd_lat').val() && $('#bd_lng').val()) {
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            
            marker.on('dragend', function(e) {
                updateCoordinates(e.target.getLatLng());
            });
        }
        
        // Click to add/move marker
        map.on('click', function(e) {
            if (!marker) {
                marker = L.marker(e.latlng, {
                    draggable: true
                }).addTo(map);
                
                marker.on('dragend', function(e) {
                    updateCoordinates(e.target.getLatLng());
                });
            } else {
                marker.setLatLng(e.latlng);
            }
            
            updateCoordinates(e.latlng);
        });
        
        // Manual coordinate input
        $('#bd_lat, #bd_lng').on('change', function() {
            const newLat = parseFloat($('#bd_lat').val());
            const newLng = parseFloat($('#bd_lng').val());
            
            if (!isNaN(newLat) && !isNaN(newLng)) {
                const newLatLng = L.latLng(newLat, newLng);
                
                if (!marker) {
                    marker = L.marker(newLatLng, {
                        draggable: true
                    }).addTo(map);
                    
                    marker.on('dragend', function(e) {
                        updateCoordinates(e.target.getLatLng());
                    });
                } else {
                    marker.setLatLng(newLatLng);
                }
                
                map.setView(newLatLng, 13);
            }
        });
    }
    
    function updateCoordinates(latlng) {
        $('#bd_lat').val(latlng.lat.toFixed(6));
        $('#bd_lng').val(latlng.lng.toFixed(6));
    }
    
    $(document).ready(function() {
        // Load Leaflet CSS and JS
        if (!document.getElementById('leaflet-css')) {
            const css = document.createElement('link');
            css.id = 'leaflet-css';
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(css);
        }
        
        if (typeof L === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = initMap;
            document.body.appendChild(script);
        } else {
            initMap();
        }
    });
    
})(jQuery);
ADMINMAPFILE

################################################################################
# CSV IMPORTER (E4-S3)
################################################################################

echo "📝 Creating CSV importer..."

cat > src/Importer/CSV.php << 'CSVFILE'
<?php
namespace BD\Importer;

/**
 * CSV Importer for bulk business import
 */
class CSV {
    
    /**
     * Import businesses from CSV file
     */
    public static function import($file_path, $options = []) {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', 'CSV file not found');
        }
        
        $defaults = [
            'create_terms' => true,
            'dry_run' => false,
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new \WP_Error('file_open_error', 'Could not open CSV file');
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return new \WP_Error('invalid_csv', 'CSV has no header row');
        }
        
        $headers = array_map('trim', $headers);
        
        // Process rows
        $row_number = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Combine headers with data
            $row = array_combine($headers, $data);
            
            // Validate required fields
            if (empty($row['title']) || empty($row['lat']) || empty($row['lng'])) {
                $results['skipped']++;
                $results['errors'][] = "Row $row_number: Missing required fields (title, lat, lng)";
                continue;
            }
            
            if (!$options['dry_run']) {
                $business_id = self::import_business($row, $options);
                
                if (is_wp_error($business_id)) {
                    $results['skipped']++;
                    $results['errors'][] = "Row $row_number: " . $business_id->get_error_message();
                } else {
                    $results['imported']++;
                }
            } else {
                $results['imported']++;
            }
        }
        
        fclose($handle);
        
        return $results;
    }
    
    /**
     * Import a single business
     */
    private static function import_business($data, $options) {
        // Create post
        $post_data = [
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'post_type' => 'bd_business',
            'post_status' => 'publish',
        ];
        
        $business_id = wp_insert_post($post_data);
        
        if (is_wp_error($business_id)) {
            return $business_id;
        }
        
        // Add category
        if (!empty($data['category']) && $options['create_terms']) {
            $term = get_term_by('name', $data['category'], 'bd_category');
            if (!$term) {
                $term_data = wp_insert_term($data['category'], 'bd_category');
                if (!is_wp_error($term_data)) {
                    wp_set_object_terms($business_id, (int)$term_data['term_id'], 'bd_category');
                }
            } else {
                wp_set_object_terms($business_id, $term->term_id, 'bd_category');
            }
        }
        
        // Add area
        if (!empty($data['area']) && $options['create_terms']) {
            $term = get_term_by('name', $data['area'], 'bd_area');
            if (!$term) {
                $term_data = wp_insert_term($data['area'], 'bd_area');
                if (!is_wp_error($term_data)) {
                    wp_set_object_terms($business_id, (int)$term_data['term_id'], 'bd_area');
                }
            } else {
                wp_set_object_terms($business_id, $term->term_id, 'bd_area');
            }
        }
        
        // Add location
        $location_data = [
            'business_id' => $business_id,
            'lat' => floatval($data['lat']),
            'lng' => floatval($data['lng']),
            'address' => isset($data['address']) ? sanitize_text_field($data['address']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
        ];
        
        \BD\DB\LocationsTable::insert($location_data);
        
        // Add phone
        if (!empty($data['phone'])) {
            update_post_meta($business_id, 'bd_phone', sanitize_text_field($data['phone']));
        }
        
        // Add website
        if (!empty($data['website'])) {
            update_post_meta($business_id, 'bd_website', esc_url_raw($data['website']));
        }
        
        return $business_id;
    }
}
CSVFILE

# Add importer admin page
cat > src/Admin/ImporterPage.php << 'IMPORTERPAGEFILE'
<?php
namespace BD\Admin;

/**
 * CSV Importer admin page
 */
class ImporterPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_post_bd_import_csv', [$this, 'handle_import']);
    }
    
    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Import CSV', 'business-directory'),
            __('Import CSV', 'business-directory'),
            'manage_options',
            'bd-import-csv',
            [$this, 'render_page']
        );
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import Businesses from CSV', 'business-directory'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('bd_import_csv', 'bd_import_nonce'); ?>
                <input type="hidden" name="action" value="bd_import_csv" />
                
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file"><?php _e('CSV File', 'business-directory'); ?></label></th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                            <p class="description">
                                <?php _e('Required columns: title, lat, lng', 'business-directory'); ?><br>
                                <?php _e('Optional: description, category, area, address, city, phone, website', 'business-directory'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="create_terms"><?php _e('Create Terms', 'business-directory'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="create_terms" id="create_terms" value="1" checked />
                                <?php _e('Automatically create categories/areas if they don\'t exist', 'business-directory'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Import', 'business-directory')); ?>
            </form>
        </div>
        <?php
    }
    
    public function handle_import() {
        if (!isset($_POST['bd_import_nonce']) || !wp_verify_nonce($_POST['bd_import_nonce'], 'bd_import_csv')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(['page' => 'bd-import-csv', 'error' => 'upload'], admin_url('edit.php?post_type=bd_business')));
            exit;
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $options = [
            'create_terms' => isset($_POST['create_terms']),
        ];
        
        $results = \BD\Importer\CSV::import($file, $options);
        
        if (is_wp_error($results)) {
            wp_redirect(add_query_arg([
                'page' => 'bd-import-csv',
                'error' => urlencode($results->get_error_message())
            ], admin_url('edit.php?post_type=bd_business')));
            exit;
        }
        
        wp_redirect(add_query_arg([
            'page' => 'bd-import-csv',
            'imported' => $results['imported'],
            'skipped' => $results['skipped']
        ], admin_url('edit.php?post_type=bd_business')));
        exit;
    }
}
IMPORTERPAGEFILE

################################################################################
# REST API (E5-S1)
################################################################################

echo "📝 Creating REST API..."

cat > src/REST/BusinessesController.php << 'RESTFILE'
<?php
namespace BD\REST;

/**
 * REST API: /bd/v1/businesses
 */
class BusinessesController {
    
    public static function register() {
        register_rest_route('bd/v1', '/businesses', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_businesses'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'q' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'category' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'lat' => [
                    'default' => null,
                    'sanitize_callback' => 'floatval',
                ],
                'lng' => [
                    'default' => null,
                    'sanitize_callback' => 'floatval',
                ],
            ],
        ]);
    }
    
    public static function get_businesses($request) {
        $args = [
            'post_type' => 'bd_business',
            'post_status' => 'publish',
            'posts_per_page' => min($request['per_page'], 50),
            'paged' => $request['page'],
        ];
        
        // Keyword search
        if (!empty($request['q'])) {
            $args['s'] = $request['q'];
        }
        
        // Category filter
        if (!empty($request['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'bd_category',
                    'field' => 'slug',
                    'terms' => $request['category'],
                ],
            ];
        }
        
        $query = new \WP_Query($args);
        
        $businesses = [];
        foreach ($query->posts as $post) {
            $location = \BD\DB\LocationsTable::get($post->ID);
            
            $business = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => $post->post_excerpt,
                'permalink' => get_permalink($post->ID),
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'medium'),
                'categories' => wp_get_post_terms($post->ID, 'bd_category', ['fields' => 'names']),
                'phone' => get_post_meta($post->ID, 'bd_phone', true),
                'website' => get_post_meta($post->ID, 'bd_website', true),
                'price_level' => get_post_meta($post->ID, 'bd_price_level', true),
            ];
            
            if ($location) {
                $business['location'] = [
                    'lat' => (float)$location['lat'],
                    'lng' => (float)$location['lng'],
                    'address' => $location['address'],
                    'city' => $location['city'],
                ];
                
                // Calculate distance if user location provided
                if ($request['lat'] && $request['lng']) {
                    $business['distance_km'] = self::calculate_distance(
                        $request['lat'],
                        $request['lng'],
                        $location['lat'],
                        $location['lng']
                    );
                }
            }
            
            $businesses[] = $business;
        }
        
        return rest_ensure_response([
            'data' => $businesses,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }
    
    private static function calculate_distance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return round($earth_radius * $c, 2);
    }
}
RESTFILE

################################################################################
# FRONTEND SHORTCODE (E5-S3)
################################################################################

echo "📝 Creating frontend shortcode..."

cat > src/Frontend/Shortcodes.php << 'SHORTCODEFILE'
<?php
namespace BD\Frontend;

/**
 * Frontend shortcodes
 */
class Shortcodes {
    
    public function __construct() {
        add_shortcode('bd_directory', [$this, 'directory_shortcode']);
    }
    
    public function directory_shortcode($atts) {
        $atts = shortcode_atts([
            'view' => 'map',
            'category' => '',
            'per_page' => 20,
        ], $atts, 'bd_directory');
        
        // Enqueue assets
        wp_enqueue_style('bd-frontend');
        wp_enqueue_script('bd-frontend');
        
        ob_start();
        ?>
        <div class="bd-directory" data-view="<?php echo esc_attr($atts['view']); ?>">
            <div class="bd-directory-header">
                <h2><?php _e('Business Directory', 'business-directory'); ?></h2>
            </div>
            
            <div class="bd-directory-content">
                <aside class="bd-filters">
                    <h3><?php _e('Filters', 'business-directory'); ?></h3>
                    <p><?php _e('Filters coming soon...', 'business-directory'); ?></p>
                </aside>
                
                <main class="bd-results">
                    <div class="bd-view-toggle">
                        <button class="bd-view-btn active" data-view="map"><?php _e('Map', 'business-directory'); ?></button>
                        <button class="bd-view-btn" data-view="list"><?php _e('List', 'business-directory'); ?></button>
                    </div>
                    
                    <div id="bd-map-container" class="bd-map-view">
                        <div id="bd-map" style="height: 600px;"></div>
                    </div>
                    
                    <div id="bd-list-container" class="bd-list-view" style="display:none;">
                        <p><?php _e('Loading businesses...', 'business-directory'); ?></p>
                    </div>
                </main>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
SHORTCODEFILE

cat > assets/js/frontend.js << 'FRONTENDFILE'
/**
 * Business Directory - Frontend
 */

(function() {
    'use strict';
    
    let map = null;
    let markers = [];
    
    function initDirectory() {
        const mapEl = document.getElementById('bd-map');
        if (!mapEl) return;
        
        // Initialize Leaflet
        map = L.map('bd-map').setView([30.2672, -97.7431], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Load businesses
        loadBusinesses();
        
        // View toggle
        document.querySelectorAll('.bd-view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchView(this.dataset.view);
            });
        });
    }
    
    function loadBusinesses() {
        fetch('/wp-json/bd/v1/businesses')
            .then(r => r.json())
            .then(data => {
                renderMarkers(data.data);
                renderList(data.data);
            })
            .catch(err => console.error('Error loading businesses:', err));
    }
    
    function renderMarkers(businesses) {
        businesses.forEach(business => {
            if (business.location) {
                const marker = L.marker([business.location.lat, business.location.lng])
                    .bindPopup(`
                        <strong>${business.title}</strong><br>
                        ${business.location.address}<br>
                        <a href="${business.permalink}">View Details</a>
                    `)
                    .addTo(map);
                
                markers.push(marker);
            }
        });
    }
    
    function renderList(businesses) {
        const container = document.getElementById('bd-list-container');
        
        if (businesses.length === 0) {
            container.innerHTML = '<p>No businesses found.</p>';
            return;
        }
        
        let html = '<div class="bd-business-grid">';
        
        businesses.forEach(business => {
            html += `
                <article class="bd-business-card">
                    ${business.thumbnail ? `<img src="${business.thumbnail}" alt="${business.title}">` : ''}
                    <h3>${business.title}</h3>
                    ${business.excerpt ? `<p>${business.excerpt}</p>` : ''}
                    <div class="bd-business-meta">
                        ${business.price_level ? `<span class="price">${business.price_level}</span>` : ''}
                        ${business.categories.length ? `<span class="category">${business.categories[0]}</span>` : ''}
                    </div>
                    <a href="${business.permalink}" class="bd-btn">View Details</a>
                </article>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    function switchView(view) {
        document.querySelectorAll('.bd-view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        
        if (view === 'map') {
            document.getElementById('bd-map-container').style.display = 'block';
            document.getElementById('bd-list-container').style.display = 'none';
            if (map) map.invalidateSize();
        } else {
            document.getElementById('bd-map-container').style.display = 'none';
            document.getElementById('bd-list-container').style.display = 'block';
        }
    }
    
    // Load Leaflet if not already loaded
    if (typeof L === 'undefined') {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);
        
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = initDirectory;
        document.body.appendChild(script);
    } else {
        initDirectory();
    }
    
})();
FRONTENDFILE

cat > assets/css/frontend.css << 'FRONTENDCSSFILE'
/**
 * Business Directory - Frontend Styles
 */

.bd-directory {
    max-width: 1200px;
    margin: 0 auto;
}

.bd-directory-content {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 20px;
    margin-top: 20px;
}

.bd-filters {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
}

.bd-view-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.bd-view-btn {
    padding: 10px 20px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
}

.bd-view-btn.active {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.bd-business-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.bd-business-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.bd-business-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.bd-business-card h3 {
    padding: 15px;
    margin: 0;
    font-size: 18px;
}

.bd-business-card p {
    padding: 0 15px;
    color: #666;
}

.bd-business-meta {
    padding: 0 15px 15px;
    display: flex;
    gap: 10px;
}

.bd-btn {
    display: block;
    padding: 10px 15px;
    background: #0073aa;
    color: white;
    text-align: center;
    text-decoration: none;
    margin: 15px;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .bd-directory-content {
        grid-template-columns: 1fr;
    }
    
    .bd-filters {
        order: 2;
    }
}
FRONTENDCSSFILE

################################################################################
# UPDATE PLUGIN.PHP TO LOAD NEW CLASSES
################################################################################

echo "📝 Updating Plugin.php..."

cat > src/Plugin.php << 'PLUGINUPDATEFILE'
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
        }
        
        // Frontend components
        new Frontend\Shortcodes();
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
            
            // Enqueue map script for edit screen
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
    }
}
PLUGINUPDATEFILE

################################################################################
# UPDATE CHANGELOG
################################################################################

echo "📝 Updating CHANGELOG..."

cat >> CHANGELOG.md << 'CHANGELOGUPDATE'

## [0.2.0] - Sprint 1 Week 2

### Added
- Business metaboxes for custom fields (phone, website, hours, social)
- Location map with Leaflet for coordinate selection
- CSV bulk importer with admin page
- REST API endpoint /bd/v1/businesses
- Frontend directory shortcode [bd_directory]
- Map/list view toggle
- Distance calculation from user location

### Technical
- Admin map integration with Leaflet
- Haversine distance formula
- CSV parsing with term creation
- Frontend asset enqueuing system
CHANGELOGUPDATE

################################################################################
# FINISH
################################################################################

echo ""
echo "✅ SPRINT 1 WEEK 2 COMPLETE!"
echo ""
echo "📍 New features added:"
echo "   - Business metaboxes (phone, website, hours, etc.)"
echo "   - Admin location map with Leaflet"
echo "   - CSV bulk importer"
echo "   - REST API /bd/v1/businesses"
echo "   - Frontend directory shortcode"
echo ""
echo "Next steps:"
echo "1. Refresh WordPress admin (hard refresh: Cmd+Shift+R)"
echo "2. Edit a business - you'll see new metaboxes and map"
echo "3. Try CSV import: Directory → Import CSV"
echo "4. Test API: http://business-directory.local/wp-json/bd/v1/businesses"
echo "5. Add shortcode to a page: [bd_directory]"
echo ""
echo "🎉 Ready to commit to GitHub!"
