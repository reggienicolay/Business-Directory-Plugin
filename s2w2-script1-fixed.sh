#!/bin/bash

# Sprint 2 Week 2 - Script 1: Search Infrastructure (FIXED & IDEMPOTENT)
# Creates FilterHandler, QueryBuilder, Geocoder, Cache utilities, and REST API enhancements

set -e

PLUGIN_DIR="wp-content/plugins/business-directory"

echo "🚀 Sprint 2 Week 2 - Script 1: Search Infrastructure (FIXED)"
echo "============================================================"

# Create Search directory
mkdir -p "$PLUGIN_DIR/src/Search"
mkdir -p "$PLUGIN_DIR/src/Utils"
mkdir -p "$PLUGIN_DIR/src/API"
mkdir -p "$PLUGIN_DIR/includes"

# ============================================================================
# 1. FilterHandler.php - Processes filter requests
# ============================================================================
echo "Creating FilterHandler.php..."
cat > "$PLUGIN_DIR/src/Search/FilterHandler.php" << 'EOF'
<?php
namespace BusinessDirectory\Search;

class FilterHandler {
    
    /**
     * Sanitize and validate filter inputs
     */
    public static function sanitize_filters($filters) {
        $sanitized = [];
        
        // Categories (array of term IDs)
        if (!empty($filters['categories'])) {
            $cats = is_array($filters['categories']) ? $filters['categories'] : explode(',', $filters['categories']);
            $sanitized['categories'] = array_map('intval', array_filter($cats));
        }
        
        // Areas (array of term IDs)
        if (!empty($filters['areas'])) {
            $areas = is_array($filters['areas']) ? $filters['areas'] : explode(',', $filters['areas']);
            $sanitized['areas'] = array_map('intval', array_filter($areas));
        }
        
        // Price levels (array of strings)
        if (!empty($filters['price_level'])) {
            $valid_prices = ['$', '$$', '$$$', '$$$$'];
            $prices = is_array($filters['price_level']) ? $filters['price_level'] : explode(',', $filters['price_level']);
            $sanitized['price_level'] = array_intersect($prices, $valid_prices);
        }
        
        // Minimum rating (1-5)
        if (isset($filters['min_rating'])) {
            $rating = floatval($filters['min_rating']);
            $sanitized['min_rating'] = max(0, min(5, $rating));
        }
        
        // Open now (boolean)
        if (isset($filters['open_now'])) {
            $sanitized['open_now'] = filter_var($filters['open_now'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Keyword search
        if (!empty($filters['q'])) {
            $sanitized['q'] = sanitize_text_field($filters['q']);
        }
        
        // Location & radius
        if (isset($filters['lat'])) {
            $sanitized['lat'] = floatval($filters['lat']);
        }
        if (isset($filters['lng'])) {
            $sanitized['lng'] = floatval($filters['lng']);
        }
        if (isset($filters['radius_km'])) {
            $sanitized['radius_km'] = max(1, min(80, floatval($filters['radius_km']))); // 1-80km
        }
        
        // Sorting
        $valid_sorts = ['distance', 'rating', 'newest', 'name'];
        if (!empty($filters['sort']) && in_array($filters['sort'], $valid_sorts)) {
            $sanitized['sort'] = $filters['sort'];
        } else {
            $sanitized['sort'] = 'distance';
        }
        
        // Pagination
        $sanitized['page'] = !empty($filters['page']) ? max(1, intval($filters['page'])) : 1;
        $sanitized['per_page'] = !empty($filters['per_page']) ? max(1, min(100, intval($filters['per_page']))) : 20;
        
        return $sanitized;
    }
    
    /**
     * Get filter metadata (available options with counts)
     */
    public static function get_filter_metadata() {
        $cache_key = 'bd_filter_metadata';
        $metadata = get_transient($cache_key);
        
        if (false !== $metadata) {
            return $metadata;
        }
        
        $metadata = [
            'categories' => self::get_category_counts(),
            'areas' => self::get_area_counts(),
            'price_levels' => self::get_price_level_counts(),
            'rating_ranges' => self::get_rating_counts(),
        ];
        
        // Cache for 15 minutes
        set_transient($cache_key, $metadata, 15 * MINUTE_IN_SECONDS);
        
        return $metadata;
    }
    
    /**
     * Get category counts
     */
    private static function get_category_counts() {
        $terms = get_terms([
            'taxonomy' => 'business_category',
            'hide_empty' => true,
        ]);
        
        if (is_wp_error($terms)) {
            return [];
        }
        
        $counts = [];
        foreach ($terms as $term) {
            $counts[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
            ];
        }
        
        return $counts;
    }
    
    /**
     * Get area counts
     */
    private static function get_area_counts() {
        $terms = get_terms([
            'taxonomy' => 'business_area',
            'hide_empty' => true,
        ]);
        
        if (is_wp_error($terms)) {
            return [];
        }
        
        $counts = [];
        foreach ($terms as $term) {
            $counts[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
            ];
        }
        
        return $counts;
    }
    
    /**
     * Get price level counts
     */
    private static function get_price_level_counts() {
        global $wpdb;
        
        $counts = $wpdb->get_results("
            SELECT meta_value as price_level, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'bd_price_level'
            AND meta_value != ''
            GROUP BY meta_value
        ", ARRAY_A);
        
        $result = [];
        foreach ($counts as $row) {
            $result[$row['price_level']] = intval($row['count']);
        }
        
        return $result;
    }
    
    /**
     * Get rating range counts
     */
    private static function get_rating_counts() {
        global $wpdb;
        
        $businesses = $wpdb->get_col("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'bd_avg_rating'
            AND meta_value != ''
        ");
        
        $ranges = [
            '4+' => 0,
            '3+' => 0,
            '2+' => 0,
            '1+' => 0,
        ];
        
        foreach ($businesses as $business_id) {
            $rating = floatval(get_post_meta($business_id, 'bd_avg_rating', true));
            
            if ($rating >= 4) $ranges['4+']++;
            if ($rating >= 3) $ranges['3+']++;
            if ($rating >= 2) $ranges['2+']++;
            if ($rating >= 1) $ranges['1+']++;
        }
        
        return $ranges;
    }
    
    /**
     * Check if business is open now
     */
    public static function is_open_now($business_id) {
        $hours = get_post_meta($business_id, 'bd_hours', true);
        
        if (empty($hours) || !is_array($hours)) {
            return false;
        }
        
        $current_day = strtolower(date('l')); // 'monday', 'tuesday', etc.
        $current_time = date('H:i'); // '14:30'
        
        if (!isset($hours[$current_day])) {
            return false;
        }
        
        $today = $hours[$current_day];
        
        // Check if closed
        if (empty($today['open']) || empty($today['close'])) {
            return false;
        }
        
        return ($current_time >= $today['open'] && $current_time <= $today['close']);
    }
}
EOF

# ============================================================================
# 2. QueryBuilder.php - Builds complex WP_Query
# ============================================================================
echo "Creating QueryBuilder.php..."
cat > "$PLUGIN_DIR/src/Search/QueryBuilder.php" << 'EOF'
<?php
namespace BusinessDirectory\Search;

class QueryBuilder {
    
    private $filters = [];
    private $base_args = [];
    
    public function __construct($filters = []) {
        $this->filters = $filters;
        
        $this->base_args = [
            'post_type' => 'business',
            'post_status' => 'publish',
            'posts_per_page' => $filters['per_page'] ?? 20,
            'paged' => $filters['page'] ?? 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
    }
    
    /**
     * Build the complete WP_Query args
     */
    public function build() {
        $args = $this->base_args;
        
        // Keyword search
        if (!empty($this->filters['q'])) {
            $args['s'] = $this->filters['q'];
        }
        
        // Tax queries
        $tax_query = $this->build_tax_query();
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        
        // Meta queries
        $meta_query = $this->build_meta_query();
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        // Sorting (non-distance sorts)
        if ($this->filters['sort'] === 'rating') {
            $args['meta_key'] = 'bd_avg_rating';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($this->filters['sort'] === 'name') {
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
        } elseif ($this->filters['sort'] === 'newest') {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }
        
        return $args;
    }
    
    /**
     * Build taxonomy query
     */
    private function build_tax_query() {
        $tax_query = ['relation' => 'AND'];
        
        // Categories
        if (!empty($this->filters['categories'])) {
            $tax_query[] = [
                'taxonomy' => 'business_category',
                'field' => 'term_id',
                'terms' => $this->filters['categories'],
                'operator' => 'IN',
            ];
        }
        
        // Areas
        if (!empty($this->filters['areas'])) {
            $tax_query[] = [
                'taxonomy' => 'business_area',
                'field' => 'term_id',
                'terms' => $this->filters['areas'],
                'operator' => 'IN',
            ];
        }
        
        return count($tax_query) > 1 ? $tax_query : [];
    }
    
    /**
     * Build meta query
     */
    private function build_meta_query() {
        $meta_query = ['relation' => 'AND'];
        
        // Price level
        if (!empty($this->filters['price_level'])) {
            $meta_query[] = [
                'key' => 'bd_price_level',
                'value' => $this->filters['price_level'],
                'compare' => 'IN',
            ];
        }
        
        // Minimum rating
        if (!empty($this->filters['min_rating'])) {
            $meta_query[] = [
                'key' => 'bd_avg_rating',
                'value' => $this->filters['min_rating'],
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        return count($meta_query) > 1 ? $meta_query : [];
    }
    
    /**
     * Get businesses with location filtering
     */
    public function get_businesses_with_location() {
        // First, get businesses matching other filters
        $args = $this->build();
        $args['posts_per_page'] = -1; // Get all for distance filtering
        $args['fields'] = 'ids';
        
        $business_ids = get_posts($args);
        
        if (empty($business_ids)) {
            return ['businesses' => [], 'total' => 0];
        }
        
        // If no location filter, just paginate and return
        if (empty($this->filters['lat']) || empty($this->filters['lng'])) {
            return $this->paginate_businesses($business_ids);
        }
        
        // Calculate distances and filter by radius
        $businesses_with_distance = $this->calculate_distances($business_ids);
        
        // Filter by radius if specified
        if (!empty($this->filters['radius_km'])) {
            $businesses_with_distance = array_filter($businesses_with_distance, function($b) {
                return $b['distance_km'] <= $this->filters['radius_km'];
            });
        }
        
        // Sort by distance if requested
        if ($this->filters['sort'] === 'distance') {
            usort($businesses_with_distance, function($a, $b) {
                return $a['distance_km'] <=> $b['distance_km'];
            });
        }
        
        // Apply "open now" filter (must be done after getting all data)
        if (!empty($this->filters['open_now'])) {
            $businesses_with_distance = array_filter($businesses_with_distance, function($b) {
                return FilterHandler::is_open_now($b['id']);
            });
        }
        
        return $this->paginate_array($businesses_with_distance);
    }
    
    /**
     * Calculate distances for businesses
     */
    private function calculate_distances($business_ids) {
        $user_lat = $this->filters['lat'];
        $user_lng = $this->filters['lng'];
        
        $businesses = [];
        
        foreach ($business_ids as $id) {
            $location = get_post_meta($id, 'bd_location', true);
            
            if (empty($location['lat']) || empty($location['lng'])) {
                continue;
            }
            
            $distance_km = $this->haversine_distance(
                $user_lat,
                $user_lng,
                $location['lat'],
                $location['lng']
            );
            
            $businesses[] = [
                'id' => $id,
                'distance_km' => $distance_km,
                'distance_mi' => $distance_km * 0.621371, // km to miles
            ];
        }
        
        return $businesses;
    }
    
    /**
     * Haversine formula for distance calculation
     */
    private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earth_radius * $c;
    }
    
    /**
     * Paginate business IDs only
     */
    private function paginate_businesses($business_ids) {
        $total = count($business_ids);
        $per_page = $this->filters['per_page'] ?? 20;
        $page = $this->filters['page'] ?? 1;
        
        $offset = ($page - 1) * $per_page;
        $paginated_ids = array_slice($business_ids, $offset, $per_page);
        
        $businesses = [];
        foreach ($paginated_ids as $id) {
            $businesses[] = ['id' => $id];
        }
        
        return [
            'businesses' => $businesses,
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ];
    }
    
    /**
     * Paginate array of businesses with distance
     */
    private function paginate_array($businesses) {
        $total = count($businesses);
        $per_page = $this->filters['per_page'] ?? 20;
        $page = $this->filters['page'] ?? 1;
        
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($businesses, $offset, $per_page);
        
        return [
            'businesses' => $paginated,
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ];
    }
}
EOF

# ============================================================================
# 3. Geocoder.php - Geocoding helper
# ============================================================================
echo "Creating Geocoder.php..."
cat > "$PLUGIN_DIR/src/Search/Geocoder.php" << 'EOF'
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
EOF

# ============================================================================
# 4. Cache.php - Caching helper utility
# ============================================================================
echo "Creating Cache.php..."
cat > "$PLUGIN_DIR/src/Utils/Cache.php" << 'EOF'
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
EOF

# ============================================================================
# 5. BusinessEndpoint.php - Enhanced REST API
# ============================================================================
echo "Creating enhanced BusinessEndpoint.php..."
cat > "$PLUGIN_DIR/src/API/BusinessEndpoint.php" << 'EOF'
<?php
namespace BusinessDirectory\API;

use BusinessDirectory\Search\FilterHandler;
use BusinessDirectory\Search\QueryBuilder;
use BusinessDirectory\Utils\Cache;

class BusinessEndpoint {
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }
    
    public static function register_routes() {
        // Enhanced businesses endpoint with filtering
        register_rest_route('bd/v1', '/businesses', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_businesses'],
            'permission_callback' => '__return_true',
            'args' => [
                'lat' => ['type' => 'number'],
                'lng' => ['type' => 'number'],
                'radius_km' => ['type' => 'number'],
                'categories' => ['type' => 'string'],
                'areas' => ['type' => 'string'],
                'price_level' => ['type' => 'string'],
                'min_rating' => ['type' => 'number'],
                'open_now' => ['type' => 'boolean'],
                'q' => ['type' => 'string'],
                'sort' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
            ],
        ]);
        
        // Filter metadata endpoint
        register_rest_route('bd/v1', '/filters', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_filter_metadata'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Get filtered businesses
     */
    public static function get_businesses($request) {
        // Sanitize filters
        $filters = FilterHandler::sanitize_filters($request->get_params());
        
        // Check cache
        $cache_key = Cache::get_query_key($filters);
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return rest_ensure_response($cached);
        }
        
        // Build and execute query
        $query_builder = new QueryBuilder($filters);
        $result = $query_builder->get_businesses_with_location();
        
        // Format businesses
        $businesses = [];
        foreach ($result['businesses'] as $b) {
            $business = self::format_business($b['id']);
            
            // Add distance if available
            if (isset($b['distance_km'])) {
                $business['distance'] = [
                    'km' => round($b['distance_km'], 2),
                    'mi' => round($b['distance_mi'], 2),
                    'display' => round($b['distance_mi'], 1) . ' mi away',
                ];
            }
            
            $businesses[] = $business;
        }
        
        // Calculate map bounds
        $bounds = self::calculate_bounds($businesses);
        
        $response = [
            'businesses' => $businesses,
            'total' => $result['total'],
            'pages' => $result['pages'] ?? 0,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'bounds' => $bounds,
            'filters_applied' => self::get_applied_filters($filters),
        ];
        
        // Cache for 5 minutes
        set_transient($cache_key, $response, 5 * MINUTE_IN_SECONDS);
        
        return rest_ensure_response($response);
    }
    
    /**
     * Format single business
     */
    private static function format_business($business_id) {
        $post = get_post($business_id);
        $location = get_post_meta($business_id, 'bd_location', true);
        $contact = get_post_meta($business_id, 'bd_contact', true);
        
        return [
            'id' => $business_id,
            'title' => get_the_title($business_id),
            'slug' => $post->post_name,
            'excerpt' => get_the_excerpt($business_id),
            'permalink' => get_permalink($business_id),
            'featured_image' => get_the_post_thumbnail_url($business_id, 'medium'),
            'rating' => floatval(get_post_meta($business_id, 'bd_avg_rating', true)),
            'review_count' => intval(get_post_meta($business_id, 'bd_review_count', true)),
            'price_level' => get_post_meta($business_id, 'bd_price_level', true),
            'categories' => wp_get_post_terms($business_id, 'business_category', ['fields' => 'names']),
            'areas' => wp_get_post_terms($business_id, 'business_area', ['fields' => 'names']),
            'location' => $location,
            'phone' => $contact['phone'] ?? '',
            'is_open_now' => FilterHandler::is_open_now($business_id),
        ];
    }
    
    /**
     * Calculate map bounds for all businesses
     */
    private static function calculate_bounds($businesses) {
        if (empty($businesses)) {
            return null;
        }
        
        $lats = [];
        $lngs = [];
        
        foreach ($businesses as $business) {
            if (!empty($business['location']['lat']) && !empty($business['location']['lng'])) {
                $lats[] = $business['location']['lat'];
                $lngs[] = $business['location']['lng'];
            }
        }
        
        if (empty($lats)) {
            return null;
        }
        
        return [
            'north' => max($lats),
            'south' => min($lats),
            'east' => max($lngs),
            'west' => min($lngs),
        ];
    }
    
    /**
     * Get summary of applied filters
     */
    private static function get_applied_filters($filters) {
        $applied = [];
        
        if (!empty($filters['categories'])) {
            $applied['categories'] = count($filters['categories']);
        }
        if (!empty($filters['areas'])) {
            $applied['areas'] = count($filters['areas']);
        }
        if (!empty($filters['price_level'])) {
            $applied['price_level'] = $filters['price_level'];
        }
        if (!empty($filters['min_rating'])) {
            $applied['min_rating'] = $filters['min_rating'];
        }
        if (!empty($filters['open_now'])) {
            $applied['open_now'] = true;
        }
        if (!empty($filters['q'])) {
            $applied['search'] = $filters['q'];
        }
        if (!empty($filters['radius_km'])) {
            $applied['radius_km'] = $filters['radius_km'];
        }
        
        return $applied;
    }
    
    /**
     * Get filter metadata
     */
    public static function get_filter_metadata() {
        $metadata = FilterHandler::get_filter_metadata();
        return rest_ensure_response($metadata);
    }
}
EOF

# ============================================================================
# 6. Database indexes helper
# ============================================================================
echo "Creating database-indexes.php..."
cat > "$PLUGIN_DIR/includes/database-indexes.php" << 'EOF'
<?php
/**
 * Add database indexes for search performance
 * This file is called during plugin activation
 */

function bd_add_database_indexes() {
    global $wpdb;
    
    // Check if indexes already exist to avoid duplicate key errors
    $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->posts}");
    $index_exists = false;
    
    foreach ($existing_indexes as $index) {
        if ($index->Key_name === 'idx_bd_business_date') {
            $index_exists = true;
            break;
        }
    }
    
    if (!$index_exists) {
        // Add index for business post type queries
        $wpdb->query("
            ALTER TABLE {$wpdb->posts}
            ADD INDEX idx_bd_business_date (post_type(20), post_date, post_status(20))
        ");
    }
    
    // Check postmeta indexes
    $existing_meta_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
    $meta_index_exists = false;
    
    foreach ($existing_meta_indexes as $index) {
        if ($index->Key_name === 'idx_bd_meta_search') {
            $meta_index_exists = true;
            break;
        }
    }
    
    if (!$meta_index_exists) {
        // Add index for meta queries (rating, price level)
        $wpdb->query("
            ALTER TABLE {$wpdb->postmeta}
            ADD INDEX idx_bd_meta_search (meta_key(191), meta_value(20))
        ");
    }
}

/**
 * Remove indexes on plugin deactivation
 */
function bd_remove_database_indexes() {
    global $wpdb;
    
    // Remove posts index
    $wpdb->query("
        ALTER TABLE {$wpdb->posts}
        DROP INDEX IF EXISTS idx_bd_business_date
    ");
    
    // Remove postmeta index
    $wpdb->query("
        ALTER TABLE {$wpdb->postmeta}
        DROP INDEX IF EXISTS idx_bd_meta_search
    ");
}
EOF

# ============================================================================
# 7. Create loader file (IDEMPOTENT APPROACH)
# ============================================================================
echo "Creating sprint2-week2-loader.php..."
cat > "$PLUGIN_DIR/includes/sprint2-week2-loader.php" << 'EOF'
<?php
/**
 * Sprint 2 Week 2 - Search Infrastructure Loader
 * This file is safe to include multiple times
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load once
if (defined('BD_S2W2_LOADED')) {
    return;
}

define('BD_S2W2_LOADED', true);

// Load Search classes
require_once plugin_dir_path(__FILE__) . '../src/Search/FilterHandler.php';
require_once plugin_dir_path(__FILE__) . '../src/Search/QueryBuilder.php';
require_once plugin_dir_path(__FILE__) . '../src/Search/Geocoder.php';

// Load Utils
require_once plugin_dir_path(__FILE__) . '../src/Utils/Cache.php';

// Load enhanced API
require_once plugin_dir_path(__FILE__) . '../src/API/BusinessEndpoint.php';

// Initialize API
\BusinessDirectory\API\BusinessEndpoint::init();

// Hook database indexes to plugin activation
add_action('bd_plugin_activation', 'bd_add_database_indexes');
add_action('bd_plugin_deactivation', 'bd_remove_database_indexes');
EOF

# ============================================================================
# 8. Update main plugin file (IDEMPOTENT)
# ============================================================================
echo "Updating business-directory.php..."

# Check if loader is already included
if ! grep -q "sprint2-week2-loader.php" "$PLUGIN_DIR/business-directory.php" 2>/dev/null; then
    cat >> "$PLUGIN_DIR/business-directory.php" << 'EOF'

// Load Sprint 2 Week 2 - Search Infrastructure
if (file_exists(plugin_dir_path(__FILE__) . 'includes/sprint2-week2-loader.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/sprint2-week2-loader.php';
}
EOF
    echo "✓ Added loader to business-directory.php"
else
    echo "✓ Loader already present in business-directory.php (skipped)"
fi

echo ""
echo "✅ Script 1 Complete: Search Infrastructure"
echo "============================================================"
echo "Created:"
echo "  ✓ FilterHandler.php - Filter processing"
echo "  ✓ QueryBuilder.php - Complex queries with distance"
echo "  ✓ Geocoder.php - Address geocoding (Nominatim)"
echo "  ✓ Cache.php - Caching utilities"
echo "  ✓ BusinessEndpoint.php - Enhanced REST API"
echo "  ✓ database-indexes.php - Performance indexes"
echo "  ✓ sprint2-week2-loader.php - Idempotent loader"
echo ""
echo "Features:"
echo "  ✓ Safe to run multiple times (idempotent)"
echo "  ✓ Proper error handling"
echo "  ✓ Database index safety checks"
echo "  ✓ Single loader file approach"
echo ""
echo "Next: Run sprint2-week2-script2.sh for Filter UI"
echo ""
