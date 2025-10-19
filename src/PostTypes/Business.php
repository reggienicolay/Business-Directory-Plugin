<?php
namespace BD\PostTypes;

class Business {

	public static function register() {
		register_post_type(
			'bd_business',
			array(
				'labels'        => array(
					'name'          => __( 'Businesses', 'business-directory' ),
					'singular_name' => __( 'Business', 'business-directory' ),
					'add_new'       => __( 'Add New', 'business-directory' ),
					'add_new_item'  => __( 'Add New Business', 'business-directory' ),
					'edit_item'     => __( 'Edit Business', 'business-directory' ),
					'menu_name'     => __( 'Directory', 'business-directory' ),
				),
				'public'        => true,
				'show_ui'       => true,
				'show_in_menu'  => true,
				'rewrite'       => array( 'slug' => 'places' ),
				'has_archive'   => true,
				'menu_position' => 20,
				'menu_icon'     => 'dashicons-store',
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest'  => true,
			)
		);
	}
}
