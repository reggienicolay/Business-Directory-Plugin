<?php
namespace BusinessDirectory\API;

use BusinessDirectory\Search\Geocoder;

class GeocodeEndpoint {
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }
    
    public static function register_routes() {
        // Geocode address to coordinates
        register_rest_route('bd/v1', '/geocode', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'geocode'],
            'permission_callback' => '__return_true',
            'args' => [
                'address' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Reverse geocode coordinates to address
        register_rest_route('bd/v1', '/geocode/reverse', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'reverse_geocode'],
            'permission_callback' => '__return_true',
            'args' => [
                'lat' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'lng' => [
                    'required' => true,
                    'type' => 'number',
                ],
            ],
        ]);
    }
    
    /**
     * Geocode address
     */
    public static function geocode($request) {
        $address = $request->get_param('address');
        $result = Geocoder::geocode($address);
        
        if (!$result) {
            return new \WP_Error('geocode_failed', 'Could not geocode address', ['status' => 404]);
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Reverse geocode
     */
    public static function reverse_geocode($request) {
        $lat = $request->get_param('lat');
        $lng = $request->get_param('lng');
        
        $result = Geocoder::reverse_geocode($lat, $lng);
        
        if (!$result) {
            return new \WP_Error('reverse_geocode_failed', 'Could not reverse geocode coordinates', ['status' => 404]);
        }
        
        return rest_ensure_response($result);
    }
}
