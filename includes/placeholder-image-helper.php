<?php
/**
 * Placeholder Image Helper Functions
 *
 * Template helper functions for integrating placeholder images.
 *
 * @package BusinessDirectory
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the PlaceholderImage class.
$placeholder_class_file = plugin_dir_path( __FILE__ ) . '../src/Utils/PlaceholderImage.php';

if ( file_exists( $placeholder_class_file ) ) {
	require_once $placeholder_class_file;
}

/**
 * Get business hero image URL or placeholder.
 *
 * @param int|WP_Post|null $business Business post ID or object.
 * @param string           $size     Image size (default: 'full').
 * @return array {
 *     Image data array.
 *
 *     @type string $url            Image URL or data URI.
 *     @type bool   $is_placeholder Whether this is a placeholder image.
 *     @type string $type           Either 'image' or 'svg'.
 * }
 */
function bd_get_business_image( $business = null, $size = 'full' ) {
	if ( null === $business ) {
		$business = get_the_ID();
	}

	$business = get_post( $business );

	if ( ! $business ) {
		return array(
			'url'            => '',
			'is_placeholder' => true,
			'type'           => 'svg',
		);
	}

	// Check for featured image.
	$thumbnail_id = get_post_thumbnail_id( $business->ID );

	if ( $thumbnail_id ) {
		$image_url = wp_get_attachment_image_url( $thumbnail_id, $size );

		if ( $image_url ) {
			return array(
				'url'            => $image_url,
				'is_placeholder' => false,
				'type'           => 'image',
			);
		}
	}

	// Check for gallery photos.
	$gallery_ids = get_post_meta( $business->ID, 'bd_photos', true );

	if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
		// Sanitize gallery ID before use.
		$first_photo = absint( reset( $gallery_ids ) );

		if ( $first_photo ) {
			$image_url = wp_get_attachment_image_url( $first_photo, $size );

			if ( $image_url ) {
				return array(
					'url'            => $image_url,
					'is_placeholder' => false,
					'type'           => 'image',
				);
			}
		}
	}

	// No real image - generate placeholder.
	if ( ! class_exists( 'BusinessDirectory\Utils\PlaceholderImage' ) ) {
		return array(
			'url'            => '',
			'is_placeholder' => true,
			'type'           => 'svg',
		);
	}

	$data_uri = \BusinessDirectory\Utils\PlaceholderImage::get_data_uri( $business );

	return array(
		'url'            => $data_uri,
		'is_placeholder' => true,
		'type'           => 'svg',
	);
}

/**
 * Get inline SVG placeholder markup.
 *
 * @param int|WP_Post|null $business Business post ID or object.
 * @return string SVG markup.
 */
function bd_get_placeholder_svg( $business = null ) {
	if ( null === $business ) {
		$business = get_the_ID();
	}

	if ( ! class_exists( 'BusinessDirectory\Utils\PlaceholderImage' ) ) {
		return '';
	}

	return \BusinessDirectory\Utils\PlaceholderImage::generate_for_business( $business );
}

/**
 * Check if business has real photos.
 *
 * @param int|WP_Post|null $business Business post ID or object.
 * @return bool True if business has at least one real photo.
 */
function bd_has_photos( $business = null ) {
	if ( null === $business ) {
		$business = get_the_ID();
	}

	$business = get_post( $business );

	if ( ! $business ) {
		return false;
	}

	if ( has_post_thumbnail( $business->ID ) ) {
		return true;
	}

	$gallery_ids = get_post_meta( $business->ID, 'bd_photos', true );

	return ! empty( $gallery_ids ) && is_array( $gallery_ids );
}

/**
 * Get photo count for business.
 *
 * @param int|WP_Post|null $business Business post ID or object.
 * @return int Number of photos.
 */
function bd_get_photo_count( $business = null ) {
	if ( null === $business ) {
		$business = get_the_ID();
	}

	$business = get_post( $business );

	if ( ! $business ) {
		return 0;
	}

	$gallery_ids = get_post_meta( $business->ID, 'bd_photos', true );
	$featured_id = get_post_thumbnail_id( $business->ID );

	$all_ids = array();

	if ( $featured_id ) {
		$all_ids[] = $featured_id;
	}

	if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
		foreach ( $gallery_ids as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $id, $all_ids, true ) ) {
				$all_ids[] = $id;
			}
		}
	}

	return count( $all_ids );
}

/**
 * Register shortcode for testing placeholders.
 * Only available to logged-in users with edit_posts capability.
 */
function bd_placeholder_shortcode( $atts ) {
	// Security: Only allow for users who can edit posts.
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'id'       => 0,
			'name'     => 'Sample Business',
			'category' => 'default',
			'lat'      => 37.6819,
			'lng'      => -121.7680,
			'width'    => '300px',
			'height'   => '200px',
		),
		$atts,
		'bd_placeholder'
	);

	if ( ! class_exists( 'BusinessDirectory\Utils\PlaceholderImage' ) ) {
		return '<p style="color:red;background:#fee;padding:10px;">Error: PlaceholderImage class not found.</p>';
	}

	if ( ! empty( $atts['id'] ) ) {
		$svg = \BusinessDirectory\Utils\PlaceholderImage::generate_for_business( absint( $atts['id'] ) );
	} else {
		$svg = \BusinessDirectory\Utils\PlaceholderImage::generate(
			array(
				'name'     => sanitize_text_field( $atts['name'] ),
				'category' => sanitize_text_field( $atts['category'] ),
				'lat'      => floatval( $atts['lat'] ),
				'lng'      => floatval( $atts['lng'] ),
			)
		);
	}

	// Sanitize dimensions - only allow valid CSS values.
	$width  = preg_match( '/^\d+(px|%|em|rem|vw)?$/', $atts['width'] ) ? $atts['width'] : '300px';
	$height = preg_match( '/^\d+(px|%|em|rem|vh)?$/', $atts['height'] ) ? $atts['height'] : '200px';

	return '<div class="bd-placeholder-preview" style="width:' . esc_attr( $width ) . ';height:' . esc_attr( $height ) . ';border-radius:8px;overflow:hidden;">' . $svg . '</div>';
}
add_shortcode( 'bd_placeholder', 'bd_placeholder_shortcode' );

/**
 * Clear placeholder caches when a business is updated.
 *
 * @param int $post_id Post ID.
 */
function bd_clear_placeholder_cache_on_save( $post_id ) {
	// Skip autosaves and revisions.
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( 'bd_business' !== get_post_type( $post_id ) ) {
		return;
	}

	// Clear any transient cache if implemented.
	delete_transient( 'bd_placeholder_' . $post_id );

	// Clear API response caches that might contain this business.
	// Delete both value and timeout rows to avoid orphans.
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_bd_businesses_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_bd_businesses_' ) . '%'
		)
	);
}
add_action( 'save_post', 'bd_clear_placeholder_cache_on_save' );
