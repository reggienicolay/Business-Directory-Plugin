<?php
namespace BD\DB;

class ReviewsTable {
    
    private static $table_name = null;
    
    private static function table() {
        global $wpdb;
        if (self::$table_name === null) {
            self::$table_name = $wpdb->prefix . 'bd_reviews';
        }
        return self::$table_name;
    }
    
    public static function insert($data) {
        global $wpdb;
        
        if (empty($data['business_id']) || empty($data['rating'])) {
            return new \WP_Error('missing_fields', 'Business ID and rating are required');
        }
        
        $rating = absint($data['rating']);
        if ($rating < 1 || $rating > 5) {
            return new \WP_Error('invalid_rating', 'Rating must be between 1 and 5');
        }
        
        $clean_data = [
            'business_id' => absint($data['business_id']),
            'rating' => $rating,
            'author_name' => isset($data['author_name']) ? sanitize_text_field($data['author_name']) : null,
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : null,
            'content' => isset($data['content']) ? wp_kses_post($data['content']) : null,
            'status' => 'pending',
        ];
        
        $result = $wpdb->insert(
            self::table(),
            $clean_data,
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_by_business($business_id) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table() . " 
             WHERE business_id = %d AND status = 'approved' 
             ORDER BY created_at DESC",
            absint($business_id)
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
}
