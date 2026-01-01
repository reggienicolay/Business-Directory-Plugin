<?php
/**
 * Feature Block - Gutenberg block for embedding businesses
 *
 * @package BusinessDirectory
 */


namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class FeatureBlock {

	/**
	 * Initialize the block
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the block
	 */
	public static function register_block() {
		register_block_type(
			'bd/feature',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
				'attributes'      => array(
					'ids'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'  => array(
						'type'    => 'string',
						'default' => 'card',
					),
					'columns' => array(
						'type'    => 'string',
						'default' => '3',
					),
					'ctaText' => array(
						'type'    => 'string',
						'default' => 'View Details',
					),
					'show'    => array(
						'type'    => 'string',
						'default' => 'image,title,rating,excerpt,category,cta',
					),
				),
			)
		);
	}

	/**
	 * Enqueue editor assets
	 */
	public static function enqueue_editor_assets() {
		// Block JavaScript
		wp_enqueue_script(
			'bd-feature-block',
			BD_PLUGIN_URL . 'assets/js/feature-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-api-fetch' ),
			BD_VERSION,
			true
		);

		// Check if cross-site source is configured
		$source_url    = get_option( 'bd_feature_source_url', '' );
		$is_cross_site = ! empty( $source_url );

		// Determine API URL (local or remote)
		if ( $is_cross_site ) {
			$api_url = 'https://' . ltrim( $source_url, 'https://' ) . '/wp-json/bd/v1/';
		} else {
			$api_url = rest_url( 'bd/v1/' );
		}

		// Pass data to JavaScript
		wp_localize_script(
			'bd-feature-block',
			'bdFeatureBlock',
			array(
				'apiUrl'       => $api_url,
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'    => BD_PLUGIN_URL,
				'previewNonce' => wp_create_nonce( 'bd_block_preview' ),
				'sourceUrl'    => $source_url,
				'isCrossSite'  => $is_cross_site,
			)
		);

		// Editor styles
		wp_enqueue_style(
			'bd-feature-block-editor',
			BD_PLUGIN_URL . 'assets/css/feature-block-editor.css',
			array( 'wp-edit-blocks' ),
			BD_VERSION
		);

		// Also load frontend styles for preview
		wp_enqueue_style(
			'bd-feature-embed',
			BD_PLUGIN_URL . 'assets/css/feature-embed.css',
			array(),
			BD_VERSION
		);
	}

	/**
	 * Render block on frontend (and in editor via ServerSideRender)
	 */
	public static function render_block( $attributes ) {
		$ids     = sanitize_text_field( $attributes['ids'] ?? '' );
		$layout  = sanitize_text_field( $attributes['layout'] ?? 'card' );
		$columns = sanitize_text_field( $attributes['columns'] ?? '3' );
		$cta     = sanitize_text_field( $attributes['ctaText'] ?? 'View Details' );
		$show    = sanitize_text_field( $attributes['show'] ?? 'image,title,rating,excerpt,category,cta' );

		if ( empty( $ids ) ) {
			// Show placeholder in editor
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div class="bd-feature-placeholder" style="padding: 40px; background: #f0f0f0; text-align: center; border-radius: 8px; color: #666;">
					<p style="margin: 0; font-size: 14px;">Select businesses from the sidebar to display them here.</p>
				</div>';
			}
			return '';
		}

		// Use the shortcode to render
		$shortcode = sprintf(
			'[bd_feature ids="%s" layout="%s" columns="%s" cta_text="%s" show="%s"]',
			esc_attr( $ids ),
			esc_attr( $layout ),
			esc_attr( $columns ),
			esc_attr( $cta ),
			esc_attr( $show )
		);

		return do_shortcode( $shortcode );
	}
}
