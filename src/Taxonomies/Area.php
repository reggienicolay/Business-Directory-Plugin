<?php
namespace BD\Taxonomies;

class Area {

	public static function register() {
		register_taxonomy(
			'bd_area',
			'bd_business',
			array(
				'labels'            => array(
					'name'          => __( 'Areas', 'business-directory' ),
					'singular_name' => __( 'Area', 'business-directory' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'area' ),
				'show_in_rest'      => true,
			)
		);
	}
}
