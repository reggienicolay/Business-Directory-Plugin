<?php
/**
 * Plugin Name: BD Debug
 * Description: Debug locations table
 */

add_action('admin_menu', function() {
    add_menu_page('BD Debug', 'BD Debug', 'manage_options', 'bd-debug', function() {
        global $wpdb;
        
        echo '<div class="wrap"><h1>Business Directory Debug</h1>';
        
        echo '<h2>Step 1: Get Published Businesses</h2>';
        $business_ids = get_posts([
            'post_type' => 'bd_business',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        echo '<p><strong>Found IDs:</strong> ' . implode(', ', $business_ids) . ' (' . count($business_ids) . ' total)</p>';
        
        echo '<h2>Step 2: Query Locations Table</h2>';
        if (!empty($business_ids)) {
            $ids_string = implode(',', array_map('intval', $business_ids));
            
            $locations = $wpdb->get_results(
                "SELECT business_id, lat, lng, address 
                 FROM {$wpdb->prefix}bd_locations 
                 WHERE business_id IN ($ids_string)",
                ARRAY_A
            );
            
            echo '<p><strong>Locations found:</strong> ' . count($locations) . '</p>';
            
            if (!empty($locations)) {
                echo '<table class="wp-list-table widefat"><tr><th>Business ID</th><th>Lat</th><th>Lng</th><th>Address</th></tr>';
                foreach ($locations as $loc) {
                    echo '<tr><td>' . $loc['business_id'] . '</td><td>' . $loc['lat'] . '</td><td>' . $loc['lng'] . '</td><td>' . $loc['address'] . '</td></tr>';
                }
                echo '</table>';
            }
        }
        
        echo '</div>';
    });
});
