<?php
namespace BusinessDirectory\Utils;

class Cache {
    
    /**
     * Get or set a cached value
     */
    public static function remember($key, $ttl, $callback) {
        $value = get_transient($key);
        
        if (false !== $value) {
            return $value;
        }
        
        $value = $callback();
        set_transient($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Invalidate all business-related caches
     */
    public static function invalidate_business_caches() {
        global $wpdb;
        
        // Delete all transients starting with bd_
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_bd_%'
            OR option_name LIKE '_transient_timeout_bd_%'
        ");
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Get cache key for business query
     */
    public static function get_query_key($filters) {
        return 'bd_query_' . md5(serialize($filters));
    }
}
