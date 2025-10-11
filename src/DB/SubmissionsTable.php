<?php
namespace BD\DB;

class SubmissionsTable {
    
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'bd_submissions';
    }
    
    public static function insert($data) {
        global $wpdb;
        
        $clean_data = [
            'business_data' => wp_json_encode($data['business_data']),
            'submitted_by' => isset($data['submitted_by']) ? absint($data['submitted_by']) : null,
            'submitter_name' => sanitize_text_field($data['submitter_name'] ?? ''),
            'submitter_email' => sanitize_email($data['submitter_email'] ?? ''),
            'ip_address' => sanitize_text_field($data['ip_address'] ?? ''),
        ];
        
        $result = $wpdb->insert(self::table(), $clean_data, ['%s', '%d', '%s', '%s', '%s']);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get($id) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            absint($id)
        ), ARRAY_A);
        
        if ($row && !empty($row['business_data'])) {
            $row['business_data'] = json_decode($row['business_data'], true);
        }
        
        return $row;
    }
    
    public static function get_pending($limit = 50) {
        global $wpdb;
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d",
            absint($limit)
        ), ARRAY_A);
        
        foreach ($rows as &$row) {
            if (!empty($row['business_data'])) {
                $row['business_data'] = json_decode($row['business_data'], true);
            }
        }
        
        return $rows;
    }
    
    public static function approve($id) {
        global $wpdb;
        
        return $wpdb->update(
            self::table(),
            ['status' => 'approved'],
            ['id' => absint($id)],
            ['%s'],
            ['%d']
        );
    }
    
    public static function reject($id) {
        global $wpdb;
        
        return $wpdb->update(
            self::table(),
            ['status' => 'rejected'],
            ['id' => absint($id)],
            ['%s'],
            ['%d']
        );
    }
}
