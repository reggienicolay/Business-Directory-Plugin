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
