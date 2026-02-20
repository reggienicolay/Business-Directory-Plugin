<?php
/**
 * Tag Taxonomy Registration
 *
 * @package BusinessDirectory
 * @since   0.1.0
 */

namespace BD\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tag
 *
 * Registers the bd_tag taxonomy for business attributes.
 *
 * @since 0.1.0
 */
class Tag {

	/**
	 * Register the taxonomy.
	 *
	 * @since 0.1.0
	 * @since 0.1.8 Changed rewrite slug from 'tag' to 'places/tag'.
	 *
	 * @return void
	 */
	public static function register() {
		register_taxonomy(
			'bd_tag',
			'bd_business',
			array(
				'labels'            => array(
					'name'          => __( 'Tags', 'business-directory' ),
					'singular_name' => __( 'Tag', 'business-directory' ),
				),
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'places/tag',
					'with_front' => false,
				),
				'show_in_rest'      => true,
			)
		);
	}
}
