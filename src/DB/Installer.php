<?php
namespace BD\DB;

class Installer {
    
    const DB_VERSION = '2.1.0'; // Bumped version for gamification tables
    
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::flush_rewrite_rules();
        
        $current_version = get_option('bd_db_version', '1.0.0');
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::upgrade_database($current_version);
        }
        
        update_option('bd_db_version', self::DB_VERSION);
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Existing tables
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
            author_email varchar(120) DEFAULT NULL,
            rating tinyint(1) UNSIGNED NOT NULL,
            title varchar(180) DEFAULT NULL,
            content text DEFAULT NULL,
            photo_ids text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_business (business_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        $submissions_table = $wpdb->prefix . 'bd_submissions';
        $submissions_sql = "CREATE TABLE IF NOT EXISTS $submissions_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_data longtext NOT NULL,
            submitted_by bigint(20) UNSIGNED DEFAULT NULL,
            submitter_name varchar(120) DEFAULT NULL,
            submitter_email varchar(120) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // NEW: Gamification Tables
        $user_reputation_table = $wpdb->prefix . 'bd_user_reputation';
        $user_reputation_sql = "CREATE TABLE IF NOT EXISTS $user_reputation_table (
            user_id bigint(20) UNSIGNED NOT NULL,
            total_points int(11) NOT NULL DEFAULT 0,
            total_reviews int(11) NOT NULL DEFAULT 0,
            helpful_votes int(11) NOT NULL DEFAULT 0,
            lists_created int(11) NOT NULL DEFAULT 0,
            photos_uploaded int(11) NOT NULL DEFAULT 0,
            categories_reviewed int(11) NOT NULL DEFAULT 0,
            badges text DEFAULT NULL,
            badge_count int(11) NOT NULL DEFAULT 0,
            rank varchar(50) NOT NULL DEFAULT 'newcomer',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_points (total_points),
            KEY idx_rank (rank)
        ) $charset_collate;";
        
        $user_activity_table = $wpdb->prefix . 'bd_user_activity';
        $user_activity_sql = "CREATE TABLE IF NOT EXISTS $user_activity_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            activity_type varchar(50) NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_type (activity_type),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        $badge_awards_table = $wpdb->prefix . 'bd_badge_awards';
        $badge_awards_sql = "CREATE TABLE IF NOT EXISTS $badge_awards_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            badge_key varchar(100) NOT NULL,
            awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            awarded_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_badge (user_id, badge_key),
            KEY idx_user (user_id),
            KEY idx_badge (badge_key),
            KEY idx_awarded (awarded_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($locations_sql);
        dbDelta($reviews_sql);
        dbDelta($submissions_sql);
        dbDelta($user_reputation_sql);
        dbDelta($user_activity_sql);
        dbDelta($badge_awards_sql);
    }
    
    private static function upgrade_database($from_version) {
        global $wpdb;
        
        if (version_compare($from_version, '2.0.0', '<')) {
            $reviews_table = $wpdb->prefix . 'bd_reviews';
            
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $reviews_table");
            $column_names = array_column($columns, 'Field');
            
            if (!in_array('photo_ids', $column_names)) {
                $wpdb->query("ALTER TABLE $reviews_table ADD COLUMN photo_ids text DEFAULT NULL AFTER content");
            }
            if (!in_array('author_email', $column_names)) {
                $wpdb->query("ALTER TABLE $reviews_table ADD COLUMN author_email varchar(120) DEFAULT NULL AFTER author_name");
            }
            if (!in_array('ip_address', $column_names)) {
                $wpdb->query("ALTER TABLE $reviews_table ADD COLUMN ip_address varchar(45) DEFAULT NULL AFTER status");
            }
        }
        
        // Run gamification table creation if upgrading from pre-2.1.0
        if (version_compare($from_version, '2.1.0', '<')) {
            self::create_tables(); // This will create missing tables
        }
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