<?php
/**
 * Area Taxonomy Registration
 *
 * @package BusinessDirectory
 * @since   0.1.0
 */

namespace BD\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Area
 *
 * Registers the bd_area taxonomy for geographic areas.
 *
 * @since 0.1.0
 */
class Area {

	/**
	 * Register the taxonomy.
	 *
	 * @since 0.1.0
	 * @since 0.1.8 Changed rewrite slug from 'area' to 'places/area'.
	 *
	 * @return void
	 */
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
				'rewrite'           => array(
					'slug'       => 'places/area',
					'with_front' => false,
				),
				'show_in_rest'      => true,
			)
		);
	}
}
