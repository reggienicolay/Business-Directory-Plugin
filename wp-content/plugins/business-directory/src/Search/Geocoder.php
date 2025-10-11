<?php
namespace BusinessDirectory\Search;

class Geocoder {
    
    /**
     * Geocode an address to lat/lng using Nominatim (free, no API key)
     */
    public static function geocode($address) {
        $cache_key = 'bd_geocode_' . md5($address);
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $encoded_address = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?q={$encoded_address}&format=json&limit=1";
        
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'BusinessDirectory WordPress Plugin',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data[0])) {
            return false;
        }
        
        $result = [
            'lat' => floatval($data[0]['lat']),
            'lng' => floatval($data[0]['lon']),
            'display_name' => $data[0]['display_name'],
        ];
        
        // Cache for 30 days
        set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Reverse geocode lat/lng to address
     */
    public static function reverse_geocode($lat, $lng) {
        $cache_key = 'bd_reverse_' . md5("{$lat},{$lng}");
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lng}&format=json";
        
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'BusinessDirectory WordPress Plugin',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data)) {
            return false;
        }
        
        $result = [
            'display_name' => $data['display_name'] ?? '',
            'city' => $data['address']['city'] ?? $data['address']['town'] ?? '',
            'state' => $data['address']['state'] ?? '',
            'country' => $data['address']['country'] ?? '',
        ];
        
        // Cache for 30 days
        set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);
        
        return $result;
    }
}
