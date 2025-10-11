<?php
/**
 * Sprint 2 Week 2 Script 3 - Geolocation & Performance Loader
 * This file is safe to include multiple times
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load once
if (defined('BD_S2W2_SCRIPT3_LOADED')) {
    return;
}

define('BD_S2W2_SCRIPT3_LOADED', true);

// Load Geocode API endpoint
require_once plugin_dir_path(__FILE__) . '../src/API/GeocodeEndpoint.php';

// Initialize Geocode API
\BusinessDirectory\API\GeocodeEndpoint::init();

// Load cache warmer
require_once plugin_dir_path(__FILE__) . 'cache-warmer.php';

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function() {
    // Only load on directory pages
    if (is_page_template('templates/directory.php') || is_singular('business') || has_shortcode(get_post()->post_content ?? '', 'business_filters')) {
        
        // Enqueue geolocation script (depends on filters)
        wp_enqueue_script(
            'bd-geolocation',
            plugins_url('assets/js/geolocation.js', dirname(__FILE__)),
            ['jquery', 'bd-filters'],
            '1.0.0',
            true
        );
        
        // Enqueue enhanced map script (depends on Leaflet if present)
        $map_deps = ['jquery'];
        if (wp_script_is('leaflet', 'registered')) {
            $map_deps[] = 'leaflet';
        }
        
        wp_enqueue_script(
            'bd-map',
            plugins_url('assets/js/directory-map.js', dirname(__FILE__)),
            $map_deps,
            '1.0.0',
            true
        );
        
        // Enqueue map marker styles
        wp_enqueue_style(
            'bd-map-markers',
            plugins_url('assets/css/map-markers.css', dirname(__FILE__)),
            [],
            '1.0.0'
        );
    }
}, 30);
