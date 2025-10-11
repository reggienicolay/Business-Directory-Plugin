<?php
/**
 * Sprint 2 Week 2 - Feature Loader
 * Include this file from business-directory.php
 */

// Load Search classes
require_once __DIR__ . '/src/Search/FilterHandler.php';
require_once __DIR__ . '/src/Search/QueryBuilder.php';
require_once __DIR__ . '/src/Search/Geocoder.php';

// Load Frontend
require_once __DIR__ . '/src/Frontend/Filters.php';

// Load Utils
require_once __DIR__ . '/src/Utils/Cache.php';

// Load API endpoints
require_once __DIR__ . '/src/API/BusinessEndpoint-enhanced.php';
require_once __DIR__ . '/src/API/GeocodeEndpoint.php';

// Initialize Filters shortcode
add_action('init', function() {
    add_shortcode('business_filters', ['BusinessDirectory\Frontend\Filters', 'render_filters']);
});

// Enqueue assets
add_action('wp_enqueue_scripts', function() {
    if (is_page() || is_singular('business')) {
        wp_enqueue_style('bd-filters', plugins_url('assets/css/filters.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('bd-filters', plugins_url('assets/js/directory-filters.js', __FILE__), ['jquery'], '1.0.0', true);
        wp_enqueue_script('bd-geolocation', plugins_url('assets/js/geolocation.js', __FILE__), ['jquery'], '1.0.0', true);
        
        wp_localize_script('bd-filters', 'bdVars', [
            'apiUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
});
