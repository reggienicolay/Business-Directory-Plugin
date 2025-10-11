<?php
namespace BD\Security;

class RateLimit {
    
    public static function check($action, $identifier, $max = 3, $window = 3600) {
        $key = "bd_ratelimit_{$action}_" . md5($identifier);
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, $window);
            return true;
        }
        
        if ($attempts >= $max) {
            return new \WP_Error('rate_limit', sprintf(
                __('Too many attempts. Try again in %d minutes.', 'business-directory'),
                ceil($window / 60)
            ));
        }
        
        set_transient($key, $attempts + 1, $window);
        return true;
    }
    
    public static function get_client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
