<?php
namespace BusinessDirectory\Search;

class QueryBuilder {
    
    private $filters = [];
    private $base_args = [];
    
    public function __construct($filters = []) {
        $this->filters = $filters;
        
        $this->base_args = [
            'post_type' => 'bd_business',
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
                'taxonomy' => 'bd_category',
                'field' => 'term_id',
                'terms' => $this->filters['categories'],
                'operator' => 'IN',
            ];
        }
        
        // Areas
        if (!empty($this->filters['areas'])) {
            $tax_query[] = [
                'taxonomy' => 'bd_area',
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
        global $wpdb;
        
        // First, get businesses matching other filters
        $args = $this->build();
        $args['posts_per_page'] = -1; // Get all for distance filtering
        $args['fields'] = 'ids';
        
        $business_ids = get_posts($args);
        
        if (empty($business_ids)) {
            return ['businesses' => [], 'total' => 0, 'pages' => 1];
        }
        
        // Get locations from custom table (wp_bd_locations)
        $ids_string = implode(',', array_map('intval', $business_ids));
        $locations = $wpdb->get_results(
            "SELECT business_id, lat, lng, address, city, state, postal_code 
             FROM {$wpdb->prefix}bd_locations 
             WHERE business_id IN ($ids_string)",
            ARRAY_A
        );
        
        // Map locations by business ID
        $location_map = [];
        foreach ($locations as $loc) {
            $location_map[$loc['business_id']] = [
                'lat' => floatval($loc['lat']),
                'lng' => floatval($loc['lng']),
                'address' => $loc['address'],
                'city' => $loc['city'] ?? '',
                'state' => $loc['state'] ?? '',
                'postal_code' => $loc['postal_code'] ?? ''
            ];
        }
        
        // Filter to only businesses that have location data
        $business_ids_with_location = array_keys($location_map);
        
        if (empty($business_ids_with_location)) {
            return ['businesses' => [], 'total' => 0, 'pages' => 1];
        }
        
        // If no user location provided, just return businesses with their IDs
        if (empty($this->filters['lat']) || empty($this->filters['lng'])) {
            $businesses = [];
            foreach ($business_ids_with_location as $id) {
                $businesses[] = ['id' => $id];
            }
            return $this->paginate_array($businesses);
        }
        
        // Calculate distances for businesses with location
        $businesses_with_distance = $this->calculate_distances_from_table($location_map);
        
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
     * Calculate distances for businesses from location table
     */
    private function calculate_distances_from_table($location_map) {
        $user_lat = $this->filters['lat'];
        $user_lng = $this->filters['lng'];
        
        $businesses = [];
        
        foreach ($location_map as $business_id => $location) {
            $distance_km = $this->haversine_distance(
                $user_lat,
                $user_lng,
                $location['lat'],
                $location['lng']
            );
            
            $businesses[] = [
                'id' => $business_id,
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
            'pages' => max(1, ceil($total / $per_page)),
        ];
    }
}