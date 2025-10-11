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
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
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
        }
    }
}
