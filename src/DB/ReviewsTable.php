<?php

namespace BD\DB;

class ReviewsTable
{

    private static $table_name = null;

    private static function table()
    {
        global $wpdb;
        if (self::$table_name === null) {
            self::$table_name = $wpdb->prefix . 'bd_reviews';
        }
        return self::$table_name;
    }

    public static function insert($data)
    {
        global $wpdb;

        if (empty($data['business_id']) || empty($data['rating'])) {
            error_log('BD Reviews: Missing required fields');
            return new \WP_Error('missing_fields', 'Business ID and rating are required');
        }

        $rating = absint($data['rating']);
        if ($rating < 1 || $rating > 5) {
            error_log('BD Reviews: Invalid rating - ' . $rating);
            return new \WP_Error('invalid_rating', 'Rating must be between 1 and 5');
        }

        // Build data array dynamically, only including non-null values
        $clean_data = [
            'business_id' => absint($data['business_id']),
            'rating' => $rating,
            'status' => 'pending',
        ];

        // Build format array dynamically
        $formats = ['%d', '%d', '%s']; // business_id, rating, status

        // Add optional fields only if they exist
        if (isset($data['user_id']) && $data['user_id']) {
            $clean_data['user_id'] = absint($data['user_id']);
            $formats[] = '%d';
        }

        if (isset($data['author_name']) && !empty($data['author_name'])) {
            $clean_data['author_name'] = sanitize_text_field($data['author_name']);
            $formats[] = '%s';
        }

        if (isset($data['author_email']) && !empty($data['author_email'])) {
            $clean_data['author_email'] = sanitize_email($data['author_email']);
            $formats[] = '%s';
        }

        if (isset($data['title']) && !empty($data['title'])) {
            $clean_data['title'] = sanitize_text_field($data['title']);
            $formats[] = '%s';
        }

        if (isset($data['content']) && !empty($data['content'])) {
            $clean_data['content'] = wp_kses_post($data['content']);
            $formats[] = '%s';
        }

        if (isset($data['photo_ids']) && !empty($data['photo_ids'])) {
            $clean_data['photo_ids'] = sanitize_text_field($data['photo_ids']);
            $formats[] = '%s';
        }

        if (isset($data['ip_address']) && !empty($data['ip_address'])) {
            $clean_data['ip_address'] = sanitize_text_field($data['ip_address']);
            $formats[] = '%s';
        }

        // DEBUG: Log what we're trying to insert
        error_log('BD Reviews: Attempting insert with data: ' . print_r($clean_data, true));
        error_log('BD Reviews: Using formats: ' . print_r($formats, true));

        $result = $wpdb->insert(
            self::table(),
            $clean_data,
            $formats
        );

        // DEBUG: Log the result and any errors
        if ($result === false) {
            error_log('BD Reviews: Insert failed. Error: ' . $wpdb->last_error);
            error_log('BD Reviews: Last query: ' . $wpdb->last_query);
        } else {
            error_log('BD Reviews: Insert successful. ID: ' . $wpdb->insert_id);
        }

        return $result ? $wpdb->insert_id : false;
    }

    public static function get_by_business($business_id)
    {
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