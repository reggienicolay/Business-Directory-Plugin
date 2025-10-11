<?php
/**
 * Cache Warmer - Pre-populate caches for better performance
 */

namespace BusinessDirectory\Performance;

use BusinessDirectory\Search\FilterHandler;
use BusinessDirectory\Utils\Cache;

class CacheWarmer {
    
    /**
     * Warm all caches
     */
    public static function warm_caches() {
        // Warm filter metadata
        FilterHandler::get_filter_metadata();
        
        // Warm popular queries
        self::warm_popular_queries();
        
        // Schedule next warming
        if (!wp_next_scheduled('bd_warm_caches')) {
            wp_schedule_single_event(time() + 3600, 'bd_warm_caches');
        }
    }
    
    /**
     * Warm popular query combinations
     */
    private static function warm_popular_queries() {
        // This is a placeholder for actual implementation
        // In production, you'd track actual popular queries
        $popular_queries = [
            ['sort' => 'rating', 'per_page' => 20],
            ['sort' => 'newest', 'per_page' => 20],
            ['min_rating' => 4, 'per_page' => 20],
        ];
        
        // Actual warming would happen here
        // Left as placeholder to avoid heavy processing
    }
    
    /**
     * Clear all caches
     */
    public static function clear_caches() {
        Cache::invalidate_business_caches();
    }
}

// Hook into WordPress
add_action('bd_warm_caches', [\BusinessDirectory\Performance\CacheWarmer::class, 'warm_caches']);

// Clear caches when businesses are updated
add_action('save_post_business', function($post_id) {
    \BusinessDirectory\Performance\CacheWarmer::clear_caches();
});

// Initialize cache warming (only once)
if (!wp_next_scheduled('bd_warm_caches')) {
    wp_schedule_single_event(time() + 300, 'bd_warm_caches');
}
