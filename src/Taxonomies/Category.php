<?php
/**
 * Category Taxonomy Registration
 *
 * @package BusinessDirectory
 * @since   0.1.0
 */

namespace BD\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Category
 *
 * Registers the bd_category taxonomy.
 *
 * @since 0.1.0
 */
class Category {

	/**
	 * Register the taxonomy.
	 *
	 * @since 0.1.0
	 * @since 0.1.8 Changed rewrite slug from 'category' to 'places/category'.
	 *
	 * @return void
	 */
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
				'rewrite'           => array(
					'slug'       => 'places/category',
					'with_front' => false,
				),
				'show_in_rest'      => true,
			)
		);
	}
}
