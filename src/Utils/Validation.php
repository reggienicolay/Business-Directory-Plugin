<?php
namespace BD\Utils;

class Validation {
    
    public static function is_valid_latitude($lat) {
        $lat = floatval($lat);
        return $lat >= -90 && $lat <= 90;
    }
    
    public static function is_valid_longitude($lng) {
        $lng = floatval($lng);
        return $lng >= -180 && $lng <= 180;
    }
    
    public static function sanitize_phone($phone) {
        return preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
    }
    
    public static function sanitize_url($url) {
        return esc_url_raw($url);
    }
}
