<?php
/**
 * List Cover Social Meta Tags
 *
 * Adds Open Graph and Twitter Card meta tags for lists with custom covers.
 * Ensures social shares display the cover image prominently.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Lists\ListManager;
use BD\Lists\CoverManager;

class ListSocialMeta {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_meta_tags' ), 5 );
		
		// Filter for Yoast SEO compatibility
		add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'filter_yoast_og_image' ), 10, 1 );
		add_filter( 'wpseo_twitter_image', array( __CLASS__, 'filter_yoast_og_image' ), 10, 1 );
		
		// Filter for Rank Math compatibility
		add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'filter_yoast_og_image' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( __CLASS__, 'filter_yoast_og_image' ) );
	}

	/**
	 * Output OG meta tags on single list pages
	 */
	public static function output_meta_tags() {
		$list = self::get_current_list();
		if ( ! $list ) {
			return;
		}

		$meta = self::build_meta_data( $list );
		if ( empty( $meta ) ) {
			return;
		}

		// Only output if no SEO plugin is handling it
		if ( self::seo_plugin_active() ) {
			return;
		}

		echo "\n<!-- Business Directory List Social Meta -->\n";

		// Open Graph
		if ( ! empty( $meta['og_image'] ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $meta['og_image'] ) );
			printf( '<meta property="og:image:width" content="%d" />' . "\n", esc_attr( $meta['og_image_width'] ) );
			printf( '<meta property="og:image:height" content="%d" />' . "\n", esc_attr( $meta['og_image_height'] ) );
			echo '<meta property="og:image:type" content="image/jpeg" />' . "\n";
		}

		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $meta['title'] ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $meta['description'] ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $meta['url'] ) );
		echo '<meta property="og:type" content="article" />' . "\n";

		// Twitter Card
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $meta['title'] ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $meta['description'] ) );

		if ( ! empty( $meta['og_image'] ) ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $meta['og_image'] ) );
		}

		echo "<!-- /Business Directory List Social Meta -->\n\n";
	}

	/**
	 * Get the current list being viewed
	 *
	 * @return array|null List data or null
	 */
	private static function get_current_list() {
		// Check query var first (for rewritten URLs)
		$list_slug = get_query_var( 'bd_list' );
		if ( $list_slug ) {
			return ListManager::get_list_by_slug( $list_slug );
		}

		// Check for list ID in URL
		$list_id = get_query_var( 'bd_list_id' );
		if ( $list_id ) {
			return ListManager::get_list( absint( $list_id ) );
		}

		// Check shortcode context
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'bd_single_list' ) ) {
			// Parse shortcode to get list ID
			if ( preg_match( '/\[bd_single_list[^\]]*id=["\']?(\d+)["\']?/', $post->post_content, $matches ) ) {
				return ListManager::get_list( absint( $matches[1] ) );
			}
		}

		return null;
	}

	/**
	 * Build meta data array for a list
	 *
	 * @param array $list List data.
	 * @return array Meta data.
	 */
	private static function build_meta_data( $list ) {
		$cover_type = $list['cover_type'] ?? 'auto';
		$og_image   = null;
		$width      = 1200;
		$height     = 630;

		// Get cover image URL
		if ( 'image' === $cover_type && ! empty( $list['cover_image_id'] ) ) {
			// Try to get social size (1200x630), fall back to large
			$og_image = wp_get_attachment_image_url( $list['cover_image_id'], 'bd-social' );
			if ( ! $og_image ) {
				$og_image = wp_get_attachment_image_url( $list['cover_image_id'], 'large' );
			}
		} elseif ( in_array( $cover_type, array( 'youtube', 'vimeo' ), true ) ) {
			// Use video thumbnail
			if ( ! empty( $list['cover_video_thumb_id'] ) ) {
				$og_image = wp_get_attachment_image_url( $list['cover_video_thumb_id'], 'large' );
			} else {
				$og_image = CoverManager::get_video_thumbnail_url( $cover_type, $list['cover_video_id'] );
			}
			$width  = 1280;
			$height = 720;
		} else {
			// Fall back to first business image
			$og_image = ListManager::get_list_cover_image( $list );
		}

		// Build description
		$description = ! empty( $list['description'] ) 
			? $list['description'] 
			: sprintf( 'A curated list of %d places in the Tri-Valley', $list['item_count'] ?? 0 );

		// Truncate description for social
		if ( strlen( $description ) > 200 ) {
			$description = substr( $description, 0, 197 ) . '...';
		}

		// Get author name
		$author = '';
		if ( ! empty( $list['user_id'] ) ) {
			$user = get_userdata( $list['user_id'] );
			if ( $user ) {
				$author = $user->display_name;
			}
		}

		$title = $list['title'];
		if ( $author ) {
			$title .= ' by ' . $author;
		}

		return array(
			'title'           => $title,
			'description'     => $description,
			'url'             => ListManager::get_list_url( $list ),
			'og_image'        => $og_image,
			'og_image_width'  => $width,
			'og_image_height' => $height,
		);
	}

	/**
	 * Check if a major SEO plugin is active
	 *
	 * @return bool
	 */
	private static function seo_plugin_active() {
		// Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			return true;
		}

		// Rank Math
		if ( class_exists( 'RankMath' ) ) {
			return true;
		}

		// All in One SEO
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter for Yoast/Rank Math OG image
	 *
	 * @param string $image Current image URL.
	 * @return string Filtered image URL.
	 */
	public static function filter_yoast_og_image( $image ) {
		$list = self::get_current_list();
		if ( ! $list ) {
			return $image;
		}

		$meta = self::build_meta_data( $list );
		if ( ! empty( $meta['og_image'] ) ) {
			return $meta['og_image'];
		}

		return $image;
	}
}

// Initialize
ListSocialMeta::init();
