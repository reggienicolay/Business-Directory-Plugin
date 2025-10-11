<?php
namespace BD;

class Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    private function init_components() {
        // Admin components
        if (is_admin()) {
            new Admin\MetaBoxes();
            new Admin\ImporterPage();
        }
        
        // Frontend components
        new Frontend\Shortcodes();
    }
    
    public function register_post_types() {
        PostTypes\Business::register();
    }
    
    public function register_taxonomies() {
        Taxonomies\Category::register();
        Taxonomies\Area::register();
        Taxonomies\Tag::register();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'business-directory',
            false,
            dirname(BD_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    public function admin_assets($hook) {
        $screen = get_current_screen();
        
        if ($screen && $screen->post_type === 'bd_business') {
            wp_enqueue_style(
                'bd-admin',
                BD_PLUGIN_URL . 'assets/css/admin.css',
                [],
                BD_VERSION
            );
            
            wp_enqueue_script(
                'bd-admin',
                BD_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                BD_VERSION,
                true
            );
            
            // Enqueue map script for edit screen
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                wp_enqueue_script(
                    'bd-admin-map',
                    BD_PLUGIN_URL . 'assets/js/admin-map.js',
                    ['jquery'],
                    BD_VERSION,
                    true
                );
            }
        }
    }
    
    public function frontend_assets() {
        wp_register_style(
            'bd-frontend',
            BD_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            BD_VERSION
        );
        
        wp_register_script(
            'bd-frontend',
            BD_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            BD_VERSION,
            true
        );
    }
    
    public function register_rest_routes() {
        REST\BusinessesController::register();
    }
}
