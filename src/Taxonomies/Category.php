<?php
namespace BD\Taxonomies;

class Category {

	public static function register() {
		register_taxonomy(
			'bd_category',
			'bd_business',
			array(
				'labels'            => array(
					'name'          => __( 'Categories', 'business-directory' ),
					'singular_name' => __( 'Category', 'business-directory' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'category' ),
				'show_in_rest'      => true,
			)
		);
	}
}
