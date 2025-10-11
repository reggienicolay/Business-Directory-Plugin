<?php
namespace BD\DB;

class Installer {
    
    const DB_VERSION = '1.0.0';
    
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::flush_rewrite_rules();
        add_option('bd_db_version', self::DB_VERSION);
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $locations_table = $wpdb->prefix . 'bd_locations';
        $locations_sql = "CREATE TABLE IF NOT EXISTS $locations_table (
            business_id bigint(20) UNSIGNED NOT NULL,
            lat double NOT NULL,
            lng double NOT NULL,
            geohash char(12) DEFAULT NULL,
            address varchar(255) DEFAULT NULL,
            city varchar(120) DEFAULT NULL,
            state varchar(80) DEFAULT NULL,
            postal_code varchar(20) DEFAULT NULL,
            country varchar(80) DEFAULT NULL,
            PRIMARY KEY (business_id),
            KEY idx_lat (lat),
            KEY idx_lng (lng),
            KEY idx_geohash (geohash)
        ) $charset_collate;";
        
        $reviews_table = $wpdb->prefix . 'bd_reviews';
        $reviews_sql = "CREATE TABLE IF NOT EXISTS $reviews_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            author_name varchar(120) DEFAULT NULL,
            rating tinyint(1) UNSIGNED NOT NULL,
            title varchar(180) DEFAULT NULL,
            content text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_business (business_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($locations_sql);
        dbDelta($reviews_sql);
    }
    
    private static function create_roles() {
        \BD\Roles\Manager::create();
    }
    
    private static function flush_rewrite_rules() {
        \BD\PostTypes\Business::register();
        \BD\Taxonomies\Category::register();
        \BD\Taxonomies\Area::register();
        \BD\Taxonomies\Tag::register();
        flush_rewrite_rules();
    }
}
