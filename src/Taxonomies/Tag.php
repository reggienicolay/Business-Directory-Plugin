<?php
namespace BD\Taxonomies;

class Tag {
    
    public static function register() {
        register_taxonomy('bd_tag', 'bd_business', [
            'labels' => [
                'name' => __('Tags', 'business-directory'),
                'singular_name' => __('Tag', 'business-directory'),
            ],
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'tag'],
            'show_in_rest' => true,
        ]);
    }
}
