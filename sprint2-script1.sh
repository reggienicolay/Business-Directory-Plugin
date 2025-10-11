#!/bin/bash

################################################################################
# Sprint 2 Week 1 - Part 1: Database Tables & Security
# Run this FIRST
################################################################################

set -e

echo "🚀 Sprint 2 Week 1 - Part 1/3: Database & Security"
echo "===================================================="
echo ""

if [[ ! -f "business-directory.php" ]]; then
    echo "❌ Error: Run from plugin directory"
    exit 1
fi

echo "✅ Creating directories..."
mkdir -p src/{Forms,Moderation,Notifications,Security}

################################################################################
# UPDATE DATABASE INSTALLER
################################################################################

echo "📝 Updating database installer..."

cat > src/DB/Installer.php << 'INSTALLEREOF'
<?php
namespace BD\DB;

class Installer {
    
    const DB_VERSION = '2.0.0';
    
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
        
        // NEW: Submissions table
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
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($locations_sql);
        dbDelta($reviews_sql);
        dbDelta($submissions_sql);
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
INSTALLEREOF

################################################################################
# SUBMISSIONS TABLE DAO
################################################################################

echo "📝 Creating SubmissionsTable..."

cat > src/DB/SubmissionsTable.php << 'SUBMISSIONSEOF'
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
SUBMISSIONSEOF

################################################################################
# SECURITY: RATE LIMITING
################################################################################

echo "📝 Creating RateLimit..."

cat > src/Security/RateLimit.php << 'RATELIMITEOF'
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
RATELIMITEOF

################################################################################
# SECURITY: TURNSTILE
################################################################################

echo "📝 Creating Captcha handler..."

cat > src/Security/Captcha.php << 'CAPTCHAEOF'
<?php
namespace BD\Security;

class Captcha {
    
    const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    public static function verify_turnstile($token) {
        $secret = get_option('bd_turnstile_secret_key');
        
        if (empty($secret)) {
            return true; // Skip if not configured
        }
        
        $response = wp_remote_post(self::TURNSTILE_VERIFY_URL, [
            'body' => [
                'secret' => $secret,
                'response' => $token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['success']) && $body['success']) {
            return true;
        }
        
        return new \WP_Error('captcha_failed', __('Verification failed. Please try again.', 'business-directory'));
    }
}
CAPTCHAEOF

################################################################################
# SETTINGS PAGE
################################################################################

echo "📝 Creating settings page..."

cat > src/Admin/Settings.php << 'SETTINGSEOF'
<?php
namespace BD\Admin;

class Settings {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Settings', 'business-directory'),
            __('Settings', 'business-directory'),
            'manage_options',
            'bd-settings',
            [$this, 'render_page']
        );
    }
    
    public function register_settings() {
        register_setting('bd_settings', 'bd_turnstile_site_key');
        register_setting('bd_settings', 'bd_turnstile_secret_key');
        register_setting('bd_settings', 'bd_notification_emails');
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Business Directory Settings', 'business-directory'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('bd_settings'); ?>
                
                <h2><?php _e('Cloudflare Turnstile', 'business-directory'); ?></h2>
                <p><?php _e('Get free keys at: <a href="https://dash.cloudflare.com/sign-up/turnstile" target="_blank">Cloudflare Turnstile</a>', 'business-directory'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="bd_turnstile_site_key"><?php _e('Site Key', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_turnstile_site_key" name="bd_turnstile_site_key" value="<?php echo esc_attr(get_option('bd_turnstile_site_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bd_turnstile_secret_key"><?php _e('Secret Key', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_turnstile_secret_key" name="bd_turnstile_secret_key" value="<?php echo esc_attr(get_option('bd_turnstile_secret_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Notifications', 'business-directory'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="bd_notification_emails"><?php _e('Email Addresses', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_notification_emails" name="bd_notification_emails" value="<?php echo esc_attr(get_option('bd_notification_emails', get_option('admin_email'))); ?>" class="regular-text" />
                            <p class="description"><?php _e('Comma-separated list of emails to receive notifications', 'business-directory'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
SETTINGSEOF

echo ""
echo "✅ Part 1 Complete!"
echo ""
echo "NEXT STEPS:"
echo "1. Deactivate then reactivate the plugin to create new tables"
echo "2. Run Part 2: bash sprint2-script2.sh"
echo ""
