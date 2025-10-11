<?php
/**
 * Sprint 2 Week 2 - Search Infrastructure Loader
 */

if (!defined('ABSPATH')) exit;
if (defined('BD_S2W2_LOADED')) return;
define('BD_S2W2_LOADED', true);

// Load Search classes
require_once plugin_dir_path(__FILE__) . '../src/Search/FilterHandler.php';
require_once plugin_dir_path(__FILE__) . '../src/Search/QueryBuilder.php';
require_once plugin_dir_path(__FILE__) . '../src/Search/Geocoder.php';

// Load Utils
require_once plugin_dir_path(__FILE__) . '../src/Utils/Cache.php';

// Load and initialize API
require_once plugin_dir_path(__FILE__) . '../src/API/BusinessEndpoint.php';

// Load database indexes
if (file_exists(plugin_dir_path(__FILE__) . '../includes/database-indexes.php')) {
    require_once plugin_dir_path(__FILE__) . '../includes/database-indexes.php';
}

// Enqueue scripts and styles for filter shortcodes
add_action('wp_enqueue_scripts', function() {
    global $post;
    
    // Check if page has either shortcode
    $has_filters = false;
    if (is_a($post, 'WP_Post')) {
        $has_filters = has_shortcode($post->post_content, 'business_filters') || 
              has_shortcode($post->post_content, 'business_directory_complete') ||
              has_shortcode($post->post_content, 'bd_directory');
    }

    if ($has_filters) {
        $plugin_url = plugins_url('', dirname(__FILE__));
        
        // Enqueue Leaflet CSS
        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );
        
        // Enqueue Leaflet MarkerCluster CSS
        wp_enqueue_style(
            'leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
            ['leaflet'],
            '1.5.3'
        );
        
        wp_enqueue_style(
            'leaflet-markercluster-default',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
            ['leaflet'],
            '1.5.3'
        );
        
        // Enqueue filter CSS
        wp_enqueue_style(
            'bd-filters',
            $plugin_url . '/assets/css/filters.css',
            [],
            '1.0.4'
        );
        
        // Enqueue Leaflet JS
        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );
        
        // Enqueue Leaflet MarkerCluster JS
        wp_enqueue_script(
            'leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
            ['leaflet'],
            '1.5.3',
            true
        );

        // Enqueue map JS
        wp_enqueue_script(
            'bd-map',
            $plugin_url . '/assets/js/directory-map.js',
            ['jquery', 'leaflet', 'leaflet-markercluster'],
            '1.0.4',
            true
        );
        
        // Enqueue filter JS
        wp_enqueue_script(
            'bd-filters',
            $plugin_url . '/assets/js/directory-filters.js',
            ['jquery', 'bd-map'],
            '1.0.4',
            true
        );

        // Pass API URL and nonce to JavaScript
        wp_localize_script('bd-filters', 'bdVars', [
            'apiUrl' => home_url('/wp-json/bd/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}, 20);