<?php
/**
 * Cover Editor Assets
 *
 * Handles enqueueing of scripts and styles for the cover editor.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoverEditorAssets {

	/**
	 * Cropper.js CDN URLs
	 */
	const CROPPER_JS_URL  = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js';
	const CROPPER_CSS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css';

	/**
	 * Initialize asset loading
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ), 25 );
	}

	/**
	 * Register assets (doesn't enqueue yet)
	 */
	public static function register_assets() {
		$version = defined( 'BD_VERSION' ) ? BD_VERSION : '1.2.0';

		// Cropper.js
		wp_register_style(
			'cropperjs',
			self::CROPPER_CSS_URL,
			array(),
			'1.6.1'
		);

		wp_register_script(
			'cropperjs',
			self::CROPPER_JS_URL,
			array(),
			'1.6.1',
			true
		);

		// Cover editor CSS
		wp_register_style(
			'bd-cover-editor',
			plugins_url( 'assets/css/cover-editor.css', dirname( __DIR__ ) ),
			array( 'cropperjs' ),
			$version
		);

		// Cover editor JS - depends on bd-lists which provides bdLists object
		wp_register_script(
			'bd-cover-editor',
			plugins_url( 'assets/js/cover-editor.js', dirname( __DIR__ ) ),
			array( 'jquery', 'cropperjs', 'bd-lists' ),
			$version,
			true
		);
	}

	/**
	 * Conditionally enqueue assets on list pages
	 */
	public static function maybe_enqueue_assets() {
		// Only load on single list pages or list browse pages
		if ( ! self::should_load_editor() ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * Check if we should load the editor
	 *
	 * @return bool
	 */
	private static function should_load_editor() {
		// Must be logged in to edit covers
		if ( ! is_user_logged_in() ) {
			return false;
		}

		global $post;

		// Check for query var first (works without $post)
		if ( get_query_var( 'bd_list' ) ) {
			return true;
		}

		// Need $post for shortcode checks
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		// Check for ANY list-related shortcodes
		$list_shortcodes = array(
			'bd_my_lists',
			'bd_list',
			'bd_single_list',
			'bd_public_lists',
		);

		foreach ( $list_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		// Allow filtering for custom implementations
		return apply_filters( 'bd_load_cover_editor', false );
	}

	/**
	 * Enqueue all cover editor assets
	 */
	public static function enqueue_assets() {
		wp_enqueue_style( 'bd-cover-editor' );
		wp_enqueue_script( 'bd-cover-editor' );
	}

	/**
	 * Manually enqueue from shortcode or template
	 * Call this from your shortcode if auto-detection doesn't work
	 */
	public static function enqueue() {
		self::enqueue_assets();
	}
}

// Initialize
CoverEditorAssets::init();
