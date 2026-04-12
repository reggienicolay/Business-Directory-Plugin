<?php
/**
 * Global helper functions for WebP-aware image output.
 *
 * Thin wrappers around BD\Media\ImageHelper so templates can call
 * bd_picture() without a use-statement or full namespace path.
 *
 * Loaded via require_once in business-directory.php alongside the
 * other includes/*.php function files.
 *
 * @package BusinessDirectory
 * @since 0.1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output a <picture> element with WebP source + <img> fallback.
 *
 * @param int    $attachment_id WP attachment post ID.
 * @param string $size          WordPress image size (e.g. 'bd-card', 'bd-hero').
 * @param array  $attrs         Optional <img> attributes (alt, class, loading, width, height).
 * @return string HTML.
 */
function bd_picture( $attachment_id, $size = 'medium', $attrs = array() ) {
	return \BD\Media\ImageHelper::picture( $attachment_id, $size, $attrs );
}

/**
 * Output a <picture> element for a post's featured image.
 *
 * Convenience wrapper that resolves the thumbnail attachment ID from a
 * post ID, so templates don't need to call get_post_thumbnail_id() first.
 *
 * @param int    $post_id Post ID (any post type).
 * @param string $size    WordPress image size.
 * @param array  $attrs   Optional <img> attributes.
 * @return string HTML. Empty if no featured image.
 */
function bd_post_picture( $post_id, $size = 'medium', $attrs = array() ) {
	$attachment_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $attachment_id ) {
		return '';
	}
	return bd_picture( $attachment_id, $size, $attrs );
}
