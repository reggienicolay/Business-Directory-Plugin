<?php
/**
 * Image Helper — WebP-aware picture element builder
 *
 * Reads the WebP manifest stored by ImageOptimizer in `_bd_webp_sizes`
 * attachment meta and builds a `<picture>` element with a WebP `<source>`
 * and a JPEG/PNG `<img>` fallback. Browsers that support WebP load the
 * smaller file; older browsers fall through to the original.
 *
 * @package BusinessDirectory
 * @subpackage Media
 * @since 0.1.8
 */

namespace BD\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImageHelper
 */
class ImageHelper {

	/**
	 * Build a <picture> element with WebP source + <img> fallback.
	 *
	 * If no WebP variant exists for the requested size, falls back to a
	 * plain <img> tag — so this is always safe to call, even for images
	 * uploaded before the optimizer was installed.
	 *
	 * @param int    $attachment_id WP attachment post ID.
	 * @param string $size          WordPress image size (e.g. 'bd-card', 'bd-hero', 'medium').
	 * @param array  $attrs         Optional HTML attributes for the <img> tag.
	 *                              Common: 'alt', 'class', 'loading', 'width', 'height'.
	 *                              'loading' defaults to 'lazy' if not provided.
	 * @return string HTML string. Empty string if attachment doesn't exist.
	 */
	public static function picture( $attachment_id, $size = 'medium', $attrs = array() ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return '';
		}

		// Get the original (non-WebP) image URL for the requested size.
		$img_url = wp_get_attachment_image_url( $attachment_id, $size );
		if ( ! $img_url ) {
			return '';
		}

		// Default attributes.
		$defaults = array(
			'loading' => 'lazy',
			'alt'     => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
		$attrs = wp_parse_args( $attrs, $defaults );

		// Try to find a WebP variant.
		$webp_url = self::get_webp_url( $attachment_id, $size );

		// Build attribute string for the <img> tag.
		$attr_html = self::build_attr_string( $attrs );

		if ( $webp_url ) {
			return sprintf(
				'<picture><source srcset="%s" type="image/webp"><img src="%s"%s></picture>',
				esc_url( $webp_url ),
				esc_url( $img_url ),
				$attr_html
			);
		}

		// No WebP available — plain <img>.
		return sprintf( '<img src="%s"%s>', esc_url( $img_url ), $attr_html );
	}

	/**
	 * Get the WebP URL for an attachment at a given size.
	 *
	 * Reads the `_bd_webp_sizes` meta (relative paths stored by
	 * ImageOptimizer) and resolves to a full URL.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Image size name.
	 * @return string|null WebP URL or null if not available.
	 */
	public static function get_webp_url( $attachment_id, $size ) {
		$webp_sizes = get_post_meta( $attachment_id, '_bd_webp_sizes', true );

		if ( ! is_array( $webp_sizes ) || empty( $webp_sizes[ $size ] ) ) {
			return null;
		}

		$relative_path = $webp_sizes[ $size ];
		$upload_dir    = wp_get_upload_dir();

		// Relative paths are relative to the uploads base directory.
		$abs_path = trailingslashit( $upload_dir['basedir'] ) . $relative_path;

		if ( ! file_exists( $abs_path ) ) {
			return null;
		}

		return trailingslashit( $upload_dir['baseurl'] ) . $relative_path;
	}

	/**
	 * Build an HTML attribute string from an associative array.
	 *
	 * @param array $attrs Key-value pairs.
	 * @return string Attribute string with a leading space, or empty.
	 */
	private static function build_attr_string( $attrs ) {
		$parts = array();
		foreach ( $attrs as $key => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}
			$parts[] = sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
		return implode( '', $parts );
	}
}
